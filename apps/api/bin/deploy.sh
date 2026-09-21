#!/usr/bin/env bash
# Pull, build and migrate. Run from the server: bash apps/api/bin/deploy.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$ROOT" && git pull origin main

cd "$ROOT/apps/api"
composer install --no-interaction --no-dev --optimize-autoloader
rm -rf var/cache/*
php bin/console.php migrations:migrate --no-interaction

cd "$ROOT/apps/web"
npm ci
npm run build

# Reload PHP-FPM so the compiled container and opcache pick up the new code.
kill -USR2 "$(pgrep -o php-fpm)" || true
echo "Deployed."
