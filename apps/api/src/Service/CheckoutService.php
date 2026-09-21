<?php

declare(strict_types=1);

namespace StagasBites\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use StagasBites\Entity\Coupon;
use StagasBites\Entity\Order;
use StagasBites\Entity\OrderItem;
use StagasBites\Entity\OrderStatus;
use StagasBites\Exception\ApiException;
use StagasBites\Repository\CouponRepository;
use StagasBites\Repository\OrderRepository;
use StagasBites\Repository\ProductRepository;
use Stripe\Checkout\Session;

/**
 * Prices are always recomputed server-side from the catalogue; nothing monetary
 * sent by the browser is trusted.
 */
final class CheckoutService
{
    private const ORDER_NUMBER_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const MAX_LINE_QUANTITY = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly OrderRepository $orders,
        private readonly CouponRepository $coupons,
        private readonly SettingsService $settings,
        private readonly StripeService $stripe,
        private readonly MailService $mail,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Prices a cart without creating anything. Used by the checkout page for live totals.
     *
     * @param list<array{product_id: string, option_id: string, quantity: int}> $items
     *
     * @return array<string, mixed>
     */
    public function quote(array $items, string $method, ?string $couponCode): array
    {
        $priced = $this->price($items, $method, $couponCode);
        unset($priced['lines'], $priced['coupon']);

        return $priced;
    }

    /**
     * @param array<string, mixed> $data validated checkout payload
     *
     * @return array{order_id: string, order_number: string, checkout_url: string}
     */
    public function placeOrder(array $data, ?string $userId): array
    {
        $fulfilment = $data['fulfilment'];
        $method = $fulfilment['method'];
        $priced = $this->price($data['items'], $method, $data['coupon_code'] ?? null);
        $date = $this->assertFulfilmentDate((string) $fulfilment['date'], $priced['max_lead_hours']);

        $slots = (array) $this->settings->get('time_slots');
        if (!in_array($fulfilment['time_slot'], $slots, true)) {
            throw ApiException::validation('Please check the highlighted fields.', ['fulfilment.time_slot' => ['Please choose one of the available time slots.']]);
        }

        $address = null;
        if ($method === 'delivery') {
            $address = $this->assertDeliveryAddress($fulfilment);
        }

        $customer = $data['customer'];
        $order = new Order($this->generateOrderNumber(), $date);
        $order->setUserId($userId);
        $order->setCustomer($customer['first_name'], $customer['last_name'], $customer['email'], $customer['phone']);
        $order->setFulfilment($method, $fulfilment['time_slot'], $address);
        $order->setNotes($data['notes'] ?? null);
        $order->setCouponCode($priced['coupon']?->getCode());
        foreach ($priced['lines'] as $line) {
            $order->addItem(new OrderItem($order, $line['product'], $line['option'], $line['quantity']));
        }
        $order->setTotals($priced['subtotal'], $priced['discount'], $priced['delivery_fee'], $priced['tax'], $priced['currency']);
        $this->orders->save($order);

        try {
            $session = $this->stripe->createCheckoutSession($order);
        } catch (ApiException $e) {
            $this->orders->remove($order);
            throw $e;
        } catch (\Throwable $e) {
            $this->orders->remove($order);
            $this->logger->error('Stripe session creation failed.', ['error' => $e->getMessage()]);
            throw new ApiException('We could not start the payment. Please try again.', 502);
        }

        $order->setStripeSessionId($session->id);
        $this->orders->save($order);

        return ['order_id' => $order->getId(), 'order_number' => $order->getOrderNumber(), 'checkout_url' => (string) $session->url];
    }

