# Testing & Quality Assurance Guide — IAP

Pengujian pada IAP menggunakan **Pest**, **Laravel Pint**, dan **Larastan (PHPStan)**.

---

## Perintah Pengujian Standard

```bash
# 1. Reset Database & Seed
php artisan migrate:fresh --seed --force

# 2. Format Kode (Laravel Pint)
./vendor/bin/pint

# 3. Analisis Statis (Larastan Level 5)
./vendor/bin/phpstan analyse --memory-limit=512M

# 4. Pengujian Fitur & Unit (Pest)
./vendor/bin/pest
```

---

## Cakupan Test Suite Current
- `AuthenticationTest`: Pengujian login, registrasi, logout, verifikasi email, reset password.
- `CoreDatabaseTest`: Pengujian relasi ULID 7 modul core database.
- `AdminPlatformFoundationTest`: Pengujian navigasi modular, activity logger, settings service, dan notifikasi.
- `QuestionBankAndTestAuthoringTest`: Pengujian Question Bank, Passage, Media Service, dan Test Builder.
