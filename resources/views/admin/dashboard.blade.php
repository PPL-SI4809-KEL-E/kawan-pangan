{{-- PBI-04 · M-10 Panel Superadmin (PSKE-16, PSKE-31) --}}
@extends('layouts.admin')

@section('title', 'Panel Superadmin')

@section('content')
<div class="stack">
    <div>
        <h1>Panel Superadmin</h1>
        <p class="text-muted">Selamat datang, <strong>{{ auth()->user()->name }}</strong>. Kelola administrasi sistem, data master, dan verifikasi akun.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4); margin-top: var(--space-4);">
        {{-- Tautan ke Master Wilayah (PBI-08 / PSKE-19) --}}
        <div class="card">
            <h2 class="card-title">Data Master Wilayah</h2>
            <p class="text-muted" style="margin-bottom: var(--space-4); font-size: var(--font-size-sm);">
                Impor dan kelola data wilayah Kemendagri 4 level (provinsi, kabupaten/kota, kecamatan, desa).
            </p>
            <div class="card-footer">
                <a href="{{ route('admin.wilayah.index') }}" class="btn btn-primary">Buka Master Wilayah</a>
            </div>
        </div>

        {{-- Tautan ke Verifikasi Akun (PBI-06 / PSKE-17) --}}
        <div class="card">
            <h2 class="card-title">Verifikasi Akun</h2>
            <p class="text-muted" style="margin-bottom: var(--space-4); font-size: var(--font-size-sm);">
                Verifikasi pendaftaran akun Kepala Wilayah dan staf Badan Resmi beserta surat tugasnya.
            </p>
            <div class="card-footer">
                <a href="{{ route('admin.akun.index') }}" class="btn btn-secondary">Buka Verifikasi Akun</a>
            </div>
        </div>
    </div>
</div>
@endsection
