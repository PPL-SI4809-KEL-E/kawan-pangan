{{-- PBI-04 · Panel Kepala Wilayah (PSKE-16, PSKE-31) --}}
@extends('layouts.panel')

@section('title', 'Panel Kepala Wilayah')

@section('content')
<div class="stack">
    <div>
        <h1>Panel Kepala Wilayah</h1>
        <p class="text-muted">
            Selamat datang, <strong>{{ auth()->user()->name }}</strong>. 
            Cakupan wilayah penugasan: <code>{{ auth()->user()->kode_wilayah ?? 'Belum ditentukan' }}</code>.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4); margin-top: var(--space-4);">
        <div class="card">
            <h2 class="card-title">Laporan Kebutuhan Pangan</h2>
            <p class="text-muted" style="margin-bottom: var(--space-4); font-size: var(--font-size-sm);">
                Buat dan ajukan laporan kekurangan atau kebutuhan pangan untuk wilayah Anda (Sprint 3 · PBI-18).
            </p>
            <div class="card-footer">
                <button type="button" class="btn btn-primary" disabled title="Akan hadir di Sprint 3">Buat Laporan</button>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Penerimaan Bantuan</h2>
            <p class="text-muted" style="margin-bottom: var(--space-4); font-size: var(--font-size-sm);">
                Konfirmasi penerimaan komoditas bantuan yang tiba di wilayah Anda (Sprint 3 · PBI-23).
            </p>
            <div class="card-footer">
                <button type="button" class="btn btn-secondary" disabled title="Akan hadir di Sprint 3">Daftar Penerimaan</button>
            </div>
        </div>
    </div>
</div>
@endsection
