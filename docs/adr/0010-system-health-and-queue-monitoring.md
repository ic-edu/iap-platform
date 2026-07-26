# ADR 0010: System Health and Queue Monitoring Architecture

- **Status**: Accepted
- **Date**: 2026-07-26

## Context
System administrators require visibility into server storage, DB connectivity, failed queue jobs, and runtime environment versions.

## Decision
1. **SystemHealthService**: Aggregates health metrics (Storage, DB, Queue, Cache, PHP/Laravel versions) and dispatches `SystemHealthChecked`.

## Consequences
Enables proactive operational monitoring and health checks.
