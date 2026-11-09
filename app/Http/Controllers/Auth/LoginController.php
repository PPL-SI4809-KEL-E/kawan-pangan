<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * PBI-04 / PSKE-16 · Login, logout, dan sesi per role (UC02, UC03, layar M-07).
 */
class LoginController extends Controller
{
    /**
     * Tampilkan formulir login.
     */
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->arahkanSesuaiRole(Auth::user()->role);
        }

        return view('auth.login');
    }

    /**
     * Tangani permintaan autentikasi login (PSKE-31, PSKE-32, PSKE-33).
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        return $this->arahkanSesuaiRole($user->role);
    }

    /**
     * Hancurkan sesi autentikasi dan logout pengguna (PSKE-34).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('sukses', 'Anda telah berhasil keluar.');
    }

    /**
     * Arahkan pengguna ke panel yang sesuai dengan perannya (PSKE-31).
     */
    protected function arahkanSesuaiRole(?Role $role): RedirectResponse
    {
        return match ($role) {
            Role::Superadmin => redirect()->intended(route('admin.dashboard')),
            Role::KepalaWilayah => redirect()->intended(route('panel.wilayah')),
            Role::BadanResmi => redirect()->intended(route('panel.instansi')),
            default => redirect()->intended('/'),
        };
    }
}
