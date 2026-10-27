<?php

namespace Tests\Feature\Registrasi;

use Illuminate\Http\UploadedFile;

/**
 * Data formulir registrasi yang valid, dipakai bersama oleh test PBI-05.
 */
trait DataRegistrasi
{
    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    protected function dataValid(array $ubah = []): array
    {
        return array_merge([
            'role' => 'KEPALA_WILAYAH',
            'name' => 'Budi Santoso',
            'email' => 'budi@desa.id',
            'no_hp' => '081234567890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'wilayah_provinsi' => '32',
            'wilayah_kabkota' => '32.04',
            'wilayah_kecamatan' => '32.04.12',
            'wilayah_desa' => '32.04.12.2001',
            'surat_tugas' => $this->suratTugasPdf(),
            'setuju' => '1',
        ], $ubah);
    }

    /** Berkas PDF asli (diawali %PDF) sebesar $kb kilobyte. */
    protected function suratTugasPdf(int $kb = 300, string $nama = 'surat-tugas.pdf'): UploadedFile
    {
        return $this->berkasDenganIsi("%PDF-1.4\n".str_repeat('0', max(0, $kb * 1024 - 9)), $nama, 'application/pdf');
    }

    /** Berkas JPG asli (diawali penanda JPEG). */
    protected function suratTugasJpg(int $kb = 500, string $nama = 'surat-tugas.jpg'): UploadedFile
    {
        // Penanda awal JPEG/JFIF; cukup untuk deteksi tipe tanpa ekstensi GD.
        $isi = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

        return $this->berkasDenganIsi($isi.str_repeat("\0", max(0, $kb * 1024 - strlen($isi))), $nama, 'image/jpeg');
    }

    protected function berkasDenganIsi(string $isi, string $nama, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'kp');
        file_put_contents($path, $isi);

        return new UploadedFile($path, $nama, $mime, null, true);
    }
}
