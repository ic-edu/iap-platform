# API Authentication Documentation — iC.edu Assessment Platform (IAP)

IAP REST API uses **Laravel Sanctum** Bearer Token Authentication.

## Headers Required
```http
Authorization: Bearer <YOUR_PERSONAL_ACCESS_TOKEN>
Accept: application/json
Content-Type: application/json
```

## Endpoints
- `POST /api/v1/auth/login`: Authenticate email & password.
- `POST /api/v1/auth/logout`: Revoke current active Bearer token.
- `GET /api/v1/auth/me`: Retrieve current authenticated user profile.
