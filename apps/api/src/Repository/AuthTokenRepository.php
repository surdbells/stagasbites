<?php

declare(strict_types=1);

namespace StagasBites\Repository;

use StagasBites\Entity\AuthToken;

/**
 * @extends BaseRepository<AuthToken>
 */
class AuthTokenRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return AuthToken::class;
    }

    public function findUsable(string $plainToken, string $type): ?AuthToken
    {
        $token = $this->findOneBy(['tokenHash' => AuthToken::hash($plainToken), 'type' => $type]);

        return $token !== null && $token->isUsable() ? $token : null;
    }

    public function revokeAllForUser(string $userId, string $type): void
    {
        $this->em->createQueryBuilder()
            ->update(AuthToken::class, 't')
            ->set('t.usedAt', ':now')->setParameter('now', new \DateTimeImmutable())
            ->where('t.userId = :uid AND t.type = :type AND t.usedAt IS NULL')
            ->setParameter('uid', $userId)->setParameter('type', $type)
            ->getQuery()->execute();
    }
}
