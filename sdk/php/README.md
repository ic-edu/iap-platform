# iC.edu Assessment Platform (IAP) — PHP SDK

Official PHP SDK for interacting with the iC.edu Assessment Platform REST API v1.

## Installation
```bash
composer require ic-edu/iap-sdk-php
```

## Quick Start
```php
use IcEdu\Iap\IapClient;

$client = new IapClient([
    'base_url' => 'https://api.icedu.org/api/v1',
    'api_token' => 'YOUR_SANCTUM_BEARER_TOKEN',
]);

// Verify Certificate
$certificate = $client->certificates()->verify('VRF-1234-5678');

// List Public Courses
$courses = $client->courses()->all();
```
