# Payment Gateway Abstraction Architecture — iC.edu Assessment Platform (IAP)

## Overview
The platform decouples payment provider integrations using the `PaymentGatewayInterface` contract.

## Drivers Supported
1. `ManualTransferGateway`: Direct bank transfer with manual/admin verification (Production Ready).
2. `MidtransGateway`: Placeholder for Midtrans Snap API integration.
3. `XenditGateway`: Placeholder for Xendit Invoices API integration.
4. `StripeGateway`: Placeholder for Stripe Checkout API integration.
