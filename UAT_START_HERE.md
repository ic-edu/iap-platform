# 🚀 iC.edu Assessment Platform (IAP) — Internal UAT Start Guide

Welcome Product Owner & Internal UAT Tester! This guide assumes **zero prior Laravel experience** and contains everything required to access and test the platform on your local macOS machine.

---

## 🟢 Server Status & Listening Address
The development server is active and bound to `0.0.0.0:8000`, making it accessible via any of the following URLs in your browser:

- **Primary Local URL**: `http://127.0.0.1:8000`
- **Alternative Localhost URL**: `http://localhost:8000`

---

## 🌐 Application URLs

| Service / Subsystem | Local URL | Description |
| :--- | :--- | :--- |
| **Main Web Portal** | `http://127.0.0.1:8000` | Redirects guests to `/login` |
| **Login Screen** | `http://127.0.0.1:8000/login` | User Authentication Screen |
| **Admin Monitoring** | `http://127.0.0.1:8000/admin/monitoring` | Real-time Observability & System Metrics |
| **OpenAPI Contract Spec**| `http://127.0.0.1:8000/docs/api/openapi.yaml` | REST API Specification |
| **Full Health Check** | `http://127.0.0.1:8000/health` | Complete System Component Status (JSON) |
| **Readiness Probe** | `http://127.0.0.1:8000/ready` | Load Balancer Readiness Status |
| **Liveness Probe** | `http://127.0.0.1:8000/live` | Application Liveness Status |

---

## 🔑 Final Verified UAT Demo Credentials

All 4 demo accounts are seeded and 100% verified in the database:

| Name | Role | Email | Password | Primary Redirect Destination |
| :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | `super-admin` | `admin@icedu.org` | `password` | `http://127.0.0.1:8000/admin/monitoring` |
| **Teacher Instructor** | `teacher` | `teacher@icedu.org` | `password` | `http://127.0.0.1:8000/admin/question-banks` |
| **Candidate Student** | `student` | `student@icedu.org` | `password` | `http://127.0.0.1:8000/candidate/portal` |
| **Finance Manager** | `admin` | `finance@icedu.org` | `password` | `http://127.0.0.1:8000/admin/monitoring` |

---

## 📋 Recommended Testing Order

1. **Step 1: Admin & Observability Audit**
   - Open `http://127.0.0.1:8000/login` and log in as `admin@icedu.org` / `password`.
   - You will be automatically redirected to `http://127.0.0.1:8000/admin/monitoring`. Review active memory, storage, database connections, and queue status.
2. **Step 2: Question Bank & Test Authoring (Teacher Role)**
   - Log in as `teacher@icedu.org` / `password`.
   - You will be redirected to `http://127.0.0.1:8000/admin/question-banks`. Create a question bank, add a reading passage, and author multiple choice questions.
   - Build an assessment test with 60-minute duration and publish it.
3. **Step 3: Candidate CBT Exam Experience (Student Role)**
   - Log in as `student@icedu.org` / `password`.
   - You will be redirected to `http://127.0.0.1:8000/candidate/portal`. Select an available assessment test, answer questions, observe the live timer and anti-cheating tracking, and submit the exam.
   - View immediate test result breakdown and download digital certificate.
4. **Step 4: Commerce & Public Certificate Verification**
   - Browse public courses/products, test shopping cart checkout, and simulate manual bank transfer payment confirmation.
   - Copy certificate verification code and test public verification at `http://127.0.0.1:8000/verify/{code}`.

---

## 💻 Recommended Browser
- Google Chrome, Safari, or Brave (Desktop version).

---

## 🛠️ How to Keep / Restart Server During Testing

The background dev server process is currently running on `0.0.0.0:8000`. If you ever close your terminal or restart your computer, simply open Terminal and run:

```bash
cd /Users/indrawahyudi/.gemini/antigravity/scratch/iap-platform
php artisan db:seed --force
php artisan serve --host=0.0.0.0 --port=8000
```
Keep that Terminal tab open while performing UAT testing.
