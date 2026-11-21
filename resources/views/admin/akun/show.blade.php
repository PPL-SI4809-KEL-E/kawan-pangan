{{-- PBI-06 · M-16 Rincian & Formulir Verifikasi Akun (PSKE-17) --}}
@extends('layouts.admin')

@section('title', 'Verifikasi Akun · ' . $user->name)

@section('content')
<div class="stack">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
        <div>
            <a href="{{ route('admin.akun.index') }}" class="btn btn-ghost btn-sm" style="margin-bottom: var(--space-2);">
                &larr; Kembali ke Daftar Akun
            </a>
            <h1>Rincian Akun: {{ $user->name }}</h1>
            <p class="text-muted">Periksa dokumen surat tugas dan tentukan penugasan wilayah atau instansi.</p>
        </div>
        <div>
            <span class="badge {{ $user->status->badge() }}" style="font-size: var(--font-size-sm); padding: var(--space-1) var(--space-3);">
                Status: {{ $user->status->label() }}
            </span>
        </div>
    </div>

    {{-- Kartu Profil & Surat Tugas --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: var(--space-4);">
        <div class="card">
            <h2 class="card-title">Informasi Pendaftar</h2>
            <table style="width: 100%; border-collapse: collapse; font-size: var(--font-size-sm);">
                <tr>
                    <td class="text-muted" style="padding: var(--space-2) 0; width: 40%;">Nama Lengkap</td>
                    <td style="padding: var(--space-2) 0;"><strong>{{ $user->name }}</strong></td>
                </tr>
                <tr>
                    <td class="text-muted" style="padding: var(--space-2) 0;">Email</td>
                    <td style="padding: var(--space-2) 0;">{{ $user->email }}</td>
                </tr>
                <tr>
                    <td class="text-muted" style="padding: var(--space-2) 0;">Nomor HP</td>
                    <td style="padding: var(--space-2) 0;">{{ $user->no_hp ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted" style="padding: var(--space-2) 0;">Jenis Peran</td>
                    <td style="padding: var(--space-2) 0;">
                        <span class="badge {{ $user->isKepalaWilayah() ? 'badge-info' : 'badge-waspada' }}">
                            {{ $user->role->label() }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted" style="padding: var(--space-2) 0;">Terdaftar Pada</td>
                    <td style="padding: var(--space-2) 0;">{{ $user->created_at?->format('d M Y, H:i') ?? '-' }}</td>
                </tr>
                @if ($user->diverifikasi_pada)
                    <tr>
                        <td class="text-muted" style="padding: var(--space-2) 0;">Diverifikasi Pada</td>
                        <td style="padding: var(--space-2) 0;">
                            {{ $user->diverifikasi_pada->format('d M Y, H:i') }}
                            @if ($user->verifikator)
                                <div class="text-muted" style="font-size: var(--font-size-xs);">Oleh: {{ $user->verifikator->name }}</div>
                            @endif
                        </td>
                    </tr>
                @endif
                @if ($user->alasan_penolakan)
                    <tr>
                        <td class="text-muted" style="padding: var(--space-2) 0;">Alasan Penolakan</td>
                        <td style="padding: var(--space-2) 0; color: var(--color-kritis);">
                            <em>"{{ $user->alasan_penolakan }}"</em>
                        </td>
                    </tr>
                @endif
            </table>
        </div>

        <div class="card">
            <h2 class="card-title">Dokumen Pendukung</h2>
            <p class="text-muted" style="font-size: var(--font-size-sm); margin-bottom: var(--space-3);">
                Surat tugas dinas yang diunggah saat registrasi publik (tersimpan aman di disk privat).
            </p>
            @if ($suratTugas)
                <div style="background: var(--color-netral-bg); padding: var(--space-3); border-radius: var(--radius-md); margin-bottom: var(--space-4);">
                    <div style="font-weight: var(--font-weight-semibold); word-break: break-all;">
                        📄 {{ $suratTugas->nama_file }}
                    </div>
                    <div class="text-muted" style="font-size: var(--font-size-xs); margin-top: 4px;">
                        Tipe: {{ $suratTugas->mime }} • Ukuran: {{ number_format($suratTugas->ukuran / 1024, 1) }} KB
                    </div>
                </div>
                <a href="{{ route('admin.akun.unduh-surat-tugas', $user) }}" class="btn btn-secondary">
                    ⬇️ Unduh Surat Tugas
                </a>
            @else
                <div class="alert alert-error">
                    Tidak ditemukan berkas surat tugas untuk pendaftar ini.
                </div>
            @endif
        </div>
    </div>

    @if ($user->status === \App\Enums\StatusAkun::Menunggu)
        {{-- PSKE-35: Formulir Aktivasi & Penugasan --}}
        <div class="card" style="border-top: 4px solid var(--color-aman);">
            <h2 class="card-title">Aktivasi Akun & Tetapkan Penugasan (PSKE-35)</h2>
            <p class="text-muted" style="font-size: var(--font-size-sm); margin-bottom: var(--space-4);">
                Aktivasi wajib disertai penugasan wilayah atau instansi agar akun memiliki hak akses data yang sah.
            </p>

            <form method="POST" action="{{ route('admin.akun.aktivasi', $user) }}">
                @csrf

                @if ($user->isKepalaWilayah())
                    <div class="field @error('kode_wilayah') has-error @enderror">
                        <label for="kode_wilayah">Pilih Wilayah Penugasan (Kecamatan atau Desa) <span class="required">*</span></label>

                        {{-- Pencarian Wilayah Live / Filter --}}
                        <div style="margin-bottom: var(--space-2);">
                            <input 
                                type="text" 
                                id="cari-wilayah-input" 
                                class="input" 
                                placeholder="Ketik minimal 2 karakter untuk mencari wilayah..."
                                autocomplete="off"
                            >
                        </div>

                        <select name="kode_wilayah" id="kode_wilayah" class="select" required>
                            <option value="">-- Pilih Wilayah Kemendagri --</option>
                            @if ($wilayahPendaftar)
                                <optgroup label="Wilayah Pilihan Pendaftar">
                                    <option value="{{ $wilayahPendaftar->kode }}" @selected(old('kode_wilayah', $user->kode_wilayah) === $wilayahPendaftar->kode)>
                                        {{ $wilayahPendaftar->kode }} — {{ $wilayahPendaftar->nama }} ({{ $wilayahPendaftar->level === 3 ? 'Kecamatan' : 'Desa/Kelurahan' }}) [Pilihan Pendaftar]
                                    </option>
                                </optgroup>
                            @endif
                            <optgroup label="Daftar Wilayah" id="optgroup-wilayah">
                                @foreach ($daftarWilayah as $wilayah)
                                    @if (!$wilayahPendaftar || $wilayah->kode !== $wilayahPendaftar->kode)
                                        <option value="{{ $wilayah->kode }}" @selected(old('kode_wilayah', $user->kode_wilayah) === $wilayah->kode)>
                                            {{ $wilayah->kode }} — {{ $wilayah->nama }} ({{ $wilayah->level === 3 ? 'Kecamatan' : 'Desa/Kelurahan' }})
                                        </option>
                                    @endif
                                @endforeach
                            </optgroup>
                        </select>
                        @error('kode_wilayah')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                        <span class="field-help">Pilih wilayah kecamatan atau desa yang sah sesuai surat tugas pendaftar.</span>
                    </div>
                @elseif ($user->isBadanResmi())
                    <div class="field @error('instansi_id') has-error @enderror">
                        <label for="instansi_id">Instansi Penugasan <span class="required">*</span></label>
                        <select name="instansi_id" id="instansi_id" class="select" required>
                            <option value="">-- Pilih Instansi Pangan --</option>
                            @foreach ($daftarInstansi as $instansi)
                                <option value="{{ $instansi->id }}" @selected(old('instansi_id', $user->instansi_id) == $instansi->id)>
                                    {{ $instansi->nama }}
                                </option>
                            @endforeach
                        </select>
                        @error('instansi_id')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field @error('jabatan') has-error @enderror">
                        <label>Peran Jabatan di Instansi <span class="required">*</span></label>
                        <div style="display: flex; gap: var(--space-4); margin-top: var(--space-1);">
                            <label style="display: inline-flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                                <input 
                                    type="radio" 
                                    name="jabatan" 
                                    value="{{ \App\Models\User::JABATAN_PENGELOLA }}" 
                                    @checked(old('jabatan', $user->getAttribute('jabatan')) === \App\Models\User::JABATAN_PENGELOLA)
                                >
                                <span><strong>Pengelola Instansi</strong> (kelola stok & gudang)</span>
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                                <input 
                                    type="radio" 
                                    name="jabatan" 
                                    value="{{ \App\Models\User::JABATAN_PETUGAS }}" 
                                    @checked(old('jabatan', $user->getAttribute('jabatan')) === \App\Models\User::JABATAN_PETUGAS)
                                >
                                <span><strong>Petugas Lapangan</strong> (pengantaran & distribusi)</span>
                            </label>
                        </div>
                        @error('jabatan')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                @endif

                <div style="margin-top: var(--space-4);">
                    <button type="submit" class="btn btn-primary">
                        ✓ Verifikasi & Aktifkan Akun
                    </button>
                </div>
            </form>
        </div>

        {{-- PSKE-36: Formulir Penolakan --}}
        <div class="card" style="border-top: 4px solid var(--color-kritis);">
            <h2 class="card-title">Tolak Pendaftaran (PSKE-36)</h2>
            <p class="text-muted" style="font-size: var(--font-size-sm); margin-bottom: var(--space-3);">
                Penolakan wajib beralasan. Alasan akan ditampilkan ke pendaftar saat mencoba login.
            </p>
            <form method="POST" action="{{ route('admin.akun.tolak', $user) }}">
                @csrf
                <div class="field @error('alasan_penolakan') has-error @enderror">
                    <label for="alasan_penolakan">Alasan Penolakan <span class="required">*</span></label>
                    <textarea 
                        name="alasan_penolakan" 
                        id="alasan_penolakan" 
                        class="textarea" 
                        placeholder="Contoh: Surat tugas buram atau masa berlaku telah habis..." 
                        required
                    >{{ old('alasan_penolakan') }}</textarea>
                    @error('alasan_penolakan')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menolak pendaftaran akun ini?');">
                    ✕ Tolak Pendaftaran
                </button>
            </form>
        </div>
    @elseif ($user->status === \App\Enums\StatusAkun::Aktif)
        {{-- Rincian Penugasan Akun Aktif --}}
        <div class="card" style="border-top: 4px solid var(--color-aman);">
            <h2 class="card-title">Penugasan Akun Aktif</h2>
            <div style="font-size: var(--font-size-sm); margin-top: var(--space-2);">
                @if ($user->isKepalaWilayah())
                    <p><strong>Wilayah Penugasan:</strong> {{ $user->kode_wilayah }} &mdash; {{ $user->wilayah?->nama ?? 'Wilayah Terdaftar' }}</p>
                @elseif ($user->isBadanResmi())
                    <p><strong>Instansi:</strong> {{ $user->instansi?->nama ?? '-' }}</p>
                    <p><strong>Peran Jabatan:</strong> {{ $user->isPengelolaInstansi() ? 'Pengelola Instansi' : 'Petugas Lapangan' }}</p>
                @endif
            </div>
        </div>

        {{-- PSKE-37: Nonaktifkan Akun --}}
        <div class="card" style="border-top: 4px solid var(--color-netral-border);">
            <h2 class="card-title">Nonaktifkan Akun (PSKE-37)</h2>
            <p class="text-muted" style="font-size: var(--font-size-sm); margin-bottom: var(--space-3);">
                Nonaktifkan akses login pengguna tanpa menghapus riwayat dokumen atau transaksi sebelumnya.
            </p>
            <form method="POST" action="{{ route('admin.akun.nonaktifkan', $user) }}">
                @csrf
                <p style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: var(--space-3);">
                    Status akun akan berubah menjadi <strong>NONAKTIF</strong> dan pengguna tidak dapat masuk lagi.
                </p>
                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Apakah Anda yakin ingin menonaktifkan akun ini?');">
                    Nonaktifkan Akun
                </button>
            </form>
        </div>
    @elseif ($user->status === \App\Enums\StatusAkun::Nonaktif)
        <div class="alert alert-info">
            Akun ini saat ini dalam status <strong>NONAKTIF</strong>. Akses login telah ditutup dan riwayat data tetap tersimpan.
        </div>
    @endif
</div>

@if ($user->status === \App\Enums\StatusAkun::Menunggu && $user->isKepalaWilayah())
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cariInput = document.getElementById('cari-wilayah-input');
    const selectWilayah = document.getElementById('kode_wilayah');
    const optgroupDaftar = document.getElementById('optgroup-wilayah');
    if (!cariInput || !selectWilayah || !optgroupDaftar) return;

    let debounceTimer;
    cariInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        if (query.length < 2) return;

        debounceTimer = setTimeout(async function () {
            try {
                const response = await fetch('{{ route('admin.akun.cari-wilayah') }}?q=' + encodeURIComponent(query));
                if (!response.ok) return;
                const data = await response.json();
                
                optgroupDaftar.innerHTML = '';
                if (data.length === 0) {
                    const opt = document.createElement('option');
                    opt.disabled = true;
                    opt.textContent = '-- Tidak ada wilayah yang cocok --';
                    optgroupDaftar.appendChild(opt);
                } else {
                    data.forEach(function (w) {
                        const opt = document.createElement('option');
                        opt.value = w.kode;
                        const levelStr = w.level === 3 ? 'Kecamatan' : 'Desa/Kelurahan';
                        opt.textContent = `${w.kode} — ${w.nama} (${levelStr})`;
                        optgroupDaftar.appendChild(opt);
                    });
                }
            } catch (e) {
                console.error('Gagal memuat wilayah:', e);
            }
        }, 300);
    });
});
</script>
@endif
@endsection
