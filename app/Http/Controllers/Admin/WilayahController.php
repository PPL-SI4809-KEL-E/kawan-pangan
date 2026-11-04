<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wilayah;
use App\Services\ImporWilayah;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WilayahController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'level' => ['nullable', Rule::in(array_keys(Wilayah::LEVEL))],
            'induk' => ['nullable', 'string', 'exists:wilayah,kode'],
        ]);
        $wilayah = Wilayah::query()->with('induk')
            ->when($filter['q'] ?? null, fn ($q, $cari) => $q->where(fn ($q) => $q->where('kode', 'like', '%'.$cari.'%')->orWhere('nama', 'like', '%'.$cari.'%')))
            ->when($filter['level'] ?? null, fn ($q, $level) => $q->where('level', $level))
            ->when($filter['induk'] ?? null, fn ($q, $induk) => $q->where('kode_induk', $induk))
            ->orderBy('kode')->paginate(25)->withQueryString();

        return view('admin.wilayah.index', [
            'wilayah' => $wilayah,
            'ringkasan' => Wilayah::selectRaw('level, count(*) as jumlah')->groupBy('level')->pluck('jumlah', 'level'),
            'induk' => isset($filter['induk']) ? Wilayah::find($filter['induk']) : null,
        ]);
    }

    public function impor(Request $request, ImporWilayah $importer): RedirectResponse
    {
        $request->validate(['berkas' => ['required', 'file', 'extensions:csv', 'mimes:csv,txt', 'max:20480']], [
            'berkas.required' => 'Pilih berkas CSV wilayah.',
            'berkas.extensions' => 'Berkas harus berekstensi .csv.',
            'berkas.mimes' => 'Isi berkas harus berupa CSV teks.',
            'berkas.max' => 'Ukuran berkas maksimal 20 MB.',
            'berkas.uploaded' => 'Unggah gagal. Periksa batas unggah PHP dan pilih ulang berkas.',
        ]);
        try {
            $hasil = $importer->impor($request->file('berkas'));
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['berkas' => 'Impor gagal disimpan. Tidak ada perubahan data. Silakan coba lagi.']);
        }

        return to_route('admin.wilayah.index')->with('sukses', "Impor berhasil: {$hasil['baru']} wilayah baru, {$hasil['diperbarui']} wilayah diperbarui.");
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "kode,nama,kode_induk,jumlah_penduduk\r\n";
        }, 'template-wilayah.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function edit(Wilayah $wilayah): View
    {
        return view('admin.wilayah.edit', ['wilayah' => $wilayah->load('induk')]);
    }

    public function update(Request $request, Wilayah $wilayah): RedirectResponse
    {
        $data = $request->validate([
            'jumlah_penduduk' => ['required', 'integer', 'min:0', 'max:'.Wilayah::MAKS_PENDUDUK],
        ], [
            'jumlah_penduduk.required' => 'Jumlah penduduk wajib diisi.',
            'jumlah_penduduk.integer' => 'Jumlah penduduk harus bilangan bulat.',
            'jumlah_penduduk.min' => 'Jumlah penduduk tidak boleh negatif.',
            'jumlah_penduduk.max' => 'Jumlah penduduk maksimal '.Wilayah::MAKS_PENDUDUK.'.',
        ]);
        // Hanya populasi yang dapat diedit lewat endpoint ini.
        $wilayah->update(['jumlah_penduduk' => $data['jumlah_penduduk']]);

        return to_route('admin.wilayah.edit', $wilayah)->with('sukses', 'Jumlah penduduk berhasil diperbarui.');
    }
}
