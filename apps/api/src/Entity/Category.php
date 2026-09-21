<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'categories')]
#[ORM\HasLifecycleCallbacks]
class Category
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 140, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'string', length: 160, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: 'string', length: 320, nullable: true)]
    private ?string $metaDescription = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fill(array $data): void
    {
        $this->name = trim((string) ($data['name'] ?? $this->name));
        $this->slug = trim((string) ($data['slug'] ?? $this->slug));
        $this->description = array_key_exists('description', $data) ? self::nullable($data['description']) : $this->description;
        $this->imageUrl = array_key_exists('image_url', $data) ? self::nullable($data['image_url']) : $this->imageUrl;
        $this->sortOrder = (int) ($data['sort_order'] ?? $this->sortOrder);
        $this->isActive = (bool) ($data['is_active'] ?? $this->isActive);
        $this->metaTitle = array_key_exists('meta_title', $data) ? self::nullable($data['meta_title']) : $this->metaTitle;
        $this->metaDescription = array_key_exists('meta_description', $data) ? self::nullable($data['meta_description']) : $this->metaDescription;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_url' => $this->imageUrl,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
