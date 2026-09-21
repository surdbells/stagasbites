<?php

declare(strict_types=1);

namespace StagasBites\Module\Checkout;

use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use StagasBites\Entity\Order;
use StagasBites\Exception\ApiException;
use StagasBites\Helper\JsonResponse;
use StagasBites\Repository\OrderRepository;
use StagasBites\Service\CheckoutService;
use StagasBites\Service\StripeService;
use StagasBites\Service\ValidationService;
use Stripe\Checkout\Session;
use Symfony\Component\Validator\Constraints as Assert;

final class CheckoutController
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly StripeService $stripe,
        private readonly OrderRepository $orders,
        private readonly ValidationService $validator,
        private readonly Connection $db,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function quote(Request $request, Response $response): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'items' => self::itemRules(),
            'method' => [new Assert\NotBlank(), new Assert\Choice(['pickup', 'delivery'])],
            'coupon_code' => [new Assert\Length(max: 40)],
        ]);

        return JsonResponse::success($response, $this->checkout->quote($data['items'], $data['method'], $data['coupon_code']));
    }

    public function place(Request $request, Response $response): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'items' => self::itemRules(),
            'customer' => [new Assert\NotBlank(), new Assert\Collection(fields: [
                'first_name' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
                'last_name' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
                'email' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
                'phone' => [new Assert\NotBlank(), new Assert\Length(min: 7, max: 30)],
            ], allowExtraFields: true)],
            'fulfilment' => [new Assert\NotBlank(), new Assert\Collection(fields: [
                'method' => [new Assert\NotBlank(), new Assert\Choice(['pickup', 'delivery'])],
                'date' => [new Assert\NotBlank(), new Assert\Date()],
                'time_slot' => [new Assert\NotBlank(), new Assert\Length(max: 40)],
            ], allowExtraFields: true)],
            'notes' => [new Assert\Length(max: 2000)],
            'coupon_code' => [new Assert\Length(max: 40)],
        ]);

        $userId = $request->getAttribute('user_id');

        return JsonResponse::success($response, $this->checkout->placeOrder($data, is_string($userId) ? $userId : null), 201);
    }

    /**
     * Public order page. The UUID is the capability: it is only ever shown to the buyer
     * (Stripe redirect + confirmation email), so no sign-in is required.
     */
    public function show(Request $request, Response $response, string $id): Response
    {
        $order = preg_match('/^[0-9a-f-]{36}$/i', $id) === 1 ? $this->orders->find($id) : null;
        if (!$order instanceof Order) {
            throw ApiException::notFound('We could not find that order.');
        }

        return JsonResponse::success($response, $order->toArray())->withHeader('Cache-Control', 'no-store');
    }

    public function webhook(Request $request, Response $response): Response
    {
        $payload = (string) $request->getBody();
        $event = $this->stripe->constructEvent($payload, $request->getHeaderLine('Stripe-Signature'));

        // Idempotency: Stripe retries deliveries, so each event id is processed at most once.
        $fresh = $this->db->executeStatement(
            'INSERT INTO stripe_events (id, type, received_at) VALUES (?, ?, NOW()) ON CONFLICT (id) DO NOTHING',
            [$event->id, $event->type],
        );
        if ($fresh === 0) {
            return JsonResponse::success($response, ['duplicate' => true]);
        }

        try {
            $object = $event->data->object;
            if ($object instanceof Session) {
                match ($event->type) {
                    'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->checkout->handleSessionCompleted($object),
                    'checkout.session.expired', 'checkout.session.async_payment_failed' => $this->checkout->handleSessionExpired($object),
                    default => null,
                };
            }
        } catch (\Throwable $e) {
            // Forget the event so Stripe's retry gets another chance, then surface a 5xx.
            $this->db->executeStatement('DELETE FROM stripe_events WHERE id = ?', [$event->id]);
            $this->logger->error('Stripe webhook handling failed.', ['event' => $event->id, 'error' => $e->getMessage()]);
            throw $e;
        }

        return JsonResponse::success($response, ['received' => true]);
    }

    /**
     * @return list<\Symfony\Component\Validator\Constraint>
     */
    private static function itemRules(): array
    {
        return [
            new Assert\NotBlank(message: 'Your cart is empty.'),
            new Assert\Count(max: 60),
            new Assert\All([new Assert\Collection(fields: [
                'product_id' => [new Assert\NotBlank(), new Assert\Uuid(strict: false)],
                'option_id' => [new Assert\NotBlank(), new Assert\Uuid(strict: false)],
                'quantity' => [new Assert\NotBlank(), new Assert\Type('integer'), new Assert\Positive()],
            ], allowExtraFields: true)]),
        ];
    }
}
