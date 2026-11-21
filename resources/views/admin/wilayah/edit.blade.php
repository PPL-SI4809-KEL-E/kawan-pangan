@extends('layouts.admin')
@section('title', 'Ubah Jumlah Penduduk')
@section('content')
<div class="page-header"><div><h1>Ubah jumlah penduduk</h1><p class="text-muted">{{ $wilayah->kode }} · {{ $wilayah->nama }}@if ($wilayah->is_contoh) <span class="badge badge-netral">Data contoh</span>@endif</p></div><a class="btn btn-secondary" href="{{ route('admin.wilayah.index') }}">Kembali ke daftar</a></div>
<section class="card wilayah-edit" aria-label="Form jumlah penduduk">
    <dl><dt>Tingkat wilayah</dt><dd>{{ \App\Models\Wilayah::LEVEL[$wilayah->level] }}</dd><dt>Induk</dt><dd>{{ $wilayah->induk?->nama ?? 'Tidak ada (provinsi)' }}</dd></dl>
    <form action="{{ route('admin.wilayah.update', $wilayah) }}" method="post">
        @csrf
        @method('PATCH')
        <div class="field @error('jumlah_penduduk') has-error @enderror">
            <label for="jumlah_penduduk">Jumlah penduduk (jiwa) <span class="required">*</span></label>
            <input class="input" id="jumlah_penduduk" name="jumlah_penduduk" type="number" min="0" max="{{ \App\Models\Wilayah::MAKS_PENDUDUK }}" step="1" required value="{{ old('jumlah_penduduk', $wilayah->jumlah_penduduk) }}" aria-describedby="bantuan-penduduk" @error('jumlah_penduduk') aria-invalid="true" @enderror>
            <span class="field-help" id="bantuan-penduduk">Masukkan bilangan bulat tanpa pemisah ribuan. Nilai 0 diperbolehkan. Pembaruan berlaku pada wilayah ini.</span>
        </div>
        <button class="btn btn-primary" type="submit">Simpan jumlah penduduk</button>
    </form>
</section>
@endsection
