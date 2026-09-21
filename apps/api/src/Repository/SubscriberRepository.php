<?php

declare(strict_types=1);

namespace StagasBites\Repository;

use StagasBites\Entity\Subscriber;

/**
 * @extends BaseRepository<Subscriber>
 */
class SubscriberRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return Subscriber::class;
    }
}
