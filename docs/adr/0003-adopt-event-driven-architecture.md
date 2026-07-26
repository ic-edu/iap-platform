# 3. Adopt Event-Driven Architecture for Activity Logging

* Status: **Accepted**
* Date: 2026-07-26

## Context
Aktivitas seperti login, pemakaian ujian, pembuatan soal, dan pencatatan kecurangan memerlukan pencatatan audit log yang transparan tanpa mengotori logika bisnis controller.

## Decision
Menerapkan **Event-Driven Architecture** dengan menembakkan Event Laravel (`UserLoggedIn`, `AttemptStarted`, `QuestionAnswered`, `RuleViolationDetected`) yang ditangani secara terpisah oleh Listener (`LogAssessmentDeliveryActivity`, `ActivityLogger`).

## Consequences
- **Positive**: Decoupling tinggi antar komponen, mudah menambahkan pemrosesan asinkron (queue), dan controller tetap bersih.
- **Negative**: Jumlah kelas Event dan Listener bertambah.
