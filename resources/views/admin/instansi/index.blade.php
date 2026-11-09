@extends('layouts.admin')
@section('title', 'Master Instansi')
@section('content')
<div class="page-header"><div><h1>Master Instansi</h1><p class="text-muted">Kelola instansi pengelola gudang beserta alamat dan koordinatnya.</p></div><a class="btn btn-primary" href="{{ route('admin.instansi.create') }}">Tambah instansi</a></div>
<form class="card" method="get" action="{{ route('admin.instansi.index') }}">
    <div class="field"><label for="q">Cari nama instansi</label><input class="input" id="q" name="q" maxlength="150" value="{{ request('q') }}"></div>
    <div class="page-actions"><button class="btn btn-primary" type="submit">Cari</button><a class="btn btn-secondary" href="{{ route('admin.instansi.index') }}">Reset</a></div>
</form>
<div class="table-wrap" tabindex="0" role="region" aria-label="Daftar instansi, geser untuk melihat semua kolom">
    <table class="table"><thead><tr><th scope="col">Nama instansi</th><th scope="col">Alamat</th><th scope="col">Latitude, longitude</th><th scope="col">Gudang</th><th scope="col">Aksi</th></tr></thead><tbody>
    @forelse ($instansi as $item)
        <tr><td>{{ $item->nama }}</td><td>{{ $item->alamat }}</td><td>{{ $item->latitude }}, {{ $item->longitude }}</td><td><a href="{{ route('admin.gudang.index', ['instansi_id' => $item->id]) }}">{{ $item->gudang_count }} gudang</a></td><td><div class="page-actions">
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.instansi.edit', $item) }}" aria-label="Ubah {{ $item->nama }}">Ubah</a>
            <form method="post" action="{{ route('admin.instansi.destroy', $item) }}" data-confirm="Hapus instansi {{ $item->nama }}? Instansi yang masih digunakan tidak dapat dihapus.">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" aria-label="Hapus {{ $item->nama }}">Hapus</button></form>
        </div></td></tr>
    @empty
        <tr><td colspan="5">{{ request('q') ? 'Tidak ada instansi yang sesuai pencarian.' : 'Belum ada instansi. Tambahkan instansi untuk memulai.' }}</td></tr>
    @endforelse
    </tbody></table>
</div>
@include('admin.partials.pagination', ['paginator' => $instansi, 'label' => 'instansi'])
@endsection
