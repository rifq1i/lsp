# Dokumentasi API – Sistem Sertifikasi SKK (LSP)

Dokumen ini berisi daftar endpoint API untuk tim frontend (Web dan Mobile).
Diverifikasi terhadap `php artisan route:list` per 24 Nov 2026 (22 route).

## 1. Informasi Umum

- **Base URL (Development):** `http://127.0.0.1:8000/api`
- **Header Wajib:**
  - `Accept: application/json` (selalu wajib, agar error berupa JSON, bukan HTML)
  - `Content-Type: application/json` (jika mengirim body)
  - `Authorization: Bearer <token>` (untuk endpoint yang membutuhkan login)
- **Format Respons:**

```json
// Sukses
{ "success": true, "message": "...", "data": { } }

// Gagal validasi (422)
{ "success": false, "errors": { "email": ["The email has already been taken."] } }
```

| Kode | Arti |
|---|---|
| 200 / 201 | Berhasil / berhasil dibuat |
| 401 | Belum login atau token tidak valid |
| 403 | Login, tetapi role tidak diizinkan |
| 404 | Data tidak ditemukan |
| 422 | Validasi gagal (lihat `errors`) |
| 500 | Bug di backend, mohon dilaporkan |

## 2. Autentikasi

### Register Pemohon

- **Method:** `POST` | **Endpoint:** `/auth/register` | **Akses:** Publik
- **Request Body:**

```json
{
  "name": "Nama Panggilan",
  "fullname": "Nama sesuai KTP",
  "place": "Kota Lahir",
  "date_of_birth": "1990-01-01",
  "phone": "081234567890",
  "email": "user@example.com",
  "password": "rahasia123",
  "password_confirmation": "rahasia123"
}
```

*Catatan:* Semua field wajib. `email` harus unik, `password` minimal 8 karakter dan harus sama dengan `password_confirmation`. Role selalu dipaksa menjadi `"user"` oleh server. Frontend **tidak perlu** mengirim field `role`.

### Login

- **Method:** `POST` | **Endpoint:** `/auth/login` | **Akses:** Publik
- **Request Body:** `{ "email": "user@example.com", "password": "rahasia123", "device_name": "web" }`
- *Catatan:* `device_name` opsional. Respons sukses berisi `token` (Sanctum) dan data `user`. Simpan token, lalu kirim sebagai `Authorization: Bearer <token>`. Password salah menghasilkan 401.

### Logout dan Info User

- `POST /auth/logout` (Login): menghapus token yang sedang dipakai.
- `GET /user` (Login): mengembalikan data user yang sedang login.

## 3. Pemohon (Role: `user`)

### Buat Permohonan Baru

- **Method:** `POST` | **Endpoint:** `/certifications`
- **Request Body:** semua field opsional pada tahap ini. Server mengisi `user_id` dan status awal secara otomatis.

```json
{ "judul_skema": "Desainer Grafis Muda", "jenis_kelamin": "Laki-laki" }
```

Field yang divalidasi: `judul_skema`, `skema_sertifikasi`, `jenis_kelamin` (masing-masing teks, opsional).

### Kirim Data APL-01 (Formulir Lengkap)

- **Method:** `POST` | **Endpoint:** `/certifications/apl-01`
- **Field Wajib:** `jenis_kelamin`, `alamat_rumah`, `pendidikan_terakhir`, `judul_skema`, `nomor_skema`, `tujuan_asesmen`, `unit_kompetensi` (array, minimal 1 item).
- **Aturan nilai:**
  - `jenis_kelamin`: `Laki-laki` atau `Wanita`
  - `tujuan_asesmen`: `Sertifikasi` atau `Sertifikasi Ulang`
  - Setiap item `unit_kompetensi` wajib punya `kode_unit`, `judul_unit`, `jenis_standar`
  - Opsional: `kebangsaan`, `kode_pos_rumah` (maks 10 karakter), `nama_perusahaan`, `jabatan`, `alamat_kantor`

```json
{
  "jenis_kelamin": "Laki-laki",
  "alamat_rumah": "Jl. Contoh No. 1",
  "pendidikan_terakhir": "S1",
  "judul_skema": "Desainer Grafis Muda",
  "nomor_skema": "SKM-001",
  "tujuan_asesmen": "Sertifikasi",
  "unit_kompetensi": [
    { "kode_unit": "J.620100.001.01", "judul_unit": "Contoh Unit", "jenis_standar": "SKKNI" }
  ]
}
```

