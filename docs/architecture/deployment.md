# Deployment & Operations Guide — IAP

Panduan deployment dan operasi produksi untuk iC.edu Assessment Platform.

---

## Langkah Deployment Standard

1. **Environment Setup**:
   Copy `.env.example` ke `.env` dan konfigurasikan database SQLite/MySQL/PostgreSQL, `APP_KEY`, `APP_URL`, dan mail credentials.

2. **Jalankan Migrasi & Seeder Production**:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=RolesAndPermissionsSeeder --force
   ```

3. **Optimasi Production Cache**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. **Build Frontend Assets**:
   ```bash
   npm install
   npm run build
   ```
