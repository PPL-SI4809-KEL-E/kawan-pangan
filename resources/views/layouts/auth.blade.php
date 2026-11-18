<!DOCTYPE html>
<html lang="id">
<head>
    @include('layouts.partials.head')
    <title>@yield('title') · Kawan Pangan</title>
    @vite('resources/css/design-system.css')
    @stack('head')
</head>
<body>
    @include('layouts.partials.ikon')
    {{-- Halaman tanpa sidebar untuk login dan registrasi: panel navy di kiri (desktop), formulir di kanan (PSKE-88) --}}
    <div class="auth-page">
        <aside class="auth-aside" aria-label="Tentang Kawan Pangan">
            <a class="app-brand" href="{{ url('/') }}">
                <span class="app-brand-mark" aria-hidden="true">KP</span>Kawan Pangan
            </a>
            <div>
                <h2>Stok pangan dan penyaluran bantuan dalam satu peta.</h2>
                <p>Ruang kerja untuk kepala wilayah dan petugas badan resmi yang sudah diverifikasi lewat surat tugas.</p>
            </div>
            <ul class="auth-points">
                <li><svg class="icon"><use href="#i-check"/></svg><span>Status Aman, Waspada, dan Kritis dihitung dari ketahanan stok per wilayah.</span></li>
                <li><svg class="icon"><use href="#i-check"/></svg><span>Setiap perubahan saldo tercatat sebagai mutasi yang bisa ditelusuri.</span></li>
                <li><svg class="icon"><use href="#i-check"/></svg><span>Akses dibatasi sesuai wilayah atau instansi penugasan.</span></li>
            </ul>
        </aside>
        <main class="auth-main">
            <div class="auth-card @yield('card_class')">
                <a class="app-brand auth-brand" href="{{ url('/') }}">
                    <span class="app-brand-mark" aria-hidden="true">KP</span>Kawan Pangan
                </a>
                @yield('content')
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html>
