<?php

declare(strict_types=1);

namespace StagasBites\Service;

use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Live Google reviews via the Places API (New). The key stays on the server and the
 * response is cached briefly, so a page view never costs an API call.
 */
final class GoogleReviewsService
{
    private const CACHE_KEY = 'google.reviews.v1';
    private const CACHE_TTL = 3600;
    private const FAILURE_TTL = 300;

    /**
     * @param array{api_key: string, place_id: string} $config
     */
    public function __construct(
        private readonly array $config,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly ?Client $client = null,
    ) {
    }

    /**
     * @return array{configured: bool, rating: ?float, count: ?int, url: string, write_url: string, reviews: list<array<string, mixed>>}
     */
    public function summary(): array
    {
        $placeId = $this->config['place_id'];
        $base = [
            'configured' => false,
            'rating' => null,
            'count' => null,
            'url' => 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode($placeId),
            'write_url' => 'https://search.google.com/local/writereview?placeid=' . rawurlencode($placeId),
            'reviews' => [],
        ];
        if ($this->config['api_key'] === '' || $placeId === '') {
            return $base;
        }

        $item = $this->cache->getItem(self::CACHE_KEY);
        if ($item->isHit()) {
            return $item->get();
        }

        try {
            $response = ($this->client ?? new Client(['timeout' => 8]))->get(
                'https://places.googleapis.com/v1/places/' . rawurlencode($placeId),
                [
                    'query' => ['languageCode' => 'en'],
                    'headers' => [
                        'X-Goog-Api-Key' => $this->config['api_key'],
                        'X-Goog-FieldMask' => 'rating,userRatingCount,googleMapsUri,reviews',
                    ],
                ],
            );
            $place = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->warning('Google reviews fetch failed.', ['error' => $e->getMessage()]);
            // Remember the failure briefly so an outage does not slow every page view.
            $this->cache->save($item->set($base)->expiresAfter(self::FAILURE_TTL));

            return $base;
        }

        $summary = [
            'configured' => true,
            'rating' => isset($place['rating']) ? (float) $place['rating'] : null,
            'count' => isset($place['userRatingCount']) ? (int) $place['userRatingCount'] : null,
            'url' => (string) ($place['googleMapsUri'] ?? $base['url']),
            'write_url' => $base['write_url'],
            'reviews' => array_values(array_map(static fn (array $r): array => [
                'author' => (string) ($r['authorAttribution']['displayName'] ?? 'Google user'),
                'author_url' => $r['authorAttribution']['uri'] ?? null,
                'photo_url' => $r['authorAttribution']['photoUri'] ?? null,
                'rating' => (int) ($r['rating'] ?? 0),
                'text' => (string) ($r['originalText']['text'] ?? $r['text']['text'] ?? ''),
                'when' => (string) ($r['relativePublishTimeDescription'] ?? ''),
            ], array_filter((array) ($place['reviews'] ?? []), static fn (array $r): bool => trim((string) ($r['text']['text'] ?? '')) !== ''))),
        ];
        $this->cache->save($item->set($summary)->expiresAfter(self::CACHE_TTL));

        return $summary;
    }
}
