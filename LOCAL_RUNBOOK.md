# Local Operations Runbook — iC.edu Assessment Platform (IAP)

## Daily Development Commands
```bash
# Start local web server
php artisan serve

# Run queue worker in terminal
php artisan queue:work

# Run cron scheduler
php artisan schedule:run

# Run automated tests
./vendor/bin/pest

# Run code style & static analysis
./vendor/bin/pint
./vendor/bin/phpstan analyse --memory-limit=512M
```

## Observability & Health Probes
- Full System Health: `http://127.0.0.1:8000/health`
- Readiness Probe: `http://127.0.0.1:8000/ready`
- Liveness Probe: `http://127.0.0.1:8000/live`
- Admin Monitoring Dashboard: `http://127.0.0.1:8000/admin/monitoring`
