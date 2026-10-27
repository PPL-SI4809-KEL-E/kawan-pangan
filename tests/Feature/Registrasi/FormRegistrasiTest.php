<?php

namespace Tests\Feature\Registrasi;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PSKE-4 · Form registrasi tanpa pilihan role Superadmin.
 */
class FormRegistrasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array<string, mixed>
     */
    protected function dataValid(array $ubah = []): array
    {
        return array_merge([
            'role' => 'KEPALA_WILAYAH',
            'name' => 'Budi Santoso',
            'email' => 'budi@desa.id',
            'no_hp' => '081234567890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'wilayah_provinsi' => '32',
            'wilayah_kabkota' => '32.04',
            'wilayah_kecamatan' => '32.04.12',
            'wilayah_desa' => '32.04.12.2001',
            'setuju' => '1',
        ], $ubah);
    }

    public function test_halaman_registrasi_hanya_menampilkan_kepala_wilayah_dan_badan_resmi(): void
    {
        $response = $this->get('/registrasi');

        $response->assertOk()
            ->assertSee('value="KEPALA_WILAYAH"', false)
            ->assertSee('value="BADAN_RESMI"', false)
            ->assertDontSee('SUPERADMIN')
            ->assertDontSee('Superadmin');
    }

    public function test_role_superadmin_ditolak_walau_dikirim_langsung(): void
    {
        $response = $this->post('/registrasi', $this->dataValid(['role' => 'SUPERADMIN']));

        $response->assertSessionHasErrors(['role' => 'Jenis akun tidak diizinkan untuk registrasi.']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_role_wajib_dipilih(): void
    {
        $this->post('/registrasi', $this->dataValid(['role' => '']))
            ->assertSessionHasErrors('role');
    }

    public function test_kepala_wilayah_wajib_memilih_wilayah_sampai_kecamatan(): void
    {
        $this->post('/registrasi', $this->dataValid(['wilayah_kecamatan' => '', 'wilayah_desa' => '']))
            ->assertSessionHasErrors('kode_wilayah');
    }

    public function test_badan_resmi_wajib_memilih_instansi(): void
    {
        $this->post('/registrasi', $this->dataValid(['role' => 'BADAN_RESMI']))
            ->assertSessionHasErrors('instansi_id');
    }

    public function test_kata_sandi_lemah_dan_konfirmasi_berbeda_ditolak(): void
    {
        $this->post('/registrasi', $this->dataValid(['password' => 'abc12', 'password_confirmation' => 'abc12']))
            ->assertSessionHasErrors('password');
        $this->post('/registrasi', $this->dataValid(['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh']))
            ->assertSessionHasErrors('password');
        $this->post('/registrasi', $this->dataValid(['password_confirmation' => 'lain12345']))
            ->assertSessionHasErrors('password');
    }

    public function test_email_nomor_hp_dan_pernyataan_divalidasi(): void
    {
        $this->post('/registrasi', $this->dataValid(['email' => 'bukan-email', 'no_hp' => '12345', 'setuju' => '']))
            ->assertSessionHasErrors(['email', 'no_hp', 'setuju']);
    }

    public function test_email_yang_sudah_terdaftar_ditolak(): void
    {
        User::factory()->create(['email' => 'budi@desa.id']);

        $this->post('/registrasi', $this->dataValid(['email' => 'BUDI@desa.id']))
            ->assertSessionHasErrors(['email' => 'Email sudah terdaftar.']);
    }

    public function test_kepala_wilayah_tersimpan_dengan_kode_desa_dan_tanpa_instansi(): void
    {
        $this->post('/registrasi', $this->dataValid(['instansi_id' => '3']));

        $user = User::where('email', 'budi@desa.id')->firstOrFail();
        $this->assertSame(Role::KepalaWilayah, $user->role);
        $this->assertSame('32.04.12.2001', $user->kode_wilayah);
        $this->assertNull($user->instansi_id);
        $this->assertNotSame('rahasia123', $user->password);
    }

    public function test_camat_cukup_memilih_sampai_kecamatan(): void
    {
        $this->post('/registrasi', $this->dataValid(['wilayah_desa' => '']));

        $this->assertSame('32.04.12', User::where('email', 'budi@desa.id')->value('kode_wilayah'));
    }

    public function test_badan_resmi_tersimpan_dengan_instansi_dan_tanpa_wilayah(): void
    {
        $this->post('/registrasi', $this->dataValid(['role' => 'BADAN_RESMI', 'instansi_id' => '1']));

        $user = User::where('email', 'budi@desa.id')->firstOrFail();
        $this->assertSame(Role::BadanResmi, $user->role);
        $this->assertSame(1, $user->instansi_id);
        $this->assertNull($user->kode_wilayah);
    }

    public function test_opsi_wilayah_dan_instansi_tersedia_untuk_dropdown(): void
    {
        $this->getJson('/registrasi/opsi/wilayah')->assertOk()->assertJsonFragment(['kode' => '32']);
        $this->getJson('/registrasi/opsi/wilayah?induk=32.04.12')->assertOk()->assertJsonFragment(['kode' => '32.04.12.2001']);
        $this->getJson('/registrasi/opsi/instansi')->assertOk()->assertJsonFragment(['id' => 1]);
    }
}
