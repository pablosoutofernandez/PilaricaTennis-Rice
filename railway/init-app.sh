#!/bin/bash
# Pre-deploy de Railway: migraciones, administrador y cachés de Laravel.
set -e

php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
