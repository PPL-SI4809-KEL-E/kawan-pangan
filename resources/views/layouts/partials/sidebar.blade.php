{{-- Sidebar navy bersama untuk Superadmin, Kepala Wilayah, dan Badan Resmi (PSKE-88).
     Menu mengikuti role; route dan nama route tidak diubah. --}}
@php
    $pengguna = auth()->user();
    $inisial = collect(preg_split('/\s+/', trim($pengguna->name)))->filter()->take(2)
        ->map(fn ($kata) => mb_strtoupper(mb_substr($kata, 0, 1)))->implode('');

    if ($pengguna->isSuperadmin()) {
        $grupMenu = [
            'Utama' => [
                ['label' => 'Dashboard', 'ikon' => 'i-home', 'url' => route('admin.dashboard'), 'aktif' => request()->routeIs('admin.dashboard')],
                ['label' => 'Verifikasi akun', 'ikon' => 'i-user-check', 'url' => route('admin.akun.index'), 'aktif' => request()->routeIs('admin.akun.*')],
            ],
            'Data master' => [
                ['label' => 'Wilayah', 'ikon' => 'i-map', 'url' => route('admin.wilayah.index'), 'aktif' => request()->routeIs('admin.wilayah.*')],
                ['label' => 'Komoditas', 'ikon' => 'i-package', 'url' => route('admin.komoditas.index'), 'aktif' => request()->routeIs('admin.komoditas.*')],
                ['label' => 'Instansi', 'ikon' => 'i-building', 'url' => route('admin.instansi.index'), 'aktif' => request()->routeIs('admin.instansi.*')],
                ['label' => 'Gudang', 'ikon' => 'i-warehouse', 'url' => route('admin.gudang.index'), 'aktif' => request()->routeIs('admin.gudang.*')],
                ['label' => 'Ambang status', 'ikon' => 'i-sliders', 'url' => route('admin.ambang-status.edit'), 'aktif' => request()->routeIs('admin.ambang-status.*')],
            ],
        ];
    } elseif ($pengguna->isKepalaWilayah()) {
        $grupMenu = [
            'Utama' => [
                ['label' => 'Dashboard', 'ikon' => 'i-home', 'url' => route('panel.wilayah'), 'aktif' => request()->routeIs('panel.wilayah')],
            ],
            'Segera hadir' => [
                ['label' => 'Laporan kebutuhan', 'ikon' => 'i-file', 'tag' => 'Sprint 3'],
                ['label' => 'Penerimaan bantuan', 'ikon' => 'i-inbox', 'tag' => 'Sprint 3'],
            ],
        ];
    } else {
        $grupMenu = [
            'Utama' => [
                ['label' => 'Dashboard', 'ikon' => 'i-home', 'url' => route('panel.instansi'), 'aktif' => request()->routeIs('panel.instansi')],
                ['label' => 'Cakupan gudang', 'ikon' => 'i-warehouse', 'url' => route('panel.instansi').'#gudang', 'aktif' => request()->routeIs('panel.instansi.gudang.*')],
                ['label' => 'Daftar stok', 'ikon' => 'i-chart', 'url' => route('panel.instansi.stok.index'), 'aktif' => request()->routeIs('panel.instansi.stok.*')],
            ],
            'Segera hadir' => [
                ['label' => 'Penyaluran', 'ikon' => 'i-truck', 'tag' => 'Sprint 3'],
            ],
        ];
    }

@endphp
<nav class="app-sidebar" id="sidebar" aria-label="Menu utama">
    <div class="sidebar-top">
        <a class="app-brand" href="{{ url('/') }}">
            <span class="app-brand-mark" aria-hidden="true">KP</span>
            <span class="app-brand-text">Kawan Pangan <small>{{ $pengguna->role->label() }}</small></span>
        </a>
        <button class="sidebar-collapse" id="sidebar-collapse" type="button" aria-label="Ciutkan menu" aria-pressed="false">
            <svg class="icon"><use href="#i-sidebar"/></svg>
        </button>
    </div>

    <label class="sidebar-search">
        <span class="sr-only">Cari menu</span>
        <svg class="icon icon-sm"><use href="#i-search"/></svg>
        <input type="search" id="sidebar-search" placeholder="Cari menu" autocomplete="off">
        <span class="kbd" aria-hidden="true">Ctrl K</span>
    </label>

    <div class="sidebar-nav">
        @foreach ($grupMenu as $judul => $menu)
            <p class="nav-label" data-nav-group>{{ $judul }}</p>
            <ul class="nav">
                @foreach ($menu as $item)
                    <li data-nav-item>
                        @isset($item['url'])
                            <a class="nav-link" href="{{ $item['url'] }}" title="{{ $item['label'] }}" @if ($item['aktif']) aria-current="page" @endif>
                                <svg class="icon"><use href="#{{ $item['ikon'] }}"/></svg>
                                <span class="nav-text">{{ $item['label'] }}</span>
                            </a>
                        @else
                            <span class="nav-link" aria-disabled="true" title="{{ $item['label'] }} ({{ $item['tag'] }})">
                                <svg class="icon"><use href="#{{ $item['ikon'] }}"/></svg>
                                <span class="nav-text">{{ $item['label'] }}</span>
                                <span class="nav-tag">{{ $item['tag'] }}</span>
                            </span>
                        @endisset
                    </li>
                @endforeach
            </ul>
        @endforeach
        <p class="nav-empty" id="sidebar-empty" hidden>Menu tidak ditemukan.</p>
    </div>

    <div class="sidebar-promo">
        <strong>Pantau stok di peta</strong>
        <p>Status Aman, Waspada, dan Kritis per kecamatan dalam satu layar.</p>
        <a class="btn btn-sm" href="{{ url('/') }}" title="Buka peta ketersediaan"><svg class="icon icon-sm"><use href="#i-layers"/></svg><span class="nav-text">Buka peta</span></a>
    </div>

    <div class="sidebar-foot">
        <span class="avatar" aria-hidden="true">{{ $inisial }}</span>
        <span class="sidebar-user">
            <strong>{{ $pengguna->name }}</strong>
            <span>{{ $pengguna->email }}</span>
        </span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-logout" aria-label="Keluar" title="Keluar">
                <svg class="icon"><use href="#i-logout"/></svg>
            </button>
        </form>
    </div>
</nav>
