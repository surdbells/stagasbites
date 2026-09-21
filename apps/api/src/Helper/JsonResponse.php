<?php

declare(strict_types=1);

namespace StagasBites\Helper;

use Psr\Http\Message\ResponseInterface as Response;

final class JsonResponse
{
    public static function success(Response $response, mixed $data = null, int $status = 200, ?string $message = null): Response
    {
        $payload = ['success' => true, 'data' => $data];
        if ($message !== null) {
            $payload['message'] = $message;
        }

        return self::write($response, $payload, $status);
    }

    /**
     * @param array<string, list<string>> $errors
     */
    public static function error(Response $response, string $message, int $status = 400, array $errors = []): Response
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return self::write($response, $payload, $status);
    }

    /**
     * @param list<mixed> $items
     */
    public static function paginated(Response $response, array $items, int $total, int $page, int $perPage): Response
    {
        return self::write($response, [
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
            ],
        ], 200);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function write(Response $response, array $payload, int $status): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
