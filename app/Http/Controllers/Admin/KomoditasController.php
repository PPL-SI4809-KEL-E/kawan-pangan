<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\KomoditasRequest;
use App\Models\Komoditas;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KomoditasController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $komoditas = Komoditas::query()
            ->when($filter['q'] ?? null, fn ($query, $q) => $query->where('nama', 'like', '%'.$q.'%'))
            ->orderBy('nama')->orderBy('id')->paginate(25)->withQueryString();

        return view('admin.komoditas.index', compact('komoditas'));
    }

    public function create(): View
    {
        return view('admin.komoditas.form', ['komoditas' => new Komoditas]);
    }

    public function store(KomoditasRequest $request): RedirectResponse
    {
        $this->simpan($request, new Komoditas);

        return to_route('admin.komoditas.index')->with('sukses', 'Komoditas berhasil ditambahkan.');
    }

    public function edit(Komoditas $komoditas): View
    {
        return view('admin.komoditas.form', compact('komoditas'));
    }

    public function update(KomoditasRequest $request, Komoditas $komoditas): RedirectResponse
    {
        $this->simpan($request, $komoditas);

        return to_route('admin.komoditas.index')->with('sukses', 'Komoditas berhasil diperbarui.');
    }

    private function simpan(KomoditasRequest $request, Komoditas $komoditas): void
    {
        try {
            $komoditas->fill($request->validated())->save();
        } catch (UniqueConstraintViolationException $e) {
            // Dua request bersamaan tetap dilindungi unique index database.
            throw ValidationException::withMessages(['nama' => 'Nama komoditas sudah digunakan.']);
        }
    }

    public function destroy(Komoditas $komoditas): RedirectResponse
    {
        try {
            DB::transaction(function () use ($komoditas) {
                $komoditas = Komoditas::lockForUpdate()->findOrFail($komoditas->id);
                if ($komoditas->stok()->exists()) {
                    throw ValidationException::withMessages(['komoditas' => 'Komoditas masih punya stok dan tidak bisa dihapus.']);
                }
                $komoditas->delete();
            });
        } catch (QueryException $e) {
            report($e);

            return back()->withErrors(['komoditas' => 'Komoditas gagal dihapus. Periksa apakah masih digunakan, lalu coba lagi.']);
        }

        return to_route('admin.komoditas.index')->with('sukses', 'Komoditas berhasil dihapus.');
    }
}
