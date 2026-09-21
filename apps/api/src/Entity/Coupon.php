<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'coupons')]
#[ORM\HasLifecycleCallbacks]
class Coupon
{
    use TimestampsTrait;

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 40, unique: true)]
    private string $code = '';

    #[ORM\Column(type: 'string', length: 10)]
    private string $type = self::TYPE_PERCENT;

    /** Percent (1-100) or a fixed amount in cents, depending on type. */
    #[ORM\Column(type: 'integer')]
    private int $value = 0;

    #[ORM\Column(type: 'integer')]
    private int $minSubtotal = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxRedemptions = null;

    #[ORM\Column(type: 'integer')]
    private int $timesRedeemed = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getMinSubtotal(): int
    {
        return $this->minSubtotal;
    }

    public function isRedeemable(): bool
    {
        return $this->isActive
            && ($this->expiresAt === null || $this->expiresAt > new \DateTimeImmutable())
            && ($this->maxRedemptions === null || $this->timesRedeemed < $this->maxRedemptions);
    }

    public function discountFor(int $subtotal): int
    {
        $discount = $this->type === self::TYPE_PERCENT
            ? (int) round($subtotal * min(100, $this->value) / 100)
            : $this->value;

        return max(0, min($subtotal, $discount));
    }

    public function recordRedemption(): void
    {
        ++$this->timesRedeemed;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fill(array $data): void
    {
        $this->code = strtoupper(trim((string) ($data['code'] ?? $this->code)));
        $type = (string) ($data['type'] ?? $this->type);
        $this->type = $type === self::TYPE_FIXED ? self::TYPE_FIXED : self::TYPE_PERCENT;
        $this->value = max(0, (int) ($data['value'] ?? $this->value));
        $this->minSubtotal = max(0, (int) ($data['min_subtotal'] ?? $this->minSubtotal));
        if (array_key_exists('max_redemptions', $data)) {
            $this->maxRedemptions = $data['max_redemptions'] === null || $data['max_redemptions'] === ''
                ? null
                : max(1, (int) $data['max_redemptions']);
        }
        if (array_key_exists('expires_at', $data)) {
            $this->expiresAt = empty($data['expires_at']) ? null : new \DateTimeImmutable((string) $data['expires_at']);
        }
        $this->isActive = (bool) ($data['is_active'] ?? $this->isActive);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'min_subtotal' => $this->minSubtotal,
            'max_redemptions' => $this->maxRedemptions,
            'times_redeemed' => $this->timesRedeemed,
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
            'is_active' => $this->isActive,
        ];
    }
}
