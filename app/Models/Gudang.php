<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gudang extends Model
{
    protected $table = 'gudang';

    protected $fillable = ['nama', 'instansi_id', 'kode_wilayah', 'alamat', 'latitude', 'longitude', 'aktif'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'aktif' => 'boolean'];
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'kode_wilayah', 'kode');
    }

    public function stok(): HasMany
    {
        return $this->hasMany(Stok::class, 'gudang_id');
    }

    public function pengiriman(): HasMany
    {
        return $this->hasMany(Pengiriman::class, 'gudang_asal_id');
    }
}
