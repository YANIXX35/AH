#!/bin/bash
set -e

# ── Vérification APP_KEY ──────────────────────────────────────────────────────
if [ -z "$APP_KEY" ] || [[ "$APP_KEY" != base64:* ]]; then
    GENERATED=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    echo "========================================================"
    echo "  APP_KEY manquant/invalide → clé générée pour ce boot :"
    echo "  $GENERATED"
    echo "  Ajoutez-la dans Render > Environment > APP_KEY"
    echo "========================================================"
    export APP_KEY="$GENERATED"
fi

echo "==> Test de boot Laravel..."
php -r "
define('LARAVEL_START', microtime(true));
require '/var/www/html/vendor/autoload.php';
\$app = require '/var/www/html/bootstrap/app.php';
echo 'Bootstrap OK' . PHP_EOL;
" 2>&1 || { echo "ERREUR BOOTSTRAP"; exit 1; }

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

# ── Diagnostic : simule une requête HTTP et capture l'exception ───────────────
echo "==> Diagnostic requête HTTP..."
php -d display_errors=1 -r "
define('LARAVEL_START', microtime(true));
\$_SERVER = [
    'REQUEST_METHOD'  => 'GET',
    'REQUEST_URI'     => '/',
    'HTTP_HOST'       => 'localhost',
    'SERVER_NAME'     => 'localhost',
    'SERVER_PORT'     => '80',
    'SCRIPT_NAME'     => '/index.php',
    'PHP_SELF'        => '/index.php',
    'HTTPS'           => 'off',
    'REMOTE_ADDR'     => '127.0.0.1',
    'HTTP_ACCEPT'     => 'text/html',
];
\$_GET = []; \$_POST = []; \$_COOKIE = []; \$_FILES = [];
ob_start();
try {
    require '/var/www/html/public/index.php';
    \$body = ob_get_clean();
    echo 'HTTP_STATUS: 200 ou redirect' . PHP_EOL;
    echo 'Body(' . strlen(\$body) . ' bytes): ' . substr(strip_tags(\$body), 0, 300) . PHP_EOL;
} catch (Throwable \$e) {
    ob_end_clean();
    echo 'EXCEPTION: ' . get_class(\$e) . PHP_EOL;
    echo 'MESSAGE: '   . \$e->getMessage() . PHP_EOL;
    echo 'FILE: '      . \$e->getFile() . ':' . \$e->getLine() . PHP_EOL;
}
" 2>&1 || true

echo "==> Démarrage Apache..."
exec apache2-foreground
