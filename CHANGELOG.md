# Changelog — iC.edu Assessment Platform (IAP)

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.8.0] - 2026-07-26 (Sprint 8: Commerce & Billing Platform)
### Added
- Modular Commerce domain (`app/Modules/Commerce/`) following Lightweight Domain-Driven Design (DDD).
- `ProductCategory`, `Product`, `Coupon`, `Order`, `OrderItem`, `Invoice`, `Payment`, `Subscription` domain models.
- `PricingEngine.php` for fixed/promotional pricing, percentage/fixed discounts, and tax calculations.
- `CartEngine.php` for shopping cart management and automated totals calculation.
- `CheckoutEngine.php` for cart validation, order generation, and invoice generation.
- `InvoiceEngine.php` for ULID invoice number generation (`INV-YYYYMMDD-XXXX`).
- `BillingEngine.php` for payment confirmation, cancellation, and refunds.
- `CouponEngine.php` for promo code validation and usage tracking.
- `SubscriptionEngine.php` for managing monthly, quarterly, yearly, and lifetime licenses.
- `PaymentGatewayInterface` with `ManualTransferGateway` (Production Ready) and stubs for Midtrans, Xendit, and Stripe.
- Listener `ActivateEnrollmentOnPayment` automatically activating Course Enrollments and Test Assignments upon `PaymentConfirmed`.
- Product Documentation Suite in `docs/product/` (`vision.md`, `user-personas.md`, `business-rules.md`, `feature-matrix.md`, `acceptance-criteria.md`, `release-plan.md`).
- Architectural docs (`docs/architecture/`): `commerce.md`, `billing.md`, `payment-gateway.md`, `pricing.md`, `subscription.md`.
- Architecture Decision Records (`docs/adr/`): `0011`, `0012`, `0013`, `0014`.
- 20+ feature tests in `tests/Feature/CommerceAndBillingTest.php` bringing total test suite to **110+ Tests Passed**.

## [0.7.0] - 2026-07-26 (Sprint 7: Platform Operations & Learning Management Foundation)
### Added
- `EnrollmentEngine.php` and `EnrollmentStatus` enum (`active`, `completed`, `cancelled`, `suspended`).
- `AssignmentEngine.php` for assigning tests to users, courses, and batches.
- `SchedulingEngine.php` for test window validation and candidate attempt limits.
- `CalendarService.php` for upcoming assessment calendar widgets.
- `NotificationService.php` and `ReminderEngine.php` for test reminders.
- `SystemHealthService.php` monitoring database, storage, queue health, cache, and system versions.
- `ImportEngine.php` and `BatchOperationService.php` for CSV candidate import and bulk operations.
- `TimelineService.php` for activity timeline tracking.
- ADRs 0008, 0009, 0010 and architectural documentation.

## [0.6.0] - 2026-07-26 (Sprint 6: Results, Certificate & Analytics)
### Added
- `ResultEngine.php` for score computation, grade assignment, and pass/fail status.
- `CertificateEngine.php` generating unique certificate numbers (`CERT-YYYYMMDD-XXXX`) and verification codes (`VRF-XXXX-XXXX`).
- `VerificationService.php` and Public Verification Portal at `/verify`.
- `AnalyticsEngine.php` and `ItemAnalysisService.php` for psychometric difficulty index.
- `ExportService.php` and `GenerateCertificatePdfJob.php` queue job.
- GitHub Actions CI workflow in `.github/workflows/ci.yml`.

## [0.5.0] - 2026-07-26 (Sprint 5: CBT Engine & Student Examination Interface)
### Added
- Candidate Examination Portal.
- 8 Core Assessment Engines: `AssessmentEngine`, `AttemptEngine`, `TimerEngine`, `NavigationEngine`, `AutoSaveEngine`, `RandomizationEngine`, `ScoringEngine`, `ReviewEngine`.
- Anti-cheating audit trail foundation.

## [0.4.0] - 2026-07-26 (Sprint 4: Question Bank & Test Authoring)
### Added
- Passage management and 12 question types.
- Rich Question Editor and Test Builder.
- `MediaService` abstraction layer.

## [0.3.0] - 2026-07-26 (Sprint 3: Admin Platform Foundation)
### Added
- Blade + Tailwind Enterprise Admin Layout.
- Dynamic Modular Navigation (`NavigationService`).
- `DashboardMetricsService` with 5-minute cache.

## [0.2.0] - 2026-07-26 (Sprint 2: Core Database)
### Added
- Core database schema with ULID primary keys.
- Models, Enums, Seeders across 7 modules.

## [0.1.0] - 2026-07-26 (Sprint 1: Foundation)
### Added
- Initial project foundation with Laravel 13, Breeze, Spatie Permission, Pint, Larastan, Pest.
