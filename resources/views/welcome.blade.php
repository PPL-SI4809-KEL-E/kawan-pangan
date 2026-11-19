{{-- Beranda publik v2: peta ketersediaan pangan per kecamatan (PSKE-90, PSKE-91).
     Data diambil dari $petaWilayah bila controller sudah mengirimnya; tanpa itu dipakai data simulasi di beranda.js.
     Kontrak data ada di docs/design-system.md bagian "Pola layar peta". --}}
<!DOCTYPE html>
<html lang="id">
<head>
    @include('layouts.partials.head')
    <title>Kawan Pangan · Peta ketersediaan pangan</title>
    <meta name="description" content="Peta status ketersediaan pangan per kecamatan: Aman, Waspada, dan Kritis, beserta stok dan mutasi terakhir.">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    @vite(['resources/css/design-system.css', 'resources/css/beranda.css'])
</head>
<body class="beranda">
    @include('layouts.partials.ikon')
    <a class="sr-only" href="#peta">Lewati ke peta</a>

    <header class="pub-bar">
        <a class="app-brand" href="{{ url('/') }}">
            <span class="app-brand-mark" aria-hidden="true">KP</span>Kawan Pangan
        </a>
        <nav class="pub-nav" aria-label="Navigasi beranda">
            <a href="#peta">Peta</a>
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
                <a href="{{ route('registrasi.create') }}" class="btn btn-accent btn-sm">Daftar</a>
            @endauth
        </div>
    </header>

    <main>
        <section class="peta" id="peta" aria-labelledby="peta-judul">
            <aside class="peta-panel">
                <div class="peta-head">
                    <p class="eyebrow">Peta ketersediaan pangan</p>
                    <h1 id="peta-judul">Status stok pangan per kecamatan</h1>
                    <p class="text-muted">Pilih kecamatan untuk melihat stok, estimasi habis, dan mutasi terakhir.</p>
                    <p class="peta-sumber" id="peta-sumber">
                        <span class="badge badge-info">Data simulasi</span>
                        <span>Angka contoh sampai stok gudang tersambung.</span>
                    </p>
                </div>

                <div class="peta-tools">
                    <label class="peta-cari">
                        <span class="sr-only">Cari kecamatan</span>
                        <svg class="icon icon-sm"><use href="#i-search"/></svg>
                        <input class="input" type="search" id="peta-cari" placeholder="Cari kecamatan" autocomplete="off">
                    </label>
                    <div class="seg" role="group" aria-label="Saring status">
                        <button type="button" class="seg-item" data-filter="semua" aria-pressed="true">Semua <span class="seg-count" data-count="semua">0</span></button>
                        <button type="button" class="seg-item" data-filter="kritis" aria-pressed="false">Kritis <span class="seg-count" data-count="kritis">0</span></button>
                        <button type="button" class="seg-item" data-filter="waspada" aria-pressed="false">Waspada <span class="seg-count" data-count="waspada">0</span></button>
                        <button type="button" class="seg-item" data-filter="aman" aria-pressed="false">Aman <span class="seg-count" data-count="aman">0</span></button>
                    </div>
                </div>

                <ul class="peta-list" id="peta-list" aria-label="Daftar kecamatan">
                    <li><div class="skeleton peta-skeleton"></div></li>
                    <li><div class="skeleton peta-skeleton"></div></li>
                    <li><div class="skeleton peta-skeleton"></div></li>
                </ul>
                <p class="peta-kosong" id="peta-kosong" hidden>Tidak ada kecamatan yang cocok dengan pencarian.</p>
            </aside>

            <div class="peta-map">
                <div id="map" class="peta-canvas" role="region" aria-label="Peta kecamatan"></div>

                <article class="peta-detail" id="peta-detail" aria-live="polite" hidden>
                    <div class="peta-detail-head">
                        <div>
                            <h2 class="peta-detail-title" data-detail="nama"></h2>
                            <p class="peta-detail-sub" data-detail="lokasi"></p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-icon btn-sm" id="peta-tutup" aria-label="Tutup detail">
                            <svg class="icon icon-sm"><use href="#i-x"/></svg>
                        </button>
                    </div>
                    <div class="peta-detail-status">
                        <span data-detail="badge"></span>
                        <span class="text-muted" data-detail="diperbarui"></span>
                    </div>
                    <dl class="peta-angka">
                        <div><dt>Estimasi habis</dt><dd data-detail="hari"></dd></div>
                        <div><dt>Total stok</dt><dd data-detail="stok"></dd></div>
                    </dl>
                    <h3 class="peta-detail-label">Stok per komoditas</h3>
                    <ul class="peta-komoditas" data-detail="komoditas"></ul>
                    <h3 class="peta-detail-label">Gudang utama</h3>
                    <p class="peta-gudang" data-detail="gudang"></p>
                    <h3 class="peta-detail-label">Aktivitas terakhir</h3>
                    <ol class="timeline" data-detail="aktivitas"></ol>
                </article>

                <div class="peta-legend" aria-label="Keterangan warna">
                    <span><i class="dot dot-aman"></i>Aman &gt; 30 hari</span>
                    <span><i class="dot dot-waspada"></i>Waspada 14–30</span>
                    <span><i class="dot dot-kritis"></i>Kritis &lt; 14</span>
                </div>
            </div>
        </section>

        <section class="alur" id="cara-kerja" aria-labelledby="alur-judul">
            <div class="alur-intro">
                <p class="eyebrow">Cara kerja</p>
                <h2 id="alur-judul">Dari stok gudang sampai bantuan diterima warga</h2>
                <p class="text-muted">Setiap peran mengisi bagiannya, dan peta di atas dihitung dari catatan mereka.</p>
            </div>
            <ol class="alur-list">
                <li>
                    <span class="alur-no">01</span>
                    <div>
                        <h3>Petugas mendaftar dengan surat tugas</h3>
                        <p>Kepala wilayah dan staf badan resmi diverifikasi dulu sebelum bisa masuk, lalu ditugaskan ke wilayah atau instansinya.</p>
                    </div>
                </li>
                <li>
                    <span class="alur-no">02</span>
                    <div>
                        <h3>Gudang mencatat setiap mutasi stok</h3>
                        <p>Barang masuk, keluar, retur, dan rusak tercatat sampai milidetik, jadi saldo selalu bisa ditelusuri.</p>
                    </div>
                </li>
                <li>
                    <span class="alur-no">03</span>
                    <div>
                        <h3>Status dihitung dari ketahanan stok</h3>
                        <p>Stok dibandingkan dengan kebutuhan penduduk per hari, lalu diberi status Aman, Waspada, atau Kritis.</p>
                    </div>
                </li>
                <li>
                    <span class="alur-no">04</span>
                    <div>
                        <h3>Bantuan dikirim dan dikonfirmasi</h3>
                        <p>Laporan kebutuhan diverifikasi, pengiriman dibuat, dan wilayah penerima mencatat jumlah yang benar-benar tiba.</p>
                    </div>
                </li>
            </ol>
        </section>
    </main>

    <footer class="pub-foot">
        <span>© 2026 Kawan Pangan · Proyek PPL SI4809 Kelompok E, Telkom University</span>
        <span class="cluster">
            @guest
                <a href="{{ route('login') }}">Masuk</a>
                <a href="{{ route('registrasi.create') }}">Daftar akun</a>
            @endguest
        </span>
    </footer>

    <script type="application/json" id="peta-data">@json($petaWilayah ?? null)</script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @vite('resources/js/beranda.js')
</body>
</html>
