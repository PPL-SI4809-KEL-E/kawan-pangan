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
        // Login (PBI-04) belum tersedia di main. Jangan redirect ke rute login yang belum ada.
        abort_unless($request->user(), 401, 'Silakan masuk terlebih dahulu.');
        abort_unless($request->user()->role === Role::Superadmin && $request->user()->bolehLogin(), 403, 'Hanya Superadmin aktif yang dapat mengelola master wilayah.');

        return $next($request);
    }
}
