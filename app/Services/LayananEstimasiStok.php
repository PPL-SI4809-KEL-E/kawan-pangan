<?php

namespace App\Services;

use App\Models\AmbangStatus;
use App\Models\Cadangan;
use App\Models\Gudang;
use App\Models\Komoditas;
use App\Models\MutasiStok;
use App\Models\Stok;
use App\Models\Wilayah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class LayananEstimasiStok
{
    public const MINIMUM_HARI_RIWAYAT = 30;

    public const STATUS_KRITIS = 'KRITIS';

    public const STATUS_WASPADA = 'WASPADA';

    public const STATUS_AMAN = 'AMAN';

    public const STATUS_RIWAYAT_KURANG = 'RIWAYAT_KURANG';

    public const STATUS_TANPA_DATA = 'TANPA_DATA';

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
            ->withMin(['mutasi as penyaluran_pertama' => fn ($q) => $q
                ->where('jenis', MutasiStok::JENIS_PENYALURAN)], 'waktu')
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

    public function estimasiStokGudang(Gudang|int $gudang, Komoditas|int $komoditas, ?Carbon $tanggalAcuan = null): array
    {
        $acuan = $tanggalAcuan ?? Carbon::today();
        $query = Stok::where('gudang_id', $gudang instanceof Gudang ? $gudang->id : $gudang)
            ->where('komoditas_id', $komoditas instanceof Komoditas ? $komoditas->id : $komoditas);

        return $this->ringkas($this->denganTotalPeriode($query, $acuan)->get(), $acuan);
    }

    public function ambilGudangMelayaniWilayah(Wilayah|string $wilayah): array
    {
        $wilayah = $wilayah instanceof Wilayah ? $wilayah : Wilayah::find($wilayah);
        if (! $wilayah) {
            return [];
        }

        $kode = $wilayah->kode;
        $kecamatan = $wilayah->level === 4 ? $wilayah->kode_induk : $kode;

        return Gudang::where('aktif', true)->where(function ($gudang) use ($kode, $kecamatan, $wilayah) {
            $gudang->where('kode_wilayah', $kode)
                ->orWhere('kode_wilayah', 'like', $kode.'.%')
                ->orWhere('kode_wilayah', $kecamatan)
                ->orWhereHas('cakupan', function ($cakupan) use ($wilayah, $kecamatan) {
                    $cakupan->where('kode_kecamatan', $kecamatan);
                    if ($wilayah->level < 3) {
                        $cakupan->orWhere('kode_kecamatan', 'like', $kecamatan.'.%');
                    }
                });
        })->orderBy('id')->pluck('id')->all();
    }

    public function estimasiStokWilayah(Wilayah|string $wilayah, Komoditas|int $komoditas, ?Carbon $tanggalAcuan = null): array
    {
        return $this->agregasiStokMultiWilayah([$wilayah], $komoditas, $tanggalAcuan);
    }

    /** @param  array<Wilayah|string>  $daftarWilayah */
    public function agregasiStokMultiWilayah(array $daftarWilayah, Komoditas|int $komoditas, ?Carbon $tanggalAcuan = null): array
    {
        $acuan = $tanggalAcuan ?? Carbon::today();
        $gudang = collect($daftarWilayah)->flatMap(fn ($wilayah) => $this->ambilGudangMelayaniWilayah($wilayah))->unique();
        $query = Stok::whereIn('gudang_id', $gudang)
            ->where('komoditas_id', $komoditas instanceof Komoditas ? $komoditas->id : $komoditas);
        $hasil = $this->ringkas($this->denganTotalPeriode($query, $acuan)->get(), $acuan);
        if ($gudang->isEmpty()) {
            $hasil['pesan'] = 'Belum ada gudang pangan yang melayani wilayah ini.';
        }

        return $hasil + ['jumlah_gudang' => $gudang->count()];
    }

    /**
     * Ringkas satu atau beberapa baris stok dari denganTotalPeriode().
     * Stok fisik menentukan tanggal habis. Cadangan aktif mengurangi jumlah yang dapat disalurkan.
     *
     * @param  iterable<Stok>  $daftarStok
     */
    public function ringkas(iterable $daftarStok, Carbon $acuan, ?AmbangStatus $ambang = null): array
    {
        $fisik = $cadangan = $tersedia = $penyaluran = 0.0;
        $riwayat = null;
        foreach ($daftarStok as $stok) {
            $fisik += (float) $stok->jumlah_fisik_layak;
            $cadangan += (float) $stok->cadangan_aktif;
            $tersedia += max(0.0, (float) $stok->jumlah_fisik_layak - (float) $stok->cadangan_aktif);
            $penyaluran += (float) $stok->penyaluran_periode;
            $mulai = $stok->mulai_pencatatan_lengkap ?? $stok->penyaluran_pertama;
            $umur = $mulai ? (int) Carbon::parse($mulai)->startOfDay()->diffInDays($acuan->copy()->startOfDay()) + 1 : 0;
            $riwayat = min($riwayat ?? PHP_INT_MAX, max(0, $umur));
        }

        $hasil = [
            'stok_fisik' => $fisik,
            'cadangan_aktif' => $cadangan,
            'stok_tersedia' => $tersedia,
            'pemakaian_harian' => $penyaluran / self::MINIMUM_HARI_RIWAYAT,
            'riwayat_hari' => $riwayat ?? 0,
            'hari' => null,
            'perkiraan_habis' => null,
            'pesan' => null,
        ];
        if ($riwayat === null) {
            $hasil['pesan'] = 'Belum ada catatan stok untuk komoditas ini.';

            return $hasil + ['status' => self::STATUS_TANPA_DATA, 'tanpa_data' => true];
        }
        if ($tersedia <= 0.0) {
            $hasil['hari'] = 0.0;
            $hasil['perkiraan_habis'] = $acuan->toDateString();
            $hasil['pesan'] = 'Stok habis atau seluruh stok sudah dicadangkan.';

            return $hasil + ['status' => self::STATUS_KRITIS];
        }
        if ($riwayat < self::MINIMUM_HARI_RIWAYAT) {
            $hasil['pesan'] = "Estimasi belum dapat dihitung. Riwayat penyaluran baru {$riwayat} dari 30 hari.";

            return $hasil + ['status' => self::STATUS_RIWAYAT_KURANG, 'riwayat_kurang' => true];
        }

        // Kolom kuantitas memiliki tiga desimal. Rasio satuan terkecil menjaga batas
        // tepat 14/30 hari dari galat pembagian rata-rata harian pecahan.
        $hari = $this->hitungKetahanan(round($fisik * 1000) * self::MINIMUM_HARI_RIWAYAT, round($penyaluran * 1000));
        $hasil['status'] = $this->tentukanStatus($hari, $ambang);
        if (is_infinite($hari)) {
            $hasil['is_tak_terhingga'] = true;
            $hasil['pesan'] = 'Tidak ada penyaluran dalam 30 hari terakhir.';
        } else {
            $hasil['hari'] = $hari;
            $hasil['perkiraan_habis'] = $acuan->copy()->addDays((int) ceil($hari))->toDateString();
        }

        return $hasil;
    }
}
