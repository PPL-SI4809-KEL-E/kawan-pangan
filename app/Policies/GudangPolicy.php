<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Gudang;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy otorisasi untuk gudang dan stok pangan.
 *
 * Pengelola (Badan Resmi) hanya boleh mengakses dan mengubah stok pada gudang
 * yang berada di bawah naungan instansinya sendiri. Superadmin memiliki
 * akses penuh untuk mengelola semua gudang.
 */
class GudangPolicy
{
    /**
     * Superadmin boleh melakukan apa pun terhadap gudang.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === Role::Superadmin) {
            return true;
        }

        return null;
    }

    /**
     * Badan Resmi boleh melihat daftar gudang instansinya.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === Role::BadanResmi
            && $user->instansi_id !== null;
    }

    /**
     * Badan Resmi boleh melihat gudang jika gudang tersebut milik instansinya.
     *
     * @param  Model  $gudang  Model gudang atau stok dengan atribut `instansi_id` / `gudang_id`.
     */
    public function view(User $user, Model $gudang): bool
    {
        return $user->role === Role::BadanResmi
            && $this->instansiCocok($user, $gudang);
    }

    /**
     * Hanya Pengelola instansi (Badan Resmi dengan jabatan PENGELOLA) yang boleh
     * memperbarui gudang/stok milik instansinya sendiri (PSKE-39).
     *
     * @param  Model  $gudang  Model gudang atau stok dengan atribut `instansi_id` / `gudang_id`.
     */
    public function update(User $user, Model $gudang): bool
    {
        return $user->isPengelolaInstansi()
            && $this->instansiCocok($user, $gudang);
    }

    /**
     * Hanya Pengelola instansi (Badan Resmi dengan jabatan PENGELOLA) yang boleh
     * memperbarui stok jika gudang milik instansinya sendiri (PSKE-39).
     *
     * @param  Model  $gudang  Model gudang atau stok dengan atribut `instansi_id` / `gudang_id`.
     */
    public function updateStok(User $user, Model $gudang): bool
    {
        return $user->isPengelolaInstansi()
            && $this->instansiCocok($user, $gudang);
    }

    /**
     * Badan Resmi dengan peran PENGELOLA boleh mengatur cakupan gudang instansinya (PSKE-48 / PSKE-51).
     *
     * @param  Model  $gudang  Model gudang dengan atribut `instansi_id`.
     */
    public function kelolaCakupan(User $user, Model $gudang): bool
    {
        return $user->isPengelolaInstansi()
            && $this->instansiCocok($user, $gudang);
    }

    /**
     * Periksa apakah gudang (atau stok gudang) termasuk instansi pengguna.
     *
     * Jika pengguna tidak memiliki penugasan instansi, akses ditolak.
     */
    private function instansiCocok(User $user, Model $gudang): bool
    {
        $instansiGudang = $gudang->getAttribute('instansi_id');

        if ($instansiGudang === null && $gudang->relationLoaded('gudang')) {
            $instansiGudang = $gudang->getRelation('gudang')?->getAttribute('instansi_id');
        }

        if ($instansiGudang === null && $gudang->getAttribute('gudang_id') !== null) {
            $instansiGudang = Gudang::whereKey($gudang->getAttribute('gudang_id'))->value('instansi_id');
        }

        if ($instansiGudang === null) {
            return false;
        }

        return $user->menguasaiInstansi((int) $instansiGudang);
    }
}