<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/** Snapshot of what was bought: name and price are copied so later catalogue edits don't rewrite history. */
#[ORM\Entity]
#[ORM\Table(name: 'order_items')]
#[ORM\Index(name: 'idx_order_item_order', columns: ['order_id'])]
class OrderItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $productId;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(type: 'string', length: 120)]
    private string $optionLabel;

    #[ORM\Column(type: 'integer')]
    private int $unitPrice;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'integer')]
    private int $lineTotal;

    public function __construct(Order $order, Product $product, ProductOption $option, int $quantity)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->order = $order;
        $this->productId = $product->getId();
        $this->name = $product->getName();
        $this->optionLabel = $option->getLabel();
        $this->unitPrice = $option->getPrice();
        $this->quantity = $quantity;
        $this->lineTotal = $option->getPrice() * $quantity;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOptionLabel(): string
    {
        return $this->optionLabel;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getLineTotal(): int
    {
        return $this->lineTotal;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'name' => $this->name,
            'option_label' => $this->optionLabel,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'line_total' => $this->lineTotal,
        ];
    }
}
