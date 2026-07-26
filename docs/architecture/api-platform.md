# API Platform Architecture — iC.edu Assessment Platform (IAP)

## Overview
The API Platform exposes RESTful JSON endpoints under `/api/v1` for external client integrations (Mobile Apps, LMS, HRIS, ERP).

## Flow Diagram
```mermaid
sequenceDiagram
    autonumber
    actor Client
    participant Sanctum as Laravel Sanctum
    participant Audit as ApiAuditLogMiddleware
    participant Controller as ApiResourceController
    participant Engine as BusinessEngine

    Client->>Sanctum: Request with Bearer Token
    Sanctum->>Audit: Validate Token & Pass Request
    Audit->>Controller: Route to Resource Controller
    Controller->>Engine: Execute Business Logic
    Engine-->>Controller: Domain Model Data
    Controller-->>Client: Standard JSON Response {"success": true, "data": {}}
```
