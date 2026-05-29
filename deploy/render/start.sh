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

# ── Variables critiques : forcées ici pour ne pas dépendre du dashboard Render ──
# (les valeurs render.yaml ne sont pas toujours injectées sur un service existant)
export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG=true               # temporaire — à remettre false après validation
export SESSION_DRIVER=file
export CACHE_STORE=file
export LOG_CHANNEL=stderr
export QUEUE_CONNECTION=sync
export FILESYSTEM_DISK=local
export BCRYPT_ROUNDS="${BCRYPT_ROUNDS:-12}"

# ── Base de données : force PostgreSQL ────────────────────────────────────────
export DB_CONNECTION=pgsql

# ── Diagnostic : affiche ce que Render injecte réellement ────────────────────
echo "==> Variables DB injectées par Render :"
echo "  DATABASE_URL = ${DATABASE_URL:-(non défini)}"
echo "  DB_HOST      = ${DB_HOST:-(non défini)}"
echo "  DB_PORT      = ${DB_PORT:-(non défini)}"
echo "  DB_DATABASE  = ${DB_DATABASE:-(non défini)}"
echo "  DB_USERNAME  = ${DB_USERNAME:-(non défini)}"
echo "  DB_PASSWORD  = ${DB_PASSWORD:+****(défini)}"

# ── Si DB_HOST absent mais DATABASE_URL disponible → DB_URL (Laravel pgsql) ──
# Render fournit DATABASE_URL pour les bases liées ; Laravel config/database.php
# lit DB_URL en priorité sur DB_HOST/DB_PORT/etc. dans la connexion pgsql.
if [ -z "$DB_HOST" ] && [ -n "$DATABASE_URL" ]; then
    echo "  → DB_HOST absent, utilisation de DATABASE_URL via DB_URL"
    export DB_URL="$DATABASE_URL"
fi

# ── Garde-fou : aucune variable DB → erreur claire ────────────────────────────
if [ -z "$DB_HOST" ] && [ -z "$DATABASE_URL" ]; then
    echo "========================================================"
    echo "  ERREUR FATALE : aucune variable de base de données !"
    echo "  Ajoutez ces variables dans Render > sitiame-capitale > Environment :"
    echo "    DB_HOST      (onglet Connect de sitiame-db)"
    echo "    DB_PORT      (généralement 5432)"
    echo "    DB_DATABASE  (onglet Connect de sitiame-db)"
    echo "    DB_USERNAME  (onglet Connect de sitiame-db)"
    echo "    DB_PASSWORD  (onglet Connect de sitiame-db)"
    echo "  OU liez la base via le Blueprint Render (render.yaml)."
    echo "========================================================"
    exit 1
fi

echo "==> Découverte des packages Composer..."
php artisan package:discover --ansi

echo "==> Migrations..."
php artisan migrate --force

echo "==> Seed utilisateurs système (updateOrCreate — idempotent)..."
php artisan db:seed --class=SystemBaseUsersSeeder --force

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
(
    sleep 12
    CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 http://localhost/)
    echo "====== DIAGNOSTIC APACHE : HTTP $CODE ======" >&2

    if [ "$CODE" != "200" ]; then
        # --- Afficher le log Laravel pour voir l'exception réelle ---
        LOG=/var/www/html/storage/logs/laravel.log
        if [ -f "$LOG" ]; then
            echo "--- laravel.log (50 dernières lignes) ---" >&2
            tail -50 "$LOG" >&2
        else
            echo "--- laravel.log absent (LOG_CHANNEL=stderr : erreur dans les logs au-dessus) ---" >&2
        fi
        # --- Corps brut de la réponse HTTP ---
        echo "--- Réponse HTTP brute (texte) ---" >&2
        curl -s --max-time 10 http://localhost/ 2>/dev/null \
            | php -r "
                \$h = stream_get_contents(STDIN);
                \$t = strip_tags(html_entity_decode(\$h, ENT_QUOTES|ENT_HTML5, 'UTF-8'));
                \$l = array_filter(array_map('trim', explode(\"\n\", \$t)));
                echo implode(\"\n\", array_slice(\$l, 0, 100));
            " 2>/dev/null >&2
    fi
    echo "====== FIN DIAGNOSTIC ======" >&2
) &

echo "==> Démarrage Apache..."
exec apache2-foreground
