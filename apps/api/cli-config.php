<?php

declare(strict_types=1);

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

Dotenv::createImmutable(__DIR__)->safeLoad();

$settings = require __DIR__ . '/config/settings.php';
$entityManager = (require __DIR__ . '/config/doctrine.php')($settings);

return DependencyFactory::fromEntityManager(
    new PhpFile(__DIR__ . '/config/migrations.php'),
    new ExistingEntityManager($entityManager),
);
