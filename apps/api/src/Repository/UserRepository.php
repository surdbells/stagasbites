<?php

declare(strict_types=1);

namespace StagasBites\Repository;

use StagasBites\Entity\User;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => strtolower(trim($email))]);
    }
}
