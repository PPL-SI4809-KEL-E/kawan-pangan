<?php

namespace App\Services;

use App\Models\AmbangStatus;
use App\Models\Cadangan;
use App\Models\MutasiStok;
use App\Models\Stok;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class LayananEstimasiStok
{
    public const MINIMUM_HARI_RIWAYAT = 30;

    public const STATUS_KRITIS = 'KRITIS';

    public const STATUS_WASPADA = 'WASPADA';

    public const STATUS_AMAN = 'AMAN';

    public function hitungKetahanan(float|int|string $stokFisik, float|int|string $pemakaianHarian): float
    {
        $fisik = (float) $stokFisik;
        $pemakaian = (float) $pemakaianHarian;
        if ($pemakaian < 0 || $fisik < 0) {
            throw new InvalidArgumentException('Stok fisik dan penggunaan harian harus nonnegatif.');
        }
        if ($fisik === 0.0) {
            return 0.0;
        }

        return $pemakaian === 0.0 ? INF : $fisik / $pemakaian;
    }

    public function tentukanStatus(float $hari, ?AmbangStatus $ambang = null): string
    {
        if ($hari === INF) {
            return self::STATUS_AMAN;
        }

        return ($ambang ?? AmbangStatus::aktif())->statusUntukHari($hari);
    }

    /**
     * Tambahkan kedua total tanpa menggandakan mutasi ketika stok memiliki beberapa cadangan.
     * Jendela mencakup tanggal acuan dan 29 tanggal sebelumnya.
     */
    public function denganTotalPeriode(Builder $stok, Carbon $acuan): Builder
    {
        $mulai = $acuan->copy()->startOfDay()->subDays(self::MINIMUM_HARI_RIWAYAT - 1);
        $besok = $acuan->copy()->startOfDay()->addDay();

        return $stok
            ->withSum(['mutasi as penyaluran_periode' => fn ($q) => $q
                ->where('jenis', MutasiStok::JENIS_PENYALURAN)
                ->where('waktu', '>=', $mulai)
                ->where('waktu', '<', $besok)], 'jumlah')
            ->withSum(['cadangan as cadangan_aktif' => fn ($q) => $q
                ->where('status', Cadangan::STATUS_AKTIF)], 'jumlah');
    }

    public function hitungPemakaianHarian(Stok|int $stok, ?Carbon $tanggalAcuan = null): float
    {
        $baris = $this->denganTotalPeriode(Stok::query(), $tanggalAcuan ?? Carbon::today())
            ->findOrFail($stok instanceof Stok ? $stok->id : $stok);

        return (float) $baris->penyaluran_periode / self::MINIMUM_HARI_RIWAYAT;
    }
}
