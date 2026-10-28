<?php

namespace Tests\Unit\Otorisasi;

use App\Enums\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pengujian helper otorisasi pada model User.
 *
 * Test ini memastikan metode isSuperadmin(), isKepalaWilayah(),
 * isBadanResmi(), menguasaiWilayah(), dan menguasaiInstansi()
 * berperilaku benar di berbagai kondisi.
 */
class UserOtorisasiHelperTest extends TestCase
{
    // ── Helper role ────────────────────────────────────────────────

    #[Test]
    public function kepala_wilayah_terdeteksi_benar(): void
    {
        $user = new User(['role' => Role::KepalaWilayah->value]);

        $this->assertTrue($user->isKepalaWilayah());
        $this->assertFalse($user->isBadanResmi());
        $this->assertFalse($user->isSuperadmin());
    }

    #[Test]
    public function badan_resmi_terdeteksi_benar(): void
    {
        $user = new User(['role' => Role::BadanResmi->value]);

        $this->assertTrue($user->isBadanResmi());
        $this->assertFalse($user->isKepalaWilayah());
        $this->assertFalse($user->isSuperadmin());
    }

    #[Test]
    public function superadmin_terdeteksi_benar(): void
    {
        $user = new User(['role' => Role::Superadmin->value]);

        $this->assertTrue($user->isSuperadmin());
        $this->assertFalse($user->isKepalaWilayah());
        $this->assertFalse($user->isBadanResmi());
    }

    // ── menguasaiWilayah ──────────────────────────────────────────

    #[Test]
    public function wilayah_cocok_persis(): void
    {
        $user = new User(['kode_wilayah' => '32.04']);

        $this->assertTrue($user->menguasaiWilayah('32.04'));
    }

    #[Test]
    public function wilayah_anak_cocok_dengan_prefix(): void
    {
        $user = new User(['kode_wilayah' => '32.04']);

        $this->assertTrue($user->menguasaiWilayah('32.04.12'));
        $this->assertTrue($user->menguasaiWilayah('32.04.12.2001'));
        $this->assertTrue($user->menguasaiWilayah('32.04.13'));
    }

    #[Test]
    public function wilayah_lain_ditolak(): void
    {
        $user = new User(['kode_wilayah' => '32.04']);

        $this->assertFalse($user->menguasaiWilayah('32.73'));
        $this->assertFalse($user->menguasaiWilayah('33'));
        $this->assertFalse($user->menguasaiWilayah('32.73.01'));
    }

    #[Test]
    public function prefix_parsial_tidak_cocok(): void
    {
        // kode_wilayah '32' tidak boleh cocok dengan '321' (bukan anak hierarki).
        $user = new User(['kode_wilayah' => '32']);

        $this->assertFalse($user->menguasaiWilayah('321'));
        $this->assertTrue($user->menguasaiWilayah('32.04'));
    }

    #[Test]
    public function wilayah_null_selalu_ditolak(): void
    {
        $user = new User(['kode_wilayah' => null]);

        $this->assertFalse($user->menguasaiWilayah('32.04'));
    }

    // ── menguasaiInstansi ─────────────────────────────────────────

    #[Test]
    public function instansi_cocok(): void
    {
        $user = new User(['instansi_id' => 1]);

        $this->assertTrue($user->menguasaiInstansi(1));
    }

    #[Test]
    public function instansi_lain_ditolak(): void
    {
        $user = new User(['instansi_id' => 1]);

        $this->assertFalse($user->menguasaiInstansi(2));
        $this->assertFalse($user->menguasaiInstansi(99));
    }

    #[Test]
    public function instansi_null_selalu_ditolak(): void
    {
        $user = new User(['instansi_id' => null]);

        $this->assertFalse($user->menguasaiInstansi(1));
    }
}
