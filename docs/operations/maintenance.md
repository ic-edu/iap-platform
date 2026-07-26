# Maintenance Operations Manual — iC.edu Assessment Platform (IAP)

## Maintenance Window Procedure
1. Enable maintenance mode: `php artisan down --secret="iap-maintenance"`
2. Perform system upgrades or DB migrations.
3. Bring site back online: `php artisan up`
