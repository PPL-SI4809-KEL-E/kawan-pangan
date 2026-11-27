{{-- Beranda publik v3: atlas stok pangan per kecamatan (PSKE-96, lanjutan PSKE-90/91).
     Data diambil dari $petaWilayah bila controller sudah mengirimnya; tanpa itu dipakai data simulasi di beranda.js.
     Kontrak data ada di docs/design-system.md bagian "Pola layar peta". --}}
@php($ambang ??= ['kritis' => 14, 'aman' => 30])
<!DOCTYPE html>
<html lang="id">
<head>
    @include('layouts.partials.head')
    <title>Peta stok pangan per kecamatan | Kawan Pangan</title>
    <meta name="description" content="Peta status ketersediaan pangan per kecamatan: Aman, Waspada, dan Kritis, beserta stok dan mutasi terakhir.">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    @vite(['resources/css/design-system.css', 'resources/css/beranda.css'])
</head>
<body class="beranda">
    @include('layouts.partials.ikon')
    <a class="skip-link" href="#peta-list">Lewati ke daftar kecamatan</a>

    <header class="pub-bar">
        <a class="app-brand" href="{{ url('/') }}">
            <span class="app-brand-mark" aria-hidden="true">KP</span>Kawan Pangan
        </a>
        <nav class="pub-nav" aria-label="Navigasi beranda">
            <a href="#peta" aria-current="page">Peta</a>
            <a href="#cara-kerja">Cara kerja</a>
        </nav>
        <div class="pub-actions">
            @auth
                @if (auth()->user()->isSuperadmin())
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-primary btn-sm">Panel Admin</a>
                @elseif (auth()->user()->isKepalaWilayah())
                    <a href="{{ route('panel.wilayah') }}" class="btn btn-primary btn-sm">Panel Wilayah</a>
                @elseif (auth()->user()->isBadanResmi())
                    <a href="{{ route('panel.instansi') }}" class="btn btn-primary btn-sm">Panel Instansi</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="pub-keluar">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">Keluar</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Masuk</a>
                <a href="{{ route('registrasi.create') }}" class="btn btn-primary btn-sm"><span>Daftar<span class="pub-lebar"> petugas</span></span></a>
            @endauth
        </div>
    </header>

    <main>
        <section class="atlas" id="peta" aria-labelledby="peta-judul">
            <div class="atlas-panel">
                <div class="atlas-head">
                    <nav class="atlas-breadcrumb" id="peta-breadcrumb" aria-label="Hierarki wilayah peta" hidden></nav>
                    <h1 id="peta-judul">Stok pangan <span id="peta-level-label">{{ !empty($petaAwal) ? 'per provinsi' : 'per kecamatan' }}</span></h1>
                    <p class="atlas-lead" id="peta-cakupan">Berapa hari stok gudang cukup untuk penyaluran warga.</p>
                    <p class="atlas-sumber" id="peta-sumber"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-alert"/></svg>Angka contoh. Data gudang belum tersambung.</p>
                </div>

                <div class="sebaran" role="group" aria-labelledby="sebaran-judul">
                    <p class="sebaran-judul" id="sebaran-judul">Saring menurut status</p>
                    <div class="sebaran-bar" id="sebaran-bar" aria-hidden="true">
                        <span class="sebaran-seg" data-status="kritis" data-bar="kritis"></span>
                        <span class="sebaran-seg" data-status="waspada" data-bar="waspada"></span>
                        <span class="sebaran-seg" data-status="aman" data-bar="aman"></span>
                        <span class="sebaran-seg" data-status="netral" data-bar="netral"></span>
                    </div>
                    <div class="sebaran-opsi">
                        <button type="button" class="sebaran-opt" data-filter="semua" aria-pressed="true">
                            <span class="swatch" aria-hidden="true" style="--swatch: var(--color-text)"></span>
                            <span class="sebaran-opt-label">Semua</span>
                            <span class="sebaran-opt-count" data-count="semua">0</span>
                            <span class="sebaran-opt-hint">Semua status</span>
                        </button>
                        <button type="button" class="sebaran-opt" data-filter="kritis" aria-pressed="false">
                            <span class="swatch swatch-kritis" aria-hidden="true"></span>
                            <span class="sebaran-opt-label">Kritis</span>
                            <span class="sebaran-opt-count" data-count="kritis">0</span>
                            <span class="sebaran-opt-hint">Kurang dari {{ $ambang['kritis'] }} hari</span>
                        </button>
                        <button type="button" class="sebaran-opt" data-filter="waspada" aria-pressed="false">
                            <span class="swatch swatch-waspada" aria-hidden="true"></span>
                            <span class="sebaran-opt-label">Waspada</span>
                            <span class="sebaran-opt-count" data-count="waspada">0</span>
                            <span class="sebaran-opt-hint">{{ $ambang['kritis'] }} sampai {{ $ambang['aman'] }} hari</span>
                        </button>
                        <button type="button" class="sebaran-opt" data-filter="aman" aria-pressed="false">
                            <span class="swatch swatch-aman" aria-hidden="true"></span>
                            <span class="sebaran-opt-label">Aman</span>
                            <span class="sebaran-opt-count" data-count="aman">0</span>
                            <span class="sebaran-opt-hint">Lebih dari {{ $ambang['aman'] }} hari</span>
                        </button>
                    </div>
                    <p class="sebaran-catatan" id="sebaran-netral" hidden></p>
                </div>

                <div class="atlas-komoditas-wrap" id="peta-komoditas-wrap" hidden>
                    <label class="sr-only" for="peta-komoditas">Filter komoditas</label>
                    <select class="select atlas-komoditas" id="peta-komoditas" aria-label="Filter komoditas">
                        <option value="">Semua komoditas</option>
                    </select>
                </div>

                <label class="atlas-cari">
                    <span class="sr-only" id="peta-cari-label">Cari wilayah</span>
                    <svg class="icon icon-sm" aria-hidden="true"><use href="#i-search"/></svg>
                    <input class="input" type="search" id="peta-cari" placeholder="Cari wilayah" autocomplete="off">
                </label>

                <ul class="wil-list atlas-list" id="peta-list" aria-label="Daftar kecamatan, urut dari stok paling cepat habis" tabindex="-1">
                    <li><div class="skeleton atlas-skeleton"></div></li>
                    <li><div class="skeleton atlas-skeleton"></div></li>
                    <li><div class="skeleton atlas-skeleton"></div></li>
                </ul>
                <div class="state atlas-kosong" id="peta-kosong" hidden>
                    <span class="state-icon" aria-hidden="true"><svg class="icon"><use href="#i-search"/></svg></span>
                    <div class="state-content">
                        <p class="state-title" id="peta-kosong-judul">Tidak ada kecamatan yang cocok</p>
                        <p class="state-text" id="peta-kosong-teks">Coba nama lain, atau pilih status Semua.</p>
                    </div>
                </div>
            </div>

            <div class="atlas-map">
                <div id="map" class="atlas-canvas" role="region" aria-label="Peta kecamatan"></div>

                <article class="atlas-detail" id="peta-detail" aria-labelledby="detail-judul" hidden>
                    <header class="detail-head">
                        <div class="detail-title-wrap">
                            <h2 class="detail-title" id="detail-judul" data-detail="nama"></h2>
                            <p class="detail-sub" data-detail="lokasi"></p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-icon detail-close" id="peta-tutup" aria-label="Tutup detail">
                            <svg class="icon" aria-hidden="true"><use href="#i-x"/></svg>
                        </button>
                    </header>

                    <div class="detail-figure" data-detail="figure">
                        <span data-detail="badge"></span>
                        <p class="detail-figure-label" data-detail="hari-label">Perkiraan stok habis dalam</p>
                        <p class="detail-figure-value" data-detail="hari"></p>
                        <p class="detail-figure-meta"><span data-detail="stok"></span><span data-detail="diperbarui"></span></p>
                    </div>

                    <div class="detail-drill" id="peta-drill-wrap" hidden>
                        <button type="button" class="btn btn-primary btn-sm btn-block" id="peta-drill-btn">
                            <span id="peta-drill-label">Buka wilayah turunan</span>
                        </button>
                    </div>

                    <section class="detail-section" aria-labelledby="detail-komoditas">
                        <h3 id="detail-komoditas">Per komoditas</h3>
                        <table class="detail-table">
                            <thead class="sr-only"><tr><th scope="col">Komoditas</th><th scope="col">Stok</th><th scope="col">Cukup untuk</th></tr></thead>
                            <tbody data-detail="komoditas"></tbody>
                        </table>
                    </section>

                    <section class="detail-section" aria-labelledby="detail-gudang">
                        <h3 id="detail-gudang">Gudang</h3>
                        <p class="detail-gudang" data-detail="gudang"></p>
                    </section>

                    <section class="detail-section" aria-labelledby="detail-mutasi">
                        <h3 id="detail-mutasi">Mutasi terakhir</h3>
                        <ol class="ledger" data-detail="aktivitas"></ol>
                    </section>
                </article>
            </div>
        </section>

        <section class="alur" id="cara-kerja" aria-labelledby="alur-judul">
            <div class="alur-intro">
                <h2 id="alur-judul">Cara angka di peta dihitung</h2>
                <p>Peta ini tidak diisi tangan. Setiap angka berasal dari catatan petugas di empat tahap berikut.</p>
            </div>
            <ol class="alur-list">
                <li>
                    <h3>Petugas mendaftar dengan surat tugas</h3>
                    <p>Kepala wilayah dan staf badan resmi diverifikasi dulu, lalu ditugaskan ke wilayah atau instansinya.</p>
                </li>
                <li>
                    <h3>Gudang mencatat setiap mutasi</h3>
                    <p>Barang masuk, keluar, retur, dan rusak tercatat sampai milidetik, jadi saldo selalu bisa ditelusuri.</p>
                </li>
                <li>
                    <h3>Status dihitung dari ketahanan stok</h3>
                    <p>Stok tersedia dibagi rata-rata penyaluran harian 30 hari terakhir, lalu diberi status Aman, Waspada, atau Kritis.</p>
                </li>
                <li>
                    <h3>Penerima mengonfirmasi bantuan</h3>
                    <p>Wilayah penerima mencatat jumlah yang benar-benar tiba, beserta selisih dan foto surat jalan.</p>
                </li>
            </ol>
        </section>
    </main>

    <footer class="pub-foot">
        <p>Kawan Pangan, proyek PPL SI4809 Kelompok E, Telkom University. 2026.</p>
        <p class="pub-foot-links">
            @guest
                <a href="{{ route('login') }}">Masuk</a>
                <a href="{{ route('registrasi.create') }}">Daftar petugas</a>
            @endguest
        </p>
    </footer>

    <script type="application/json" id="peta-awal">@json($petaAwal ?? null)</script>
    <script type="application/json" id="peta-data">@json($petaWilayah ?? null)</script>
    <script type="application/json" id="peta-ambang">@json($ambang)</script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @vite('resources/js/beranda.js')
</body>
</html>
