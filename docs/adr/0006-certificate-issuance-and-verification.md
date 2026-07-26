# ADR 0006: Digital Certificate Issuance and Verification Architecture

- **Status**: Accepted
- **Date**: 2026-07-26

## Context
Candidates completing evaluations require verifiable digital certificates. Employers and third-party institutions need an instant, reliable public verification portal without requiring authentication.

## Decision
1. **ULID & Verification Code**: Certificates use ULIDs as database primary keys, while exposing a user-friendly `certificate_number` (`CERT-YYYYMMDD-XXXX`) and an immutable `verification_code` (`VRF-XXXX-XXXX`).
2. **Public Portal**: Unauthenticated endpoint `/verify` accepts both certificate number and verification code to return verified status (`Valid`, `Revoked`, `Expired`, `Not Found`).
3. **Decoupled Engine**: Business actions (`issue`, `reissue`, `revoke`) are encapsulated inside `CertificateEngine`.

## Consequences
Guarantees certificate integrity while keeping verification publicly accessible and audit-friendly.
