# PBI-03 · Skema basis data dan data simulasi (PSKE-15)

Jira: PSKE-15; subtask PSKE-28 (migrasi 22 tabel ERD), PSKE-29 (seeder data simulasi 60 hari), PSKE-30 (label data contoh).

## Daftar 22 Tabel ERD dan Relasinya

Seluruh tabel dirancang kompatibel untuk SQLite (pengembangan lokal & pengujian otomatis) dan MySQL 8 (server produksi/demo). Jumlah kuantitas stok memakai `decimal(14,3)` dan waktu mutasi memakai `datetime(3)`.

| No | Nama Tabel | Kunci Utama & Unik | Relasi (Foreign Key) & Aturan Utama |
| :--- | :--- | :--- | :--- |
| 1 | `wilayah` | PK `kode` (`varchar(20)`) | `kode_induk -> wilayah.kode` (4 tingkat Kemendagri: `PP`, `PP.KK`, `PP.KK.CC`, `PP.KK.CC.DDDD`), koordinat `lat`/`lng`, `jumlah_penduduk`, `is_contoh`. |
| 2 | `instansi` | PK `id` | Master badan resmi pengelola gudang (`nama`, `jenis`, `alamat`, `telepon`, `latitude`, `longitude`, `aktif`, `is_contoh`). |
| 3 | `users` | PK `id`, UNIQUE `email` | `verifikator_id -> users.id`, atribut `role`, `kode_wilayah`, `instansi_id`, `status`, `is_contoh`. |
| 4 | `dokumen_pendukung` | PK `id` | `user_id -> users.id` (berkas surat tugas registrasi). |
| 5 | `komoditas` | PK `id`, UNIQUE `nama_unik` | Master komoditas pangan (`nama`, `satuan`, `kebutuhan_per_kapita_harian` `decimal(14,3)`, `aktif`, `is_contoh`). |
| 6 | `ambang_status` | PK `id` (ID global `1`) | `diubah_oleh -> users.id`, `kritis_hari` (default 14), `aman_hari` (default 30), `berlaku_sejak`. |
| 7 | `gudang` | PK `id`, UNIQUE `kode_gudang` | `instansi_id -> instansi.id`, `kode_wilayah -> wilayah.kode`, `latitude`, `longitude`, `radius_km`, `aktif`, `is_contoh`. |
| 8 | `cakupan_gudang` | PK `id`, UNIQUE `(gudang_id, kode_kecamatan)` | `gudang_id -> gudang.id`, `kode_kecamatan -> wilayah.kode`, `is_utama`. |
| 9 | `stok` | PK `id`, UNIQUE `(gudang_id, komoditas_id)` | `gudang_id -> gudang.id`, `komoditas_id -> komoditas.id`, `jumlah_fisik_layak` `decimal(14,3)` (wajib $\ge 0$), `mulai_pencatatan_lengkap`, `diperbarui_pada`, `is_contoh`. |
| 10 | `mutasi_stok` | PK `id`, UNIQUE `ref_operasi` | `stok_id -> stok.id`, `pengiriman_id -> pengiriman.id` (nullable), `pencatat_id -> users.id`, `jenis` (`PENERIMAAN`, `PENYALURAN`, `RETUR`, `RUSAK`, `PENYESUAIAN`), `jumlah` `decimal(14,3)`, `saldo_setelah` `decimal(14,3)` (wajib $\ge 0$), `waktu` `datetime(3)`, `is_contoh`. |
| 11 | `cadangan` | PK `id` | `stok_id -> stok.id`, `pengiriman_id -> pengiriman.id`, `jumlah` `decimal(14,3)` (wajib $\ge 0$), `status` (`AKTIF`, `SELESAI`, `BATAL`), `is_contoh`. |
| 12 | `laporan` | PK `id`, UNIQUE `kode_laporan` | `pelapor_id -> users.id`, `kode_wilayah -> wilayah.kode`, `verifikator_id -> users.id` (nullable), `jenis`, `urgensi`, `status`, `is_contoh`. |
| 13 | `detail_laporan` | PK `id`, UNIQUE `(laporan_id, komoditas_id)` | `laporan_id -> laporan.id`, `komoditas_id -> komoditas.id`, `jumlah_dibutuhkan` `decimal(14,3)`, `stok_dilaporkan` `decimal(14,3)`. |
| 14 | `lampiran_laporan` | PK `id` | `laporan_id -> laporan.id`, `path_file`, `jenis_file`, `diunggah_pada`. |
| 15 | `pengiriman` | PK `id`, UNIQUE `no_resi` | `laporan_id -> laporan.id` (nullable), `gudang_asal_id -> gudang.id`, `kode_wilayah_tujuan -> wilayah.kode`, `pembuat_id`/`penyetuju_id`/`petugas_id -> users.id`, `status`, `is_contoh`. |
| 16 | `detail_pengiriman` | PK `id`, UNIQUE `(pengiriman_id, komoditas_id)` | `pengiriman_id -> pengiriman.id`, `komoditas_id -> komoditas.id`, `jumlah_dikirim` `decimal(14,3)`. |
| 17 | `checkpoint` | PK `id` | `pengiriman_id -> pengiriman.id`, `petugas_id -> users.id`, `jenis`, `lokasi`, `lat`, `lng`, `waktu`. |
| 18 | `penerimaan` | PK `id`, UNIQUE `ref_operasi` | `pengiriman_id -> pengiriman.id`, `pencatat_id -> users.id`, `waktu_diterima`, `bukti_foto`, `catatan`. |
| 19 | `detail_penerimaan` | PK `id`, UNIQUE `(penerimaan_id, komoditas_id)` | `penerimaan_id -> penerimaan.id`, `komoditas_id -> komoditas.id`, `jumlah_dikirim` `decimal(14,3)`, `jumlah_diterima` `decimal(14,3)`, `alasan_selisih`. |
| 20 | `artikel` | PK `id` | `penulis_id -> users.id`, `judul`, `isi`, `kategori`, `terbit`, `diterbitkan_pada`, `is_contoh`. |
| 21 | `kontak` | PK `id` | `kode_wilayah -> wilayah.kode`, `instansi_id -> instansi.id` (nullable), `nama`, `jabatan`, `telepon`, `email`, `is_contoh`. |
| 22 | `notifikasi` | PK `id` | `penerima_id -> users.id`, `jenis`, `pesan`, `tautan`, `dibaca`, `dibuat_pada`. |

