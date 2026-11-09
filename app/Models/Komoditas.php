<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Komoditas extends Model
{
    protected $table = 'komoditas';

    protected $fillable = ['nama', 'satuan'];

    protected $hidden = ['nama_unik'];

    public static function rapikanNama(string $nama): string
    {
        return trim(preg_replace('/\s+/u', ' ', $nama));
    }

    protected static function booted(): void
    {
        static::saving(function (Komoditas $komoditas) {
            $komoditas->nama = self::rapikanNama($komoditas->nama);
            $komoditas->nama_unik = mb_strtolower($komoditas->nama, 'UTF-8');
            $komoditas->satuan = trim($komoditas->satuan);
        });
    }
}
