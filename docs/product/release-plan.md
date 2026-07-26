# Release Plan — iC.edu Assessment Platform (IAP)

- **v0.1.0-sprint1**: Foundation (Laravel 13, Breeze, Spatie, Modular Architecture)
- **v0.2.0-sprint2**: Core Database Schema (Academic, QuestionBank, Assessment, Certificate, Finance, CMS, Reporting)
- **v0.3.0-sprint3**: Admin Platform Foundation (Admin Layout, Metrics, Activity Logging)
- **v0.4.0-sprint4**: Question Bank & Test Authoring (12 Question Types, Passage, Test Builder)
- **v0.5.0-sprint5**: CBT Delivery Engine (Candidate Portal, Timer, Anti-Cheating, 8 Engines)
- **v0.6.0-sprint6**: Results, Certificate & Analytics (ResultEngine, Public `/verify`, CI Workflow)
- **v0.7.0-sprint7**: Platform Operations & LM Foundation (Enrollment, Scheduling, System Health, CHANGELOG)
- **v0.8.0-sprint8**: Commerce & Billing Platform (Products, Cart, Checkout, Invoices, Payment Abstraction, Auto-Enrollment)
- **v0.9.0-sprint9**: API Platform & Integration Layer (Sanctum Auth, REST v1, Webhook Engine, Integrations, OpenAPI 3.1)
- **Architecture & Beta Readiness Review (Pre-Sprint 10 Quality Gate)**:
  - Architectural consistency check across all 8 domain modules.
  - Query performance & caching audit.
  - Test coverage audit (>130 Pest tests passed).
  - Security audit (Sanctum token revocation, rate limiting, input validation).
  - Deployment & UAT readiness verification.
- **v1.0.0-sprint10**: Production Readiness & Beta Release (Hardening, Observability, Deployment Pipeline)
