<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PSKE-5 · Surat tugas atau dokumen lain yang diunggah pendaftar.
 */
class DokumenPendukung extends Model
{
    public const JENIS_SURAT_TUGAS = 'SURAT_TUGAS';

    /** Ukuran maksimal surat tugas dalam kilobyte (2 MB). */
    public const MAKS_KB = 2048;

    protected $table = 'dokumen_pendukung';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'jenis',
        'nama_file',
        'path',
        'mime',
        'ukuran',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
