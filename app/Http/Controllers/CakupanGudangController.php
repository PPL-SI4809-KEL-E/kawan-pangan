<?php

namespace App\Http\Controllers;

use App\Models\CakupanGudang;
use App\Models\Gudang;
use App\Models\Wilayah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Controller untuk Layar M-12: Atur Cakupan Gudang (PSKE-48 / PBI-10).
 *
 * Mengelola pemetaan kecamatan yang dilayani oleh gudang, status gudang utama (PSKE-50),
 * dan menampilkan penandaan untuk kecamatan yang belum memiliki cakupan (PSKE-51).
 */
class CakupanGudangController extends Controller
{
    /**
     * Tampilkan halaman Layar M-12 (Daftar cakupan gudang & kecamatan tanpa cakupan).
     */
    public function index(Gudang $gudang): View
    {
        Gate::authorize('kelolaCakupan', $gudang);

        $gudang->load(['instansi', 'wilayah']);

        // Daftar cakupan aktif untuk gudang ini
        $daftarCakupan = CakupanGudang::where('gudang_id', $gudang->id)
            ->with('kecamatan')
            ->orderBy('is_utama', 'desc')
            ->get();

        // Cari kode kabupaten/kota induk dari lokasi gudang
        $kabupatenKode = null;
        if ($gudang->wilayah) {
            if ((int) $gudang->wilayah->level === 3) {
                $kabupatenKode = $gudang->wilayah->kode_induk;
            } elseif ((int) $gudang->wilayah->level === 2) {
                $kabupatenKode = $gudang->wilayah->kode;
            } elseif ((int) $gudang->wilayah->level === 4) {
                $kecamatan = Wilayah::where('kode', $gudang->wilayah->kode_induk)->first();
                $kabupatenKode = $kecamatan?->kode_induk;
            }
        }

        // Ambil semua kecamatan dalam kabupaten terkait (atau seluruh kecamatan jika gudang di tingkat provinsi)
        $semuaKecamatanQuery = Wilayah::where('level', 3);
        if ($kabupatenKode) {
            $semuaKecamatanQuery->where('kode_induk', $kabupatenKode);
        }
        $semuaKecamatan = $semuaKecamatanQuery->orderBy('nama')->get();

        // Kecamatan yang sudah dicakup oleh gudang ini
        $sudahDicakupGudangIni = $daftarCakupan->pluck('kode_kecamatan')->all();

        // Ambil seluruh cakupan gudang pada wilayah tersebut untuk mencari kecamatan tanpa cakupan (PSKE-51)
        $semuaCakupan = CakupanGudang::whereIn('kode_kecamatan', $semuaKecamatan->pluck('kode'))
            ->with('gudang')
            ->get()
            ->groupBy('kode_kecamatan');

        // PSKE-51: Identifikasi kecamatan yang belum dilayani oleh gudang mana pun
        $kecamatanTanpaCakupan = $semuaKecamatan->filter(function ($kec) use ($semuaCakupan) {
            return ! isset($semuaCakupan[$kec->kode]) || $semuaCakupan[$kec->kode]->isEmpty();
        })->values();

        // Pilihan kecamatan yang belum dicakup oleh gudang ini untuk form tambah
        $pilihanKecamatan = $semuaKecamatan->filter(function ($kec) use ($sudahDicakupGudangIni) {
            return ! in_array($kec->kode, $sudahDicakupGudangIni, true);
        })->values();

        // Info wilayah kabupaten untuk label antarmuka
        $wilayahKabupaten = $kabupatenKode ? Wilayah::where('kode', $kabupatenKode)->first() : null;

        return view('gudang.cakupan', [
            'gudang' => $gudang,
            'daftarCakupan' => $daftarCakupan,
            'pilihanKecamatan' => $pilihanKecamatan,
            'kecamatanTanpaCakupan' => $kecamatanTanpaCakupan,
            'semuaCakupan' => $semuaCakupan,
            'wilayahKabupaten' => $wilayahKabupaten,
        ]);
    }

    /**
     * Tambahkan kecamatan ke dalam cakupan gudang.
     */
    public function store(Request $request, Gudang $gudang): RedirectResponse
    {
        Gate::authorize('kelolaCakupan', $gudang);

        $request->validate([
            'kode_kecamatan' => ['required', 'string', 'exists:wilayah,kode'],
            'is_utama' => ['nullable', 'boolean'],
        ], [
            'kode_kecamatan.required' => 'Pilih kecamatan yang ingin dicakup.',
            'kode_kecamatan.exists' => 'Kecamatan yang dipilih tidak valid dalam master wilayah.',
        ]);

        CakupanGudang::create([
            'gudang_id' => $gudang->id,
            'kode_kecamatan' => $request->input('kode_kecamatan'),
            'is_utama' => $request->boolean('is_utama'),
        ]);

        return back()->with('sukses', 'Kecamatan berhasil ditambahkan ke cakupan gudang.');
    }

    /**
     * Alihkan atau ubah status gudang utama (PSKE-50).
     */
    public function toggleUtama(Gudang $gudang, CakupanGudang $cakupan): RedirectResponse
    {
        Gate::authorize('kelolaCakupan', $gudang);

        if ($cakupan->gudang_id !== $gudang->id) {
            abort(404, 'Data cakupan tidak sesuai dengan gudang.');
        }

        $statusBaru = ! $cakupan->is_utama;
        $cakupan->update(['is_utama' => $statusBaru]);

        $pesan = $statusBaru
            ? "Status gudang utama untuk kecamatan {$cakupan->kecamatan?->nama} berhasil diaktifkan."
            : "Status gudang utama untuk kecamatan {$cakupan->kecamatan?->nama} dilepas.";

        return back()->with('sukses', $pesan);
    }

    /**
     * Hapus kecamatan dari cakupan gudang.
     */
    public function destroy(Gudang $gudang, CakupanGudang $cakupan): RedirectResponse
    {
        Gate::authorize('kelolaCakupan', $gudang);

        if ($cakupan->gudang_id !== $gudang->id) {
            abort(404, 'Data cakupan tidak sesuai dengan gudang.');
        }

        $namaKecamatan = $cakupan->kecamatan?->nama ?? $cakupan->kode_kecamatan;
        $cakupan->delete();

        return back()->with('sukses', "Kecamatan {$namaKecamatan} berhasil dihapus dari cakupan gudang.");
    }
}
