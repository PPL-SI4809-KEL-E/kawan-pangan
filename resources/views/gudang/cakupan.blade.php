@php
    $isSuperadmin = auth()->user()->isSuperadmin() && request()->routeIs('admin.*');
    $layout = $isSuperadmin ? 'layouts.admin' : 'layouts.panel';
    $routePrefix = $isSuperadmin ? 'admin.gudang.cakupan' : 'panel.instansi.gudang.cakupan';
    $backRoute = $isSuperadmin ? route('admin.gudang.index') : route('panel.instansi.index');
@endphp

@extends($layout)

@section('title', 'Atur Cakupan Gudang · ' . $gudang->nama)

@section('content')
<div class="stack" style="gap: var(--space-6);">
    {{-- Header Layar M-12 --}}
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-1);">
                <a href="{{ $backRoute }}" class="btn btn-ghost btn-sm" style="padding-left: 0;">← Kembali</a>
                <span class="text-muted">·</span>
                <span class="text-muted" style="font-size: var(--font-size-xs); font-family: monospace;">Layar M-12 (PBI-10)</span>
            </div>
            <h1>Atur Cakupan Wilayah Gudang</h1>
            <p class="text-muted" style="margin: 0;">
                Menetapkan kecamatan yang dilayani gudang distribusi pangan agar batas layanan dan gudang utama terdefinisi jelas.
            </p>
        </div>
        <div style="display: flex; align-items: center; gap: var(--space-2);">
            <span class="badge {{ $gudang->aktif ? 'badge-aman' : 'badge-netral' }}">
                {{ $gudang->aktif ? 'Gudang Aktif' : 'Gudang Nonaktif' }}
            </span>
        </div>
    </div>

    {{-- Kartu Ringkasan Informasi Gudang --}}
    <div class="card" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4); background: var(--color-surface);">
        <div>
            <span class="text-muted" style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.05em; font-weight: var(--font-weight-semibold);">Nama Gudang</span>
            <div style="font-size: var(--font-size-md); font-weight: var(--font-weight-bold); margin-top: var(--space-1);">{{ $gudang->nama }}</div>
            <div class="text-muted" style="font-size: var(--font-size-xs);">Kode: <code>{{ $gudang->kode_gudang ?? 'GDG-' . str_pad($gudang->id, 3, '0', STR_PAD_LEFT) }}</code></div>
        </div>
        <div>
            <span class="text-muted" style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.05em; font-weight: var(--font-weight-semibold);">Instansi Pengelola</span>
            <div style="font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-top: var(--space-1);">{{ $gudang->instansi->nama }}</div>
            <div class="text-muted" style="font-size: var(--font-size-xs);">{{ $gudang->instansi->jenis ?? 'Badan Resmi Pemerintah' }}</div>
        </div>
        <div>
            <span class="text-muted" style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.05em; font-weight: var(--font-weight-semibold);">Lokasi Gudang</span>
            <div style="font-size: var(--font-size-md); margin-top: var(--space-1);">{{ $gudang->wilayah ? $gudang->wilayah->nama : '-' }}</div>
            <div class="text-muted" style="font-size: var(--font-size-xs);">{{ $gudang->alamat }}</div>
        </div>
        <div>
            <span class="text-muted" style="font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.05em; font-weight: var(--font-weight-semibold);">Statistik Cakupan</span>
            <div style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); margin-top: var(--space-1); font-variant-numeric: tabular-nums;">
                {{ $daftarCakupan->count() }} <span style="font-size: var(--font-size-xs); font-weight: normal; color: var(--color-text-muted);">kecamatan dilayani</span>
            </div>
            <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);">
                {{ $daftarCakupan->where('is_utama', true)->count() }} sebagai gudang utama
            </div>
        </div>
    </div>

    {{-- Grid Dua Kolom: Daftar Cakupan Saat Ini & Form Tambah Cakupan --}}
    <div style="display: grid; grid-template-columns: 1fr 340px; gap: var(--space-6); align-items: start;">
        {{-- Kolom Kiri: Tabel Kecamatan yang Dilayani --}}
        <div class="stack" style="gap: var(--space-4);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2>Kecamatan yang Dilayani</h2>
                    <p class="text-muted" style="font-size: var(--font-size-xs); margin: 0;">
                        Kecamatan yang menerima pasokan distribusi dari gudang ini (PSKE-49).
                    </p>
                </div>
                <span class="badge badge-info">{{ $daftarCakupan->count() }} Terdaftar</span>
            </div>

            <div class="table-wrap" tabindex="0" role="region" aria-label="Daftar cakupan kecamatan">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 120px;">Kode</th>
                            <th scope="col">Nama Kecamatan</th>
                            <th scope="col" style="width: 140px;">Status Peran</th>
                            <th scope="col" style="width: 160px; text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftarCakupan as $cakupan)
                            <tr>
                                <td>
                                    <code style="font-variant-numeric: tabular-nums;">{{ $cakupan->kode_kecamatan }}</code>
                                </td>
                                <td>
                                    <strong>{{ $cakupan->kecamatan->nama ?? $cakupan->kode_kecamatan }}</strong>
                                    @if ($cakupan->kecamatan && $cakupan->kecamatan->jumlah_penduduk)
                                        <div class="text-muted" style="font-size: var(--font-size-xs); font-variant-numeric: tabular-nums;">
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
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: var(--space-2); justify-content: flex-end;">
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
                                        <form method="POST" action="{{ route($routePrefix . '.destroy', [$gudang, $cakupan]) }}" onsubmit="return confirm('Hapus cakupan kecamatan {{ $cakupan->kecamatan?->nama }} dari gudang ini?');">
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
                                <td colspan="4" style="text-align: center; padding: var(--space-6); color: var(--color-text-muted);">
                                    Belum ada kecamatan yang ditugaskan ke gudang ini. Tambahkan melalui form di samping.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Kolom Kanan: Form Tambah Cakupan Kecamatan --}}
        <div class="card stack" style="gap: var(--space-4);">
            <div>
                <h3>Tambah Cakupan</h3>
                <p class="text-muted" style="font-size: var(--font-size-xs); margin: 0;">
                    Pilih kecamatan untuk dihubungkan ke gudang {{ $gudang->nama }}.
                </p>
            </div>

            <form method="POST" action="{{ route($routePrefix . '.store', $gudang) }}" class="stack" style="gap: var(--space-3);">
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

                <div style="display: flex; align-items: flex-start; gap: var(--space-2); margin-top: var(--space-1);">
                    <input type="checkbox" id="is_utama" name="is_utama" value="1" @checked(old('is_utama')) style="margin-top: 3px; cursor: pointer;">
                    <label for="is_utama" style="font-size: var(--font-size-xs); line-height: 1.4; cursor: pointer;">
                        <strong>Tetapkan sebagai Gudang Utama</strong><br>
                        <span class="text-muted">Hanya boleh ada 1 gudang utama per kecamatan (PSKE-50).</span>
                    </label>
                </div>
                @error('is_utama')
                    <div class="alert alert-error" style="font-size: var(--font-size-xs); padding: var(--space-2);">
                        {{ $message }}
                    </div>
                @enderror

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: var(--space-2);">
                    Tambahkan ke Cakupan
                </button>
            </form>
        </div>
    </div>

    {{-- PSKE-51: Penanda Kecamatan Tanpa Cakupan --}}
    <div class="card stack" style="gap: var(--space-4); border-left: 4px solid var(--color-kritis);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--space-2);">
            <div>
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <span class="badge badge-kritis">Perhatian Operasional</span>
                    <h2 style="margin: 0; font-size: var(--font-size-lg);">Kecamatan Tanpa Cakupan Gudang</h2>
                </div>
                <p class="text-muted" style="font-size: var(--font-size-xs); margin-top: var(--space-1); margin-bottom: 0;">
                    Kecamatan di wilayah {{ $wilayahKabupaten ? $wilayahKabupaten->nama : 'operasional' }} yang belum dilayani oleh gudang mana pun. Segera tetapkan cakupan agar ketahanan pangan desa dapat dipantau (PSKE-51).
                </p>
            </div>
            <span class="badge badge-kritis" style="font-variant-numeric: tabular-nums;">
                {{ $kecamatanTanpaCakupan->count() }} Kecamatan Belum Tercover
            </span>
        </div>

        @if ($kecamatanTanpaCakupan->isNotEmpty())
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 120px;">Kode</th>
                            <th scope="col">Nama Kecamatan</th>
                            <th scope="col">Status Cakupan</th>
                            <th scope="col" style="width: 180px; text-align: right;">Aksi Cepat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($kecamatanTanpaCakupan as $kecTanpa)
                            <tr>
                                <td><code style="font-variant-numeric: tabular-nums;">{{ $kecTanpa->kode }}</code></td>
                                <td>
                                    <strong>{{ $kecTanpa->nama }}</strong>
                                    @if ($kecTanpa->jumlah_penduduk)
                                        <span class="text-muted" style="font-size: var(--font-size-xs); margin-left: var(--space-2); font-variant-numeric: tabular-nums;">
                                            ({{ number_format($kecTanpa->jumlah_penduduk, 0, ',', '.') }} jiwa)
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-kritis" title="Belum memiliki gudang distributor logistik pangan">
                                        Tanpa Cakupan
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <form method="POST" action="{{ route($routePrefix . '.store', $gudang) }}" style="display: inline;">
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
            <div class="alert alert-success" style="margin: 0;">
                Semua kecamatan di wilayah ini sudah terhubung dengan gudang distribusi logistik pangan.
            </div>
        @endif
    </div>
</div>
@endsection
