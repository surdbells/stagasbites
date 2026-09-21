<?php

declare(strict_types=1);

namespace StagasBites\Module\Seo;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StagasBites\Entity\Product;
use StagasBites\Helper\Str;
use StagasBites\Repository\CategoryRepository;
use StagasBites\Repository\ProductRepository;

/**
 * SEO without SSR. The Angular app is a plain client-rendered SPA; this controller serves its
 * built index.html with route-specific <title>, description, canonical, Open Graph and JSON-LD
 * already in the HTML, so link-preview bots and non-JS crawlers see real metadata.
 * Once Angular boots, SeoService keeps the same tags up to date on navigation.
 */
final class SeoController
{
    private const SITE_NAME = "Staga's Bites";

    /** Static storefront routes: path => [title, description]. */
    private const PAGES = [
        '/' => ["Staga's Bites | Nigerian Small Chops, Pastries & Grills in Oakville", 'Authentic Nigerian small chops, meat pies, puff puff, suya and flame-grilled favourites in Oakville, ON. Pre-order for weekend pickup or delivery, or book us to cater your event.'],
        '/menu' => ['Menu — Small Chops, Grills & Pastries', 'Browse the full Staga\'s Bites menu: samosas, spring rolls, puff puff, meat pies, asun, suya, grilled tilapia and party platters. Order online for pickup or delivery in the GTA.'],
        '/catering' => ['Nigerian Event Catering & Bulk Orders in Oakville & the GTA', 'Small chops, grills and pastries for weddings, birthdays, corporate events and house parties. Custom platters for any guest count. Request a catering quote today.'],
        '/about' => ['Our Story', "Meet Staga's Bites — an award-winning, certified Nigerian kitchen in Oakville, Ontario, serving small chops, pastries and grills made fresh to order."],
        '/contact' => ['Contact Us', "Questions, custom orders or catering? Call +1 (647) 673-8796 or message Staga's Bites in Oakville, Ontario. We reply within one business day."],
        '/faq' => ['Frequently Asked Questions', 'How pre-orders, pickup, delivery, lead times, allergens and catering work at Staga\'s Bites.'],
        '/legal/delivery' => ['Pickup & Delivery', 'Pickup and local delivery information for Staga\'s Bites orders in Oakville and the GTA.'],
        '/legal/refunds' => ['Refund Policy', 'Refund and cancellation policy for Staga\'s Bites orders.'],
        '/legal/terms' => ['Terms & Conditions', 'Terms and conditions for ordering from Staga\'s Bites.'],
        '/legal/privacy' => ['Privacy Policy', 'How Staga\'s Bites collects, uses and protects your personal information.'],
    ];

