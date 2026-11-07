# PBI-08 · Impor dan kelola master wilayah

Jira: PSKE-19; subtask PSKE-41 (impor), PSKE-42 (induk), PSKE-43 (penduduk).

## Menggunakan fitur

1. Ikuti setup README, jalankan `php artisan migrate` dan `npm run build`.
2. Masuk sebagai Superadmin dengan status `AKTIF`, lalu buka `/admin/wilayah`.
3. Unduh template, isi dengan data Kemendagri yang telah disiapkan dalam format di bawah, lalu unggah CSV.
4. Periksa ringkasan hasil impor. Cari wilayah atau pilih tingkat; tombol **Lihat anak** menampilkan wilayah dengan induk tersebut.
5. Pilih **Ubah penduduk**, isi jumlah jiwa tanpa pemisah ribuan, lalu simpan.

Rute login belum ada di `main` saat fitur ini dibuat (PBI-04 dikerjakan terpisah). Semua endpoint pengelolaan tetap memerlukan sesi autentikasi; tamu menerima 401, akun selain Superadmin aktif menerima 403. Pengujian menggunakan sesi `actingAs`, tanpa menambahkan login khusus atau bypass ke aplikasi. Integrasikan tautan **Wilayah** ke navigasi setelah modul login tersedia.

## Kontrak CSV

- UTF-8, boleh memakai BOM; pemisah koma atau titik koma; ekstensi `.csv`.
- Header wajib `kode,nama,kode_induk`; `jumlah_penduduk` opsional. Urutan kolom boleh berbeda. Header tidak dikenal/duplikat ditolak.
- Nama wajib diisi, maksimal 150 karakter, tanpa karakter kontrol. Nama yang mengandung pemisah dibungkus tanda kutip CSV.
- Kode disimpan sebagai string dengan titik, tanpa konversi ke angka. Tingkat diturunkan dari kode: `PP`, `PP.KK`, `PP.KK.CC`, `PP.KK.CC.DDDD`.
- Provinsi tidak mempunyai induk. Tingkat lainnya wajib mencantumkan kode induk langsung sesuai awalan kode. Induk harus ada dalam CSV yang sama atau database. Urutan baris bebas.
- Penduduk berupa bilangan bulat 0–2.147.483.647. Kosong/tanpa kolom: nilai lama dipertahankan; wilayah baru mendapat `NULL` (belum tersedia). Angka 0 disimpan sebagai nol.
- Maksimal 20 MB dan 100.000 wilayah per berkas. Atur `upload_max_filesize=20M` dan `post_max_size=21M` (atau lebih besar) pada server PHP serta batas request reverse proxy. Batas aplikasi tetap 20 MB.

Contoh format berikut **data ilustrasi untuk pengujian, bukan dataset resmi**:

```csv
kode,nama,kode_induk,jumlah_penduduk
32,Provinsi Contoh,,
32.04,Kabupaten Contoh,32,
32.04.12,Kecamatan Contoh,32.04,
32.04.12.2001,Desa Contoh,32.04.12,1200
```

Operator menyiapkan kode dan nama dari sumber resmi Kemendagri beserta versi/tanggal sumbernya. Referensi struktur kode: [Permendagri 58 Tahun 2021](https://peraturan.bpk.go.id/Details/196233/permendagri-no-58-tahun-2021). Aplikasi memvalidasi struktur dan hubungan kode, bukan memverifikasi keanggotaan kode terhadap daftar resmi melalui jaringan. Tidak ada dataset nasional yang ditanam atau data contoh yang otomatis dimasukkan ke master. PDF/XLSX sumber perlu dinormalisasi menjadi CSV sesuai template sebelum diunggah.

## Perilaku penyimpanan

- Seluruh CSV divalidasi sebelum penulisan. Maksimal 20 kesalahan ditampilkan per unggahan; perbaiki lalu unggah kembali.
- Penulisan dalam satu transaksi, per tingkat dan batch 100 baris. Jika penulisan gagal, transaksi di-rollback.
- Kode unik menjadi primary key. Impor ulang memperbarui nama dan penduduk yang diisi, mempertahankan kode/induk serta wilayah yang tidak disebut dalam berkas. Tidak ada penghapusan massal.
- Foreign key `wilayah.kode_induk` membatasi penghapusan/perubahan induk yang masih dipakai. Penyimpanan melalui model `Wilayah` juga memvalidasi induk dan melarang perubahan kode yang sudah tersimpan. Query builder langsung melewati event model; gunakan model atau service impor untuk penulisan master.
- Jumlah penduduk setiap wilayah disimpan mandiri, tanpa penjumlahan otomatis dari anak. Endpoint pembaruan hanya menerima kolom penduduk.
- CSV dibaca dari unggahan sementara dan tidak disimpan sebagai berkas publik.

## Integrasi tim

- Tabel `wilayah(kode,nama,level,kode_induk,jumlah_penduduk,created_at,updated_at)` mengikuti kontrak dropdown registrasi yang sudah ada. Model `Wilayah` menggunakan primary key string `kode`.
- Dropdown registrasi otomatis memakai tabel ini. Setelah migrasi dan sebelum impor, dropdown kosong dengan `contoh: false`.
- PBI-03/PSKE-15 masih dikerjakan terpisah. Koordinasikan migrasi tabel wilayah pada penggabungan agar tidak membuat tabel yang sama dua kali. Foreign key `users.kode_wilayah` dan validasi hierarki formulir registrasi perlu diselaraskan dalam pekerjaan integrasi skema tersebut, termasuk data pengguna yang sudah memakai kode contoh.
- PSKE-18 (pembatasan akses) belum digabungkan ke `main`; middleware `SuperadminAktif` khusus master wilayah memakai enum role/status yang sudah tersedia. Selaraskan pemakaian middleware saat modul akses digabungkan.

## Rute

| Method | URL | Fungsi |
| --- | --- | --- |
| GET | `/admin/wilayah` | Daftar, pencarian `q`, filter `level`/`induk`, 25 wilayah per halaman |
| GET | `/admin/wilayah/template` | Unduh header template kosong |
| POST | `/admin/wilayah/impor` | Unggah field `berkas` |
| GET | `/admin/wilayah/{kode}/edit` | Form penduduk |
| PATCH | `/admin/wilayah/{kode}` | Perbarui field `jumlah_penduduk` |

Semua memakai middleware `SuperadminAktif`, sesi web, dan perlindungan CSRF Laravel.

## Verifikasi

```sh
php artisan test
npm run build
```

`tests/Feature/Wilayah/MasterWilayahTest.php` mencakup otorisasi seluruh endpoint, impor empat tingkat dengan urutan acak, dropdown registrasi, impor ulang/BOM/pemisah titik koma, nilai penduduk kosong/nol, validasi berkas/kode/induk/duplikat/penduduk, rollback transaksi, proteksi relasi, pencarian/filter/paginasi, serta pembaruan penduduk.

Uji manual setelah modul login terintegrasi: buka halaman pada lebar 360 px dan desktop, unggah CSV valid/tidak valid, telusuri anak wilayah, periksa navigasi halaman berikutnya, lalu ubah jumlah penduduk dan muat ulang.
