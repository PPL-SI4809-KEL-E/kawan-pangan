<?php

namespace Tests\Feature\Otorisasi;

use App\Enums\StatusAkun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pengujian middleware PeriksaRole dan integrasi otorisasi.
 *
 * Test ini mendaftarkan route sementara untuk memverifikasi bahwa
 * middleware `role` dan `auth` bekerja dengan benar pada level HTTP.
 */
class PeriksaRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route sementara khusus pengujian.
        Route::middleware(['web', 'auth', 'role:KEPALA_WILAYAH'])
            ->get('/test/kepala-wilayah', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', 'auth', 'role:BADAN_RESMI'])
            ->get('/test/badan-resmi', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', 'auth', 'role:KEPALA_WILAYAH,SUPERADMIN'])
            ->get('/test/multi-role', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', 'auth'])
            ->get('/test/auth-only', fn () => response()->json(['ok' => true]));
    }

    // ── Pengguna tanpa autentikasi ────────────────────────────────

    #[Test]
    public function tamu_diarahkan_ke_login(): void
    {
        // Endpoint privat yang memerlukan autentikasi.
        $response = $this->getJson('/test/auth-only');

        // Laravel default: 401 untuk JSON request tanpa auth.
        $response->assertStatus(401);
    }

    // ── Role cocok ────────────────────────────────────────────────

    #[Test]
    public function kepala_wilayah_boleh_mengakses_route_kepala_wilayah(): void
    {
        $user = User::factory()->kepalaWilayah()->create();

        $response = $this->actingAs($user)->getJson('/test/kepala-wilayah');

        $response->assertOk();
    }

    #[Test]
    public function badan_resmi_boleh_mengakses_route_badan_resmi(): void
    {
        $user = User::factory()->badanResmi()->create();

        $response = $this->actingAs($user)->getJson('/test/badan-resmi');

        $response->assertOk();
    }

    // ── Role tidak cocok → 403 ────────────────────────────────────

    #[Test]
    public function kepala_wilayah_tidak_boleh_mengakses_route_badan_resmi(): void
    {
        $user = User::factory()->kepalaWilayah()->create();

        $response = $this->actingAs($user)->getJson('/test/badan-resmi');

        $response->assertForbidden();
    }

    #[Test]
    public function badan_resmi_tidak_boleh_mengakses_route_kepala_wilayah(): void
    {
        $user = User::factory()->badanResmi()->create();

        $response = $this->actingAs($user)->getJson('/test/kepala-wilayah');

        $response->assertForbidden();
    }

    // ── Multi-role ────────────────────────────────────────────────

    #[Test]
    public function kepala_wilayah_boleh_mengakses_route_multi_role(): void
    {
        $user = User::factory()->kepalaWilayah()->create();

        $response = $this->actingAs($user)->getJson('/test/multi-role');

        $response->assertOk();
    }

    #[Test]
    public function superadmin_boleh_mengakses_route_multi_role(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->getJson('/test/multi-role');

        $response->assertOk();
    }

    #[Test]
    public function badan_resmi_tidak_boleh_mengakses_route_multi_role(): void
    {
        $user = User::factory()->badanResmi()->create();

        $response = $this->actingAs($user)->getJson('/test/multi-role');

        $response->assertForbidden();
    }

    // ── 403 tidak membocorkan data ────────────────────────────────

    #[Test]
    public function respons_403_tidak_mengandung_data_privat(): void
    {
        $user = User::factory()->kepalaWilayah()->create();

        $response = $this->actingAs($user)->getJson('/test/badan-resmi');

        $response->assertForbidden();
        $response->assertDontSee('password');
        $response->assertDontSee('remember_token');
    }

    // ── PSKE-17: bolehLogin() check ───────────────────────────────

    #[Test]
    public function akun_nonaktif_dikeluarkan_dan_dialihkan_ke_login(): void
    {
        $user = User::factory()->kepalaWilayah()->create([
            'status' => StatusAkun::Nonaktif,
        ]);

        $response = $this->actingAs($user)->get('/test/kepala-wilayah');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'Akun Anda telah dinonaktifkan.']);
        $this->assertGuest();
    }

    #[Test]
    public function akun_ditolak_dikeluarkan_dan_dialihkan_ke_login(): void
    {
        $user = User::factory()->kepalaWilayah()->create([
            'status' => StatusAkun::Ditolak,
            'alasan_penolakan' => 'Dokumen tidak lengkap.',
        ]);

        $response = $this->actingAs($user)->get('/test/kepala-wilayah');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'Pendaftaran Anda ditolak. Alasan: Dokumen tidak lengkap.']);
        $this->assertGuest();
    }

    #[Test]
    public function akun_menunggu_dikeluarkan_dan_dialihkan_ke_login(): void
    {
        $user = User::factory()->kepalaWilayah()->create([
            'status' => StatusAkun::Menunggu,
        ]);

        $response = $this->actingAs($user)->get('/test/kepala-wilayah');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
