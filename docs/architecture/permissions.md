# Spatie Roles & Permissions Guide — IAP

Sistem otentikasi IAP terintegrasi penuh dengan `spatie/laravel-permission`.

---

## Roles Standard
1. `super-admin`: Hak akses tak terbatas ke seluruh sistem.
2. `admin`: Pengelola konten akademik, tes, pembayaran, dan settings.
3. `teacher`: Pengelola bank soal, penyusun tes, pemeriksa nilai, dan sertifikat.
4. `student`: Peserta ujian dan pengakses materi kursus.

---

## Granular Permissions Map

| Modul | Permission Key | Fungsi |
|---|---|---|
| **Academic** | `academic.view`, `manage courses` | Akses modul akademik |
| **QuestionBank** | `question-bank.view`, `question-bank.create`, `question-bank.update`, `question-bank.delete` | Manajemen Bank Soal & Editor |
| **Assessment** | `assessment.view`, `test.view`, `test.create`, `test.update`, `test.delete`, `test.publish` | Penulisan & Publikasi Ujian CBT |
| **Certificate** | `certificate.view`, `issue certificates` | Penerbitan & Tinjauan Sertifikat |
| **Finance** | `finance.view`, `manage payments` | Verifikasi Pembayaran |
| **CMS** | `cms.view` | Halaman Statis & Pengumuman |
| **Reporting** | `reporting.view` | Laporan Analitis & Metrik |
| **Settings** | `settings.view`, `manage settings` | Konfigurasi Sistem & Maintenance |
