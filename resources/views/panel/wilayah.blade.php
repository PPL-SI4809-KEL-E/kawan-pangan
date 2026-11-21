{{-- PBI-04 · Panel Kepala Wilayah (PSKE-16, PSKE-31). Tampilan v2: PSKE-92 --}}
@extends('layouts.panel')

@section('title', 'Panel Kepala Wilayah')

@section('content')
<div class="stack stack-lg">
    <section class="welcome-card">
        <div>
            <p class="eyebrow">Panel Kepala Wilayah</p>
            <h1>Selamat datang, {{ auth()->user()->name }}</h1>
        </div>
        <p>Pantau ketersediaan pangan di wilayah Anda dan ajukan laporan kebutuhan bila stok mulai menipis.</p>
        <div class="welcome-facts">
            <span class="welcome-fact"><svg class="icon icon-sm"><use href="#i-pin"/></svg>Wilayah penugasan <strong class="mono">{{ auth()->user()->kode_wilayah ?? 'Belum ditentukan' }}</strong></span>
        </div>
    </section>

    <section>
        <div class="section-head">
            <h2>Fitur wilayah</h2>
            <p>Fitur di bawah sedang dikembangkan dan akan aktif sesuai jadwal sprint.</p>
        </div>
        <div class="tile-grid">
            <a class="tile" href="{{ url('/') }}">
                <span class="tile-icon"><svg class="icon"><use href="#i-layers"/></svg></span>
                <h3 class="tile-title">Peta ketersediaan</h3>
                <p>Lihat status stok per kecamatan di peta publik.</p>
                <span class="tile-foot">Buka peta <svg class="icon icon-sm"><use href="#i-arrow-right"/></svg></span>
            </a>
            <div class="tile is-soon" aria-disabled="true">
                <span class="tile-icon"><svg class="icon"><use href="#i-file"/></svg></span>
                <h3 class="tile-title">Laporan kebutuhan pangan</h3>
                <p>Ajukan laporan kekurangan atau kebutuhan pangan untuk wilayah Anda.</p>
                <span class="tile-foot"><span class="badge badge-netral">Sprint 3 · PBI-18</span></span>
            </div>
            <div class="tile is-soon" aria-disabled="true">
                <span class="tile-icon"><svg class="icon"><use href="#i-inbox"/></svg></span>
                <h3 class="tile-title">Penerimaan bantuan</h3>
                <p>Konfirmasi jumlah komoditas bantuan yang tiba, selisih, dan bukti foto.</p>
                <span class="tile-foot"><span class="badge badge-netral">Sprint 3 · PBI-23</span></span>
            </div>
        </div>
    </section>
</div>
@endsection
