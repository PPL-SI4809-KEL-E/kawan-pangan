<?php

namespace Tests\Feature\Database;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\AmbangStatus;
use App\Models\Cadangan;
use App\Models\Gudang;
use App\Models\Komoditas;
use App\Models\MutasiStok;
use App\Models\Stok;
use App\Models\User;
use App\Models\Wilayah;
use Database\Seeders\DataSimulasiSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PSKE-15 · Pengujian skema 22 tabel ERD (PSKE-28), seeder simulasi 60 hari (PSKE-29),
 * dan penanda badge "Data contoh" pada antarmuka (PSKE-30).
 */
class SkemaDanSimulasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dua_puluh_dua_tabel_erd_tersedia_setelah_migrasi(): void
    {
        $tabelErd = [
            'wilayah',
            'instansi',
            'users',
            'dokumen_pendukung',
            'komoditas',
            'ambang_status',
            'gudang',
            'cakupan_gudang',
            'stok',
            'laporan',
            'detail_laporan',
            'lampiran_laporan',
            'pengiriman',
            'detail_pengiriman',
            'checkpoint',
            'penerimaan',
            'detail_penerimaan',
            'artikel',
            'kontak',
            'notifikasi',
            'mutasi_stok',
            'cadangan',
        ];

        $this->assertCount(22, $tabelErd);
        foreach ($tabelErd as $namaTabel) {
            $this->assertTrue(Schema::hasTable($namaTabel), "Tabel ERD '{$namaTabel}' belum tersedia.");
        }

        $this->assertTrue(Schema::hasColumns('stok', ['gudang_id', 'komoditas_id', 'jumlah_fisik_layak', 'mulai_pencatatan_lengkap', 'diperbarui_pada', 'is_contoh']));
        $this->assertTrue(Schema::hasColumns('mutasi_stok', ['stok_id', 'jenis', 'jumlah', 'saldo_setelah', 'waktu', 'ref_operasi', 'pengiriman_id', 'pencatat_id', 'keterangan', 'is_contoh']));
        $this->assertTrue(Schema::hasColumns('cadangan', ['stok_id', 'pengiriman_id', 'jumlah', 'status', 'dibuat_pada', 'selesai_pada', 'is_contoh']));
    }

    public function test_seeder_simulasi_60_hari_berhasil_deterministik_aman_diulang_dan_mencakup_tiga_status(): void
    {
        $this->artisan('db:seed')->assertExitCode(0);

        $jumlahWilayahPertama = Wilayah::where('is_contoh', true)->count();
        $jumlahMutasiPertama = MutasiStok::where('is_contoh', true)->count();
        $saldoPertama = Stok::orderBy('id')->pluck('jumlah_fisik_layak', 'id')->all();

        // Jalankan ulang seeder untuk memastikan deterministik & idempotent
        $this->artisan('db:seed')->assertExitCode(0);

        $this->assertSame($jumlahWilayahPertama, Wilayah::where('is_contoh', true)->count());
        $this->assertSame($jumlahMutasiPertama, MutasiStok::where('is_contoh', true)->count());
        $this->assertSame($saldoPertama, Stok::orderBy('id')->pluck('jumlah_fisik_layak', 'id')->all());

        // Verifikasi perhitungan ketahanan pangan 3 wilayah: Aman (>30), Waspada (14..30), Kritis (<14)
        $beras = Komoditas::where('nama_unik', 'beras medium')->firstOrFail();
        $ambang = AmbangStatus::aktif();
        $batas30Hari = Carbon::today()->subDays(29)->startOfDay();

        $targetStatus = [
            'GDG-BDG-01' => ['status' => 'AMAN', 'hari' => 45.0],
            'GDG-KBDG-01' => ['status' => 'WASPADA', 'hari' => 21.0],
            'GDG-KBB-01' => ['status' => 'KRITIS', 'hari' => 9.0],
        ];

        foreach ($targetStatus as $kodeGudang => $ekspektasi) {
            $gudang = Gudang::where('kode_gudang', $kodeGudang)->firstOrFail();
            $stok = Stok::where('gudang_id', $gudang->id)->where('komoditas_id', $beras->id)->firstOrFail();

            // Pastikan riwayat penyaluran 60 hari tersedia (60 hari penyaluran + 1 penerimaan + 3 non-penyaluran = 64 mutasi)
            $this->assertSame(60, $stok->mutasi()->where('jenis', MutasiStok::JENIS_PENYALURAN)->count());
            $this->assertSame(64, $stok->mutasi()->count());

            $cadanganAktif = (float) $stok->cadangan()->where('status', Cadangan::STATUS_AKTIF)->sum('jumlah');
            $tersedia = (float) $stok->jumlah_fisik_layak - $cadanganAktif;

            // Hanya jenis PENYALURAN dalam 30 hari yang dihitung (RETUR, RUSAK, PENYESUAIAN diabaikan)
            $totalPenyaluran30Hari = (float) $stok->mutasi()
                ->where('jenis', MutasiStok::JENIS_PENYALURAN)
                ->where('waktu', '>=', $batas30Hari)
                ->sum('jumlah');

            $pemakaianHarian = $totalPenyaluran30Hari / 30.0;
            $ketahananHari = $tersedia / $pemakaianHarian;

            $this->assertEqualsWithDelta($ekspektasi['hari'], $ketahananHari, 0.0001);
            $this->assertSame($ekspektasi['status'], $ambang->statusUntukHari($ketahananHari));
        }
    }

    public function test_unique_key_menolak_duplikat_stok_ref_operasi_dan_cakupan_gudang(): void
    {
        $this->seed(DataSimulasiSeeder::class);

        $gudang = Gudang::where('kode_gudang', 'GDG-BDG-01')->firstOrFail();
        $beras = Komoditas::where('nama_unik', 'beras medium')->firstOrFail();
        $stok = Stok::where('gudang_id', $gudang->id)->where('komoditas_id', $beras->id)->firstOrFail();
        $pencatat = User::where('email', 'petugas.simulasi@kawanpangan.id')->firstOrFail();

        // 1. Duplikat (gudang_id, komoditas_id) pada tabel stok harus ditolak
        $this->expectException(QueryException::class);
        DB::table('stok')->insert([
            'gudang_id' => $gudang->id,
            'komoditas_id' => $beras->id,
            'jumlah_fisik_layak' => '100.000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ref_operasi_unik_menolak_permintaan_mutasi_dan_penerimaan_berulang(): void
    {
        $this->seed(DataSimulasiSeeder::class);

        $stok = Stok::firstOrFail();
        $pencatat = User::where('email', 'petugas.simulasi@kawanpangan.id')->firstOrFail();

        try {
            DB::table('mutasi_stok')->insert([
                'stok_id' => $stok->id,
                'jenis' => MutasiStok::JENIS_PENYALURAN,
                'jumlah' => '10.000',
                'saldo_setelah' => '100.000',
                'waktu' => now()->format('Y-m-d H:i:s.000'),
                'ref_operasi' => 'SIM-GDG-BDG-01-BERAS-H00-SALUR', // sudah dipakai
                'pencatat_id' => $pencatat->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Duplikat ref_operasi pada mutasi_stok seharusnya ditolak.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $gudang = Gudang::where('kode_gudang', 'GDG-BDG-01')->firstOrFail();
        $this->expectException(QueryException::class);
        DB::table('cakupan_gudang')->insert([
            'gudang_id' => $gudang->id,
            'kode_kecamatan' => '32.04.12', // sudah terdaftar
            'is_utama' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_saldo_stok_dan_mutasi_tidak_bisa_negatif(): void
    {
        $this->seed(DataSimulasiSeeder::class);

        $stok = Stok::firstOrFail();
        $pencatat = User::where('email', 'petugas.simulasi@kawanpangan.id')->firstOrFail();

        // 1. Penolakan di tingkat Model Eloquent (ValidationException)
        try {
            $stok->update(['jumlah_fisik_layak' => '-1.000']);
            $this->fail('Model Stok seharusnya menolak saldo negatif.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('jumlah_fisik_layak', $e->errors());
        }

        try {
            MutasiStok::create([
                'stok_id' => $stok->id,
                'jenis' => MutasiStok::JENIS_PENYALURAN,
                'jumlah' => '50000.000',
                'saldo_setelah' => '-0.001',
                'waktu' => now(),
                'ref_operasi' => 'UJI-NEGATIF-MODEL',
                'pencatat_id' => $pencatat->id,
            ]);
            $this->fail('Model MutasiStok seharusnya menolak saldo_setelah negatif.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('saldo_setelah', $e->errors());
        }

        // 2. Penolakan langsung di tingkat Database (Trigger SQLite / CHECK MySQL 8)
        try {
            DB::table('stok')->where('id', $stok->id)->update(['jumlah_fisik_layak' => '-5.000']);
            $this->fail('Database seharusnya menolak update stok menjadi negatif.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);
        DB::table('mutasi_stok')->insert([
            'stok_id' => $stok->id,
            'jenis' => MutasiStok::JENIS_PENYALURAN,
            'jumlah' => '50000.000',
            'saldo_setelah' => '-10.000',
            'waktu' => now()->format('Y-m-d H:i:s.000'),
            'ref_operasi' => 'UJI-NEGATIF-DB',
            'pencatat_id' => $pencatat->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_label_data_contoh_ditampilkan_pada_antarmuka(): void
    {
        $this->seed(DataSimulasiSeeder::class);

        Wilayah::create([
            'kode' => '33',
            'nama' => 'Jawa Tengah Resmi',
            'kode_induk' => null,
            'jumlah_penduduk' => 37000000,
        ]);

        $admin = User::factory()->create([
            'role' => Role::Superadmin,
            'status' => StatusAkun::Aktif,
        ]);

        $this->actingAs($admin)
            ->get('/admin/wilayah?q=Jawa Barat')
            ->assertOk()
            ->assertSee('Jawa Barat')
            ->assertSee('<span class="badge badge-netral">Data contoh</span>', false);

        $this->actingAs($admin)
            ->get('/admin/wilayah/32/edit')
            ->assertOk()
            ->assertSee('<span class="badge badge-netral">Data contoh</span>', false);

        $this->actingAs($admin)
            ->get('/admin/wilayah?q=Jawa Tengah Resmi')
            ->assertOk()
            ->assertSee('Jawa Tengah Resmi')
            ->assertDontSee('<span class="badge badge-netral">Data contoh</span>', false);
    }
}

