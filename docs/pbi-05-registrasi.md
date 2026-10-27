# PBI-05 · Registrasi Akun dengan Surat Tugas

Jira: PSKE-2 (story) dengan subtask PSKE-4, PSKE-5, PSKE-6 · PIC: Hafizh · 3 SP · Sprint 1
Rujukan laporan: KF-01, UC01, layar M-07

Calon Kepala Wilayah dan staf Badan Resmi mendaftar sendiri lewat `/registrasi`, mengunggah surat tugas, lalu menunggu verifikasi Superadmin (PBI-06). Halaman memakai token dan komponen dari `design-system/` yang dimuat lewat Vite.

## Kriteria penerimaan

| Subtask | Kriteria | Diwujudkan oleh | Test |
| --- | --- | --- | --- |
| PSKE-4 | Form registrasi tanpa pilihan role Superadmin | `Role::bolehDaftar()` hanya berisi Kepala Wilayah dan Badan Resmi; `RegistrasiRequest` menolak role lain, termasuk `SUPERADMIN` yang dikirim langsung | `FormRegistrasiTest` |
| PSKE-5 | Unggah surat tugas PDF/JPG maksimal 2 MB | Aturan `mimes:pdf,jpg,jpeg`, `mimetypes`, `max:2048`; tipe dicek dari isi berkas, jadi berkas .exe berganti nama .pdf ditolak; berkas disimpan di disk `local` (storage/app/private) dan dicatat di `dokumen_pendukung` | `UnggahSuratTugasTest` |
| PSKE-6 | Akun baru berstatus Menunggu dan belum bisa login | Kolom `status` default `MENUNGGU`, tidak fillable, ditetapkan controller; halaman `/registrasi/terkirim`; `User::bolehLogin()` dan `User::pesanLoginDitolak()` untuk login | `StatusMenungguTest` |

## Berkas

| Berkas | Isi |
| --- | --- |
| `app/Enums/Role.php`, `app/Enums/StatusAkun.php` | Role akun dan status akun |
| `app/Http/Requests/RegistrasiRequest.php` | Aturan validasi dan pesan galat berbahasa Indonesia |
| `app/Http/Controllers/Auth/RegistrasiController.php` | Form, simpan akun + surat tugas dalam satu transaksi, halaman terkirim |
| `app/Http/Controllers/Auth/OpsiRegistrasiController.php` | JSON untuk dropdown wilayah dan instansi |
| `app/Support/DataContohRegistrasi.php` | Data contoh dropdown selama tabel `wilayah`/`instansi` belum ada |
| `app/Models/DokumenPendukung.php`, `app/Models/User.php` | Model surat tugas; relasi dan aturan login di User |
| `database/migrations/2026_10_27_1000*` | Kolom registrasi di `users`, tabel `dokumen_pendukung`, kolom status |
| `resources/views/layouts/auth.blade.php` | Layout halaman tanpa sidebar (bisa dipakai login PBI-04) |
| `resources/views/auth/registrasi*.blade.php` | Halaman M-07 dan halaman terkirim |
| `resources/css/design-system.css` | Memuat `design-system/` lewat Vite |
| `resources/css/registrasi.css`, `resources/js/registrasi.js` | Gaya dan interaksi khusus registrasi |
| `tests/Feature/Registrasi/*` | 27 feature test |

## Rute

| Method | URL | Nama rute |
| --- | --- | --- |
| GET | `/registrasi` | `registrasi.create` |
| POST | `/registrasi` | `registrasi.store` |
| GET | `/registrasi/terkirim` | `registrasi.terkirim` |
| GET | `/registrasi/opsi/wilayah?induk=<kode>` | `registrasi.opsi.wilayah` |
| GET | `/registrasi/opsi/instansi` | `registrasi.opsi.instansi` |

Semua rute memakai middleware `guest`.

## Mencoba di lokal

Ikuti README untuk setup awal, lalu:

```
php artisan migrate
npm run build      # atau npm run dev di terminal terpisah
php artisan serve
```

Buka `http://localhost:8000/registrasi`. Surat tugas yang berhasil diunggah tersimpan di `storage/app/private/surat-tugas/<id-user>/`.

## Menjalankan test

```
php artisan test --filter=Registrasi
```

## Catatan untuk anggota lain

**Login (Yudha, PBI-04).** Kredensial yang benar harus tetap ditolak bila akun belum aktif. Contoh di controller login:

```php
$user = User::where('email', $request->email)->first();

if ($user && Hash::check($request->password, $user->password) && ! $user->bolehLogin()) {
    return back()->withErrors(['email' => $user->pesanLoginDitolak()])->onlyInput('email');
}

Auth::attempt([...$request->only('email', 'password'), 'status' => StatusAkun::Aktif->value]);
```

Seeder Superadmin perlu mengisi `role = SUPERADMIN` dan `status = AKTIF` dengan `forceFill`, karena kolom status sengaja tidak fillable. Layout `layouts/auth.blade.php` bisa dipakai untuk halaman login.

**Skema (Syamsul, PBI-03, PBI-08, PBI-09).** Laravel memakai kolom `name` untuk nama pengguna. Kolom `kode_wilayah` dan `instansi_id` belum diberi foreign key karena tabel `wilayah` dan `instansi` belum ada. Setelah tabel itu dibuat:
- tambahkan foreign key,
- ubah aturan di `RegistrasiRequest` menjadi `exists:wilayah,kode` dan `exists:instansi,id`,
- dropdown otomatis membaca tabel bila kolomnya `wilayah(kode, nama, kode_induk)` dan `instansi(id, nama)`.

**Verifikasi (Yudha, PBI-06).** Kolom `verifikator_id`, `diverifikasi_pada`, dan `alasan_penolakan` sudah tersedia. Surat tugas dibuka dari disk privat dengan `Storage::disk('local')->temporaryUrl($dokumen->path, now()->addMinutes(10))` atau `Storage::download()`, dan hanya untuk Superadmin.

## Uji manual untuk Definition of Done

- [ ] Pilihan jenis akun hanya Kepala Wilayah dan Badan Resmi
- [ ] Kirim `role=SUPERADMIN` lewat Postman atau DevTools → galat "Jenis akun tidak diizinkan", tidak ada akun baru
- [ ] Unggah PDF 500 KB dan JPG 1,5 MB → diterima
- [ ] Unggah PNG, DOCX, atau PDF 3 MB → ditolak dengan pesan jelas
- [ ] Ganti nama berkas .exe menjadi .pdf lalu unggah → ditolak
- [ ] Registrasi berhasil → halaman "Pendaftaran Terkirim" dengan badge Menunggu verifikasi; `users.status = MENUNGGU`; berkas ada di `storage/app/private/surat-tugas/`
- [ ] Daftar ulang dengan email yang sama → "Email sudah terdaftar."
- [ ] Validasi gagal → isian dan pilihan wilayah tetap terisi, kecuali kata sandi dan berkas
- [ ] Lebar 360 px, 768 px, dan 1280 px tanpa geser ke samping
