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
        if ($user->role === Role::KepalaWilayah) {
            return trim((string) $user->kode_wilayah) !== '';
        }

        if ($user->role === Role::BadanResmi) {
            return $user->instansi_id !== null;
        }

        return false;
    }

    /**
     * Kepala Wilayah hanya boleh melihat laporan yang wilayahnya cocok.
     * Badan Resmi boleh melihat laporan untuk proses verifikasi.
     *
     * @param  Model  $laporan  Model laporan dengan atribut `kode_wilayah` (atau `wilayah_kode`).
     */
    public function view(User $user, Model $laporan): bool
    {
        if ($user->role === Role::BadanResmi) {
            return $user->instansi_id !== null;
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
            && trim((string) $user->kode_wilayah) !== '';
    }

    /**
     * Kepala Wilayah hanya boleh mengubah laporan di wilayahnya sendiri.
     *
     * @param  Model  $laporan  Model laporan dengan atribut `kode_wilayah` (atau `wilayah_kode`).
     */
    public function update(User $user, Model $laporan): bool
    {
        return $user->role === Role::KepalaWilayah
            && $this->wilayahCocok($user, $laporan);
    }

    /**
     * Kepala Wilayah hanya boleh menghapus/membatalkan laporan di wilayahnya sendiri.
     */
    public function delete(User $user, Model $laporan): bool
    {
        return $user->role === Role::KepalaWilayah
            && $this->wilayahCocok($user, $laporan);
    }

    /**
     * Badan Resmi yang memiliki penugasan instansi boleh memverifikasi laporan.
     */
    public function verifikasi(User $user, Model $laporan): bool
    {
        return $user->role === Role::BadanResmi
            && $user->instansi_id !== null;
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
        $kodeLaporan = $laporan->getAttribute('kode_wilayah')
            ?? $laporan->getAttribute('wilayah_kode');

        if ($kodeLaporan === null) {
            return false;
        }

        return $user->menguasaiWilayah((string) $kodeLaporan);
    }
}
