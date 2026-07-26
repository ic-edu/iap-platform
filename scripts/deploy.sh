#!/usr/bin/env bash
set -e

echo "🚀 Starting Production Deployment for iC.edu Assessment Platform (IAP)..."

# 1. Enable Maintenance Mode
php artisan down --secret="iap-deploy-secret-2026" || true

# 2. Pull latest code
git pull origin main

# 3. Install composer dependencies
composer install --no-dev --optimize-autoloader

# 4. Run Database Migrations
php artisan migrate --force

# 5. Clear and Cache Configurations, Routes, and Views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Build Production Frontend Assets
npm run build

# 7. Restart Queue Workers
php artisan queue:restart

# 8. Disable Maintenance Mode
php artisan up

echo "✅ Production Deployment Completed Successfully!"
