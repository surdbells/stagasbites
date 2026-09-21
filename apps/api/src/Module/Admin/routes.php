<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use StagasBites\Entity\UserRole;
use StagasBites\Middleware\AuthMiddleware;
use StagasBites\Middleware\RoleMiddleware;
use StagasBites\Module\Admin\AdminCatalogController;
use StagasBites\Module\Admin\AdminOrderController;

return function (App $app): void {
    $container = $app->getContainer();

    $app->group('/api/v1/admin', function (RouteCollectorProxy $group): void {
        $group->get('/dashboard', [AdminOrderController::class, 'dashboard']);

        $group->get('/orders', [AdminOrderController::class, 'listOrders']);
        $group->get('/orders/{id}', [AdminOrderController::class, 'showOrder']);
        $group->patch('/orders/{id}/status', [AdminOrderController::class, 'updateOrderStatus']);

        $group->get('/products', [AdminCatalogController::class, 'listProducts']);
        $group->post('/products', [AdminCatalogController::class, 'createProduct']);
        $group->get('/products/{id}', [AdminCatalogController::class, 'showProduct']);
        $group->put('/products/{id}', [AdminCatalogController::class, 'updateProduct']);
        $group->delete('/products/{id}', [AdminCatalogController::class, 'deleteProduct']);

        $group->get('/categories', [AdminCatalogController::class, 'listCategories']);
        $group->post('/categories', [AdminCatalogController::class, 'createCategory']);
        $group->put('/categories/{id}', [AdminCatalogController::class, 'updateCategory']);
        $group->delete('/categories/{id}', [AdminCatalogController::class, 'deleteCategory']);

        $group->post('/uploads', [AdminCatalogController::class, 'upload']);

        $group->get('/coupons', [AdminOrderController::class, 'listCoupons']);
        $group->post('/coupons', [AdminOrderController::class, 'saveCoupon']);
        $group->put('/coupons/{id}', [AdminOrderController::class, 'saveCoupon']);
        $group->delete('/coupons/{id}', [AdminOrderController::class, 'deleteCoupon']);

        $group->put('/settings', [AdminOrderController::class, 'updateSettings']);
    })
        ->add(new RoleMiddleware(UserRole::ADMIN))
        ->add($container->get(AuthMiddleware::class));
};
