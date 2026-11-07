<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pembatasan akses berdasarkan role pengguna.
 *
 * Dipakai pada kelompok route untuk memastikan hanya role tertentu yang
 * boleh mengakses endpoint di dalamnya.
 *
 * Contoh penggunaan di routes:
 *   Route::middleware('role:KEPALA_WILAYAH')->group(fn () => ...);
 *   Route::middleware('role:KEPALA_WILAYAH,SUPERADMIN')->group(fn () => ...);
 *
 * Middleware ini memeriksa role pada level route. Pemeriksaan cakupan objek privat
 * (wilayah, instansi, penugasan) dilakukan lebih lanjut melalui Policy.
 */
class PeriksaRole
{
    /**
     * @param  string  ...$roles  Satu atau lebih nilai Role enum yang diizinkan, dipisah koma.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Pengguna belum login ditangani oleh middleware `auth` sebelum middleware ini.
        if (! $user) {
            abort(403);
        }

        // Bandingkan nilai enum role pengguna dengan daftar yang diizinkan.
        $roleDiizinkan = array_map(
            fn (string $r) => Role::tryFrom(trim($r)),
            $roles,
        );

        if (! in_array($user->role, $roleDiizinkan, strict: true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
