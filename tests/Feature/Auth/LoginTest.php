<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * PBI-04 / PSKE-16 · Pengujian Login, logout, dan sesi per role.
 * Mencakup kriteria penerimaan PSKE-31, PSKE-32, PSKE-33, dan PSKE-34.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Tamu dapat membuka formulir login.
     */
    public function test_halaman_login_dapat_dibuka_oleh_tamu(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Masuk ke Kawan Pangan');
        $response->assertSee(route('registrasi.create'));
    }

    /**
     * PSKE-31 · Login Superadmin mengarahkan ke panel Superadmin (/admin).
     */
    public function test_login_berhasil_mengarahkan_superadmin_ke_panel_admin(): void
    {
        $superadmin = User::factory()->superadmin()->create([
            'email' => 'superadmin@kawanpangan.go.id',
            'password' => Hash::make('passwordSuper123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'superadmin@kawanpangan.go.id',
            'password' => 'passwordSuper123',
        ]);

        $this->assertAuthenticatedAs($superadmin);
        $response->assertRedirect(route('admin.dashboard'));
    }

    /**
     * PSKE-31 · Login Kepala Wilayah mengarahkan ke panel Kepala Wilayah (/panel/wilayah).
     */
    public function test_login_berhasil_mengarahkan_kepala_wilayah_ke_panel_wilayah(): void
    {
        $kepalaWilayah = User::factory()->kepalaWilayah('32.04')->create([
            'email' => 'kades@desa.id',
            'password' => Hash::make('passwordKades123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'kades@desa.id',
            'password' => 'passwordKades123',
        ]);

        $this->assertAuthenticatedAs($kepalaWilayah);
        $response->assertRedirect(route('panel.wilayah'));
    }

    /**
     * PSKE-31 · Login Badan Resmi mengarahkan ke panel Badan Resmi (/panel/instansi).
     */
    public function test_login_berhasil_mengarahkan_badan_resmi_ke_panel_instansi(): void
    {
        $badanResmi = User::factory()->badanResmi(1)->create([
            'email' => 'staf@bulog.go.id',
            'password' => Hash::make('passwordBulog123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'staf@bulog.go.id',
            'password' => 'passwordBulog123',
        ]);

        $this->assertAuthenticatedAs($badanResmi);
        $response->assertRedirect(route('panel.instansi'));
    }

    /**
     * PSKE-32 · Kata sandi disimpan dan diverifikasi sebagai hash bcrypt.
     */
    public function test_kata_sandi_disimpan_sebagai_hash_bcrypt(): void
    {
        $user = User::factory()->aktif()->create([
            'password' => 'rahasia123',
        ]);

        // Model User memiliki cast 'password' => 'hashed'
        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));

        $info = Hash::info($user->password);
        $this->assertSame('bcrypt', $info['algoName'] ?? 'bcrypt');
    }

    /**
     * Kredensial salah menghasilkan pesan umum tanpa membocorkan eksistensi akun.
     */
    public function test_kredensial_salah_menampilkan_pesan_umum_dan_tidak_login(): void
    {
        User::factory()->aktif()->create([
            'email' => 'terdaftar@kawanpangan.go.id',
            'password' => Hash::make('rahasia123'),
        ]);

        // Uji dengan password salah
        $response = $this->post(route('login'), [
            'email' => 'terdaftar@kawanpangan.go.id',
            'password' => 'passwordsalah',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Email atau kata sandi salah.',
        ]);
        $this->assertGuest();

        // Uji dengan email yang tidak terdaftar sama sekali
        $responseTakAda = $this->post(route('login'), [
            'email' => 'tidakada@kawanpangan.go.id',
            'password' => 'rahasia123',
        ]);

        $responseTakAda->assertSessionHasErrors([
            'email' => 'Email atau kata sandi salah.',
        ]);
        $this->assertGuest();
    }

    /**
     * PSKE-31 · Akun berstatus Menunggu ditolak masuk dan menampilkan pesan sesuai.
     */
    public function test_akun_menunggu_verifikasi_ditolak_masuk(): void
    {
        $user = User::factory()->create([
            'email' => 'pendaftar@desa.id',
            'password' => Hash::make('rahasia123'),
            'status' => StatusAkun::Menunggu,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'pendaftar@desa.id',
            'password' => 'rahasia123',
        ]);

        $response->assertSessionHasErrors([
            'email' => $user->pesanLoginDitolak(),
        ]);
        $this->assertGuest();
    }

    /**
     * PSKE-31 · Akun berstatus Ditolak menampilkan alasan penolakan dan tidak membuat sesi.
     */
    public function test_akun_ditolak_menampilkan_alasan_penolakan(): void
    {
        $user = User::factory()->create([
            'email' => 'ditolak@desa.id',
            'password' => Hash::make('rahasia123'),
            'status' => StatusAkun::Ditolak,
            'alasan_penolakan' => 'Surat tugas tidak terbaca dengan jelas',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'ditolak@desa.id',
            'password' => 'rahasia123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Pendaftaran Anda ditolak. Alasan: Surat tugas tidak terbaca dengan jelas',
        ]);
        $this->assertGuest();
    }

    /**
     * PSKE-31 · Akun berstatus Nonaktif ditolak masuk.
     */
    public function test_akun_nonaktif_ditolak_masuk(): void
    {
        $user = User::factory()->create([
            'email' => 'nonaktif@desa.id',
            'password' => Hash::make('rahasia123'),
            'status' => StatusAkun::Nonaktif,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'nonaktif@desa.id',
            'password' => 'rahasia123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Akun Anda telah dinonaktifkan.',
        ]);
        $this->assertGuest();
    }

    /**
     * PSKE-33 · Akun dikunci 15 menit setelah 5 kali gagal login berturut-turut.
     */
    public function test_akun_dikunci_15_menit_setelah_5_kali_gagal_login(): void
    {
        $email = 'target.kunci@kawanpangan.go.id';

        RateLimiter::clear(mb_strtolower($email).'|127.0.0.1');

        // Gagal 5 kali berturut-turut
        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('login'), [
                'email' => $email,
                'password' => 'passwordsalah',
            ]);
        }

        // Percobaan ke-6 harus ditolak oleh RateLimiter (Lockout)
        $responseKe6 = $this->post(route('login'), [
            'email' => $email,
            'password' => 'passwordsalah',
        ]);

        $responseKe6->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Akun dikunci sementara',
            session('errors')->first('email')
        );
        $this->assertGuest();
    }

    /**
     * PSKE-34 · Logout mengakhiri sesi dan diarahkan kembali ke beranda.
     */
    public function test_logout_mengakhiri_sesi_dan_kembali_ke_beranda(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('sukses', 'Anda telah berhasil keluar.');
    }

    /**
     * Pengguna tamu yang mengakses rute superadmin (/admin/wilayah) diarahkan ke login.
     */
    public function test_tamu_ke_admin_wilayah_diarahkan_ke_halaman_login(): void
    {
        $response = $this->get(route('admin.wilayah.index'));

        $response->assertRedirect(route('login'));
    }
}
