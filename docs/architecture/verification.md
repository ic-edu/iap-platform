# Public Verification Portal Architecture — IAP

Public Verification Portal tersedia di rute `/verify` tanpa autentikasi.

## Verification Flow
1. Pengguna memasukkan `Certificate Number` (`CERT-...`) atau `Verification Code` (`VRF-...`).
2. `VerificationService::verify($code)` mencari rekaman sertifikat di database.
3. Mengembalikan status validasi:
   - `Valid`: Sertifikat asli dan aktif.
   - `Revoked`: Sertifikat telah dicabut oleh pihak berwenang.
   - `Expired`: Sertifikat telah melewati masa berlaku.
   - `Not Found`: Nomor/kode tidak terdaftar dalam sistem resmi.
