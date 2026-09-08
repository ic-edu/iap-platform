# IAP — PERMANENT DATABASE SAFETY & PRESERVATION POLICY

**Platform:** iC.edu Assessment Platform (IAP)  
**Effective Date:** September 2026  
**Status:** **MANDATORY PERMANENT SYSTEM POLICY**

---

### 1. ABSOLUTE PROHIBITIONS

All AI coding assistants, automated tools, and software developers **MUST NOT**:

1. **NEVER run destructive database commands** (`migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`) against the primary UAT database (`database/database.sqlite`) or staged recovery database (`database/recovery/uat-reconstructed.sqlite`).
2. **NEVER attempt bypasses** (e.g. `--force`, `ALLOW_DESTRUCTIVE_DB_COMMANDS`, or custom debug flags) to execute destructive commands on protected databases. The framework enforces a strict **zero-bypass block**.
3. **NEVER bootstrap local Laravel** (`php -r`, external CLI scripts) and execute destructive Artisan commands or manual test harness setups against physical application databases.
4. **NEVER invoke test case `setUp()` methods manually** outside isolated automated test runners (`./vendor/bin/pest` / `phpunit`).
5. **NEVER execute automated tests against physical database files**. Automated tests must exclusively run against in-memory SQLite (`:memory:`).

---

### 2. DATABASE PATH CLASSIFICATIONS & GOVERNANCE MATRIX

The application enforces path-aware safety classification via `App\Services\DatabaseSafetyService`:

| Classification | Path Patterns | Destructive Commands (`fresh`, `wipe`, `reset`) | Forward Migrations (`migrate`) |
| :--- | :--- | :---: | :---: |
| **`PROTECTED_UAT`** | `database/database.sqlite`<br>`database/recovery/uat-reconstructed.sqlite` | **STRICTLY BLOCKED 🔒**<br>*(Zero Bypass, No Force Flag)* | **ALLOWED WITH AUTO-SNAPSHOT 📸**<br>*(Requires Verified Snapshot)* |
| **`TEST_MEMORY`** | `:memory:` | **ALLOWED (In-Memory Testing) ✅** | **ALLOWED ✅** |
| **`DISPOSABLE`** | `database/disposable/*`<br>`database/recovery/test-*` | **ALLOWED (Disposable DB only) ⚠️** | **ALLOWED ✅** |
| **`UNKNOWN`** | Any unclassified database path | **STRICTLY BLOCKED (Fail Closed) 🔒** | **MANUAL SNAPSHOT REQUIRED ⚠️** |

---

### 3. TEST HARNESS ISOLATION RULES

All automated tests must satisfy strict pre-execution isolation assertions before any database operations occur:

```php
// Enforced in Tests\TestCase at createApplication(), setUp(), and beforeRefreshingDatabase()
if ($env !== 'testing' || $db !== ':memory:') {
    throw new \LogicException(
        "CRITICAL TEST HARNESS SAFETY VIOLATION: Automated tests must run with APP_ENV=testing and DB_DATABASE=:memory:."
    );
}
```

- When running tests locally:
  ```bash
  APP_ENV=testing DB_DATABASE=:memory: ./vendor/bin/pest
  ```
- If an experiment requires a persistent SQLite database, developers must explicitly create a file under `database/disposable/` and configure their environment to point to that disposable file.

---

### 4. ATOMIC SNAPSHOTS & FORWARD MIGRATION

Before applying forward migrations (`php artisan migrate`) on a protected database:
1. An atomic, integrity-checked snapshot is automatically created in `database/backups/` via SQLite `VACUUM INTO`.
2. The snapshot is validated using `PRAGMA integrity_check` and table verification.
3. If snapshot creation or verification fails, the migration is aborted immediately before modifying any schema.

Manual snapshots can be triggered at any time:
```bash
php artisan iap:db:snapshot --label=pre-maintenance
```

Safety configuration can be verified at any time:
```bash
php artisan iap:db:safety-status
```
