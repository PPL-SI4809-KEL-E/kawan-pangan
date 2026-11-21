@extends('layouts.admin')
@section('title', 'Master Wilayah')
@section('content')
<div class="page-header">
    <div><h1>Master Wilayah</h1><p class="text-muted">Kelola kode wilayah dan jumlah penduduk untuk peta dan laporan.</p></div>
    <a class="btn btn-primary" href="#impor">Impor wilayah</a>
</div>
<div class="grid grid-4">
    @foreach (\App\Models\Wilayah::LEVEL as $level => $label)
        <div class="card stat-card"><span class="stat-label">{{ $label }}</span><span class="stat-value">{{ number_format($ringkasan[$level] ?? 0, 0, ',', '.') }}</span></div>
    @endforeach
</div>
<section class="card" id="impor" aria-labelledby="judul-impor">
    <h2 id="judul-impor">Impor wilayah Kemendagri</h2>
    <p>Siapkan data resmi dalam CSV UTF-8 dengan pemisah koma atau titik koma. Maksimal 20 MB dan 100.000 wilayah.</p>
    <p class="field-help" id="bantuan-berkas">Kolom: kode, nama, kode_induk, jumlah_penduduk (opsional). Provinsi tanpa induk; wilayah lainnya wajib memiliki induk satu level di atas. Jumlah penduduk kosong mempertahankan nilai yang sudah ada. Seluruh impor dibatalkan jika ada data tidak valid.</p>
    <form action="{{ route('admin.wilayah.impor') }}" method="post" enctype="multipart/form-data" class="wilayah-upload" data-impor>
        @csrf
        <div class="field"><label for="berkas">Berkas wilayah CSV <span class="required">*</span></label><input class="input" id="berkas" name="berkas" type="file" accept=".csv,text/csv" required aria-describedby="bantuan-berkas" @error('berkas') aria-invalid="true" @enderror></div>
        <div class="page-actions"><button class="btn btn-primary" type="submit">Impor CSV</button><a class="btn btn-secondary" href="{{ route('admin.wilayah.template') }}">Unduh template</a></div>
        <p class="field-help" data-proses hidden role="status">Impor sedang diproses. Tunggu hingga hasil ditampilkan.</p>
    </form>
</section>
<section class="stack" aria-labelledby="judul-daftar">
    <h2 id="judul-daftar">Daftar wilayah</h2>
    @if ($induk)
        <div class="alert alert-info">Menampilkan anak wilayah {{ $induk->kode }} · {{ $induk->nama }}. <a href="{{ route('admin.wilayah.index') }}">Lihat semua wilayah</a></div>
    @endif
    <form class="card wilayah-filter" method="get" action="{{ route('admin.wilayah.index') }}">
        @if ($induk)<input type="hidden" name="induk" value="{{ $induk->kode }}">@endif
        <div class="field"><label for="q">Cari kode atau nama wilayah</label><input class="input" id="q" name="q" maxlength="150" value="{{ request('q') }}" placeholder="Cari wilayah…"></div>
        <div class="field"><label for="level">Tingkat wilayah</label><select class="select" name="level" id="level"><option value="">Semua tingkat</option>@foreach (\App\Models\Wilayah::LEVEL as $level => $label)<option value="{{ $level }}" @selected((string) request('level') === (string) $level)>{{ $label }}</option>@endforeach</select></div>
        <div class="page-actions"><button class="btn btn-primary" type="submit">Terapkan</button><a class="btn btn-secondary" href="{{ route('admin.wilayah.index') }}">Reset</a></div>
    </form>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Tabel master wilayah, geser untuk melihat semua kolom">
        <table class="table">
            <thead><tr><th scope="col">Kode</th><th scope="col">Nama wilayah</th><th scope="col">Tingkat</th><th scope="col">Induk</th><th scope="col" class="num">Penduduk (jiwa)</th><th scope="col">Aksi</th></tr></thead>
            <tbody>
            @forelse ($wilayah as $item)
                <tr>
                    <td class="wilayah-kode">{{ $item->kode }}</td><td>{{ $item->nama }}@if ($item->is_contoh) <span class="badge badge-netral">Data contoh</span>@endif</td><td>{{ \App\Models\Wilayah::LEVEL[$item->level] }}</td>
                    <td>{{ $item->induk?->nama ?? '—' }}</td><td class="num">{{ $item->jumlah_penduduk === null ? 'Belum diisi' : number_format($item->jumlah_penduduk, 0, ',', '.') }}</td>
                    <td><div class="page-actions"><a class="btn btn-secondary btn-sm" href="{{ route('admin.wilayah.edit', $item) }}" aria-label="Ubah penduduk {{ $item->nama }}">Ubah penduduk</a>@if ($item->level < 4)<a class="btn btn-ghost btn-sm" href="{{ route('admin.wilayah.index', ['induk' => $item->kode]) }}" aria-label="Lihat anak wilayah {{ $item->nama }}">Lihat anak</a>@endif</div></td>
                </tr>
            @empty
                <tr><td colspan="6">{{ request()->hasAny(['q', 'level', 'induk']) ? 'Tidak ada wilayah yang sesuai filter.' : 'Belum ada master wilayah. Unggah CSV untuk memulai.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <nav class="wilayah-pagination" aria-label="Navigasi halaman wilayah">
        <span class="text-muted">{{ number_format($wilayah->total(), 0, ',', '.') }} wilayah · Halaman {{ $wilayah->currentPage() }} dari {{ $wilayah->lastPage() }}</span>
        <div class="page-actions">@if ($wilayah->previousPageUrl())<a class="btn btn-secondary" rel="prev" href="{{ $wilayah->previousPageUrl() }}">Sebelumnya</a>@endif @if ($wilayah->nextPageUrl())<a class="btn btn-secondary" rel="next" href="{{ $wilayah->nextPageUrl() }}">Berikutnya</a>@endif</div>
    </nav>
</section>
@endsection
