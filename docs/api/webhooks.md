# Webhook Platform Documentation — iC.edu Assessment Platform (IAP)

IAP dispatches real-time webhooks for key system events.

## Signature Verification
Each request contains `X-IAP-Signature` computed using HMAC-SHA256:
```php
$computedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);
```

## Supported Events
- `payment.confirmed`
- `certificate.issued`
- `assessment.completed`
- `enrollment.created`
- `subscription.activated`
- `product.purchased`
