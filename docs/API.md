# Dokumentasi API – Sistem Sertifikasi SKK (LSP)

Dokumen ini berisi daftar endpoint API untuk tim frontend (Web dan Mobile).
Diverifikasi terhadap `php artisan route:list` dan `CertificationController` per 26 Nov 2026 (28 route).

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
| 403 | Login, tetapi role tidak diizinkan (atau bukan data/penugasan milik Anda) |
| 404 | Data tidak ditemukan |
| 409 | Status permohonan belum sesuai syarat endpoint |
| 422 | Validasi gagal (lihat `errors`) |
| 500 | Bug di backend, mohon dilaporkan |

### Role

| Role | Peran |
|---|---|
| `user` | Pemohon |
| `mitra` | Mitra yang mendaftarkan pemohon |
| `asesor` | Asesor LSP |
| `admin` | Admin LSP |
| `super_admin` | Super admin |

### Ringkasan endpoint

| Method | Endpoint | Akses |
|---|---|---|
| POST | `/auth/register` | Publik |
| POST | `/auth/login` | Publik |
| POST | `/auth/logout` | Login |
| GET | `/user` | Login |
| POST | `/user/signature` | Login |
| GET | `/certifications` | Login |
| POST | `/certifications` | `user` |
| POST | `/certifications/apl-01` | `user` |
| POST | `/certifications/{id}/get-sipp` | `mitra` |
| POST | `/certifications/{id}/submit-apl` | `user`, `mitra` |
| POST | `/certifications/{id}/approve-apl` | `user` |
| POST | `/certifications/{id}/review-apl01` | `admin`, `super_admin` |
| POST | `/certifications/{id}/nominal` | `admin`, `super_admin` |
| POST | `/certifications/{id}/pay` | `user`, `mitra` |
| POST | `/certifications/{id}/confirm-payment` | `admin`, `super_admin` |
| POST | `/certifications/{id}/schedule` | `admin`, `super_admin` |
| POST | `/certifications/{id}/pre-assessment` | `asesor`, `admin`, `super_admin` |
| POST | `/certifications/{id}/approve-assessment` | `user` |
| POST | `/certifications/{id}/final-decision` | `asesor`, `admin`, `super_admin` |
| GET | `/certifications/{id}/pdf/{jenis_dokumen}` | Login |
| POST | `/super-admin/register-admin` | `super_admin` |
| POST | `/super-admin/register-super-admin` | `super_admin` |
| POST | `/super-admin/register-asesor` | `admin`, `super_admin` |
| POST | `/super-admin/register-mitra` | `admin`, `super_admin` |
| GET | `/super-admin/users`, `/super-admin/users/{id}` | `super_admin` |
| PUT, DELETE | `/super-admin/users/{id}` | `super_admin` |

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

*Catatan:* Field di atas wajib semua. `email` harus unik, `password` minimal 8 karakter dan harus sama dengan `password_confirmation`. Role selalu dipaksa menjadi `"user"` oleh server. Frontend **tidak perlu** mengirim field `role`.

**Field opsional:**

| Field | Keterangan |
|---|---|
| `nik` | Teks, maks 32 karakter |
| `no_reg_asesor` | Teks, maks 64 karakter. Dipakai untuk akun asesor |
| `signature_base64` | Tanda tangan sebagai string base64 dari canvas, format `data:image/png;base64,...` |
| `signature` | Tanda tangan sebagai file gambar (png, jpg, jpeg, maks 2 MB). Kirim sebagai `multipart/form-data`, bukan JSON |

Register pemohon langsung mengembalikan `token`, jadi pengguna tidak perlu login ulang setelah mendaftar. Register admin, super admin, asesor, dan mitra tidak mengembalikan token.

