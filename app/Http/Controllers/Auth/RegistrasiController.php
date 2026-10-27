<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrasiRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * PBI-05 · Registrasi akun dengan surat tugas (UC01, KF-01, layar M-07).
 */
class RegistrasiController extends Controller
{
    public function create(): View
    {
        return view('auth.registrasi', [
            'roles' => Role::bolehDaftar(),
        ]);
    }

    public function store(RegistrasiRequest $request): RedirectResponse
    {
        $user = User::create($request->dataAkun());

        return redirect()
            ->route('registrasi.create')
            ->with('registrasi_email', $user->email);
    }
}
