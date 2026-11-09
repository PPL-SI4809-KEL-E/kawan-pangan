<?php

namespace Tests\Feature\Stok;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\Komoditas;
use App\Models\MutasiStok;
use App\Models\Stok;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\LayananStok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LayananStokTest extends TestCase
{
    use RefreshDatabase;

    private LayananStok $layanan;

    private User $pencatat;

    private Stok $stok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->layanan = new LayananStok;

        Wilayah::create([
            'kode' => '32',
            'nama' => 'Jawa Barat',
            'kode_induk' => null,
            'jumlah_penduduk' => 49900000,
        ]);

        Wilayah::create([
            'kode' => '32.04',
            'nama' => 'Kab. Bandung',
            'kode_induk' => '32',
            'jumlah_penduduk' => 3720000,
        ]);

        Wilayah::create([
            'kode' => '32.04.12',
            'nama' => 'Dayeuhkolot',
            'kode_induk' => '32.04',
            'jumlah_penduduk' => 115000,
        ]);

        $instansi = Instansi::create([
            'nama' => 'Bulog Cabang Bandung',
            'alamat' => 'Jl. Soekarno Hatta No. 711',
            'latitude' => '-6.9388890',
            'longitude' => '107.6622220',
            'aktif' => true,
        ]);

        $gudang = Gudang::create([
            'kode_gudang' => 'GDG-UJI-01',
            'instansi_id' => $instansi->id,
            'kode_wilayah' => '32.04.12',
            'nama' => 'Gudang Uji Dayeuhkolot',
            'alamat' => 'Jl. Raya Dayeuhkolot No. 10',
            'latitude' => '-6.9881000',
            'longitude' => '107.6263000',
            'aktif' => true,
        ]);

        $komoditas = Komoditas::create([
            'nama' => 'Beras Medium',
            'satuan' => 'kg',
        ]);

        $this->pencatat = User::factory()->create([
            'role' => Role::BadanResmi->value,
            'status' => StatusAkun::Aktif->value,
            'instansi_id' => $instansi->id,
        ]);

        $this->stok = $this->layanan->ambilAtauBuatStok($gudang, $komoditas);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function setiap_perubahan_saldo_menghasilkan_tepat_satu_baris_mutasi_dengan_waktu_sampai_milidetik(): void
    {
        $this->assertSame(0, MutasiStok::where('stok_id', $this->stok->id)->count());
        $this->assertSame('0.000', $this->stok->jumlah_fisik_layak);

        // 1. Penerimaan +500.250 -> saldo 500.250
        $m1 = $this->layanan->catatPenerimaan($this->stok, '500.250', $this->pencatat, 'REF-UJI-01', 'Stok masuk awal');
        $this->assertSame(1, MutasiStok::where('stok_id', $this->stok->id)->count());
        $this->assertSame('500.250', $m1->saldo_setelah);
        $this->assertSame('500.250', $this->stok->fresh()->jumlah_fisik_layak);

        // 2. Penyaluran -120.125 -> saldo 380.125
        $m2 = $this->layanan->catatPenyaluran($this->stok, '120.125', $this->pencatat, 'REF-UJI-02', 'Penyaluran desa');
        $this->assertSame(2, MutasiStok::where('stok_id', $this->stok->id)->count());
        $this->assertSame('380.125', $m2->saldo_setelah);
        $this->assertSame('380.125', $this->stok->fresh()->jumlah_fisik_layak);

        // 3. Retur +19.875 -> saldo 400.000
        $m3 = $this->layanan->catatRetur($this->stok, '19.875', $this->pencatat, 'REF-UJI-03', 'Retur kemasan utuh');
        $this->assertSame(3, MutasiStok::where('stok_id', $this->stok->id)->count());
        $this->assertSame('400.000', $m3->saldo_setelah);
        $this->assertSame('400.000', $this->stok->fresh()->jumlah_fisik_layak);

        // 4. Rusak -15.500 -> saldo 384.500
        $m4 = $this->layanan->catatRusak($this->stok, '15.500', $this->pencatat, 'REF-UJI-04', 'Beras lembap');
        $this->assertSame(4, MutasiStok::where('stok_id', $this->stok->id)->count());
        $this->assertSame('384.500', $m4->saldo_setelah);
        $this->assertSame('384.500', $this->stok->fresh()->jumlah_fisik_layak);

        // 5. Penyesuaian -4.500 -> saldo 380.000
        $m5 = $this->layanan->catatPenyesuaian($this->stok, '-4.500', $this->pencatat, 'REF-UJI-05', 'Koreksi stok opname');
        $this->assertSame(5, MutasiStok::where('stok_id', $this->stok->id)->count());
        $this->assertSame('4.500', $m5->jumlah);
        $this->assertSame('380.000', $m5->saldo_setelah);
        $this->assertSame('380.000', $this->stok->fresh()->jumlah_fisik_layak);

        // Pastikan seluruh baris mutasi memiliki format waktu sampai milidetik (YYYY-MM-DD HH:MM:SS.mmm)
        $barisMentah = DB::table('mutasi_stok')
            ->where('stok_id', $this->stok->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(5, $barisMentah);
        foreach ($barisMentah as $baris) {
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}$/',
                (string) $baris->waktu
            );
        }
    }

    #[Test]
    public function waktu_mutasi_menyimpan_presisi_milidetik_yang_tepat(): void
    {
        Carbon::setTestNow(Carbon::createFromFormat('Y-m-d H:i:s.v', '2026-11-09 14:25:36.789'));

        $mutasi = $this->layanan->catatPenerimaan(
            stok: $this->stok,
            jumlah: 250,
            pencatat: $this->pencatat,
            refOperasi: 'REF-MILIDETIK-789',
        );

        $waktuDb = DB::table('mutasi_stok')->where('id', $mutasi->id)->value('waktu');

        $this->assertSame('2026-11-09 14:25:36.789', $waktuDb);
        $this->assertSame('2026-11-09 14:25:36.789', $mutasi->fresh()->waktu->format('Y-m-d H:i:s.v'));
        $this->assertSame(789, $mutasi->fresh()->waktu->millisecond);
    }

    #[Test]
    public function perubahan_saldo_beruntun_menghasilkan_waktu_milidetik_yang_terurut_kronologis(): void
    {
        $m1 = $this->layanan->catatPenerimaan($this->stok, 100, $this->pencatat);
        $m2 = $this->layanan->catatPenyaluran($this->stok, 20, $this->pencatat);
        $m3 = $this->layanan->catatRetur($this->stok, 5, $this->pencatat);

        $w1 = $m1->fresh()->waktu->format(LayananStok::FORMAT_WAKTU_MILIDETIK);
        $w2 = $m2->fresh()->waktu->format(LayananStok::FORMAT_WAKTU_MILIDETIK);
        $w3 = $m3->fresh()->waktu->format(LayananStok::FORMAT_WAKTU_MILIDETIK);

        $this->assertLessThan($w2, $w1);
        $this->assertLessThan($w3, $w2);
        $this->assertSame('85.000', $this->stok->fresh()->jumlah_fisik_layak);
    }

    #[Test]
    public function sesuaikan_saldo_ke_nilai_target_menghasilkan_satu_baris_mutasi_penyesuaian(): void
    {
        $this->layanan->catatPenerimaan($this->stok, '300.000', $this->pencatat);

        $mutasi = $this->layanan->sesuaikanSaldo(
            stok: $this->stok,
            saldoFisikBaru: '275.500',
            pencatat: $this->pencatat,
            keterangan: 'Penyesuaian hasil cek fisik',
        );

        $this->assertSame(2, MutasiStok::where('stok_id', $this->stok->id)->count());
        $this->assertSame(MutasiStok::JENIS_PENYESUAIAN, $mutasi->jenis);
        $this->assertSame('24.500', $mutasi->jumlah);
        $this->assertSame('275.500', $mutasi->saldo_setelah);
        $this->assertSame('275.500', $this->stok->fresh()->jumlah_fisik_layak);
    }

    #[Test]
    public function perubahan_saldo_nol_ditolak_agar_tidak_menghasilkan_baris_mutasi_kosong(): void
    {
        $this->layanan->catatPenerimaan($this->stok, '100.000', $this->pencatat);

        try {
            $this->layanan->catatPenerimaan($this->stok, '0.000', $this->pencatat);
            $this->fail('Mutasi dengan jumlah 0 seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('jumlah', $e->errors());
        }

        try {
            $this->layanan->sesuaikanSaldo($this->stok, '100.000', $this->pencatat);
            $this->fail('Penyesuaian ke saldo yang sama seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('jumlah', $e->errors());
        }

        $this->assertSame(1, MutasiStok::where('stok_id', $this->stok->id)->count());
    }
}
