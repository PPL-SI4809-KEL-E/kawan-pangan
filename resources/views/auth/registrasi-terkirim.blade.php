{{-- PBI-05 · Konfirmasi registrasi (PSKE-6) --}}
@extends('layouts.auth')

@section('title', 'Pendaftaran Terkirim')
@section('card_class', 'reg-card')

@push('head')
    @vite('resources/css/registrasi.css')
@endpush

@section('content')
<section class="card reg-terkirim" role="status">
    <span class="state-icon reg-ikon" aria-hidden="true"><svg class="icon"><use href="#i-clock"/></svg></span>
    <span class="badge {{ $status->badge() }}">{{ $status->label() }}</span>
    <h1 class="auth-title reg-title">Pendaftaran Terkirim</h1>
    <p class="text-muted">
        Akun <strong>{{ $email }}</strong> sedang diperiksa Superadmin.
        Anda belum dapat masuk sampai akun diaktifkan.
    </p>
    <ol class="reg-langkah">
        <li>Superadmin memeriksa surat tugas yang Anda unggah.</li>
        <li>Bila sah, akun diaktifkan dan dikaitkan dengan wilayah atau instansi Anda.</li>
        <li>Bila ditolak, alasannya ditampilkan saat Anda mencoba masuk.</li>
    </ol>
    <a class="btn btn-secondary" href="{{ url('/') }}">Kembali ke peta</a>
</section>
@endsection
