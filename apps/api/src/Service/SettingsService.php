<?php

declare(strict_types=1);

namespace StagasBites\Service;

use Doctrine\ORM\EntityManagerInterface;
use StagasBites\Entity\Setting;

/** Store-level settings, editable from the admin. Defaults apply until a key is saved. */
final class SettingsService
{
    /** Amounts are in cents; tax_rate is a fraction (0.13 = Ontario HST). pickup_days uses ISO weekdays, 1 = Monday. */
    private const DEFAULTS = [
        'currency' => 'CAD',
        'tax_rate' => 0.13,
        'tax_label' => 'HST (13%)',
        'delivery_fee' => 1500,
        'free_delivery_threshold' => 25000,
        'delivery_areas' => ['Oakville', 'Burlington', 'Mississauga', 'Milton'],
        'min_lead_hours' => 48,
        'pickup_days' => [5, 6, 7],
        'time_slots' => ['10:00 AM – 12:00 PM', '12:00 PM – 2:00 PM', '2:00 PM – 4:00 PM', '4:00 PM – 6:00 PM'],
        'pickup_address' => 'Oakville, ON (exact address sent with your confirmation)',
        'phone' => '+1 (647) 673-8796',
        'email' => 'contact@stagasbites.ca',
        'whatsapp' => '16476738796',
        'instagram' => null,
        'facebook' => null,
        'google_reviews_url' => 'https://search.google.com/local/writereview?placeid=ChIJ218RGCxlK4gRtgqbVBx8Nks',
    ];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = self::DEFAULTS;
            foreach ($this->em->getRepository(Setting::class)->findAll() as $setting) {
                if (array_key_exists($setting->getKey(), self::DEFAULTS)) {
                    $this->cache[$setting->getKey()] = $setting->getValue();
                }
            }
        }

        return $this->cache;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public function update(array $values): array
    {
        $repo = $this->em->getRepository(Setting::class);
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            $value = self::coerce($key, $value);
            $setting = $repo->find($key);
            if ($setting === null) {
                $this->em->persist(new Setting($key, $value));
            } else {
                $setting->setValue($value);
            }
        }
        $this->em->flush();
        $this->cache = null;

        return $this->all();
    }

    private static function coerce(string $key, mixed $value): mixed
    {
        $default = self::DEFAULTS[$key];

        return match (true) {
            $key === 'tax_rate' => max(0.0, min(1.0, (float) $value)),
            $key === 'free_delivery_threshold' => $value === null || $value === '' ? null : max(0, (int) $value),
            $key === 'pickup_days' => array_values(array_unique(array_filter(array_map('intval', (array) $value), static fn (int $d): bool => $d >= 1 && $d <= 7))),
            is_int($default) => max(0, (int) $value),
            is_array($default) => array_values(array_filter(array_map(static fn (mixed $v): string => trim((string) $v), (array) $value))),
            $default === null => trim((string) $value) === '' ? null : trim((string) $value),
            default => trim((string) $value),
        };
    }
}
