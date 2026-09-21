<?php

declare(strict_types=1);

namespace StagasBites\Module\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StagasBites\Entity\Coupon;
use StagasBites\Entity\Inquiry;
use StagasBites\Entity\Order;
use StagasBites\Entity\OrderStatus;
use StagasBites\Exception\ApiException;
use StagasBites\Helper\JsonResponse;
use StagasBites\Repository\CouponRepository;
use StagasBites\Repository\InquiryRepository;
use StagasBites\Repository\OrderRepository;
use StagasBites\Service\MailService;
use StagasBites\Service\SettingsService;
use StagasBites\Service\ValidationService;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminOrderController
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly InquiryRepository $inquiries,
        private readonly CouponRepository $coupons,
        private readonly SettingsService $settings,
        private readonly MailService $mail,
        private readonly ValidationService $validator,
    ) {
    }

    public function dashboard(Request $request, Response $response): Response
    {
        $stats = $this->orders->dashboardStats();
        $stats['upcoming'] = array_map(static fn (Order $o): array => $o->toArray(), $stats['upcoming']);
        $stats['new_inquiries'] = count($this->inquiries->findBy(['status' => 'new']));

        return JsonResponse::success($response, $stats);
    }

    public function listOrders(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $page = max(1, (int) ($q['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($q['per_page'] ?? 25)));
        $result = $this->orders->search([
            'status' => OrderStatus::tryFrom((string) ($q['status'] ?? ''))?->value ?? '',
            'search' => (string) ($q['search'] ?? ''),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($q['date'] ?? '')) === 1 ? (string) $q['date'] : '',
        ], $page, $perPage);

        return JsonResponse::paginated($response, array_map(static fn (Order $o): array => $o->toArray(), $result['items']), $result['total'], $page, $perPage);
    }

    public function showOrder(Request $request, Response $response, string $id): Response
    {
        return JsonResponse::success($response, $this->orders->findOrFail($id)->toArray());
    }

    public function updateOrderStatus(Request $request, Response $response, string $id): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'status' => [new Assert\NotBlank(), new Assert\Type('string')],
            'notify' => [new Assert\Type('bool')],
        ]);
        $status = OrderStatus::tryFrom($data['status']);
        if ($status === null || !$status->isManuallySettable()) {
            throw ApiException::validation('That status cannot be set manually.');
        }

        $order = $this->orders->findOrFail($id);
        if ($order->getStatus() === OrderStatus::PENDING_PAYMENT && $status !== OrderStatus::CANCELLED) {
            throw ApiException::conflict('This order has not been paid yet.');
        }
        $order->setStatus($status);
        $this->orders->save($order);

        if ($data['notify'] ?? true) {
            $this->mail->sendOrderStatusUpdate($order);
        }

        return JsonResponse::success($response, $order->toArray());
    }

    public function listInquiries(Request $request, Response $response): Response
    {
        $all = $this->inquiries->findBy([], ['createdAt' => 'DESC']);

        return JsonResponse::success($response, array_map(static fn (Inquiry $i): array => $i->toArray(), array_slice($all, 0, 200)));
    }

    public function updateInquiry(Request $request, Response $response, string $id): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'status' => [new Assert\NotBlank(), new Assert\Choice(['new', 'in_progress', 'closed'])],
        ]);
        $inquiry = $this->inquiries->findOrFail($id);
        $inquiry->setStatus($data['status']);
        $this->inquiries->save($inquiry);

        return JsonResponse::success($response, $inquiry->toArray());
    }

    public function listCoupons(Request $request, Response $response): Response
    {
        $all = $this->coupons->findBy([], ['createdAt' => 'DESC']);

        return JsonResponse::success($response, array_map(static fn (Coupon $c): array => $c->toArray(), $all));
    }

    public function saveCoupon(Request $request, Response $response, ?string $id = null): Response
    {
        $body = (array) $request->getParsedBody();
        $this->validator->validate($body, [
            'code' => [new Assert\NotBlank(), new Assert\Length(max: 40), new Assert\Regex('/^[A-Za-z0-9_-]+$/', 'Letters, numbers, dashes and underscores only.')],
            'type' => [new Assert\NotBlank(), new Assert\Choice([Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED])],
            'value' => [new Assert\NotBlank(), new Assert\Type('integer'), new Assert\Positive()],
        ]);

        $coupon = $id === null ? new Coupon() : $this->coupons->findOrFail($id);
        $clash = $this->coupons->findByCode((string) $body['code']);
        if ($clash !== null && $clash->getId() !== $coupon->getId()) {
            throw ApiException::validation('Please check the highlighted fields.', ['code' => ['This code already exists.']]);
        }
        $coupon->fill($body);
        $this->coupons->save($coupon);

        return JsonResponse::success($response, $coupon->toArray(), $id === null ? 201 : 200);
    }

    public function deleteCoupon(Request $request, Response $response, string $id): Response
    {
        $this->coupons->remove($this->coupons->findOrFail($id));

        return JsonResponse::success($response, null, 200, 'Coupon deleted.');
    }

    public function updateSettings(Request $request, Response $response): Response
    {
        return JsonResponse::success($response, $this->settings->update((array) $request->getParsedBody()));
    }
}
