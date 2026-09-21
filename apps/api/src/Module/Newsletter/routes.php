<?php

declare(strict_types=1);

use Psr\Cache\CacheItemPoolInterface;
use Slim\App;
use StagasBites\Entity\UserRole;
use StagasBites\Middleware\AuthMiddleware;
use StagasBites\Middleware\RateLimitMiddleware;
use StagasBites\Middleware\RoleMiddleware;
use StagasBites\Module\Newsletter\NewsletterController;

return function (App $app): void {
    $container = $app->getContainer();

    $app->post('/api/v1/newsletter', [NewsletterController::class, 'subscribe'])
        ->add(new RateLimitMiddleware($container->get(CacheItemPoolInterface::class), 5, 600, 'newsletter'));
    $app->post('/api/v1/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe']);

    $app->get('/api/v1/admin/subscribers', [NewsletterController::class, 'index'])
        ->add(new RoleMiddleware(UserRole::ADMIN))
        ->add($container->get(AuthMiddleware::class));
};
