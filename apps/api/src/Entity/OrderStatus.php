<?php

declare(strict_types=1);

namespace StagasBites\Entity;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Awaiting payment',
            self::PAID => 'Confirmed',
            self::PREPARING => 'Being prepared',
            self::READY => 'Ready',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }

    /** Statuses an admin may set by hand; payment states are driven by Stripe. */
    public function isManuallySettable(): bool
    {
        return in_array($this, [self::PREPARING, self::READY, self::COMPLETED, self::CANCELLED], true);
    }
}
