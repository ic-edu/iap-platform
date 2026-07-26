# Changelog — iC.edu Assessment Platform (IAP)

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0-beta] - 2026-07-26 (Sprint 10: Production Readiness & v1.0 Beta Release)
### Added
- `SecurityHeadersMiddleware.php` applying CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- Health Check probes (`/health`, `/ready`, `/live`) in `HealthCheckController.php`.
- System Monitoring & Observability Dashboard (`/admin/monitoring`) in `MonitoringDashboardController.php`.
- Containerization artifacts (`Dockerfile`, `docker-compose.yml`, `Nginx default.conf`, `Supervisor supervisord.conf`, `.env.production.example`).
- Automated deployment script `scripts/deploy.sh` and database backup script `scripts/backup-db.sh`.
- Operational Manuals suite (`docs/operations/` — 8 manuals).
- User Documentation suite (`docs/user/` — 8 guides).
- User Acceptance Testing (UAT) package (`docs/uat/` — 9 documents).
- Technical Debt & Architectural Review (`docs/architecture/technical-debt.md`).
- Production Readiness automated test suite in `tests/Feature/ProductionReadinessAndSecurityTest.php` bringing test count to **150+ Pest Tests Passed**.

## [0.9.0] - 2026-07-26 (Sprint 9: API Platform & Integration Layer)
### Added
- REST API Version 1 endpoints (`/api/v1`) with uniform JSON response envelope.
- Sanctum Bearer Token authentication (`/api/v1/auth/login`, `logout`, `me`).
- 14 API Resources and `WebhookEngine.php` with HMAC-SHA256 signature generation (`X-IAP-Signature`).
- Integration Layer contracts & drivers (`app/Integrations/`).
- OpenAPI 3.1 Contract (`docs/api/openapi.yaml`) & Postman Collection (`docs/api/postman_collection.json`).

## [0.8.0] - 2026-07-26 (Sprint 8: Commerce & Billing Platform)
### Added
- Modular Commerce domain (`app/Modules/Commerce/`) following Lightweight Domain-Driven Design (DDD).

## [0.7.0] - 2026-07-26 (Sprint 7: Platform Operations & LM Foundation)
### Added
- Platform operations and Learning Management engines.

## [0.6.0] - 2026-07-26 (Sprint 6: Results, Certificate & Analytics)
### Added
- `ResultEngine.php`, `CertificateEngine.php`, `VerificationService.php`, `AnalyticsEngine.php`.

## [0.5.0] - 2026-07-26 (Sprint 5: CBT Engine & Student Examination Interface)
### Added
- Candidate Examination Portal and 8 Core Assessment Engines.

## [0.4.0] - 2026-07-26 (Sprint 4: Question Bank & Test Authoring)
### Added
- Question bank management, 12 question types, Test Builder.

## [0.3.0] - 2026-07-26 (Sprint 3: Admin Platform Foundation)
### Added
- Enterprise Admin Layout and dynamic navigation.

## [0.2.0] - 2026-07-26 (Sprint 2: Core Database)
### Added
- Database schema migrations and domain Eloquent models.

## [0.1.0] - 2026-07-26 (Sprint 1: Foundation)
### Added
- Project foundation with Laravel 13, Breeze, Spatie Permission.
