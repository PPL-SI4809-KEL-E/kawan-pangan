{{-- Shell aplikasi v2 untuk semua role yang sudah masuk (PSKE-88).
     Dipakai lewat layouts.admin dan layouts.panel supaya view lama tidak perlu diubah. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    @include('layouts.partials.head')
    <title>@yield('title') · Kawan Pangan</title>
    @vite(['resources/css/design-system.css', 'resources/css/wilayah.css', 'resources/js/admin.js'])
    @stack('head')
</head>
<body>
@include('layouts.partials.ikon')
<a class="sr-only" href="#content">Lewati ke isi</a>
<div class="app" id="app">
    <header class="app-header">
        <button class="menu-toggle" id="menu-toggle" type="button" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false"><span class="menu-icon"></span></button>
        <a class="app-brand" href="{{ url('/') }}"><span class="app-brand-mark" aria-hidden="true">KP</span>Kawan Pangan</a>
        <div class="app-header-actions">
            <span class="badge badge-netral">{{ auth()->user()->role->label() }}</span>
        </div>
    </header>

    @include('layouts.partials.sidebar')
    <div class="app-overlay" id="overlay"></div>

    <main class="app-main" id="content" tabindex="-1">
        <div class="container stack">
            @if (session('sukses'))
                <div class="alert alert-success" role="status">{{ session('sukses') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <strong>Perubahan belum disimpan.</strong>
                    <ul>@foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>
