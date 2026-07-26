# Project Roadmap — iC.edu Assessment Platform (IAP)

---

## 📌 Sprint Progress Summary

- [x] **Sprint 1 — Foundation** (`v0.1.0-sprint1`)
  - Laravel 13, Breeze, Modular Architecture (`app/Modules/Authentication`), Spatie Permission, Pint, Larastan, Pest.
- [x] **Sprint 2 — Core Database** (`v0.2.0-sprint2`)
  - ULID Primary Keys, Enums, Migrations, Models, and Seeders across Academic, QuestionBank, Assessment, Certificate, Finance, CMS, and Reporting.
- [x] **Sprint 3 — Admin Platform Foundation** (`v0.3.0-sprint3`)
  - Enterprise Admin Layout, Dynamic Modular Navigation, Extended Dashboard Metrics (5-min cache), Event-Driven Activity Logging, Settings Module, and Notification Foundation.
- [x] **Sprint 4 — Question Bank & Test Authoring** (`v0.4.0-sprint4`)
  - Passage Management, 12 Question Types, Difficulty Levels, Tagging, MediaService Abstraction, Test Builder & Validation Rules, Granular Permissions, and Architectural Documentation (`docs/architecture/`).
- [x] **Sprint 5 — CBT Engine & Student Examination Interface** (`v0.5.0-sprint5`)
  - Candidate Portal, Engine Layer (`AssessmentEngine`, `AttemptEngine`, `TimerEngine`, `NavigationEngine`, `AutoSaveEngine`, `RandomizationEngine`, `ScoringEngine`, `ReviewEngine`), Distraction-Free CBT UI, Anti-Cheating Foundation, Architecture Decision Records (`docs/adr/`), and 60+ Pest Tests.
- [x] **Sprint 6 — Results, Certificate & Analytics** (`v0.6.0-sprint6`)
  - Result Engine, Certificate Engine & Template System, Public Verification Portal (`/verify`), Analytics Engine, Psychometric Item Analysis, Export Service, Queue Foundation, GitHub Actions CI Pipeline, ADRs (`0006`, `0007`), and 71 Pest Tests.
- [x] **Sprint 7 — Platform Operations & Learning Management Foundation** (`v0.7.0-sprint7`)
  - Enrollment Engine (`EnrollmentStatus` enum), Assignment Engine, Scheduling Engine, Calendar Module, Notification Center & Reminder Engine, System Health Monitoring, Import Engine & Batch Operation Service, Timeline Service, CHANGELOG.md, Release Notes (`docs/releases/`), 3 new ADRs (`0008`, `0009`, `0010`), and 91 Pest Tests.
