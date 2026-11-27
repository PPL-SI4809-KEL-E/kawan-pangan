# Design System Kawan Pangan v3

Token ada di `design-system/tokens.css`, komponen di `design-system/components.css`, dan kerangka halaman di
`design-system/layout.css`. Ketiganya dimuat lewat `resources/css/design-system.css`. Semua warna, ukuran, dan
jarak wajib memakai token, misalnya `color: var(--color-text)`, bukan kode warna langsung.

Versi 3 (PSKE-96) mengganti tampilan v2 (PSKE-85) yang terasa seperti template: semua isi dipotong jadi kartu putih
bundar yang sama, label huruf kapital di atas setiap bagian, biru royal, dan peta dasar yang ramai.
Arah v3 adalah **kertas dan tinta**. Latarnya abu kehijauan yang tenang, teksnya tinta gelap, dan hanya ada satu
warna utama, hijau laut. Warna paling keras di halaman adalah warna status, supaya Kritis, Waspada, dan Aman
langsung terbaca. Nama kelas v1 dan v2 tetap ada, jadi halaman admin dan panel ikut berubah tanpa diedit.

## Warna
| Token | Nilai | Dipakai untuk |
| --- | --- | --- |
| `--color-primary` | #0E5E6F | Satu-satunya warna utama: tombol utama, tautan, menu aktif. Sengaja bukan hijau supaya tidak tertukar dengan Aman |
| `--color-primary-soft` | #E3EFF0 | Latar menu aktif dan baris terpilih |
| `--color-accent` | = primary | Alias supaya kelas lama (`.btn-accent`) ikut |
| `--color-canvas` | #F2F3EF | Latar halaman |
| `--color-bg` | #F7F8F5 | Isian halus: kepala tabel, hover |
| `--color-surface` | #FFFFFF | Panel, tabel, form |
| `--color-border` / `--color-border-strong` | #E2E5DF / #C9CEC6 | Garis tipis, garis input |
| `--color-text` | #17201C | Tinta, teks utama |
| `--color-text-muted` | #56605B | Teks sekunder (kontras 6.3:1 di atas putih) |

## Warna status
Ada dua set: warna **teks dan badge** (kontras minimal 4.5:1) dan warna **isian** untuk peta, bar, dan garis status.

| Status | Teks | Latar badge | Isian peta (`--fill-*`) | Arti |
| --- | --- | --- | --- | --- |
| Kritis | #B3321C | #FBE6E1 | #D2452B | Ketahanan stok < 14 hari, laporan ditolak |
| Waspada | #8F5406 | #FBEFD6 | #E2A23A | 14 sampai 30 hari, menunggu verifikasi |
| Aman | #2E6B2F | #E4F0E0 | #5B9A4C | Lebih dari 30 hari, selesai |
| Info | #0E5E6F | #E3EFF0 | | Diajukan, dalam pengiriman |
| Netral | #4A5450 | #EEF0EC | #A3ABA6 | Draf, riwayat kurang (ketahanan belum bisa dihitung) |

## Tipografi
Font **Instrument Sans** untuk judul dan isi, dimuat dari Google Fonts di `layouts/partials/head.blade.php`.
Font ini dipilih karena angka tabularnya tidak ikut melebarkan titik ribuan, jadi `1.284.560 kg` tetap rapat.
Kode (misalnya nomor pengiriman) memakai monospace bawaan sistem.

- Ukuran: 12 / 14 / 16 / 18 / 24 / 32 / 44 px. Judul halaman publik 28 sampai 32 px dengan tracking -0.02em.
- Label ditulis kalimat biasa, **tidak huruf kapital semua**. `.eyebrow`, `.meta-label`, dan `.nav-label` sudah ikut.
- Angka yang dibandingkan (hari, jumlah di tabel) memakai `font-variant-numeric: tabular-nums` (kelas `.num`).

## Spasi, sudut, bayangan
- Spasi kelipatan 4px: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64 (`--space-*`).
- Sudut berjenjang: 6px (badge, tombol kecil), 8px (input, tombol), 12px (kartu), 16px (lembar dan laci).
- Bayangan hanya untuk yang benar-benar melayang: laci menu, lembar detail di HP, contoh tampilan di halaman masuk.
  Panel biasa cukup garis tipis.

