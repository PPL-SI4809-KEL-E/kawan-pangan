<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class AmbangStatus extends Model
{
    protected $table = 'ambang_status';

    public $incrementing = false;

    protected $fillable = ['kritis_hari', 'aman_hari'];

    protected $attributes = ['id' => 1, 'kritis_hari' => 14, 'aman_hari' => 30];

    protected function casts(): array
    {
        return ['kritis_hari' => 'integer', 'aman_hari' => 'integer'];
    }

    public static function aturan(): array
    {
        return [
            'kritis_hari' => ['bail', 'required', 'integer', 'min:1', 'max:65535'],
            'aman_hari' => ['bail', 'required', 'integer', 'min:1', 'max:65535', 'gt:kritis_hari'],
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AmbangStatus $ambang) {
            // Validasi atribut mentah sebelum casts dapat mengubah pecahan menjadi integer.
            Validator::make($ambang->getAttributes(), array_merge(
                ['id' => ['required', 'integer', 'in:1']],
                self::aturan(),
            ))->validate();
        });
    }

    public static function aktif(): static
    {
        // Tidak mengubah pengaturan dari operasi baca; migrasi menyediakan nilai awal.
        return static::query()->findOrFail(1);
    }

    public function statusUntukHari(float $hari): string
    {
        if (! is_finite($hari)) {
            throw new InvalidArgumentException('Hari ketersediaan harus berupa angka yang valid.');
        }

        // Bandingkan nilai asli, tanpa pembulatan atau pemotongan pecahan.
        if ($hari < $this->kritis_hari) {
            return 'KRITIS';
        }
        if ($hari > $this->aman_hari) {
            return 'AMAN';
        }

        return 'WASPADA';
    }
}
