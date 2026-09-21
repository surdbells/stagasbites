<?php

declare(strict_types=1);

namespace StagasBites\Service;

use StagasBites\Entity\Order;
use StagasBites\Exception\ApiException;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/** Hosted Stripe Checkout: card data never touches this server. */
final class StripeService
{
    private ?StripeClient $client = null;

    /**
     * @param array{secret_key: string, webhook_secret: string} $config
     */
    public function __construct(private readonly array $config, private readonly string $siteUrl)
    {
    }

    public function createCheckoutSession(Order $order): Session
    {
        $currency = strtolower($order->getCurrency());
        $line = static fn (string $name, int $amount, int $quantity = 1, ?string $description = null): array => [
            'quantity' => $quantity,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => $amount,
                'product_data' => array_filter(['name' => $name, 'description' => $description]),
            ],
        ];

        $lineItems = [];
        foreach ($order->getItems() as $item) {
            $lineItems[] = $line($item->getName(), $item->getUnitPrice(), $item->getQuantity(), $item->getOptionLabel());
        }
        if ($order->getDeliveryFee() > 0) {
            $lineItems[] = $line('Delivery', $order->getDeliveryFee());
        }
        if ($order->getTax() > 0) {
            $lineItems[] = $line('Tax', $order->getTax());
        }

        $params = [
            'mode' => 'payment',
            'line_items' => $lineItems,
            'customer_email' => $order->getCustomerEmail(),
            'client_reference_id' => $order->getId(),
            'metadata' => ['order_id' => $order->getId(), 'order_number' => $order->getOrderNumber()],
            'payment_intent_data' => [
                'description' => "Staga's Bites order " . $order->getOrderNumber(),
                'metadata' => ['order_id' => $order->getId(), 'order_number' => $order->getOrderNumber()],
            ],
            'success_url' => $this->siteUrl . '/order/' . $order->getId() . '?placed=1',
            'cancel_url' => $this->siteUrl . '/checkout?cancelled=1',
            'expires_at' => time() + 3600,
        ];

        // Checkout has no negative line items, so a discount becomes a single-use amount-off coupon.
        if ($order->getDiscount() > 0) {
            $coupon = $this->client()->coupons->create([
                'amount_off' => $order->getDiscount(),
                'currency' => $currency,
                'duration' => 'once',
                'max_redemptions' => 1,
                'name' => 'Discount ' . ($order->getCouponCode() ?? ''),
            ]);
            $params['discounts'] = [['coupon' => $coupon->id]];
        }

        return $this->client()->checkout->sessions->create($params, [
            'idempotency_key' => 'checkout-' . $order->getId(),
        ]);
    }

    /**
     * Verifies the signature against the raw payload. Fails closed when the secret is not configured.
     */
    public function constructEvent(string $payload, string $signature): Event
    {
        if ($this->config['webhook_secret'] === '') {
            throw ApiException::unavailable('Webhook secret not configured.');
        }

        try {
            return Webhook::constructEvent($payload, $signature, $this->config['webhook_secret']);
        } catch (SignatureVerificationException | \UnexpectedValueException) {
            throw ApiException::unauthorized('Invalid webhook signature.');
        }
    }

    private function client(): StripeClient
    {
        if ($this->config['secret_key'] === '') {
            throw ApiException::unavailable('Online payment is not configured yet. Please call us to place your order.');
        }

        return $this->client ??= new StripeClient($this->config['secret_key']);
    }
}
