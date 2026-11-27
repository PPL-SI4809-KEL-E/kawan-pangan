<?php

namespace App\Services;

use App\Models\AmbangStatus;
use App\Models\CakupanGudang;
use App\Models\Gudang;
use App\Models\Komoditas;
use App\Models\MutasiStok;
use App\Models\Stok;
use App\Models\Wilayah;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Layanan agregasi peta choropleth dengan drill-down per level (PSKE-69).
 *
 * - PSKE-70: Data diambil per level yang dibuka (Provinsi / Kab-Kota / Kecamatan), bukan nasional sekaligus.
 * - PSKE-71: Warna dan status wilayah dihitung dari rekap gudang unik yang melayani wilayah tersebut.
 * - PSKE-72: Seluruh query dilakukan secara batch (tanpa N+1) agar respons <= 2 detik p95.
 */
class LayananPetaChoropleth
{
    public const LEVEL_NAMA = [
        1 => 'Provinsi',
        2 => 'Kabupaten/Kota',
        3 => 'Kecamatan',
    ];

    private const PRIORITAS_STATUS = [
        'kritis' => 5,
        'waspada' => 4,
        'aman' => 3,
        'riwayat_kurang' => 2,
        'tanpa_data' => 1,
    ];

    public function __construct(
        private readonly LayananEstimasiStok $layananEstimasi,
    ) {}

