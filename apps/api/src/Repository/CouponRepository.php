<?php

declare(strict_types=1);

namespace StagasBites\Repository;

use StagasBites\Entity\Coupon;

/**
 * @extends BaseRepository<Coupon>
 */
class CouponRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return Coupon::class;
    }

    public function findByCode(string $code): ?Coupon
    {
        return $this->findOneBy(['code' => strtoupper(trim($code))]);
    }
}
