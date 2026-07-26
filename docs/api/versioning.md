# API Versioning Strategy — iC.edu Assessment Platform (IAP)

IAP uses URI path versioning: `/api/v1/`.

## Deprecation & Compatibility Policy
- Non-breaking additions (new fields, new optional query params) are introduced directly in `v1`.
- Breaking changes require a new version path (e.g. `/api/v2/`).