## Gerak
- Tombol mengecil `scale(0.97)` saat ditekan. Transisi menyebut properti satu per satu, tidak `all`.
- Kurva: `--ease-out` (cubic-bezier(0.23, 1, 0.32, 1)) untuk masuk, `--ease-drawer` untuk laci dan lembar.
- Lembar detail kecamatan muncul dengan `@starting-style` (geser dari bawah di HP, dari kanan di desktop).
  Pindah antar kecamatan tidak dianimasikan karena dipakai berkali-kali.
- Efek hover gerak dibatasi ke `@media (hover: hover) and (pointer: fine)`. `prefers-reduced-motion` mematikan semuanya.

## Komponen
| Kelas | Fungsi |
| --- | --- |
| `.btn` + `-primary/-secondary/-accent/-danger/-ghost`, `-sm`, `-icon`, `-block` | Tombol |
| `.field`, `.input`, `.select`, `.textarea`, `.field-error`, `.check` | Form; error tampil di bawah field |
| `.card`, `.card-head`, `.card-title`, `.card-footer` | Panel putih |
| `.badge` + `-kritis/-waspada/-aman/-info/-netral` | Status |
| `.table-wrap` > `.table`, `.num`, `.actions` | Tabel yang bisa digeser di HP |
| `.alert` + `-error/-success/-warning/-info`, `.alert-list` | Pesan; `.alert-list` untuk ringkasan error yang menautkan ke field |
| `.seg` > `.seg-item[aria-pressed]` > `.seg-count` | Tab filter bersegmen dengan jumlah |
| `.sebaran-bar` > `.sebaran-seg[data-status]` (`--n`) | **Baru v3.** Bar proporsi status, sekaligus legenda warna peta |
| `.sebaran-opsi` > `.sebaran-opt[aria-pressed]` | **Baru v3.** Filter status dengan warna, jumlah, dan batas hari |
| `.wil-list` > `.wil-row[data-status][aria-current]` | **Baru v3.** Baris kecamatan: garis status di kiri, angka hari besar di kanan |
| `.sheet` > `.sheet-section`, `.sheet-head` | **Baru v3.** Satu lembar dibagi garis tipis, pengganti tumpukan kartu |
| `.state` (+ `-info/-waspada/-kritis`) > `.state-icon`, `.state-content` | **Baru v3.** Keadaan kosong atau khusus dengan ikon garis |
| `.route` | **Baru v3.** Rute asal ke tujuan pengiriman |
| `.qty` > `.input` + `.qty-unit` | **Baru v3.** Input jumlah dengan satuan di dalamnya |
| `.dropzone` | **Baru v3.** Zona unggah; input file asli ada di dalam label, jadi hanya ada satu kontrol |
| `.action-bar` | **Baru v3.** Tombol aksi yang menempel di bawah layar HP |
| `.ledger` > `li.is-masuk/.is-keluar/.is-rusak` | **Baru v3.** Catatan mutasi dengan ikon arah |
| `.swatch` + `-kritis/-waspada/-aman/-netral` | Kotak warna status kecil |
| `.item-card`, `.timeline`, `.meter` | Komponen v2, masih ada untuk halaman lama |
| `.tile`, `.empty-state`, `.skeleton`, `.avatar`, `.kbd`, `.icon` | Tile dashboard, kosong, memuat, inisial, tombol pintas, ikon |

Ikon garis (stroke 1.75) ada di sprite `resources/views/layouts/partials/ikon.blade.php`, dipakai dengan
`<svg class="icon"><use href="#i-map"/></svg>`. Ikon baru di v3: `i-arrow-in`, `i-arrow-out`, `i-arrow-left`, `i-alert`,
`i-camera`, `i-lock`, `i-history`.

Tanda merek (butir padi di kotak hijau laut) digambar lewat CSS di `.app-brand-mark`. Teks "KP" di markup lama
disembunyikan, jadi view tidak perlu diubah.

## Layout
| Bagian | Fungsi |
| --- | --- |
| `layouts/app.blade.php` | Shell untuk semua role yang sudah masuk. `layouts.admin` dan `layouts.panel` meneruskan ke sini |
| `layouts/partials/sidebar.blade.php` | Menu per role, pencarian menu (Ctrl/Cmd + K), blok pengguna, tombol keluar |
| `.app-sidebar` | Putih, menempel di tepi kiri dengan garis tipis (desktop), laci di HP, bisa diciutkan |
| `.page-header`, `.page-actions` | Judul halaman dan tombol aksi |
| `.welcome-card`, `.tile-grid`, `.section-head` | Dashboard per role. Sambutan tanpa panel berwarna |
| `.grid .grid-2/3/4`, `.grid-main-aside`, `.stack`, `.cluster` | Grid dan tumpukan |
| `layouts/auth.blade.php` > `.auth-page` | Masuk dan daftar: formulir di kiri, contoh tampilan peta (`.preview`) di kanan pada desktop |

