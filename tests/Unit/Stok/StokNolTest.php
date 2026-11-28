<?php

namespace Tests\Unit\Stok;

use App\Models\Cadangan;
use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\Komoditas;
use App\Models\MutasiStok;
use App\Models\Stok;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\LayananEstimasiStok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PSKE-79 · Kasus stok nol, penggunaan nol, riwayat kurang, wilayah tanpa data lulus.
 *
 * Menguji penanganan kondisi-kondisi khusus:
 * - Stok nol / habis -> status KRITIS (0.0 hari)
 * - Penggunaan nol (tidak ada penyaluran) -> status AMAN (bebas division by zero)
 * - Stok nol & penggunaan nol -> status KRITIS
 * - Riwayat penyaluran < 30 hari -> status RIWAYAT_KURANG
 * - Wilayah tanpa data / tanpa gudang -> status TANPA_DATA (bebas exception)
 */
class StokNolTest extends TestCase
{
    use RefreshDatabase;

    private LayananEstimasiStok $layanan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->layanan = new LayananEstimasiStok();
    }

    #[Test]
    public function kasus_stok_nol_menghasilkan_ketahanan_nol_dan_status_kritis(): void
    {
        // 1. Stok tersedia 0 dengan pemakaian harian 10 kg/hari -> 0 hari, KRITIS
        $hari = $this->layanan->hitungKetahanan(0, 10);
        $this->assertSame(0.0, $hari);
        $this->assertSame(LayananEstimasiStok::STATUS_KRITIS, $this->layanan->tentukanStatus($hari));

        // 2. Stok fisik 100 kg tetapi seluruhnya dicadangkan (cadangan aktif = 100 kg)
        $data = $this->buatDataGudangDanStok(fisik: 100.0, mulaiHariLalu: 35);
        $pengirimanId = DB::table('pengiriman')->insertGetId([
            'no_resi' => 'RESI-TEST-' . uniqid(),
            'gudang_asal_id' => $data['gudang']->id,
            'kode_wilayah_tujuan' => $data['wilayah']->kode,
            'petugas_id' => $data['user']->id,
            'status' => 'DIRENCANAKAN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cadangan = Cadangan::create([
            'stok_id' => $data['stok']->id,
            'pengiriman_id' => $pengirimanId,
            'jumlah' => '100.000',
            'status' => Cadangan::STATUS_AKTIF,
            'dibuat_pada' => Carbon::now(),
        ]);

        $this->buatMutasiPenyaluranHarian($data['stok'], $data['user'], hari: 30, kgPerHari: 10);

        $estimasi = $this->layanan->estimasiStokGudang($data['gudang'], $data['komoditas']);

        $this->assertSame(0.0, $estimasi['stok_tersedia']);
        $this->assertSame(0.0, $estimasi['hari']);
        $this->assertSame(LayananEstimasiStok::STATUS_KRITIS, $estimasi['status']);

        $data['stok']->update(['jumlah_fisik_layak' => '310.000']);
        $cadangan->update(['jumlah' => '171.000']);
        $estimasi = $this->layanan->estimasiStokWilayah($data['wilayah'], $data['komoditas']);
        $this->assertSame(139.0, $estimasi['stok_tersedia']);
        $this->assertSame(13.9, $estimasi['hari']);
        $this->assertSame(LayananEstimasiStok::STATUS_KRITIS, $estimasi['status']);
    }

    #[Test]
    public function kasus_penggunaan_nol_dengan_stok_positif_aman_dari_division_by_zero(): void
    {
        // 1. hitungKetahanan dengan pemakaian = 0 tidak melempar DivisionByZeroError
        $hari = $this->layanan->hitungKetahanan(500, 0);
        $this->assertTrue(is_infinite($hari));
        $this->assertSame(LayananEstimasiStok::STATUS_AMAN, $this->layanan->tentukanStatus($hari));

        // 2. estimasiStokGudang dengan riwayat cukup (>= 30 hari) tetapi tidak ada penyaluran (pemakaian = 0)
        $data = $this->buatDataGudangDanStok(fisik: 500.0, mulaiHariLalu: 35);

        $estimasi = $this->layanan->estimasiStokGudang($data['gudang'], $data['komoditas']);

        $this->assertSame(500.0, $estimasi['stok_tersedia']);
        $this->assertSame(0.0, $estimasi['pemakaian_harian']);
        $this->assertNull($estimasi['hari']);
        $this->assertTrue($estimasi['is_tak_terhingga'] ?? false);
        $this->assertSame(LayananEstimasiStok::STATUS_AMAN, $estimasi['status']);
    }

    #[Test]
    public function kasus_stok_nol_dan_penggunaan_nol_menghasilkan_status_kritis(): void
    {
        // Stok nol dan penggunaan nol
        $hari = $this->layanan->hitungKetahanan(0, 0);
        $this->assertSame(0.0, $hari);
        $this->assertSame(LayananEstimasiStok::STATUS_KRITIS, $this->layanan->tentukanStatus($hari));

        $data = $this->buatDataGudangDanStok(fisik: 0.0, mulaiHariLalu: 35);
        $estimasi = $this->layanan->estimasiStokGudang($data['gudang'], $data['komoditas']);

        $this->assertSame(0.0, $estimasi['stok_tersedia']);
        $this->assertSame(0.0, $estimasi['hari']);
        $this->assertSame(LayananEstimasiStok::STATUS_KRITIS, $estimasi['status']);
    }

    #[Test]
    public function kasus_riwayat_kurang_dari_30_hari_menghasilkan_status_riwayat_kurang(): void
    {
        // Stok baru dicatat 10 hari lalu (< 30 hari)
        $data = $this->buatDataGudangDanStok(fisik: 1000.0, mulaiHariLalu: 10);
        $this->buatMutasiPenyaluranHarian($data['stok'], $data['user'], hari: 10, kgPerHari: 15);

        $estimasi = $this->layanan->estimasiStokGudang($data['gudang'], $data['komoditas']);

        $this->assertSame(LayananEstimasiStok::STATUS_RIWAYAT_KURANG, $estimasi['status']);
        $this->assertTrue($estimasi['riwayat_kurang'] ?? false);
        $this->assertNull($estimasi['hari']);
        $this->assertLessThan(30, $estimasi['riwayat_hari']);
        $this->assertStringContainsString('Riwayat penyaluran baru', $estimasi['pesan']);
    }

    #[Test]
    public function mutasi_retur_rusak_dan_penyesuaian_tidak_ikut_dihitung_dalam_pemakaian_harian(): void
    {
        $data = $this->buatDataGudangDanStok(fisik: 1000.0, mulaiHariLalu: 35);

        // Buat 30 hari mutasi PENYALURAN @ 10 kg/hari = 300 kg / 30 hari = 10 kg/hari
        $this->buatMutasiPenyaluranHarian($data['stok'], $data['user'], hari: 30, kgPerHari: 10);

        // Buat mutasi non-penyaluran: retur, rusak, dan penyesuaian
        MutasiStok::create([
            'stok_id' => $data['stok']->id,
            'jenis' => MutasiStok::JENIS_RETUR,
            'jumlah' => '50.000',
            'saldo_setelah' => '0.000',
            'waktu' => Carbon::now()->subDays(5)->format('Y-m-d H:i:s.000'),
            'ref_operasi' => 'REF-RETUR-' . uniqid(),
            'pencatat_id' => $data['user']->id,
        ]);
        MutasiStok::create([
            'stok_id' => $data['stok']->id,
            'jenis' => MutasiStok::JENIS_RUSAK,
            'jumlah' => '25.000',
            'saldo_setelah' => '0.000',
            'waktu' => Carbon::now()->subDays(10)->format('Y-m-d H:i:s.000'),
            'ref_operasi' => 'REF-RUSAK-' . uniqid(),
            'pencatat_id' => $data['user']->id,
        ]);
        MutasiStok::create([
            'stok_id' => $data['stok']->id,
            'jenis' => MutasiStok::JENIS_PENYESUAIAN,
            'jumlah' => '100.000',
            'saldo_setelah' => '0.000',
            'waktu' => Carbon::now()->subDays(15)->format('Y-m-d H:i:s.000'),
            'ref_operasi' => 'REF-PENYESUAIAN-' . uniqid(),
            'pencatat_id' => $data['user']->id,
        ]);

        $estimasi = $this->layanan->estimasiStokGudang($data['gudang'], $data['komoditas']);

        // Pemakaian harian HANYA dari mutasi penyaluran: 300 / 30 = 10 kg/hari
        $this->assertSame(10.0, $estimasi['pemakaian_harian']);
        $this->assertSame(100.0, $estimasi['hari']);
        $this->assertSame(LayananEstimasiStok::STATUS_AMAN, $estimasi['status']);
    }

    #[Test]
    public function kasus_wilayah_tanpa_data_gudang_atau_stok_lulus_dengan_aman(): void
    {
        $this->pastikanHierarkiWilayah();
        $komoditas = Komoditas::create(['nama' => 'Beras Medium', 'satuan' => 'kg']);

        // 1. Wilayah ada tetapi tidak memiliki gudang/cakupan sama sekali
        $kecamatanKosong = Wilayah::firstOrCreate(
            ['kode' => '32.04.99'],
            ['nama' => 'Kecamatan Tanpa Gudang', 'kode_induk' => '32.04', 'jumlah_penduduk' => 50000]
        );

        $estimasi = $this->layanan->estimasiStokWilayah($kecamatanKosong, $komoditas);

        $this->assertSame(LayananEstimasiStok::STATUS_TANPA_DATA, $estimasi['status']);
        $this->assertTrue($estimasi['tanpa_data'] ?? false);
        $this->assertSame(0, $estimasi['jumlah_gudang']);
        $this->assertSame(0.0, $estimasi['stok_tersedia']);
        $this->assertNull($estimasi['hari']);
        $this->assertStringContainsString('Belum ada gudang pangan', $estimasi['pesan']);

        // 2. Kode wilayah fiktif yang tidak ada di basis data
        $estimasiFiktif = $this->layanan->estimasiStokWilayah('99.99.99', $komoditas);
        $this->assertSame(LayananEstimasiStok::STATUS_TANPA_DATA, $estimasiFiktif['status']);
        $this->assertTrue($estimasiFiktif['tanpa_data'] ?? false);
        $this->assertNull($estimasiFiktif['hari']);

        // 3. Gudang ada tetapi belum ada pencatatan stok komoditas tersebut
        $data = $this->buatDataGudangDanStok(fisik: 500.0, mulaiHariLalu: 35);
        $komoditasLain = Komoditas::create(['nama' => 'Minyak Goreng', 'satuan' => 'liter']);

        $estimasiTanpaStok = $this->layanan->estimasiStokGudang($data['gudang'], $komoditasLain);
        $this->assertSame(LayananEstimasiStok::STATUS_TANPA_DATA, $estimasiTanpaStok['status']);
        $this->assertTrue($estimasiTanpaStok['tanpa_data'] ?? false);
        $this->assertNull($estimasiTanpaStok['hari']);
    }

    // =========================================================================
    // Helper Data Uji
    // =========================================================================

    private function pastikanHierarkiWilayah(): void
    {
        Wilayah::firstOrCreate(
            ['kode' => '32'],
            ['nama' => 'Jawa Barat', 'kode_induk' => null, 'jumlah_penduduk' => 49900000]
        );

        Wilayah::firstOrCreate(
            ['kode' => '32.04'],
            ['nama' => 'Kab. Bandung', 'kode_induk' => '32', 'jumlah_penduduk' => 3720000]
        );

        Wilayah::firstOrCreate(
            ['kode' => '32.04.12'],
            ['nama' => 'Dayeuhkolot', 'kode_induk' => '32.04', 'jumlah_penduduk' => 115000]
        );
    }

    private function buatDataGudangDanStok(float $fisik, int $mulaiHariLalu = 35): array
    {
        $this->pastikanHierarkiWilayah();
        $wilayah = Wilayah::where('kode', '32.04.12')->firstOrFail();

        $instansi = Instansi::firstOrCreate(
            ['nama' => 'Instansi Uji Pangan'],
            [
                'alamat' => 'Jl. Pangan No. 123',
                'latitude' => '-6.9388890',
                'longitude' => '107.6622220',
                'aktif' => true,
            ]
        );

        $gudang = Gudang::create([
            'kode_gudang' => 'GDG-TEST-' . uniqid(),
            'instansi_id' => $instansi->id,
            'kode_wilayah' => $wilayah->kode,
            'nama' => 'Gudang Uji ' . uniqid(),
            'alamat' => 'Jl. Gudang No. 1',
            'latitude' => '-6.9881000',
            'longitude' => '107.6263000',
            'aktif' => true,
        ]);

        $komoditas = Komoditas::firstOrCreate(
            ['nama' => 'Beras Medium'],
            ['satuan' => 'kg']
        );

        $stok = Stok::create([
            'gudang_id' => $gudang->id,
            'komoditas_id' => $komoditas->id,
            'jumlah_fisik_layak' => number_format($fisik, 3, '.', ''),
            'mulai_pencatatan_lengkap' => Carbon::now()->subDays($mulaiHariLalu)->toDateString(),
            'diperbarui_pada' => Carbon::now(),
        ]);

        $user = User::factory()->create([
            'instansi_id' => $instansi->id,
        ]);

        return compact('wilayah', 'instansi', 'gudang', 'komoditas', 'stok', 'user');
    }

    private function buatMutasiPenyaluranHarian(Stok $stok, User $user, int $hari, float $kgPerHari): void
    {
        for ($i = 0; $i < $hari; $i++) {
            MutasiStok::create([
                'stok_id' => $stok->id,
                'jenis' => MutasiStok::JENIS_PENYALURAN,
                'jumlah' => number_format($kgPerHari, 3, '.', ''),
                'saldo_setelah' => '0.000',
                'waktu' => Carbon::now()->subDays($i)->format('Y-m-d H:i:s.000'),
                'ref_operasi' => 'REF-TEST-' . uniqid() . "-{$i}",
                'pencatat_id' => $user->id,
            ]);
        }
    }
}
