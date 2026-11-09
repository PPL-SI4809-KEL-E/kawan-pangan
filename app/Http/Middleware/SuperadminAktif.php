<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class SuperadminAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            // JSON tetap 401; request web memakai login saat PSKE-16 sudah terintegrasi.
            if (! $request->expectsJson() && Route::has('login')) {
                return redirect()->guest(route('login'));
            }
            abort(401, 'Silakan masuk terlebih dahulu.');
        }
        abort_unless($request->user()->role === Role::Superadmin && $request->user()->bolehLogin(), 403, 'Hanya Superadmin aktif yang dapat mengelola data master.');

        return $next($request);
    }
}
