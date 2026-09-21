<?php

declare(strict_types=1);

use Psr\Cache\CacheItemPoolInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use StagasBites\Middleware\AuthMiddleware;
use StagasBites\Middleware\RateLimitMiddleware;
use StagasBites\Module\Checkout\CheckoutController;
use StagasBites\Service\JwtService;

return function (App $app): void {
    $container = $app->getContainer();
    $cache = $container->get(CacheItemPoolInterface::class);

    // Stripe calls this server-to-server: no auth, signature-verified inside the controller.
    $app->post('/api/v1/webhooks/stripe', [CheckoutController::class, 'webhook']);

    $app->group('/api/v1', function (RouteCollectorProxy $group) use ($container, $cache): void {
        $group->post('/checkout/quote', [CheckoutController::class, 'quote'])
            ->add(new RateLimitMiddleware($cache, 60, 60, 'quote'));
        $group->post('/checkout', [CheckoutController::class, 'place'])
            ->add(new AuthMiddleware($container->get(JwtService::class), optional: true))
            ->add(new RateLimitMiddleware($cache, 10, 600, 'checkout'));
        $group->get('/orders/{id}', [CheckoutController::class, 'show'])
            ->add(new RateLimitMiddleware($cache, 60, 60, 'order-view'));
    });
};
