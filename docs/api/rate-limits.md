# API Rate Limiting Documentation — iC.edu Assessment Platform (IAP)

IAP enforces rate limits using Laravel RateLimiter throttle middleware.

## Default Limits
- **Authenticated Clients**: 60 requests / minute.
- **Unauthenticated Public Endpoints**: 30 requests / minute.

## Headers Returned
- `X-RateLimit-Limit`: Maximum allowed requests per window.
- `X-RateLimit-Remaining`: Remaining requests.
- `Retry-After`: Seconds to wait if 429 status is returned.