Tanda tangan boleh dikirim sekalian saat register, atau nanti lewat `POST /user/signature`. Jika `signature_base64` dan `signature` dikirim bersamaan, yang dipakai adalah `signature_base64`.

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

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/submit-apl` | **Akses:** `user`, `mitra`
- **Request Body:** `{ "apl_01_data": {}, "apl_02_data": {} }`
- **Syarat:** status `Cek`. `apl_01_data` dan `apl_02_data` wajib berupa objek atau array JSON.
- Untuk permohonan jalur **mitra**, mitra harus sudah melakukan `get-sipp`, jika belum dijawab 409 ("Lakukan Get SIPP sebelum mengisi APL").
- Setelah ini status menjadi `Pemohon`.

### Setujui APL oleh Pemohon

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/approve-apl` | **Akses:** `user`
- **Request Body:** `{}`
- **Syarat:** status `Pemohon`. Hasilnya status menjadi `Apl_01`.

### Konfirmasi Pembayaran (Upload Bukti)

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/pay` | **Akses:** `user`, `mitra`
- **Syarat:** status permohonan harus `Tagih` dan belum melewati batas waktu pembayaran.
- **Request Body:** `{ "bukti_bayar": "url_atau_kode_referensi" }` (wajib, teks)
- *Catatan Penting:* Endpoint ini hanya menyimpan bukti bayar dan **tidak** mengubah status menjadi `Lunas`. Status `Lunas` diubah Admin lewat `POST /certifications/{id}/confirm-payment`.

### Setujui Asesmen oleh Peserta

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/approve-assessment` | **Akses:** `user`
- **Syarat:** status `Pra_Assesment`, belum melewati batas waktu (1×24 jam), dan permohonan milik user yang login.
- **Request Body:** `kuesioner_data` wajib (objek atau array berisi jawaban kuesioner).

```json
{ "kuesioner_data": { "contoh_pertanyaan": "jawaban" } }
```

- Hasilnya status menjadi `Assesment`.

### Lihat Daftar dan Unduh Dokumen

- `GET /certifications` (Login): melihat daftar permohonan.
- `GET /certifications/{id}/pdf/{jenis_dokumen}` (Login): unduh PDF. Contoh `jenis_dokumen`: `APL01`, `IA02`, `IA05`.

## 4. Mitra (Role: `mitra`)

### Ambil Data dari SIPP

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/get-sipp` | **Akses:** `mitra`
- **Syarat:** status `Cek` dan permohonan dapat diakses oleh akun yang login. Status **tetap `Cek`** setelah get. Lanjutkan dengan `submit-apl`.
- **Request Body:**

```json
{
  "nomor_registrasi": "REG-2026-0001",
  "sipp_data": { "data_1": "...", "data_2": "...", "data_3": "...", "data_4": "...", "data_5": "..." }
}
```

- `nomor_registrasi` wajib (teks, maks 100 karakter). `sipp_data` wajib berupa array atau objek dengan **minimal 5 isian** (sesuai flowchart: 5 data yang ditetapkan di SIPP).
- *Aturan waktu:* mitra hanya dapat melakukan *get* sampai pukul 18.00. Jika dilakukan setelah 18.01, permohonan diperlakukan sebagai **pemohon langsung**.

Mitra juga dapat memakai `POST /certifications/{id}/submit-apl` dan `POST /certifications/{id}/pay` (lihat bagian 3).

## 5. Operasional LSP (Role: `admin`, `super_admin`)

### Review APL-01 oleh Admin

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/review-apl01`
- **Syarat:** admin sudah mengunggah tanda tangan (`POST /user/signature`) dan status permohonan harus `Apl_01`.
- **Field opsional:** `nama_admin_lsp` (jika kosong, dipakai nama lengkap admin yang login).
- **Nilai `rekomendasi_lsp`:** `Diterima` (status menjadi `Peserta`) atau `Tidak diterima` (status menjadi `Batal`).
- **Request Body:**

```json
{
  "rekomendasi_lsp": "Diterima",
  "catatan_lsp": "Berkas lengkap dan sesuai persyaratan"
}
```

### Atur Nominal Tagihan

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/nominal`
- **Syarat:** status `Peserta` atau `Tagih`, jika tidak maka dijawab 409.
- **Request Body:** `{ "nominal_tagihan": 1500000 }` (wajib, angka, minimal 0)

### Konfirmasi Pembayaran (Admin)

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/confirm-payment`
- **Syarat:** status `Tagih` dan pemohon sudah mengunggah `bukti_bayar` (jika belum, dijawab 409).
- **Hasil:** status menjadi `Lunas`, dan `lunas_at` terisi.

