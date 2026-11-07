<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * PSKE-32 · Seeder akun Superadmin pertama dari nilai konfigurasi environment.
 */
class SuperadminSeeder extends Seeder
{
    /**
     * Jalankan database seeds.
     */
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL');
        $password = env('SUPERADMIN_PASSWORD');

        // Jangan membuat akun jika konfigurasi di .env belum diisi.
        if (empty($email) || empty($password)) {
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Superadmin Kawan Pangan',
                'password' => Hash::make($password),
                'role' => Role::Superadmin,
                'status' => StatusAkun::Aktif,
                'diverifikasi_pada' => now(),
            ]
        );
    }
}
