<?php

declare(strict_types=1);

use Slim\App;
use StagasBites\Middleware\AuthMiddleware;
use StagasBites\Module\Account\AccountController;

return function (App $app): void {
    $app->get('/api/v1/account/orders', [AccountController::class, 'orders'])
        ->add($app->getContainer()->get(AuthMiddleware::class));
};
