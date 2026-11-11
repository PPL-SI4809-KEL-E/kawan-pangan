<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AktivasiAkunRequest;
use App\Http\Requests\Admin\TolakAkunRequest;
use App\Models\DokumenPendukung;
use App\Models\Instansi;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PBI-06 / PSKE-17 · Verifikasi akun dan penugasan (KF-03 – KF-05 · UC04 – UC07 · Layar M-16).
 */
class AkunController extends Controller
{
    /** Disk privat untuk dokumen surat tugas (PSKE-5, PSKE-6). */
    private const DISK = 'local';

    /**
     * Tampilkan daftar akun dengan filter status (default: Menunggu).
     */
    public function index(Request $request): View
    {
        $statusDipilih = $request->query('status', StatusAkun::Menunggu->value);
        $pencarian = $request->query('q');

        $query = User::query()
            ->with(['dokumenPendukung', 'wilayah', 'instansi'])
            ->where('role', '!=', Role::Superadmin->value)
            ->latest('id');

        if ($statusDipilih && $statusDipilih !== 'SEMUA') {
            $query->where('status', $statusDipilih);
        }

        if ($pencarian) {
            $query->where(function ($q) use ($pencarian) {
                $q->where('name', 'like', "%{$pencarian}%")
                    ->orWhere('email', 'like', "%{$pencarian}%")
                    ->orWhere('no_hp', 'like', "%{$pencarian}%");
            });
        }

        $akun = $query->paginate(15)->withQueryString();

        // Hitung jumlah per status untuk tab filter
        $jumlahStatus = User::query()
            ->where('role', '!=', Role::Superadmin->value)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.akun.index', [
            'akun' => $akun,
            'statusDipilih' => $statusDipilih,
            'pencarian' => $pencarian,
            'jumlahStatus' => $jumlahStatus,
        ]);
    }

    /**
     * Tampilkan rincian pendaftar dan formulir penugasan/verifikasi.
     */
    public function show(Request $request, User $user): View
    {
        abort_if($user->isSuperadmin(), 403, 'Akun Superadmin tidak dapat dimodifikasi.');

        $user->load(['dokumenPendukung', 'wilayah', 'instansi', 'verifikator']);

        // Data opsi untuk formulir penugasan
        $daftarInstansi = Instansi::orderBy('nama')->get();

        // Wilayah pilihan pendaftar (jika ada) selalu disertakan
        $wilayahPendaftar = $user->kode_wilayah
            ? Wilayah::where('kode', $user->kode_wilayah)->first()
            : null;

        $pencarianWilayah = $request->query('q_wilayah');

        $queryWilayah = Wilayah::whereIn('level', [3, 4]);

        if ($pencarianWilayah) {
            $queryWilayah->where(function ($q) use ($pencarianWilayah) {
                $q->where('nama', 'like', "%{$pencarianWilayah}%")
                    ->orWhere('kode', 'like', "%{$pencarianWilayah}%");
            });
        }

        $daftarWilayah = $queryWilayah->orderBy('kode')->limit(100)->get();

        // Pastikan wilayah pilihan pendaftar selalu ada di daftar pilihan
        if ($wilayahPendaftar && ! $daftarWilayah->contains('kode', $wilayahPendaftar->kode)) {
            $daftarWilayah->prepend($wilayahPendaftar);
        }

        return view('admin.akun.show', [
            'user' => $user,
            'suratTugas' => $user->dokumenPendukung()->where('jenis', DokumenPendukung::JENIS_SURAT_TUGAS)->latest()->first(),
            'daftarInstansi' => $daftarInstansi,
            'daftarWilayah' => $daftarWilayah,
            'wilayahPendaftar' => $wilayahPendaftar,
            'pencarianWilayah' => $pencarianWilayah,
        ]);
    }

    /**
     * Endpoint pencarian wilayah AJAX untuk dropdown dinamis (level 3 & 4).
     */
    public function cariWilayah(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $query = Wilayah::whereIn('level', [3, 4]);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nama', 'like', "%{$q}%")
                    ->orWhere('kode', 'like', "%{$q}%");
            });
        }

        $hasil = $query->orderBy('kode')
            ->limit(50)
            ->get(['kode', 'nama', 'level']);

        return response()->json($hasil);
    }

    /**
     * Unduh surat tugas pendaftar dari penyimpanan privat (hanya Superadmin).
     */
    public function unduhSuratTugas(User $user): StreamedResponse
    {
        abort_if($user->isSuperadmin(), 403, 'Akun Superadmin tidak memiliki surat tugas.');

        $dokumen = $user->dokumenPendukung()
            ->where('jenis', DokumenPendukung::JENIS_SURAT_TUGAS)
            ->latest()
            ->firstOrFail();

        if (! Storage::disk(self::DISK)->exists($dokumen->path)) {
            abort(404, 'Berkas surat tugas tidak ditemukan di server.');
        }

        return Storage::disk(self::DISK)->download($dokumen->path, $dokumen->nama_file);
    }

    /**
     * PSKE-35 · Aktivasi akun pendaftar dengan penugasan wilayah atau instansi + peran.
     */
    public function aktivasi(AktivasiAkunRequest $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperadmin(), 403, 'Akun Superadmin tidak dapat dimodifikasi.');
        abort_if($user->status !== StatusAkun::Menunggu, 422, 'Hanya akun berstatus Menunggu yang dapat diaktifkan.');

        $data = [
            'status' => StatusAkun::Aktif,
            'verifikator_id' => $request->user()->id,
            'diverifikasi_pada' => now(),
            'alasan_penolakan' => null,
        ];

        if ($user->isKepalaWilayah()) {
            $data['kode_wilayah'] = $request->input('kode_wilayah');
            $data['instansi_id'] = null;
            $data['jabatan'] = null;
        } elseif ($user->isBadanResmi()) {
            $data['instansi_id'] = $request->input('instansi_id');
            $data['jabatan'] = $request->input('jabatan');
            $data['kode_wilayah'] = null;
        }

        // Jabatan dan status sengaja tidak fillable, diisi melalui forceFill
        $user->forceFill($data)->save();

        return redirect()->route('admin.akun.show', $user)
            ->with('sukses', "Akun {$user->name} berhasil diaktifkan dengan penugasan.");
    }

    /**
     * PSKE-36 · Tolak pendaftaran akun wajib dengan menyertakan alasan.
     */
    public function tolak(TolakAkunRequest $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperadmin(), 403, 'Akun Superadmin tidak dapat dimodifikasi.');
        abort_if($user->status !== StatusAkun::Menunggu, 422, 'Hanya akun berstatus Menunggu yang dapat ditolak.');

        $user->forceFill([
            'status' => StatusAkun::Ditolak,
            'alasan_penolakan' => $request->input('alasan_penolakan'),
            'verifikator_id' => $request->user()->id,
            'diverifikasi_pada' => now(),
        ])->save();

        return redirect()->route('admin.akun.show', $user)
            ->with('sukses', "Pendaftaran akun {$user->name} telah ditolak dengan alasan.");
    }

    /**
     * PSKE-37 · Nonaktifkan akun tanpa menghapus data atau riwayatnya.
     */
    public function nonaktifkan(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperadmin(), 403, 'Akun Superadmin tidak dapat dimodifikasi.');
        abort_if($user->status !== StatusAkun::Aktif, 422, 'Hanya akun berstatus Aktif yang dapat dinonaktifkan.');

        $user->forceFill([
            'status' => StatusAkun::Nonaktif,
        ])->save();

        return redirect()->route('admin.akun.show', $user)
            ->with('sukses', "Akun {$user->name} telah dinonaktifkan tanpa menghapus riwayat data.");
    }
}
