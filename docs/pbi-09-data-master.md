# PBI-09 — Master komoditas, instansi, gudang, dan ambang status

Implementasi PSKE-44, PSKE-45, dan PSKE-46 di bawah PSKE-20. Seluruh pekerjaan berada pada branch story `PSKE-20-master-komoditas-instansi-gudang`.

## Penggunaan

1. Jalankan `php artisan migrate`, kemudian `npm run build`.
2. Gunakan sesi akun `SUPERADMIN` berstatus `AKTIF`. Menu **Data Master** menyediakan Komoditas, Instansi, Gudang, dan Wilayah.
3. Tambahkan komoditas beserta satuannya, misalnya kg, ton, atau liter. Nama wajib unik tanpa membedakan huruf besar dan spasi berlebih. Satuan berupa teks wajib, maksimal 30 karakter; belum ada konversi otomatis antar satuan.
4. Tambahkan instansi dengan nama, alamat, latitude, dan longitude. Instansi langsung tersedia pada dropdown registrasi Badan Resmi. Jika master kosong, dropdown kosong dan tidak memakai data contoh. Registrasi menolak ID instansi yang tidak tersedia.
5. Tambahkan gudang dengan instansi pengelola, kode wilayah yang sudah diimpor lewat PSKE-19, nama, alamat, koordinat, dan status. Ketik awalan kode wilayah untuk mendapatkan maksimal 20 saran. Tanpa JavaScript, kode dapat disalin dari halaman master wilayah.
6. Gunakan **Nonaktifkan** untuk mempertahankan data gudang. Gudang tetap terlihat dan dapat difilter menurut status atau instansi, lalu diaktifkan kembali. Endpoint status menerima nilai tujuan eksplisit, sehingga pengiriman ulang tidak membalik status.
7. Ubah data lewat **Ubah**, atau gunakan **Hapus** untuk menghapus data yang tidak diperlukan. Instansi yang masih dipakai akun atau gudang, termasuk gudang nonaktif, ditolak penghapusannya.
8. Buka **Ambang Status** (`/admin/ambang-status`) untuk mengubah batas hari ketersediaan pangan. Migrasi menyediakan pengaturan awal 14/30 tanpa memerlukan seeder. Kedua batas berupa bilangan bulat positif, maksimal 65535, dengan `kritis_hari < aman_hari`.

## Ambang status dan integrasi PSKE-56

Aturan berlaku pada nilai hari asli, sebelum pembulatan:

| Kondisi | Status |
| --- | --- |
| Hari < `kritis_hari` | `KRITIS` |
| Hari > `aman_hari` | `AMAN` |
| Selebihnya, termasuk tepat pada kedua batas | `WASPADA` |

Dengan default 14/30: 13,9999999 Kritis; 14 dan 30 Waspada; 30,0000001 Aman. Baca pengaturan melalui API yang sama pada setiap perhitungan atau satu kali sebagai snapshot untuk satu batch:

```php
use App\Models\AmbangStatus;

$ambang = AmbangStatus::aktif();
$status = $ambang->statusUntukHari($hariTanpaPembulatan);
// string KRITIS, WASPADA, atau AMAN
```

`aktif()` membaca baris global ID 1 dari database tanpa menulis atau mengembalikan default diam-diam. Pemanggilan berikutnya mengambil nilai terbaru; objek yang sudah dibaca adalah snapshot. Jangan menyimpan cache tanpa invalidasi ketika pengaturan diubah. `statusUntukHari()` menolak NaN/infinity dan menerima nol sebagai Kritis. Perhitungan estimasi stok/konsumsi tetap menjadi lingkup PSKE-56.

Nama maksimal 150 karakter, alamat maksimal 500 karakter. Koordinat wajib pada instansi dan gudang, berupa derajat desimal dengan titik; latitude -90 sampai 90, longitude -180 sampai 180. Nol diterima dan maksimal tujuh angka di belakang titik disimpan. Validasi rentang tidak memverifikasi kesesuaian koordinat dengan batas wilayah geografis.

Seluruh daftar menyediakan pencarian nama dan paginasi 25 baris. Form menyimpan kembali input saat validasi gagal. Tabel dapat digeser pada layar kecil dan navigasi menggunakan layout/design system yang sudah tersedia.

## Rute dan akses

Ketiga resource memakai pola yang sama dengan nama rute `admin.{resource}.{action}`:

| Method | URL | Fungsi |
| --- | --- | --- |
| GET | `/admin/{resource}` | Daftar dan pencarian `q` |
| GET | `/admin/{resource}/create` | Form tambah |
| POST | `/admin/{resource}` | Simpan baru |
| GET | `/admin/{resource}/{id}/edit` | Form ubah |
| PATCH / PUT | `/admin/{resource}/{id}` | Simpan perubahan |
| DELETE | `/admin/{resource}/{id}` | Hapus |
| PATCH | `/admin/gudang/{id}/status` | Ubah hanya `aktif`, nilai `0` atau `1` |
| GET | `/admin/gudang/opsi-wilayah?q=...` | Saran kode/nama wilayah, maksimal 20 |
| GET | `/admin/ambang-status` | Form pengaturan ambang |
| PATCH | `/admin/ambang-status` | Simpan `kritis_hari` dan `aman_hari` |