## Aturan Perhitungan Stok & Mutasi

1. **Stok tersedia:** `tersedia = stok.jumlah_fisik_layak - SUM(cadangan.jumlah WHERE status = 'AKTIF')`.
2. **Pemakaian harian:** total keluar `mutasi_stok` berjenis `PENYALURAN` selama 30 hari terakhir dibagi 30 (`RETUR`, `RUSAK`, dan `PENYESUAIAN` tidak dihitung).
3. **Ketahanan (hari):** `tersedia / pemakaian_harian`, dibandingkan sebelum pembulatan terhadap `AmbangStatus::aktif()` (**Kritis < 14**, **Waspada 14–30**, **Aman > 30**).
4. **Integritas saldo:** `stok.jumlah_fisik_layak` dan `mutasi_stok.saldo_setelah` dilindungi di tingkat model dan basis data (trigger SQLite & `CHECK` constraint MySQL 8) agar tidak pernah bernilai negatif, serta `ref_operasi` wajib unik.

## Cara Menjalankan Migrasi dan Seeder Simulasi

Untuk membangun ulang seluruh skema 22 tabel sekaligus mengisi data simulasi 60 hari:

```sh
php artisan migrate:fresh --seed
```

Atau menjalankan seeder simulasi saja (deterministik dan aman diulang tanpa menggandakan baris):

```sh
php artisan db:seed --class=DataSimulasiSeeder
```

### Skenario Data Simulasi (`DataSimulasiSeeder`)

Seluruh baris yang dibuat seeder memiliki `is_contoh = true` dan ditampilkan dengan `<span class="badge badge-netral">Data contoh</span>` pada antarmuka:

| Wilayah Contoh | Gudang | Komoditas | Fisik (`kg`) | Cadangan Aktif (`kg`) | Tersedia (`kg`) | Penyaluran Harian (`kg/hari`) | Ketahanan | Status |
| :--- | :--- | :--- | ---: | ---: | ---: | ---: | ---: | :--- |
| `32.04` Kab. Bandung (`32.04.12` Dayeuhkolot) | `GDG-BDG-01` Gudang Bulog Dayeuhkolot | Beras Medium | 4.600,000 | 100,000 | 4.500,000 | 100,000 | 45 hari | **AMAN** (> 30) |
| `32.73` Kota Bandung (`32.73.01` Sukasari) | `GDG-KBDG-01` Gudang Pangan Sukasari | Beras Medium | 2.150,000 | 50,000 | 2.100,000 | 100,000 | 21 hari | **WASPADA** (14–30) |
| `32.17` Kab. Bandung Barat (`32.17.01` Lembang) | `GDG-KBB-01` Gudang Pangan Lembang | Beras Medium | 950,000 | 50,000 | 900,000 | 100,000 | 9 hari | **KRITIS** (< 14) |

## Verifikasi

```sh
php artisan test --filter=SkemaDanSimulasiTest
php artisan test
```

