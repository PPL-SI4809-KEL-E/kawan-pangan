{{-- PSKE-81 · Beranda publik memakai design system (PSKE-11) --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kawan Pangan · Peta Ketersediaan Pangan Indonesia</title>
    <meta name="description" content="Pantau status ketersediaan stok pangan per wilayah di Indonesia: Aman, Waspada, atau Kritis.">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    @vite(['resources/css/design-system.css', 'resources/css/beranda.css', 'resources/js/beranda.js'])
</head>
<body>
    {{-- PSKE-83: header navy dari layout design system; menu teks muncul mulai tablet --}}
    <header class="app-header">
        <a class="app-brand" href="{{ url('/') }}"><span class="app-brand-mark">KP</span>Kawan Pangan</a>
        <nav class="beranda-nav" aria-label="Menu beranda">
            <a href="#peta">Peta</a>
            <a href="#status">Status wilayah</a>
            <a href="#cara-kerja">Cara kerja</a>
        </nav>
        <div class="app-header-actions">
            <a class="btn btn-accent btn-sm" href="{{ route('registrasi.create') }}">Daftar akun</a>
        </div>
    </header>

    <main>
        <section class="beranda-hero" aria-labelledby="judul-beranda">
            <div class="container">
                <h1 id="judul-beranda">Pantau ketersediaan pangan di setiap wilayah Indonesia</h1>
                <p class="beranda-lead">
                    Kawan Pangan membantu pemerintah daerah dan masyarakat melihat apakah stok pangan
                    di suatu wilayah masih aman, perlu diwaspadai, atau sudah kritis.
                </p>
                <div class="page-actions">
                    <a class="btn btn-accent" href="#peta">Lihat peta</a>
                    <a class="btn btn-secondary" href="{{ route('registrasi.create') }}">Daftar sebagai petugas</a>
                </div>
            </div>
        </section>

        <div class="container beranda-isi stack">
            <section class="card" id="peta" aria-labelledby="judul-peta">
                <div class="page-header beranda-section-header">
                    <h2 id="judul-peta" class="card-title">Peta ketersediaan pangan</h2>
                    <span class="badge badge-netral">Data sedang disiapkan</span>
                </div>
                <div id="peta-beranda" class="beranda-peta" role="region" aria-label="Peta Indonesia"></div>
                <p class="field-help beranda-catatan">
                    Warna wilayah akan mengikuti status stok setelah data gudang dari petugas tersedia.
                </p>
            </section>

            {{-- Ambang status dari aturan stok: dibandingkan sebelum pembulatan --}}
            <section id="status" aria-labelledby="judul-status">
                <h2 id="judul-status">Arti status wilayah</h2>
                <p class="text-muted">Status dihitung dari berapa hari stok tersedia cukup untuk kebutuhan harian wilayah.</p>
                <div class="grid grid-3">
                    <article class="card">
                        <span class="badge badge-aman">Aman</span>
                        <p class="beranda-angka">Lebih dari 30 hari</p>
                        <p class="text-muted">Stok tersedia mencukupi kebutuhan wilayah.</p>
                    </article>
                    <article class="card">
                        <span class="badge badge-waspada">Waspada</span>
                        <p class="beranda-angka">14 sampai 30 hari</p>
                        <p class="text-muted">Stok perlu dipantau dan disiapkan tambahannya.</p>
                    </article>
                    <article class="card">
                        <span class="badge badge-kritis">Kritis</span>
                        <p class="beranda-angka">Kurang dari 14 hari</p>
                        <p class="text-muted">Wilayah membutuhkan penyaluran bantuan segera.</p>
                    </article>
                </div>
            </section>

            <section id="cara-kerja" aria-labelledby="judul-cara-kerja">
                <h2 id="judul-cara-kerja">Cara kerja</h2>
                <ol class="grid grid-3 beranda-langkah">
                    <li class="card">
                        <h3 class="card-title">Petugas mencatat stok</h3>
                        <p class="text-muted">Badan Resmi mencatat stok masuk dan keluar di setiap gudang.</p>
                    </li>
                    <li class="card">
                        <h3 class="card-title">Sistem menghitung ketahanan</h3>
                        <p class="text-muted">Stok tersedia dibagi pemakaian harian 30 hari terakhir.</p>
                    </li>
                    <li class="card">
                        <h3 class="card-title">Status tampil di peta</h3>
                        <p class="text-muted">Kepala wilayah dan masyarakat melihat status Aman, Waspada, atau Kritis.</p>
                    </li>
                </ol>
            </section>
        </div>
    </main>

    <footer class="beranda-footer">
        <div class="container">
            &copy; {{ date('Y') }} Kawan Pangan · Proyek Perangkat Lunak SI4809 Kelompok E, Telkom University
        </div>
    </footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</body>
</html>
