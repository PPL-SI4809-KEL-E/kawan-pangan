<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Kawan Pangan</title>
    @vite(['resources/css/design-system.css', 'resources/css/wilayah.css', 'resources/js/admin.js'])
</head>
<body>
<div class="app" id="app">
    <header class="app-header">
        <button class="menu-toggle" id="menu-toggle" type="button" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false"><span class="menu-icon"></span></button>
        <a class="app-brand" href="{{ url('/') }}"><span class="app-brand-mark">KP</span>Kawan Pangan</a>
        <div class="app-header-actions">
            <span class="app-user">{{ auth()->user()->name }}</span>
            <span class="badge badge-info">Superadmin</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline; margin-left: var(--space-2);">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">Keluar</button>
            </form>
        </div>
    </header>
    <nav class="app-sidebar" id="sidebar" aria-label="Menu utama">
        <ul class="nav">
            <li><a class="nav-link" href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard Admin</a></li>
            <li><a class="nav-link" href="{{ route('admin.akun.index') }}" @if(request()->routeIs('admin.akun.*')) aria-current="page" @endif>Verifikasi Akun</a></li>
        </ul>
        <p class="nav-label">Data Master</p>
        <ul class="nav">
            @foreach (['wilayah' => 'Wilayah', 'komoditas' => 'Komoditas', 'instansi' => 'Instansi', 'gudang' => 'Gudang'] as $menu => $label)
                <li><a class="nav-link" href="{{ route('admin.'.$menu.'.index') }}" @if(request()->routeIs('admin.'.$menu.'.*')) aria-current="page" @endif>{{ $label }}</a></li>
            @endforeach
            <li><a class="nav-link" href="{{ route('admin.ambang-status.edit') }}" @if(request()->routeIs('admin.ambang-status.*')) aria-current="page" @endif>Ambang Status</a></li>
        </ul>
        <a class="nav-link" href="{{ url('/') }}">Beranda</a>
    </nav>
    <div class="app-overlay" id="overlay"></div>
    <main class="app-main" id="content">
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
</body>
</html>
