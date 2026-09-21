<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'subscribers')]
#[ORM\HasLifecycleCallbacks]
class Subscriber
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $unsubscribeToken;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    public function __construct(string $email)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->email = strtolower(trim($email));
        $this->unsubscribeToken = bin2hex(random_bytes(32));
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function setActive(bool $active): void
    {
        $this->isActive = $active;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'is_active' => $this->isActive,
            'created_at' => $this->getCreatedAt()?->format(DATE_ATOM),
        ];
    }
}
