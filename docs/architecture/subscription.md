# Subscription Lifecycle Architecture — iC.edu Assessment Platform (IAP)

## Overview
`SubscriptionEngine.php` manages candidate licenses (`monthly`, `quarterly`, `yearly`, `lifetime`).

## Lifecycle Statuses
- `active`: Candidate has full access to subscriber courses and evaluation products.
- `expired`: Subscription duration ended.
- `cancelled`: Subscription terminated early by admin or candidate.
- `pending`: Awaiting payment confirmation.
