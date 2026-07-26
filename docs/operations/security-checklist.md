# Production Security Checklist — iC.edu Assessment Platform (IAP)

- [x] HTTPS enforced across all domain routes.
- [x] `SecurityHeadersMiddleware.php` applying CSP, X-Frame-Options, X-Content-Type-Options.
- [x] Sanctum API Bearer Token expiration and revocation active.
- [x] HMAC-SHA256 signatures enforced for outbound Webhook delivery callbacks.
- [x] SQL Injection & XSS sanitization verified.
