<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Gudang;
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
        if ($user->role === Role::BadanResmi) {
            return $user->instansi_id !== null;
        }

        if ($user->role === Role::KepalaWilayah) {
            return trim((string) $user->kode_wilayah) !== '';
        }

        return false;
    }

    /**
     * Badan Resmi boleh melihat pengiriman instansinya.
     * Kepala Wilayah boleh melihat pengiriman ke wilayahnya.
     *
     * @param  Model  $pengiriman  Model pengiriman dengan atribut
     *                             `gudang_asal_id`/`instansi_id`, `petugas_id`, `kode_wilayah_tujuan`/`wilayah_tujuan_kode`.
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
     * Hanya Pengelola instansi (Badan Resmi dengan jabatan PENGELOLA) yang boleh membuat pengiriman.
     */
    public function create(User $user): bool
    {
        return $user->isPengelolaInstansi();
    }

    /**
     * Izin memperbarui pengiriman dibedakan berdasarkan penugasan:
     * - Pengelola instansi boleh memperbarui pengiriman milik instansinya.
     * - Petugas hanya boleh memperbarui pengiriman yang ditugaskan kepadanya,
     *   meskipun pengiriman lain berada di instansi yang sama.
     *
     * @param  Model  $pengiriman  Model pengiriman.
     */
    public function update(User $user, Model $pengiriman): bool
    {
        if ($user->role !== Role::BadanResmi || $user->instansi_id === null) {
            return false;
        }

        if ($user->isPengelolaInstansi()) {
            return $this->instansiCocok($user, $pengiriman);
        }

        return $this->petugasCocok($user, $pengiriman)
            && $this->instansiCocok($user, $pengiriman);
    }

    /**
     * Hanya petugas yang ditugaskan pada pengiriman instansinya yang boleh memperbarui checkpoint.
     *
     * @param  Model  $pengiriman  Model pengiriman.
     */
    public function updateCheckpoint(User $user, Model $pengiriman): bool
    {
        if ($user->role !== Role::BadanResmi || $user->instansi_id === null) {
            return false;
        }

        return $this->petugasCocok($user, $pengiriman)
            && $this->instansiCocok($user, $pengiriman);
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
     * Periksa kecocokan instansi: user.instansi_id === instansi gudang asal pengiriman.
     */
    private function instansiCocok(User $user, Model $pengiriman): bool
    {
        $instansiPengiriman = $pengiriman->getAttribute('instansi_id');

        if ($instansiPengiriman === null && $pengiriman->relationLoaded('gudangAsal')) {
            $instansiPengiriman = $pengiriman->getRelation('gudangAsal')?->getAttribute('instansi_id');
        }

        if ($instansiPengiriman === null && $pengiriman->relationLoaded('gudang')) {
            $instansiPengiriman = $pengiriman->getRelation('gudang')?->getAttribute('instansi_id');
        }

        if ($instansiPengiriman === null && $pengiriman->getAttribute('gudang_asal_id') !== null) {
            $instansiPengiriman = Gudang::whereKey($pengiriman->getAttribute('gudang_asal_id'))->value('instansi_id');
        }

        if ($instansiPengiriman === null) {
            return false;
        }

        return $user->menguasaiInstansi((int) $instansiPengiriman);
    }

    /**
     * Periksa kecocokan wilayah tujuan: pengiriman menuju wilayah
     * yang berada di bawah cakupan Kepala Wilayah.
     */
    private function wilayahTujuanCocok(User $user, Model $pengiriman): bool
    {
        $kodeTujuan = $pengiriman->getAttribute('kode_wilayah_tujuan')
            ?? $pengiriman->getAttribute('wilayah_tujuan_kode');

        if ($kodeTujuan === null) {
            return false;
        }

        return $user->menguasaiWilayah((string) $kodeTujuan);
    }
}
