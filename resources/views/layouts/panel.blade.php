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
    <div style="min-height: 100vh; display: flex; flex-direction: column;">
        {{-- Header Panel --}}
        <header style="background: var(--color-surface); border-bottom: 1px solid var(--color-border); padding: var(--space-3) var(--space-6); display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: var(--space-4);">
                <a class="app-brand" href="{{ url('/') }}" style="text-decoration: none; display: flex; align-items: center; gap: var(--space-2); font-weight: var(--font-weight-bold); color: var(--color-text);">
                    <span class="app-brand-mark" style="background: var(--color-primary); color: var(--color-on-primary); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: var(--font-size-xs);">KP</span>
                    Kawan Pangan
                </a>
                <span class="badge {{ auth()->user()->role === \App\Enums\Role::KepalaWilayah ? 'badge-info' : 'badge-waspada' }}">
                    {{ auth()->user()->role->label() }}
                </span>
            </div>

            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <span class="text-muted" style="font-size: var(--font-size-sm);">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Keluar</button>
                </form>
            </div>
        </header>

        {{-- Isi Konten Panel --}}
        <main style="flex: 1; padding: var(--space-6);">
            <div style="max-width: 1000px; margin: 0 auto;" class="stack">
                @if (session('sukses'))
                    <div class="alert alert-success" role="status">{{ session('sukses') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        <ul style="margin: 0; padding-left: var(--space-4);">
                            @foreach ($errors->all() as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html>