    /**
     * Marks the order paid exactly once. Safe against webhook retries and concurrent deliveries.
     */
    public function handleSessionCompleted(Session $session): void
    {
        if ($session->payment_status !== 'paid') {
            return; // async payment methods complete later via async_payment_succeeded
        }

        $paidOrder = $this->em->wrapInTransaction(function () use ($session): ?Order {
            $order = $this->orders->findByStripeSession($session->id);
            if ($order === null) {
                $this->logger->warning('Stripe session has no matching order.', ['session' => $session->id]);

                return null;
            }
            $this->em->lock($order, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($order);

            if ($order->getStatus() !== OrderStatus::PENDING_PAYMENT) {
                return null;
            }
            if ((int) $session->amount_total !== $order->getTotal()) {
                $this->logger->critical('Stripe amount does not match order total.', [
                    'order' => $order->getOrderNumber(), 'expected' => $order->getTotal(), 'received' => $session->amount_total,
                ]);

                return null;
            }

            $order->markPaid(is_string($session->payment_intent) ? $session->payment_intent : null);
            if ($order->getCouponCode() !== null) {
                $this->coupons->findByCode($order->getCouponCode())?->recordRedemption();
            }

            return $order;
        });

        if ($paidOrder instanceof Order) {
            $this->mail->sendOrderConfirmation($paidOrder);
            $this->mail->sendNewOrderAlert($paidOrder);
        }
    }

    public function handleSessionExpired(Session $session): void
    {
        $order = $this->orders->findByStripeSession($session->id);
        if ($order !== null && $order->getStatus() === OrderStatus::PENDING_PAYMENT) {
            $order->setStatus(OrderStatus::CANCELLED);
            $this->orders->save($order);
        }
    }

    /**
     * @param list<array{product_id: string, option_id: string, quantity: int}> $items
     *
     * @return array{lines: list<array<string, mixed>>, coupon: ?Coupon, subtotal: int, discount: int, delivery_fee: int, tax: int, total: int, currency: string, tax_label: string, max_lead_hours: int, coupon_message: ?string}
     */
    private function price(array $items, string $method, ?string $couponCode): array
    {
        if ($items === []) {
            throw ApiException::validation('Your cart is empty.');
        }

        $lines = [];
        $subtotal = 0;
        $maxLead = (int) $this->settings->get('min_lead_hours');
        foreach ($items as $index => $item) {
            $product = $this->products->find((string) ($item['product_id'] ?? ''));
            $option = $product?->findOption((string) ($item['option_id'] ?? ''));
            if ($product === null || $option === null || !$product->isAvailable()) {
                throw ApiException::validation('An item in your cart is no longer available. Please review your cart.', ["items.{$index}" => ['No longer available.']]);
            }
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < $product->getMinQuantity() || $quantity > self::MAX_LINE_QUANTITY) {
                throw ApiException::validation("Minimum order for {$product->getName()} is {$product->getMinQuantity()}.", ["items.{$index}" => ['Invalid quantity.']]);
            }
            $lines[] = ['product' => $product, 'option' => $option, 'quantity' => $quantity];
            $subtotal += $option->getPrice() * $quantity;
            $maxLead = max($maxLead, $product->getLeadTimeHours());
        }

        $coupon = null;
        $couponMessage = null;
        $discount = 0;
        if ($couponCode !== null && trim($couponCode) !== '') {
            $coupon = $this->coupons->findByCode($couponCode);
            if ($coupon === null || !$coupon->isRedeemable()) {
                $coupon = null;
                $couponMessage = 'This code is not valid.';
            } elseif ($subtotal < $coupon->getMinSubtotal()) {
                $couponMessage = 'Spend a little more to use this code.';
                $coupon = null;
            } else {
                $discount = $coupon->discountFor($subtotal);
            }
        }

        $deliveryFee = 0;
        if ($method === 'delivery') {
            $threshold = $this->settings->get('free_delivery_threshold');
            $deliveryFee = $threshold !== null && ($subtotal - $discount) >= (int) $threshold ? 0 : (int) $this->settings->get('delivery_fee');
        }

        $tax = (int) round(($subtotal - $discount + $deliveryFee) * (float) $this->settings->get('tax_rate'));

        return [
            'lines' => $lines,
            'coupon' => $coupon,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'tax' => $tax,
            'total' => $subtotal - $discount + $deliveryFee + $tax,
            'currency' => (string) $this->settings->get('currency'),
            'tax_label' => (string) $this->settings->get('tax_label'),
            'max_lead_hours' => $maxLead,
            'coupon_message' => $couponMessage,
        ];
    }

    private function assertFulfilmentDate(string $value, int $leadHours): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $error = null;
        if ($date === false) {
            $error = 'Please choose a valid date.';
        } elseif (!in_array((int) $date->format('N'), (array) $this->settings->get('pickup_days'), true)) {
            $error = 'We are not taking orders for that day. Please pick another date.';
        } elseif ($date->setTime(23, 59) < new \DateTimeImmutable("+{$leadHours} hours")) {
            $error = "We need at least {$leadHours} hours to prepare this order. Please pick a later date.";
        } elseif ($date > new \DateTimeImmutable('+90 days')) {
            $error = 'Please choose a date within the next 90 days.';
        }
        if ($error !== null || $date === false) {
            throw ApiException::validation('Please check the highlighted fields.', ['fulfilment.date' => [$error ?? 'Invalid date.']]);
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $fulfilment
     */
    private function assertDeliveryAddress(array $fulfilment): string
    {
        $errors = [];
        foreach (['address_line1' => 'Street address', 'city' => 'City', 'postal_code' => 'Postal code'] as $field => $label) {
            if (trim((string) ($fulfilment[$field] ?? '')) === '') {
                $errors["fulfilment.{$field}"] = ["{$label} is required for delivery."];
            }
        }
        $areas = array_map('mb_strtolower', (array) $this->settings->get('delivery_areas'));
        if (!isset($errors['fulfilment.city']) && $areas !== [] && !in_array(mb_strtolower(trim((string) $fulfilment['city'])), $areas, true)) {
            $errors['fulfilment.city'] = ['Sorry, we do not deliver there yet. Choose pickup or contact us for a quote.'];
        }
        if ($errors !== []) {
            throw ApiException::validation('Please check the highlighted fields.', $errors);
        }

        return implode(', ', array_filter([
            trim((string) $fulfilment['address_line1']),
            trim((string) ($fulfilment['address_line2'] ?? '')),
            trim((string) $fulfilment['city']),
            strtoupper(trim((string) $fulfilment['postal_code'])),
        ]));
    }

    private function generateOrderNumber(): string
    {
        $max = strlen(self::ORDER_NUMBER_ALPHABET) - 1;
        do {
            $code = 'SB-';
            for ($i = 0; $i < 6; ++$i) {
                $code .= self::ORDER_NUMBER_ALPHABET[random_int(0, $max)];
            }
        } while ($this->orders->findByNumber($code) !== null);

        return $code;
    }
}
