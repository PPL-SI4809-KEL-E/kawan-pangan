<?php

namespace App\Enums;

/**
 * Status akun (PSKE-6). Akun hasil registrasi selalu MENUNGGU sampai
 * diverifikasi Superadmin (PBI-06). Hanya AKTIF yang boleh login.
 */
enum StatusAkun: string
{
    case Menunggu = 'MENUNGGU';
    case Aktif = 'AKTIF';
    case Ditolak = 'DITOLAK';
    case Nonaktif = 'NONAKTIF';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu verifikasi',
            self::Aktif => 'Aktif',
            self::Ditolak => 'Ditolak',
            self::Nonaktif => 'Nonaktif',
        };
    }

    /** Kelas badge design system untuk status ini. */
    public function badge(): string
    {
        return match ($this) {
            self::Menunggu => 'badge-waspada',
            self::Aktif => 'badge-aman',
            self::Ditolak => 'badge-kritis',
            self::Nonaktif => 'badge-netral',
        };
    }
}
