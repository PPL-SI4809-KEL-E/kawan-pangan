{{-- PBI-13 · Layar M-11 Daftar stok per gudang dan komoditas --}}
@extends('layouts.panel')

@section('title', 'Daftar Stok')

@php
    // Angka kuantitas: pemisah ribuan titik, desimal koma, nol di belakang dibuang.
    $angka = function ($nilai) {
        $teks = number_format((float) $nilai, 3, ',', '.');
        return rtrim(rtrim($teks, '0'), ',');
    };
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1>Daftar stok</h1>
        <p>Stok fisik, cadangan, dan stok tersedia per komoditas di setiap gudang instansi Anda.</p>
    </div>
</div>

@if (empty($estimasi))
    <div class="alert alert-info" role="status">
        Penggunaan harian, hari tersisa, perkiraan habis, status, periode hitung, dan kelengkapan data akan tampil setelah layanan estimasi stok (PBI-12) aktif.
    </div>
@endif

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
                <th scope="col" class="num">Penggunaan harian</th>
                <th scope="col" class="num">Hari tersisa</th>
                <th scope="col">Perkiraan habis</th>
                <th scope="col">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stok as $baris)
                @php $est = $estimasi[$baris->id] ?? null; @endphp
                <tr data-stok-id="{{ $baris->id }}">
                    <td><strong>{{ $baris->komoditas->nama }}</strong> <x-badge-data-contoh :contoh="$baris->is_contoh" /></td>
                    <td>{{ $baris->gudang->nama }}</td>
                    <td>{{ $baris->komoditas->satuan }}</td>
                    <td class="num">{{ $angka($baris->jumlah_fisik_layak) }}</td>
                    <td class="num">{{ $angka($baris->cadangan_aktif) }}</td>
                    <td class="num"><strong>{{ $angka($baris->tersedia) }}</strong></td>
                    <td class="num">{{ $est['penggunaan_harian'] ?? '–' }}</td>
                    <td class="num">{{ $est['hari_tersisa'] ?? '–' }}</td>
                    <td>{{ $est['perkiraan_habis'] ?? '–' }}</td>
                    <td>{{ $est['status'] ?? '–' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">
                        @if (! auth()->user()->instansi_id)
                            Akun Anda belum ditugaskan ke instansi. Hubungi Superadmin.
                        @else
                            Belum ada stok tercatat di gudang instansi Anda.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
