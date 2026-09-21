<?php

declare(strict_types=1);

namespace StagasBites\Repository;

use StagasBites\Entity\Inquiry;

/**
 * @extends BaseRepository<Inquiry>
 */
class InquiryRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return Inquiry::class;
    }
}
