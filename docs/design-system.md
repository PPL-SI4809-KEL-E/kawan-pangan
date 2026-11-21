# Design System Kawan Pangan v2

Token ada di `design-system/tokens.css`, komponen di `design-system/components.css`, dan kerangka halaman di
`design-system/layout.css`. Ketiganya dimuat lewat `resources/css/design-system.css`. Semua warna, ukuran, dan
jarak wajib memakai token, misalnya `color: var(--color-text)`, bukan kode warna langsung.

Versi 2 (PSKE-85) merapikan tiga gaya visual yang sebelumnya berbeda (beranda gelap, admin navy-kuning, panel
dengan inline style) menjadi satu gaya. Arah desain mengikuti referensi aplikasi pencarian properti berbasis peta:
panel putih melayang di atas kanvas biru muda, sidebar putih dengan menu aktif biru muda, satu warna utama biru
royal, sudut membulat lebar, kartu ajakan biru, dan peta berwarna dengan titik bercincin putih.

## Warna
| Token | Nilai | Dipakai untuk |
| --- | --- | --- |
| `--color-primary` | #1D4FAE | Satu-satunya aksen: tombol utama, tautan, menu aktif, kartu sambutan |
| `--color-primary-soft` | #E6EEFA | Latar menu aktif, kartu terpilih, ikon tile |
| `--color-accent` | = primary | Alias supaya kelas lama (`.btn-accent`) ikut biru |
| `--color-highlight` | #F2B53A | Dekorasi kecil saja, bukan untuk tombol |
| `--color-canvas` | #E7EDF5 | Latar biru muda di belakang panel |
| `--color-bg` | #F3F6FA | Isian halus: kepala tabel, hover |
| `--color-surface` | #FFFFFF | Kartu, tabel, panel, sidebar |
| `--color-border` / `--color-border-strong` | #E3E8EF / #CBD3DE | Garis tipis, garis input |
| `--color-text` | #0E1A2B | Teks utama |
| `--color-text-muted` | #5A6577 | Teks sekunder (kontras 5.9:1 di atas putih) |

Semua abu memakai satu keluarga (abu kebiruan). Bayangan lembut bernuansa biru dengan satu arah cahaya dari atas.

## Warna status
| Status | Teks | Latar | Arti |
| --- | --- | --- | --- |
| Kritis | #B42318 | #FEE4E2 | Ketahanan stok < 14 hari, laporan ditolak |
| Waspada | #B54708 | #FEF0C7 | 14–30 hari, menunggu verifikasi |
| Aman | #067647 | #DCFAE6 | > 30 hari, selesai/diterima |
| Info | #175CD3 | #D1E9FF | Diajukan, diproses, dalam pengiriman |
| Netral | #344054 | #F2F4F7 | Draf, dibatalkan |

Semua pasangan teks dan latar memenuhi kontras WCAG AA (minimal 4.5:1).

## Tipografi
Font **Geist** (dimuat dari Google Fonts di `layouts/partials/head.blade.php`) dan **Geist Mono** untuk kode wilayah
dan referensi. Angka stok memakai `font-variant-numeric: tabular-nums` (kelas `.num`) supaya rata saat dibandingkan.
Ukuran: 12 / 14 / 16 / 18 / 24 / 32 / 44 px. Ketebalan: 400, 500, 600, 700. Judul memakai tracking -0.02em.

## Spasi dan sudut
Spasi kelipatan 4px: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64 (`--space-*`).
Sudut makin luar makin lembut: 8px (badge, tombol kecil), 12px (input, tombol), 16px (kartu), 24px (panel besar dan sidebar).

## Komponen
| Kelas | Fungsi |
| --- | --- |
| `.btn` + `-primary/-secondary/-accent/-danger/-ghost`, `-sm`, `-icon`, `-block` | Tombol dengan hover, tekan, dan fokus |
| `.field`, `.input`, `.select`, `.textarea`, `.field-error`, `.check` | Form; error tampil di bawah field |
| `.card`, `.card-head`, `.card-title`, `.card-footer` | Panel putih |
| `.badge` + `-kritis/-waspada/-aman/-info/-netral` | Status |
| `.table-wrap` > `.table`, `.num`, `.actions` | Tabel yang bisa digeser di HP |
| `.alert` + `-error/-success/-warning/-info` | Pesan |
| `.seg` > `.seg-item[aria-pressed]` > `.seg-count` | Tab filter bersegmen dengan jumlah |
| `.item-card[aria-current]` | Kartu daftar yang bisa dipilih, pasangan peta |
| `.meter` + `-kritis/-waspada/-aman` (`--meter: 0–100%`) | Bar ketahanan stok |
| `.timeline` > `li.is-masuk/.is-keluar/.is-rusak` | Timeline aktivitas mutasi |
| `.tile`, `.tile.is-soon` | Tile modul di dashboard; `is-soon` untuk fitur sprint berikutnya |
| `.empty-state`, `.skeleton` | Keadaan kosong dan memuat |
| `.avatar`, `.kbd`, `.icon` | Inisial pengguna, tombol pintas, ikon garis |

