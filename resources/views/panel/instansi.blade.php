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

    {{-- Daftar Gudang Instansi & Akses Layar M-12 (PSKE-48 / PBI-10) --}}
    @if (isset($gudangList) && $gudangList->isNotEmpty())
        <div class="card stack" style="margin-top: var(--space-4); gap: var(--space-4);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                <div>
                    <h2 class="card-title" style="margin: 0;">Gudang Instansi Anda</h2>
                    <p class="text-muted" style="font-size: var(--font-size-xs); margin: 0;">
                        Kelola cakupan kecamatan dan penetapan gudang utama logistik pangan (Layar M-12).
                    </p>
                </div>
                <span class="badge badge-info">{{ $gudangList->count() }} Gudang Terdaftar</span>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Nama Gudang</th>
                            <th scope="col">Wilayah</th>
                            <th scope="col">Alamat</th>
                            <th scope="col">Status</th>
                            <th scope="col" style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gudangList as $gdg)
                            <tr>
                                <td><strong>{{ $gdg->nama }}</strong></td>
                                <td>{{ $gdg->wilayah ? $gdg->wilayah->nama : '-' }}</td>
                                <td class="text-muted">{{ $gdg->alamat }}</td>
                                <td>
                                    <span class="badge {{ $gdg->aktif ? 'badge-aman' : 'badge-netral' }}">
                                        {{ $gdg->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    @if (auth()->user()->isPengelolaInstansi())
                                        <a href="{{ route('panel.instansi.gudang.cakupan.index', $gdg) }}" class="btn btn-primary btn-sm">
                                            Atur Cakupan (M-12)
                                        </a>
                                    @else
                                        <span class="text-muted" style="font-size: var(--font-size-xs);">Hanya Pengelola</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

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
