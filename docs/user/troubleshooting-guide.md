# Troubleshooting Guide — iC.edu Assessment Platform (IAP)

## Common Issues & Fixes
- **Permission Denied for Storage**: Run `chmod -R 775 storage bootstrap/cache`.
- **API 401 Unauthorized**: Ensure Bearer token header is sent (`Authorization: Bearer <TOKEN>`).
- **Migration Error**: Run `php artisan migrate:fresh --seed --force`.
