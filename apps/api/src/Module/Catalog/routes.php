<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use StagasBites\Module\Catalog\CatalogController;

return function (App $app): void {
    $app->group('/api/v1', function (RouteCollectorProxy $group): void {
        $group->get('/categories', [CatalogController::class, 'categories']);
        $group->get('/products', [CatalogController::class, 'products']);
        $group->get('/products/{slug}', [CatalogController::class, 'product']);
        $group->get('/settings', [CatalogController::class, 'settings']);
    });
};
