<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function cakupan(): HasMany
    {
        return $this->hasMany(CakupanGudang::class, 'gudang_id');
    }

    public function kecamatan(): BelongsToMany
    {
        return $this->belongsToMany(Wilayah::class, 'cakupan_gudang', 'gudang_id', 'kode_kecamatan', 'id', 'kode')
            ->withPivot('is_utama')
            ->withTimestamps();
    }
}
