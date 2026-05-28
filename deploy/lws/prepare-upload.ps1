# Préparation du déploiement LWS (à lancer en local avant envoi FTP)
# Usage : powershell -ExecutionPolicy Bypass -File deploy\lws\prepare-upload.ps1

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot\..\.. 

Write-Host "=== Build des assets front (Vite) ===" -ForegroundColor Cyan
if (Test-Path "package.json") {
    npm ci 2>$null; if ($LASTEXITCODE -ne 0) { npm install }
    npm run build
}

Write-Host "=== Optimisation Composer (production) ===" -ForegroundColor Cyan
composer install --no-dev --optimize-autoloader --no-interaction

Write-Host "=== Cache config (optionnel en local) ===" -ForegroundColor Cyan
php artisan config:clear
php artisan route:clear
php artisan view:clear

Write-Host ""
Write-Host "OK. Envoyez tout le projet sur LWS (sauf .git, node_modules, tests)." -ForegroundColor Green
Write-Host "Inclure : vendor/, public/build/, deploy/lws/htaccess-racine-public_html" -ForegroundColor Green
Write-Host "Sur le serveur : Terminal Web LWS → php artisan key:generate && php artisan migrate --force" -ForegroundColor Yellow
