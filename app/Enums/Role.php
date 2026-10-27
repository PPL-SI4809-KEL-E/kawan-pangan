<?php

namespace App\Enums;

/**
 * Role akun Kawan Pangan (laporan 2.2.1).
 * Superadmin dibuat lewat seeding, tidak bisa dipilih saat registrasi (PSKE-4).
 */
enum Role: string
{
    case Superadmin = 'SUPERADMIN';
    case KepalaWilayah = 'KEPALA_WILAYAH';
    case BadanResmi = 'BADAN_RESMI';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::KepalaWilayah => 'Kepala Wilayah',
            self::BadanResmi => 'Badan Resmi',
        };
    }

    /**
     * Role yang boleh dipilih di formulir registrasi publik.
     *
     * @return list<self>
     */
    public static function bolehDaftar(): array
    {
        return [self::KepalaWilayah, self::BadanResmi];
    }

    /**
     * @return list<string>
     */
    public static function nilaiBolehDaftar(): array
    {
        return array_map(fn (self $r) => $r->value, self::bolehDaftar());
    }
}
