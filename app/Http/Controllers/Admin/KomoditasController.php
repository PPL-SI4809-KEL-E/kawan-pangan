<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\KomoditasRequest;
use App\Models\Komoditas;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $komoditas->delete();

        return to_route('admin.komoditas.index')->with('sukses', 'Komoditas berhasil dihapus.');
    }
}
