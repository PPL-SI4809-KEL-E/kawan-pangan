<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;

class Stok extends Model
{
    protected $table = 'stok';

    protected $fillable = [
        'gudang_id',
        'komoditas_id',
        'jumlah_fisik_layak',
        'mulai_pencatatan_lengkap',
        'diperbarui_pada',
        'is_contoh',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_fisik_layak' => 'decimal:3',
            'mulai_pencatatan_lengkap' => 'date',
            'diperbarui_pada' => 'datetime',
            'is_contoh' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $stok) {
            Validator::make($stok->getAttributes(), [
                'gudang_id' => ['required', 'integer'],
                'komoditas_id' => ['required', 'integer'],
                'jumlah_fisik_layak' => ['required', 'numeric', 'min:0'],
            ])->validate();
        });
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function komoditas(): BelongsTo
    {
        return $this->belongsTo(Komoditas::class);
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiStok::class, 'stok_id');
    }

    public function cadangan(): HasMany
    {
        return $this->hasMany(Cadangan::class, 'stok_id');
    }
}

