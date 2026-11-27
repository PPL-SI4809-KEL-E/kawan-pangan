<?php

namespace Tests\Feature\Peta;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\Komoditas;
use App\Models\MutasiStok;
use App\Models\Stok;
use App\Models\User;
use App\Models\Wilayah;
use Database\Seeders\DataSimulasiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengujian PBI-15 · Peta choropleth dengan drill-down (PSKE-69).
 *
 * Mencakup:
 * - PSKE-70: Data diambil per level yang dibuka, bukan nasional sekaligus.
 * - PSKE-71: Warna wilayah ikut status rekap gudang unik.
 * - PSKE-72: Respons API per level <= 2 detik p95 pada data uji.
 */
class PetaChoroplethTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_pske_70_data_diambil_per_level_bukan_nasional_sekaligus(): void
    {
        $this->seed(DataSimulasiSeeder::class);

        Wilayah::create([
            'kode' => '33',
            'nama' => 'Jawa Tengah',
            'kode_induk' => null,
            'jumlah_penduduk' => 37000000,
        ]);
        Wilayah::create([
            'kode' => '33.01',
            'nama' => 'Kab. Cilacap',
            'kode_induk' => '33',
            'jumlah_penduduk' => 1900000,
        ]);

        // Level 1: Hanya Provinsi (32, 33)
        $resLv1 = $this->getJson('/api/peta?level=1');
        $resLv1->assertOk()
            ->assertJsonPath('level', 1)
            ->assertJsonPath('geojson_url', '/geojson/provinsi.json');

        $kodesLv1 = collect($resLv1->json('wilayah'))->pluck('kode_wilayah')->all();
        $this->assertEqualsCanonicalizing(['32', '33'], $kodesLv1);
        $this->assertNotContains('32.04', $kodesLv1);
        $this->assertNotContains('32.04.12', $kodesLv1);

        // Level 2: Hanya Kab/Kota di Provinsi 32
        $resLv2 = $this->getJson('/api/peta?level=2&kode_induk=32');
        $resLv2->assertOk()
            ->assertJsonPath('level', 2)
            ->assertJsonPath('kode_induk', '32')
            ->assertJsonPath('geojson_url', '/geojson/kabupaten/32.json');

        $kodesLv2 = collect($resLv2->json('wilayah'))->pluck('kode_wilayah')->all();
        $this->assertEqualsCanonicalizing(['32.04', '32.73', '32.17'], $kodesLv2);
        $this->assertNotContains('33.01', $kodesLv2);
        $this->assertNotContains('32.04.12', $kodesLv2);

        // Level 3: Hanya Kecamatan di Kab. Bandung (32.04)
        $resLv3 = $this->getJson('/api/peta?level=3&kode_induk=32.04');
        $resLv3->assertOk()
            ->assertJsonPath('level', 3)
            ->assertJsonPath('kode_induk', '32.04')
            ->assertJsonPath('geojson_url', '/geojson/kecamatan/32.04.json');

        $kodesLv3 = collect($resLv3->json('wilayah'))->pluck('kode_wilayah')->all();
        $this->assertEqualsCanonicalizing(['32.04.12', '32.04.13'], $kodesLv3);
        $this->assertNotContains('32.73.01', $kodesLv3);

        // Validasi parameter tidak sah ditolak 422
        $this->getJson('/api/peta?level=4')->assertStatus(422);
        $this->getJson('/api/peta?level=2')->assertStatus(422);
        $this->getJson('/api/peta?level=2&kode_induk=99')->assertStatus(422);
    }

    public function test_pske_71_rekap_gudang_unik_dan_status_warna_wilayah(): void
    {
        $this->seed(DataSimulasiSeeder::class);

        $beras = Komoditas::where('nama_unik', 'beras medium')->firstOrFail();

        // Uji Level 2 pada Kab. Bandung (32.04): gudang GDG-BDG-01 mencakup 32.04.12 DAN 32.04.13,
        // sehingga harus dihitung SATU kali (unik), bukan digandakan!
        $resKab = $this->getJson('/api/peta?level=2&kode_induk=32&komoditas_id='.$beras->id);
        $resKab->assertOk();

        $byKode = collect($resKab->json('wilayah'))->keyBy('kode_wilayah');
        $this->assertSame('aman', $byKode['32.04']['status']);
        $this->assertSame(1, $byKode['32.04']['jumlah_gudang']);
        $this->assertEqualsWithDelta(4500.0, $byKode['32.04']['stok_kg'], 0.01);

        $this->assertSame('waspada', $byKode['32.73']['status']);
        $this->assertSame(1, $byKode['32.73']['jumlah_gudang']);

        $this->assertSame('kritis', $byKode['32.17']['status']);
        $this->assertSame(1, $byKode['32.17']['jumlah_gudang']);

        // Uji Level 1 (Provinsi 32): agregasi 3 gudang unik di Jawa Barat
        $resProv = $this->getJson('/api/peta?level=1&komoditas_id='.$beras->id);
        $resProv->assertOk();
        $jabar = collect($resProv->json('wilayah'))->firstWhere('kode_wilayah', '32');
        $this->assertNotNull($jabar);
        $this->assertSame(3, $jabar['jumlah_gudang']);
        $this->assertSame('waspada', $jabar['status']);

        // Wilayah tanpa gudang berstatus tanpa_data
        Wilayah::create(['kode' => '35', 'nama' => 'Jawa Timur', 'kode_induk' => null]);
        $resProv2 = $this->getJson('/api/peta?level=1');
        $jatim = collect($resProv2->json('wilayah'))->firstWhere('kode_wilayah', '35');
        $this->assertSame('tanpa_data', $jatim['status']);
        $this->assertSame(0, $jatim['jumlah_gudang']);
    }

    public function test_pske_72_respons_api_per_level_tidak_melebihi_2_detik_p95(): void
    {
        $hariIni = Carbon::today();

        Wilayah::create(['kode' => '35', 'nama' => 'Jawa Timur', 'kode_induk' => null, 'lat' => -7.5, 'lng' => 112.5]);
        $instansi = Instansi::create([
            'nama' => 'Bulog Jatim',
            'alamat' => 'Surabaya',
            'latitude' => -7.25,
            'longitude' => 112.75,
        ]);
        $komoditas = Komoditas::create(['nama' => 'Beras', 'satuan' => 'kg']);
        $pencatat = User::factory()->create([
            'role' => Role::BadanResmi,
            'status' => StatusAkun::Aktif,
            'instansi_id' => $instansi->id,
        ]);

        // Seed 20 kabupaten & 300 kecamatan (15 per kabupaten) + 20 gudang + stok & mutasi
        $kabRows = [];
        $kecRows = [];
        $gudangRows = [];
        $now = now();

        for ($k = 1; $k <= 20; $k++) {
            $kabKode = sprintf('35.%02d', $k);
            $kabRows[] = [
                'kode' => $kabKode,
                'nama' => "Kabupaten Uji {$k}",
                'level' => 2,
                'kode_induk' => '35',
                'lat' => -7.5 + ($k * 0.02),
                'lng' => 112.0 + ($k * 0.02),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            for ($c = 1; $c <= 15; $c++) {
                $kecKode = sprintf('%s.%02d', $kabKode, $c);
                $kecRows[] = [
                    'kode' => $kecKode,
                    'nama' => "Kecamatan {$k}-{$c}",
                    'level' => 3,
                    'kode_induk' => $kabKode,
                    'lat' => -7.5 + ($k * 0.02) + ($c * 0.001),
                    'lng' => 112.0 + ($k * 0.02) + ($c * 0.001),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $gudangRows[] = [
                'nama' => "Gudang Uji {$k}",
                'instansi_id' => $instansi->id,
                'kode_wilayah' => "{$kabKode}.01",
                'alamat' => "Jl. Uji {$k}",
                'latitude' => -7.5,
                'longitude' => 112.5,
                'aktif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('wilayah')->insert($kabRows);
        DB::table('wilayah')->insert($kecRows);
        DB::table('gudang')->insert($gudangRows);

        $gudangIds = Gudang::pluck('id')->all();
        $cakupanRows = [];
        $stokRows = [];
        foreach ($gudangIds as $idx => $gid) {
            $kabKode = sprintf('35.%02d', $idx + 1);
            for ($c = 1; $c <= 5; $c++) {
                $cakupanRows[] = [
                    'gudang_id' => $gid,
                    'kode_kecamatan' => sprintf('%s.%02d', $kabKode, $c),
                    'is_utama' => $c === 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $stokRows[] = [
                'gudang_id' => $gid,
                'komoditas_id' => $komoditas->id,
                'jumlah_fisik_layak' => '4500.000',
                'mulai_pencatatan_lengkap' => $hariIni->copy()->subDays(40)->toDateString(),
                'diperbarui_pada' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('cakupan_gudang')->insert($cakupanRows);
        DB::table('stok')->insert($stokRows);

        $stokIds = Stok::pluck('id')->all();
        $mutasiRows = [];
        foreach ($stokIds as $sid) {
            for ($d = 0; $d < 10; $d++) {
                $mutasiRows[] = [
                    'stok_id' => $sid,
                    'jenis' => MutasiStok::JENIS_PENYALURAN,
                    'jumlah' => '100.000',
                    'saldo_setelah' => '4500.000',
                    'waktu' => $hariIni->copy()->subDays($d)->format('Y-m-d H:i:s.000'),
                    'ref_operasi' => "PERF-{$sid}-{$d}",
                    'pencatat_id' => $pencatat->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('mutasi_stok')->insert($mutasiRows);

        // Ukur 20 pemanggilan lintas level (Level 1, Level 2, Level 3)
        $durasiMs = [];
        $urls = [
            '/api/peta?level=1',
            '/api/peta?level=2&kode_induk=35',
            '/api/peta?level=3&kode_induk=35.01',
            '/api/peta?level=3&kode_induk=35.10',
        ];

        for ($i = 0; $i < 20; $i++) {
            $url = $urls[$i % count($urls)];
            $mulai = microtime(true);
            $res = $this->getJson($url);
            $selesai = (microtime(true) - $mulai) * 1000.0;
            $res->assertOk();
            $durasiMs[] = $selesai;
        }

        sort($durasiMs);
        $indexP95 = (int) ceil(0.95 * count($durasiMs)) - 1;
        $p95Ms = $durasiMs[$indexP95];

        $this->assertLessThanOrEqual(
            2000.0,
            $p95Ms,
            sprintf('Respons p95 API peta terlalu lambat: %.2f ms (batas 2000 ms).', $p95Ms)
        );
    }

    public function test_beranda_dan_berkas_geojson_publik_tersedia(): void
    {
        $this->seed(DataSimulasiSeeder::class);

        $res = $this->get('/');
        $res->assertOk();
        $res->assertSee('Jawa Barat');
        $res->assertSee('geojson\/provinsi.json', false);

        $provGeo = public_path('geojson/provinsi.json');
        $jabarKabGeo = public_path('geojson/kabupaten/32.json');
        $bandungKecGeo = public_path('geojson/kecamatan/32.04.json');

        $this->assertFileExists($provGeo);
        $this->assertFileExists($jabarKabGeo);
        $this->assertFileExists($bandungKecGeo);

        $provJson = json_decode(file_get_contents($provGeo), true);
        $this->assertSame('FeatureCollection', $provJson['type']);
        $this->assertNotEmpty($provJson['features']);
        $this->assertArrayHasKey('kode_wilayah', $provJson['features'][0]['properties']);

        $kecJson = json_decode(file_get_contents($bandungKecGeo), true);
        $this->assertSame('FeatureCollection', $kecJson['type']);
        $kecNames = collect($kecJson['features'])->pluck('properties.nama')->all();
        $this->assertContains('Dayeuhkolot', $kecNames);
        $this->assertContains('Bojongsoang', $kecNames);
    }
}
