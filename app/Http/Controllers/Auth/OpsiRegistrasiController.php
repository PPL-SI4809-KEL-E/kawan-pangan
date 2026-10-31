<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\DataContohRegistrasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan dropdown wilayah dan instansi di formulir registrasi.
 *
 * Membaca tabel `wilayah` (PBI-08) dan `instansi` (PBI-09) bila sudah ada.
 * Sebelum itu, memakai data contoh agar formulir tetap bisa dicoba.
 */
class OpsiRegistrasiController extends Controller
{
    public function wilayah(Request $request): JsonResponse
    {
        $induk = $request->query('induk');

        if (Schema::hasTable('wilayah')) {
            $data = DB::table('wilayah')
                ->select('kode', 'nama')
                ->when($induk, fn ($q) => $q->where('kode_induk', $induk), fn ($q) => $q->whereNull('kode_induk'))
                ->orderBy('nama')
                ->get();

            return response()->json(['data' => $data, 'contoh' => false]);
        }

        return response()->json(['data' => DataContohRegistrasi::wilayah($induk), 'contoh' => true]);
    }

    public function instansi(): JsonResponse
    {
        if (Schema::hasTable('instansi')) {
            $data = DB::table('instansi')->select('id', 'nama')->orderBy('nama')->get();

            return response()->json(['data' => $data, 'contoh' => false]);
        }

        return response()->json(['data' => DataContohRegistrasi::instansi(), 'contoh' => true]);
    }
}
