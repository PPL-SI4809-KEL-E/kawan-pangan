# Kontrak tabel PSKE-20 untuk integrasi tim

Branch story: `PSKE-20-master-komoditas-instansi-gudang`. Kontrak ini mencatat implementasi PSKE-44/45/46 agar PSKE-15 tidak membuat master yang sama dua kali. PSKE-15/ERD final belum selesai; kesesuaian nama/tipe harus diperiksa bersama Calista sebelum integrasi migrasinya. Ini merupakan kontrak implementasi saat ini, bukan salinan ERD final.

| Tabel | Kolom dan tipe |
| --- | --- |
| `komoditas` | `id` bigint PK, `nama` varchar(150), `nama_unik` varchar(150) UNIQUE, `satuan` varchar(30), timestamps |
| `instansi` | `id` bigint PK, `nama` varchar(150), `alamat` varchar(500), `latitude` decimal(10,7), `longitude` decimal(10,7), timestamps |
| `gudang` | `id` bigint PK, `instansi_id` bigint FK, `kode_wilayah` varchar(20) FK, `nama` varchar(150), `alamat` varchar(500), `latitude` decimal(10,7), `longitude` decimal(10,7), `aktif` boolean default true, timestamps |
| `ambang_status` | `id` unsigned tinyint PK (ID global 1), `kritis_hari` unsigned smallint default 14, `aman_hari` unsigned smallint default 30, timestamps(3) |

- **Calista / PSKE-15:** gunakan migrasi master PSKE-20 yang sudah tersedia, lalu tambahkan relasi/tabel lanjutan sesuai ERD. Jangan memakai `Schema::hasTable` untuk menyembunyikan dua migrasi berbeda dengan kontrak yang tidak sama. Jika ERD menetapkan tipe/nama berbeda, sepakati pemetaan dan migration penyesuaian terlebih dahulu.
- **Yudha / PSKE-17:** opsi registrasi membaca `instansi.id,nama`. Akun Badan Resmi menyimpan `users.instansi_id`. Akun legacy yang memakai ID contoh perlu diaudit sebelum menambahkan foreign key.
- **Naufan / PSKE-48:** cakupan gudang menggunakan `gudang.instansi_id` dan `kode_wilayah`. Nilai `aktif=false` mempertahankan data; modul operasional perlu menyaring gudang aktif sesuai kebutuhan.
- **Rizky / PSKE-56:** baca `AmbangStatus::aktif()` lalu panggil `statusUntukHari($hariAsli)`. Hasil string `KRITIS`, `WASPADA`, atau `AMAN`; batas dibandingkan sebelum pembulatan.

Relasi yang sudah diberi foreign key: `gudang.instansi_id -> instansi.id` dan `gudang.kode_wilayah -> wilayah.kode`, dengan penghapusan induk dibatasi. Penghapusan instansi melalui aplikasi juga ditolak ketika dipakai akun, termasuk akun yang belum aktif.

ID pengaturan global dan kedua batas divalidasi di model `AmbangStatus`. Perubahan ambang selalu memperbarui ID 1, bukan menambah riwayat/baris aktif baru. Migrasi membuat default, sehingga pembaca tersedia setelah `php artisan migrate` tanpa bergantung pada seeder demo.
