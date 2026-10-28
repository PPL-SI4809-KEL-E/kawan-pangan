<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy otorisasi untuk laporan kebutuhan pangan.
 *
 * Kepala Wilayah hanya boleh mengakses laporan yang wilayahnya berada di bawah
 * kewenangannya berdasarkan hierarki kode wilayah. Badan Resmi dapat melihat
 * laporan untuk keperluan verifikasi dan penyaluran bantuan pangan.
 * Superadmin memiliki akses penuh untuk pengawasan sistem.
 */
class LaporanPolicy
{
    /**
     * Superadmin boleh melakukan apa pun terhadap laporan.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === Role::Superadmin) {
            return true;
        }

        return null;
    }

    /**
     * Kepala Wilayah boleh melihat daftar laporan (daftar difilter di query).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === Role::KepalaWilayah
            || $user->role === Role::BadanResmi;
    }

    /**
     * Kepala Wilayah hanya boleh melihat laporan yang wilayahnya cocok.
     * Badan Resmi boleh melihat laporan untuk proses verifikasi.
     *
     * @param  Model  $laporan  Model laporan dengan atribut `wilayah_kode`.
     */
    public function view(User $user, Model $laporan): bool
    {
        if ($user->role === Role::BadanResmi) {
            return true;
        }

        return $user->role === Role::KepalaWilayah
            && $this->wilayahCocok($user, $laporan);
    }

    /**
     * Hanya Kepala Wilayah yang boleh membuat laporan, dan hanya untuk
     * wilayahnya sendiri (divalidasi saat store, bukan di sini).
     */
    public function create(User $user): bool
    {
        return $user->role === Role::KepalaWilayah
            && $user->kode_wilayah !== null;
    }

    /**
     * Kepala Wilayah hanya boleh mengubah laporan di wilayahnya sendiri.
     *
     * @param  Model  $laporan  Model laporan dengan atribut `wilayah_kode`.
     */
    public function update(User $user, Model $laporan): bool
    {
        return $user->role === Role::KepalaWilayah
            && $this->wilayahCocok($user, $laporan);
    }

    /**
     * Periksa apakah kode_wilayah laporan berada di bawah cakupan wilayah
     * pengguna. Menggunakan pencocokan prefix: jika pengguna bertugas di
     * kode `32.04`, maka laporan `32.04.12` atau `32.04.12.2001` juga cocok.
     *
     * Jika pengguna tidak memiliki penugasan wilayah, akses ditolak.
     */
    private function wilayahCocok(User $user, Model $laporan): bool
    {
        $kodeUser = $user->kode_wilayah;
        $kodeLaporan = $laporan->getAttribute('wilayah_kode');

        if ($kodeUser === null || $kodeLaporan === null) {
            return false;
        }

        // Wilayah cocok jika kode laporan sama persis atau berada di bawah
        // hierarki wilayah pengguna (prefix match dengan pemisah titik).
        return $kodeLaporan === $kodeUser
            || str_starts_with($kodeLaporan, $kodeUser.'.');
    }
}
