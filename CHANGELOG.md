# Changelog — iC.edu Assessment Platform (IAP)

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.9.0] - 2026-07-26 (Sprint 9: API Platform & Integration Layer)
### Added
- REST API Version 1 endpoints (`/api/v1`) with uniform JSON response envelope.
- Sanctum Bearer Token authentication (`/api/v1/auth/login`, `logout`, `me`).
- 14 API Resources for User, Course, Enrollment, QuestionBank, Question, Test, Attempt, Result, Certificate, Product, Order, Invoice, Payment, Subscription.
- `WebhookEngine.php` with HMAC-SHA256 signature generation (`X-IAP-Signature`), delivery logs, and retry handling.
- Integration Layer contracts & drivers (`app/Integrations/`) for Zoom, Google Calendar, WhatsApp, SMS, Email, Payment, and Storage.
- `ApiAuditLogMiddleware.php` for tracking API latency, user token, IP, and execution details in `ActivityLog`.
- OpenAPI 3.1 Contract (`docs/api/openapi.yaml`) & Postman Collection (`docs/api/postman_collection.json`).
- SDK Preparation structure and guides (`sdk/php/`, `sdk/javascript/`, `sdk/flutter/`).
- Architectural docs (`docs/architecture/`): `api-platform.md`, `webhooks.md`, `integrations.md`.
- Architecture Decision Records (`docs/adr/`): `0015`, `0016`, `0017`, `0018`.
- 20+ new feature tests in `tests/Feature/ApiPlatformAndIntegrationTest.php` bringing total test suite to **130+ Tests Passed**.

## [0.8.0] - 2026-07-26 (Sprint 8: Commerce & Billing Platform)
### Added
- Modular Commerce domain (`app/Modules/Commerce/`) following Lightweight Domain-Driven Design (DDD).
- `ProductCategory`, `Product`, `Coupon`, `Order`, `OrderItem`, `Invoice`, `Payment`, `Subscription` domain models.
- `PricingEngine.php` for fixed/promotional pricing, percentage/fixed discounts, and tax calculations.
- `CartEngine.php`, `CheckoutEngine.php`, `InvoiceEngine.php`, `BillingEngine.php`, `CouponEngine.php`, `SubscriptionEngine.php`.
- `PaymentGatewayInterface` with `ManualTransferGateway` (Production Ready) and stubs for Midtrans, Xendit, and Stripe.
- Listener `ActivateEnrollmentOnPayment` automatically activating Course Enrollments and Test Assignments upon `PaymentConfirmed`.
- Product Documentation Suite in `docs/product/` (`vision.md`, `user-personas.md`, `business-rules.md`, `feature-matrix.md`, `acceptance-criteria.md`, `release-plan.md`).

## [0.7.0] - 2026-07-26 (Sprint 7: Platform Operations & Learning Management Foundation)
### Added
- `EnrollmentEngine.php`, `AssignmentEngine.php`, `SchedulingEngine.php`, `CalendarService.php`, `SystemHealthService.php`, `ImportEngine.php`.

## [0.6.0] - 2026-07-26 (Sprint 6: Results, Certificate & Analytics)
### Added
- `ResultEngine.php`, `CertificateEngine.php`, `VerificationService.php`, `AnalyticsEngine.php`, `ExportService.php`.

## [0.5.0] - 2026-07-26 (Sprint 5: CBT Engine & Student Examination Interface)
### Added
- Candidate Examination Portal and 8 Core Assessment Engines.

## [0.4.0] - 2026-07-26 (Sprint 4: Question Bank & Test Authoring)
### Added
- Passage management, 12 question types, Rich Question Editor, Test Builder.

## [0.3.0] - 2026-07-26 (Sprint 3: Admin Platform Foundation)
### Added
- Blade + Tailwind Enterprise Admin Layout, Dynamic Modular Navigation.

## [0.2.0] - 2026-07-26 (Sprint 2: Core Database)
### Added
- Core database schema with ULID primary keys.

## [0.1.0] - 2026-07-26 (Sprint 1: Foundation)
### Added
- Initial project foundation with Laravel 13, Breeze, Spatie Permission, Pint, Larastan, Pest.
