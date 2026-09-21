<?php

declare(strict_types=1);

// Pickup slots, lead times and timestamps are all local to the kitchen.
date_default_timezone_set($_ENV["APP_TIMEZONE"] ?? "America/Toronto");

$env = static fn (string $key, mixed $default = null): mixed => $_ENV[$key] ?? $default;
$bool = static fn (string $key, bool $default): bool => filter_var($_ENV[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);

return [
    'app' => [
        'name' => $env('APP_NAME', "Staga's Bites"),
        'env' => $env('APP_ENV', 'production'),
        'debug' => $bool('APP_DEBUG', false),
        // Public URL of the API (used for uploaded file URLs).
        'url' => rtrim((string) $env('APP_URL', 'http://localhost:8090'), '/'),
        // Public URL of the storefront (used in emails, sitemap, Stripe redirects).
        'site_url' => rtrim((string) $env('SITE_URL', 'http://localhost:4200'), '/'),
        // Absolute path to the built Angular index.html, served with per-route SEO tags.
        'spa_index' => $env('SPA_INDEX_PATH', dirname(__DIR__, 2) . '/web/dist/web/browser/index.html'),
    ],
    'database' => [
        'driver' => 'pdo_pgsql',
        'host' => $env('DB_HOST', '127.0.0.1'),
        'port' => (int) $env('DB_PORT', 5432),
        'dbname' => $env('DB_NAME', 'stagasbites'),
        'user' => $env('DB_USER', 'stagasbites'),
        'password' => $env('DB_PASSWORD', ''),
        'charset' => 'utf8',
    ],
    'jwt' => [
        'secret' => (string) $env('JWT_SECRET', ''),
        'access_ttl' => (int) $env('JWT_ACCESS_TTL', 900),
        'refresh_ttl' => (int) $env('JWT_REFRESH_TTL', 604800),
        'algorithm' => 'HS256',
        'issuer' => 'stagasbites',
    ],
    'zeptomail' => [
        'api_key' => (string) $env('ZEPTOMAIL_API_KEY', ''),
        'from_email' => $env('ZEPTOMAIL_FROM_EMAIL', 'orders@stagasbites.ca'),
        'from_name' => $env('ZEPTOMAIL_FROM_NAME', "Staga's Bites"),
        'admin_email' => $env('ADMIN_NOTIFY_EMAIL', 'contact@stagasbites.ca'),
    ],
    'stripe' => [
        'secret_key' => (string) $env('STRIPE_SECRET_KEY', ''),
        'webhook_secret' => (string) $env('STRIPE_WEBHOOK_SECRET', ''),
    ],
    'google' => [
        'api_key' => (string) $env('GOOGLE_PLACES_API_KEY', ''),
        'place_id' => (string) $env('GOOGLE_PLACE_ID', 'ChIJ218RGCxlK4gRtgqbVBx8Nks'),
    ],
    'storage' => [
        'upload_dir' => dirname(__DIR__) . '/public/uploads',
        'upload_url' => '/uploads',
        'max_bytes' => 5 * 1024 * 1024,
    ],
    'cors' => [
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200')),
        ))),
    ],
    'logging' => [
        'level' => $env('LOG_LEVEL', 'info'),
        'path' => $env('LOG_PATH', dirname(__DIR__) . '/var/log/app.log'),
    ],
];
