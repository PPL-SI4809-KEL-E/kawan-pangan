<?php

namespace App\Http\Controllers;

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
        return view('panel.instansi');
    }
}
