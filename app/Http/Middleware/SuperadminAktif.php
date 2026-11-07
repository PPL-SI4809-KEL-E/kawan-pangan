<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperadminAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        // PSKE-16: Pengunjung belum login diarahkan ke halaman login.
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        abort_unless($request->user()->role === Role::Superadmin && $request->user()->bolehLogin(), 403, 'Hanya Superadmin aktif yang dapat mengelola master wilayah.');

        return $next($request);
    }
}
