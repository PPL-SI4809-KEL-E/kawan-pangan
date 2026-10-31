<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Kawan Pangan</title>
    @vite('resources/css/design-system.css')
    @stack('head')
</head>
<body>
    {{-- Kerangka halaman tanpa sidebar untuk login dan registrasi (.auth-page dari design system) --}}
    <main class="auth-page">
        <div class="auth-card @yield('card_class')">
            <a class="app-brand auth-brand" href="{{ url('/') }}">
                <span class="app-brand-mark">KP</span>Kawan Pangan
            </a>
            @yield('content')
        </div>
    </main>
    @stack('scripts')
</body>
</html>
