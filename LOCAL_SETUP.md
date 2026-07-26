# Local macOS Setup Guide — iC.edu Assessment Platform (IAP)

## Machine Environment
- **Host OS**: macOS (Intel x86_64 / Apple Silicon)
- **PHP Version**: PHP 8.4+ (Homebrew)
- **Node.js**: Node v20+ / v26+ (Homebrew)
- **Composer**: Composer v2.8+

## First-Time Setup Instructions
```bash
# 1. Clone repository & enter workspace
cd /Users/indrawahyudi/.gemini/antigravity/scratch/iap-platform

# 2. Install PHP & Node dependencies
composer install
/usr/local/bin/npm install

# 3. Environment configuration
cp .env.example .env
php artisan key:generate

# 4. Storage symlink & asset compilation
php artisan storage:link
/usr/local/bin/npm run build

# 5. Database migrations & seeders
php artisan migrate:fresh --seed --force

# 6. Run local server
php artisan serve
```
Access application at `http://127.0.0.1:8000`.
