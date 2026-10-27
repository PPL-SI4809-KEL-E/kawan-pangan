<?php

namespace App\Support;

/**
 * Data contoh wilayah dan instansi untuk formulir registrasi.
 * Hanya dipakai selama tabel `wilayah` dan `instansi` belum dibuat (PBI-08, PBI-09).
 */
class DataContohRegistrasi
{
    private const WILAYAH = [
        '' => [['kode' => '32', 'nama' => 'Jawa Barat'], ['kode' => '33', 'nama' => 'Jawa Tengah']],
        '32' => [['kode' => '32.04', 'nama' => 'Kab. Bandung'], ['kode' => '32.73', 'nama' => 'Kota Bandung']],
        '33' => [['kode' => '33.74', 'nama' => 'Kota Semarang']],
        '32.04' => [['kode' => '32.04.12', 'nama' => 'Dayeuhkolot'], ['kode' => '32.04.13', 'nama' => 'Bojongsoang']],
        '32.73' => [['kode' => '32.73.01', 'nama' => 'Sukasari']],
        '33.74' => [['kode' => '33.74.01', 'nama' => 'Semarang Tengah']],
        '32.04.12' => [['kode' => '32.04.12.2001', 'nama' => 'Citeureup'], ['kode' => '32.04.12.2002', 'nama' => 'Sukapura']],
        '32.04.13' => [['kode' => '32.04.13.2001', 'nama' => 'Lengkong']],
        '32.73.01' => [['kode' => '32.73.01.1001', 'nama' => 'Gegerkalong']],
        '33.74.01' => [['kode' => '33.74.01.1001', 'nama' => 'Pekunden']],
    ];

    private const INSTANSI = [
        ['id' => 1, 'nama' => 'Perum Bulog Kanwil Jawa Barat'],
        ['id' => 2, 'nama' => 'Dinas Ketahanan Pangan Kab. Bandung'],
        ['id' => 3, 'nama' => 'Badan Pangan Nasional'],
        ['id' => 4, 'nama' => 'ID FOOD'],
    ];

    /**
     * @return list<array{kode: string, nama: string}>
     */
    public static function wilayah(?string $induk): array
    {
        return self::WILAYAH[$induk ?? ''] ?? [];
    }

    /**
     * @return list<array{id: int, nama: string}>
     */
    public static function instansi(): array
    {
        return self::INSTANSI;
    }
}
