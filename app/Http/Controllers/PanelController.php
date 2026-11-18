<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use Illuminate\View\View;

/**
 * Kontroler panel/dashboard per role (PSKE-31).
 */
class PanelController extends Controller
{
    /**
     * Panel untuk peran Superadmin (/admin).
     */
    public function admin(): View
    {
        return view('admin.dashboard');
    }

    /**
     * Panel untuk peran Kepala Wilayah (/panel/wilayah).
     */
    public function wilayah(): View
    {
        return view('panel.wilayah');
    }

    /**
     * Panel untuk peran Badan Resmi (/panel/instansi).
     */
    public function instansi(): View
    {
        $user = auth()->user();
        $gudangList = $user && $user->instansi_id
            ? Gudang::where('instansi_id', $user->instansi_id)->with('wilayah')->get()
            : collect();

        return view('panel.instansi', [
            'gudangList' => $gudangList,
        ]);
    }
}
