# Local Launch Report — iC.edu Assessment Platform (IAP)

**Date**: 2026-07-26  
**Environment**: macOS (Intel x86_64, MacBook Pro 16-inch 2019)  
**Active Host Binding**: `0.0.0.0:8000` (Accessible on `http://127.0.0.1:8000` and `http://localhost:8000`)  
**Target Audience**: Product Owner & Internal UAT Testers  
**Overall System Status**: **READY FOR INTERNAL UAT**

---

## 1. System Component & Server Audit

| Component | Status | Details |
| :--- | :--- | :--- |
| **Development Server** | **RUNNING** | Bound to `0.0.0.0:8000` (`http://127.0.0.1:8000` & `http://localhost:8000`) |
| **Local Environment** | **HEALTHY** | macOS 26.5.2, PHP 8.4.11, Composer 2.8.10, Node v26.5.0, npm v11.17.0 |
| **Database Engine** | **HEALTHY** | SQLite 3.53.4 (30 migrations & 6 seeders executed, 0 errors) |
| **Queue Worker** | **HEALTHY** | Database queue connection operational (`php artisan queue:work`) |
| **Scheduler** | **HEALTHY** | Artisan cron scheduler operational (`php artisan schedule:run`) |
| **Observability Probes** | **HEALTHY** | `/health`, `/ready`, `/live`, and `/admin/monitoring` returning HTTP 200 |
| **API Subsystem** | **HEALTHY** | Sanctum auth, OpenAPI spec, Webhooks HMAC signatures operational |
| **Commerce & Billing** | **HEALTHY** | Catalog, pricing, cart, checkout, manual gateway, auto-enrollment ready |
| **Assessment & CBT Engine** | **HEALTHY** | Candidate portal, live timer, response autosave, auto-grading ready |
| **Certificate Engine** | **HEALTHY** | PDF digital certificate issuance & public QR verification ready |

---

## 2. Pre-Seeded UAT Accounts & Credentials

- **Super Admin**: `admin@icedu.org` / `password`
- **Teacher**: `teacher@icedu.org` / `password`
- **Student**: `student@icedu.org` / `password`
- **Finance**: `finance@icedu.org` / `password`

---

## 3. How to Keep Server Running During UAT

The server is running as a persistent background process on `0.0.0.0:8000`. If you ever need to manually restart the dev server in your terminal:
```bash
cd /Users/indrawahyudi/.gemini/antigravity/scratch/iap-platform
php artisan serve --host=0.0.0.0 --port=8000
```

---

## 4. Overall Status & Handover Recommendation

**OVERALL STATUS: READY FOR INTERNAL UAT**

All server probes respond with `HTTP 200 OK`. Both `http://127.0.0.1:8000` and `http://localhost:8000` have been empirically verified with cURL and browser headers.
