#!/usr/bin/env php
<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command as Migrations;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use Dotenv\Dotenv;
use StagasBites\Command\CreateAdminCommand;
use StagasBites\Command\SeedCommand;
use Symfony\Component\Console\Application;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$container = (new ContainerBuilder())->addDefinitions(__DIR__ . '/../config/container.php')->build();
$entityManager = $container->get(EntityManagerInterface::class);

$migrations = DependencyFactory::fromEntityManager(
    new PhpFile(__DIR__ . '/../config/migrations.php'),
    new ExistingEntityManager($entityManager),
);

$cli = new Application("Staga's Bites console");
ConsoleRunner::addCommands($cli, new SingleManagerProvider($entityManager));
$cli->addCommands([
    new Migrations\MigrateCommand($migrations),
    new Migrations\StatusCommand($migrations),
    new Migrations\DiffCommand($migrations),
    new Migrations\GenerateCommand($migrations),
    new Migrations\ExecuteCommand($migrations),
    new Migrations\LatestCommand($migrations),
    new Migrations\ListCommand($migrations),
    new Migrations\VersionCommand($migrations),
    $container->get(SeedCommand::class),
    $container->get(CreateAdminCommand::class),
]);

$cli->run();
