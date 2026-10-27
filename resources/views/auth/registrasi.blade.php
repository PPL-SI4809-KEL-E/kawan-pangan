{{-- PBI-05 · M-07 Registrasi akun (PSKE-4) --}}
@extends('layouts.auth')

@section('title', 'Daftar Akun')
@section('card_class', 'reg-card')

@push('head')
    @vite(['resources/css/registrasi.css', 'resources/js/registrasi.js'])
@endpush

@section('content')
<section class="card">
    <h1 class="reg-title">Daftar Akun</h1>
    <p class="text-muted reg-sub">
        Untuk Kepala Wilayah (kepala desa, lurah, camat) dan staf Badan Resmi.
        Masyarakat tidak perlu mendaftar untuk melihat peta dan informasi.
    </p>

    @if (session('registrasi_email'))
        <div class="alert alert-success reg-alert" role="status">
            Pendaftaran {{ session('registrasi_email') }} berhasil dikirim.
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error reg-alert" role="alert">
            <strong>Pendaftaran belum dapat dikirim.</strong>
            Periksa kembali isian yang ditandai merah.
        </div>
    @endif

    <form id="form-registrasi" method="POST" action="{{ route('registrasi.store') }}" enctype="multipart/form-data" novalidate>
        @csrf

        {{-- PSKE-4: hanya dua pilihan. Superadmin dibuat lewat seeding. --}}
        <fieldset class="field reg-fieldset @error('role') has-error @enderror">
            <legend class="reg-legend">Jenis akun <span class="required">*</span></legend>
            <div class="role-group">
                @foreach ($roles as $role)
                    <label class="role-option">
                        <input type="radio" name="role" value="{{ $role->value }}" @checked(old('role') === $role->value)>
                        <span class="role-option-body">
                            <strong>{{ $role->label() }}</strong>
                            <small class="text-muted">
                                @if ($role === \App\Enums\Role::KepalaWilayah)
                                    Kepala desa, lurah, atau camat
                                @else
                                    Staf Bapanas, Bulog, Dinas Ketahanan Pangan, ID FOOD, atau petugas lapangan
                                @endif
                            </small>
                        </span>
                    </label>
                @endforeach
            </div>
            <span class="field-error" data-error="role">@error('role'){{ $message }}@enderror</span>
        </fieldset>

        <div class="field @error('name') has-error @enderror">
            <label for="name">Nama lengkap <span class="required">*</span></label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" placeholder="Sesuai surat tugas">
            <span class="field-error" data-error="name">@error('name'){{ $message }}@enderror</span>
        </div>

        <div class="field @error('email') has-error @enderror">
            <label for="email">Email <span class="required">*</span></label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="nama@instansi.go.id">
            <span class="field-error" data-error="email">@error('email'){{ $message }}@enderror</span>
        </div>

        <div class="field @error('no_hp') has-error @enderror">
            <label for="no_hp">Nomor HP <span class="required">*</span></label>
            <input class="input" id="no_hp" name="no_hp" type="tel" inputmode="tel" value="{{ old('no_hp') }}" autocomplete="tel" placeholder="081234567890">
            <span class="field-error" data-error="no_hp">@error('no_hp'){{ $message }}@enderror</span>
        </div>

        {{-- Muncul bila Kepala Wilayah --}}
        <fieldset class="field reg-fieldset @error('kode_wilayah') has-error @enderror" id="grup-wilayah" hidden
                  data-url="{{ route('registrasi.opsi.wilayah') }}">
            <legend class="reg-legend">Wilayah tugas <span class="required">*</span></legend>
            <span class="field-help">Pilih sampai kecamatan (camat) atau desa/kelurahan (kepala desa, lurah).</span>
            <div class="reg-stack">
                @foreach (['provinsi' => 'Provinsi', 'kabkota' => 'Kabupaten/Kota', 'kecamatan' => 'Kecamatan', 'desa' => 'Desa/Kelurahan'] as $level => $label)
                    <select class="select" name="wilayah_{{ $level }}" aria-label="{{ $label }}"
                            data-label="{{ $label }}" data-old="{{ old('wilayah_'.$level) }}" @disabled(! $loop->first)>
                        <option value="">{{ $label }}</option>
                    </select>
                @endforeach
            </div>
            <span class="field-error" data-error="kode_wilayah">@error('kode_wilayah'){{ $message }}@enderror</span>
        </fieldset>

        {{-- Muncul bila Badan Resmi --}}
        <div class="field @error('instansi_id') has-error @enderror" id="grup-instansi" hidden
             data-url="{{ route('registrasi.opsi.instansi') }}">
            <label for="instansi_id">Instansi <span class="required">*</span></label>
            <select class="select" id="instansi_id" name="instansi_id" data-old="{{ old('instansi_id') }}">
                <option value="">Pilih instansi</option>
            </select>
            <span class="field-error" data-error="instansi_id">@error('instansi_id'){{ $message }}@enderror</span>
        </div>

        <div class="field @error('password') has-error @enderror">
            <label for="password">Kata sandi <span class="required">*</span></label>
            <input class="input" id="password" name="password" type="password" autocomplete="new-password">
            <span class="field-help">Minimal 8 karakter, berisi huruf dan angka.</span>
            <span class="field-error" data-error="password">@error('password'){{ $message }}@enderror</span>
        </div>

        <div class="field">
            <label for="password_confirmation">Ulangi kata sandi <span class="required">*</span></label>
            <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
        </div>

        <div class="field @error('setuju') has-error @enderror">
            <label class="reg-check">
                <input type="checkbox" name="setuju" value="1" @checked(old('setuju'))>
                <span>Saya menyatakan data dan surat tugas yang saya unggah benar dan dapat dipertanggungjawabkan.</span>
            </label>
            <span class="field-error" data-error="setuju">@error('setuju'){{ $message }}@enderror</span>
        </div>

        <button class="btn btn-primary btn-block" type="submit" id="btn-daftar">Daftar</button>
        <p class="text-muted reg-foot">Sudah punya akun? Masuk lewat halaman login.</p>
    </form>
</section>
@endsection
