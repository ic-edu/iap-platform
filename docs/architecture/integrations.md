# Integration Abstraction Architecture — iC.edu Assessment Platform (IAP)

## Overview
Decouples third-party integrations (Zoom, Google Calendar, WhatsApp, SMS, Email, Payment, Storage) via interface contracts in `app/Integrations/`.

## Architecture Principles
1. **Contract First**: Controllers and domain services depend exclusively on Interface contracts.
2. **Pluggable Drivers**: Switching driver implementations requires zero modification to application code.
