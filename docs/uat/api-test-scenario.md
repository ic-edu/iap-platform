# API & Webhook UAT Test Scenario — iC.edu Assessment Platform (IAP)

## Test Cases
1. **TC-API-01**: Authenticate via `POST /api/v1/auth/login` and receive Bearer Token.
2. **TC-API-02**: Verify certificate via `GET /api/v1/public/verify/{code}`.
3. **TC-API-03**: Verify HMAC-SHA256 signature on outbound Webhook delivery.
