@extends('layouts.admin')
@section('title', $komoditas->exists ? 'Ubah Komoditas' : 'Tambah Komoditas')
@section('content')
<div class="page-header"><h1>{{ $komoditas->exists ? 'Ubah komoditas' : 'Tambah komoditas' }}</h1><a class="btn btn-secondary" href="{{ route('admin.komoditas.index') }}">Kembali ke daftar</a></div>
<section class="card wilayah-edit">
    <form method="post" action="{{ $komoditas->exists ? route('admin.komoditas.update', $komoditas) : route('admin.komoditas.store') }}">
        @csrf
        @if ($komoditas->exists) @method('PATCH') @endif
        <div class="field @error('nama') has-error @enderror">
            <label for="nama">Nama komoditas <span class="required">*</span></label>
            <input class="input" id="nama" name="nama" maxlength="150" required value="{{ old('nama', $komoditas->nama) }}" aria-describedby="nama-help" @error('nama') aria-invalid="true" @enderror>
            <span class="field-help" id="nama-help">Nama harus unik. Perbedaan huruf besar dan spasi tidak membedakan komoditas.</span>
        </div>
        <div class="field @error('satuan') has-error @enderror">
            <label for="satuan">Satuan <span class="required">*</span></label>
            <input class="input" id="satuan" name="satuan" maxlength="30" required placeholder="Contoh: kg, ton, liter" value="{{ old('satuan', $komoditas->satuan) }}" @error('satuan') aria-invalid="true" @enderror>
        </div>
        <button class="btn btn-primary" type="submit">Simpan komoditas</button>
    </form>
</section>
@endsection
