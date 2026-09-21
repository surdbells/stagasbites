<?php

declare(strict_types=1);

use Psr\Cache\CacheItemPoolInterface;
use Slim\App;
use StagasBites\Middleware\RateLimitMiddleware;
use StagasBites\Module\Inquiry\InquiryController;

return function (App $app): void {
    $app->post('/api/v1/inquiries', [InquiryController::class, 'create'])
        ->add(new RateLimitMiddleware($app->getContainer()->get(CacheItemPoolInterface::class), 5, 600, 'inquiry'));
};
