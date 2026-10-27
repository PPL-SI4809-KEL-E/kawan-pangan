<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrasiRequest;
use App\Models\DokumenPendukung;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

/**
 * PBI-05 · Registrasi akun dengan surat tugas (UC01, KF-01, layar M-07).
 */
class RegistrasiController extends Controller
{
    /** Disk privat (storage/app/private); surat tugas tidak bisa dibuka publik. */
    private const DISK = 'local';

    public function create(): View
    {
        return view('auth.registrasi', [
            'roles' => Role::bolehDaftar(),
        ]);
    }

    public function store(RegistrasiRequest $request): RedirectResponse
    {
        $berkas = $request->file('surat_tugas');
        $path = null;

        try {
            $user = DB::transaction(function () use ($request, $berkas, &$path) {
                // PSKE-6: status ditetapkan server, tidak pernah diambil dari input.
                $user = new User($request->dataAkun());
                $user->status = StatusAkun::Menunggu;
                $user->save();

                // PSKE-5: nama berkas acak, disimpan per pengguna di disk privat.
                $path = $berkas->store("surat-tugas/{$user->id}", self::DISK);

                $user->dokumenPendukung()->create([
                    'jenis' => DokumenPendukung::JENIS_SURAT_TUGAS,
                    'nama_file' => mb_substr($berkas->getClientOriginalName(), 0, 255),
                    'path' => $path,
                    'mime' => $berkas->getMimeType(),
                    'ukuran' => $berkas->getSize(),
                ]);

                return $user;
            });
        } catch (Throwable $e) {
            // Akun dibatalkan oleh transaksi; jangan tinggalkan berkas yatim.
            if ($path) {
                Storage::disk(self::DISK)->delete($path);
            }
            report($e);

            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'surat_tugas']))
                ->withErrors(['surat_tugas' => 'Surat tugas gagal disimpan. Silakan coba lagi.']);
        }

        return redirect()
            ->route('registrasi.terkirim')
            ->with('registrasi_email', $user->email);
    }

    /**
     * PSKE-6: konfirmasi bahwa akun menunggu verifikasi. Pengguna tidak diarahkan ke panel.
     */
    public function terkirim(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('registrasi_email');

        if (! $email) {
            return redirect()->route('registrasi.create');
        }

        return view('auth.registrasi-terkirim', [
            'email' => $email,
            'status' => StatusAkun::Menunggu,
        ]);
    }
}
