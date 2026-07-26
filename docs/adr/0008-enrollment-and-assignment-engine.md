# ADR 0008: Enrollment and Assignment Engine Architecture

- **Status**: Accepted
- **Date**: 2026-07-26

## Context
Educational evaluations require structured student enrollment into courses and automated test assignment across individual users, courses, and student batches.

## Decision
1. **EnrollmentEngine**: Encapsulates enrollment operations (`enrollStudent`, `bulkEnroll`, `cancelEnrollment`) using `EnrollmentStatus` enum (`active`, `completed`, `cancelled`, `suspended`).
2. **AssignmentEngine**: Encapsulates test assignment (`assignToUser`, `assignToCourse`, `revokeAssignment`).

## Consequences
Decouples course enrollment and assessment assignment from controllers.
