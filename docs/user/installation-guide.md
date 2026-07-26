# Installation Guide — iC.edu Assessment Platform (IAP)

## Requirements
- PHP 8.3 or higher
- MySQL 8.0+ / PostgreSQL 14+
- Composer 2.7+
- Node.js 20+ & npm 10+

## Local Installation
```bash
git clone <repository_url> iap-platform
cd iap-platform
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build
php artisan serve
```
