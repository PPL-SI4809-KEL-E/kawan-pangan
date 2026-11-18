{{-- PBI-04 · M-10 Panel Superadmin (PSKE-16, PSKE-31). Tampilan v2: PSKE-92 --}}
@extends('layouts.admin')

@section('title', 'Panel Superadmin')

@section('content')
<div class="stack stack-lg">
    <section class="welcome-card">
        <div>
            <p class="eyebrow">Panel Superadmin</p>
            <h1>Selamat datang, {{ auth()->user()->name }}</h1>
        </div>
        <p>Verifikasi pendaftar baru dan jaga data master tetap rapi. Data ini dipakai peta, laporan, dan perhitungan status stok.</p>
        <div class="welcome-facts">
            <span class="welcome-fact"><svg class="icon icon-sm"><use href="#i-user-check"/></svg><strong>Verifikasi akun</strong> surat tugas pendaftar</span>
            <span class="welcome-fact"><svg class="icon icon-sm"><use href="#i-map"/></svg><strong>4 level</strong> wilayah Kemendagri</span>
        </div>
    </section>

    <section>
        <div class="section-head">
            <h2>Pekerjaan rutin</h2>
            <p>Mulai dari antrean pendaftar, lalu periksa data master.</p>
        </div>
        <div class="tile-grid">
            <a class="tile" href="{{ route('admin.akun.index') }}">
                <span class="tile-icon"><svg class="icon"><use href="#i-user-check"/></svg></span>
                <h3 class="tile-title">Verifikasi akun</h3>
                <p>Periksa surat tugas Kepala Wilayah dan staf Badan Resmi, lalu aktifkan dengan penugasan wilayah atau instansi.</p>
                <span class="tile-foot">Buka antrean <svg class="icon icon-sm"><use href="#i-arrow-right"/></svg></span>
            </a>
            <a class="tile" href="{{ route('admin.wilayah.index') }}">
                <span class="tile-icon"><svg class="icon"><use href="#i-map"/></svg></span>
                <h3 class="tile-title">Master wilayah</h3>
                <p>Impor kode wilayah Kemendagri dan jumlah penduduk untuk peta dan laporan.</p>
                <span class="tile-foot">Kelola wilayah <svg class="icon icon-sm"><use href="#i-arrow-right"/></svg></span>
            </a>
            <a class="tile" href="{{ route('admin.gudang.index') }}">
                <span class="tile-icon"><svg class="icon"><use href="#i-warehouse"/></svg></span>
                <h3 class="tile-title">Gudang dan cakupan</h3>
                <p>Daftarkan gudang tiap instansi dan atur kecamatan yang dilayani.</p>
                <span class="tile-foot">Kelola gudang <svg class="icon icon-sm"><use href="#i-arrow-right"/></svg></span>
            </a>
            <a class="tile" href="{{ route('admin.komoditas.index') }}">
                <span class="tile-icon"><svg class="icon"><use href="#i-package"/></svg></span>
                <h3 class="tile-title">Komoditas</h3>
                <p>Jenis pangan yang dicatat beserta satuannya.</p>
                <span class="tile-foot">Kelola komoditas <svg class="icon icon-sm"><use href="#i-arrow-right"/></svg></span>
            </a>
            <a class="tile" href="{{ route('admin.instansi.index') }}">
                <span class="tile-icon"><svg class="icon"><use href="#i-building"/></svg></span>
                <h3 class="tile-title">Instansi</h3>
                <p>Badan resmi pemilik gudang, seperti Bulog atau dinas ketahanan pangan.</p>
                <span class="tile-foot">Kelola instansi <svg class="icon icon-sm"><use href="#i-arrow-right"/></svg></span>
            </a>
            <a class="tile" href="{{ route('admin.ambang-status.edit') }}">
                <span class="tile-icon"><svg class="icon"><use href="#i-sliders"/></svg></span>
                <h3 class="tile-title">Ambang status</h3>
                <p>Batas hari ketahanan stok untuk status Aman, Waspada, dan Kritis.</p>
                <span class="tile-foot">Atur ambang <svg class="icon icon-sm"><use href="#i-arrow-right"/></svg></span>
            </a>
        </div>
    </section>
</div>
@endsection
