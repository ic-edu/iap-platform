# 18. API Security, Audit Logging, and Rate Limiting

Date: 2026-07-26

## Status
Accepted

## Context
APIs must be protected against brute force attacks, unauthorized access, and abuse while maintaining complete audit trails.

## Decision
Enforce Sanctum Bearer token authentication, rate limiting throttles, and `ApiAuditLogMiddleware.php` to log every API request to `ActivityLog`.

## Consequences
- Full auditability of client requests, latency monitoring, and attack mitigation.
