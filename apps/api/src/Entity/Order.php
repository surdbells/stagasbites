<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/** All monetary amounts are integers in minor units (cents). */
#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_order_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_order_status', columns: ['status'])]
#[ORM\Index(name: 'idx_order_fulfilment_date', columns: ['fulfilment_date'])]
#[ORM\HasLifecycleCallbacks]
class Order
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 20, unique: true)]
    private string $orderNumber;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $userId = null;

    #[ORM\Column(type: 'string', length: 20, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PENDING_PAYMENT;

    #[ORM\Column(type: 'string', length: 80)]
    private string $customerFirstName = '';

    #[ORM\Column(type: 'string', length: 80)]
    private string $customerLastName = '';

    #[ORM\Column(type: 'string', length: 180)]
    private string $customerEmail = '';

    #[ORM\Column(type: 'string', length: 30)]
    private string $customerPhone = '';

    #[ORM\Column(type: 'string', length: 10)]
    private string $fulfilmentMethod = 'pickup';

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $fulfilmentDate;

    #[ORM\Column(type: 'string', length: 40)]
    private string $fulfilmentTimeSlot = '';

    #[ORM\Column(type: 'string', length: 400, nullable: true)]
    private ?string $deliveryAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'integer')]
    private int $subtotal = 0;

    #[ORM\Column(type: 'integer')]
    private int $discount = 0;

    #[ORM\Column(type: 'integer')]
    private int $deliveryFee = 0;

    #[ORM\Column(type: 'integer')]
    private int $tax = 0;

    #[ORM\Column(type: 'integer')]
    private int $total = 0;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency = 'CAD';

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $couponCode = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(string $orderNumber, \DateTimeImmutable $fulfilmentDate)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->orderNumber = $orderNumber;
        $this->fulfilmentDate = $fulfilmentDate;
        $this->items = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): void
    {
        $this->status = $status;
    }

    public function setCustomer(string $firstName, string $lastName, string $email, string $phone): void
    {
        $this->customerFirstName = trim($firstName);
        $this->customerLastName = trim($lastName);
        $this->customerEmail = strtolower(trim($email));
        $this->customerPhone = trim($phone);
    }

    public function getCustomerName(): string
    {
        return trim($this->customerFirstName . ' ' . $this->customerLastName);
    }

    public function getCustomerFirstName(): string
    {
        return $this->customerFirstName;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function setFulfilment(string $method, string $timeSlot, ?string $deliveryAddress): void
    {
        $this->fulfilmentMethod = $method;
        $this->fulfilmentTimeSlot = $timeSlot;
        $this->deliveryAddress = $deliveryAddress;
    }

    public function getFulfilmentMethod(): string
    {
        return $this->fulfilmentMethod;
    }

    public function getFulfilmentDate(): \DateTimeImmutable
    {
        return $this->fulfilmentDate;
    }

    public function getFulfilmentTimeSlot(): string
    {
        return $this->fulfilmentTimeSlot;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $notes = trim((string) $notes);
        $this->notes = $notes === '' ? null : mb_substr($notes, 0, 2000);
    }

    public function addItem(OrderItem $item): void
    {
        $this->items->add($item);
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function setTotals(int $subtotal, int $discount, int $deliveryFee, int $tax, string $currency): void
    {
        $this->subtotal = $subtotal;
        $this->discount = $discount;
        $this->deliveryFee = $deliveryFee;
        $this->tax = $tax;
        $this->total = $subtotal - $discount + $deliveryFee + $tax;
        $this->currency = $currency;
    }

    public function getSubtotal(): int
    {
        return $this->subtotal;
    }

    public function getDiscount(): int
    {
        return $this->discount;
    }

    public function getDeliveryFee(): int
    {
        return $this->deliveryFee;
    }

    public function getTax(): int
    {
        return $this->tax;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCouponCode(): ?string
    {
        return $this->couponCode;
    }

    public function setCouponCode(?string $couponCode): void
    {
        $this->couponCode = $couponCode;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): void
    {
        $this->stripeSessionId = $stripeSessionId;
    }

    public function markPaid(?string $paymentIntentId): void
    {
        $this->status = OrderStatus::PAID;
        $this->stripePaymentIntentId = $paymentIntentId;
        $this->paidAt = new \DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->orderNumber,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer_first_name' => $this->customerFirstName,
            'customer_last_name' => $this->customerLastName,
            'customer_email' => $this->customerEmail,
            'customer_phone' => $this->customerPhone,
            'fulfilment_method' => $this->fulfilmentMethod,
            'fulfilment_date' => $this->fulfilmentDate->format('Y-m-d'),
            'fulfilment_time_slot' => $this->fulfilmentTimeSlot,
            'delivery_address' => $this->deliveryAddress,
            'notes' => $this->notes,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'delivery_fee' => $this->deliveryFee,
            'tax' => $this->tax,
            'total' => $this->total,
            'currency' => $this->currency,
            'coupon_code' => $this->couponCode,
            'items' => array_values($this->items->map(static fn (OrderItem $i): array => $i->toArray())->toArray()),
            'created_at' => $this->getCreatedAt()?->format(DATE_ATOM),
            'paid_at' => $this->paidAt?->format(DATE_ATOM),
        ];
    }
}
