<?php

declare(strict_types=1);

namespace StagasBites\Service;

use StagasBites\Entity\Inquiry;
use StagasBites\Entity\Order;
use StagasBites\Entity\OrderStatus;
use StagasBites\Entity\User;
use StagasBites\Helper\Str;

/** Composes the transactional emails. Every interpolated value goes through Str::e(). */
final class MailService
{
    public function __construct(private readonly ZeptoMailService $zepto, private readonly string $siteUrl)
    {
    }

    public function sendWelcome(User $user): void
    {
        $body = '<p>Hi ' . Str::e($user->getFirstName()) . ',</p>'
            . "<p>Welcome to Staga's Bites! Your account is ready — you can now track your orders and re-order your favourites in a couple of taps.</p>"
            . $this->button('Browse the menu', $this->siteUrl . '/menu');

        $this->zepto->send($user->getEmail(), $user->getFullName(), "Welcome to Staga's Bites", $this->layout('Welcome to the table', $body));
    }

    public function sendPasswordReset(User $user, string $plainToken): void
    {
        $link = $this->siteUrl . '/account/reset-password?token=' . urlencode($plainToken);
        $body = '<p>Hi ' . Str::e($user->getFirstName()) . ',</p>'
            . '<p>We received a request to reset your password. This link is valid for one hour.</p>'
            . $this->button('Reset my password', $link)
            . "<p style=\"color:#8a7d70;font-size:13px\">If you didn't ask for this, you can safely ignore this email.</p>";

        $this->zepto->send($user->getEmail(), $user->getFullName(), "Reset your Staga's Bites password", $this->layout('Reset your password', $body));
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $when = $order->getFulfilmentDate()->format('l, F j, Y') . ' · ' . $order->getFulfilmentTimeSlot();
        $how = $order->getFulfilmentMethod() === 'delivery'
            ? 'Delivery to ' . Str::e($order->getDeliveryAddress())
            : 'Pickup in Oakville (we will text you the exact address)';

        $body = '<p>Hi ' . Str::e($order->getCustomerFirstName()) . ',</p>'
            . '<p>Thank you — your payment went through and your order is confirmed. We cook everything fresh for your slot.</p>'
            . '<p><strong>' . Str::e($when) . '</strong><br>' . $how . '</p>'
            . $this->orderTable($order)
            // The order UUID is an unguessable capability, so guests can open the page without signing in.
            . $this->button('View your order', $this->siteUrl . '/order/' . urlencode($order->getId()));

        $this->zepto->send(
            $order->getCustomerEmail(),
            $order->getCustomerName(),
            'Order ' . $order->getOrderNumber() . ' confirmed',
            $this->layout('Your order is confirmed', $body),
        );
    }

    public function sendNewOrderAlert(Order $order): void
    {
        $data = $order->toArray();
        $body = '<p>New paid order <strong>' . Str::e($order->getOrderNumber()) . '</strong> from '
            . Str::e($order->getCustomerName()) . ' (' . Str::e($data['customer_phone']) . ', ' . Str::e($order->getCustomerEmail()) . ').</p>'
            . '<p><strong>' . Str::e(ucfirst($order->getFulfilmentMethod())) . ':</strong> '
            . Str::e($order->getFulfilmentDate()->format('D, M j')) . ' · ' . Str::e($order->getFulfilmentTimeSlot())
            . ($order->getDeliveryAddress() !== null ? '<br>' . Str::e($order->getDeliveryAddress()) : '') . '</p>'
            . ($order->getNotes() !== null ? '<p><strong>Notes:</strong> ' . nl2br(Str::e($order->getNotes())) . '</p>' : '')
            . $this->orderTable($order)
            . $this->button('Open in admin', $this->siteUrl . '/admin/orders/' . urlencode($order->getId()));

        $this->zepto->send(
            $this->zepto->adminEmail(),
            "Staga's Bites",
            'New order ' . $order->getOrderNumber() . ' — ' . Str::money($order->getTotal(), $order->getCurrency()),
            $this->layout('New order received', $body),
            $order->getCustomerEmail(),
        );
    }

    public function sendOrderStatusUpdate(Order $order): void
    {
        $message = match ($order->getStatus()) {
            OrderStatus::PREPARING => "We've started preparing your order.",
            OrderStatus::READY => $order->getFulfilmentMethod() === 'delivery'
                ? 'Your order is packed and on its way to you.'
                : 'Your order is ready for pickup.',
            OrderStatus::COMPLETED => 'Your order is complete. Thank you for choosing us — we hope every bite was perfect.',
            OrderStatus::CANCELLED => 'Your order has been cancelled. If you were charged, a refund is on its way. Reply to this email with any questions.',
            OrderStatus::REFUNDED => 'Your order has been refunded. It can take 5–10 business days to appear on your statement.',
            default => null,
        };
        if ($message === null) {
            return;
        }

        $body = '<p>Hi ' . Str::e($order->getCustomerFirstName()) . ',</p><p>' . Str::e($message) . '</p>'
            . '<p style="color:#8a7d70">Order ' . Str::e($order->getOrderNumber()) . '</p>';

        $this->zepto->send(
            $order->getCustomerEmail(),
            $order->getCustomerName(),
            'Order ' . $order->getOrderNumber() . ': ' . $order->getStatus()->label(),
            $this->layout($order->getStatus()->label(), $body),
        );
    }

