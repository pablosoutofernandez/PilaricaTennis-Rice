#!/bin/bash
# Pre-deploy de Railway: migraciones, administrador y cachés de Laravel.
set -e

# Primero fuera la configuración en caché: si no, se usarían variables de un despliegue anterior.
php artisan optimize:clear

# A qué base de datos se conecta (sin usuario ni contraseña), para revisarlo en los logs.
php -r '$u = parse_url((string) getenv("DB_URL")); echo "Base de datos: ".($u["host"] ?? getenv("DB_HOST") ?: "(sin DB_URL ni DB_HOST)").($u["path"] ?? "")."\n";'

php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
