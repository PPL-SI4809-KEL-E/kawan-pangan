@extends('layouts.admin')
@section('title', 'Master Gudang')
@section('content')
<div class="page-header"><div><h1>Master Gudang</h1><p class="text-muted">Kelola lokasi, instansi pengelola, dan status aktif gudang.</p></div><a class="btn btn-primary" href="{{ route('admin.gudang.create') }}">Tambah gudang</a></div>
<form class="card" method="get" action="{{ route('admin.gudang.index') }}">
    <div class="grid grid-3">
        <div class="field"><label for="q">Cari nama gudang</label><input class="input" id="q" name="q" maxlength="150" value="{{ request('q') }}"></div>
        <div class="field"><label for="instansi_id">Instansi pengelola</label><select class="select" id="instansi_id" name="instansi_id"><option value="">Semua instansi</option>@foreach ($instansi as $pilihan)<option value="{{ $pilihan->id }}" @selected((string) request('instansi_id') === (string) $pilihan->id)>{{ $pilihan->nama }}</option>@endforeach</select></div>
        <div class="field"><label for="aktif">Status</label><select class="select" id="aktif" name="aktif"><option value="">Semua status</option><option value="1" @selected(request('aktif') === '1')>Aktif</option><option value="0" @selected(request('aktif') === '0')>Nonaktif</option></select></div>
    </div>
    <div class="page-actions"><button class="btn btn-primary" type="submit">Terapkan</button><a class="btn btn-secondary" href="{{ route('admin.gudang.index') }}">Reset</a></div>
</form>
<div class="table-wrap" tabindex="0" role="region" aria-label="Daftar gudang, geser untuk melihat semua kolom">
    <table class="table"><thead><tr><th scope="col">Nama gudang</th><th scope="col">Instansi</th><th scope="col">Wilayah</th><th scope="col">Alamat</th><th scope="col">Latitude, longitude</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead><tbody>
    @forelse ($gudang as $item)
        <tr><td>{{ $item->nama }}</td><td>{{ $item->instansi->nama }}</td><td>{{ $item->kode_wilayah }} · {{ $item->wilayah->nama }}</td><td>{{ $item->alamat }}</td><td>{{ $item->latitude }}, {{ $item->longitude }}</td><td><span class="badge {{ $item->aktif ? 'badge-aman' : 'badge-netral' }}">{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</span></td><td><div class="page-actions">
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.gudang.cakupan.index', $item) }}" aria-label="Atur cakupan {{ $item->nama }}">Cakupan</a>
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.gudang.edit', $item) }}" aria-label="Ubah {{ $item->nama }}">Ubah</a>
            <form method="post" action="{{ route('admin.gudang.status', $item) }}" data-confirm="{{ $item->aktif ? 'Nonaktifkan' : 'Aktifkan' }} gudang {{ $item->nama }}?">@csrf @method('PATCH')<input type="hidden" name="aktif" value="{{ $item->aktif ? '0' : '1' }}"><button class="btn btn-secondary btn-sm" aria-label="{{ $item->aktif ? 'Nonaktifkan' : 'Aktifkan' }} {{ $item->nama }}">{{ $item->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
            <form method="post" action="{{ route('admin.gudang.destroy', $item) }}" data-confirm="Hapus gudang {{ $item->nama }}? Pilih Nonaktifkan jika data masih ingin disimpan.">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" aria-label="Hapus {{ $item->nama }}">Hapus</button></form>
        </div></td></tr>
    @empty
        <tr><td colspan="7">{{ request()->hasAny(['q', 'aktif', 'instansi_id']) ? 'Tidak ada gudang yang sesuai filter.' : 'Belum ada gudang. Tambahkan gudang untuk memulai.' }}</td></tr>
    @endforelse
    </tbody></table>
</div>
@include('admin.partials.pagination', ['paginator' => $gudang, 'label' => 'gudang'])
@endsection
