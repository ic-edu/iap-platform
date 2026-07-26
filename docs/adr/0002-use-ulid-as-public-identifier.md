# 2. Use ULID as Primary Key and Public Identifier

* Status: **Accepted**
* Date: 2026-07-26

## Context
Penggunaan auto-incrementing integer (`id = 1, 2, 3...`) berisiko terhadap serangan enumerasi data pada platform ujian, serta mempersulit penggabungan data terdistribusi.

## Decision
Kami memutuskan untuk menggunakan **ULID (Universally Unique Lexicographically Sortable Identifier)** sebagai primary key untuk seluruh entitas domain (Courses, Question Banks, Questions, Tests, Attempts, Certificates, dll).

## Consequences
- **Positive**: Aman dari ID guessing attack, lexicographically sortable berdasarkan waktu pembuatan, dan kompatibel dengan distributed database.
- **Negative**: Ukuran kolom primary key (char 26) sedikit lebih besar daripada int/bigint.
