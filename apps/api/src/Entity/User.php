<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User
{
    use TimestampsTrait;

    private const MAX_FAILED_LOGINS = 5;
    private const LOCK_MINUTES = 15;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $passwordHash = '';

    #[ORM\Column(type: 'string', length: 80)]
    private string $firstName = '';

    #[ORM\Column(type: 'string', length: 80)]
    private string $lastName = '';

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 20, enumType: UserRole::class)]
    private UserRole $role = UserRole::CUSTOMER;

    #[ORM\Column(type: 'integer')]
    private int $failedLogins = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lockedUntil = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = strtolower(trim($email));
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = trim($firstName);
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = trim($lastName);
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone !== null && trim($phone) !== '' ? trim($phone) : null;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): void
    {
        $this->role = $role;
    }

    public function setPassword(string $plain): void
    {
        $this->passwordHash = password_hash($plain, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }

    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->passwordHash);
    }

    public function isLocked(): bool
    {
        return $this->lockedUntil !== null && $this->lockedUntil > new \DateTimeImmutable();
    }

    public function recordFailedLogin(): void
    {
        if (++$this->failedLogins >= self::MAX_FAILED_LOGINS) {
            $this->lockedUntil = new \DateTimeImmutable('+' . self::LOCK_MINUTES . ' minutes');
            $this->failedLogins = 0;
        }
    }

    public function recordLogin(): void
    {
        $this->failedLogins = 0;
        $this->lockedUntil = null;
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'role' => $this->role->value,
        ];
    }
}
