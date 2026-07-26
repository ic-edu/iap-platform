# 1. Use Modular Architecture (`app/Modules`)

* Status: **Accepted**
* Date: 2026-07-26

## Context
IAP (iC.edu Assessment Platform) dirancang sebagai sistem penilaian berskala besar yang mencakup akademik, bank soal, CBT engine, sertifikasi, finansial, dan pelaporan. Arsitektur bawaan Laravel cenderung mencampurkan seluruh controller, model, dan view dalam satu direktori tunggal yang sulit dikembangkan secara mandiri oleh tim terpisah.

## Decision
Kami memutuskan untuk menerapkan **Modular Architecture** di mana setiap domain berada di bawah `app/Modules/{ModuleName}`. Setiap modul memiliki Controller, Model, Service, Migrations, Seeders, Views, dan Routes terisolasi. `ModuleServiceProvider` secara otomatis memuat seluruh modul.

## Consequences
- **Positive**: Isolasi domain tinggi, pemeliharaan kode lebih mudah, dan skalabilitas tim pengembang lebih lancar.
- **Negative**: Pendaftaran namespace view memerlukan konvensi sintaks khusus (`modulename::view`).
