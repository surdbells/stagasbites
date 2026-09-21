#!/usr/bin/env bash
# API deploy for the aaPanel server. The storefront is NOT built here: Cloudflare Pages
# builds and publishes it from GitHub on every push to main.
#
#   sudo bash /www/wwwroot/stagasbites/apps/api/bin/deploy.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
PHP_VERSION="${PHP_VERSION:-83}"
PHP="/www/server/php/${PHP_VERSION}/bin/php"
FPM_USER="${FPM_USER:-www}"
[ -x "$PHP" ] || PHP="$(command -v php)"

cd "$ROOT"
git pull --ff-only origin main

cd "$ROOT/apps/api"
"$PHP" "$(command -v composer)" install --no-interaction --no-dev --optimize-autoloader
"$PHP" bin/console.php migrations:migrate --no-interaction

# Compiled container, Doctrine proxies and caches must be rebuilt, and must belong to the FPM user.
rm -rf var/cache/* var/doctrine/*
mkdir -p var/cache var/log var/doctrine public/uploads
chown -R "$FPM_USER":"$FPM_USER" var public/uploads

# aaPanel runs one FPM master per PHP version; reloading it also clears opcache.
/etc/init.d/php-fpm-"$PHP_VERSION" reload

curl -fsS "https://${API_HOST:-api.stagasbites.ca}/api/health" || echo "WARNING: health check failed"
echo
echo "API deployed."
