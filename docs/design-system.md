# Design System Kawan Pangan

Token disimpan di `design-system/tokens.css`, komponen di `design-system/components.css`,
dan contoh semua komponen bisa dilihat di `design-system/styleguide.html`. Semua komponen wajib memakai token,
misalnya `color: var(--color-text)`, bukan kode warna langsung.

## Warna
| Token | Nilai | Dipakai untuk |
| --- | --- | --- |
| `--color-primary` | #1E3A5F | Header, tombol utama |
| `--color-accent` | #F5B700 | Aksen, highlight |
| `--color-bg` | #F8FAFC | Latar halaman |
| `--color-surface` | #FFFFFF | Kartu, tabel, modal |
| `--color-text` | #111827 | Teks utama |
| `--color-text-muted` | #4B5563 | Teks sekunder |

## Warna Status
| Status | Teks | Latar | Arti |
| --- | --- | --- | --- |
| Kritis | #B42318 | #FEE4E2 | Ketahanan stok < 14 hari, laporan ditolak |
| Waspada | #B54708 | #FEF0C7 | 14–30 hari, menunggu verifikasi |
| Aman | #067647 | #DCFAE6 | > 30 hari, selesai/diterima |
| Info | #175CD3 | #D1E9FF | Diajukan, diproses, dalam pengiriman |
| Netral | #344054 | #F2F4F7 | Draf, dibatalkan |

Semua pasangan teks dan latar sudah dicek dan memenuhi kontras WCAG AA (minimal 4.5:1).

## Tipografi
Font: Inter (cadangan: font sistem). Ukuran: 12 / 14 / 16 / 20 / 24 / 32 px.
Ketebalan: 400 (isi), 600 (label, judul kecil), 700 (judul).

## Spasi
Kelipatan 4px: 4, 8, 12, 16, 24, 32, 48. Gunakan `--space-*`.

## Breakpoint
Mobile mulai 360px, tablet mulai 768px, desktop mulai 1024px.

## Layout
Kerangka halaman ada di `design-system/layout.css`, contohnya di `design-system/layout-demo.html`.

| Kelas | Fungsi |
| --- | --- |
| `.app` | Pembungkus seluruh halaman yang memakai sidebar |
| `.app-header` | Bar atas berisi tombol menu, logo, dan akun |
| `.app-sidebar` | Menu navigasi. Di HP dan tablet berupa laci yang dibuka lewat tombol menu; di desktop selalu tampil |
| `.app-main` > `.container` | Area isi halaman |
| `.page-header` | Judul halaman dan tombol aksi (bertumpuk di HP, sejajar mulai tablet) |
| `.grid .grid-2/3/4` | Grid kartu: 1 kolom di HP, 2 di tablet, 2/3/4 di desktop |
| `.grid .grid-main-aside` | Isi utama dan panel samping (2:1) di desktop |
| `.auth-page` > `.auth-card` | Halaman tanpa sidebar, misalnya login dan registrasi |

Sudah dicek pada lebar 360px, 768px, dan 1280px tanpa scroll ke samping. Tabel lebar bisa digeser di dalam `.table-wrap`.
