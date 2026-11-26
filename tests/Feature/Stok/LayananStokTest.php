<?php

namespace Tests\Feature\Stok;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\Cadangan;
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

    #[Test]
    public function ref_operasi_duplikat_pada_penerimaan_ditolak_dan_tidak_menggandakan_saldo(): void
    {
        $this->layanan->catatPenerimaan($this->stok, '100.000', $this->pencatat, 'REF-UJI-DUPLIKAT');
        $this->assertSame('100.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(1, MutasiStok::where('stok_id', $this->stok->id)->count());

        try {
            $this->layanan->catatPenerimaan($this->stok, '50.000', $this->pencatat, 'REF-UJI-DUPLIKAT');
            $this->fail('Penggunaan ref_operasi yang sama seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ref_operasi', $e->errors());
        }

        // Pastikan saldo TIDAK bertambah ganda dan baris mutasi TIDAK bertambah
        $this->assertSame('100.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(1, MutasiStok::where('stok_id', $this->stok->id)->count());
    }

    #[Test]
    public function helper_ref_sudah_ada_dan_cari_berdasarkan_ref_bekerja_dengan_benar(): void
    {
        $this->assertFalse($this->layanan->refSudahAda('REF-CEK-001'));
        $this->assertNull($this->layanan->cariBerdasarkanRef('REF-CEK-001'));

        $mutasi = $this->layanan->catatPenerimaan($this->stok, '75.000', $this->pencatat, 'REF-CEK-001');

        $this->assertTrue($this->layanan->refSudahAda('REF-CEK-001'));
        $ditemukan = $this->layanan->cariBerdasarkanRef('REF-CEK-001');
        $this->assertNotNull($ditemukan);
        $this->assertSame($mutasi->id, $ditemukan->id);
    }

    #[Test]
    public function permintaan_berulang_dengan_catat_mutasi_idempoten_tidak_menggandakan_saldo_atau_baris(): void
    {
        $pertama = $this->layanan->catatMutasiIdempoten(
            $this->stok,
            MutasiStok::JENIS_PENERIMAAN,
            '120.000',
            $this->pencatat,
            '  REF-IDEMP-001  ',
            'Penerimaan tahap 1',
        );

        $kedua = $this->layanan->catatMutasiIdempoten(
            $this->stok,
            MutasiStok::JENIS_PENERIMAAN,
            '120.000',
            $this->pencatat,
            'REF-IDEMP-001',
            'Percobaan ulang karena timeout',
        );

        $ketiga = $this->layanan->catatMutasiIdempoten(
            $this->stok,
            MutasiStok::JENIS_PENERIMAAN,
            120,
            $this->pencatat,
            '  REF-IDEMP-001 ',
        );

        $this->assertSame($pertama->id, $kedua->id);
        $this->assertSame($pertama->id, $ketiga->id);
        $this->assertSame('REF-IDEMP-001', $pertama->ref_operasi);
        $this->assertSame('120.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(1, MutasiStok::where('stok_id', $this->stok->id)->count());

        $keluarPertama = $this->layanan->catatMutasiIdempoten(
            $this->stok,
            MutasiStok::JENIS_PENYALURAN,
            '20.000',
            $this->pencatat,
            'REF-IDEMP-OUT-001',
        );
        $keluarUlang = $this->layanan->catatMutasiIdempoten(
            $this->stok,
            MutasiStok::JENIS_PENYALURAN,
            '20.000',
            $this->pencatat,
            'REF-IDEMP-OUT-001',
        );

        $this->assertSame($keluarPertama->id, $keluarUlang->id);
        $this->assertSame('100.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(2, MutasiStok::where('stok_id', $this->stok->id)->count());
    }

    #[Test]
    public function catat_mutasi_idempoten_menolak_ref_yang_sudah_dipakai_dengan_payload_berbeda(): void
    {
        $this->layanan->catatMutasiIdempoten(
            $this->stok,
            MutasiStok::JENIS_PENERIMAAN,
            '50.000',
            $this->pencatat,
            'REF-KONFLIK-001',
        );

        $komoditasLain = Komoditas::create([
            'nama' => 'Minyak Goreng',
            'satuan' => 'liter',
        ]);
        $stokLain = $this->layanan->ambilAtauBuatStok($this->stok->gudang_id, $komoditasLain);

        $skenarioKonflik = [
            [$this->stok, MutasiStok::JENIS_PENERIMAAN, '75.000'],
            [$this->stok, MutasiStok::JENIS_RETUR, '50.000'],
            [$stokLain, MutasiStok::JENIS_PENERIMAAN, '50.000'],
        ];

        foreach ($skenarioKonflik as [$targetStok, $jenis, $jumlah]) {
            try {
                $this->layanan->catatMutasiIdempoten(
                    $targetStok,
                    $jenis,
                    $jumlah,
                    $this->pencatat,
                    'REF-KONFLIK-001',
                );
                $this->fail('Penggunaan ref_operasi dengan payload berbeda seharusnya ditolak.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('ref_operasi', $e->errors());
            }
        }

        $this->assertSame('50.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame('0.000', $stokLain->fresh()->jumlah_fisik_layak);
        $this->assertSame(1, MutasiStok::count());
    }

    #[Test]
    public function catat_mutasi_idempoten_mewajibkan_ref_operasi_tidak_kosong(): void
    {
        try {
            $this->layanan->catatMutasiIdempoten(
                $this->stok,
                MutasiStok::JENIS_PENERIMAAN,
                '25.000',
                $this->pencatat,
                '   ',
            );
            $this->fail('ref_operasi kosong seharusnya ditolak pada pencatatan idempoten.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ref_operasi', $e->errors());
        }

        $this->assertSame('0.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(0, MutasiStok::where('stok_id', $this->stok->id)->count());
    }

    #[Test]
    public function ref_operasi_duplikat_dengan_spasi_ditolak_di_semua_jalur_mutasi_tanpa_mengubah_saldo(): void
    {
        $this->layanan->catatPenerimaan($this->stok, '200.000', $this->pencatat, 'REF-SEMUA-001');

        $percobaanDuplikat = [
            fn () => $this->layanan->catatPenyaluran($this->stok, '10.000', $this->pencatat, '  REF-SEMUA-001  '),
            fn () => $this->layanan->catatRetur($this->stok, '10.000', $this->pencatat, 'REF-SEMUA-001'),
            fn () => $this->layanan->catatRusak($this->stok, '10.000', $this->pencatat, 'REF-SEMUA-001'),
            fn () => $this->layanan->catatPenyesuaian($this->stok, '10.000', $this->pencatat, 'REF-SEMUA-001'),
            fn () => $this->layanan->sesuaikanSaldo($this->stok, '150.000', $this->pencatat, '  REF-SEMUA-001  '),
        ];

        foreach ($percobaanDuplikat as $eksekusi) {
            try {
                $eksekusi();
                $this->fail('ref_operasi duplikat seharusnya ditolak pada seluruh jalur mutasi.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('ref_operasi', $e->errors());
            }
        }

        $this->assertSame('200.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(1, MutasiStok::where('stok_id', $this->stok->id)->count());
    }

    #[Test]
    public function ref_operasi_melebihi_batas_191_karakter_ditolak_tanpa_mengubah_saldo(): void
    {
        $refTerlaluPanjang = str_repeat('A', 192);

        foreach ([
            fn () => $this->layanan->catatPenerimaan($this->stok, '10.000', $this->pencatat, $refTerlaluPanjang),
            fn () => $this->layanan->catatMutasiIdempoten($this->stok, MutasiStok::JENIS_PENERIMAAN, '10.000', $this->pencatat, $refTerlaluPanjang),
        ] as $eksekusi) {
            try {
                $eksekusi();
                $this->fail('ref_operasi > 191 karakter seharusnya ditolak.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('ref_operasi', $e->errors());
            }
        }

        $this->assertSame('0.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(0, MutasiStok::where('stok_id', $this->stok->id)->count());

        $mutasiSah = $this->layanan->catatMutasiIdempoten(
            $this->stok,
            MutasiStok::JENIS_PENERIMAAN,
            '10.000',
            $this->pencatat,
            str_repeat('B', 191),
        );

        $this->assertSame(191, mb_strlen($mutasiSah->ref_operasi));
        $this->assertSame('10.000', $this->stok->fresh()->jumlah_fisik_layak);
    }

    #[Test]
    public function pelanggaran_unique_constraint_db_pada_ref_operasi_dikonversi_ke_validation_exception_dan_rollback(): void
    {
        $sudahSisipkanKonflik = false;

        MutasiStok::creating(function (MutasiStok $mutasi) use (&$sudahSisipkanKonflik): void {
            if ($sudahSisipkanKonflik || $mutasi->ref_operasi !== 'REF-RACE-001') {
                return;
            }

            $sudahSisipkanKonflik = true;
            DB::table('mutasi_stok')->insert($mutasi->getAttributes());
        });

        try {
            $this->layanan->catatPenerimaan($this->stok, '40.000', $this->pencatat, 'REF-RACE-001');
            $this->fail('Konflik unique constraint pada tingkat database seharusnya dikonversi menjadi ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ref_operasi', $e->errors());
        }

        $this->assertTrue($sudahSisipkanKonflik);
        $this->assertSame('0.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(0, MutasiStok::where('stok_id', $this->stok->id)->count());
    }

    #[Test]
    public function helper_total_cadangan_aktif_dan_hitung_stok_tersedia_menghitung_dengan_presisi(): void
    {
        $this->assertSame('0.000', $this->layanan->totalCadanganAktif($this->stok));
        $this->assertSame('0.000', $this->layanan->hitungStokTersedia($this->stok));

        $this->layanan->catatPenerimaan($this->stok, '150.500', $this->pencatat, 'REF-TERSEDIA-001');

        $daftarCadangan = [
            ['RESI-CAD-01', '40.250', Cadangan::STATUS_AKTIF],
            ['RESI-CAD-02', '10.125', Cadangan::STATUS_AKTIF],
            ['RESI-CAD-03', '25.000', Cadangan::STATUS_SELESAI],
            ['RESI-CAD-04', '15.000', Cadangan::STATUS_BATAL],
        ];

        foreach ($daftarCadangan as [$noResi, $jumlahCadangan, $status]) {
            $this->buatCadangan($noResi, $jumlahCadangan, $status);
        }

        $this->assertSame('50.375', $this->layanan->totalCadanganAktif($this->stok));
        $this->assertSame('100.125', $this->layanan->hitungStokTersedia($this->stok));
    }

    #[Test]
    public function mutasi_keluar_awal_ditolak_bila_membuat_saldo_fisik_negatif(): void
    {
        $this->layanan->catatPenerimaan($this->stok, '50.000', $this->pencatat, 'REF-NEG-AWAL');

        try {
            $this->layanan->catatPenyaluran($this->stok, '50.001', $this->pencatat, 'REF-NEG-TOLAK');
            $this->fail('Mutasi keluar yang menyebabkan saldo fisik negatif seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('jumlah', $e->errors());
        }

        $this->assertSame('50.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(1, MutasiStok::where('stok_id', $this->stok->id)->count());
        $this->assertFalse($this->layanan->refSudahAda('REF-NEG-TOLAK'));
    }

    #[Test]
    public function mutasi_keluar_ditolak_bila_melebihi_stok_tersedia_karena_cadangan_aktif(): void
    {
        $this->layanan->catatPenerimaan($this->stok, '100.000', $this->pencatat, 'REF-TERSEDIA-MASUK');
        $this->buatCadangan('RESI-AKTIF-01', '30.000', Cadangan::STATUS_AKTIF);
        $this->buatCadangan('RESI-SELESAI-01', '20.000', Cadangan::STATUS_SELESAI);

        $this->assertSame('70.000', $this->layanan->hitungStokTersedia($this->stok));

        $percobaanMelebihiTersedia = [
            ['REF-SALUR-LEBIH', fn () => $this->layanan->catatPenyaluran($this->stok, '70.001', $this->pencatat, 'REF-SALUR-LEBIH')],
            ['REF-RUSAK-LEBIH', fn () => $this->layanan->catatRusak($this->stok, '70.001', $this->pencatat, 'REF-RUSAK-LEBIH')],
            ['REF-SESUAI-LEBIH', fn () => $this->layanan->catatPenyesuaian($this->stok, '-70.001', $this->pencatat, 'REF-SESUAI-LEBIH')],
        ];

        foreach ($percobaanMelebihiTersedia as [$ref, $eksekusi]) {
            try {
                $eksekusi();
                $this->fail("Mutasi keluar {$ref} yang melebihi stok tersedia seharusnya ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('jumlah', $e->errors());
            }
            $this->assertFalse($this->layanan->refSudahAda($ref));
        }

        $this->assertSame('100.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(1, MutasiStok::where('stok_id', $this->stok->id)->count());

        // Tepat menghabiskan seluruh stok tersedia (70.000) harus diperbolehkan
        $this->layanan->catatPenyaluran($this->stok, '70.000', $this->pencatat, 'REF-SALUR-PAS');
        $this->assertSame('30.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame('0.000', $this->layanan->hitungStokTersedia($this->stok));

        // Setelah stok tersedia 0.000, pengurangan sekecil 0.001 pun harus ditolak
        try {
            $this->layanan->catatPenyaluran($this->stok, '0.001', $this->pencatat, 'REF-SALUR-HABIS');
            $this->fail('Mutasi keluar saat stok tersedia 0.000 seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('jumlah', $e->errors());
        }

        $this->assertSame('30.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame(2, MutasiStok::where('stok_id', $this->stok->id)->count());
    }

    #[Test]
    public function penyaluran_untuk_pengiriman_tercadang_boleh_memakai_cadangan_sendiri_tetapi_tidak_cadangan_lain(): void
    {
        $this->layanan->catatPenerimaan($this->stok, '100.000', $this->pencatat, 'REF-KRM-MASUK');
        $pengiriman1 = $this->buatCadangan('RESI-KRM-01', '30.000', Cadangan::STATUS_AKTIF);
        $this->buatCadangan('RESI-KRM-02', '20.000', Cadangan::STATUS_AKTIF);

        // Tidak boleh mengambil hingga memakan cadangan pengiriman lain (20.000)
        try {
            $this->layanan->catatPenyaluran($this->stok, '80.001', $this->pencatat, 'REF-KRM-OVER', pengirimanId: $pengiriman1);
            $this->fail('Penyaluran pengiriman 1 tidak boleh memakan cadangan pengiriman lain.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('jumlah', $e->errors());
        }

        // Boleh menyalurkan jatah cadangan miliknya sendiri (30.000)
        $mutasi = $this->layanan->catatPenyaluran($this->stok, '30.000', $this->pencatat, 'REF-KRM-OK', pengirimanId: $pengiriman1);
        $this->assertSame('70.000', $mutasi->saldo_setelah);
        $this->assertSame('70.000', $this->stok->fresh()->jumlah_fisik_layak);
    }

    #[Test]
    public function mutasi_rusak_dan_penyesuaian_negatif_ditolak_bila_saldo_menjadi_negatif(): void
    {
        try {
            $this->layanan->catatPenyaluran($this->stok, '0.001', $this->pencatat, 'REF-NOL-SALUR');
            $this->fail('Penyaluran pada saldo 0.000 seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('jumlah', $e->errors());
        }
        $this->assertSame('0.000', $this->stok->fresh()->jumlah_fisik_layak);

        $this->layanan->catatPenerimaan($this->stok, '25.000', $this->pencatat, 'REF-BATAS-MASUK');

        foreach ([
            fn () => $this->layanan->catatRusak($this->stok, '25.001', $this->pencatat, 'REF-RUSAK-NEG'),
            fn () => $this->layanan->catatPenyesuaian($this->stok, '-25.001', $this->pencatat, 'REF-SESUAI-NEG'),
        ] as $eksekusi) {
            try {
                $eksekusi();
                $this->fail('Mutasi pengurangan yang menghasilkan saldo negatif seharusnya ditolak.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('jumlah', $e->errors());
            }
        }

        $this->assertSame('25.000', $this->stok->fresh()->jumlah_fisik_layak);

        // Pengurangan tepat sampai 0.000 diizinkan dan saldo tidak negatif
        $this->layanan->catatRusak($this->stok, '25.000', $this->pencatat, 'REF-RUSAK-PAS');
        $this->assertSame('0.000', $this->stok->fresh()->jumlah_fisik_layak);
    }

    #[Test]
    public function sesuaikan_saldo_menolak_target_negatif_dan_target_di_bawah_cadangan_aktif(): void
    {
        try {
            $this->layanan->sesuaikanSaldo($this->stok, '-0.001', $this->pencatat, 'REF-TARGET-NEG');
            $this->fail('sesuaikanSaldo dengan target negatif seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('saldo_setelah', $e->errors());
        }

        $this->layanan->catatPenerimaan($this->stok, '100.000', $this->pencatat, 'REF-TARGET-AWAL');
        $this->buatCadangan('RESI-TARGET-01', '40.000', Cadangan::STATUS_AKTIF);

        try {
            $this->layanan->sesuaikanSaldo($this->stok, '39.999', $this->pencatat, 'REF-TARGET-KURANG');
            $this->fail('sesuaikanSaldo di bawah total cadangan aktif seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('saldo_setelah', $e->errors());
        }
        $this->assertSame('100.000', $this->stok->fresh()->jumlah_fisik_layak);

        $this->layanan->sesuaikanSaldo($this->stok, '40.000', $this->pencatat, 'REF-TARGET-PAS');
        $this->assertSame('40.000', $this->stok->fresh()->jumlah_fisik_layak);
        $this->assertSame('0.000', $this->layanan->hitungStokTersedia($this->stok));
    }

    #[Test]
    public function catat_mutasi_idempoten_yang_ditolak_karena_stok_kurang_tidak_mengunci_ref_operasi(): void
    {
        $this->layanan->catatPenerimaan($this->stok, '20.000', $this->pencatat, 'REF-IDEMP-AWAL');

        try {
            $this->layanan->catatMutasiIdempoten(
                $this->stok,
                MutasiStok::JENIS_PENYALURAN,
                '50.000',
                $this->pencatat,
                'REF-IDEMP-RETRY',
            );
            $this->fail('Mutasi keluar idempoten yang melebihi stok seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('jumlah', $e->errors());
        }

        $this->assertFalse($this->layanan->refSudahAda('REF-IDEMP-RETRY'));
        $this->assertSame('20.000', $this->stok->fresh()->jumlah_fisik_layak);

        // Setelah stok ditambah, permintaan ulang dengan ref_operasi yang sama dapat diproses
        $this->layanan->catatPenerimaan($this->stok, '40.000', $this->pencatat, 'REF-IDEMP-TAMBAH');

        $pertama = $this->layanan->catatMutasiIdempoten(
            $this->stok,
            MutasiStok::JENIS_PENYALURAN,
            '50.000',
            $this->pencatat,
            'REF-IDEMP-RETRY',
        );
        $ulang = $this->layanan->catatMutasiIdempoten(
            $this->stok,
            MutasiStok::JENIS_PENYALURAN,
            '50.000',
            $this->pencatat,
            'REF-IDEMP-RETRY',
        );

        $this->assertSame($pertama->id, $ulang->id);
        $this->assertSame('10.000', $this->stok->fresh()->jumlah_fisik_layak);
    }

    private function buatCadangan(string $noResi, string $jumlah, string $status = Cadangan::STATUS_AKTIF): int
    {
        $pengirimanId = DB::table('pengiriman')->insertGetId([
            'no_resi' => $noResi,
            'gudang_asal_id' => $this->stok->gudang_id,
            'kode_wilayah_tujuan' => '32.04.12',
            'petugas_id' => $this->pencatat->id,
            'status' => 'DISETUJUI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cadangan::create([
            'stok_id' => $this->stok->id,
            'pengiriman_id' => $pengirimanId,
            'jumlah' => $jumlah,
            'status' => $status,
            'dibuat_pada' => now(),
            'selesai_pada' => $status === Cadangan::STATUS_AKTIF ? null : now(),
            'is_contoh' => false,
        ]);

        return $pengirimanId;
    }
}