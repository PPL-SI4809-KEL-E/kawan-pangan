<?php

namespace App\Http\Controllers;

use App\Models\AmbangStatus;
use App\Services\LayananPetaChoropleth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Controller untuk PBI-15 · Layar M-02 Peta choropleth dengan drill-down (PSKE-69).
 */
class PetaChoroplethController extends Controller
{
    public function __construct(
        private readonly LayananPetaChoropleth $layananPeta,
    ) {}

    /**
     * Tampilkan beranda publik dengan data awal level 1 (Provinsi) bila basis data siap.
     */
    public function beranda(): View
    {
        $petaAwal = null;

        if ($this->layananPeta->tersediaUntukBeranda()) {
            $petaAwal = $this->layananPeta->ambilLevel(1);
        }

        // PSKE-106: legenda beranda mengikuti ambang status yang diatur Superadmin.
        // Tanpa tabel/baris ambang (misalnya tes tanpa migrasi), pakai nilai awal 14/30.
        $ambang = rescue(fn () => AmbangStatus::query()->find(1), null, false);

        return view('welcome', [
            'petaAwal' => $petaAwal,
            'petaWilayah' => $petaAwal['wilayah'] ?? null,
            'ambang' => [
                'kritis' => (int) ($ambang->kritis_hari ?? 14),
                'aman' => (int) ($ambang->aman_hari ?? 30),
            ],
        ]);
    }

    /**
     * PSKE-70 / PSKE-71 / PSKE-72: Endpoint JSON per level yang dibuka.
     */
    public function apiLevel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['nullable', 'integer', 'in:1,2,3'],
            'kode_induk' => ['nullable', 'string', 'max:20'],
            'komoditas_id' => ['nullable', 'integer', 'exists:komoditas,id'],
        ]);

        $level = (int) ($validated['level'] ?? 1);
        $kodeInduk = $validated['kode_induk'] ?? null;
        $komoditasId = isset($validated['komoditas_id']) ? (int) $validated['komoditas_id'] : null;

        try {
            $payload = $this->layananPeta->ambilLevel($level, $kodeInduk, $komoditasId);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['kode_induk' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json($payload);
    }
}
