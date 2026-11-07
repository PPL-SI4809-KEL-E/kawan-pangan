<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class Cadangan extends Model
{
    public const STATUS_AKTIF = 'AKTIF';

    public const STATUS_SELESAI = 'SELESAI';

    public const STATUS_BATAL = 'BATAL';

    protected $table = 'cadangan';

    protected $fillable = [
        'stok_id',
        'pengiriman_id',
        'jumlah',
        'status',
        'dibuat_pada',
        'selesai_pada',
        'is_contoh',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:3',
            'dibuat_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'is_contoh' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $cadangan) {
            Validator::make($cadangan->getAttributes(), [
                'stok_id' => ['required', 'integer'],
                'pengiriman_id' => ['required', 'integer'],
                'jumlah' => ['required', 'numeric', 'min:0'],
                'status' => ['required', 'string'],
            ])->validate();
        });
    }

    public function stok(): BelongsTo
    {
        return $this->belongsTo(Stok::class, 'stok_id');
    }
}

