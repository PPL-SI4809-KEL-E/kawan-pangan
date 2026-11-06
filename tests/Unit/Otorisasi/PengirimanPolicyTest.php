<?php

namespace Tests\Unit\Otorisasi;

use App\Enums\Role;
use App\Models\User;
use App\Policies\PengirimanPolicy;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pengujian PengirimanPolicy.
 *
 * Menguji izin akses pengiriman bantuan pangan berdasarkan penugasan petugas,
 * instansi pengelola, tujuan wilayah, dan peran superadmin.
 */
class PengirimanPolicyTest extends TestCase
{
    private PengirimanPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PengirimanPolicy();
    }

    private function buatPengiriman(
        ?int $petugasId = null,
        ?int $instansiId = null,
        ?string $wilayahTujuanKode = null,
    ): Model {
        $model = new class extends Model
        {
            protected $guarded = [];
        };
        $model->forceFill([
            'petugas_id' => $petugasId,
            'instansi_id' => $instansiId,
            'wilayah_tujuan_kode' => $wilayahTujuanKode,
        ]);

        return $model;
    }

    // ── Superadmin ────────────────────────────────────────────────

    #[Test]
    public function superadmin_boleh_mengakses_semua_pengiriman(): void
    {
        $admin = new User(['role' => Role::Superadmin->value]);

        $this->assertTrue($this->policy->before($admin, 'view'));
        $this->assertTrue($this->policy->before($admin, 'update'));
        $this->assertTrue($this->policy->before($admin, 'updateCheckpoint'));
    }

    // ── Petugas (Badan Resmi): penugasan sendiri ──────────────────

    #[Test]
    public function petugas_boleh_memperbarui_pengiriman_yang_ditugaskan(): void
    {
        $petugas = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);
        $petugas->id = 10;

        $pengiriman = $this->buatPengiriman(petugasId: 10, instansiId: 1);

        $this->assertTrue($this->policy->update($petugas, $pengiriman));
        $this->assertTrue($this->policy->updateCheckpoint($petugas, $pengiriman));
    }

    // ── Petugas: pengiriman petugas lain → ditolak ────────────────

    #[Test]
    public function petugas_tidak_boleh_memperbarui_pengiriman_petugas_lain(): void
    {
        $petugasA = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);
        $petugasA->id = 10;

        // Pengiriman milik petugas B pada instansi yang sama.
        $pengirimanPetugasB = $this->buatPengiriman(petugasId: 20, instansiId: 1);

        $this->assertFalse($this->policy->update($petugasA, $pengirimanPetugasB));
        $this->assertFalse($this->policy->updateCheckpoint($petugasA, $pengirimanPetugasB));
    }

    #[Test]
    public function manipulasi_id_pengiriman_tetap_ditolak(): void
    {
        $petugas = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);
        $petugas->id = 10;

        // Pengiriman milik instansi lain dengan petugas lain.
        $pengirimanLain = $this->buatPengiriman(petugasId: 30, instansiId: 2);

        $this->assertFalse($this->policy->update($petugas, $pengirimanLain));
        $this->assertFalse($this->policy->updateCheckpoint($petugas, $pengirimanLain));
    }

    // ── Pengelola (Badan Resmi): instansi sendiri ─────────────────

    private function buatPengelola(int $id, int $instansiId): User
    {
        $pengelola = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => $instansiId,
        ]);
        $pengelola->id = $id;
        $pengelola->forceFill(['jabatan' => User::JABATAN_PENGELOLA]);

        return $pengelola;
    }

    #[Test]
    public function pengelola_boleh_memperbarui_pengiriman_instansinya(): void
    {
        $pengelola = $this->buatPengelola(id: 5, instansiId: 1);

        // Pengiriman instansi yang sama, petugas berbeda.
        $pengiriman = $this->buatPengiriman(petugasId: 10, instansiId: 1);

        $this->assertTrue($this->policy->update($pengelola, $pengiriman));
    }

    #[Test]
    public function pengelola_tidak_boleh_memperbarui_pengiriman_instansi_lain(): void
    {
        $pengelola = $this->buatPengelola(id: 5, instansiId: 1);

        $pengirimanInstansiLain = $this->buatPengiriman(petugasId: 30, instansiId: 2);

        $this->assertFalse($this->policy->update($pengelola, $pengirimanInstansiLain));
    }

    #[Test]
    public function badan_resmi_tanpa_jabatan_pengelola_tidak_dianggap_pengelola(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);
        $user->id = 5;

        $pengiriman = $this->buatPengiriman(petugasId: 10, instansiId: 1);

        $this->assertFalse($user->isPengelolaInstansi());
        $this->assertFalse($this->policy->update($user, $pengiriman));
    }

    #[Test]
    public function jabatan_pengelola_tidak_bisa_diisi_lewat_mass_assignment(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
            'jabatan' => User::JABATAN_PENGELOLA,
        ]);

        $this->assertNull($user->getAttribute('jabatan'));
        $this->assertFalse($user->isPengelolaInstansi());
    }

    #[Test]
    public function pengelola_tidak_boleh_update_checkpoint_jika_bukan_petugas(): void
    {
        $pengelola = $this->buatPengelola(id: 5, instansiId: 1);

        $pengiriman = $this->buatPengiriman(petugasId: 10, instansiId: 1);

        // Checkpoint hanya boleh diperbarui oleh petugas yang ditugaskan.
        $this->assertFalse($this->policy->updateCheckpoint($pengelola, $pengiriman));
    }

    // ── Badan Resmi: tanpa penugasan → ditolak ────────────────────

    #[Test]
    public function badan_resmi_tanpa_instansi_tidak_boleh_membuat_pengiriman(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => null,
        ]);

        $this->assertFalse($this->policy->create($user));
    }

    #[Test]
    public function badan_resmi_tanpa_instansi_tidak_boleh_update(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => null,
        ]);
        $user->id = 10;

        $pengiriman = $this->buatPengiriman(petugasId: 10, instansiId: null);

        // Instansi null pada kedua sisi → ditolak.
        $this->assertFalse($this->policy->update($user, $pengiriman));
    }

    // ── Kepala Wilayah ────────────────────────────────────────────

    #[Test]
    public function kepala_wilayah_boleh_melihat_pengiriman_ke_wilayahnya(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);

        $pengiriman = $this->buatPengiriman(
            petugasId: 10,
            instansiId: 1,
            wilayahTujuanKode: '32.04.12',
        );

        $this->assertTrue($this->policy->view($user, $pengiriman));
    }

    #[Test]
    public function kepala_wilayah_tidak_boleh_melihat_pengiriman_wilayah_lain(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);

        $pengiriman = $this->buatPengiriman(
            petugasId: 10,
            instansiId: 1,
            wilayahTujuanKode: '33.74.01',
        );

        $this->assertFalse($this->policy->view($user, $pengiriman));
    }

    #[Test]
    public function kepala_wilayah_tidak_boleh_mengubah_pengiriman(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);

        $pengiriman = $this->buatPengiriman(
            petugasId: 10,
            instansiId: 1,
            wilayahTujuanKode: '32.04.12',
        );

        $this->assertFalse($this->policy->update($user, $pengiriman));
        $this->assertFalse($this->policy->updateCheckpoint($user, $pengiriman));
    }

    // ── Kepala Wilayah tanpa penugasan ────────────────────────────

    #[Test]
    public function kepala_wilayah_tanpa_wilayah_tidak_boleh_melihat_pengiriman(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => null,
        ]);

        $pengiriman = $this->buatPengiriman(
            petugasId: 10,
            instansiId: 1,
            wilayahTujuanKode: '32.04',
        );

        $this->assertFalse($this->policy->view($user, $pengiriman));
    }
}
