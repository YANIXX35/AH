#!/bin/bash
# Script de déploiement LWS - à lancer sur le serveur via SSH après chaque mise à jour.
# Usage: bash deploy.sh
set -e

echo "=== 1. Récupération du code ==="
git pull origin master

# Le script vient potentiellement de se mettre à jour lui-même (git pull peut
# modifier deploy.sh) : on se relance dans un nouveau processus pour être sûr
# de lire la version fraîche du fichier plutôt qu'un mélange ancien/nouveau
# resté en mémoire dans le processus bash actuel.
if [ -z "$DEPLOY_SH_REEXEC" ]; then
    export DEPLOY_SH_REEXEC=1
    exec bash "$0" "$@"
fi

echo "=== 2. Migrations ==="
php artisan migrate --force

echo "=== 3. Vidage des caches Laravel ==="
php artisan config:clear
php artisan route:clear
php artisan cache:clear

echo "=== 4. Purge de l'OPcache PHP-FPM (plusieurs appels pour couvrir tous les workers) ==="
TOKEN=$( (grep "^OPCACHE_RESET_TOKEN=" .env || true) | cut -d '=' -f2-)
APP_URL=$( (grep "^APP_URL=" .env || true) | cut -d '=' -f2-)

if [ -z "$TOKEN" ]; then
    echo "ATTENTION : OPCACHE_RESET_TOKEN absent du .env, purge OPcache ignorée."
else
    COUNT=0
    while [ $COUNT -lt 10 ]; do
        curl -s "${APP_URL}/internal/opcache-reset?token=${TOKEN}" > /dev/null || true
        COUNT=$((COUNT + 1))
    done
    echo "Purge OPcache envoyée (10 appels)."
fi

echo "=== Déploiement terminé ==="
