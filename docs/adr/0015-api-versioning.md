# 15. URI Path API Versioning Strategy

Date: 2026-07-26

## Status
Accepted

## Context
External clients (mobile apps, LMS, third-party integrations) require stable API contracts that do not break unexpectedly.

## Decision
Adopt URI path versioning (`/api/v1/`). Non-breaking changes are added directly in `v1`. Breaking changes will trigger a new version release (`/api/v2/`).

## Consequences
- Clean separation between active API versions.
