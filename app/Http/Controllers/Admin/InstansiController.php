<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LokasiMasterRequest;
use App\Models\Instansi;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstansiController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $instansi = Instansi::withCount('gudang')
            ->when($filter['q'] ?? null, fn ($query, $q) => $query->where('nama', 'like', '%'.$q.'%'))
            ->orderBy('nama')->orderBy('id')->paginate(25)->withQueryString();

        return view('admin.instansi.index', compact('instansi'));
    }

    public function create(): View
    {
        return view('admin.instansi.form', ['instansi' => new Instansi]);
    }

    public function store(LokasiMasterRequest $request): RedirectResponse
    {
        Instansi::create($request->validated());

        return to_route('admin.instansi.index')->with('sukses', 'Instansi berhasil ditambahkan.');
    }

    public function edit(Instansi $instansi): View
    {
        return view('admin.instansi.form', compact('instansi'));
    }

    public function update(LokasiMasterRequest $request, Instansi $instansi): RedirectResponse
    {
        $instansi->update($request->validated());

        return to_route('admin.instansi.index')->with('sukses', 'Instansi berhasil diperbarui.');
    }

    public function destroy(Instansi $instansi): RedirectResponse
    {
        try {
            DB::transaction(function () use ($instansi) {
                $instansi = Instansi::lockForUpdate()->findOrFail($instansi->id);
                if ($instansi->gudang()->exists() || $instansi->pengguna()->exists()) {
                    throw ValidationException::withMessages(['instansi' => 'Instansi masih digunakan oleh gudang atau akun dan tidak dapat dihapus.']);
                }
                $instansi->delete();
            });
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['instansi' => 'Instansi gagal dihapus. Periksa apakah masih digunakan, lalu coba lagi.']);
        }

        return to_route('admin.instansi.index')->with('sukses', 'Instansi berhasil dihapus.');
    }
}
