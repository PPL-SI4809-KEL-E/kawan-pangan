# Kawan Pangan - SDG 2 Website

[![CI/CD Pipeline & Build Verification](https://github.com/PPL-SI4809-KEL-E/kawan-pangan/actions/workflows/deploy.yml/badge.svg)](https://github.com/PPL-SI4809-KEL-E/kawan-pangan/actions/workflows/deploy.yml)
[![Framework](https://img.shields.io/badge/Framework-Laravel%2012-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)

Repository tugas besar **Proyek Perangkat Lunak** dari **Kelompok E SI4809**.

Kawan Pangan adalah website untuk memetakan ketersediaan stok pangan di Indonesia. Website ini ditujukan untuk instansi pemerintah dan merupakan bagian dari tujuan **SDG 2: Tanpa Kelaparan**. Lewat website ini, petugas bisa mencatat stok gudang, melihat wilayah mana yang stoknya aman, waspada, atau kritis, dan memantau pengiriman bantuan pangan.

---

## 👥 Anggota Tim

| Nama | Peran |
| --- | --- |
| **Pak Gelar Aditya** | Product Owner (dosen) |
| **Hadid Hamar** | Scrum Master & Developer |
| **Hafizh Rafi Maulana Suyufi** | Developer |
| **Ahmad Rizky Ivanzya** | Developer |
| **Naufan Rae Rasyad Torick** | Developer |
| **Calista Aurelia Putri** | Developer |
| **Muhammad Syamsul Arifin** | Developer |
| **Yudha Pamungkas** | Developer |

---

## 🛠️ Tools

- **Jira**: product backlog, sprint, dan pembagian tugas (project `PSKE` / `PPL-SI4809-KEL E`)
- **GitHub**: tempat menyimpan kode, satu branch untuk setiap tugas

---

## 🌿 Aturan Main Git

Supaya kode tidak bentrok, kita sepakat:

1. **Jangan push langsung ke `main`.** `main` hanya berisi kode yang sudah jadi dan sudah di-review.
2. **Satu tiket Jira = satu branch.** Nama branch-nya pakai kode tiket:
   ```bash
   PSKE-<nomor>-<deskripsi-singkat>
   ```
   *Contoh branch:*
   - `PSKE-11-design-system`
   - `PSKE-7-setup-repositori`
   - `PSKE-15-skema-basis-data`
3. **Pesan commit selalu diawali kode tiket**, supaya otomatis tercatat di Jira:
   ```bash
   PSKE-7 setup repositori, struktur proyek Laravel, dan pipeline deploy demo
   ```
4. Kalau sudah selesai, buat **Pull Request** ke `main`, lalu minta minimal **1 teman** untuk review sebelum dimerge.
5. Sebelum mulai ngoding, selalu update dulu `main`-nya:
   ```bash
   git checkout main
   git pull origin main
   ```

---

## 🚀 Cara Menjalankan Proyek Secara Lokal

### 1. Prasyarat Sistem
* **PHP** versi 8.2 atau lebih baru (ekstensi `sqlite3`, `mbstring`, `openssl`, `pdo` aktif)
* **Composer** versi 2.x
* **Node.js** (v18 / v20 / v24) & npm
* **Git**

### 2. Kloning Repositori
```bash
git clone https://github.com/PPL-SI4809-KEL-E/kawan-pangan.git
cd kawan-pangan
```

### 3. Instalasi Dependensi
```bash
composer install
npm install
```

### 4. Konfigurasi Environment File
Salin file konfigurasi `.env.example` menjadi `.env`:

```bash
# Linux / macOS / Git Bash:
cp .env.example .env

# Windows PowerShell:
Copy-Item .env.example .env

# Atau Windows CMD:
copy .env.example .env
```

### 5. Generate Application Key
```bash
php artisan key:generate
```

### 6. Setup Basis Data Lokal (SQLite)
Secara default, proyek telah disiapkan menggunakan SQLite agar pengembang dapat langsung menjalankan sistem tanpa konfigurasi database server tambahan:

```bash
# Linux / macOS / Git Bash:
touch database/database.sqlite
chmod -R 775 storage bootstrap/cache

# Windows PowerShell:
if (!(Test-Path 'database/database.sqlite')) { New-Item -ItemType File -Path 'database/database.sqlite' }

# Jalankan migrasi tabel:
php artisan migrate
```

*(Catatan: Untuk deployment server produksi memakai MySQL, sesuaikan konfigurasi `DB_*` pada file `.env`)*.

### 7. Menjalankan Server Aplikasi
```bash
php artisan serve
```

Aplikasi dapat langsung diakses melalui browser pada:
* **Halaman Frontend / Landing Page:** [http://localhost:8000](http://localhost:8000)
* **Endpoint Health Check API:** [http://localhost:8000/api/health](http://localhost:8000/api/health)
* **Design System Styleguide:** [design-system/styleguide.html](design-system/styleguide.html)

---

## 📅 Progress Sprint

| Sprint | Periode | Fokus | Status |
| --- | --- | --- | --- |
| **Sprint 1** | Minggu ke-1 (26 Okt - 2 Nov) | Fondasi proyek, akun, dan design system | **Sedang Berjalan** |
| **Sprint 2** | Minggu ke-2 | Data master, gudang, dan stok | Direncanakan |
| **Sprint 3** | Minggu ke-3 | Peta, pelaporan, dan pengiriman | Direncanakan |
| **Sprint 4** | Minggu ke-4 | Dashboard, notifikasi, testing, dan UAT | Direncanakan |

---
&copy; 2026 Kawan Pangan • S1 Sistem Informasi Telkom University (SI-48-09 Kelompok E)
