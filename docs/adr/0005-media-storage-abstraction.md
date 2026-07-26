# 5. Media Storage Abstraction Layer

* Status: **Accepted**
* Date: 2026-07-26

## Context
Media soal (gambar, audio listening, video prompt, dokumen PDF) disimpan di berbagai lokasi tergantung lingkungan (disk local saat pengujian, S3/MinIO/R2 saat produksi).

## Decision
Membangun `MediaService` terpusat yang menggunakan `StorageDriverInterface` (`LocalStorageDriver`). Logika bisnis penyimpanan tidak bergantung langsung pada disk filesystem Laravel tertentu.

## Consequences
- **Positive**: Perpindahan driver penyimpanan dari Local ke S3/R2 dapat dilakukan tanpa mengubah kode bisnis aplikasi.
- **Negative**: Memerlukan wrapper service saat mengunggah media.
