<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Builds the EntityManager. Shared by the DI container and the CLI (cli-config.php, bin/console.php).
 *
 * @param array<string, mixed> $settings
 */
return static function (array $settings): EntityManager {
    $isDev = (bool) $settings['app']['debug'];
    $varDir = dirname(__DIR__) . '/var';

    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [dirname(__DIR__) . '/src/Entity'],
        isDevMode: $isDev,
        proxyDir: $varDir . '/doctrine/proxies',
        cache: $isDev ? new ArrayAdapter() : new FilesystemAdapter('doctrine', 0, $varDir . '/cache'),
    );
    $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
    // stripe_events is a plain DBAL table with no entity; keep schema tools from proposing to drop it.
    $config->setSchemaAssetsFilter(static fn (string|object $asset): bool => (is_string($asset) ? $asset : $asset->getName()) !== 'stripe_events');

    $connection = DriverManager::getConnection($settings['database'], $config);

    return new EntityManager($connection, $config);
};
