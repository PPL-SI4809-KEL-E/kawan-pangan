<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy otorisasi untuk pengiriman bantuan pangan.
 *
 * Petugas (Badan Resmi) hanya boleh memperbarui pengiriman yang
 * ditugaskan kepadanya. Pengelola instansi dapat melihat dan mengelola
 * pengiriman instansinya. Kepala Wilayah dapat melihat pengiriman
 * menuju wilayahnya. Superadmin memiliki akses penuh.
 */
class PengirimanPolicy
{
    /**
     * Superadmin boleh melakukan apa pun terhadap pengiriman.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === Role::Superadmin) {
            return true;
        }

        return null;
    }

    /**
     * Badan Resmi boleh melihat daftar pengiriman.
     * Kepala Wilayah boleh melihat pengiriman masuk ke wilayahnya.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === Role::BadanResmi
            || $user->role === Role::KepalaWilayah;
    }

    /**
     * Badan Resmi boleh melihat pengiriman instansinya.
     * Kepala Wilayah boleh melihat pengiriman ke wilayahnya.
     *
     * @param  Model  $pengiriman  Model pengiriman dengan atribut
     *                             `instansi_id`, `petugas_id`, `wilayah_tujuan_kode`.
     */
    public function view(User $user, Model $pengiriman): bool
    {
        if ($user->role === Role::BadanResmi) {
            if ($user->instansi_id === null) {
                return false;
            }

            return $this->instansiCocok($user, $pengiriman)
                || $this->petugasCocok($user, $pengiriman);
        }

        if ($user->role === Role::KepalaWilayah) {
            return $this->wilayahTujuanCocok($user, $pengiriman);
        }

        return false;
    }

    /**
     * Hanya Badan Resmi yang boleh membuat pengiriman.
     */
    public function create(User $user): bool
    {
        return $user->role === Role::BadanResmi
            && $user->instansi_id !== null;
    }

    /**
     * Petugas hanya boleh memperbarui pengiriman yang ditugaskan kepadanya.
     * Pengelola (Badan Resmi) boleh memperbarui pengiriman instansinya.
     *
     * @param  Model  $pengiriman  Model pengiriman.
     */
    public function update(User $user, Model $pengiriman): bool
    {
        if ($user->role !== Role::BadanResmi || $user->instansi_id === null) {
            return false;
        }

        return $this->petugasCocok($user, $pengiriman)
            || $this->instansiCocok($user, $pengiriman);
    }

    /**
     * Hanya petugas yang ditugaskan yang boleh memperbarui checkpoint.
     *
     * @param  Model  $pengiriman  Model pengiriman.
     */
    public function updateCheckpoint(User $user, Model $pengiriman): bool
    {
        if ($user->role !== Role::BadanResmi || $user->instansi_id === null) {
            return false;
        }

        return $this->petugasCocok($user, $pengiriman);
    }

    /**
     * Periksa kecocokan petugas: user.id === pengiriman.petugas_id.
     */
    private function petugasCocok(User $user, Model $pengiriman): bool
    {
        $petugasId = $pengiriman->getAttribute('petugas_id');

        if ($petugasId === null) {
            return false;
        }

        return (int) $user->id === (int) $petugasId;
    }

    /**
     * Periksa kecocokan instansi: user.instansi_id === pengiriman.instansi_id.
     */
    private function instansiCocok(User $user, Model $pengiriman): bool
    {
        $instansiUser = $user->instansi_id;
        $instansiPengiriman = $pengiriman->getAttribute('instansi_id');

        if ($instansiUser === null || $instansiPengiriman === null) {
            return false;
        }

        return (int) $instansiUser === (int) $instansiPengiriman;
    }

    /**
     * Periksa kecocokan wilayah tujuan: pengiriman menuju wilayah
     * yang berada di bawah cakupan Kepala Wilayah.
     */
    private function wilayahTujuanCocok(User $user, Model $pengiriman): bool
    {
        $kodeUser = $user->kode_wilayah;
        $kodeTujuan = $pengiriman->getAttribute('wilayah_tujuan_kode');

        if ($kodeUser === null || $kodeTujuan === null) {
            return false;
        }

        return $kodeTujuan === $kodeUser
            || str_starts_with($kodeTujuan, $kodeUser.'.');
    }
}
