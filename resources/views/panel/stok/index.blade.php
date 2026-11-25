{{-- PBI-13 · Layar M-11 Daftar stok per gudang dan komoditas --}}
@extends('layouts.panel')

@section('title', 'Daftar Stok')

@php
    // Angka kuantitas: pemisah ribuan titik, desimal koma, nol di belakang dibuang.
    $angka = function ($nilai) {
        $teks = number_format((float) $nilai, 3, ',', '.');
        return rtrim(rtrim($teks, '0'), ',');
    };
    $adaFilter = filled($filter['gudang'] ?? null) || filled($filter['komoditas'] ?? null);
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1>Daftar stok</h1>
        <p>Stok fisik, cadangan, dan stok tersedia per komoditas di setiap gudang instansi Anda.</p>
    </div>
    <p class="text-muted cluster">
        <svg class="icon icon-sm"><use href="#i-clock"/></svg>
        Terakhir diperbarui:
        <strong>{{ $terakhirDiperbarui ? \Illuminate\Support\Carbon::parse($terakhirDiperbarui)->translatedFormat('d M Y H:i') : 'belum ada mutasi' }}</strong>
    </p>
</div>

<form class="card" method="get" action="{{ route('panel.instansi.stok.index') }}">
    <div class="grid grid-2">
        <div class="field">
            <label for="gudang">Gudang</label>
            <select class="select" id="gudang" name="gudang">
                <option value="">Semua gudang</option>
                @foreach ($pilihanGudang as $gdg)
                    <option value="{{ $gdg->id }}" @selected((string) ($filter['gudang'] ?? '') === (string) $gdg->id)>{{ $gdg->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="komoditas">Komoditas</label>
            <select class="select" id="komoditas" name="komoditas">
                <option value="">Semua komoditas</option>
                @foreach ($pilihanKomoditas as $kmd)
                    <option value="{{ $kmd->id }}" @selected((string) ($filter['komoditas'] ?? '') === (string) $kmd->id)>{{ $kmd->nama }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" type="submit">Terapkan</button>
        <a class="btn btn-secondary" href="{{ route('panel.instansi.stok.index') }}">Reset</a>
    </div>
</form>

<div class="table-wrap" tabindex="0" role="region" aria-label="Daftar stok, geser untuk melihat semua kolom">
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Komoditas</th>
                <th scope="col">Gudang</th>
                <th scope="col">Satuan</th>
                <th scope="col" class="num">Stok fisik</th>
                <th scope="col" class="num">Cadangan</th>
                <th scope="col" class="num">Tersedia</th>
                <th scope="col" class="num">Penggunaan harian (30 hari)</th>
                <th scope="col" class="num">Hari tersisa</th>
                <th scope="col">Perkiraan habis</th>
                <th scope="col">Status</th>
                <th scope="col">Diperbarui</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stok as $baris)
                @php $est = $estimasi[$baris->id]; @endphp
                <tr data-stok-id="{{ $baris->id }}">
                    <td><strong>{{ $baris->komoditas->nama }}</strong> <x-badge-data-contoh :contoh="$baris->is_contoh" /></td>
                    <td>{{ $baris->gudang->nama }}</td>
                    <td>{{ $baris->komoditas->satuan }}</td>
                    <td class="num">{{ $angka($baris->jumlah_fisik_layak) }}</td>
                    <td class="num">{{ $angka($baris->cadangan_aktif) }}</td>
                    <td class="num"><strong>{{ $angka($baris->tersedia) }}</strong></td>
                    <td class="num">{{ $angka($est['pemakaian_harian']) }}</td>
                    <td class="num">{{ $est['hari'] === null ? '–' : $angka($est['hari']) }}</td>
                    <td>{{ $est['perkiraan_habis'] ?? '–' }}</td>
                    <td>{{ ucfirst(strtolower(str_replace('_', ' ', $est['status']))) }}
                        @if ($est['pesan']) <small>{{ $est['pesan'] }}</small> @endif
                    </td>
                    <td class="text-muted">{{ $baris->diperbarui_pada ? $baris->diperbarui_pada->translatedFormat('d M Y H:i') : 'Belum ada mutasi' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">
                        @if (! auth()->user()->instansi_id)
                            Akun Anda belum ditugaskan ke instansi. Hubungi Superadmin.
                        @elseif ($adaFilter)
                            Tidak ada stok yang sesuai filter.
                        @else
                            Belum ada stok tercatat di gudang instansi Anda.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.partials.pagination', ['paginator' => $stok, 'label' => 'baris stok'])
@endsection
