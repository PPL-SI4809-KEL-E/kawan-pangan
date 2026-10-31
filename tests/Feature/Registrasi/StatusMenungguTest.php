<?php

namespace Tests\Feature\Registrasi;

use App\Enums\StatusAkun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PSKE-6 · Akun baru berstatus Menunggu dan belum bisa login.
 */
class StatusMenungguTest extends TestCase
{
    use DataRegistrasi, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_akun_baru_berstatus_menunggu(): void
    {
        $this->post('/registrasi', $this->dataValid());

        $user = User::where('email', 'budi@desa.id')->firstOrFail();
        $this->assertSame(StatusAkun::Menunggu, $user->status);
        $this->assertNull($user->verifikator_id);
        $this->assertNull($user->diverifikasi_pada);
    }

    public function test_status_tidak_bisa_dipaksa_aktif_dari_input(): void
    {
        $this->post('/registrasi', $this->dataValid(['status' => 'AKTIF', 'verifikator_id' => 1]));

        $user = User::where('email', 'budi@desa.id')->firstOrFail();
        $this->assertSame(StatusAkun::Menunggu, $user->status);
        $this->assertNull($user->verifikator_id);
    }

    public function test_setelah_daftar_diarahkan_ke_halaman_menunggu_verifikasi_dan_tidak_login(): void
    {
        $this->post('/registrasi', $this->dataValid())
            ->assertRedirect(route('registrasi.terkirim'));

        $this->assertGuest();

        $this->withSession(['registrasi_email' => 'budi@desa.id'])
            ->get(route('registrasi.terkirim'))
            ->assertOk()
            ->assertSee('Pendaftaran Terkirim')
            ->assertSee('Menunggu verifikasi')
            ->assertSee('budi@desa.id')
            ->assertSee('Anda belum dapat masuk');
    }

    public function test_halaman_terkirim_tanpa_registrasi_kembali_ke_formulir(): void
    {
        $this->get(route('registrasi.terkirim'))->assertRedirect(route('registrasi.create'));
    }

    public function test_akun_menunggu_tidak_bisa_login(): void
    {
        $this->post('/registrasi', $this->dataValid());
        $user = User::where('email', 'budi@desa.id')->firstOrFail();

        // Pola yang dipakai login PBI-04: kredensial benar tetap ditolak bila status bukan AKTIF.
        $this->assertFalse(Auth::attempt([
            'email' => 'budi@desa.id',
            'password' => 'rahasia123',
            'status' => StatusAkun::Aktif->value,
        ]));
        $this->assertGuest();

        $this->assertFalse($user->bolehLogin());
        $this->assertStringContainsString('menunggu verifikasi', $user->pesanLoginDitolak());
    }

    public function test_akun_yang_sudah_diaktifkan_bisa_login(): void
    {
        $this->post('/registrasi', $this->dataValid());
        $user = User::where('email', 'budi@desa.id')->firstOrFail();
        $user->forceFill(['status' => StatusAkun::Aktif])->save();

        $this->assertTrue(Auth::attempt([
            'email' => 'budi@desa.id',
            'password' => 'rahasia123',
            'status' => StatusAkun::Aktif->value,
        ]));
        $this->assertNull($user->fresh()->pesanLoginDitolak());
    }

    public function test_pesan_login_untuk_akun_ditolak_menampilkan_alasan(): void
    {
        $user = User::factory()->make([]);
        $user->forceFill(['status' => StatusAkun::Ditolak, 'alasan_penolakan' => 'Surat tugas tidak terbaca']);

        $this->assertFalse($user->bolehLogin());
        $this->assertSame('Pendaftaran Anda ditolak. Alasan: Surat tugas tidak terbaca', $user->pesanLoginDitolak());
    }
}
