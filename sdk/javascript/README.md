# iC.edu Assessment Platform (IAP) — JavaScript / TypeScript SDK

Official JS/TS SDK for Node.js, Web, and React Native.

## Installation
```bash
npm install @ic-edu/iap-sdk-js
```

## Quick Start
```typescript
import { IapClient } from '@ic-edu/iap-sdk-js';

const client = new IapClient({
  baseUrl: 'https://api.icedu.org/api/v1',
  apiToken: 'YOUR_SANCTUM_BEARER_TOKEN',
});

// Authenticate
const user = await client.auth.me();

// Fetch Candidate Certificates
const certs = await client.certificates.list();
```
