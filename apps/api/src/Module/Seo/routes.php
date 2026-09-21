<?php

declare(strict_types=1);

use Slim\App;
use StagasBites\Module\Seo\SeoController;

return function (App $app): void {
    $app->get('/robots.txt', [SeoController::class, 'robots']);
    $app->get('/sitemap.xml', [SeoController::class, 'sitemap']);

    // Must be registered last: every non-API GET falls through to the SPA shell.
    $app->get('/{path:(?!api/|uploads/).*}', [SeoController::class, 'spa']);
};
