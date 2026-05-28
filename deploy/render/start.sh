#!/bin/bash
set -e

# ── Vérification et génération de APP_KEY ─────────────────────────────────────
if [ -z "$APP_KEY" ] || [[ "$APP_KEY" != base64:* ]]; then
    GENERATED=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    echo ""
    echo "========================================================"
    echo "  ERREUR : APP_KEY manquant ou invalide."
    echo "  Copiez cette valeur dans Render > Environment > APP_KEY :"
    echo "  $GENERATED"
    echo "========================================================"
    echo ""
    # On continue avec la clé générée pour ce démarrage
    export APP_KEY="$GENERATED"
fi

echo "==> Test de boot Laravel..."
php -r "
define('LARAVEL_START', microtime(true));
require '/var/www/html/vendor/autoload.php';
\$app = require '/var/www/html/bootstrap/app.php';
echo 'Bootstrap OK' . PHP_EOL;
" 2>&1 || { echo "ERREUR BOOTSTRAP LARAVEL"; exit 1; }

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
