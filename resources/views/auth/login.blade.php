{{-- PBI-04 · M-07 Halaman login akun (PSKE-16, PSKE-31) --}}
@extends('layouts.auth')

@section('title', 'Masuk')
@section('card_class', 'login-card')

@section('content')
<section class="card" role="region" aria-label="Formulir Masuk">
    <h1 style="margin-bottom: var(--space-1); font-size: var(--font-size-2xl);">Masuk ke Kawan Pangan</h1>
    <p class="text-muted" style="margin-bottom: var(--space-4); font-size: var(--font-size-sm);">
        Gunakan alamat email dan kata sandi akun Anda yang telah terverifikasi.
    </p>

    @if (session('status'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" style="margin-bottom: var(--space-4);" role="alert">
            <ul style="margin: 0; padding-left: var(--space-4);">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        {{-- Email --}}
        <div class="field @error('email') has-error @enderror">
            <label for="email">Alamat Email <span class="required">*</span></label>
            <input 
                id="email" 
                type="email" 
                name="email" 
                value="{{ old('email') }}" 
                required 
                autofocus 
                autocomplete="username" 
                class="input" 
                placeholder="nama@instansi.go.id"
            >
            @error('email')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        {{-- Password --}}
        <div class="field @error('password') has-error @enderror">
            <label for="password">Kata Sandi <span class="required">*</span></label>
            <input 
                id="password" 
                type="password" 
                name="password" 
                required 
                autocomplete="current-password" 
                class="input" 
                placeholder="••••••••"
            >
            @error('password')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        {{-- Ingat Saya --}}
        <div class="field" style="flex-direction: row; align-items: center; justify-content: space-between; margin-bottom: var(--space-4);">
            <label style="display: inline-flex; align-items: center; gap: var(--space-2); cursor: pointer; font-weight: normal; font-size: var(--font-size-sm);">
                <input type="checkbox" name="remember" value="1">
                <span>Ingat saya di perangkat ini</span>
            </label>
        </div>

        {{-- Tombol Submit --}}
        <div style="margin-top: var(--space-4);">
            <button type="submit" class="btn btn-primary btn-block">
                Masuk
            </button>
        </div>
    </form>

    {{-- Tautan ke Registrasi --}}
    <div style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--color-border); text-align: center; font-size: var(--font-size-sm);">
        <span class="text-muted">Belum memiliki akun?</span>
        <a href="{{ route('registrasi.create') }}" style="color: var(--color-primary); font-weight: var(--font-weight-semibold); text-decoration: none; margin-left: var(--space-1);">
            Daftar akun baru
        </a>
    </div>
</section>
@endsection
