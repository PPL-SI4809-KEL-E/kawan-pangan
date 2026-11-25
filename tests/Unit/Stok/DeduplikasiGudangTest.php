<?php

namespace Tests\Unit\Stok;

use App\Models\CakupanGudang;
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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PSKE-80 · Uji gudang yang melayani beberapa desa tidak dihitung berulang.
 *
 * Menguji agregasi stok pangan pada wilayah (kabupaten/desa/multi-wilayah):
 * - Gudang yang mencakup lebih dari satu kecamatan/desa dihitung tepat 1 kali.
 * - Stok fisik, stok tersedia, dan pemakaian harian tidak terduplikasi.
 * - Metode ambilGudangMelayaniWilayah() mengembalikan ID gudang unik.
 */
class DeduplikasiGudangTest extends TestCase
{
    use RefreshDatabase;

    private LayananEstimasiStok $layanan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->layanan = new LayananEstimasiStok();
    }

    #[Test]
    public function gudang_yang_melayani_beberapa_kecamatan_tidak_dihitung_berulang_pada_estimasi_kabupaten(): void
    {
        $this->pastikanHierarkiWilayah();
        $kabupaten = Wilayah::where('kode', '32.04')->firstOrFail();

        // Buat 2 kecamatan dalam kabupaten yang sama
        $kecamatan1 = Wilayah::where('kode', '32.04.12')->firstOrFail();
        $kecamatan2 = Wilayah::firstOrCreate(
            ['kode' => '32.04.13'],
            ['nama' => 'Baleendah', 'kode_induk' => '32.04', 'jumlah_penduduk' => 120000]
        );

        $instansi = Instansi::firstOrCreate(
            ['nama' => 'Bulog Regional Jabar'],
            [
                'alamat' => 'Jl. Soekarno Hatta No. 711',
                'latitude' => '-6.9388890',
                'longitude' => '107.6622220',
                'aktif' => true,
            ]
        );

        // Gudang A berada di Dayeuhkolot
        $gudangA = Gudang::create([
            'kode_gudang' => 'GDG-MULTI-01',
            'instansi_id' => $instansi->id,
            'kode_wilayah' => $kecamatan1->kode,
            'nama' => 'Gudang Distribusi Multi-Kecamatan',
            'alamat' => 'Jl. Raya Dayeuhkolot No. 50',
            'latitude' => '-6.9881000',
            'longitude' => '107.6263000',
            'aktif' => true,
        ]);

        // Cakupan: Gudang A melayani KEDUA kecamatan (Kecamatan 1 dan Kecamatan 2)
        CakupanGudang::create([
            'gudang_id' => $gudangA->id,
            'kode_kecamatan' => $kecamatan1->kode,
            'is_utama' => true,
        ]);

        CakupanGudang::create([
            'gudang_id' => $gudangA->id,
            'kode_kecamatan' => $kecamatan2->kode,
            'is_utama' => false,
        ]);

        $komoditas = Komoditas::firstOrCreate(
            ['nama' => 'Beras Medium'],
            ['satuan' => 'kg']
        );

        // Stok fisik 1.000 kg di Gudang A
        $stokA = Stok::create([
            'gudang_id' => $gudangA->id,
            'komoditas_id' => $komoditas->id,
            'jumlah_fisik_layak' => '1000.000',
            'mulai_pencatatan_lengkap' => Carbon::now()->subDays(35)->toDateString(),
            'diperbarui_pada' => Carbon::now(),
        ]);

        $user = User::factory()->create(['instansi_id' => $instansi->id]);
        $this->buatMutasiPenyaluranHarian($stokA, $user, hari: 30, kgPerHari: 20); // 20 kg/hari

        // Verifikasi pada level kabupaten (mencakup kecamatan 1 dan kecamatan 2)
        $estimasiKabupaten = $this->layanan->estimasiStokWilayah($kabupaten, $komoditas);

        // Gudang A melayani 2 kecamatan, tetapi pada level kabupaten harus dihitung tepat 1 kali:
        // Stok harus 1.000 kg (BUKAN 2.000 kg), pemakaian harus 20 kg/hari (BUKAN 40 kg/hari),
        // jumlah gudang harus 1 (BUKAN 2).
        $this->assertSame(1, $estimasiKabupaten['jumlah_gudang']);
        $this->assertEqualsWithDelta(1000.0, $estimasiKabupaten['stok_fisik'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $estimasiKabupaten['stok_tersedia'], 0.001);
        $this->assertEqualsWithDelta(20.0, $estimasiKabupaten['pemakaian_harian'], 0.001);
        $this->assertEqualsWithDelta(50.0, $estimasiKabupaten['hari'], 0.001);
        $this->assertSame(LayananEstimasiStok::STATUS_AMAN, $estimasiKabupaten['status']);
    }

    #[Test]
    public function gudang_yang_melayani_beberapa_desa_tidak_dihitung_berulang_pada_agregasi_multi_wilayah(): void
    {
        $this->pastikanHierarkiWilayah();
        $kecamatan = Wilayah::where('kode', '32.04.12')->firstOrFail();

        // Buat 3 desa di bawah kecamatan Dayeuhkolot
        $desa1 = Wilayah::firstOrCreate(
            ['kode' => '32.04.12.2001'],
            ['nama' => 'Desa Dayeuhkolot', 'kode_induk' => '32.04.12', 'jumlah_penduduk' => 30000]
        );
        $desa2 = Wilayah::firstOrCreate(
            ['kode' => '32.04.12.2002'],
            ['nama' => 'Desa Pasawahan', 'kode_induk' => '32.04.12', 'jumlah_penduduk' => 25000]
        );
        $desa3 = Wilayah::firstOrCreate(
            ['kode' => '32.04.12.2003'],
            ['nama' => 'Desa Cangkuang Kulon', 'kode_induk' => '32.04.12', 'jumlah_penduduk' => 28000]
        );

        $instansi = Instansi::firstOrCreate(
            ['nama' => 'Instansi Distribusi Wilayah'],
            [
                'alamat' => 'Jl. Moch Toha No. 200',
                'latitude' => '-6.9500000',
                'longitude' => '107.6100000',
                'aktif' => true,
            ]
        );

        $gudang = Gudang::create([
            'kode_gudang' => 'GDG-DESA-01',
            'instansi_id' => $instansi->id,
            'kode_wilayah' => $kecamatan->kode,
            'nama' => 'Gudang Penyangga Tiga Desa',
            'alamat' => 'Jl. Cisirung No. 5',
            'latitude' => '-6.9800000',
            'longitude' => '107.6200000',
            'aktif' => true,
        ]);

        CakupanGudang::create([
            'gudang_id' => $gudang->id,
            'kode_kecamatan' => $kecamatan->kode,
            'is_utama' => true,
        ]);

        $komoditas = Komoditas::firstOrCreate(['nama' => 'Beras Medium'], ['satuan' => 'kg']);

        $stok = Stok::create([
            'gudang_id' => $gudang->id,
            'komoditas_id' => $komoditas->id,
            'jumlah_fisik_layak' => '1500.000',
            'mulai_pencatatan_lengkap' => Carbon::now()->subDays(35)->toDateString(),
            'diperbarui_pada' => Carbon::now(),
        ]);

        $user = User::factory()->create(['instansi_id' => $instansi->id]);
        $this->buatMutasiPenyaluranHarian($stok, $user, hari: 30, kgPerHari: 30); // 30 kg/hari

        // Agregasi untuk gabungan 3 desa yang sama-sama dilayani gudang ini
        $agregasi = $this->layanan->agregasiStokMultiWilayah([$desa1, $desa2, $desa3], $komoditas);

        // Gudang hanya boleh dihitung 1 kali, bukan 3 kali!
        $this->assertSame(1, $agregasi['jumlah_gudang']);
        $this->assertEqualsWithDelta(1500.0, $agregasi['stok_tersedia'], 0.001);
        $this->assertEqualsWithDelta(30.0, $agregasi['pemakaian_harian'], 0.001);
        $this->assertEqualsWithDelta(50.0, $agregasi['hari'], 0.001);
        $this->assertSame(LayananEstimasiStok::STATUS_AMAN, $agregasi['status']);
    }

    #[Test]
    public function beberapa_gudang_dengan_cakupan_tumpang_tindih_tidak_dihitung_berulang(): void
    {
        $this->pastikanHierarkiWilayah();
        $kabupaten = Wilayah::where('kode', '32.04')->firstOrFail();

        $kec1 = Wilayah::where('kode', '32.04.12')->firstOrFail();
        $kec2 = Wilayah::firstOrCreate(
            ['kode' => '32.04.13'],
            ['nama' => 'Baleendah', 'kode_induk' => '32.04', 'jumlah_penduduk' => 120000]
        );
        $kec3 = Wilayah::firstOrCreate(
            ['kode' => '32.04.14'],
            ['nama' => 'Bojongsoang', 'kode_induk' => '32.04', 'jumlah_penduduk' => 95000]
        );

        $instansi = Instansi::firstOrCreate(
            ['nama' => 'Bulog Induk'],
            [
                'alamat' => 'Jl. Soekarno Hatta No. 711',
                'latitude' => '-6.9388890',
                'longitude' => '107.6622220',
                'aktif' => true,
            ]
        );

        // Gudang 1: covers Kec 1 & Kec 2 (1.000 kg, 20 kg/hari)
        $gudang1 = Gudang::create([
            'kode_gudang' => 'GDG-OVERLAP-01',
            'instansi_id' => $instansi->id,
            'kode_wilayah' => $kec1->kode,
            'nama' => 'Gudang Barat',
            'alamat' => 'Jl. Barat No. 1',
            'latitude' => '-6.9800000',
            'longitude' => '107.6200000',
            'aktif' => true,
        ]);
        CakupanGudang::create(['gudang_id' => $gudang1->id, 'kode_kecamatan' => $kec1->kode, 'is_utama' => true]);
        CakupanGudang::create(['gudang_id' => $gudang1->id, 'kode_kecamatan' => $kec2->kode, 'is_utama' => false]);

        // Gudang 2: covers Kec 2 & Kec 3 (500 kg, 10 kg/hari)
        $gudang2 = Gudang::create([
            'kode_gudang' => 'GDG-OVERLAP-02',
            'instansi_id' => $instansi->id,
            'kode_wilayah' => $kec2->kode,
            'nama' => 'Gudang Timur',
            'alamat' => 'Jl. Timur No. 1',
            'latitude' => '-6.9700000',
            'longitude' => '107.6300000',
            'aktif' => true,
        ]);
        CakupanGudang::create(['gudang_id' => $gudang2->id, 'kode_kecamatan' => $kec2->kode, 'is_utama' => true]);
        CakupanGudang::create(['gudang_id' => $gudang2->id, 'kode_kecamatan' => $kec3->kode, 'is_utama' => false]);

        $komoditas = Komoditas::firstOrCreate(['nama' => 'Beras Medium'], ['satuan' => 'kg']);

        $stok1 = Stok::create([
            'gudang_id' => $gudang1->id,
            'komoditas_id' => $komoditas->id,
            'jumlah_fisik_layak' => '1000.000',
            'mulai_pencatatan_lengkap' => Carbon::now()->subDays(35)->toDateString(),
            'diperbarui_pada' => Carbon::now(),
        ]);
        $stok2 = Stok::create([
            'gudang_id' => $gudang2->id,
            'komoditas_id' => $komoditas->id,
            'jumlah_fisik_layak' => '500.000',
            'mulai_pencatatan_lengkap' => Carbon::now()->subDays(35)->toDateString(),
            'diperbarui_pada' => Carbon::now(),
        ]);

        $user = User::factory()->create(['instansi_id' => $instansi->id]);
        $this->buatMutasiPenyaluranHarian($stok1, $user, hari: 30, kgPerHari: 20); // 20 kg/hari
        $this->buatMutasiPenyaluranHarian($stok2, $user, hari: 30, kgPerHari: 10); // 10 kg/hari

        // Estimasi tingkat kabupaten (mencakup kec 1, 2, dan 3 dengan total 4 baris cakupan_gudang)
        $estimasi = $this->layanan->estimasiStokWilayah($kabupaten, $komoditas);

        // Hanya ada 2 gudang unik. Total stok = 1000 + 500 = 1500 kg, pemakaian = 20 + 10 = 30 kg/hari
        // Hari ketahanan = 1500 / 30 = 50.0 hari -> AMAN
        $this->assertSame(2, $estimasi['jumlah_gudang']);
        $this->assertEqualsWithDelta(1500.0, $estimasi['stok_tersedia'], 0.001);
        $this->assertEqualsWithDelta(30.0, $estimasi['pemakaian_harian'], 0.001);
        $this->assertEqualsWithDelta(50.0, $estimasi['hari'], 0.001);
        $this->assertSame(LayananEstimasiStok::STATUS_AMAN, $estimasi['status']);
    }

    #[Test]
    public function metode_ambil_gudang_melayani_wilayah_mengembalikan_daftar_unik(): void
    {
        $this->pastikanHierarkiWilayah();
        $instansi = Instansi::firstOrCreate(
            ['nama' => 'Instansi Deduplikasi'],
            [
                'alamat' => 'Jl. Test No. 9',
                'latitude' => '-6.9388890',
                'longitude' => '107.6622220',
                'aktif' => true,
            ]
        );

        $gudang = Gudang::create([
            'kode_gudang' => 'GDG-DEDUP-' . uniqid(),
            'instansi_id' => $instansi->id,
            'kode_wilayah' => '32.04.12',
            'nama' => 'Gudang Dedup',
            'alamat' => 'Jl. Dedup',
            'latitude' => '-6.9881000',
            'longitude' => '107.6263000',
            'aktif' => true,
        ]);

        CakupanGudang::create(['gudang_id' => $gudang->id, 'kode_kecamatan' => '32.04.12', 'is_utama' => true]);

        $ids = $this->layanan->ambilGudangMelayaniWilayah('32.04.12');

        // Gudang terdaftar baik di cakupan_gudang maupun lokasi fisik gudang,
        // namun harus terdeduplikasi menjadi tepat 1 entri
        $this->assertCount(1, $ids);
        $this->assertSame([$gudang->id], $ids);
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
