# Entity Relationship Diagram (ERD) — iC.edu Assessment Platform (IAP)

Dokumen ini mendeskripsikan struktur relasi database terpadu berbasis ULID di seluruh modul IAP.

```mermaid
erDiagram
    USERS ||--o{ COURSE_ENROLLMENTS : enrolls
    USERS ||--o{ QUESTION_BANKS : creates
    USERS ||--o{ TESTS : creates
    USERS ||--o{ ATTEMPTS : takes
    USERS ||--o{ PAYMENTS : pays
    USERS ||--o{ CERTIFICATES : receives

    COURSE_CATEGORIES ||--o{ COURSES : contains
    COURSE_CATEGORIES ||--o{ QUESTION_BANKS : categorizes
    COURSES ||--o{ COURSE_ENROLLMENTS : receives
    COURSES ||--o{ PAYMENTS : monetizes

    QUESTION_BANKS ||--o{ PASSAGES : contains
    QUESTION_BANKS ||--o{ QUESTIONS : contains
    PASSAGES ||--o{ QUESTIONS : references
    QUESTIONS ||--o{ QUESTION_CHOICES : has
    QUESTIONS }|--|{ TAGS : tagged

    TESTS ||--o{ TEST_SECTIONS : contains
    TEST_SECTIONS ||--o{ TEST_QUESTIONS : includes
    QUESTIONS ||--o{ TEST_QUESTIONS : assigns
    TESTS ||--o{ ATTEMPTS : evaluates
    TESTS ||--o{ PAYMENTS : monetizes

    ATTEMPTS ||--o{ ANSWERS : records
    QUESTIONS ||--o{ ANSWERS : evaluates
    ATTEMPTS ||--o| CERTIFICATES : generates
```

---

## Tabel Domain & Deskripsi Utama

1. **`users`**: Tabel pengguna sistem (admin, teacher, student) dengan Spatie Role integration.
2. **`course_categories` & `courses`**: Pengorganisasian modul akademik dan kursus.
3. **`question_banks`**: Wadah kumpulan soal berdasarkan tipe ujian (TOEIC, TOEFL, IELTS, General).
4. **`passages`**: Teks bacaan/stimulus yang dapat dikaitkan dengan banyak soal.
5. **`questions`**: Soal ujian yang mendukung 12 tipe soal (*multiple choice*, *essay*, *speaking*, *listening*, dll.) dan tingkat kesulitan (*easy*, *medium*, *hard*).
6. **`question_choices`**: Pilihan jawaban untuk soal pilihan ganda/multi-response.
7. **`tests` & `test_sections`**: Struktur penyusunan ujian CBT (dengan batas waktu, passing score, dan opsi acak).
8. **`attempts` & `answers`**: Catatan sesi pengerjaan ujian peserta beserta skor dan umpan balik.
9. **`certificates`**: Bukti kelulusan ujian peserta.
10. **`payments`**: Transaksi pembayaran finansial kursus/ujian.
11. **`activity_logs` & `settings`**: Audit log kejadian sistem dan konfigurasi umum.
