# Modular Architecture Guide — iC.edu Assessment Platform (IAP)

IAP menerapkan **Modular Architecture** di bawah direktori `app/Modules/`. Setiap modul mengisolasi logika domain masing-masing.

---

## Struktur Modul Standard

Setiap modul dapat memiliki struktur berikut:

```
app/Modules/ModuleName/
├── Controllers/
├── Database/
│   ├── Factories/
│   ├── Migrations/
│   └── Seeders/
├── Enums/
│   ├── ModuleTypeEnum.php
├── Events/
├── Listeners/
├── Models/
├── Policies/
├── Requests/
├── Routes/
│   ├── api.php
│   └── web.php
├── Services/
└── Views/
```

---

## Otomasi OLEH `ModuleServiceProvider`

`App\Providers\ModuleServiceProvider` secara otomatis memuat:
1. **Routes**: `Routes/web.php` dan `Routes/api.php`
2. **Views**: Terdaftar dengan namespace `modulename::` (contoh: `question_bank::index`)
3. **Migrations**: Terdeteksi dari `Database/Migrations/`
