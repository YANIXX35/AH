#!/bin/bash
set -e

# ── Vérification APP_KEY ──────────────────────────────────────────────────────
if [ -z "$APP_KEY" ] || [[ "$APP_KEY" != base64:* ]]; then
    GENERATED=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    echo "========================================================"
    echo "  APP_KEY manquant/invalide → clé générée :"
    echo "  $GENERATED"
    echo "  Ajoutez-la dans Render > Environment > APP_KEY"
    echo "========================================================"
    export APP_KEY="$GENERATED"
fi

echo "==> Découverte des packages Composer..."
php artisan package:discover --ansi

echo "==> Migrations..."
php artisan migrate --force

echo "==> Cache config..."
php artisan config:cache

echo "==> Config check..."
php -r "
\$c = include '/var/www/html/bootstrap/cache/config.php';
echo 'app.debug    = ' . (\$c['app']['debug'] ? 'true' : 'false') . PHP_EOL;
echo 'session.driver = ' . \$c['session']['driver'] . PHP_EOL;
echo 'app.key      = ' . substr(\$c['app']['key'], 0, 30) . '...' . PHP_EOL;
" 2>&1

echo "==> Cache routes (fallback:clear si closures)..."
php artisan route:cache 2>&1 || { echo "route:cache KO → route:clear"; php artisan route:clear; }

echo "==> Cache vues..."
php artisan view:cache 2>&1 || { echo "view:cache KO → ignoré"; true; }

echo "==> Lien storage..."
php artisan storage:link 2>/dev/null || true

# ── Diagnostic requête HTTP ───────────────────────────────────────────────────
echo "==> Diagnostic requête HTTP..."
php -d display_errors=1 -r "
define('LARAVEL_START', microtime(true));
\$_SERVER = [
    'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/',
    'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost',
    'SERVER_PORT' => '80', 'SCRIPT_NAME' => '/index.php',
    'PHP_SELF' => '/index.php', 'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_ACCEPT' => 'text/html', 'HTTPS' => 'off',
    'DOCUMENT_ROOT' => '/var/www/html/public',
];
\$_GET = []; \$_POST = []; \$_COOKIE = []; \$_FILES = [];
ob_start();
try {
    require '/var/www/html/public/index.php';
    \$body = ob_get_clean();
    \$status = http_response_code();
    echo 'HTTP status: ' . \$status . PHP_EOL;
    echo 'Body (' . strlen(\$body) . ' bytes): ' . substr(strip_tags(\$body), 0, 400) . PHP_EOL;
} catch (Throwable \$e) {
    ob_end_clean();
    echo 'EXCEPTION : ' . get_class(\$e) . PHP_EOL;
    echo 'MESSAGE   : ' . \$e->getMessage() . PHP_EOL;
    echo 'FILE      : ' . \$e->getFile() . ':' . \$e->getLine() . PHP_EOL;
    foreach (array_slice(\$e->getTrace(), 0, 5) as \$i => \$t) {
        echo '  #' . \$i . ' ' . (\$t['file'] ?? '?') . ':' . (\$t['line'] ?? '?') . PHP_EOL;
    }
}
" 2>&1 || true

echo "==> Démarrage Apache..."
exec apache2-foreground