    /**
     * Ambil payload peta lengkap untuk tepat satu level dan induk tertentu.
     *
     * @return array<string, mixed>
     */
    public function ambilLevel(
        int $level = 1,
        ?string $kodeInduk = null,
        ?int $komoditasId = null,
        ?Carbon $tanggalAcuan = null,
    ): array {
        if (! in_array($level, [1, 2, 3], true)) {
            throw new InvalidArgumentException('Level peta harus 1 (Provinsi), 2 (Kabupaten/Kota), atau 3 (Kecamatan).');
        }

        if ($level === 1) {
            $kodeInduk = null;
        } elseif ($kodeInduk === null || trim($kodeInduk) === '') {
            throw new InvalidArgumentException('kode_induk wajib diisi untuk level '.$level.'.');
        }

        $acuan = $tanggalAcuan ?? Carbon::today();
        $indukModel = null;

        if ($kodeInduk !== null) {
            $indukModel = Wilayah::where('kode', $kodeInduk)
                ->where('level', $level - 1)
                ->first();

            if (! $indukModel) {
                throw new InvalidArgumentException("Wilayah induk {$kodeInduk} untuk level {$level} tidak ditemukan.");
            }
        }

        $daftarWilayah = Wilayah::query()
            ->where('level', $level)
            ->when($kodeInduk !== null, fn ($q) => $q->where('kode_induk', $kodeInduk))
            ->orderBy('nama')
            ->get(['kode', 'nama', 'level', 'kode_induk', 'jumlah_penduduk', 'lat', 'lng', 'is_contoh']);

        $breadcrumbs = $this->bangunBreadcrumbs($level, $indukModel);
        $daftarKomoditas = Komoditas::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'satuan']);

        if ($daftarWilayah->isEmpty()) {
            return [
                'level' => $level,
                'level_nama' => self::LEVEL_NAMA[$level],
                'kode_induk' => $kodeInduk,
                'induk' => $indukModel ? ['kode' => $indukModel->kode, 'nama' => $indukModel->nama] : null,
                'breadcrumbs' => $breadcrumbs,
                'komoditas_id' => $komoditasId,
                'daftar_komoditas' => $daftarKomoditas->map->only(['id', 'nama', 'satuan'])->values()->all(),
                'geojson_url' => $this->urlGeoJson($level, $kodeInduk),
                'wilayah' => [],
            ];
        }

        // Hitung jumlah anak per wilayah dalam 1 batch query untuk menentukan kemampuan drill-down.
        $kodeList = $daftarWilayah->pluck('kode')->all();
        $jumlahAnakMap = $level < 3
            ? Wilayah::whereIn('kode_induk', $kodeList)
                ->selectRaw('kode_induk, COUNT(*) as total')
                ->groupBy('kode_induk')
                ->pluck('total', 'kode_induk')
                ->all()
            : [];

        // Petakan gudang aktif unik yang melayani tiap wilayah pada level yang dibuka.
        [$gudangPerWilayah, $gudangMap, $gudangUtamaKecamatan] = $this->petakanGudangUnikPerWilayah(
            $level,
            $kodeInduk,
            $kodeList,
        );

        $semuaGudangIds = collect($gudangPerWilayah)
            ->flatMap(fn (array $ids) => $ids)
            ->unique()
            ->values()
            ->all();

        $ambang = AmbangStatus::aktif();
        $stokGrouped = collect();
        $mutasiPerGudang = collect();

        if (! empty($semuaGudangIds)) {
            $stokQuery = Stok::query()
                ->whereIn('gudang_id', $semuaGudangIds)
                ->when($komoditasId !== null, fn ($q) => $q->where('komoditas_id', $komoditasId))
                ->with('komoditas:id,nama,satuan,aktif');

            $semuaStok = $this->layananEstimasi
                ->denganTotalPeriode($stokQuery, $acuan)
                ->get()
                ->filter(fn (Stok $s) => $s->komoditas && ($komoditasId !== null || $s->komoditas->aktif));

            $stokGrouped = $semuaStok->groupBy('gudang_id');

            // Ambil aktivitas mutasi terkini untuk gudang terlibat (1 query teratas).
            $mutasiPerGudang = MutasiStok::query()
                ->join('stok', 'stok.id', '=', 'mutasi_stok.stok_id')
                ->join('komoditas', 'komoditas.id', '=', 'stok.komoditas_id')
                ->whereIn('stok.gudang_id', $semuaGudangIds)
                ->when($komoditasId !== null, fn ($q) => $q->where('stok.komoditas_id', $komoditasId))
                ->orderByDesc('mutasi_stok.waktu')
                ->limit(150)
                ->get([
                    'stok.gudang_id',
                    'mutasi_stok.jenis',
                    'mutasi_stok.jumlah',
                    'mutasi_stok.waktu',
                    'komoditas.nama as nama_komoditas',
                    'komoditas.satuan as satuan_komoditas',
                ])
                ->groupBy('gudang_id');
        }

        $labelInduk = $indukModel?->nama ?? 'Indonesia';
        $hasilWilayah = $daftarWilayah->map(function (Wilayah $wilayah) use (
            $level,
            $labelInduk,
            $gudangPerWilayah,
            $gudangMap,
            $gudangUtamaKecamatan,
            $stokGrouped,
            $mutasiPerGudang,
            $jumlahAnakMap,
            $acuan,
            $ambang,
            $komoditasId,
        ) {
            $gudangIds = $gudangPerWilayah[$wilayah->kode] ?? [];

            return $this->susunRingkasanWilayah(
                wilayah: $wilayah,
                level: $level,
                labelInduk: $labelInduk,
                gudangIds: $gudangIds,
                gudangMap: $gudangMap,
                gudangUtamaKecamatan: $gudangUtamaKecamatan,
                stokGrouped: $stokGrouped,
                mutasiPerGudang: $mutasiPerGudang,
                jumlahAnak: (int) ($jumlahAnakMap[$wilayah->kode] ?? 0),
                acuan: $acuan,
                ambang: $ambang,
                komoditasId: $komoditasId,
            );
        })->values()->all();

        return [
            'level' => $level,
            'level_nama' => self::LEVEL_NAMA[$level],
            'kode_induk' => $kodeInduk,
            'induk' => $indukModel ? ['kode' => $indukModel->kode, 'nama' => $indukModel->nama] : null,
            'breadcrumbs' => $breadcrumbs,
            'komoditas_id' => $komoditasId,
            'daftar_komoditas' => $daftarKomoditas->map->only(['id', 'nama', 'satuan'])->values()->all(),
            'geojson_url' => $this->urlGeoJson($level, $kodeInduk),
            'wilayah' => $hasilWilayah,
        ];
    }

    /**
     * Cek aman apakah tabel wilayah tersedia dan berisi data (untuk rute beranda publik).
     */
    public function tersediaUntukBeranda(): bool
    {
        return Schema::hasTable('wilayah')
            && Schema::hasTable('gudang')
            && Schema::hasTable('stok')
            && Wilayah::where('level', 1)->exists();
    }

    /**
     * Petakan daftar ID gudang aktif yang unik untuk setiap kode wilayah pada level tertentu.
     *
     * @param  array<int, string>  $kodeList
     * @return array{0: array<string, array<int, int>>, 1: EloquentCollection<int, Gudang>, 2: array<string, int>}
     */
    private function petakanGudangUnikPerWilayah(int $level, ?string $kodeInduk, array $kodeList): array
    {
        $gudangSet = [];
        $kodeLookup = array_flip($kodeList);

        $gudangList = Gudang::query()
            ->where('aktif', true)
            ->when($kodeInduk !== null, function ($q) use ($kodeInduk) {
                $q->where(function ($sub) use ($kodeInduk) {
                    $sub->where('kode_wilayah', $kodeInduk)
                        ->orWhere('kode_wilayah', 'like', $kodeInduk.'.%')
                        ->orWhereHas('cakupan', fn ($c) => $c
                            ->where('kode_kecamatan', $kodeInduk)
                            ->orWhere('kode_kecamatan', 'like', $kodeInduk.'.%'));
                });
            })
            ->get(['id', 'nama', 'kode_wilayah'])
            ->keyBy('id');

        foreach ($gudangList as $gudang) {
            $targetKode = $this->ekstrakKodePadaLevel((string) $gudang->kode_wilayah, $level);
            if ($targetKode !== null && isset($kodeLookup[$targetKode])) {
                $gudangSet[$targetKode][$gudang->id] = true;
            }
        }

        $cakupanList = CakupanGudang::query()
            ->whereIn('gudang_id', $gudangList->keys())
            ->when($kodeInduk !== null, fn ($q) => $q->where(function ($sub) use ($kodeInduk) {
                $sub->where('kode_kecamatan', $kodeInduk)
                    ->orWhere('kode_kecamatan', 'like', $kodeInduk.'.%');
            }))
            ->get(['gudang_id', 'kode_kecamatan', 'is_utama']);

        $gudangUtamaKecamatan = [];
        foreach ($cakupanList as $cakupan) {
            if (! $gudangList->has($cakupan->gudang_id)) {
                continue;
            }

            $targetKode = $this->ekstrakKodePadaLevel((string) $cakupan->kode_kecamatan, $level);
            if ($targetKode !== null && isset($kodeLookup[$targetKode])) {
                $gudangSet[$targetKode][$cakupan->gudang_id] = true;
            }

            if ($cakupan->is_utama && ! isset($gudangUtamaKecamatan[$cakupan->kode_kecamatan])) {
                $gudangUtamaKecamatan[$cakupan->kode_kecamatan] = (int) $cakupan->gudang_id;
            }
        }

        $gudangPerWilayah = [];
        foreach ($gudangSet as $kode => $idsMap) {
            $ids = array_keys($idsMap);
            sort($ids);
            $gudangPerWilayah[$kode] = $ids;
        }

        return [$gudangPerWilayah, $gudangList, $gudangUtamaKecamatan];
    }

    private function ekstrakKodePadaLevel(string $kode, int $level): ?string
    {
        $bagian = explode('.', $kode);
        if (count($bagian) < $level) {
            return null;
        }

        return implode('.', array_slice($bagian, 0, $level));
    }

    /**
     * @param  array<int, int>  $gudangIds
     * @param  EloquentCollection<int, Gudang>  $gudangMap
     * @param  array<string, int>  $gudangUtamaKecamatan
     * @return array<string, mixed>
     */
    private function susunRingkasanWilayah(
        Wilayah $wilayah,
        int $level,
        string $labelInduk,
        array $gudangIds,
        EloquentCollection $gudangMap,
        array $gudangUtamaKecamatan,
        \Illuminate\Support\Collection $stokGrouped,
        \Illuminate\Support\Collection $mutasiPerGudang,
        int $jumlahAnak,
        Carbon $acuan,
        AmbangStatus $ambang,
        ?int $komoditasId,
    ): array {
        $stokWilayah = collect($gudangIds)
            ->flatMap(fn (int $id) => $stokGrouped->get($id, collect()));

        $ringkasanKomoditas = [];
        $statusTerburuk = 'tanpa_data';
        $skorTerburuk = self::PRIORITAS_STATUS['tanpa_data'];
        $hariMinimum = null;
        $totalStokKg = 0.0;
        $adaKg = false;
        $totalStokSemua = 0.0;
        $totalStokFisik = 0.0;
        $totalCadangan = 0.0;
        $terakhirDiperbarui = null;

        foreach ($stokWilayah->groupBy('komoditas_id') as $group) {
            /** @var Stok $contoh */
            $contoh = $group->first();
            $ringkas = $this->layananEstimasi->ringkas($group, $acuan, $ambang);
            $status = $this->normalisasiStatus($ringkas['status']);

            $skor = self::PRIORITAS_STATUS[$status] ?? 1;
            if ($skor > $skorTerburuk) {
                $skorTerburuk = $skor;
                $statusTerburuk = $status;
            }

            if ($ringkas['hari'] !== null && is_finite($ringkas['hari'])) {
                $hariMinimum = $hariMinimum === null
                    ? (float) $ringkas['hari']
                    : min($hariMinimum, (float) $ringkas['hari']);
            }

            if (mb_strtolower($contoh->komoditas->satuan, 'UTF-8') === 'kg') {
                $totalStokKg += (float) $ringkas['stok_tersedia'];
                $adaKg = true;
            }
            $totalStokSemua += (float) $ringkas['stok_tersedia'];
            $totalStokFisik += (float) $ringkas['stok_fisik'];
            $totalCadangan += (float) $ringkas['cadangan_aktif'];

            $ringkasanKomoditas[] = [
                'id' => (int) $contoh->komoditas_id,
                'nama' => $contoh->komoditas->nama,
                'satuan' => $contoh->komoditas->satuan,
                'jumlah' => round((float) $ringkas['stok_tersedia'], 2),
                'hari' => $ringkas['hari'] === null
                    ? null
                    : (is_infinite($ringkas['hari']) ? null : round((float) $ringkas['hari'], 1)),
                'status' => $status,
            ];

            foreach ($group as $barisStok) {
                if ($barisStok->diperbarui_pada && ($terakhirDiperbarui === null || $barisStok->diperbarui_pada->gt($terakhirDiperbarui))) {
                    $terakhirDiperbarui = $barisStok->diperbarui_pada;
                }
            }
        }

        // Susun nama gudang utama / ringkasan gudang unik
        $jumlahGudang = count($gudangIds);
        if ($jumlahGudang === 0) {
            $labelGudang = 'Belum ada gudang melayani';
        } elseif ($level === 3 && isset($gudangUtamaKecamatan[$wilayah->kode])) {
            $utamaId = $gudangUtamaKecamatan[$wilayah->kode];
            $namaUtama = $gudangMap->get($utamaId)?->nama ?? 'Gudang utama';
            $labelGudang = $jumlahGudang > 1
                ? "{$namaUtama} (+".($jumlahGudang - 1).' pendukung)'
                : $namaUtama;
        } elseif ($jumlahGudang === 1) {
            $labelGudang = $gudangMap->get($gudangIds[0])?->nama ?? '1 gudang melayani';
        } else {
            $labelGudang = "{$jumlahGudang} gudang unik melayani";
        }

        // Aktivitas terakhir dari gudang-gudang unik
        $aktivitas = collect($gudangIds)
            ->flatMap(fn (int $id) => $mutasiPerGudang->get($id, collect()))
            ->sortByDesc('waktu')
            ->take(5)
            ->map(function ($m) {
                $jenisCss = match ($m->jenis) {
                    MutasiStok::JENIS_PENERIMAAN, MutasiStok::JENIS_RETUR => 'masuk',
                    MutasiStok::JENIS_RUSAK => 'rusak',
                    default => 'keluar',
                };
                $labelAksi = match ($m->jenis) {
                    MutasiStok::JENIS_PENERIMAAN => 'Penerimaan',
                    MutasiStok::JENIS_PENYALURAN => 'Penyaluran',
                    MutasiStok::JENIS_RETUR => 'Retur',
                    MutasiStok::JENIS_RUSAK => 'Rusak',
                    default => 'Penyesuaian',
                };

                return [
                    'jenis' => $jenisCss,
                    'judul' => sprintf('%s %s %s %s', $labelAksi, number_format((float) $m->jumlah, 0, ',', '.'), $m->satuan_komoditas, $m->nama_komoditas),
                    'waktu' => Carbon::parse($m->waktu)->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $totalStokTersedia = $adaKg ? $totalStokKg : $totalStokSemua;
        $kotaKonteks = $level === 1 ? 'Provinsi' : $labelInduk;

        return [
            'id' => $wilayah->kode,
            'kode_wilayah' => $wilayah->kode,
            'nama' => $wilayah->nama,
            'kode_induk' => $wilayah->kode_induk,
            'level' => (int) $wilayah->level,
            'kota' => $kotaKonteks,
            'lat' => $wilayah->lat !== null ? (float) $wilayah->lat : null,
            'lng' => $wilayah->lng !== null ? (float) $wilayah->lng : null,
            'status' => $statusTerburuk,
            'hari' => $hariMinimum !== null ? round($hariMinimum, 1) : null,
            'stok_kg' => round($totalStokTersedia, 2),
            'stok_fisik_kg' => round($totalStokFisik, 2),
            'cadangan_kg' => round($totalCadangan, 2),
            'jumlah_gudang' => $jumlahGudang,
            'gudang' => $labelGudang,
            'jumlah_anak' => $jumlahAnak,
            'bisa_drill_down' => $level < 3 && $jumlahAnak > 0,
            'is_contoh' => (bool) $wilayah->is_contoh,
            'diperbarui' => $terakhirDiperbarui?->toIso8601String(),
            'komoditas' => $ringkasanKomoditas,
            'aktivitas' => $aktivitas,
        ];
    }

    private function normalisasiStatus(string $status): string
    {
        return match ($status) {
            LayananEstimasiStok::STATUS_KRITIS => 'kritis',
            LayananEstimasiStok::STATUS_WASPADA => 'waspada',
            LayananEstimasiStok::STATUS_AMAN => 'aman',
            LayananEstimasiStok::STATUS_RIWAYAT_KURANG => 'riwayat_kurang',
            default => 'tanpa_data',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bangunBreadcrumbs(int $level, ?Wilayah $indukModel): array
    {
        $crumbs = [
            ['level' => 1, 'kode' => null, 'nama' => 'Indonesia'],
        ];

        if ($level === 2 && $indukModel) {
            $crumbs[] = ['level' => 2, 'kode' => $indukModel->kode, 'nama' => $indukModel->nama];
        } elseif ($level === 3 && $indukModel) {
            $provinsi = $indukModel->kode_induk
                ? Wilayah::where('kode', $indukModel->kode_induk)->first(['kode', 'nama'])
                : null;
            if ($provinsi) {
                $crumbs[] = ['level' => 2, 'kode' => $provinsi->kode, 'nama' => $provinsi->nama];
            }
            $crumbs[] = ['level' => 3, 'kode' => $indukModel->kode, 'nama' => $indukModel->nama];
        }

        return $crumbs;
    }

    private function urlGeoJson(int $level, ?string $kodeInduk): ?string
    {
        return match ($level) {
            1 => '/geojson/provinsi.json',
            2 => $kodeInduk ? "/geojson/kabupaten/{$kodeInduk}.json" : null,
            3 => $kodeInduk ? "/geojson/kecamatan/{$kodeInduk}.json" : null,
            default => null,
        };
    }
}
