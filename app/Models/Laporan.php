<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Laporan extends Model
{
    public const STATUS_DRAF = 'DRAF';

    public const STATUS_DIAJUKAN = 'DIAJUKAN';

    public const STATUS_DIVERIFIKASI = 'DIVERIFIKASI';

    public const STATUS_DITOLAK = 'DITOLAK';

    public const STATUS_SELESAI = 'SELESAI';

    protected $table = 'laporan';

    protected $fillable = [
        'kode_laporan',
        'pelapor_id',
        'kode_wilayah',
        'jenis',
        'deskripsi',
        'urgensi',
        'status',
        'dibuat_pada',
        'diajukan_pada',
        'verifikator_id',
        'diverifikasi_pada',
        'alasan_penolakan',
        'alasan_penutupan',
        'is_contoh',
    ];

    protected function casts(): array
    {
        return [
            'dibuat_pada' => 'datetime',
            'diajukan_pada' => 'datetime',
            'diverifikasi_pada' => 'datetime',
            'is_contoh' => 'boolean',
        ];
    }

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pelapor_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifikator_id');
    }

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'kode_wilayah', 'kode');
    }

    /**
     * Filter laporan sesuai cakupan wilayah pengguna (hierarki prefix).
     */
    public function scopeUntukPengguna(Builder $query, User $user): Builder
    {
        if ($user->isSuperadmin() || ($user->isBadanResmi() && $user->instansi_id !== null)) {
            return $query;
        }

        $kode = trim((string) $user->kode_wilayah);
        if (! $user->isKepalaWilayah() || $kode === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $sub) use ($kode) {
            $sub->where('kode_wilayah', $kode)
                ->orWhere('kode_wilayah', 'like', $kode.'.%');
        });
    }
}
