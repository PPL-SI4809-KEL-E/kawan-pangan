<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GudangRequest;
use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\Wilayah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GudangController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'instansi_id' => ['nullable', 'integer', 'exists:instansi,id'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $gudang = Gudang::with(['instansi', 'wilayah'])
            ->when($filter['q'] ?? null, fn ($query, $q) => $query->where('nama', 'like', '%'.$q.'%'))
            ->when($filter['instansi_id'] ?? null, fn ($query, $id) => $query->where('instansi_id', $id))
            ->when(isset($filter['aktif']), fn ($query) => $query->where('aktif', $filter['aktif']))
            ->orderBy('nama')->orderBy('id')->paginate(25)->withQueryString();

        return view('admin.gudang.index', ['gudang' => $gudang, 'instansi' => Instansi::orderBy('nama')->get(['id', 'nama'])]);
    }

    public function opsiWilayah(Request $request): JsonResponse
    {
        $filter = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $q = trim($filter['q'] ?? '');
        $wilayah = $q === '' ? collect() : Wilayah::query()
            ->where(fn ($query) => $query->where('kode', 'like', $q.'%')->orWhere('nama', 'like', '%'.$q.'%'))
            ->orderBy('kode')->limit(20)->get(['kode', 'nama']);

        return response()->json(['data' => $wilayah]);
    }

    private function form(Gudang $gudang): View
    {
        return view('admin.gudang.form', [
            'gudang' => $gudang,
            'instansi' => Instansi::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Gudang(['aktif' => true]));
    }

    public function store(GudangRequest $request): RedirectResponse
    {
        Gudang::create($request->validated());

        return to_route('admin.gudang.index')->with('sukses', 'Gudang berhasil ditambahkan.');
    }

    public function edit(Gudang $gudang): View
    {
        return $this->form($gudang);
    }

    public function update(GudangRequest $request, Gudang $gudang): RedirectResponse
    {
        $gudang->update($request->validated());

        return to_route('admin.gudang.index')->with('sukses', 'Gudang berhasil diperbarui.');
    }

    public function status(Request $request, Gudang $gudang): RedirectResponse
    {
        $data = $request->validate(['aktif' => ['required', 'boolean']]);
        $gudang->update(['aktif' => $data['aktif']]);

        return to_route('admin.gudang.index')->with('sukses', $gudang->aktif ? 'Gudang berhasil diaktifkan.' : 'Gudang berhasil dinonaktifkan.');
    }

    public function destroy(Gudang $gudang): RedirectResponse
    {
        $gudang->delete();

        return to_route('admin.gudang.index')->with('sukses', 'Gudang berhasil dihapus.');
    }
}
