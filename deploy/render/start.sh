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

# ── Valeurs par défaut (si les env vars Render ne sont pas injectées) ─────────
: "${APP_ENV:=production}"
: "${APP_DEBUG:=false}"
: "${SESSION_DRIVER:=file}"
: "${CACHE_STORE:=file}"
: "${LOG_CHANNEL:=stderr}"
: "${QUEUE_CONNECTION:=sync}"
: "${FILESYSTEM_DISK:=local}"

echo "==> Découverte des packages Composer..."
php artisan package:discover --ansi

echo "==> Migrations..."
php artisan migrate --force

echo "==> Cache config..."
php artisan config:cache

echo "==> Config check..."
php -r "
\$c = include '/var/www/html/bootstrap/cache/config.php';
echo 'app.debug      = ' . (\$c['app']['debug'] ? 'true' : 'false') . PHP_EOL;
echo 'app.env        = ' . \$c['app']['env'] . PHP_EOL;
echo 'session.driver = ' . \$c['session']['driver'] . PHP_EOL;
echo 'log.channel    = ' . \$c['logging']['default'] . PHP_EOL;
echo 'app.key (début)= ' . substr(\$c['app']['key'], 0, 30) . '...' . PHP_EOL;
" 2>&1

echo "==> Cache routes..."
php artisan route:cache 2>&1 || { echo "route:cache KO (closures) → route:clear"; php artisan route:clear; }

echo "==> Cache vues..."
php artisan view:cache 2>&1 || { echo "view:cache KO → ignoré"; true; }

echo "==> Cache événements..."
php artisan event:cache 2>&1 || true

echo "==> Lien storage..."
php artisan storage:link 2>/dev/null || true

echo "==> Permissions runtime (www-data)..."
chown -R www-data:www-data /var/www/html/bootstrap/cache /var/www/html/storage

echo "==> Vérification config Apache..."
apachectl configtest 2>&1 || true

# ── Diagnostic réponse Apache (arrière-plan) ─────────────────────────────────
# Ce job survit au exec ci-dessous ; ses sorties apparaissent dans les logs Render.
(
    sleep 12
    CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 http://localhost/)
    echo "====== DIAGNOSTIC APACHE : HTTP $CODE ======" >&2
    if [ "$CODE" != "200" ]; then
        echo "--- Corps de la réponse (500) ---" >&2
        curl -s --max-time 10 http://localhost/ 2>/dev/null \
            | php -r "
                \$html = stream_get_contents(STDIN);
                // Extraire le texte visible
                \$text = strip_tags(html_entity_decode(\$html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                // Nettoyer les lignes vides multiples
                \$lines = array_filter(array_map('trim', explode(\"\n\", \$text)));
                echo implode(\"\n\", array_slice(\$lines, 0, 80));
            " 2>/dev/null >&2
    fi
    echo "====== FIN DIAGNOSTIC ======" >&2
) &

echo "==> Démarrage Apache..."
exec apache2-foreground
