<?php

namespace Tests\Unit\Otorisasi;

use App\Enums\Role;
use App\Models\User;
use App\Policies\LaporanPolicy;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pengujian LaporanPolicy.
 *
 * Menguji izin akses laporan kebutuhan pangan berdasarkan cakupan hierarki
 * wilayah Kepala Wilayah, hak lihat Badan Resmi, dan peran superadmin.
 */
class LaporanPolicyTest extends TestCase
{
    private LaporanPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new LaporanPolicy();
    }

    /**
     * Buat objek anonim yang menyerupai model Laporan.
     */
    private function buatLaporan(string $wilayahKode): Model
    {
        $model = new class extends Model
        {
            protected $guarded = [];
        };
        $model->forceFill(['wilayah_kode' => $wilayahKode]);

        return $model;
    }

    // ── Superadmin ────────────────────────────────────────────────

    #[Test]
    public function superadmin_boleh_mengakses_semua_laporan(): void
    {
        $admin = new User(['role' => Role::Superadmin->value]);
        $laporan = $this->buatLaporan('33.74.01');

        $this->assertTrue($this->policy->before($admin, 'view'));
        $this->assertTrue($this->policy->before($admin, 'update'));
    }

    // ── Kepala Wilayah: akses wilayah sendiri ─────────────────────

    #[Test]
    public function kepala_wilayah_boleh_melihat_laporan_wilayahnya(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);
        $laporan = $this->buatLaporan('32.04');

        $this->assertTrue($this->policy->view($user, $laporan));
    }

    #[Test]
    public function kepala_wilayah_boleh_melihat_laporan_anak_wilayahnya(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);
        $laporan = $this->buatLaporan('32.04.12');

        $this->assertTrue($this->policy->view($user, $laporan));
    }

    #[Test]
    public function kepala_wilayah_boleh_melihat_laporan_desa_dalam_wilayahnya(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);
        $laporan = $this->buatLaporan('32.04.12.2001');

        $this->assertTrue($this->policy->view($user, $laporan));
    }

    // ── Kepala Wilayah: akses wilayah lain → ditolak ──────────────

    #[Test]
    public function kepala_wilayah_tidak_boleh_melihat_laporan_wilayah_lain(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);
        $laporan = $this->buatLaporan('32.73');

        $this->assertFalse($this->policy->view($user, $laporan));
    }

    #[Test]
    public function kepala_wilayah_tidak_boleh_melihat_laporan_provinsi_lain(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);
        $laporan = $this->buatLaporan('33.74.01');

        $this->assertFalse($this->policy->view($user, $laporan));
    }

    #[Test]
    public function ganti_id_pada_url_tetap_ditolak_jika_wilayah_berbeda(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32.04',
        ]);

        // Seolah-olah pengguna mengganti ID di URL ke laporan wilayah lain.
        $laporanWilayahLain = $this->buatLaporan('33.74.01');

        $this->assertFalse($this->policy->view($user, $laporanWilayahLain));
        $this->assertFalse($this->policy->update($user, $laporanWilayahLain));
    }

    // ── Kepala Wilayah: tanpa penugasan wilayah → ditolak ─────────

    #[Test]
    public function kepala_wilayah_tanpa_wilayah_tidak_boleh_melihat_laporan(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => null,
        ]);
        $laporan = $this->buatLaporan('32.04');

        $this->assertFalse($this->policy->view($user, $laporan));
    }

    #[Test]
    public function kepala_wilayah_tanpa_wilayah_tidak_boleh_membuat_laporan(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => null,
        ]);

        $this->assertFalse($this->policy->create($user));
    }

    // ── Badan Resmi ───────────────────────────────────────────────

    #[Test]
    public function badan_resmi_boleh_melihat_laporan_untuk_verifikasi(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);
        $laporan = $this->buatLaporan('32.04');

        $this->assertTrue($this->policy->view($user, $laporan));
    }

    #[Test]
    public function badan_resmi_tidak_boleh_mengubah_laporan(): void
    {
        $user = new User([
            'role' => Role::BadanResmi->value,
            'instansi_id' => 1,
        ]);
        $laporan = $this->buatLaporan('32.04');

        // Badan Resmi memverifikasi laporan, bukan mengubah isinya.
        $this->assertFalse($this->policy->update($user, $laporan));
    }

    // ── Prefix parsial tidak cocok ────────────────────────────────

    #[Test]
    public function prefix_tanpa_titik_tidak_cocok(): void
    {
        $user = new User([
            'role' => Role::KepalaWilayah->value,
            'kode_wilayah' => '32',
        ]);

        // '321' bukan anak dari '32' — hanya '32.xx' yang cocok.
        $laporan = $this->buatLaporan('321');
        $this->assertFalse($this->policy->view($user, $laporan));
    }
}
