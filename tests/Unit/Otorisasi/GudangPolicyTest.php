<?php

namespace Tests\Unit\Otorisasi;

use App\Enums\Role;
use App\Models\User;
use App\Policies\GudangPolicy;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pengujian GudangPolicy.
 *
 * Menguji izin akses dan modifikasi stok gudang berdasarkan instansi
 * pengelola, pembatasan untuk Kepala Wilayah, dan hak penuh superadmin.
 */
class GudangPolicyTest extends TestCase
{
    private GudangPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new GudangPolicy();
    }

    private function buatGudang(int $instansiId): Model
    {
        $model = new class extends Model
        {
            protected $guarded = [];
        };
        $model->forceFill(['instansi_id' => $instansiId]);

        return $model;
    }

    // ── Superadmin ────────────────────────────────────────────────

    #[Test]
    public function superadmin_boleh_mengakses_semua_gudang(): void
    {
        $admin = new User(['role' => Role::Superadmin->value]);

        $this->assertTrue($this->policy->before($admin, 'view'));
        $this->assertTrue($this->policy->before($admin, 'update'));
        $this->assertTrue($this->policy->before($admin, 'updateStok'));
    }

    // ── Badan Resmi: instansi sendiri ─────────────────────────────

    #[Test]
    public function pengelola_boleh_melihat_gudang_instansinya(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);
        $gudang = $this->buatGudang(1);

        $this->assertTrue($this->policy->view($user, $gudang));
    }

    #[Test]
    public function pengelola_boleh_mengubah_stok_gudang_instansinya(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);
        $gudang = $this->buatGudang(1);

        $this->assertTrue($this->policy->update($user, $gudang));
        $this->assertTrue($this->policy->updateStok($user, $gudang));
    }

    // ── Badan Resmi: instansi lain → ditolak ──────────────────────

    #[Test]
    public function pengelola_tidak_boleh_melihat_gudang_instansi_lain(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);
        $gudang = $this->buatGudang(2);

        $this->assertFalse($this->policy->view($user, $gudang));
    }

    #[Test]
    public function pengelola_tidak_boleh_mengubah_stok_gudang_instansi_lain(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);
        $gudang = $this->buatGudang(2);

        $this->assertFalse($this->policy->update($user, $gudang));
        $this->assertFalse($this->policy->updateStok($user, $gudang));
    }

    #[Test]
    public function manipulasi_id_gudang_tetap_ditolak(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);

        // Seolah-olah pengguna mengirim ID gudang instansi lain lewat request.
        $gudangInstansiLain = $this->buatGudang(3);

        $this->assertFalse($this->policy->updateStok($user, $gudangInstansiLain));
    }

    // ── Badan Resmi: tanpa instansi → ditolak ─────────────────────

    #[Test]
    public function pengelola_tanpa_instansi_tidak_boleh_mengubah_stok(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => null,
        ]);
        $gudang = $this->buatGudang(1);

        $this->assertFalse($this->policy->updateStok($user, $gudang));
        $this->assertFalse($this->policy->view($user, $gudang));
    }

    // ── Kepala Wilayah: tidak boleh mengelola gudang ──────────────

    #[Test]
    public function kepala_wilayah_tidak_boleh_melihat_daftar_gudang(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);

        $this->assertFalse($this->policy->viewAny($user));
    }

    #[Test]
    public function kepala_wilayah_tidak_boleh_mengubah_stok(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);
        $gudang = $this->buatGudang(1);

        $this->assertFalse($this->policy->updateStok($user, $gudang));
    }
}
