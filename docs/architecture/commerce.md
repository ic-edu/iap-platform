# Commerce Architecture — iC.edu Assessment Platform (IAP)

## Overview
The Commerce module (`app/Modules/Commerce/`) governs product management, catalog browsing, shopping cart operations, checkout processing, and ordering.

## Flow Diagram
```mermaid
sequenceDiagram
    autonumber
    actor Candidate
    participant Cart as CartEngine
    participant Checkout as CheckoutEngine
    participant Invoice as InvoiceEngine
    participant Event as EventDispatcher

    Candidate->>Cart: Add Product to Cart
    Cart-->>Candidate: Calculated Subtotal & Grand Total
    Candidate->>Checkout: Initiate Checkout
    Checkout->>Invoice: Generate Invoice (INV-YYYYMMDD-XXXX)
    Checkout->>Event: Dispatch CheckoutCompleted
    Invoice-->>Candidate: Return Order & Invoice Details
```
