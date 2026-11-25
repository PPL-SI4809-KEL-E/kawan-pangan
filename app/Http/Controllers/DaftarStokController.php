<?php

namespace App\Http\Controllers;

use App\Models\AmbangStatus;
use App\Models\Gudang;
use App\Models\Komoditas;
use App\Models\Stok;
use App\Services\LayananEstimasiStok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * PBI-13 · Layar M-11: daftar stok per gudang dan komoditas untuk Badan Resmi.
 *
 * Hanya gudang milik instansi pengguna yang ditampilkan.
 */
class DaftarStokController extends Controller
{
    public const PER_HALAMAN = 50;

    public function index(Request $request, LayananEstimasiStok $layanan): View
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

        $acuan = today();
        $query = Stok::query()
            ->select('stok.*')
            ->join('gudang', 'gudang.id', '=', 'stok.gudang_id')
            ->join('komoditas', 'komoditas.id', '=', 'stok.komoditas_id')
            // Tanpa instansi = tanpa data; gudang instansi lain tidak pernah ikut.
            ->where('gudang.instansi_id', $instansiId ?? 0)
            ->when($filter['gudang'] ?? null, fn ($q, $id) => $q->where('stok.gudang_id', $id))
            ->when($filter['komoditas'] ?? null, fn ($q, $id) => $q->where('stok.komoditas_id', $id))
            ->with(['gudang:id,nama', 'komoditas:id,nama,satuan'])
            ->orderBy('komoditas.nama')
            ->orderBy('gudang.nama')
            ->orderBy('stok.id');
        $stok = $layanan->denganTotalPeriode($query, $acuan)
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        $ambang = AmbangStatus::aktif();
        $estimasi = $stok->getCollection()->mapWithKeys(function (Stok $baris) use ($layanan, $acuan, $ambang) {
            $hasil = $layanan->ringkas([$baris], $acuan, $ambang);
            $baris->setAttribute('tersedia', $hasil['stok_tersedia']);

            return [$baris->id => $hasil];
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
            'estimasi' => $estimasi,
        ]);
    }
}
