# 17. Event-Driven Webhook Platform with HMAC-SHA256 Signatures

Date: 2026-07-26

## Status
Accepted

## Context
External partner systems require real-time notifications for completed events (payments, certificates, test completions).

## Decision
Build `WebhookEngine.php` generating `X-IAP-Signature` HMAC-SHA256 headers for all outbound HTTP callbacks, storing delivery logs in `webhook_deliveries`.

## Consequences
- Guaranteed authenticity and tamper-proofing for third-party webhook receivers.
