#!/bin/bash
# Commandes à exécuter dans le Terminal Web LWS (chemin = racine du projet Laravel)
# Doc : https://aide.lws.fr/base/Hebergement-web-mutualise/Terminal-web/comment-gerer-laravel-avec-php-artisan-terminal-web

cd ~/www || cd ~/public_html || exit 1

php -v
# PHP 8.3+ requis pour cette application

composer install --no-dev --optimize-autoloader --no-interaction

php artisan key:generate --force
php artisan config:clear
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

chmod -R ug+rwx storage bootstrap/cache

echo "Deploy Laravel terminé."
