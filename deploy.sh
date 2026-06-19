#!/bin/bash
set -e
cd /home/ccarlos/finanzas
echo '🔄 Jalando cambios de GitHub...'
git pull origin main
echo '🧹 Limpiando cache...'
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
echo '✅ Deploy completado.'
