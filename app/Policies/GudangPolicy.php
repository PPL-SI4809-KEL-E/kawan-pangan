<?php

namespace App\Policies;

use App\Enums\Role;
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
        return $user->role === Role::BadanResmi;
    }

    /**
     * Badan Resmi boleh melihat gudang jika gudang tersebut milik instansinya.
     *
     * @param  Model  $gudang  Model gudang dengan atribut `instansi_id`.
     */
    public function view(User $user, Model $gudang): bool
    {
        return $user->role === Role::BadanResmi
            && $this->instansiCocok($user, $gudang);
    }

    /**
     * Badan Resmi boleh memperbarui stok jika gudang milik instansinya.
     *
     * @param  Model  $gudang  Model gudang dengan atribut `instansi_id`.
     */
    public function update(User $user, Model $gudang): bool
    {
        return $user->role === Role::BadanResmi
            && $this->instansiCocok($user, $gudang);
    }

    /**
     * Badan Resmi boleh memperbarui stok jika gudang milik instansinya.
     * Alias yang lebih eksplisit untuk operasi perubahan stok.
     *
     * @param  Model  $gudang  Model gudang dengan atribut `instansi_id`.
     */
    public function updateStok(User $user, Model $gudang): bool
    {
        return $user->role === Role::BadanResmi
            && $this->instansiCocok($user, $gudang);
    }

    /**
     * Periksa apakah gudang termasuk instansi pengguna.
     *
     * Jika pengguna tidak memiliki penugasan instansi, akses ditolak.
     */
    private function instansiCocok(User $user, Model $gudang): bool
    {
        $instansiUser = $user->instansi_id;
        $instansiGudang = $gudang->getAttribute('instansi_id');

        if ($instansiUser === null || $instansiGudang === null) {
            return false;
        }

        return (int) $instansiUser === (int) $instansiGudang;
    }
}
