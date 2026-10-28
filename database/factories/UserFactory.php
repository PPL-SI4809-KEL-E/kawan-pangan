<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\StatusAkun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  State untuk role dan otorisasi
    // ──────────────────────────────────────────────────────────────

    /**
     * Akun aktif (sudah diverifikasi Superadmin).
     */
    public function aktif(): static
    {
        return $this->state(fn () => [
            'status' => StatusAkun::Aktif->value,
        ]);
    }

    /**
     * Kepala Wilayah dengan kode_wilayah tertentu.
     */
    public function kepalaWilayah(string $kodeWilayah = '32.04'): static
    {
        return $this->aktif()->state(fn () => [
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => $kodeWilayah,
            'instansi_id' => null,
        ]);
    }

    /**
     * Badan Resmi (pengelola) dengan instansi_id tertentu.
     */
    public function badanResmi(int $instansiId = 1): static
    {
        return $this->aktif()->state(fn () => [
            'role' => Role::BadanResmi->value,
            'instansi_id' => $instansiId,
            'kode_wilayah' => null,
        ]);
    }

    /**
     * Superadmin.
     */
    public function superadmin(): static
    {
        return $this->aktif()->state(fn () => [
            'role' => Role::Superadmin->value,
            'kode_wilayah' => null,
            'instansi_id' => null,
        ]);
    }
}

