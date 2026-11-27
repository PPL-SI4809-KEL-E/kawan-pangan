{{-- PBI-04 · Panel Badan Resmi (PSKE-16, PSKE-31). Tampilan v2: PSKE-92 --}}
@extends('layouts.panel')

@section('title', 'Panel Badan Resmi')

@section('content')
<div class="stack stack-lg">
    <section class="welcome-card">
        <div>
            <p class="eyebrow">Panel Badan Resmi</p>
            <h1>Selamat datang, {{ auth()->user()->name }}</h1>
        </div>
        <p>Kelola gudang instansi Anda, tentukan kecamatan yang dilayani, dan siapkan pencatatan stok.</p>
        <div class="welcome-facts">
            <span class="welcome-fact"><svg class="icon icon-sm"><use href="#i-building"/></svg>Instansi <strong class="mono">{{ auth()->user()->instansi_id ? 'ID #'.auth()->user()->instansi_id : 'Belum ditentukan' }}</strong></span>
            @if (isset($gudangList))
                <span class="welcome-fact"><svg class="icon icon-sm"><use href="#i-warehouse"/></svg><strong>{{ $gudangList->count() }}</strong> gudang terdaftar</span>
            @endif
        </div>
    </section>

    {{-- Daftar gudang instansi dan akses Layar M-12 (PSKE-48 / PBI-10) --}}
    <section id="gudang">
        <div class="section-head">
            <h2>Gudang instansi</h2>
            <p>Atur cakupan kecamatan dan gudang utama logistik pangan (Layar M-12).</p>
        </div>
        @if (isset($gudangList) && $gudangList->isNotEmpty())
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Nama gudang</th>
                            <th scope="col">Wilayah</th>
                            <th scope="col">Alamat</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="actions">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gudangList as $gdg)
                            <tr>
                                <td><strong>{{ $gdg->nama }}</strong></td>
                                <td>{{ $gdg->wilayah ? $gdg->wilayah->nama : '-' }}</td>
                                <td class="text-muted">{{ $gdg->alamat }}</td>
                                <td>
                                    <span class="badge {{ $gdg->aktif ? 'badge-aman' : 'badge-netral' }}">{{ $gdg->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                                </td>
                                <td class="actions">
                                    @if (auth()->user()->isPengelolaInstansi())
                                        <a href="{{ route('panel.instansi.gudang.cakupan.index', $gdg) }}" class="btn btn-primary btn-sm">Atur Cakupan (M-12)</a>
                                    @else
                                        <span class="text-muted">Hanya pengelola</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="card empty-state">
                <span class="tile-icon"><svg class="icon"><use href="#i-warehouse"/></svg></span>
                <strong>Belum ada gudang untuk instansi ini</strong>
                <span>Minta Superadmin mendaftarkan gudang instansi Anda di menu Data master.</span>
            </div>
        @endif
    </section>

    <section>
        <div class="section-head">
            <h2>Stok dan fitur berikutnya</h2>
            <p>Fitur yang belum aktif menyusul sesuai jadwal sprint.</p>
        </div>
        <div class="tile-grid">
            <a class="tile" href="{{ route('panel.instansi.stok.index') }}">
                <span class="tile-icon"><svg class="icon"><use href="#i-chart"/></svg></span>
                <h3 class="tile-title">Stok dan mutasi</h3>
                <p>Lihat stok tiap gudang dan catat barang masuk, penyaluran, rusak, retur, atau stock opname.</p>
                <span class="tile-foot">Buka daftar stok <svg class="icon icon-sm"><use href="#i-arrow-right"/></svg></span>
            </a>
            <div class="tile is-soon" aria-disabled="true">
                <span class="tile-icon"><svg class="icon"><use href="#i-truck"/></svg></span>
                <h3 class="tile-title">Penyaluran dan pengiriman</h3>
                <p>Verifikasi laporan kebutuhan dan buat rencana pengiriman bantuan.</p>
                <span class="tile-foot"><span class="badge badge-netral">Sprint 3 · PBI-21</span></span>
            </div>
            <a class="tile" href="{{ url('/') }}">
                <span class="tile-icon"><svg class="icon"><use href="#i-layers"/></svg></span>
                <h3 class="tile-title">Peta ketersediaan</h3>
                <p>Lihat status stok per kecamatan di peta publik.</p>
                <span class="tile-foot">Buka peta <svg class="icon icon-sm"><use href="#i-arrow-right"/></svg></span>
            </a>
        </div>
    </section>
</div>
@endsection
