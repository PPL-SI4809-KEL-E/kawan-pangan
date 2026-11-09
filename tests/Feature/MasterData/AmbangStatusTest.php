<?php

namespace Tests\Feature\MasterData;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\AmbangStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AmbangStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Superadmin, 'status' => StatusAkun::Aktif]);
    }

    public function test_migrasi_menyediakan_default_tanpa_seeder_dan_baca_tidak_mengubahnya(): void
    {
        $this->assertDatabaseCount('ambang_status', 1);
        $sebelum = AmbangStatus::aktif()->toArray();
        $this->assertSame(14, AmbangStatus::aktif()->kritis_hari);
        $this->assertSame(30, AmbangStatus::aktif()->aman_hari);
        $this->actingAs($this->admin())->get('/admin/ambang-status')->assertOk()
            ->assertSee('Ambang Status Pangan')->assertSee('14–30 hari');
        $this->assertSame($sebelum, AmbangStatus::aktif()->toArray());
        $this->assertDatabaseCount('ambang_status', 1);
    }

    public function test_perubahan_tersimpan_dan_hanya_kedua_ambang_yang_diubah(): void
    {
        $this->actingAs($this->admin());
        foreach ([[7, 21], [1, 65535]] as [$kritis, $aman]) {
            $this->patch('/admin/ambang-status', ['kritis_hari' => $kritis, 'aman_hari' => $aman, 'id' => 99])
                ->assertRedirect('/admin/ambang-status')->assertSessionHasNoErrors()
                ->assertSessionHas('sukses', 'Ambang status berhasil diperbarui.');
            $this->assertDatabaseHas('ambang_status', ['id' => 1, 'kritis_hari' => $kritis, 'aman_hari' => $aman]);
            $this->assertDatabaseCount('ambang_status', 1);
            $this->get('/admin/ambang-status')->assertOk()->assertSee($kritis.'–'.$aman.' hari');
        }
    }

    public static function ambangTidakValid(): array
    {
        return [
            'sama' => [14, 14, 'aman_hari'],
            'terbalik' => [31, 30, 'aman_hari'],
            'kritis nol' => [0, 30, 'kritis_hari'],
            'aman nol' => [14, 0, 'aman_hari'],
            'kritis negatif' => [-1, 30, 'kritis_hari'],
            'aman negatif' => [14, -1, 'aman_hari'],
            'kritis pecahan' => ['13.5', 30, 'kritis_hari'],
            'aman pecahan' => [14, '30.5', 'aman_hari'],
            'kritis kosong' => ['', 30, 'kritis_hari'],
            'aman kosong' => [14, '', 'aman_hari'],
            'kritis bukan angka' => ['abc', 30, 'kritis_hari'],
            'aman bukan angka' => [14, 'abc', 'aman_hari'],
            'kritis melebihi tipe data' => [65536, 65535, 'kritis_hari'],
            'aman melebihi tipe data' => [14, 65536, 'aman_hari'],
            'kritis array' => [[], 30, 'kritis_hari'],
            'aman array' => [14, [], 'aman_hari'],
        ];
    }

    #[DataProvider('ambangTidakValid')]
    public function test_validasi_menolak_input_tidak_valid_tanpa_mengubah_ambang(mixed $kritis, mixed $aman, string $field): void
    {
        $this->actingAs($this->admin())->from('/admin/ambang-status')
            ->patch('/admin/ambang-status', ['kritis_hari' => $kritis, 'aman_hari' => $aman])
            ->assertRedirect('/admin/ambang-status')->assertSessionHasErrors($field);
        $this->assertDatabaseHas('ambang_status', ['id' => 1, 'kritis_hari' => 14, 'aman_hari' => 30]);
        $this->assertDatabaseCount('ambang_status', 1);
    }

    public function test_input_yang_valid_dipertahankan_saat_ambang_lain_gagal(): void
    {
        $this->actingAs($this->admin())->from('/admin/ambang-status')
            ->patch('/admin/ambang-status', ['kritis_hari' => 9, 'aman_hari' => 5])
            ->assertSessionHasInput('kritis_hari', 9)->assertSessionHasInput('aman_hari', 5);
    }

    public function test_semua_role_dan_status_tanpa_izin_ditolak(): void
    {
        foreach (['GET', 'PATCH'] as $method) {
            $this->call($method, '/admin/ambang-status')->assertRedirect(route('login'));
        }
        foreach ([
            [Role::KepalaWilayah, StatusAkun::Aktif], [Role::BadanResmi, StatusAkun::Aktif],
            [Role::Superadmin, StatusAkun::Menunggu], [Role::Superadmin, StatusAkun::Ditolak], [Role::Superadmin, StatusAkun::Nonaktif],
        ] as [$role, $status]) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => $status]));
            $this->get('/admin/ambang-status')->assertForbidden();
            $this->patch('/admin/ambang-status', ['kritis_hari' => 7, 'aman_hari' => 21])->assertForbidden();
        }
        $this->assertDatabaseHas('ambang_status', ['kritis_hari' => 14, 'aman_hari' => 30]);
    }

    public function test_semua_master_redirect_tamu_web_ke_login_saat_rute_login_tersedia(): void
    {
        Route::get('/login', fn () => 'Login tersedia untuk pengujian')->name('login');
        Route::getRoutes()->refreshNameLookups();
        $endpoints = [
            ['GET', '/admin/wilayah'], ['POST', '/admin/wilayah/impor'],
            ['GET', '/admin/komoditas'], ['POST', '/admin/komoditas'],
            ['GET', '/admin/instansi'], ['POST', '/admin/instansi'],
            ['GET', '/admin/gudang'], ['POST', '/admin/gudang'],
            ['GET', '/admin/ambang-status'], ['PATCH', '/admin/ambang-status'],
        ];
        foreach ($endpoints as [$method, $url]) {
            $this->call($method, $url)->assertRedirect('/login');
            $this->json($method, $url)->assertUnauthorized();
        }
    }

    public static function hariPadaBatas(): array
    {
        return [
            'nol' => [0.0, 'KRITIS'],
            'mendekati kritis' => [13.9999999, 'KRITIS'],
            'tepat kritis' => [14.0, 'WASPADA'],
            'di atas kritis' => [14.0000001, 'WASPADA'],
            'tengah' => [21.0, 'WASPADA'],
            'di bawah aman' => [29.9999999, 'WASPADA'],
            'tepat aman' => [30.0, 'WASPADA'],
            'melewati aman sebelum pembulatan' => [30.0000001, 'AMAN'],
            'jauh di atas aman' => [60.0, 'AMAN'],
        ];
    }

    #[DataProvider('hariPadaBatas')]
    public function test_status_membandingkan_hari_asli_sebelum_pembulatan(float $hari, string $status): void
    {
        $this->assertSame($status, AmbangStatus::aktif()->statusUntukHari($hari));
    }

    public function test_pembaca_menggunakan_pengaturan_terbaru_dan_batas_kustom(): void
    {
        $this->assertSame('KRITIS', AmbangStatus::aktif()->statusUntukHari(9));
        $this->actingAs($this->admin())->patch('/admin/ambang-status', ['kritis_hari' => 7, 'aman_hari' => 21])->assertSessionHasNoErrors();
        $ambang = AmbangStatus::aktif();
        $this->assertSame('KRITIS', $ambang->statusUntukHari(6.9999));
        $this->assertSame('WASPADA', $ambang->statusUntukHari(7));
        $this->assertSame('WASPADA', $ambang->statusUntukHari(9));
        $this->assertSame('WASPADA', $ambang->statusUntukHari(21));
        $this->assertSame('AMAN', $ambang->statusUntukHari(21.0001));
    }

    public function test_model_menolak_ambang_tidak_valid_dan_id_pengaturan_lain(): void
    {
        foreach ([['kritis_hari' => 30, 'aman_hari' => 14], ['kritis_hari' => 1.5, 'aman_hari' => 30], ['id' => 2]] as $nilai) {
            $ambang = AmbangStatus::aktif();
            foreach ($nilai as $field => $value) {
                $ambang->$field = $value;
            }
            try {
                $ambang->save();
                $this->fail('Model seharusnya menolak nilai pengaturan.');
            } catch (ValidationException) {
                $this->assertDatabaseHas('ambang_status', ['id' => 1, 'kritis_hari' => 14, 'aman_hari' => 30]);
                $this->assertDatabaseCount('ambang_status', 1);
            }
        }
    }

    public function test_pembanding_menolak_nilai_hari_tidak_hingga(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AmbangStatus::aktif()->statusUntukHari(NAN);
    }
}
