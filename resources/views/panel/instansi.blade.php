{{-- PBI-04 · Panel Badan Resmi (PSKE-16, PSKE-31) --}}
@extends('layouts.panel')

@section('title', 'Panel Badan Resmi')

@section('content')
<div class="stack">
    <div>
        <h1>Panel Badan Resmi</h1>
        <p class="text-muted">
            Selamat datang, <strong>{{ auth()->user()->name }}</strong>. 
            Instansi penugasan: <code>{{ auth()->user()->instansi_id ? 'ID #' . auth()->user()->instansi_id : 'Belum ditentukan' }}</code>.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4); margin-top: var(--space-4);">
        <div class="card">
            <h2 class="card-title">Manajemen Stok & Gudang</h2>
            <p class="text-muted" style="margin-bottom: var(--space-4); font-size: var(--font-size-sm);">
                Pencatatan mutasi komoditas pangan masuk, keluar, dan penyesuaian stok (Sprint 2 · PBI-11).
            </p>
            <div class="card-footer">
                <button type="button" class="btn btn-primary" disabled title="Akan hadir di Sprint 2">Kelola Stok</button>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Penyaluran & Pengiriman</h2>
            <p class="text-muted" style="margin-bottom: var(--space-4); font-size: var(--font-size-sm);">
                Verifikasi laporan kebutuhan dan buat rencana pengiriman bantuan pangan (Sprint 3 · PBI-21).
            </p>
            <div class="card-footer">
                <button type="button" class="btn btn-secondary" disabled title="Akan hadir di Sprint 3">Penyaluran</button>
            </div>
        </div>
    </div>
</div>
@endsection
