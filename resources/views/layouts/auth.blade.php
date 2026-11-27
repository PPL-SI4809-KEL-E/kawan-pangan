<!DOCTYPE html>
<html lang="id">
<head>
    @include('layouts.partials.head')
    <title>@yield('title') | Kawan Pangan</title>
    @vite('resources/css/design-system.css')
    @stack('head')
</head>
<body>
    @include('layouts.partials.ikon')
    {{-- Halaman tanpa sidebar untuk masuk dan daftar (v3: PSKE-96).
         Formulir di kiri; di desktop, kanan menampilkan potongan tampilan peta yang dikelola petugas. --}}
    <div class="auth-page">
        <main class="auth-main">
            <div class="auth-top">
                <a class="app-brand" href="{{ url('/') }}">
                    <span class="app-brand-mark" aria-hidden="true">KP</span>Kawan Pangan
                </a>
                <a class="auth-back" href="{{ url('/') }}">
                    <svg class="icon icon-sm" aria-hidden="true"><use href="#i-arrow-left"/></svg>Lihat peta
                </a>
            </div>
            <div class="auth-card @yield('card_class')">
                @yield('content')
            </div>
            <p class="auth-legal">Akun hanya untuk kepala wilayah dan petugas badan resmi yang sudah diverifikasi lewat surat tugas.</p>
        </main>

        <aside class="auth-aside" aria-labelledby="auth-aside-judul">
            <div>
                <h2 id="auth-aside-judul">Catatan gudang Anda menjadi status stok di peta.</h2>
                <p class="auth-aside-lead">Setiap mutasi yang Anda catat menghitung ulang berapa hari stok kecamatan masih cukup.</p>
            </div>

            <div class="preview" aria-hidden="true">
                <div class="preview-head">
                    <strong>Kota Bandung</strong>
                    <span>Contoh tampilan</span>
                </div>
                <div class="sebaran-bar">
                    <span class="sebaran-seg" data-status="kritis" style="--n: 2"></span>
                    <span class="sebaran-seg" data-status="waspada" style="--n: 3"></span>
                    <span class="sebaran-seg" data-status="aman" style="--n: 5"></span>
                </div>
                <ul class="wil-list">
                    <li><span class="wil-row" data-status="kritis"><span class="wil-row-main"><span class="wil-row-name">Bojongloa Kaler</span><span class="wil-row-sub">3 komoditas</span></span><span class="wil-row-figure"><span class="wil-row-days"><b>9</b>hari</span><span class="wil-row-sub">3.180 kg</span></span></span></li>
                    <li><span class="wil-row" data-status="waspada"><span class="wil-row-main"><span class="wil-row-name">Kiaracondong</span><span class="wil-row-sub">3 komoditas</span></span><span class="wil-row-figure"><span class="wil-row-days"><b>17</b>hari</span><span class="wil-row-sub">6.940 kg</span></span></span></li>
                    <li><span class="wil-row" data-status="aman"><span class="wil-row-main"><span class="wil-row-name">Sukajadi</span><span class="wil-row-sub">3 komoditas</span></span><span class="wil-row-figure"><span class="wil-row-days"><b>52</b>hari</span><span class="wil-row-sub">21.760 kg</span></span></span></li>
                </ul>
            </div>

            <ul class="auth-points">
                <li><svg class="icon" aria-hidden="true"><use href="#i-history"/></svg><span><strong>Bisa ditelusuri.</strong> Setiap perubahan saldo tercatat sebagai mutasi dengan waktu dan petugasnya.</span></li>
                <li><svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg><span><strong>Sesuai penugasan.</strong> Anda hanya melihat wilayah atau instansi yang ditugaskan kepada Anda.</span></li>
            </ul>
        </aside>
    </div>
    @stack('scripts')
</body>
</html>
