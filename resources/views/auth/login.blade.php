{{-- PBI-04 · M-07 Halaman login akun (PSKE-16, PSKE-31). Tampilan v2: PSKE-92 --}}
@extends('layouts.auth')

@section('title', 'Masuk')
@section('card_class', 'login-card')

@section('content')
<section class="card auth-form" role="region" aria-label="Formulir Masuk">
    <p class="eyebrow">Ruang kerja petugas</p>
    <h1 class="auth-title">Masuk ke Kawan Pangan</h1>
    <p class="auth-sub text-muted">Gunakan alamat email dan kata sandi akun Anda yang telah terverifikasi.</p>

    @if (session('status'))
        <div class="alert alert-success auth-alert" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error auth-alert" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="field @error('email') has-error @enderror">
            <label for="email">Alamat email <span class="required">*</span></label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input" placeholder="nama@instansi.go.id">
            @error('email')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field @error('password') has-error @enderror">
            <label for="password">Kata sandi <span class="required">*</span></label>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="input" placeholder="••••••••">
            @error('password')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <label class="check auth-remember">
            <input type="checkbox" name="remember" value="1">
            <span>Ingat saya di perangkat ini</span>
        </label>

        <button type="submit" class="btn btn-primary btn-block">Masuk</button>
    </form>

    <p class="auth-foot">
        <span class="text-muted">Belum memiliki akun?</span>
        <a href="{{ route('registrasi.create') }}">Daftar akun baru</a>
    </p>
</section>
@endsection
