# Technical Debt & Architecture Review — iC.edu Assessment Platform (IAP)

## Architecture Compliance Summary
- **Modular Isolation**: All 8 domain modules remain independently loadable through `ModuleServiceProvider.php`.
- **Engine Layer Isolation**: Engines (`ResultEngine`, `CertificateEngine`, `PricingEngine`, `WebhookEngine`, `CBT Engine`) manage complex domain workflows without coupling to HTTP controllers.
- **Event-Driven Decoupling**: Domain events dispatch actions across boundaries (e.g. `PaymentConfirmed` -> `ActivateEnrollmentOnPayment`).

## Technical Debt Inventory
1. **Third-Party Payment Gateways**: `MidtransGateway`, `XenditGateway`, and `StripeGateway` drivers currently contain production stubs; real API client keys will be bound upon contract finalization.
2. **Real-time WebSockets**: Live candidate proctoring currently operates via polling; WebSocket broadcast (Reverb/Pusher) can be added in future minor versions.

## Strategic Roadmap Beyond v1.0
- **UAT Phase (2–4 weeks)**: Operation under `v1.0.0-beta` collecting feedback.
- **Release Candidates (`v1.0.0-rc1`, `v1.0.0-rc2`)**: Bug fixes and stability tuning only.
- **v1.0.0 Stable**: Production launch.
