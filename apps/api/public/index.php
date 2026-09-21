<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Dotenv\Dotenv;

// PHP's built-in dev server: let it serve real files (uploads) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$builder = new ContainerBuilder();
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    $builder->enableCompilation(__DIR__ . '/../var/cache/container');
}
$builder->addDefinitions(__DIR__ . '/../config/container.php');

$app = Bridge::create($builder->build());

(require __DIR__ . '/../config/middleware.php')($app);
(require __DIR__ . '/../config/routes.php')($app);

$app->run();