### Simpan Persetujuan APL

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/submit-apl`
- **Request Body:** `{ "apl_01_data": {}, "apl_02_data": {} }`

### Konfirmasi Pembayaran (Upload Bukti)

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/pay`
- **Request Body:** `{ "bukti_bayar": "url_atau_kode_referensi" }`
- *Catatan Penting:* Endpoint ini hanya untuk mengunggah bukti bayar. Validasi dan perubahan status menjadi `Lunas` seharusnya diverifikasi oleh Admin.

### Lihat Daftar dan Unduh Dokumen

- `GET /certifications` (Login): melihat daftar permohonan.
- `GET /certifications/{id}/pdf/{jenis_dokumen}` (Login): unduh PDF. Contoh `jenis_dokumen`: `APL01`, `IA02`, `IA05`.

### Persetujuan oleh Pemohon

- `POST /certifications/{id}/approve-apl` (Role: `user`)
- `POST /certifications/{id}/approve-assessment` (Role: `user`)
- *Catatan:* Body kedua endpoint ini akan dilengkapi oleh backend.

## 4. Operasional LSP (Role: `admin`, `super_admin`)

- `POST /certifications/{id}/verify-admin`: verifikasi berkas.
- `POST /certifications/{id}/pre-assessment`: penjadwalan dan pra-asesmen.
- `POST /certifications/{id}/final-decision`: keputusan akhir (`Assesor_Yes` / `Assesor_No`).
- *Catatan:* Body ketiga endpoint ini akan dilengkapi oleh backend.

## 5. Super Admin (Role: `super_admin`)

- `GET /super-admin/users`: daftar user.
- `GET`, `PUT`, `DELETE /super-admin/users/{id}`: lihat, ubah, hapus user.
- `POST /super-admin/register-admin`: register admin baru.
- `POST /super-admin/register-super-admin`: register super admin baru.
- *Catatan:* Body register sama dengan Register Pemohon (semua field wajib, termasuk `password_confirmation`). Role ditentukan oleh endpoint. Akun yang dibuat tidak langsung mendapat token, jadi harus login terpisah.

## 6. Fitur Tambahan: Tanda Tangan (TTD)

- **Method:** `POST` | **Endpoint:** `/user/signature` | **Akses:** Login
- **Request Body:**

```json
{ "signature": "data:image/png;base64,iVBORw0KGgo..." }
```

- *Catatan:* `signature` wajib berupa teks. Menerima string base64 dari canvas frontend (diawali `data:image/...;base64,`). File disimpan, lalu path-nya diperbarui di tabel `users`.

## 7. Catatan Penting untuk Frontend

1. Selalu kirim header `Accept: application/json`.
2. Jangan kirim field `role` saat register.
3. Semua endpoint register wajib menyertakan `password_confirmation`.
4. Jika menerima kode **401**, arahkan pengguna ke halaman login.
5. Jika menerima kode **422**, tampilkan pesan dari objek `errors` di bawah field yang bersangkutan.
6. Jika menerima kode **500**, itu bug di sisi backend (bukan salah frontend), silakan laporkan.

## 8. Status Keamanan dan Verifikasi (Per 24 Nov 2026)

Status setelah perbaikan dari Adis masuk lewat merge `origin/main`.

| Hal | Status |
|---|---|
| Akses data milik orang lain (`submit-apl`, `pay`, `approve-apl`, `approve-assessment`, PDF) | Sudah diperbaiki. Hasil uji tim: ditolak dengan 403 atau 404 |
| Kolom database alur sertifikasi (`apl_01_data`, `bukti_bayar`, `lunas_at`, dan lainnya) | Sudah ada. Error 500 pada `submit-apl` dan `pay` sebelumnya sudah tidak terjadi |
| Pembatasan role (`user`, `admin`, `super_admin`) | Sudah berjalan lewat `RoleMiddleware` |
| Register tidak bisa menyelipkan `role` | Terverifikasi lewat `uji-auth.sh` |

**Catatan untuk frontend:** `POST /certifications/{id}/pay` saat ini hanya menyimpan bukti bayar. Status `Lunas` diubah oleh Admin, jadi jangan menampilkan "Lunas" setelah pemohon mengunggah bukti.

**Masih terbuka (backend):**
- Body request `approve-apl`, `approve-assessment`, `verify-admin`, `pre-assessment`, `final-decision` belum didokumentasikan.
- Aturan waktu otomatis (batal setelah 1×24 jam atau 5 hari kerja) dan notifikasi WhatsApp belum diimplementasikan.

## 9. Akun Development

Hanya untuk pengujian lokal setelah `php artisan migrate:fresh --seed`. **Ganti atau hapus sebelum dipakai sungguhan.**

| Role | Email | Password |
|---|---|---|
| `super_admin` | `superadmin@lsp.com` | `password123` |
