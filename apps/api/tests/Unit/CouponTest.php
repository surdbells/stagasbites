<?php

declare(strict_types=1);

namespace StagasBites\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StagasBites\Entity\Coupon;

final class CouponTest extends TestCase
{
    public function testPercentDiscountIsRoundedToTheCent(): void
    {
        $coupon = new Coupon();
        $coupon->fill(['code' => 'ten', 'type' => 'percent', 'value' => 10]);

        self::assertSame('TEN', $coupon->getCode());
        self::assertSame(1235, $coupon->discountFor(12345));
    }

    public function testFixedDiscountNeverExceedsTheSubtotal(): void
    {
        $coupon = new Coupon();
        $coupon->fill(['code' => 'BIG', 'type' => 'fixed', 'value' => 5000]);

        self::assertSame(2000, $coupon->discountFor(2000));
        self::assertSame(5000, $coupon->discountFor(9000));
    }

    public function testExpiredOrExhaustedCouponsAreNotRedeemable(): void
    {
        $expired = new Coupon();
        $expired->fill(['code' => 'OLD', 'type' => 'percent', 'value' => 5, 'expires_at' => '2020-01-01']);
        self::assertFalse($expired->isRedeemable());

        $single = new Coupon();
        $single->fill(['code' => 'ONCE', 'type' => 'percent', 'value' => 5, 'max_redemptions' => 1]);
        self::assertTrue($single->isRedeemable());
        $single->recordRedemption();
        self::assertFalse($single->isRedeemable());
    }
}
