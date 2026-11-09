<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instansi extends Model
{
    protected $table = 'instansi';

    protected $fillable = ['nama', 'alamat', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function gudang(): HasMany
    {
        return $this->hasMany(Gudang::class);
    }

    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
