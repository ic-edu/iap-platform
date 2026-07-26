# System Upgrade Guide — iC.edu Assessment Platform (IAP)

## Minor & Major Release Upgrades
1. Backup database prior to upgrading (`./scripts/backup-db.sh`).
2. Run database migrations: `php artisan migrate --force`.
3. Clear and regenerate caches: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
