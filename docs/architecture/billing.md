# Billing Architecture & Automatic Activation — iC.edu Assessment Platform (IAP)

## Overview
The Billing Engine (`BillingEngine.php`) handles payment confirmation, verifications, cancellations, and refunds.

## Flow Diagram
```mermaid
sequenceDiagram
    autonumber
    actor System / Gateway
    participant Billing as BillingEngine
    participant Listener as ActivateEnrollmentOnPayment
    participant Academic as EnrollmentEngine
    participant Assessment as AssignmentEngine

    System/Gateway->>Billing: confirmPayment(paymentId)
    Billing->>Billing: Update Payment status = Success & Invoice = Paid
    Billing->>Listener: Dispatch PaymentConfirmed Event
    Listener->>Academic: enrollStudent(course, user)
    Listener->>Assessment: assignToUser(test, user)
    Listener-->>System / Gateway: Enrollment & Test Attempt Activated
```
