<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengiriman extends Model
{
    public const STATUS_DIRENCANAKAN = 'DIRENCANAKAN';

    public const STATUS_DISETUJUI = 'DISETUJUI';

    public const STATUS_DIKIRIM = 'DIKIRIM';

    public const STATUS_TIBA = 'TIBA';

    public const STATUS_SELESAI = 'SELESAI';

    public const STATUS_DIBATALKAN = 'DIBATALKAN';

    protected $table = 'pengiriman';

    protected $fillable = [
        'no_resi',
        'laporan_id',
        'gudang_asal_id',
        'kode_wilayah_tujuan',
        'pembuat_id',
        'penyetuju_id',
        'petugas_id',
        'jadwal_berangkat',
        'estimasi_tiba',
        'status',
        'disetujui_pada',
        'berangkat_pada',
        'alasan_batal',
        'is_contoh',
    ];

    protected function casts(): array
    {
        return [
            'jadwal_berangkat' => 'datetime',
            'estimasi_tiba' => 'datetime',
            'disetujui_pada' => 'datetime',
            'berangkat_pada' => 'datetime',
            'is_contoh' => 'boolean',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(Laporan::class, 'laporan_id');
    }

    public function gudangAsal(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function wilayahTujuan(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'kode_wilayah_tujuan', 'kode');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembuat_id');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyetuju_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    /**
     * Filter pengiriman sesuai hak akses pengguna.
     */
    public function scopeUntukPengguna(Builder $query, User $user): Builder
    {
        if ($user->isSuperadmin()) {
            return $query;
        }

        if ($user->isBadanResmi() && $user->instansi_id !== null) {
            $instansiId = (int) $user->instansi_id;

            return $query->where(function (Builder $sub) use ($user, $instansiId) {
                $sub->whereHas('gudangAsal', fn (Builder $g) => $g->where('instansi_id', $instansiId))
                    ->orWhere('petugas_id', $user->id);
            });
        }

        $kode = trim((string) $user->kode_wilayah);
        if ($user->isKepalaWilayah() && $kode !== '') {
            return $query->where(function (Builder $sub) use ($kode) {
                $sub->where('kode_wilayah_tujuan', $kode)
                    ->orWhere('kode_wilayah_tujuan', 'like', $kode.'.%');
            });
        }

        return $query->whereRaw('1 = 0');
    }
}
