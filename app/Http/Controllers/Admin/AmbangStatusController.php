<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AmbangStatusRequest;
use App\Models\AmbangStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AmbangStatusController extends Controller
{
    public function edit(): View
    {
        return view('admin.ambang-status.edit', ['ambang' => AmbangStatus::aktif()]);
    }

    public function update(AmbangStatusRequest $request): RedirectResponse
    {
        AmbangStatus::aktif()->update($request->validated());

        return to_route('admin.ambang-status.edit')->with('sukses', 'Ambang status berhasil diperbarui.');
    }
}
