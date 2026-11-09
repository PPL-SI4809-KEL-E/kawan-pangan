@extends('layouts.admin')
@section('title', $instansi->exists ? 'Ubah Instansi' : 'Tambah Instansi')
@section('content')
<div class="page-header"><h1>{{ $instansi->exists ? 'Ubah instansi' : 'Tambah instansi' }}</h1><a class="btn btn-secondary" href="{{ route('admin.instansi.index') }}">Kembali ke daftar</a></div>
<section class="card wilayah-edit">
    <form method="post" action="{{ $instansi->exists ? route('admin.instansi.update', $instansi) : route('admin.instansi.store') }}">
        @csrf
        @if ($instansi->exists) @method('PATCH') @endif
        @include('admin.partials.lokasi', ['item' => $instansi, 'jenis' => 'instansi'])
        <button class="btn btn-primary" type="submit">Simpan instansi</button>
    </form>
</section>
@endsection