`{resource}` adalah `komoditas`, `instansi`, atau `gudang`. Daftar gudang menerima filter `instansi_id` dan `aktif=0/1`. Seluruh endpoint admin, termasuk wilayah dan ambang status, memakai middleware `SuperadminAktif`, sesi web, dan CSRF. Akun tanpa izin menerima 403. Tamu web diarahkan ke named route `login` ketika PSKE-16 tersedia; request JSON tetap 401. Selama rute login belum tersedia, tamu web menerima 401. Pengujian memverifikasi kedua kondisi memakai rute sementara khusus test, tanpa menambahkan bypass autentikasi ke aplikasi.

## Kontrak data dan integrasi tim

- `komoditas(id,nama,nama_unik,satuan,timestamps)`: model `Komoditas` mengisi `nama_unik` dari nama yang dirapikan dan diubah ke huruf kecil. Unique index melindungi penulisan bersamaan, selain validasi formulir. Gunakan model untuk penulisan, karena query builder langsung melewati normalisasi event model.
- `instansi(id,nama,alamat,latitude,longitude,timestamps)`: model `Instansi`, relasi `gudang()` dan `pengguna()`. Endpoint dropdown registrasi tetap membaca `id,nama` sesuai kontrak sebelumnya.
- `gudang(id,instansi_id,kode_wilayah,nama,alamat,latitude,longitude,aktif,timestamps)`: model `Gudang`, relasi `instansi()` dan `wilayah()`. Foreign key membatasi penghapusan instansi dan wilayah yang dipakai gudang.
- `ambang_status(id,kritis_hari,aman_hari,created_at,updated_at)`: model `AmbangStatus`, ID global 1, default 14/30. Penyimpanan melalui model memvalidasi ID dan urutan batas; tidak ada endpoint membuat/menghapus baris lain. Query builder langsung melewati validasi model. Kontrak lengkap tersedia di [pbi-09-kontrak-tabel.md](pbi-09-kontrak-tabel.md).
- Tidak ada data master contoh yang dimasukkan otomatis. Nama instansi/gudang boleh sama; keunikan nama diwajibkan pada komoditas.
- PSKE-15/PBI-03 masih dikerjakan terpisah. Selaraskan nama tabel, koordinat, dan migrasi sebelum penggabungan agar tidak membuat master dua kali. Kolom `users.instansi_id` sebelumnya belum memiliki foreign key; validasi registrasi dan pemeriksaan penghapusan melindungi jalur aplikasi saat ini. Penambahan foreign key memerlukan audit ID contoh/legacy dan penyelarasan migrasi pada integrasi PSKE-15, termasuk transaksi registrasi/penghapusan bersamaan.
- Modul stok dan penugasan belum tersedia di `main`. Modul tersebut perlu membaca `gudang.aktif` untuk pilihan operasional, dan menambahkan foreign key/proteksi penghapusan komoditas dan gudang saat sudah memiliki referensi stok atau penugasan. Nonaktif saat ini mengubah penanda status tanpa menghapus master.
- Perubahan otorisasi PSKE-18 dari PR #5 sudah diintegrasikan bersama PR #8 dari `main`. Policy Gudang dari PSKE-18 tetap tersedia; seluruh halaman master tetap menggunakan `SuperadminAktif` dari PSKE-19 dan memeriksa status akun aktif.

## Verifikasi

```sh
php artisan test
npm run build
```

`tests/Feature/MasterData/MasterDataTest.php` mencakup otorisasi setiap endpoint, CRUD ketiga master, duplikat komoditas dan unique index, validasi koordinat/rentang/nol, relasi yang tidak tersedia, proteksi instansi yang dipakai, foreign key, aktif/nonaktif berulang, pencarian, paginasi, filter nonaktif, serta batas saran wilayah. Pengujian registrasi mencakup dropdown kosong dan validasi instansi yang tersedia.

`tests/Feature/MasterData/AmbangStatusTest.php` mencakup default tanpa seeder, perubahan tersimpan, input tidak valid, pembatasan role/status, redirect login/JSON, nilai batas sebelum pembulatan, pembacaan nilai kustom, serta validasi model.

Uji manual setelah modul login tersedia: buka masing-masing master pada 360 px dan desktop, tambahkan/ubah/hapus data, coba komoditas duplikat, periksa kode wilayah dan koordinat, nonaktifkan lalu aktifkan gudang, dan coba menghapus instansi yang masih dipakai.
