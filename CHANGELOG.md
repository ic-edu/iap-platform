# Changelog — iC.edu Assessment Platform (IAP)

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.7.0] - 2026-07-26 (Sprint 7)
### Added
- `EnrollmentEngine` & `EnrollmentStatus` enum for course student enrollment.
- `AssignmentEngine` for assigning tests to users, courses, and batches.
- `SchedulingEngine` for test time window and grace period validation.
- `CalendarService` & Candidate Dashboard calendar widget.
- `NotificationService` & `ReminderEngine` with event dispatchers.
- `SystemHealthService` monitoring storage, DB connection, PHP/Laravel versions, and queue status.
- `ImportEngine` for CSV pratinjau and student/teacher bulk imports.
- `BatchOperationService` for queue-driven bulk operations.
- `TimelineService` for per-user activity timeline tracking.
- 3 new ADRs (`0008`, `0009`, `0010`) and 6 architectural docs.

## [0.6.0] - 2026-07-26 (Sprint 6)
### Added
- `ResultEngine` for score, percentage, grade, and pass/fail evaluation.
- `CertificateEngine` & `CertificateStatus` enum for cert generation (`CERT-...` & `VRF-...`).
- Public Verification Portal at `/verify` (`VerificationService`).
- `AnalyticsEngine` & `ItemAnalysisService` for platform and question psychometrics.
- `ExportService` for CSV results export.
- GitHub Actions CI workflow (`.github/workflows/ci.yml`).

## [0.5.0] - 2026-07-26 (Sprint 5)
### Added
- CBT Assessment Delivery Engine (`AssessmentEngine`, `AttemptEngine`, `TimerEngine`, `NavigationEngine`, `AutoSaveEngine`, `RandomizationEngine`, `ScoringEngine`, `ReviewEngine`).
- Candidate Portal Dashboard & Distraction-free CBT Exam UI (`exam.blade.php`).
- Anti-Cheating Foundation (tab switch, blur, right-click block, violation logging).

## [0.4.0] - 2026-07-26 (Sprint 4)
### Added
- Question Bank CRUD, Passage Management, 12 Question Types, Tagging & Difficulty levels.
- Test Authoring & Builder with section rules.

## [0.3.0] - 2026-07-26 (Sprint 3)
### Added
- Enterprise Admin Platform Layout & Dynamic Modular Navigation.
- `DashboardMetricsService` & Event-driven Activity Logging.

## [0.2.0] - 2026-07-26 (Sprint 2)
### Added
- Core Database Schema (Academic, QuestionBank, Assessment, Certificate, Finance, CMS, Reporting).

## [0.1.0] - 2026-07-26 (Sprint 1)
### Added
- Laravel 13, Breeze, Modular Architecture (`app/Modules/`), Spatie Permissions, Pint, Larastan, Pest.
