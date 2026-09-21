<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use StagasBites\Middleware\CorsMiddleware;
use StagasBites\Module\Admin\AdminCatalogController;
use StagasBites\Module\Seo\SeoController;
use StagasBites\Repository\CategoryRepository;
use StagasBites\Repository\ProductRepository;
use StagasBites\Service\GoogleReviewsService;
use StagasBites\Service\JwtService;
use StagasBites\Service\MailService;
use StagasBites\Service\StripeService;
use StagasBites\Service\ValidationService;
use StagasBites\Service\ZeptoMailService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/*
 * Controllers, repositories and most services have only class-typed constructor
 * arguments and are autowired by PHP-DI. Only classes that need scalar/array
 * configuration are defined explicitly below.
 */
return [
    'settings' => fn (): array => require __DIR__ . '/settings.php',

    LoggerInterface::class => function (ContainerInterface $c): LoggerInterface {
        $config = $c->get('settings')['logging'];
        $dir = dirname($config['path']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return (new Logger('stagasbites'))->pushHandler(new StreamHandler($config['path'], Level::fromName(ucfirst(strtolower($config['level'])))));
    },

    EntityManagerInterface::class => fn (ContainerInterface $c): EntityManagerInterface => (require __DIR__ . '/doctrine.php')($c->get('settings')),

    Connection::class => fn (ContainerInterface $c): Connection => $c->get(EntityManagerInterface::class)->getConnection(),

    // Backs the rate limiter. Filesystem keeps the stack Redis-free; swap the adapter if you scale out.
    CacheItemPoolInterface::class => fn (): CacheItemPoolInterface => new FilesystemAdapter('app', 0, dirname(__DIR__) . '/var/cache'),

    CorsMiddleware::class => fn (ContainerInterface $c): CorsMiddleware => new CorsMiddleware($c->get('settings')['cors']['allowed_origins']),

    JwtService::class => fn (ContainerInterface $c): JwtService => new JwtService($c->get('settings')['jwt']),

    ZeptoMailService::class => fn (ContainerInterface $c): ZeptoMailService => new ZeptoMailService(
        $c->get('settings')['zeptomail'],
        $c->get(LoggerInterface::class),
    ),

    MailService::class => fn (ContainerInterface $c): MailService => new MailService(
        $c->get(ZeptoMailService::class),
        $c->get('settings')['app']['site_url'],
    ),

    GoogleReviewsService::class => fn (ContainerInterface $c): GoogleReviewsService => new GoogleReviewsService(
        $c->get('settings')['google'],
        $c->get(CacheItemPoolInterface::class),
        $c->get(LoggerInterface::class),
    ),

    StripeService::class => fn (ContainerInterface $c): StripeService => new StripeService(
        $c->get('settings')['stripe'],
        $c->get('settings')['app']['site_url'],
    ),

    AdminCatalogController::class => fn (ContainerInterface $c): AdminCatalogController => new AdminCatalogController(
        $c->get(ProductRepository::class),
        $c->get(CategoryRepository::class),
        $c->get(ValidationService::class),
        $c->get('settings')['storage'],
        $c->get('settings')['app']['url'],
    ),

    SeoController::class => fn (ContainerInterface $c): SeoController => new SeoController(
        $c->get(ProductRepository::class),
        $c->get(CategoryRepository::class),
        $c->get('settings')['app']['site_url'],
        $c->get('settings')['app']['spa_index'],
    ),
];
