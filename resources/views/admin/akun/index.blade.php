{{-- PBI-06 · M-16 Daftar Verifikasi Akun Pengguna (PSKE-17) --}}
@extends('layouts.admin')

@section('title', 'Verifikasi Akun')

@section('content')
<div class="stack">
    <div>
        <h1>Verifikasi & Manajemen Akun</h1>
        <p class="text-muted">Kelola persetujuan pendaftar Kepala Wilayah dan staf Badan Resmi beserta surat tugasnya.</p>
    </div>

    {{-- Tab Filter Status --}}
    <div style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-2); overflow-x: auto;">
        @php
            $daftarTab = [
                'MENUNGGU' => ['label' => 'Menunggu Verifikasi', 'badge' => 'badge-waspada'],
                'AKTIF' => ['label' => 'Aktif', 'badge' => 'badge-aman'],
                'DITOLAK' => ['label' => 'Ditolak', 'badge' => 'badge-kritis'],
                'NONAKTIF' => ['label' => 'Nonaktif', 'badge' => 'badge-netral'],
                'SEMUA' => ['label' => 'Semua Akun', 'badge' => 'badge-info'],
            ];
        @endphp

        @foreach ($daftarTab as $key => $tab)
            <a 
                href="{{ route('admin.akun.index', ['status' => $key, 'q' => $pencarian]) }}" 
                class="btn btn-sm {{ $statusDipilih === $key ? 'btn-primary' : 'btn-ghost' }}"
                style="white-space: nowrap;"
            >
                {{ $tab['label'] }}
                @if ($key !== 'SEMUA')
                    <span class="badge {{ $tab['badge'] }}" style="margin-left: var(--space-1); font-size: 0.7rem;">
                        {{ $jumlahStatus[$key] ?? 0 }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Form Pencarian --}}
    <form method="GET" action="{{ route('admin.akun.index') }}" style="display: flex; gap: var(--space-2); max-width: 450px;">
        <input type="hidden" name="status" value="{{ $statusDipilih }}">
        <input 
            type="search" 
            name="q" 
            value="{{ $pencarian }}" 
            placeholder="Cari nama, email, atau no. telepon..." 
            class="input"
        >
        <button type="submit" class="btn btn-secondary">Cari</button>
        @if ($pencarian)
            <a href="{{ route('admin.akun.index', ['status' => $statusDipilih]) }}" class="btn btn-ghost">Reset</a>
        @endif
    </form>

    {{-- Tabel Pengguna --}}
    <div class="card" style="padding: 0; overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: var(--font-size-sm);">
            <thead>
                <tr style="border-bottom: 1px solid var(--color-border); background: var(--color-netral-bg);">
                    <th style="padding: var(--space-3) var(--space-4);">Nama & Kontak</th>
                    <th style="padding: var(--space-3) var(--space-4);">Peran</th>
                    <th style="padding: var(--space-3) var(--space-4);">Status</th>
                    <th style="padding: var(--space-3) var(--space-4);">Penugasan Saat Ini</th>
                    <th style="padding: var(--space-3) var(--space-4); text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($akun as $item)
                    <tr style="border-bottom: 1px solid var(--color-border);">
                        <td style="padding: var(--space-3) var(--space-4);">
                            <strong>{{ $item->name }}</strong>
                            <div class="text-muted" style="font-size: var(--font-size-xs);">{{ $item->email }}</div>
                            <div class="text-muted" style="font-size: var(--font-size-xs);">{{ $item->no_hp ?? '-' }}</div>
                        </td>
                        <td style="padding: var(--space-3) var(--space-4);">
                            <span class="badge {{ $item->isKepalaWilayah() ? 'badge-info' : 'badge-waspada' }}">
                                {{ $item->role->label() }}
                            </span>
                        </td>
                        <td style="padding: var(--space-3) var(--space-4);">
                            <span class="badge {{ $item->status->badge() }}">
                                {{ $item->status->label() }}
                            </span>
                        </td>
                        <td style="padding: var(--space-3) var(--space-4);">
                            @if ($item->isKepalaWilayah())
                                @if ($item->kode_wilayah)
                                    <div><code>{{ $item->kode_wilayah }}</code></div>
                                    <div class="text-muted" style="font-size: var(--font-size-xs);">{{ $item->wilayah->nama ?? 'Wilayah ID' }}</div>
                                @else
                                    <span class="text-muted" style="font-style: italic;">Belum ditugaskan</span>
                                @endif
                            @elseif ($item->isBadanResmi())
                                @if ($item->instansi_id)
                                    <div><strong>{{ $item->instansi->nama ?? 'Instansi #' . $item->instansi_id }}</strong></div>
                                    <div class="text-muted" style="font-size: var(--font-size-xs);">Peran: <strong>{{ $item->getAttribute('jabatan') ?? 'PETUGAS' }}</strong></div>
                                @else
                                    <span class="text-muted" style="font-style: italic;">Belum ditugaskan</span>
                                @endif
                            @endif
                        </td>
                        <td style="padding: var(--space-3) var(--space-4); text-align: right;">
                            <a href="{{ route('admin.akun.show', $item) }}" class="btn btn-secondary btn-sm">
                                Periksa & Verifikasi
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding: var(--space-6); text-align: center;" class="text-muted">
                            Tidak ada akun yang sesuai dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginasi --}}
    @if ($akun->hasPages())
        <div style="margin-top: var(--space-4);">
            {{ $akun->links() }}
        </div>
    @endif
</div>
@endsection
