<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class MutasiStok extends Model
{
    public const JENIS_PENERIMAAN = 'PENERIMAAN';

    public const JENIS_PENYALURAN = 'PENYALURAN';

    public const JENIS_RETUR = 'RETUR';

    public const JENIS_RUSAK = 'RUSAK';

    public const JENIS_PENYESUAIAN = 'PENYESUAIAN';

    protected $table = 'mutasi_stok';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'stok_id',
        'jenis',
        'jumlah',
        'saldo_setelah',
        'waktu',
        'ref_operasi',
        'pengiriman_id',
        'pencatat_id',
        'keterangan',
        'is_contoh',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:3',
            'saldo_setelah' => 'decimal:3',
            'waktu' => 'datetime',
            'is_contoh' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $mutasi) {
            Validator::make($mutasi->getAttributes(), [
                'stok_id' => ['required', 'integer'],
                'jenis' => ['required', 'string'],
                'jumlah' => ['required', 'numeric'],
                'saldo_setelah' => ['required', 'numeric', 'min:0'],
                'ref_operasi' => ['required', 'string', 'max:191'],
                'pencatat_id' => ['required', 'integer'],
            ])->validate();
        });
    }

    public function stok(): BelongsTo
    {
        return $this->belongsTo(Stok::class, 'stok_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }
}

