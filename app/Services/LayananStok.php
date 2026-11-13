<?php

namespace App\Services;

use App\Models\Gudang;
use App\Models\Komoditas;
use App\Models\MutasiStok;
use App\Models\Stok;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LayananStok
{
    public const FORMAT_WAKTU_MILIDETIK = 'Y-m-d H:i:s.v';

    /**
     * Daftar jenis mutasi stok yang valid sesuai skema ERD.
     *
     * @var list<string>
     */
    public const JENIS_MUTASI_VALID = [
        MutasiStok::JENIS_PENERIMAAN,
        MutasiStok::JENIS_PENYALURAN,
        MutasiStok::JENIS_RETUR,
        MutasiStok::JENIS_RUSAK,
        MutasiStok::JENIS_PENYESUAIAN,
    ];

    /**
     * Ambil baris stok untuk pasangan gudang dan komoditas, atau buat baru dengan saldo 0.000.
     */
    public function ambilAtauBuatStok(Gudang|int $gudang, Komoditas|int $komoditas): Stok
    {
        $gudangId = $gudang instanceof Gudang ? $gudang->id : $gudang;
        $komoditasId = $komoditas instanceof Komoditas ? $komoditas->id : $komoditas;

        return Stok::firstOrCreate(
            [
                'gudang_id' => $gudangId,
                'komoditas_id' => $komoditasId,
            ],
            [
                'jumlah_fisik_layak' => '0.000',
                'is_contoh' => false,
            ]
        );
    }

    /**
     * Catat mutasi penerimaan (barang masuk menambah saldo fisik layak).
     */
    public function catatPenerimaan(
        Stok|int $stok,
        float|int|string $jumlah,
        User|int $pencatat,
        ?string $refOperasi = null,
        ?string $keterangan = null,
        ?int $pengirimanId = null,
        DateTimeInterface|string|null $waktu = null,
    ): MutasiStok {
        return $this->catatMutasi(
            stok: $stok,
            jenis: MutasiStok::JENIS_PENERIMAAN,
            jumlah: $jumlah,
            pencatat: $pencatat,
            refOperasi: $refOperasi,
            keterangan: $keterangan,
            pengirimanId: $pengirimanId,
            waktu: $waktu,
        );
    }

    /**
     * Cari mutasi stok berdasarkan ref_operasi.
     */
    public function cariBerdasarkanRef(string $refOperasi): ?MutasiStok
    {
        $ref = trim($refOperasi);
        if ($ref === '') {
            return null;
        }

        return MutasiStok::where('ref_operasi', $ref)->first();
    }

    /**
     * Periksa apakah ref_operasi sudah pernah dicatat di sistem.
     */
    public function refSudahAda(string $refOperasi): bool
    {
        $ref = trim($refOperasi);
        if ($ref === '') {
            return false;
        }

        return MutasiStok::where('ref_operasi', $ref)->exists();
    }

    /**
     * Catat mutasi penyaluran (barang keluar mengurangi saldo fisik layak).
     */
    public function catatPenyaluran(
        Stok|int $stok,
        float|int|string $jumlah,
        User|int $pencatat,
        ?string $refOperasi = null,
        ?string $keterangan = null,
        ?int $pengirimanId = null,
        DateTimeInterface|string|null $waktu = null,
    ): MutasiStok {
        return $this->catatMutasi(
            stok: $stok,
            jenis: MutasiStok::JENIS_PENYALURAN,
            jumlah: $jumlah,
            pencatat: $pencatat,
            refOperasi: $refOperasi,
            keterangan: $keterangan,
            pengirimanId: $pengirimanId,
            waktu: $waktu,
        );
    }

    /**
     * Catat mutasi retur (barang kembali yang layak menambah saldo fisik layak).
     */
    public function catatRetur(
        Stok|int $stok,
        float|int|string $jumlah,
        User|int $pencatat,
        ?string $refOperasi = null,
        ?string $keterangan = null,
        ?int $pengirimanId = null,
        DateTimeInterface|string|null $waktu = null,
    ): MutasiStok {
        return $this->catatMutasi(
            stok: $stok,
            jenis: MutasiStok::JENIS_RETUR,
            jumlah: $jumlah,
            pencatat: $pencatat,
            refOperasi: $refOperasi,
            keterangan: $keterangan,
            pengirimanId: $pengirimanId,
            waktu: $waktu,
        );
    }

    /**
     * Catat mutasi rusak (barang rusak dikeluarkan sehingga mengurangi saldo fisik layak).
     */
    public function catatRusak(
        Stok|int $stok,
        float|int|string $jumlah,
        User|int $pencatat,
        ?string $refOperasi = null,
        ?string $keterangan = null,
        DateTimeInterface|string|null $waktu = null,
    ): MutasiStok {
        return $this->catatMutasi(
            stok: $stok,
            jenis: MutasiStok::JENIS_RUSAK,
            jumlah: $jumlah,
            pencatat: $pencatat,
            refOperasi: $refOperasi,
            keterangan: $keterangan,
            pengirimanId: null,
            waktu: $waktu,
        );
    }

    /**
     * Catat mutasi penyesuaian berdasarkan selisih perubahan saldo (positif menambah, negatif mengurangi).
     */
    public function catatPenyesuaian(
        Stok|int $stok,
        float|int|string $selisihJumlah,
        User|int $pencatat,
        ?string $refOperasi = null,
        ?string $keterangan = null,
        DateTimeInterface|string|null $waktu = null,
    ): MutasiStok {
        return $this->catatMutasi(
            stok: $stok,
            jenis: MutasiStok::JENIS_PENYESUAIAN,
            jumlah: $selisihJumlah,
            pencatat: $pencatat,
            refOperasi: $refOperasi,
            keterangan: $keterangan,
            pengirimanId: null,
            waktu: $waktu,
        );
    }

    /**
     * Sesuaikan saldo fisik layak ke angka target baru dan hasilkan tepat satu baris mutasi PENYESUAIAN.
     */
    public function sesuaikanSaldo(
        Stok|int $stok,
        float|int|string $saldoFisikBaru,
        User|int $pencatat,
        ?string $refOperasi = null,
        ?string $keterangan = null,
        DateTimeInterface|string|null $waktu = null,
    ): MutasiStok {
        $targetFormatted = $this->formatKuantitas($saldoFisikBaru, 'saldo_setelah');
        $stokId = $stok instanceof Stok ? $stok->id : $stok;
        $pencatatId = $pencatat instanceof User ? $pencatat->id : $pencatat;

        return DB::transaction(function () use (
            $stokId,
            $targetFormatted,
            $pencatatId,
            $refOperasi,
            $keterangan,
            $waktu
        ): MutasiStok {
            $stokTerkunci = Stok::whereKey($stokId)->lockForUpdate()->firstOrFail();
            $saldoAwal = $this->formatKuantitas($stokTerkunci->jumlah_fisik_layak, 'jumlah_fisik_layak');
            $selisih = bcsub($targetFormatted, $saldoAwal, 3);

            if (bccomp($selisih, '0.000', 3) === 0) {
                throw ValidationException::withMessages([
                    'jumlah' => ['Perubahan saldo tidak boleh nol.'],
                ]);
            }

            $jumlahAbsolut = str_starts_with($selisih, '-') ? substr($selisih, 1) : $selisih;

            return $this->simpanPerubahanSaldo(
                stokTerkunci: $stokTerkunci,
                jenis: MutasiStok::JENIS_PENYESUAIAN,
                jumlahMutasi: $jumlahAbsolut,
                saldoSetelah: $targetFormatted,
                pencatatId: $pencatatId,
                refOperasi: $refOperasi,
                keterangan: $keterangan,
                pengirimanId: null,
                waktu: $waktu,
            );
        });
    }

    /**
     * Catat perubahan saldo stok dan hasilkan tepat satu baris mutasi dengan waktu presisi milidetik.
     */
    public function catatMutasi(
        Stok|int $stok,
        string $jenis,
        float|int|string $jumlah,
        User|int $pencatat,
        ?string $refOperasi = null,
        ?string $keterangan = null,
        ?int $pengirimanId = null,
        DateTimeInterface|string|null $waktu = null,
    ): MutasiStok {
        if (! in_array($jenis, self::JENIS_MUTASI_VALID, true)) {
            throw ValidationException::withMessages([
                'jenis' => ['Jenis mutasi stok tidak valid.'],
            ]);
        }

        $jumlahFormatted = $this->formatKuantitas($jumlah, 'jumlah');
        if (bccomp($jumlahFormatted, '0.000', 3) === 0) {
            throw ValidationException::withMessages([
                'jumlah' => ['Jumlah mutasi tidak boleh nol.'],
            ]);
        }

        if ($jenis !== MutasiStok::JENIS_PENYESUAIAN && bccomp($jumlahFormatted, '0.000', 3) < 0) {
            throw ValidationException::withMessages([
                'jumlah' => ['Jumlah mutasi harus lebih besar dari nol.'],
            ]);
        }

        $stokId = $stok instanceof Stok ? $stok->id : $stok;
        $pencatatId = $pencatat instanceof User ? $pencatat->id : $pencatat;

        return DB::transaction(function () use (
            $stokId,
            $jenis,
            $jumlahFormatted,
            $pencatatId,
            $refOperasi,
            $keterangan,
            $pengirimanId,
            $waktu
        ): MutasiStok {
            $stokTerkunci = Stok::whereKey($stokId)->lockForUpdate()->firstOrFail();
            $saldoAwal = $this->formatKuantitas($stokTerkunci->jumlah_fisik_layak, 'jumlah_fisik_layak');

            $saldoSetelah = match ($jenis) {
                MutasiStok::JENIS_PENERIMAAN,
                MutasiStok::JENIS_RETUR => bcadd($saldoAwal, $jumlahFormatted, 3),
                MutasiStok::JENIS_PENYALURAN,
                MutasiStok::JENIS_RUSAK => bcsub($saldoAwal, $jumlahFormatted, 3),
                MutasiStok::JENIS_PENYESUAIAN => bcadd($saldoAwal, $jumlahFormatted, 3),
            };

            $jumlahTersimpan = str_starts_with($jumlahFormatted, '-')
                ? substr($jumlahFormatted, 1)
                : $jumlahFormatted;

            return $this->simpanPerubahanSaldo(
                stokTerkunci: $stokTerkunci,
                jenis: $jenis,
                jumlahMutasi: $jumlahTersimpan,
                saldoSetelah: $saldoSetelah,
                pencatatId: $pencatatId,
                refOperasi: $refOperasi,
                keterangan: $keterangan,
                pengirimanId: $pengirimanId,
                waktu: $waktu,
            );
        });
    }

    /**
     * Simpan perubahan saldo pada baris stok yang telah dikunci dan buat tepat satu baris mutasi.
     */
    private function simpanPerubahanSaldo(
        Stok $stokTerkunci,
        string $jenis,
        string $jumlahMutasi,
        string $saldoSetelah,
        int $pencatatId,
        ?string $refOperasi,
        ?string $keterangan,
        ?int $pengirimanId,
        DateTimeInterface|string|null $waktu,
    ): MutasiStok {
        $waktuMutasi = $this->tentukanWaktuMilidetik($stokTerkunci->id, $waktu);
        $refFinal = $this->siapkanRefOperasi($refOperasi, $waktuMutasi);

        $stokTerkunci->jumlah_fisik_layak = $saldoSetelah;
        $stokTerkunci->diperbarui_pada = $waktuMutasi;
        if ($stokTerkunci->mulai_pencatatan_lengkap === null) {
            $stokTerkunci->mulai_pencatatan_lengkap = $waktuMutasi->toDateString();
        }
        $stokTerkunci->save();

        return MutasiStok::create([
            'stok_id' => $stokTerkunci->id,
            'jenis' => $jenis,
            'jumlah' => $jumlahMutasi,
            'saldo_setelah' => $saldoSetelah,
            'waktu' => $waktuMutasi->format(self::FORMAT_WAKTU_MILIDETIK),
            'ref_operasi' => $refFinal,
            'pengiriman_id' => $pengirimanId,
            'pencatat_id' => $pencatatId,
            'keterangan' => $keterangan,
            'is_contoh' => (bool) $stokTerkunci->is_contoh,
        ]);
    }

    /**
     * Tentukan waktu mutasi dengan presisi sampai milidetik (Y-m-d H:i:s.v).
     */
    private function tentukanWaktuMilidetik(int $stokId, DateTimeInterface|string|null $waktu): Carbon
    {
        if ($waktu instanceof DateTimeInterface) {
            return Carbon::instance($waktu);
        }

        if (is_string($waktu) && trim($waktu) !== '') {
            return Carbon::parse($waktu);
        }

        $sekarang = Carbon::now();

        if (! Carbon::hasTestNow()) {
            $waktuTerakhirRaw = DB::table('mutasi_stok')
                ->where('stok_id', $stokId)
                ->orderByDesc('waktu')
                ->orderByDesc('id')
                ->value('waktu');

            if (is_string($waktuTerakhirRaw) && $waktuTerakhirRaw !== '') {
                $waktuTerakhir = Carbon::parse($waktuTerakhirRaw);
                if ($sekarang->format(self::FORMAT_WAKTU_MILIDETIK) <= $waktuTerakhir->format(self::FORMAT_WAKTU_MILIDETIK)) {
                    $sekarang = $waktuTerakhir->copy()->addMillisecond();
                }
            }
        }

        return $sekarang;
    }

    /**
     * Validasi format dan keunikan ref_operasi sebelum disimpan.
     */
    private function siapkanRefOperasi(?string $refOperasi, Carbon $waktuMutasi): string
    {
        $ref = $refOperasi !== null ? trim($refOperasi) : '';

        if ($ref === '') {
            return sprintf(
                'MUT-%s-%s',
                $waktuMutasi->format('YmdHisv'),
                Str::upper(Str::random(8))
            );
        }

        if (mb_strlen($ref) > 191) {
            throw ValidationException::withMessages([
                'ref_operasi' => ['Referensi operasi maksimal 191 karakter.'],
            ]);
        }

        if ($this->refSudahAda($ref)) {
            throw ValidationException::withMessages([
                'ref_operasi' => ['Referensi operasi sudah pernah dicatat.'],
            ]);
        }

        return $ref;
    }

    /**
     * Normalisasi input numerik menjadi string desimal 3 angka di belakang koma.
     */
    private function formatKuantitas(float|int|string $nilai, string $namaField): string
    {
        if (is_float($nilai) && (is_nan($nilai) || is_infinite($nilai))) {
            throw ValidationException::withMessages([
                $namaField => ['Nilai kuantitas tidak valid.'],
            ]);
        }

        $str = is_string($nilai) ? trim($nilai) : (string) $nilai;
        if ($str === '' || ! is_numeric($str)) {
            throw ValidationException::withMessages([
                $namaField => ['Nilai kuantitas harus berupa angka.'],
            ]);
        }

        return number_format((float) $str, 3, '.', '');
    }
}