### Jadwalkan Asesmen

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/schedule`
- **Syarat:** status `Lunas`. Hasilnya status menjadi `Assesor`.
- **Request Body:**

```json
{
  "nama_asesor": "Pak Budi",
  "no_reg_asesor": "MET.000.000000.2026",
  "jadwal_asesmen": "2026-12-01 09:00:00"
}
```

- `nama_asesor` dan `jadwal_asesmen` wajib. `no_reg_asesor` opsional, tetapi dipakai untuk mencocokkan penugasan asesor (lihat bagian 6).
- Jadwal tidak boleh lebih awal dari tanggal pelunasan ditambah beberapa hari (H+2 menurut flowchart). Jika terlalu awal, dijawab 422 dengan pesan yang menyebut tanggal paling cepat.

## 6. Asesor (Role: `asesor`, juga dapat diakses `admin`, `super_admin`)

Asesor hanya dapat memproses permohonan yang ditugaskan kepadanya (`no_reg_asesor` pada permohonan sama dengan milik akun asesor). Jika tidak, dijawab 403 "Permohonan ini bukan penugasan Anda".

### Pra-Asesmen

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/pre-assessment`
- **Syarat:** status permohonan harus `Assesor`.
- **Request Body:**

```json
{ "lolos": true, "catatan": "Opsional" }
```

- `lolos` wajib (boolean), `catatan` opsional.
- **Hasil:** `lolos: true` mengubah status menjadi `Pra_Assesment` (pemohon dikirimi notifikasi WhatsApp untuk menyetujui asesmen dalam 1×24 jam). `lolos: false` mengubah status menjadi `Assesor_No`.

### Keputusan Akhir

- **Method:** `POST` | **Endpoint:** `/certifications/{id}/final-decision`
- **Syarat:** status permohonan harus `Assesment`.
- **Request Body:**

```json
{
  "hasil": "Assesor_Yes",
  "catatan_asesor": "Kompeten",
  "nomor_sertifikat_skk": "SKK-2026-0001"
}
```

- `hasil` wajib: `Assesor_Yes` atau `Assesor_No`. Status berubah mengikuti nilai ini.
- `nomor_sertifikat_skk` wajib jika `hasil` = `Assesor_Yes`. `catatan_asesor` opsional.

## 7. Manajemen Akun (Super Admin dan Admin)

- `POST /super-admin/register-admin` dan `/super-admin/register-super-admin`: hanya `super_admin`.
- `POST /super-admin/register-asesor` dan `/super-admin/register-mitra`: `admin` dan `super_admin`.
- `GET /super-admin/users`, `GET`, `PUT`, `DELETE /super-admin/users/{id}`: hanya `super_admin`.
- *Catatan:* Body register admin dan super admin sama dengan Register Pemohon (semua field wajib, termasuk `password_confirmation`). Role ditentukan oleh endpoint. Akun yang dibuat tidak langsung mendapat token, jadi harus login terpisah. Register asesor dan mitra memakai validasi yang sama dengan register admin (`createAccountByRole`), jadi body-nya sama. Field opsional `no_reg_asesor` (teks, maks 64 karakter) dapat dikirim saat membuat akun asesor. Nilai ini dicocokkan dengan `no_reg_asesor` pada permohonan (lihat `schedule`), jadi isi dengan nilai yang sama.

## 8. Tanda Tangan (TTD)

- **Method:** `POST` | **Endpoint:** `/user/signature` | **Akses:** Login
- **Request Body:**

```json
{ "signature": "data:image/png;base64,iVBORw0KGgo..." }
```

- *Catatan:* `signature` wajib berupa teks. Menerima string base64 dari canvas frontend (diawali `data:image/...;base64,`). File disimpan, lalu path-nya diperbarui di tabel `users` (`signature_path`).

## 9. ⚠️ Alur Status (State Machine) – Wajib Dibaca Frontend

Agar tidak terjadi error 403 atau 409, frontend harus mengikuti urutan berikut. Setiap endpoint menolak permohonan yang statusnya belum sesuai.

