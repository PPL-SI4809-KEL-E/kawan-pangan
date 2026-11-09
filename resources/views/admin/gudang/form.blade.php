@extends('layouts.admin')
@section('title', $gudang->exists ? 'Ubah Gudang' : 'Tambah Gudang')
@section('content')
<div class="page-header"><h1>{{ $gudang->exists ? 'Ubah gudang' : 'Tambah gudang' }}</h1><a class="btn btn-secondary" href="{{ route('admin.gudang.index') }}">Kembali ke daftar</a></div>
@if ($instansi->isEmpty())<div class="alert alert-warning">Tambahkan instansi pengelola terlebih dahulu. <a href="{{ route('admin.instansi.create') }}">Tambah instansi</a></div>@endif
<section class="card wilayah-edit">
    <form method="post" action="{{ $gudang->exists ? route('admin.gudang.update', $gudang) : route('admin.gudang.store') }}">
        @csrf
        @if ($gudang->exists) @method('PATCH') @endif
        <div class="field @error('instansi_id') has-error @enderror">
            <label for="instansi_id">Instansi pengelola <span class="required">*</span></label>
            <select class="select" name="instansi_id" id="instansi_id" required @error('instansi_id') aria-invalid="true" @enderror><option value="">Pilih instansi</option>@foreach ($instansi as $pilihan)<option value="{{ $pilihan->id }}" @selected((string) old('instansi_id', $gudang->instansi_id) === (string) $pilihan->id)>{{ $pilihan->nama }}</option>@endforeach</select>
        </div>
        <div class="field @error('kode_wilayah') has-error @enderror">
            <label for="kode_wilayah">Kode wilayah gudang <span class="required">*</span></label>
            <input class="input" id="kode_wilayah" name="kode_wilayah" maxlength="20" required value="{{ old('kode_wilayah', $gudang->kode_wilayah) }}" list="opsi-wilayah" data-opsi-wilayah="{{ route('admin.gudang.opsi-wilayah') }}" aria-describedby="wilayah-help" autocomplete="off" @error('kode_wilayah') aria-invalid="true" @enderror>
            <datalist id="opsi-wilayah"></datalist>
            <span class="field-help" id="wilayah-help">Ketik awalan kode untuk mencari, lalu pilih kode wilayah yang tersedia. <a href="{{ route('admin.wilayah.index') }}" target="_blank" rel="noopener">Lihat master wilayah</a>.</span>
            <span class="field-help" data-wilayah-hasil role="status"></span>
        </div>
        @include('admin.partials.lokasi', ['item' => $gudang, 'jenis' => 'gudang'])
        <div class="field @error('aktif') has-error @enderror">
            <label for="aktif">Status gudang <span class="required">*</span></label>
            <select class="select" id="aktif" name="aktif" required @error('aktif') aria-invalid="true" @enderror><option value="1" @selected((string) old('aktif', (int) $gudang->aktif) === '1')>Aktif</option><option value="0" @selected((string) old('aktif', (int) $gudang->aktif) === '0')>Nonaktif</option></select>
            <span class="field-help">Nonaktif menyimpan data gudang beserta lokasi dan relasinya.</span>
        </div>
        <button class="btn btn-primary" type="submit" @disabled($instansi->isEmpty())>Simpan gudang</button>
    </form>
</section>
@endsection
