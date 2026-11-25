<?php

namespace App\Http\Controllers;

use App\Models\Cadangan;
use App\Models\Gudang;
use App\Models\Komoditas;
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
    public const PER_HALAMAN = 50;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Gudang::class);

        $instansiId = $request->user()->instansi_id;

        $filter = $request->validate([
            'gudang' => ['nullable', 'integer'],
            'komoditas' => ['nullable', 'integer'],
        ]);

        $pilihanGudang = $instansiId
            ? Gudang::where('instansi_id', $instansiId)->orderBy('nama')->get(['id', 'nama'])
            : collect();
        $pilihanKomoditas = Komoditas::orderBy('nama')->get(['id', 'nama']);

        $stok = Stok::query()
            ->select('stok.*')
            ->join('gudang', 'gudang.id', '=', 'stok.gudang_id')
            ->join('komoditas', 'komoditas.id', '=', 'stok.komoditas_id')
            // Tanpa instansi = tanpa data; gudang instansi lain tidak pernah ikut.
            ->where('gudang.instansi_id', $instansiId ?? 0)
            ->when($filter['gudang'] ?? null, fn ($q, $id) => $q->where('stok.gudang_id', $id))
            ->when($filter['komoditas'] ?? null, fn ($q, $id) => $q->where('stok.komoditas_id', $id))
            ->with(['gudang:id,nama', 'komoditas:id,nama,satuan'])
            ->withSum(['cadangan as cadangan_aktif' => fn ($q) => $q->where('status', Cadangan::STATUS_AKTIF)], 'jumlah')
            ->orderBy('komoditas.nama')
            ->orderBy('gudang.nama')
            ->orderBy('stok.id')
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        $stok->getCollection()->each(function (Stok $baris) {
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
            'pilihanGudang' => $pilihanGudang,
            'pilihanKomoditas' => $pilihanKomoditas,
            'filter' => $filter,
            'terakhirDiperbarui' => $terakhirDiperbarui,
            // Diisi layanan estimasi PBI-12: [stok_id => [...]]. Kosong sampai layanan tersedia.
            'estimasi' => [],
        ]);
    }
}
