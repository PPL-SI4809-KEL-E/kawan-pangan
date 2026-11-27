{{-- PSKE-107 · Form catat mutasi stok oleh pengelola instansi --}}
@extends('layouts.panel')

@section('title', 'Catat Mutasi Stok')

@section('content')
<div class="page-header">
    <div>
        <a class="btn btn-ghost btn-sm btn-back" href="{{ route('panel.instansi.stok.index') }}">
            <svg class="icon icon-sm" aria-hidden="true"><use href="#i-arrow-left"/></svg>Daftar stok
        </a>
        <h1>Catat mutasi stok</h1>
        <p>Setiap mutasi langsung mengubah saldo gudang dan estimasi habis di daftar stok.</p>
    </div>
</div>

@if ($pilihanGudang->isEmpty())
    <div class="sheet"><div class="sheet-section">
        <div class="state">
            <span class="state-icon" aria-hidden="true"><svg class="icon"><use href="#i-warehouse"/></svg></span>
            <div class="state-content">
                <p class="state-title">Instansi Anda belum punya gudang</p>
                <p class="state-text">Minta Superadmin mendaftarkan gudang instansi Anda dulu, lalu mutasi bisa dicatat di sini.</p>
            </div>
        </div>
    </div></div>
@else
<form class="sheet mutasi-form" method="POST" action="{{ route('panel.instansi.stok.mutasi.store') }}" novalidate>
    @csrf
    <input type="hidden" name="ref_operasi" value="{{ old('ref_operasi', $refOperasi) }}">

    <div class="sheet-section">
        <div class="grid grid-2">
            <div class="field @error('gudang_id') has-error @enderror">
                <label for="gudang_id">Gudang <span class="required">*</span></label>
                <select class="select" id="gudang_id" name="gudang_id" required>
                    <option value="">Pilih gudang</option>
                    @foreach ($pilihanGudang as $gdg)
                        <option value="{{ $gdg->id }}" @selected((string) old('gudang_id', $pilih['gudang']) === (string) $gdg->id)>{{ $gdg->nama }}</option>
                    @endforeach
                </select>
                @error('gudang_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field @error('komoditas_id') has-error @enderror">
                <label for="komoditas_id">Komoditas <span class="required">*</span></label>
                <select class="select" id="komoditas_id" name="komoditas_id" required>
                    <option value="" data-satuan="">Pilih komoditas</option>
                    @foreach ($pilihanKomoditas as $kmd)
                        <option value="{{ $kmd->id }}" data-satuan="{{ $kmd->satuan }}" @selected((string) old('komoditas_id', $pilih['komoditas']) === (string) $kmd->id)>{{ $kmd->nama }} ({{ $kmd->satuan }})</option>
                    @endforeach
                </select>
                @error('komoditas_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    <fieldset class="sheet-section mutasi-jenis @error('jenis') has-error @enderror">
        <legend class="mutasi-legend">Jenis mutasi <span class="required">*</span></legend>
        <div class="mutasi-pilihan">
            @foreach ($jenisMutasi as $nilai => $jenis)
                <label class="mutasi-opsi">
                    <input type="radio" name="jenis" value="{{ $nilai }}" @checked(old('jenis', 'PENERIMAAN') === $nilai)>
                    <span>
                        <strong>{{ $jenis['label'] }}</strong>
                        <small>{{ $jenis['bantuan'] }}</small>
                    </span>
                </label>
            @endforeach
        </div>
        @error('jenis')<span class="field-error">{{ $message }}</span>@enderror
    </fieldset>

    <div class="sheet-section">
        <div class="grid grid-2">
            <div class="field @error('jumlah') has-error @enderror">
                <label for="jumlah" id="label-jumlah">Jumlah <span class="required">*</span></label>
                <div class="qty">
                    <input class="input" id="jumlah" name="jumlah" type="number" inputmode="decimal" min="0" step="0.001" value="{{ old('jumlah') }}" required aria-describedby="bantuan-jumlah">
                    <span class="qty-unit" id="satuan-jumlah"></span>
                </div>
                <span class="field-help" id="bantuan-jumlah">Boleh desimal sampai 3 angka, misalnya 12,5.</span>
                @error('jumlah')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field @error('keterangan') has-error @enderror">
                <label for="keterangan">Catatan</label>
                <input class="input" id="keterangan" name="keterangan" maxlength="255" value="{{ old('keterangan') }}" placeholder="Misalnya nomor surat jalan atau tujuan penyaluran">
                @error('keterangan')<span class="field-error">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    <div class="sheet-section mutasi-aksi">
        <button class="btn btn-primary" type="submit">Simpan mutasi</button>
        <a class="btn btn-ghost" href="{{ route('panel.instansi.stok.index') }}">Batal</a>
    </div>
</form>
@endif
@endsection

@push('head')
<style>
    .mutasi-jenis { margin: 0; border: 0; border-top: 1px solid var(--color-border); min-width: 0; }
    .mutasi-legend { float: left; width: 100%; margin-bottom: var(--space-3); font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); }
    .mutasi-pilihan { clear: both; display: grid; gap: var(--space-2); }
    .mutasi-opsi { display: flex; gap: var(--space-3); align-items: flex-start; padding: var(--space-3) var(--space-4); border: 1px solid var(--color-border-strong); border-radius: var(--radius-md); cursor: pointer; transition: border-color var(--duration) ease, box-shadow var(--duration) ease; }
    .mutasi-opsi input { margin-top: 3px; flex: none; }
    .mutasi-opsi span { display: grid; gap: 2px; font-size: var(--font-size-sm); }
    .mutasi-opsi small { font-size: var(--font-size-xs); color: var(--color-text-muted); }
    .mutasi-opsi:has(input:checked) { border-color: var(--color-text); box-shadow: inset 0 0 0 1px var(--color-text); }
    .mutasi-opsi:has(input:focus-visible) { outline: 2px solid var(--color-primary); outline-offset: 2px; }
    .mutasi-aksi { display: flex; flex-wrap: wrap; gap: var(--space-2); }
    .mutasi-form .field:last-child { margin-bottom: 0; }
    @media (min-width: 768px) { .mutasi-pilihan { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .mutasi-pilihan { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
</style>
@endpush

@push('scripts')
<script>
    // Bantuan tampilan saja; semua validasi tetap di server.
    (() => {
        const komoditas = document.getElementById('komoditas_id');
        const satuan = document.getElementById('satuan-jumlah');
        const label = document.getElementById('label-jumlah');
        const bantuan = document.getElementById('bantuan-jumlah');
        if (!komoditas) return;
        const perbaruiSatuan = () => { satuan.textContent = komoditas.selectedOptions[0]?.dataset.satuan || ''; };
        const perbaruiJenis = () => {
            const opname = document.querySelector('input[name="jenis"]:checked')?.value === 'PENYESUAIAN';
            label.firstChild.textContent = opname ? 'Jumlah fisik hasil hitung ' : 'Jumlah ';
            bantuan.textContent = opname
                ? 'Isi total stok yang benar-benar ada di gudang sekarang, bukan selisihnya.'
                : 'Boleh desimal sampai 3 angka, misalnya 12,5.';
        };
        komoditas.addEventListener('change', perbaruiSatuan);
        document.querySelectorAll('input[name="jenis"]').forEach((r) => r.addEventListener('change', perbaruiJenis));
        perbaruiSatuan();
        perbaruiJenis();
        // Cegah klik ganda; server tetap menolak lewat ref_operasi.
        document.querySelector('.mutasi-form')?.addEventListener('submit', (e) => {
            const tombol = e.target.querySelector('button[type="submit"]');
            if (tombol) { tombol.disabled = true; tombol.textContent = 'Menyimpan…'; }
        });
    })();
</script>
@endpush
