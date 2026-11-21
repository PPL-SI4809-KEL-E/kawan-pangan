<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CakupanGudang extends Model
{
    protected $table = 'cakupan_gudang';

    protected $fillable = [
        'gudang_id',
        'kode_kecamatan',
        'is_utama',
    ];

    protected function casts(): array
    {
        return [
            'is_utama' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $cakupan) {
            Validator::make($cakupan->getAttributes(), [
                'gudang_id' => ['required', 'integer', 'exists:gudang,id'],
                'kode_kecamatan' => ['required', 'string', 'exists:wilayah,kode'],
                'is_utama' => ['nullable', 'boolean'],
            ])->validate();

            // Validasi level wilayah harus kecamatan (level 3)
            $wilayah = Wilayah::where('kode', $cakupan->kode_kecamatan)->first();
            if ($wilayah && (int) $wilayah->level !== 3) {
                throw ValidationException::withMessages([
                    'kode_kecamatan' => 'Wilayah cakupan wajib berupa tingkat kecamatan (level 3).',
                ]);
            }

            // PSKE-49: Validasi pasangan unik (gudang_id, kode_kecamatan)
            $queryDuplikat = self::where('gudang_id', $cakupan->gudang_id)
                ->where('kode_kecamatan', $cakupan->kode_kecamatan);

            if ($cakupan->exists) {
                $queryDuplikat->where('id', '!=', $cakupan->id);
            }

            if ($queryDuplikat->exists()) {
                throw ValidationException::withMessages([
                    'kode_kecamatan' => 'Kecamatan ini sudah terdaftar dalam cakupan gudang.',
                ]);
            }

            // PSKE-50: Hanya satu gudang utama per kecamatan; pelanggaran ditolak dengan pesan jelas
            if ($cakupan->is_utama) {
                $queryUtama = self::where('kode_kecamatan', $cakupan->kode_kecamatan)
                    ->where('is_utama', true)
                    ->where('gudang_id', '!=', $cakupan->gudang_id);

                if ($cakupan->exists) {
                    $queryUtama->where('id', '!=', $cakupan->id);
                }

                $gudangUtamaLain = $queryUtama->with('gudang')->first();
                if ($gudangUtamaLain) {
                    $namaGudang = $gudangUtamaLain->gudang?->nama ?? 'Gudang #' . $gudangUtamaLain->gudang_id;
                    $namaKecamatan = $wilayah?->nama ?? $cakupan->kode_kecamatan;
                    throw ValidationException::withMessages([
                        'is_utama' => "Kecamatan {$namaKecamatan} sudah memiliki gudang utama: {$namaGudang}. Hapus atau alihkan status utama terlebih dahulu.",
                    ]);
                }
            }
        });
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'kode_kecamatan', 'kode');
    }
}
