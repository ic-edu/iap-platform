# 11. Lightweight Domain-Driven Design (DDD) for Commerce Module

Date: 2026-07-26

## Status
Accepted

## Context
Commerce transactions require clean separation between domain logic (Products, Prices, Invoices, Payments) and presentation/framework code.

## Decision
Adopt a Lightweight DDD folder layout inside `app/Modules/Commerce/`:
- `Domain/`: Core models, enums, rules.
- `Application/`: Service engines (`PricingEngine`, `CartEngine`, `CheckoutEngine`, `BillingEngine`).
- `Infrastructure/`: Payment gateway drivers.
- `Presentation/`: Controllers and web views.

## Consequences
- Business logic is concentrated in Application Engines.
- Easy to audit and unit test financial logic.
