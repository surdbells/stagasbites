<?php

declare(strict_types=1);

namespace StagasBites\Module\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use StagasBites\Entity\Category;
use StagasBites\Entity\Product;
use StagasBites\Exception\ApiException;
use StagasBites\Helper\JsonResponse;
use StagasBites\Helper\Str;
use StagasBites\Repository\CategoryRepository;
use StagasBites\Repository\ProductRepository;
use StagasBites\Service\ValidationService;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminCatalogController
{
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif'];

    /**
     * @param array{upload_dir: string, upload_url: string, max_bytes: int} $storage
     */
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly ValidationService $validator,
        private readonly array $storage,
        private readonly string $appUrl,
    ) {
    }

    public function listProducts(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $page = max(1, (int) ($q['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($q['per_page'] ?? 50)));
        $result = $this->products->search([
            'search' => (string) ($q['search'] ?? ''),
            'category' => (string) ($q['category'] ?? ''),
            'sort' => 'name',
            'include_unavailable' => true,
        ], $page, $perPage);

        return JsonResponse::paginated($response, array_map(static fn (Product $p): array => $p->toArray(), $result['items']), $result['total'], $page, $perPage);
    }

    public function showProduct(Request $request, Response $response, string $id): Response
    {
        return JsonResponse::success($response, $this->products->findOrFail($id)->toArray());
    }

    public function createProduct(Request $request, Response $response): Response
    {
        $product = new Product();
        $this->applyProduct($product, (array) $request->getParsedBody());

        return JsonResponse::success($response, $product->toArray(), 201);
    }

    public function updateProduct(Request $request, Response $response, string $id): Response
    {
        $product = $this->products->findOrFail($id);
        $this->applyProduct($product, (array) $request->getParsedBody());

        return JsonResponse::success($response, $product->toArray());
    }

    public function deleteProduct(Request $request, Response $response, string $id): Response
    {
        $this->products->remove($this->products->findOrFail($id));

        return JsonResponse::success($response, null, 200, 'Product deleted.');
    }

    public function listCategories(Request $request, Response $response): Response
    {
        $all = $this->categories->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']);

        return JsonResponse::success($response, array_map(static fn (Category $c): array => $c->toArray(), $all));
    }

    public function createCategory(Request $request, Response $response): Response
    {
        $category = new Category();
        $this->applyCategory($category, (array) $request->getParsedBody());

        return JsonResponse::success($response, $category->toArray(), 201);
    }

    public function updateCategory(Request $request, Response $response, string $id): Response
    {
        $category = $this->categories->findOrFail($id);
        $this->applyCategory($category, (array) $request->getParsedBody());

        return JsonResponse::success($response, $category->toArray());
    }

    public function deleteCategory(Request $request, Response $response, string $id): Response
    {
        $this->categories->remove($this->categories->findOrFail($id));

        return JsonResponse::success($response, null, 200, 'Category deleted.');
    }

    public function upload(Request $request, Response $response): Response
    {
        $file = $request->getUploadedFiles()['file'] ?? null;
        if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            throw ApiException::validation('No file was uploaded.');
        }
        if ($file->getSize() > $this->storage['max_bytes']) {
            throw ApiException::validation('Images must be 5 MB or smaller.');
        }

        // Trust the file's actual bytes, not the client-supplied name or MIME type.
        $stream = $file->getStream();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($stream->read(8192)) ?: '';
        $extension = self::IMAGE_TYPES[$mime] ?? throw ApiException::validation('Only JPG, PNG, WebP or AVIF images are allowed.');

        $dir = $this->storage['upload_dir'] . '/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ApiException('Upload directory is not writable.', 500);
        }
        $name = bin2hex(random_bytes(12)) . '.' . $extension;
        $file->moveTo($dir . '/' . $name);

        return JsonResponse::success($response, [
            'url' => $this->appUrl . $this->storage['upload_url'] . '/' . date('Y/m') . '/' . $name,
        ], 201);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyProduct(Product $product, array $body): void
    {
        $this->validator->validate($body, [
            'name' => [new Assert\NotBlank(), new Assert\Length(max: 160)],
            'slug' => [new Assert\Length(max: 180), new Assert\Regex('/^[a-z0-9-]*$/', 'Use lowercase letters, numbers and dashes only.')],
            'short_description' => [new Assert\Length(max: 320)],
            'meta_title' => [new Assert\Length(max: 160)],
            'meta_description' => [new Assert\Length(max: 320)],
            'options' => [new Assert\NotBlank(message: 'Add at least one size / price option.'), new Assert\All([new Assert\Collection(fields: [
                'label' => [new Assert\NotBlank(), new Assert\Length(max: 120)],
                'price' => [new Assert\NotBlank(), new Assert\Type('integer'), new Assert\PositiveOrZero()],
            ], allowExtraFields: true)])],
        ]);

        $body['slug'] = trim((string) ($body['slug'] ?? '')) ?: Str::slug((string) $body['name']);
        $clash = $this->products->findBySlug($body['slug']);
        if ($clash !== null && $clash->getId() !== $product->getId()) {
            throw ApiException::validation('Please check the highlighted fields.', ['slug' => ['Another product already uses this URL slug.']]);
        }

        $product->fill($body);
        $product->syncOptions(array_values((array) $body['options']));
        if (array_key_exists('category_id', $body)) {
            $product->setCategory(empty($body['category_id']) ? null : $this->categories->findOrFail((string) $body['category_id']));
        }
        $this->products->save($product);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyCategory(Category $category, array $body): void
    {
        $this->validator->validate($body, [
            'name' => [new Assert\NotBlank(), new Assert\Length(max: 120)],
            'slug' => [new Assert\Length(max: 140), new Assert\Regex('/^[a-z0-9-]*$/', 'Use lowercase letters, numbers and dashes only.')],
            'meta_title' => [new Assert\Length(max: 160)],
            'meta_description' => [new Assert\Length(max: 320)],
        ]);

        $body['slug'] = trim((string) ($body['slug'] ?? '')) ?: Str::slug((string) $body['name']);
        $clash = $this->categories->findBySlug($body['slug']);
        if ($clash !== null && $clash->getId() !== $category->getId()) {
            throw ApiException::validation('Please check the highlighted fields.', ['slug' => ['Another category already uses this URL slug.']]);
        }

        $category->fill($body);
        $this->categories->save($category);
    }
}
