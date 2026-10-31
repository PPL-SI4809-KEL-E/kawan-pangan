{{-- PBI-05 · Konfirmasi registrasi (PSKE-6) --}}
@extends('layouts.auth')

@section('title', 'Pendaftaran Terkirim')
@section('card_class', 'reg-card')

@push('head')
    @vite('resources/css/registrasi.css')
@endpush

@section('content')
<section class="card reg-terkirim" role="status">
    <span class="reg-ikon" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
        </svg>
    </span>
    <h1 class="reg-title">Pendaftaran Terkirim</h1>
    <span class="badge {{ $status->badge() }}">{{ $status->label() }}</span>
    <p class="text-muted">
        Akun <strong>{{ $email }}</strong> sedang diperiksa Superadmin.
        Anda belum dapat masuk sampai akun diaktifkan.
    </p>
    <ol class="reg-langkah">
        <li>Superadmin memeriksa surat tugas yang Anda unggah.</li>
        <li>Bila sah, akun diaktifkan dan dikaitkan dengan wilayah atau instansi Anda.</li>
        <li>Bila ditolak, alasannya ditampilkan saat Anda mencoba masuk.</li>
    </ol>
    <a class="btn btn-secondary" href="{{ url('/') }}">Kembali ke Beranda</a>
</section>
@endsection
