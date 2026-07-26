# Certificate Engine Architecture — IAP

## Overview
Certificate Engine mengelola pembuatan, penerbitan ulang, dan pencabutan sertifikat digital secara otomatis setelah peserta lulus evaluasi CBT.

---

## Flow Sequence Diagram

```mermaid
sequenceDiagram
    autonumber
    participant Candidate
    participant AttemptEngine
    participant ResultEngine
    participant CertificateEngine
    participant Listener
    participant AuditLog

    Candidate->>AttemptEngine: Submit Attempt
    AttemptEngine->>ResultEngine: Evaluate Score & Pass Status
    alt Is Passed
        ResultEngine->>CertificateEngine: issueCertificate(attempt)
        CertificateEngine->>CertificateEngine: Generate Cert # & Verif Code
        CertificateEngine-->>Listener: Dispatch CertificateIssued
        Listener->>AuditLog: Log Activity (certificate.issued)
    end
```
