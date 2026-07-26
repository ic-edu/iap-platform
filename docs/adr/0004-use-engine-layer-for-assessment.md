# 4. Use Engine Layer for Assessment Delivery

* Status: **Accepted**
* Date: 2026-07-26

## Context
Proses pengerjaan ujian CBT melibatkan alur yang sangat kompleks: kalkulasi timer server, pengacakan deterministik, navigasi soal, auto-save, penilaian otomatis, dan evaluasi anti-kecurangan. Memasukkan logika ini ke controller akan menghasilkan Fat Controller yang rentan bug.

## Decision
Membangun **Engine Layer** khusus di `app/Modules/Assessment/Engines/`:
- `AssessmentEngine` (Orchestration)
- `AttemptEngine` (Lifecycle)
- `TimerEngine` (Time Truth)
- `NavigationEngine` (Question Navigation & Flagging)
- `AutoSaveEngine` (Automated Response Storage)
- `RandomizationEngine` (Deterministic Shuffling)
- `ScoringEngine` (Automated Scoring)
- `ReviewEngine` (Result Review)

## Consequences
- **Positive**: Controller hanya bertugas menangani HTTP Request/Response, setiap engine fokus pada satu tanggung jawab (SOLID), dan pengujian unit/integration menjadi jauh lebih mudah.
- **Negative**: Memerlukan pemahaman hirarki engine bagi pengembang baru.
