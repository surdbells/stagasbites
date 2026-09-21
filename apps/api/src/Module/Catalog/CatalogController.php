<?php

declare(strict_types=1);

namespace StagasBites\Module\Catalog;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StagasBites\Entity\Product;
use StagasBites\Exception\ApiException;
use StagasBites\Helper\JsonResponse;
use StagasBites\Repository\CategoryRepository;
use StagasBites\Repository\ProductRepository;
use StagasBites\Service\SettingsService;

final class CatalogController
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly ProductRepository $products,
        private readonly SettingsService $settings,
    ) {
    }

    public function categories(Request $request, Response $response): Response
    {
        $counts = $this->products->countByCategory();
        $data = [];
        foreach ($this->categories->findActive() as $category) {
            $data[] = $category->toArray() + ['product_count' => (int) ($counts[$category->getId()] ?? 0)];
        }

        return self::cacheable(JsonResponse::success($response, $data));
    }

    public function products(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $page = max(1, (int) ($q['page'] ?? 1));
        $perPage = max(1, min(60, (int) ($q['per_page'] ?? 24)));

        $result = $this->products->search([
            'category' => isset($q['category']) ? (string) $q['category'] : '',
            'search' => isset($q['search']) ? mb_substr((string) $q['search'], 0, 80) : '',
            'featured' => filter_var($q['featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort' => (string) ($q['sort'] ?? 'featured'),
        ], $page, $perPage);

        return self::cacheable(JsonResponse::paginated(
            $response,
            array_map(static fn (Product $p): array => $p->toArray(), $result['items']),
            $result['total'],
            $page,
            $perPage,
        ));
    }

    /**
     * @param array{slug: string} $args
     */
    public function product(Request $request, Response $response, string $slug): Response
    {
        $product = $this->products->findBySlug($slug);
        if ($product === null || !$product->isAvailable()) {
            throw ApiException::notFound('We could not find that item.');
        }

        return self::cacheable(JsonResponse::success($response, $product->toArray()));
    }

    public function settings(Request $request, Response $response): Response
    {
        return self::cacheable(JsonResponse::success($response, $this->settings->all()));
    }

    private static function cacheable(Response $response): Response
    {
        return $response->withHeader('Cache-Control', 'public, max-age=60');
    }
}
