# ADR 0009: Notification and Reminder Engine Architecture

- **Status**: Accepted
- **Date**: 2026-07-26

## Context
Candidates and teachers need timely notifications and automated reminders for upcoming assessment schedules and certificate availability.

## Decision
1. **NotificationService**: Abstract notification service supporting Database, Email, and Queue channels.
2. **ReminderEngine**: Automated scheduler engine sending H-1, Hari-H, and certificate reminders.

## Consequences
Supports future extension to SMS or WhatsApp channels without refactoring core logic.
