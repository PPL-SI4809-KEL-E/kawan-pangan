<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Wilayah extends Model
{
    public const LEVEL = [1 => 'Provinsi', 2 => 'Kabupaten/Kota', 3 => 'Kecamatan', 4 => 'Desa/Kelurahan'];

    public const MAKS_PENDUDUK = 2147483647;

    protected $table = 'wilayah';

    protected $primaryKey = 'kode';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['kode', 'nama', 'kode_induk', 'jumlah_penduduk'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'jumlah_penduduk' => 'integer', 'is_contoh' => 'boolean'];
    }

    public static function levelDariKode(string $kode): ?int
    {
        return preg_match('/\A[0-9]{2}(?:\.[0-9]{2}){0,2}(?:\.[0-9]{4})?\z/', $kode)
            && in_array(strlen($kode), [2, 5, 8, 13], true)
            ? substr_count($kode, '.') + 1 : null;
    }

    public static function indukDariKode(string $kode): ?string
    {
        $posisi = strrpos($kode, '.');

        return $posisi === false ? null : substr($kode, 0, $posisi);
    }

    protected static function booted(): void
    {
        static::saving(function (self $wilayah) {
            $data = $wilayah->getAttributes();
            Validator::make($data, [
                'kode' => ['required', 'string'],
                'nama' => ['required', 'string', 'max:150'],
                'jumlah_penduduk' => ['nullable', 'integer', 'min:0', 'max:'.self::MAKS_PENDUDUK],
            ])->validate();

            $level = self::levelDariKode($wilayah->kode);
            if ($level === null || ($wilayah->exists && $wilayah->isDirty('kode'))) {
                throw ValidationException::withMessages(['kode' => 'Kode wilayah tidak valid atau mencoba mengubah kode yang sudah tersimpan.']);
            }

            $induk = self::indukDariKode($wilayah->kode);
            if ($wilayah->kode_induk !== $induk || ($induk !== null && ! self::whereKey($induk)->where('level', $level - 1)->exists())) {
                throw ValidationException::withMessages(['kode_induk' => 'Induk wajib sesuai kode dan satu level di atas wilayah; provinsi tidak memiliki induk.']);
            }

            $wilayah->level = $level;
        });
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'kode_induk', 'kode');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'kode_induk', 'kode');
    }

    public function cakupanGudang(): HasMany
    {
        return $this->hasMany(CakupanGudang::class, 'kode_kecamatan', 'kode');
    }

    public function gudang(): BelongsToMany
    {
        return $this->belongsToMany(Gudang::class, 'cakupan_gudang', 'kode_kecamatan', 'gudang_id', 'kode', 'id')
            ->withPivot('is_utama')
            ->withTimestamps();
    }

    public function gudangUtama(): BelongsToMany
    {
        return $this->belongsToMany(Gudang::class, 'cakupan_gudang', 'kode_kecamatan', 'gudang_id', 'kode', 'id')
            ->wherePivot('is_utama', true)
            ->withPivot('is_utama')
            ->withTimestamps();
    }
}