    private const NOINDEX_PREFIXES = ['/cart', '/checkout', '/order', '/account', '/admin'];

    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly string $siteUrl,
        private readonly string $spaIndexPath,
    ) {
    }

    public function robots(Request $request, Response $response): Response
    {
        $lines = ['User-agent: *'];
        foreach (self::NOINDEX_PREFIXES as $prefix) {
            $lines[] = 'Disallow: ' . $prefix;
        }
        $lines[] = 'Disallow: /api/';
        $lines[] = '';
        $lines[] = 'Sitemap: ' . $this->siteUrl . '/sitemap.xml';
        $response->getBody()->write(implode("\n", $lines) . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')->withHeader('Cache-Control', 'public, max-age=3600');
    }

    public function sitemap(Request $request, Response $response): Response
    {
        $urls = [];
        foreach (array_keys(self::PAGES) as $path) {
            $urls[] = ['loc' => $path, 'priority' => $path === '/' ? '1.0' : ($path === '/menu' ? '0.9' : '0.6'), 'lastmod' => null];
        }
        foreach ($this->categories->findActive() as $category) {
            $urls[] = ['loc' => '/menu/' . $category->getSlug(), 'priority' => '0.8', 'lastmod' => $category->getUpdatedAt()?->format('Y-m-d')];
        }
        foreach ($this->products->sitemapEntries() as $entry) {
            $urls[] = ['loc' => '/product/' . $entry['slug'], 'priority' => '0.7', 'lastmod' => $entry['updated_at']];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>' . Str::e($this->siteUrl . $url['loc']) . '</loc>'
                . ($url['lastmod'] !== null ? '<lastmod>' . $url['lastmod'] . '</lastmod>' : '')
                . '<priority>' . $url['priority'] . '</priority></url>' . "\n";
        }
        $response->getBody()->write($xml . '</urlset>' . "\n");

        return $response->withHeader('Content-Type', 'application/xml; charset=utf-8')->withHeader('Cache-Control', 'public, max-age=3600');
    }

    /** Catch-all for storefront URLs: serves index.html with the route's metadata baked in. */
    public function spa(Request $request, Response $response): Response
    {
        if (!is_file($this->spaIndexPath)) {
            $response->getBody()->write('Storefront build not found. Run "npm run build" in apps/web.');

            return $response->withStatus(503)->withHeader('Content-Type', 'text/plain');
        }

        $path = '/' . trim($request->getUri()->getPath(), '/');
        $meta = $this->resolve($path);

        $response->getBody()->write($this->inject((string) file_get_contents($this->spaIndexPath), $meta, $path));

        return $response
            ->withStatus($meta['status'])
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache');
    }

    /**
     * @return array{title: string, description: string, image: ?string, type: string, noindex: bool, status: int, json_ld: list<array<string, mixed>>}
     */
    private function resolve(string $path): array
    {
        $meta = ['title' => self::PAGES['/'][0], 'description' => self::PAGES['/'][1], 'image' => null, 'type' => 'website', 'noindex' => false, 'status' => 200, 'json_ld' => []];

        foreach (self::NOINDEX_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return ['title' => self::SITE_NAME, 'noindex' => true] + $meta;
            }
        }

        if (isset(self::PAGES[$path])) {
            [$title, $description] = self::PAGES[$path];
            $meta = ['title' => $title, 'description' => $description] + $meta;
            if ($path === '/') {
                $meta['json_ld'][] = $this->businessJsonLd();
            }

            return $meta;
        }

        if (preg_match('#^/product/([a-z0-9-]+)$#', $path, $m) === 1) {
            $product = $this->products->findBySlug($m[1]);
            if ($product !== null && $product->isAvailable()) {
                return [
                    'title' => $product->getMetaTitle() ?? $product->getName() . ' — Order Online in Oakville',
                    'description' => $product->getMetaDescription() ?? Str::truncate((string) ($product->getShortDescription() ?? $product->getDescription() ?? $product->getName()), 158),
                    'image' => $product->getImageUrl(),
                    'type' => 'product',
                    'json_ld' => [$this->productJsonLd($product)],
                ] + $meta;
            }
        }

        if (preg_match('#^/menu/([a-z0-9-]+)$#', $path, $m) === 1) {
            $category = $this->categories->findBySlug($m[1]);
            if ($category !== null && $category->isActive()) {
                return [
                    'title' => $category->getMetaTitle() ?? $category->getName() . ' — Order Online in Oakville & the GTA',
                    'description' => $category->getMetaDescription() ?? Str::truncate((string) ($category->getDescription() ?? $category->getName()), 158),
                    'image' => $category->getImageUrl(),
                ] + $meta;
            }
        }

        // Unknown URL: let Angular render its not-found page, but tell crawlers the truth.
        return ['title' => 'Page not found', 'noindex' => true, 'status' => 404] + $meta;
    }

    /**
     * @param array{title: string, description: string, image: ?string, type: string, noindex: bool, status: int, json_ld: list<array<string, mixed>>} $meta
     */
    private function inject(string $html, array $meta, string $path): string
    {
        $title = str_contains($meta['title'], self::SITE_NAME) ? $meta['title'] : $meta['title'] . ' | ' . self::SITE_NAME;
        $url = $this->siteUrl . ($path === '/' ? '/' : $path);
        $image = $meta['image'] ?? $this->siteUrl . '/og-default.jpg';
        $image = str_starts_with($image, 'http') ? $image : $this->siteUrl . $image;

        $tags = [
            '<meta name="description" content="' . Str::e($meta['description']) . '">',
            '<meta name="robots" content="' . ($meta['noindex'] ? 'noindex, nofollow' : 'index, follow, max-image-preview:large') . '">',
            '<link rel="canonical" href="' . Str::e($url) . '">',
            '<meta property="og:site_name" content="' . Str::e(self::SITE_NAME) . '">',
            '<meta property="og:title" content="' . Str::e($title) . '">',
            '<meta property="og:description" content="' . Str::e($meta['description']) . '">',
            '<meta property="og:type" content="' . Str::e($meta['type']) . '">',
            '<meta property="og:url" content="' . Str::e($url) . '">',
            '<meta property="og:image" content="' . Str::e($image) . '">',
            '<meta property="og:locale" content="en_CA">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="twitter:title" content="' . Str::e($title) . '">',
            '<meta name="twitter:description" content="' . Str::e($meta['description']) . '">',
            '<meta name="twitter:image" content="' . Str::e($image) . '">',
        ];
        foreach ($meta['json_ld'] as $block) {
            $tags[] = '<script type="application/ld+json" data-seo-jsonld>'
                . json_encode($block, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
        }

        // Drop the build-time defaults these tags replace, then insert the route-specific set.
        $html = (string) preg_replace('#<title>.*?</title>#is', '<title>' . Str::e($title) . '</title>', $html, 1);
        $html = (string) preg_replace('#\s*<meta\s+(?:name="(?:description|robots|twitter:[^"]+)"|property="og:[^"]+")[^>]*>#i', '', $html);
        $html = (string) preg_replace('#\s*<link\s+rel="canonical"[^>]*>#i', '', $html);

        return (string) preg_replace('#</head>#i', '  ' . implode("\n  ", $tags) . "\n</head>", $html, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function productJsonLd(Product $product): array
    {
        $data = $product->toArray();
        $prices = array_column($data['options'], 'price');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $data['name'],
            'description' => Str::truncate((string) ($data['description'] ?? $data['short_description'] ?? $data['name']), 500),
            'image' => array_values(array_filter([$data['image_url'], ...$data['gallery']])),
            'category' => $data['category']['name'] ?? null,
            'brand' => ['@type' => 'Brand', 'name' => self::SITE_NAME],
            'url' => $this->siteUrl . '/product/' . $data['slug'],
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'CAD',
                'lowPrice' => number_format(min($prices ?: [0]) / 100, 2, '.', ''),
                'highPrice' => number_format(max($prices ?: [0]) / 100, 2, '.', ''),
                'offerCount' => count($prices),
                'availability' => 'https://schema.org/PreOrder',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function businessJsonLd(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => ['FoodEstablishment', 'Caterer'],
            '@id' => $this->siteUrl . '/#business',
            'name' => self::SITE_NAME,
            'url' => $this->siteUrl . '/',
            'image' => $this->siteUrl . '/og-default.jpg',
            'telephone' => '+1-647-673-8796',
            'email' => 'contact@stagasbites.ca',
            'servesCuisine' => ['Nigerian', 'West African', 'African'],
            'priceRange' => '$$',
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Oakville', 'addressRegion' => 'ON', 'addressCountry' => 'CA'],
            'areaServed' => ['Oakville', 'Mississauga', 'Burlington', 'Milton', 'Hamilton', 'Toronto'],
            'hasMenu' => $this->siteUrl . '/menu',
        ];
    }
}
