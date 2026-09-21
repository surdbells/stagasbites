<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * Hashed, single-use server-side token: refresh tokens and password-reset tokens.
 * Only the SHA-256 of the secret is stored.
 */
#[ORM\Entity]
#[ORM\Table(name: 'auth_tokens')]
#[ORM\Index(name: 'idx_auth_token_user', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_auth_token_hash', columns: ['token_hash'])]
class AuthToken
{
    public const TYPE_REFRESH = 'refresh';
    public const TYPE_PASSWORD_RESET = 'password_reset';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $userId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(type: 'string', length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $userId, string $type, string $plainToken, int $ttlSeconds)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->userId = $userId;
        $this->type = $type;
        $this->tokenHash = self::hash($plainToken);
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify("+{$ttlSeconds} seconds");
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isUsable(): bool
    {
        return $this->usedAt === null && $this->expiresAt > new \DateTimeImmutable();
    }

    public function markUsed(): void
    {
        $this->usedAt = new \DateTimeImmutable();
    }
}
