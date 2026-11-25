<?php

namespace Tests\Unit\Stok;

use App\Models\AmbangStatus;
use App\Services\LayananEstimasiStok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PSKE-78 · Kasus batas 13,9; 14; 30; 30,1 hari lulus.
 *
 * Menguji ketepatan penentuan status (Kritis, Waspada, Aman) pada nilai-nilai ambang batas:
 * - 13,9 hari (< 14) -> KRITIS
 * - 14,0 hari (>= 14 dan <= 30) -> WASPADA
 * - 30,0 hari (<= 30) -> WASPADA
 * - 30,1 hari (> 30) -> AMAN
 */
class KasusBatasAmbangTest extends TestCase
{
    use RefreshDatabase;

    private LayananEstimasiStok $layanan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->layanan = new LayananEstimasiStok();
    }

    #[Test]
    public function kasus_batas_13_koma_9_hari_berstatus_kritis(): void
    {
        $ambang = AmbangStatus::aktif();

        // 13.9 hari strictly < 14 -> status KRITIS
        $status = $ambang->statusUntukHari(13.9);
        $this->assertSame('KRITIS', $status);

        $statusLayanan = $this->layanan->tentukanStatus(13.9);
        $this->assertSame(LayananEstimasiStok::STATUS_KRITIS, $statusLayanan);
    }

    #[Test]
    public function kasus_batas_14_koma_0_hari_berstatus_waspada(): void
    {
        $ambang = AmbangStatus::aktif();

        // 14.0 hari pada ambang batas bawah -> status WASPADA (bukan KRITIS)
        $status = $ambang->statusUntukHari(14.0);
        $this->assertSame('WASPADA', $status);

        $statusLayanan = $this->layanan->tentukanStatus(14.0);
        $this->assertSame(LayananEstimasiStok::STATUS_WASPADA, $statusLayanan);
    }

    #[Test]
    public function kasus_batas_30_koma_0_hari_berstatus_waspada(): void
    {
        $ambang = AmbangStatus::aktif();

        // 30.0 hari pada ambang batas atas -> status WASPADA (bukan AMAN)
        $status = $ambang->statusUntukHari(30.0);
        $this->assertSame('WASPADA', $status);

        $statusLayanan = $this->layanan->tentukanStatus(30.0);
        $this->assertSame(LayananEstimasiStok::STATUS_WASPADA, $statusLayanan);
    }

    #[Test]
    public function kasus_batas_30_koma_1_hari_berstatus_aman(): void
    {
        $ambang = AmbangStatus::aktif();

        // 30.1 hari strictly > 30 -> status AMAN
        $status = $ambang->statusUntukHari(30.1);
        $this->assertSame('AMAN', $status);

        $statusLayanan = $this->layanan->tentukanStatus(30.1);
        $this->assertSame(LayananEstimasiStok::STATUS_AMAN, $statusLayanan);
    }

    #[Test]
    public function perbandingan_presisi_desimal_tanpa_pembulatan_atau_pemotongan_integer(): void
    {
        $ambang = AmbangStatus::aktif();

        // 13.999 tidak boleh dibulatkan ke atas menjadi 14
        $this->assertSame('KRITIS', $ambang->statusUntukHari(13.999));
        $this->assertSame(LayananEstimasiStok::STATUS_KRITIS, $this->layanan->tentukanStatus(13.999));

        // 14.000 tepat batas kritis/waspada
        $this->assertSame('WASPADA', $ambang->statusUntukHari(14.000));
        $this->assertSame(LayananEstimasiStok::STATUS_WASPADA, $this->layanan->tentukanStatus(14.000));

        // 30.000 tepat batas waspada/aman
        $this->assertSame('WASPADA', $ambang->statusUntukHari(30.000));
        $this->assertSame(LayananEstimasiStok::STATUS_WASPADA, $this->layanan->tentukanStatus(30.000));

        // 30.001 tidak boleh dibulatkan ke bawah menjadi 30
        $this->assertSame('AMAN', $ambang->statusUntukHari(30.001));
        $this->assertSame(LayananEstimasiStok::STATUS_AMAN, $this->layanan->tentukanStatus(30.001));
    }

    #[Test]
    public function kasus_batas_mengikuti_perubahan_ambang_status_kustom(): void
    {
        // Ubah batas kustom: Kritis < 7 hari, Aman > 21 hari
        $ambang = AmbangStatus::aktif();
        $ambang->kritis_hari = 7;
        $ambang->aman_hari = 21;
        $ambang->save();

        $ambangBaru = AmbangStatus::aktif();

        // 6.9 -> KRITIS (< 7)
        $this->assertSame('KRITIS', $ambangBaru->statusUntukHari(6.9));
        $this->assertSame(LayananEstimasiStok::STATUS_KRITIS, $this->layanan->tentukanStatus(6.9, $ambangBaru));

        // 7.0 -> WASPADA (>= 7 dan <= 21)
        $this->assertSame('WASPADA', $ambangBaru->statusUntukHari(7.0));
        $this->assertSame(LayananEstimasiStok::STATUS_WASPADA, $this->layanan->tentukanStatus(7.0, $ambangBaru));

        // 21.0 -> WASPADA
        $this->assertSame('WASPADA', $ambangBaru->statusUntukHari(21.0));
        $this->assertSame(LayananEstimasiStok::STATUS_WASPADA, $this->layanan->tentukanStatus(21.0, $ambangBaru));

        // 21.1 -> AMAN (> 21)
        $this->assertSame('AMAN', $ambangBaru->statusUntukHari(21.1));
        $this->assertSame(LayananEstimasiStok::STATUS_AMAN, $this->layanan->tentukanStatus(21.1, $ambangBaru));
    }

    #[Test]
    public function kalkulasi_ketahanan_menghasilkan_nilai_hari_yang_akurat(): void
    {
        // 139 kg / 10 kg per hari = 13.9 hari -> KRITIS
        $hari1 = $this->layanan->hitungKetahanan(139, 10);
        $this->assertEqualsWithDelta(13.9, $hari1, 0.001);
        $this->assertSame(LayananEstimasiStok::STATUS_KRITIS, $this->layanan->tentukanStatus($hari1));

        // 140 kg / 10 kg per hari = 14.0 hari -> WASPADA
        $hari2 = $this->layanan->hitungKetahanan(140, 10);
        $this->assertEqualsWithDelta(14.0, $hari2, 0.001);
        $this->assertSame(LayananEstimasiStok::STATUS_WASPADA, $this->layanan->tentukanStatus($hari2));

        // 300 kg / 10 kg per hari = 30.0 hari -> WASPADA
        $hari3 = $this->layanan->hitungKetahanan(300, 10);
        $this->assertEqualsWithDelta(30.0, $hari3, 0.001);
        $this->assertSame(LayananEstimasiStok::STATUS_WASPADA, $this->layanan->tentukanStatus($hari3));

        // 301 kg / 10 kg per hari = 30.1 hari -> AMAN
        $hari4 = $this->layanan->hitungKetahanan(301, 10);
        $this->assertEqualsWithDelta(30.1, $hari4, 0.001);
        $this->assertSame(LayananEstimasiStok::STATUS_AMAN, $this->layanan->tentukanStatus($hari4));
    }
}
