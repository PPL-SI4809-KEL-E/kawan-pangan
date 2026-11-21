<?php

namespace Tests\Feature\Otorisasi;

use App\Enums\StatusAkun;
use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\Komoditas;
use App\Models\Laporan;
use App\Models\Pengiriman;
use App\Models\Stok;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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

        Route::middleware(['web', 'auth', 'role:KEPALA_WILAYAH,BADAN_RESMI,SUPERADMIN'])
            ->get('/test/laporan/{laporan}', function (Laporan $laporan) {
                Gate::authorize('view', $laporan);

                return response()->json(['kode_laporan' => $laporan->kode_laporan]);
            });

        Route::middleware(['web', 'auth', 'role:BADAN_RESMI,SUPERADMIN'])
            ->post('/test/gudang/{gudang}/stok', function (Gudang $gudang) {
                Gate::authorize('updateStok', $gudang);

                return response()->json(['gudang_id' => $gudang->id]);
            });

        Route::middleware(['web', 'auth', 'role:BADAN_RESMI,SUPERADMIN'])
            ->patch('/test/pengiriman/{pengiriman}', function (Pengiriman $pengiriman) {
                Gate::authorize('update', $pengiriman);

                return response()->json(['no_resi' => $pengiriman->no_resi]);
            });

        Route::middleware(['web', 'auth', 'role:BADAN_RESMI,SUPERADMIN'])
            ->patch('/test/pengiriman/{pengiriman}/checkpoint', function (Pengiriman $pengiriman) {
                Gate::authorize('updateCheckpoint', $pengiriman);

                return response()->json(['no_resi' => $pengiriman->no_resi]);
            });
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

    // ── Status akun belum/tidak boleh login ditolak ───────────────

    #[Test]
    public function akun_belum_aktif_ditolak_mengakses_route_terproteksi(): void
    {
        foreach ([StatusAkun::Menunggu, StatusAkun::Ditolak, StatusAkun::Nonaktif] as $status) {
            $user = User::factory()->kepalaWilayah()->create(['status' => $status]);

            $this->actingAs($user)
                ->getJson('/test/kepala-wilayah')
                ->assertForbidden();
        }
    }

    // ── Integrasi HTTP & Database PBI-07 (PSKE-38, PSKE-39, PSKE-40) ──

    #[Test]
    public function pske_38_kepala_wilayah_a_tidak_dapat_membuka_laporan_wilayah_b_di_database(): void
    {
        Wilayah::create(['kode' => '32', 'nama' => 'Jawa Barat']);
        Wilayah::create(['kode' => '32.04', 'nama' => 'Kab. Bandung', 'kode_induk' => '32']);
        Wilayah::create(['kode' => '32.04.12', 'nama' => 'Dayeuhkolot', 'kode_induk' => '32.04']);
        Wilayah::create(['kode' => '32.73', 'nama' => 'Kota Bandung', 'kode_induk' => '32']);
        Wilayah::create(['kode' => '32.73.01', 'nama' => 'Sukasari', 'kode_induk' => '32.73']);

        $kepalaA = User::factory()->kepalaWilayah('32.04.12')->create();
        $kepalaB = User::factory()->kepalaWilayah('32.73.01')->create();

        $laporanA = Laporan::create([
            'kode_laporan' => 'LAP-A-001',
            'pelapor_id' => $kepalaA->id,
            'kode_wilayah' => '32.04.12',
            'jenis' => 'DARURAT',
            'urgensi' => 'TINGGI',
            'status' => Laporan::STATUS_DIAJUKAN,
        ]);

        $laporanB = Laporan::create([
            'kode_laporan' => 'LAP-B-001',
            'pelapor_id' => $kepalaB->id,
            'kode_wilayah' => '32.73.01',
            'jenis' => 'RUTIN',
            'urgensi' => 'SEDANG',
            'status' => Laporan::STATUS_DIAJUKAN,
        ]);

        $this->actingAs($kepalaA)
            ->getJson("/test/laporan/{$laporanA->id}")
            ->assertOk()
            ->assertJson(['kode_laporan' => 'LAP-A-001']);

        $this->actingAs($kepalaA)
            ->getJson("/test/laporan/{$laporanB->id}")
            ->assertForbidden()
            ->assertDontSee('LAP-B-001');

        $daftarTerfilter = Laporan::untukPengguna($kepalaA)->pluck('kode_laporan')->all();
        $this->assertSame(['LAP-A-001'], $daftarTerfilter);
    }

    #[Test]
    public function pske_39_pengelola_hanya_mengubah_stok_gudang_instansinya_di_database(): void
    {
        Wilayah::create(['kode' => '32', 'nama' => 'Jawa Barat']);
        $instansiA = Instansi::create(['nama' => 'Bulog A', 'alamat' => 'Jl. A', 'latitude' => -6.9, 'longitude' => 107.6]);
        $instansiB = Instansi::create(['nama' => 'Bulog B', 'alamat' => 'Jl. B', 'latitude' => -6.8, 'longitude' => 107.5]);

        $gudangA = Gudang::create([
            'nama' => 'Gudang A',
            'instansi_id' => $instansiA->id,
            'kode_wilayah' => '32',
            'alamat' => 'Alamat A',
            'latitude' => -6.9,
            'longitude' => 107.6,
            'aktif' => true,
        ]);
        $gudangB = Gudang::create([
            'nama' => 'Gudang B',
            'instansi_id' => $instansiB->id,
            'kode_wilayah' => '32',
            'alamat' => 'Alamat B',
            'latitude' => -6.8,
            'longitude' => 107.5,
            'aktif' => true,
        ]);

        $komoditas = Komoditas::create(['nama' => 'Beras Uji', 'satuan' => 'kg']);
        $stokA = Stok::create([
            'gudang_id' => $gudangA->id,
            'komoditas_id' => $komoditas->id,
            'jumlah_fisik_layak' => '100.000',
        ]);

        $pengelolaA = User::factory()->pengelola($instansiA->id)->create();
        $petugasA = User::factory()->petugas($instansiA->id)->create();

        $this->actingAs($pengelolaA)
            ->postJson("/test/gudang/{$gudangA->id}/stok")
            ->assertOk();

        $this->assertTrue($pengelolaA->can('updateStok', $stokA));
        $this->assertFalse($petugasA->can('updateStok', $stokA));

        $this->actingAs($pengelolaA)
            ->postJson("/test/gudang/{$gudangB->id}/stok")
            ->assertForbidden();

        $this->actingAs($petugasA)
            ->postJson("/test/gudang/{$gudangA->id}/stok")
            ->assertForbidden();
    }

    #[Test]
    public function pske_40_petugas_hanya_memperbarui_pengiriman_yang_ditugaskan_kepadanya_di_database(): void
    {
        Wilayah::create(['kode' => '32', 'nama' => 'Jawa Barat']);
        $instansiA = Instansi::create(['nama' => 'Bulog A', 'alamat' => 'Jl. A', 'latitude' => -6.9, 'longitude' => 107.6]);
        $gudangA = Gudang::create([
            'nama' => 'Gudang A',
            'instansi_id' => $instansiA->id,
            'kode_wilayah' => '32',
            'alamat' => 'Alamat A',
            'latitude' => -6.9,
            'longitude' => 107.6,
            'aktif' => true,
        ]);

        $pengelolaA = User::factory()->pengelola($instansiA->id)->create();
        $petugasA = User::factory()->petugas($instansiA->id)->create();
        $petugasB = User::factory()->petugas($instansiA->id)->create();

        $pengirimanA = Pengiriman::create([
            'no_resi' => 'RESI-UJI-A',
            'gudang_asal_id' => $gudangA->id,
            'kode_wilayah_tujuan' => '32',
            'petugas_id' => $petugasA->id,
            'status' => Pengiriman::STATUS_DIKIRIM,
        ]);
        $pengirimanB = Pengiriman::create([
            'no_resi' => 'RESI-UJI-B',
            'gudang_asal_id' => $gudangA->id,
            'kode_wilayah_tujuan' => '32',
            'petugas_id' => $petugasB->id,
            'status' => Pengiriman::STATUS_DIKIRIM,
        ]);

        $this->actingAs($petugasA)
            ->patchJson("/test/pengiriman/{$pengirimanA->id}")
            ->assertOk();
        $this->actingAs($petugasA)
            ->patchJson("/test/pengiriman/{$pengirimanA->id}/checkpoint")
            ->assertOk();

        $this->actingAs($petugasA)
            ->patchJson("/test/pengiriman/{$pengirimanB->id}")
            ->assertForbidden();
        $this->actingAs($petugasA)
            ->patchJson("/test/pengiriman/{$pengirimanB->id}/checkpoint")
            ->assertForbidden();

        $this->actingAs($pengelolaA)
            ->patchJson("/test/pengiriman/{$pengirimanB->id}")
            ->assertOk();
        $this->actingAs($pengelolaA)
            ->patchJson("/test/pengiriman/{$pengirimanB->id}/checkpoint")
            ->assertForbidden();
    }
}
