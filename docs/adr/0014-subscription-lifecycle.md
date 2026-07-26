# 14. Subscription License Lifecycle Management

Date: 2026-07-26

## Status
Accepted

## Context
Candidates and institutions require recurring subscription access (monthly, quarterly, yearly, lifetime) to platform evaluation tools.

## Decision
Build `SubscriptionEngine.php` handling license activations, expiry tracking, and status transitions (`active`, `expired`, `cancelled`, `pending`).

## Consequences
- Flexible monetization plans for single candidates and corporate organizations.
