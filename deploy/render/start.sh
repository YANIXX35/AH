#!/bin/bash
set -e

echo "==> Découverte des packages Composer..."
php artisan package:discover --ansi

echo "==> Migrations..."
php artisan migrate --force

echo "==> Cache config / routes / vues..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Lien storage..."
php artisan storage:link --quiet 2>/dev/null || true

echo "==> Démarrage Apache..."
exec apache2-foreground
