{{-- PBI-06 · M-16 Daftar Verifikasi Akun Pengguna (PSKE-17) --}}
@extends('layouts.admin')

@section('title', 'Verifikasi Akun')

@section('content')
<div class="stack">
    <div class="page-header">
        <div>
            <h1>Verifikasi & Manajemen Akun</h1>
            <p>Kelola persetujuan pendaftar Kepala Wilayah dan staf Badan Resmi beserta surat tugasnya.</p>
        </div>
    </div>

    {{-- Tab Filter Status --}}
    <div class="tab-bar">
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
            >
                {{ $tab['label'] }}
                @if ($key !== 'SEMUA')
                    <span class="badge {{ $tab['badge'] }} num">
                        {{ $jumlahStatus[$key] ?? 0 }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Form Pencarian --}}
    <form method="GET" action="{{ route('admin.akun.index') }}" class="search-bar">
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
    <div class="table-wrap" tabindex="0" role="region" aria-label="Daftar verifikasi akun pengguna">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Nama & Kontak</th>
                    <th scope="col">Peran</th>
                    <th scope="col">Status</th>
                    <th scope="col">Penugasan Saat Ini</th>
                    <th scope="col" class="actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($akun as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->name }}</strong>
                            <div class="text-muted text-xs">{{ $item->email }}</div>
                            <div class="text-muted text-xs num">{{ $item->no_hp ?? '-' }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $item->isKepalaWilayah() ? 'badge-info' : 'badge-waspada' }}">
                                {{ $item->role->label() }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $item->status->badge() }}">
                                {{ $item->status->label() }}
                            </span>
                        </td>
                        <td>
                            @if ($item->isKepalaWilayah())
                                @if ($item->kode_wilayah)
                                    <div><code class="num">{{ $item->kode_wilayah }}</code></div>
                                    <div class="text-muted text-xs">{{ $item->wilayah->nama ?? 'Wilayah ID' }}</div>
                                @else
                                    <span class="text-muted text-italic">Belum ditugaskan</span>
                                @endif
                            @elseif ($item->isBadanResmi())
                                @if ($item->instansi_id)
                                    <div><strong>{{ $item->instansi->nama ?? 'Instansi #' . $item->instansi_id }}</strong></div>
                                    <div class="text-muted text-xs">Peran: <strong>{{ $item->getAttribute('jabatan') ?? 'PETUGAS' }}</strong></div>
                                @else
                                    <span class="text-muted text-italic">Belum ditugaskan</span>
                                @endif
                            @endif
                        </td>
                        <td class="actions">
                            <a href="{{ route('admin.akun.show', $item) }}" class="btn btn-secondary btn-sm">
                                Periksa & Verifikasi
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="table-empty">
                            Tidak ada akun yang sesuai dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginasi --}}
    @if ($akun->hasPages())
        <div class="form-actions">
            {{ $akun->links() }}
        </div>
    @endif
</div>
@endsection