Ikon garis (stroke 1.75) ada di sprite `resources/views/layouts/partials/ikon.blade.php`, dipakai dengan
`<svg class="icon"><use href="#i-map"/></svg>`.

## Layout
| Bagian | Fungsi |
| --- | --- |
| `layouts/app.blade.php` | Shell untuk semua role yang sudah masuk. `layouts.admin` dan `layouts.panel` meneruskan ke sini |
| `layouts/partials/sidebar.blade.php` | Menu per role, pencarian menu (Ctrl/Cmd + K), blok pengguna, tombol keluar |
| `.app-sidebar` | Putih. Di HP berupa laci; di desktop melayang di kiri, bisa diciutkan (pilihan disimpan per perangkat), dengan kartu ajakan biru `.sidebar-promo` |
| `.page-header`, `.page-actions` | Judul halaman dan tombol aksi |
| `.welcome-card`, `.tile-grid`, `.section-head` | Dashboard per role |
| `.grid .grid-2/3/4`, `.grid-main-aside`, `.stack`, `.cluster` | Grid dan tumpukan |
| `layouts/auth.blade.php` > `.auth-page` | Login dan registrasi: panel biru di kiri (desktop), formulir di kanan |

Sudah dicek pada lebar 375px, 768px, dan 1440px tanpa scroll ke samping.

## Pola layar peta
Dipakai beranda publik (`welcome.blade.php`, `resources/css/beranda.css`, `resources/js/beranda.js`) dan bisa dipakai
ulang untuk PSKE-69 (choropleth), PSKE-73 (detail stok wilayah), dan alur pengiriman.

- Kiri: judul, pencarian, tab filter status dengan jumlah, lalu daftar `.item-card` diurutkan dari yang paling kritis.
- Kanan: peta Leaflet penuh (tile OpenStreetMap), titik bercincin putih berwarna sesuai status, ukurannya sesuai stok, dan sorotan biru di titik terpilih.
- Kartu detail melayang di kanan atas peta: status, estimasi habis, stok per komoditas, gudang utama, timeline aktivitas.
- Di HP urutannya peta, keterangan warna, kartu detail, lalu daftar.

### Kontrak data untuk backend
Selama controller belum mengirim data, beranda memakai **data simulasi** dan menampilkan badge "Data simulasi".
Untuk menyambungkan data asli, route `/` cukup mengirim `$petaWilayah` ke view `welcome`; tampilan tidak perlu diubah.

```php
return view('welcome', ['petaWilayah' => [
    [
        'id' => '32.73.01',             // unik
        'nama' => 'Coblong',
        'kota' => 'Kota Bandung',
        'lat' => -6.8915, 'lng' => 107.6107,
        'status' => 'aman',             // aman | waspada | kritis
        'hari' => 46,                   // estimasi habis (hari)
        'stok_kg' => 18420,
        'gudang' => 'Nama gudang utama',
        'diperbarui' => '2026-11-17T08:51:00+07:00',
        'komoditas' => [['nama' => 'Beras medium', 'jumlah' => 14368, 'satuan' => 'kg', 'hari' => 46]],
        'aktivitas' => [['jenis' => 'keluar', 'judul' => 'Penyaluran 1.105 kg beras', 'waktu' => '2026-11-17T08:51:00+07:00']], // jenis: masuk | keluar | rusak
    ],
]]);
```

## Pola halaman lain
| Halaman contoh | Isi |
| --- | --- |
| `design-system/penerimaan-demo.html` | Mockup M-18 penerimaan bantuan versi ponsel (PSKE-66) |
| `design-system/keadaan-khusus.html` | Data kosong, riwayat kurang, validasi gagal, menunggu verifikasi, akses ditolak (PSKE-67) |

Aturan alur penerimaan ada di `docs/pbi-14-penerimaan.md`.

## Sisa pekerjaan v2
Halaman cakupan gudang dan detail akun masih memakai inline style (nilainya sudah token, jadi tampilannya ikut v2).
Pemindahan ke kelas komponen dilanjutkan di PSKE-92.
