<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'idx_product_category', columns: ['category_id'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: 'string', length: 320, nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $imageUrl = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $gallery = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $tags = [];

    #[ORM\Column(type: 'smallint')]
    private int $spiceLevel = 0;

    #[ORM\Column(type: 'integer')]
    private int $minQuantity = 1;

    #[ORM\Column(type: 'integer')]
    private int $leadTimeHours = 48;

    #[ORM\Column(type: 'boolean')]
    private bool $isFeatured = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable = true;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'string', length: 160, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: 'string', length: 320, nullable: true)]
    private ?string $metaDescription = null;

    /** @var Collection<int, ProductOption> */
    #[ORM\OneToMany(targetEntity: ProductOption::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'price' => 'ASC'])]
    private Collection $options;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
        $this->options = new ArrayCollection();
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): void
    {
        $this->category = $category;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getMinQuantity(): int
    {
        return $this->minQuantity;
    }

    public function getLeadTimeHours(): int
    {
        return $this->leadTimeHours;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
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
     * @return Collection<int, ProductOption>
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function findOption(string $optionId): ?ProductOption
    {
        foreach ($this->options as $option) {
            if ($option->getId() === $optionId) {
                return $option;
            }
        }

        return null;
    }

    public function getPriceFrom(): int
    {
        $prices = $this->options->map(static fn (ProductOption $o): int => $o->getPrice())->toArray();

        return $prices === [] ? 0 : min($prices);
    }

    /**
     * Replaces the option set. Rows carrying a known `id` are updated in place so that
     * carts holding that option id stay valid.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function syncOptions(array $rows): void
    {
        $keep = [];
        foreach (array_values($rows) as $index => $row) {
            $option = isset($row['id']) ? $this->findOption((string) $row['id']) : null;
            if ($option === null) {
                $option = new ProductOption($this);
                $this->options->add($option);
            }
            $option->fill($row + ['sort_order' => $index]);
            $keep[] = $option->getId();
        }
        foreach ($this->options as $option) {
            if (!in_array($option->getId(), $keep, true)) {
                $this->options->removeElement($option);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fill(array $data): void
    {
        $text = static fn (string $key, ?string $current): ?string => array_key_exists($key, $data)
            ? (trim((string) $data[$key]) === '' ? null : trim((string) $data[$key]))
            : $current;
        $strings = static fn (mixed $list): array => array_values(array_filter(array_map(
            static fn (mixed $v): string => trim((string) $v),
            is_array($list) ? $list : [],
        )));

        $this->name = trim((string) ($data['name'] ?? $this->name));
        $this->slug = trim((string) ($data['slug'] ?? $this->slug));
        $this->shortDescription = $text('short_description', $this->shortDescription);
        $this->description = $text('description', $this->description);
        $this->imageUrl = $text('image_url', $this->imageUrl);
        $this->metaTitle = $text('meta_title', $this->metaTitle);
        $this->metaDescription = $text('meta_description', $this->metaDescription);
        $this->gallery = array_key_exists('gallery', $data) ? $strings($data['gallery']) : $this->gallery;
        $this->tags = array_key_exists('tags', $data) ? $strings($data['tags']) : $this->tags;
        $this->spiceLevel = max(0, min(3, (int) ($data['spice_level'] ?? $this->spiceLevel)));
        $this->minQuantity = max(1, (int) ($data['min_quantity'] ?? $this->minQuantity));
        $this->leadTimeHours = max(0, (int) ($data['lead_time_hours'] ?? $this->leadTimeHours));
        $this->isFeatured = (bool) ($data['is_featured'] ?? $this->isFeatured);
        $this->isAvailable = (bool) ($data['is_available'] ?? $this->isAvailable);
        $this->sortOrder = (int) ($data['sort_order'] ?? $this->sortOrder);
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
            'short_description' => $this->shortDescription,
            'description' => $this->description,
            'image_url' => $this->imageUrl,
            'gallery' => $this->gallery,
            'price_from' => $this->getPriceFrom(),
            'spice_level' => $this->spiceLevel,
            'min_quantity' => $this->minQuantity,
            'lead_time_hours' => $this->leadTimeHours,
            'is_featured' => $this->isFeatured,
            'is_available' => $this->isAvailable,
            'sort_order' => $this->sortOrder,
            'tags' => $this->tags,
            'category' => $this->category === null ? null : [
                'id' => $this->category->getId(),
                'name' => $this->category->getName(),
                'slug' => $this->category->getSlug(),
            ],
            'options' => array_values($this->options->map(static fn (ProductOption $o): array => $o->toArray())->toArray()),
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
        ];
    }
}
