#!/bin/bash
set -e

echo "==> Vérification APP_KEY..."
if [ -z "$APP_KEY" ]; then
    echo "ERREUR : APP_KEY non défini. Ajoutez-le dans les variables Render."
    exit 1
fi

echo "==> Découverte des packages Composer..."
php artisan package:discover --ansi

echo "==> Migrations..."
php artisan migrate --force

echo "==> Cache config / routes / vues..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Lien storage..."
php artisan storage:link 2>/dev/null || true

echo "==> Démarrage Apache..."
exec apache2-foreground