Sudah dicek pada lebar 360, 390, 768, 1024, dan 1440px tanpa scroll ke samping.

## Pola layar peta (atlas)
Dipakai beranda publik (`welcome.blade.php`, `resources/css/beranda.css`, `resources/js/beranda.js`).

- **Desktop:** panel kiri (judul, bar sebaran dan filter, pencarian, daftar `.wil-row`) menempel ke peta tanpa kartu
  melayang. Detail kecamatan menempel di sisi kanan peta.
- **HP:** peta di atas, daftar di bawah, detail muncul sebagai lembar dari bawah layar.
- Peta dasar OpenStreetMap dibuat abu lewat CSS supaya warna status yang paling menonjol.
- Daftar diurutkan dari stok yang paling cepat habis. Kecamatan yang belum bisa dihitung ada di paling bawah.
- Gambar wilayah di peta ada di satu fungsi `gambarWilayah()`. PSKE-69 cukup mengganti isi fungsi itu dengan polygon
  batas kecamatan (warna = status).

### Aturan tampilan angka
- Hari dibulatkan **ke bawah**, supaya 13,6 hari tidak tampil "14 hari" dengan status Kritis.
- Kurang dari 1 hari tampil "< 1 hari". Stok 0 tampil "Habis".
- `hari` kosong atau status di luar `aman/waspada/kritis` dianggap **riwayat kurang**: abu-abu, tertulis "Belum dihitung".

### Kontrak data untuk backend
Selama controller belum mengirim data, beranda memakai **data simulasi** dan menampilkan "Angka contoh".
Untuk menyambungkan data asli, route `/` cukup mengirim `$petaWilayah` ke view `welcome`; tampilan tidak perlu diubah.
Array kosong menampilkan "Belum ada data kecamatan".

```php
return view('welcome', ['petaWilayah' => [
    [
        'id' => '32.73.01',             // unik
        'nama' => 'Coblong',
        'kota' => 'Kota Bandung',
        'lat' => -6.8915, 'lng' => 107.6107,
        'status' => 'aman',             // aman | waspada | kritis; selain itu dianggap riwayat kurang
        'hari' => 46,                   // estimasi habis (hari), null bila riwayat kurang
        'stok_kg' => 18420,
        'gudang' => 'Nama gudang utama',
        'diperbarui' => '2026-11-17T08:51:00+07:00',
        'komoditas' => [['nama' => 'Beras medium', 'jumlah' => 14368, 'satuan' => 'kg', 'hari' => 46]],
        'aktivitas' => [['jenis' => 'keluar', 'judul' => 'Penyaluran 1.105 kg beras', 'waktu' => '2026-11-17T08:51:00+07:00']], // jenis: masuk | keluar | rusak
    ],
]]);
```

### Data uji (hanya saat `npm run dev`)
`resources/js/beranda-uji.js` berisi data terburuk untuk menguji tata letak. Modul ini hanya dimuat oleh Vite dev
server dan tidak ikut ke build produksi. Buka beranda dengan:

| URL | Isi |
| --- | --- |
| `/?data=terburuk` | Nama kecamatan dan kabupaten terpanjang, stok 0 dan 1,28 juta, hari 13,6 dan 30 (batas status), riwayat kurang, komoditas dan mutasi kosong, kecamatan tanpa koordinat |
| `/?data=satu` | Satu kecamatan |
| `/?data=kosong` | Tidak ada kecamatan |
| `/?data=banyak` | 700 kecamatan |

## Pola halaman lain
| Halaman contoh | Isi |
| --- | --- |
| `design-system/penerimaan-demo.html` | Mockup M-18 penerimaan bantuan versi ponsel (PSKE-66), bergaya surat jalan |
| `design-system/keadaan-khusus.html` | Data kosong, riwayat kurang, validasi gagal, menunggu verifikasi, akses ditolak (PSKE-67) |

Aturan alur penerimaan ada di `docs/pbi-14-penerimaan.md`.
