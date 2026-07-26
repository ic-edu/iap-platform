# 🚀 iC.edu Assessment Platform (IAP) — Internal UAT Start Guide

Welcome Product Owner & Internal UAT Tester! This guide assumes **zero prior Laravel experience** and contains everything required to test the platform on your local macOS machine.

---

## 🌐 Application URLs

| Service / Subsystem | Local URL | Description |
| :--- | :--- | :--- |
| **Main Web Portal** | `http://127.0.0.1:8000` | Landing Page & Candidate Portal |
| **Login Screen** | `http://127.0.0.1:8000/login` | User Authentication Screen |
| **Admin Monitoring** | `http://127.0.0.1:8000/admin/monitoring` | Real-time Observability & System Metrics |
| **OpenAPI Contract Spec**| `http://127.0.0.1:8000/docs/api/openapi.yaml` | REST API Specification |
| **Full Health Check** | `http://127.0.0.1:8000/health` | Complete System Component Status (JSON) |
| **Readiness Probe** | `http://127.0.0.1:8000/ready` | Load Balancer Readiness Status |
| **Liveness Probe** | `http://127.0.0.1:8000/live` | Application Liveness Status |

---

## 🔑 Demo Login Credentials

Use the following pre-seeded accounts to test each user role:

### 1. Super Administrator Account
- **Role**: `super-admin`
- **Email**: `admin@icedu.org`
- **Password**: `password`
- **Permissions**: Full system access, Question Banks, Test Authoring, System Settings, Monitoring Dashboard.

### 2. Teacher / Instructor Account
- **Role**: `teacher`
- **Email**: `teacher@icedu.org`
- **Password**: `password`
- **Permissions**: Course management, Question Bank creation, Test building, Student grading, Certificate issuance.

### 3. Student / Candidate Account
- **Role**: `student`
- **Email**: `student@icedu.org`
- **Password**: `password`
- **Permissions**: CBT Exam engine access, live timer test attempts, immediate scoring breakdown, digital certificate download.

### 4. Finance Manager Account
- **Role**: `admin` / `finance`
- **Email**: `finance@icedu.org`
- **Password**: `password`
- **Permissions**: Product pricing, promotional coupons, order invoices, manual payment confirmations.

---

## 📋 Recommended Testing Order

1. **Step 1: Admin & Observability Audit**
   - Open `http://127.0.0.1:8000/login` and log in as `admin@icedu.org` / `password`.
   - Visit `http://127.0.0.1:8000/admin/monitoring` to review active memory, storage, database connections, and failed queue jobs.
2. **Step 2: Question Bank & Test Authoring (Teacher Role)**
   - Log in as `teacher@icedu.org` / `password`.
   - Create a question bank, add a reading passage, and author multiple choice questions.
   - Build an assessment test with 60-minute duration and publish it.
3. **Step 3: Candidate CBT Exam Experience (Student Role)**
   - Log in as `student@icedu.org` / `password`.
   - Select an available assessment test, answer questions, observe the live timer and anti-cheating tracking, and submit the exam.
   - View immediate test result breakdown and download digital certificate.
4. **Step 4: Commerce & Public Certificate Verification**
   - Browse public courses/products, test shopping cart checkout, and simulate manual bank transfer payment confirmation.
   - Copy certificate verification code and test public verification at `http://127.0.0.1:8000/verify/{code}`.

---

## 💻 Recommended Browser
- Google Chrome, Safari, or Brave (Desktop version).

---

## 🛠️ Common Troubleshooting

- **Server Not Responding**: Open a Terminal window and run:
  ```bash
  cd /Users/indrawahyudi/.gemini/antigravity/scratch/iap-platform
  php artisan serve
  ```
- **Session Expired (HTTP 419)**: Refresh the browser page (`Cmd + R`) and re-submit.
- **Cache Clearing**:
  ```bash
  php artisan config:clear
  ```
