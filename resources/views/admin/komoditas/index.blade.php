@extends('layouts.admin')
@section('title', 'Master Komoditas')
@section('content')
<div class="page-header"><div><h1>Master Komoditas</h1><p class="text-muted">Kelola komoditas dan satuan pencatatan stok.</p></div><a class="btn btn-primary" href="{{ route('admin.komoditas.create') }}">Tambah komoditas</a></div>
<form class="card" method="get" action="{{ route('admin.komoditas.index') }}">
    <div class="field"><label for="q">Cari nama komoditas</label><input class="input" id="q" name="q" maxlength="150" value="{{ request('q') }}"></div>
    <div class="page-actions"><button class="btn btn-primary" type="submit">Cari</button><a class="btn btn-secondary" href="{{ route('admin.komoditas.index') }}">Reset</a></div>
</form>
<div class="table-wrap" tabindex="0" role="region" aria-label="Daftar komoditas, geser untuk melihat semua kolom">
    <table class="table"><thead><tr><th scope="col">Nama komoditas</th><th scope="col">Satuan</th><th scope="col">Aksi</th></tr></thead><tbody>
    @forelse ($komoditas as $item)
        <tr><td>{{ $item->nama }}</td><td>{{ $item->satuan }}</td><td><div class="page-actions">
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.komoditas.edit', $item) }}" aria-label="Ubah {{ $item->nama }}">Ubah</a>
            <form method="post" action="{{ route('admin.komoditas.destroy', $item) }}" data-confirm="Hapus komoditas {{ $item->nama }}?">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" aria-label="Hapus {{ $item->nama }}">Hapus</button></form>
        </div></td></tr>
    @empty
        <tr><td colspan="3">{{ request('q') ? 'Tidak ada komoditas yang sesuai pencarian.' : 'Belum ada komoditas. Tambahkan komoditas untuk memulai.' }}</td></tr>
    @endforelse
    </tbody></table>
</div>
@include('admin.partials.pagination', ['paginator' => $komoditas, 'label' => 'komoditas'])
@endsection