| Dari status | Aksi | Oleh | Ke status |
|---|---|---|---|
| `Cek` | `get-sipp` (hanya jika jalur mitra) | mitra | `Cek` (status tetap, data SIPP tersimpan) |
| `Cek` | `submit-apl` | user, mitra | `Pemohon` |
| `Pemohon` | `approve-apl` | user | `Apl_01` |
| `Apl_01` | `review-apl01` | admin | `Peserta` (Diterima) atau `Batal` (Tidak diterima) |
| `Peserta` | (otomatis pukul 11.00) | sistem | `Tagih` |
| `Tagih` | `pay` (unggah bukti, status tidak berubah) | user, mitra | `Tagih` |
| `Tagih` | `confirm-payment` | admin | `Lunas` |
| `Lunas` | `schedule` | admin | `Assesor` |
| `Assesor` | `pre-assessment` | asesor | `Pra_Assesment` (lolos) atau `Assesor_No` |
| `Pra_Assesment` | `approve-assessment` | user | `Assesment` |
| `Assesment` | `final-decision` | asesor | `Assesor_Yes` atau `Assesor_No` |

Catatan:
- Admin harus sudah mengunggah tanda tangan sebelum `review-apl01`.
- `nominal` dapat dipanggil admin saat status `Peserta` atau `Tagih`.
- Tahap yang punya batas waktu 1×24 jam (`pay`, `approve-assessment`) ditolak jika terlambat.
- Status `Tagih` dibuat otomatis oleh sistem (dijadwalkan pukul 11.00), bukan oleh pemohon.

## 10. Catatan Penting untuk Frontend

1. Selalu kirim header `Accept: application/json`.
2. Jangan kirim field `role` saat register.
3. Semua endpoint register wajib menyertakan `password_confirmation`.
4. Jika menerima kode **401**, arahkan pengguna ke halaman login.
5. Jika menerima kode **403**, role tidak diizinkan atau data bukan milik akun tersebut.
6. Jika menerima kode **409**, status permohonan belum sesuai urutan. Tampilkan pesan dari server.
7. Jika menerima kode **422**, tampilkan pesan dari objek `errors` di bawah field yang bersangkutan.
8. Jika menerima kode **500**, itu bug di sisi backend (bukan salah frontend), silakan laporkan.
9. `POST /pay` tidak membuat status `Lunas`. Jangan tampilkan "Lunas" sebelum status benar-benar `Lunas`.

## 11. Status Keamanan dan Hal yang Masih Terbuka (Per 26 Nov 2026)

| Hal | Status |
|---|---|
| Pembatasan role per endpoint | Berjalan lewat `RoleMiddleware` |
| Pemohon tidak bisa membuat dirinya `Lunas` | Terkonfirmasi: `pay` hanya menyimpan bukti, `confirm-payment` khusus admin |
| `approve-assessment` hanya untuk data milik sendiri | Terkonfirmasi di kode (`where user_id`) |
| Asesor hanya memproses penugasannya | Terkonfirmasi di kode (`no_reg_asesor`) |
| Register tidak bisa menyelipkan `role` | Terverifikasi lewat `uji-auth.sh` |
| Akses data antar pemohon pada `GET /certifications`, PDF, `submit-apl`, `approve-apl` | Belum diuji dengan dua akun |

**Masih terbuka (backend):**
- `no_reg_asesor` bersifat opsional di register asesor dan di `schedule`. Jika keduanya kosong, nilai `null` dianggap sama sehingga semua asesor lolos pengecekan penugasan. Sebaiknya diwajibkan.
- Tidak ada lagi hal terbuka pada register.
- Aturan batal otomatis (5 hari kerja tanpa pergerakan) dan notifikasi WhatsApp di luar pra-asesmen belum dicek.

## 12. Akun Development

Hanya untuk pengujian lokal setelah `php artisan migrate:fresh --seed`. **Ganti atau hapus sebelum dipakai sungguhan.**

| Role | Email | Password |
|---|---|---|
| `super_admin` | `superadmin@lsp.com` | `password123` |
