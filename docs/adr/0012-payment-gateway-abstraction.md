# 12. Payment Gateway Abstraction & Multi-Driver Architecture

Date: 2026-07-26

## Status
Accepted

## Context
The platform must support manual bank transfers immediately while preparing for automated payment gateways like Midtrans, Xendit, and Stripe in the future.

## Decision
Introduce `PaymentGatewayInterface` contract with `charge()`, `verify()`, `refund()`, `cancel()`.
Implement `ManualTransferGateway` for immediate production use alongside placeholders for Midtrans, Xendit, and Stripe.

## Consequences
- Switching payment providers requires zero changes to `CheckoutEngine` or `BillingEngine`.
