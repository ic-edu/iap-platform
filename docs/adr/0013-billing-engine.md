# 13. Event-Driven Automatic Enrollment Activation via Billing Engine

Date: 2026-07-26

## Status
Accepted

## Context
When a candidate completes payment for a course or evaluation test, enrollment and test attempt access must be activated automatically without human delay.

## Decision
`BillingEngine::confirmPayment()` emits `PaymentConfirmed`. Listener `ActivateEnrollmentOnPayment` listens to this event and synchronously invokes `EnrollmentEngine::enrollStudent()` and `AssignmentEngine::assignToUser()`.

## Consequences
- Immediate candidate access to purchased educational assets.
- Fully decoupled Commerce and Academic/Assessment modules.
