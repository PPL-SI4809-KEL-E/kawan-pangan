<?php

namespace App\Services;

use App\Models\Wilayah;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImporWilayah
{
    public const MAKS_BARIS = 100000;

    /** @return array{baru: int, diperbarui: int} */
    public function impor(UploadedFile $berkas): array
    {
        $stream = fopen($berkas->getRealPath(), 'rb');
        if ($stream === false) {
            $this->gagal('Berkas tidak dapat dibaca.');
        }

        try {
            $barisHeader = fgets($stream);
            if ($barisHeader === false) {
                $this->gagal('Berkas kosong.');
            }
            $barisHeader = preg_replace('/\A\xEF\xBB\xBF/', '', $barisHeader);
            $pemisah = substr_count($barisHeader, ';') > substr_count($barisHeader, ',') ? ';' : ',';
            $header = array_map('trim', str_getcsv($barisHeader, $pemisah, '"', ''));
            $wajib = ['kode', 'nama', 'kode_induk'];
            if (count(array_unique($header)) !== count($header)
                || array_diff($wajib, $header)
                || array_diff($header, [...$wajib, 'jumlah_penduduk'])) {
                $this->gagal('Header harus kode,nama,kode_induk dengan kolom opsional jumlah_penduduk. Gunakan template CSV.');
            }

            $data = [];
            $galat = [];
            $nomor = 1;
            while (($kolom = fgetcsv($stream, null, $pemisah, '"', '')) !== false) {
                $nomor++;
                if ($kolom === [null]) {
                    continue;
                }
                if (count($data) >= self::MAKS_BARIS) {
                    $this->gagal('Maksimal '.self::MAKS_BARIS.' wilayah per berkas.');
                }
                if (count($kolom) !== count($header)) {
                    $galat[] = "Baris {$nomor}: jumlah kolom tidak sesuai header.";
                } else {
                    $row = array_combine($header, array_map('trim', $kolom));
                    $level = Wilayah::levelDariKode($row['kode']);
                    $penduduk = $row['jumlah_penduduk'] ?? '';
                    $induk = $row['kode_induk'] === '' ? null : $row['kode_induk'];
                    if ($level === null) {
                        $galat[] = "Baris {$nomor}: kode harus PP, PP.KK, PP.KK.CC, atau PP.KK.CC.DDDD.";
                    } elseif (isset($data[$row['kode']])) {
                        $galat[] = "Baris {$nomor}: kode {$row['kode']} duplikat dalam berkas.";
                    } elseif (! mb_check_encoding($row['nama'], 'UTF-8') || mb_strlen($row['nama']) > 150 || $row['nama'] === '' || preg_match('/[\x00-\x1F\x7F]/', $row['nama'])) {
                        $galat[] = "Baris {$nomor}: nama wajib berupa teks UTF-8, maksimal 150 karakter tanpa karakter kontrol.";
                    } elseif ($induk !== Wilayah::indukDariKode($row['kode'])) {
                        $galat[] = "Baris {$nomor}: kode induk tidak sesuai hierarki; hanya provinsi yang tidak memiliki induk.";
                    } elseif ($penduduk !== '' && (! preg_match('/\A[0-9]+\z/', $penduduk) || (float) $penduduk > Wilayah::MAKS_PENDUDUK)) {
                        $galat[] = "Baris {$nomor}: jumlah penduduk harus bilangan bulat 0–".Wilayah::MAKS_PENDUDUK.'.';
                    } else {
                        $data[$row['kode']] = [
                            'kode' => $row['kode'], 'nama' => $row['nama'], 'level' => $level,
                            'kode_induk' => $induk,
                            'jumlah_penduduk' => $penduduk === '' ? null : (int) $penduduk,
                        ];
                    }
                }
                if (count($galat) >= 20) {
                    $this->gagal($galat);
                }
            }
            if ($galat) {
                $this->gagal($galat);
            }
            if ($data === []) {
                $this->gagal('Berkas harus berisi minimal satu wilayah.');
            }
        } finally {
            fclose($stream);
        }

        return DB::transaction(function () use ($data) {
            $tersimpan = Wilayah::pluck('level', 'kode')->all();
            $galat = [];
            foreach ($data as $row) {
                $induk = $row['kode_induk'];
                if ($induk !== null && ($data[$induk]['level'] ?? $tersimpan[$induk] ?? null) !== $row['level'] - 1) {
                    $galat[] = "Wilayah {$row['kode']}: induk {$induk} belum tersedia dalam berkas atau master wilayah.";
                }
                if (count($galat) >= 20) {
                    break;
                }
            }
            if ($galat) {
                $this->gagal($galat);
            }

            $baru = count(array_diff_key($data, $tersimpan));
            $waktu = now();
            // Induk disimpan lebih dahulu meskipun urutan CSV acak. Upsert per batch
            // menjaga impor nasional tetap praktis dan mempertahankan relasi pengguna.
            for ($level = 1; $level <= 4; $level++) {
                foreach ([false, true] as $denganPenduduk) {
                    $batch = [];
                    $kolomUpdate = ['nama', 'updated_at'];
                    if ($denganPenduduk) {
                        $kolomUpdate[] = 'jumlah_penduduk';
                    }
                    foreach ($data as $row) {
                        if ($row['level'] !== $level || ($row['jumlah_penduduk'] !== null) !== $denganPenduduk) {
                            continue;
                        }
                        $batch[] = [...$row, 'created_at' => $waktu, 'updated_at' => $waktu];
                        if (count($batch) === 100) {
                            Wilayah::upsert($batch, ['kode'], $kolomUpdate);
                            $batch = [];
                        }
                    }
                    if ($batch) {
                        Wilayah::upsert($batch, ['kode'], $kolomUpdate);
                    }
                }
            }

            return ['baru' => $baru, 'diperbarui' => count($data) - $baru];
        });
    }

    private function gagal(string|array $pesan): never
    {
        throw ValidationException::withMessages(['berkas' => (array) $pesan]);
    }
}
