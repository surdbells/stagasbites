<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/** A purchasable size of a product, e.g. "Tray of 50". The price is in minor units (cents). */
#[ORM\Entity]
#[ORM\Table(name: 'product_options')]
#[ORM\Index(name: 'idx_option_product', columns: ['product_id'])]
class ProductOption
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'options')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: 'string', length: 120)]
    private string $label = '';

    #[ORM\Column(type: 'integer')]
    private int $price = 0;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $serves = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isDefault = false;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    public function __construct(Product $product)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->product = $product;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fill(array $data): void
    {
        $this->label = trim((string) ($data['label'] ?? $this->label));
        $this->price = max(0, (int) ($data['price'] ?? $this->price));
        $serves = trim((string) ($data['serves'] ?? (string) $this->serves));
        $this->serves = $serves === '' ? null : $serves;
        $this->isDefault = (bool) ($data['is_default'] ?? $this->isDefault);
        $this->sortOrder = (int) ($data['sort_order'] ?? $this->sortOrder);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'price' => $this->price,
            'serves' => $this->serves,
            'is_default' => $this->isDefault,
        ];
    }
}