    public function sendInquiryAlert(Inquiry $inquiry): void
    {
        $data = $inquiry->toArray();
        $rows = '';
        foreach (['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'event_type' => 'Event', 'event_date' => 'Date', 'guest_count' => 'Guests'] as $key => $label) {
            if (!empty($data[$key])) {
                $rows .= '<tr><td style="padding:4px 12px 4px 0;color:#8a7d70">' . $label . '</td><td>' . Str::e((string) $data[$key]) . '</td></tr>';
            }
        }
        $body = '<table role="presentation" style="font-size:15px">' . $rows . '</table>'
            . '<p style="white-space:pre-line">' . Str::e($data['message']) . '</p>';
        $title = $data['type'] === Inquiry::TYPE_CATERING ? 'New catering request' : 'New contact message';

        $this->zepto->send($this->zepto->adminEmail(), "Staga's Bites", $title . ' from ' . $data['name'], $this->layout($title, $body), $data['email']);
    }

    public function sendInquiryReceipt(Inquiry $inquiry): void
    {
        $data = $inquiry->toArray();
        $body = '<p>Hi ' . Str::e($data['name']) . ',</p>'
            . "<p>Thanks for reaching out — we've received your message and will get back to you within one business day.</p>";

        $this->zepto->send($data['email'], $data['name'], "We've got your message", $this->layout('Thanks for getting in touch', $body));
    }

    private function orderTable(Order $order): string
    {
        $currency = $order->getCurrency();
        $rows = '';
        foreach ($order->getItems() as $item) {
            $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee2cf">' . Str::e($item->getName())
                . '<br><span style="color:#8a7d70;font-size:13px">' . Str::e($item->getOptionLabel()) . ' × ' . $item->getQuantity() . '</span></td>'
                . '<td style="padding:8px 0;border-bottom:1px solid #eee2cf;text-align:right;white-space:nowrap">' . Str::money($item->getLineTotal(), $currency) . '</td></tr>';
        }
        $line = static fn (string $label, string $value, bool $bold = false): string => '<tr><td style="padding:4px 0' . ($bold ? ';font-weight:bold;font-size:17px' : ';color:#8a7d70') . '">' . $label
            . '</td><td style="padding:4px 0;text-align:right' . ($bold ? ';font-weight:bold;font-size:17px' : '') . '">' . $value . '</td></tr>';

        $totals = $line('Subtotal', Str::money($order->getSubtotal(), $currency));
        if ($order->getDiscount() > 0) {
            $totals .= $line('Discount', '−' . Str::money($order->getDiscount(), $currency));
        }
        if ($order->getDeliveryFee() > 0) {
            $totals .= $line('Delivery', Str::money($order->getDeliveryFee(), $currency));
        }
        $totals .= $line('Tax', Str::money($order->getTax(), $currency));
        $totals .= $line('Total', Str::money($order->getTotal(), $currency), true);

        return '<table role="presentation" width="100%" style="border-collapse:collapse;font-size:15px;margin:18px 0">' . $rows . $totals . '</table>';
    }

    private function button(string $label, string $url): string
    {
        return '<p style="margin:26px 0"><a href="' . Str::e($url) . '" style="background:#c9302c;color:#fff;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:600;display:inline-block">'
            . Str::e($label) . '</a></p>';
    }

    private function layout(string $heading, string $body): string
    {
        return '<!doctype html><html><body style="margin:0;background:#f7eedf;font-family:Helvetica,Arial,sans-serif;color:#2e231d">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">'
            . '<table role="presentation" width="100%" style="max-width:580px;background:#fffaf2;border-radius:16px;overflow:hidden">'
            . '<tr><td style="background:#17110e;padding:22px 32px;color:#f7eedf;font-family:Georgia,serif;font-size:22px">Staga\'s <span style="color:#f0b24a">Bites</span></td></tr>'
            . '<tr><td style="padding:32px;font-size:16px;line-height:1.6"><h1 style="font-family:Georgia,serif;font-weight:normal;font-size:26px;margin:0 0 18px">' . Str::e($heading) . '</h1>' . $body . '</td></tr>'
            . '<tr><td style="padding:20px 32px;background:#f3e7d3;font-size:13px;color:#8a7d70">Staga\'s Bites · Oakville, Ontario · +1 (647) 673-8796 · contact@stagasbites.ca</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
