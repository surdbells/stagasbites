<?php

declare(strict_types=1);

use Psr\Cache\CacheItemPoolInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use StagasBites\Middleware\AuthMiddleware;
use StagasBites\Middleware\RateLimitMiddleware;
use StagasBites\Module\Auth\AuthController;

return function (App $app): void {
    $container = $app->getContainer();
    $limit = static fn (string $prefix, int $max, int $window): RateLimitMiddleware => new RateLimitMiddleware($container->get(CacheItemPoolInterface::class), $max, $window, $prefix);

    $app->group('/api/v1/auth', function (RouteCollectorProxy $group) use ($container, $limit): void {
        $group->post('/register', [AuthController::class, 'register'])->add($limit('register', 5, 600));
        $group->post('/login', [AuthController::class, 'login'])->add($limit('login', 10, 60));
        $group->post('/refresh', [AuthController::class, 'refresh']);
        $group->post('/logout', [AuthController::class, 'logout']);
        $group->post('/forgot-password', [AuthController::class, 'forgotPassword'])->add($limit('forgot', 5, 600));
        $group->post('/reset-password', [AuthController::class, 'resetPassword'])->add($limit('reset', 10, 600));

        $group->get('/me', [AuthController::class, 'me'])->add($container->get(AuthMiddleware::class));
        $group->put('/me', [AuthController::class, 'updateMe'])->add($container->get(AuthMiddleware::class));
    });
};
