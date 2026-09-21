<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;
use StagasBites\Helper\JsonResponse;

return function (App $app): void {
    $app->get('/api/health', function (Response $response) use ($app): Response {
        try {
            $app->getContainer()->get(Connection::class)->executeQuery('SELECT 1');
            $database = 'ok';
        } catch (Throwable) {
            $database = 'unreachable';
        }

        return JsonResponse::success($response, ['status' => $database === 'ok' ? 'ok' : 'degraded', 'database' => $database], $database === 'ok' ? 200 : 503);
    });
};
