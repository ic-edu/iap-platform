# iC.edu Assessment Platform (IAP) — Flutter / Dart SDK

Official Flutter SDK for iOS and Android Mobile Applications.

## Installation
Add to `pubspec.yaml`:
```yaml
dependencies:
  iap_platform_sdk: ^1.0.0
```

## Quick Start
```dart
import 'package:iap_platform_sdk/iap_platform_sdk.dart';

final client = IapClient(
  baseUrl: 'https://api.icedu.org/api/v1',
  apiToken: 'YOUR_SANCTUM_BEARER_TOKEN',
);

final result = await client.verifyCertificate('VRF-1234-5678');
print(result.recipientName);
```
