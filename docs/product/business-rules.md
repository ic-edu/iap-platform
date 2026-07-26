# Business & Billing Rules — iC.edu Assessment Platform (IAP)

## 1. Product & Pricing Rules
- Products can belong to categories: `toefl_prediction`, `toefl_prep`, `ielts_prep`, `placement_test`, `corporate_training`, `membership`.
- Pricing calculations use single-source `PricingEngine`: `Final Price = (Base Price - Discount Amount) + Tax`.

## 2. Order & Checkout Rules
- Orders transition: `pending` → `completed` | `cancelled` | `refunded`.
- Invoices use ULID primary key and format `INV-YYYYMMDD-XXXX`. Statuses: `unpaid`, `paid`, `expired`, `cancelled`.
- Unpaid invoices automatically expire after 24 hours.

## 3. Automatic Enrollment Activation
- Upon payment confirmation (`PaymentConfirmed` event):
  1. Invoice status updates to `paid`.
  2. Candidate is enrolled in mapped Course (`EnrollmentEngine::enrollStudent`).
  3. Mapped Test attempt is created (`AssignmentEngine::assignToUser`).
  4. Confirmation email and in-app notification are dispatched.

## 4. Certification & Verification Rules
- Certificates are issued only for passed evaluations (`total_score >= pass_score`).
- Each certificate receives a unique `certificate_number` (`CERT-...`) and `verification_code` (`VRF-...`).
- Verification at `/verify` is unauthenticated and returns status: `Valid`, `Revoked`, `Expired`, `Not Found`.
