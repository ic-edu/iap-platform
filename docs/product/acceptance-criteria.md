# Acceptance Criteria & Quality Gates — iC.edu Assessment Platform (IAP)

## Quality Gate Checklist

1. **Database Migrations & Seeders**:
   - `php artisan migrate:fresh --seed --force` must run with 0 migration or seeding errors.

2. **Code Formatting (Pint)**:
   - `./vendor/bin/pint` must pass with 0 style violations.

3. **Static Analysis (Larastan/PHPStan)**:
   - `./vendor/bin/phpstan analyse --memory-limit=512M` must pass at Level 5 with **0 errors**.

4. **Automated Test Suite (Pest)**:
   - `./vendor/bin/pest` must pass all tests (Sprint 8 Target: **110+ Tests Passed**).

5. **Continuous Integration**:
   - GitHub Actions workflow (`.github/workflows/ci.yml`) must build cleanly on develop branch.
