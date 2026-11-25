@php
    $isSuperadmin = auth()->user()->isSuperadmin() && request()->routeIs('admin.*');
    $layout = $isSuperadmin ? 'layouts.admin' : 'layouts.panel';
    $routePrefix = $isSuperadmin ? 'admin.gudang.cakupan' : 'panel.instansi.gudang.cakupan';
    $backRoute = $isSuperadmin ? route('admin.gudang.index') : route('panel.instansi');
@endphp

@extends($layout)

@section('title', 'Atur Cakupan Gudang · ' . $gudang->nama)

@section('content')
<div class="stack stack-md">
    {{-- Header Layar M-12 --}}
    <div class="page-header">
        <div>
            <div class="breadcrumb-bar">
                <a href="{{ $backRoute }}" class="btn btn-ghost btn-sm btn-back">← Kembali</a>
                <span class="text-muted">·</span>
                <span class="text-muted text-xs mono">Layar M-12 (PBI-10)</span>
            </div>
            <h1>Atur Cakupan Wilayah Gudang</h1>
            <p>
                Menetapkan kecamatan yang dilayani gudang distribusi pangan agar batas layanan dan gudang utama terdefinisi jelas.
            </p>
        </div>
        <div class="cluster">
            <span class="badge {{ $gudang->aktif ? 'badge-aman' : 'badge-netral' }}">
                {{ $gudang->aktif ? 'Gudang Aktif' : 'Gudang Nonaktif' }}
            </span>
        </div>
    </div>

    {{-- Kartu Ringkasan Informasi Gudang --}}
    <div class="card meta-grid">
        <div class="meta-item">
            <span class="meta-label">Nama Gudang</span>
            <div class="meta-value">{{ $gudang->nama }}</div>
            <div class="meta-sub">Kode: <code class="num">{{ $gudang->kode_gudang ?? 'GDG-' . str_pad($gudang->id, 3, '0', STR_PAD_LEFT) }}</code></div>
        </div>
        <div class="meta-item">
            <span class="meta-label">Instansi Pengelola</span>
            <div class="meta-value">{{ $gudang->instansi->nama }}</div>
            <div class="meta-sub">{{ $gudang->instansi->jenis ?? 'Badan Resmi Pemerintah' }}</div>
        </div>
        <div class="meta-item">
            <span class="meta-label">Lokasi Gudang</span>
            <div class="meta-value">{{ $gudang->wilayah ? $gudang->wilayah->nama : '-' }}</div>
            <div class="meta-sub">{{ $gudang->alamat }}</div>
        </div>
        <div class="meta-item">
            <span class="meta-label">Statistik Cakupan</span>
            <div class="meta-value-lg">
                {{ $daftarCakupan->count() }} <span class="meta-sub">kecamatan dilayani</span>
            </div>
            <div class="meta-sub">
                {{ $daftarCakupan->where('is_utama', true)->count() }} sebagai gudang utama
            </div>
        </div>
    </div>

    {{-- Grid Dua Kolom: Daftar Cakupan Saat Ini & Form Tambah Cakupan --}}
    <div class="grid grid-main-aside grid-align-start">
        {{-- Kolom Kiri: Tabel Kecamatan yang Dilayani --}}
        <div class="stack">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Kecamatan yang Dilayani</h2>
                    <p class="text-muted text-xs">
                        Kecamatan yang menerima pasokan distribusi dari gudang ini (PSKE-49).
                    </p>
                </div>
                <span class="badge badge-info num">{{ $daftarCakupan->count() }} Terdaftar</span>
            </div>

            <div class="table-wrap" tabindex="0" role="region" aria-label="Daftar cakupan kecamatan">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col" class="col-kode">Kode</th>
                            <th scope="col">Nama Kecamatan</th>
                            <th scope="col" class="col-status">Status Peran</th>
                            <th scope="col" class="actions col-aksi">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftarCakupan as $cakupan)
                            <tr>
                                <td>
                                    <code class="num">{{ $cakupan->kode_kecamatan }}</code>
                                </td>
                                <td>
                                    <strong>{{ $cakupan->kecamatan->nama ?? $cakupan->kode_kecamatan }}</strong>
                                    @if ($cakupan->kecamatan && $cakupan->kecamatan->jumlah_penduduk)
                                        <div class="text-muted text-xs num">
                                            Populasi: {{ number_format($cakupan->kecamatan->jumlah_penduduk, 0, ',', '.') }} jiwa
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($cakupan->is_utama)
                                        <span class="badge badge-aman" title="Gudang utama untuk kecamatan ini">
                                            Gudang Utama
                                        </span>
                                    @else
                                        <span class="badge badge-info" title="Gudang pendukung / cadangan">
                                            Pendukung
                                        </span>
                                    @endif
                                </td>
                                <td class="actions">
                                    <div class="table-actions">
                                        {{-- Toggle Status Gudang Utama (PSKE-50) --}}
                                        <form method="POST" action="{{ route($routePrefix . '.toggle-utama', [$gudang, $cakupan]) }}">
                                            @csrf
                                            @method('PATCH')
                                            @if ($cakupan->is_utama)
                                                <button type="submit" class="btn btn-ghost btn-sm" title="Lepas status gudang utama">
                                                    Lepas Utama
                                                </button>
                                            @else
                                                <button type="submit" class="btn btn-secondary btn-sm" title="Jadikan gudang utama untuk kecamatan ini">
                                                    Jadikan Utama
                                                </button>
                                            @endif
                                        </form>

                                        {{-- Hapus Cakupan --}}
                                        <form method="POST" action="{{ route($routePrefix . '.destroy', [$gudang, $cakupan]) }}" data-confirm="Hapus cakupan kecamatan {{ $cakupan->kecamatan?->nama }} dari gudang ini?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" aria-label="Hapus cakupan {{ $cakupan->kecamatan?->nama }}">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-empty">
                                    Belum ada kecamatan yang ditugaskan ke gudang ini. Tambahkan melalui form di samping.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Kolom Kanan: Form Tambah Cakupan Kecamatan --}}
        <div class="card stack">
            <div>
                <h3 class="card-title">Tambah Cakupan</h3>
                <p class="text-muted text-xs">
                    Pilih kecamatan untuk dihubungkan ke gudang {{ $gudang->nama }}.
                </p>
            </div>

            <form method="POST" action="{{ route($routePrefix . '.store', $gudang) }}" class="stack stack-sm">
                @csrf
                <div class="field @error('kode_kecamatan') has-error @enderror">
                    <label for="kode_kecamatan">Pilih Kecamatan <span class="required">*</span></label>
                    <select id="kode_kecamatan" name="kode_kecamatan" class="select" required>
                        <option value="">-- Pilih Kecamatan --</option>
                        @foreach ($pilihanKecamatan as $kec)
                            <option value="{{ $kec->kode }}" @selected(old('kode_kecamatan') === $kec->kode)>
                                {{ $kec->nama }} ({{ $kec->kode }})
                            </option>
                        @endforeach
                    </select>
                    @error('kode_kecamatan')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <label class="check-stack" for="is_utama">
                    <input type="checkbox" id="is_utama" name="is_utama" value="1" @checked(old('is_utama'))>
                    <span>
                        <strong>Tetapkan sebagai Gudang Utama</strong><br>
                        <span class="text-muted">Hanya boleh ada 1 gudang utama per kecamatan (PSKE-50).</span>
                    </span>
                </label>
                @error('is_utama')
                    <div class="alert alert-error text-xs">
                        {{ $message }}
                    </div>
                @enderror

                <button type="submit" class="btn btn-primary btn-block">
                    Tambahkan ke Cakupan
                </button>
            </form>
        </div>
    </div>

    {{-- PSKE-51: Penanda Kecamatan Tanpa Cakupan --}}
    <div class="card card-alert-kritis">
        <div class="card-head">
            <div class="stack stack-sm">
                <div class="cluster">
                    <span class="badge badge-kritis">Perhatian Operasional</span>
                    <h2 class="card-title">Kecamatan Tanpa Cakupan Gudang</h2>
                </div>
                <p class="text-muted text-xs">
                    Kecamatan di wilayah {{ $wilayahKabupaten ? $wilayahKabupaten->nama : 'operasional' }} yang belum dilayani oleh gudang mana pun. Segera tetapkan cakupan agar ketahanan pangan desa dapat dipantau (PSKE-51).
                </p>
            </div>
            <span class="badge badge-kritis num">
                {{ $kecamatanTanpaCakupan->count() }} Kecamatan Belum Tercover
            </span>
        </div>

        @if ($kecamatanTanpaCakupan->isNotEmpty())
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col" class="col-kode">Kode</th>
                            <th scope="col">Nama Kecamatan</th>
                            <th scope="col">Status Cakupan</th>
                            <th scope="col" class="actions col-aksi">Aksi Cepat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($kecamatanTanpaCakupan as $kecTanpa)
                            <tr>
                                <td><code class="num">{{ $kecTanpa->kode }}</code></td>
                                <td>
                                    <strong>{{ $kecTanpa->nama }}</strong>
                                    @if ($kecTanpa->jumlah_penduduk)
                                        <span class="text-muted text-xs num">
                                            ({{ number_format($kecTanpa->jumlah_penduduk, 0, ',', '.') }} jiwa)
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-kritis" title="Belum memiliki gudang distributor logistik pangan">
                                        Tanpa Cakupan
                                    </span>
                                </td>
                                <td class="actions">
                                    <form method="POST" action="{{ route($routePrefix . '.store', $gudang) }}">
                                        @csrf
                                        <input type="hidden" name="kode_kecamatan" value="{{ $kecTanpa->kode }}">
                                        <input type="hidden" name="is_utama" value="1">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Jadikan gudang ini sebagai gudang utama untuk {{ $kecTanpa->nama }}">
                                            + Layani (Gudang Utama)
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-success">
                Semua kecamatan di wilayah ini sudah terhubung dengan gudang distribusi logistik pangan.
            </div>
        @endif
    </div>
</div>
@endsection
