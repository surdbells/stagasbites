<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/** A contact-form message or a catering / bulk-order quote request. */
#[ORM\Entity]
#[ORM\Table(name: 'inquiries')]
#[ORM\Index(name: 'idx_inquiry_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Inquiry
{
    use TimestampsTrait;

    public const TYPE_CONTACT = 'contact';
    public const TYPE_CATERING = 'catering';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $phone;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $eventDate;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $guestCount;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $eventType;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'new';

    public function __construct(
        string $type,
        string $name,
        string $email,
        ?string $phone,
        string $message,
        ?\DateTimeImmutable $eventDate = null,
        ?int $guestCount = null,
        ?string $eventType = null,
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->type = $type;
        $this->name = trim($name);
        $this->email = strtolower(trim($email));
        $this->phone = $phone;
        $this->message = trim($message);
        $this->eventDate = $eventDate;
        $this->guestCount = $guestCount;
        $this->eventType = $eventType;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'event_date' => $this->eventDate?->format('Y-m-d'),
            'guest_count' => $this->guestCount,
            'event_type' => $this->eventType,
            'message' => $this->message,
            'status' => $this->status,
            'created_at' => $this->getCreatedAt()?->format(DATE_ATOM),
        ];
    }
}
