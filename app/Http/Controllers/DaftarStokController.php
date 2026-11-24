<?php

namespace App\Http\Controllers;

use App\Models\Cadangan;
use App\Models\Gudang;
use App\Models\Stok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * PBI-13 · Layar M-11: daftar stok per gudang dan komoditas untuk Badan Resmi.
 *
 * Hanya gudang milik instansi pengguna yang ditampilkan. Kolom estimasi
 * (penggunaan harian, hari tersisa, perkiraan habis, status, periode, kelengkapan)
 * diisi dari layanan estimasi PBI-12 setelah layanan itu masuk ke main.
 */
class DaftarStokController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Gudang::class);

        $instansiId = $request->user()->instansi_id;

        $stok = Stok::query()
            ->select('stok.*')
            ->join('gudang', 'gudang.id', '=', 'stok.gudang_id')
            ->join('komoditas', 'komoditas.id', '=', 'stok.komoditas_id')
            // Tanpa instansi = tanpa data; gudang instansi lain tidak pernah ikut.
            ->where('gudang.instansi_id', $instansiId ?? 0)
            ->with(['gudang:id,nama', 'komoditas:id,nama,satuan'])
            ->withSum(['cadangan as cadangan_aktif' => fn ($q) => $q->where('status', Cadangan::STATUS_AKTIF)], 'jumlah')
            ->orderBy('komoditas.nama')
            ->orderBy('gudang.nama')
            ->orderBy('stok.id')
            ->get();

        $stok->each(function (Stok $baris) {
            $cadangan = (float) ($baris->cadangan_aktif ?? 0);
            $baris->setAttribute('cadangan_aktif', $cadangan);
            $baris->setAttribute('tersedia', max(0.0, (float) $baris->jumlah_fisik_layak - $cadangan));
        });

        $terakhirDiperbarui = $instansiId
            ? Stok::join('gudang', 'gudang.id', '=', 'stok.gudang_id')
                ->where('gudang.instansi_id', $instansiId)
                ->max('stok.diperbarui_pada')
            : null;

        return view('panel.stok.index', [
            'stok' => $stok,
            'terakhirDiperbarui' => $terakhirDiperbarui,
            // Diisi layanan estimasi PBI-12: [stok_id => [...]]. Kosong sampai layanan tersedia.
            'estimasi' => [],
        ]);
    }
}
