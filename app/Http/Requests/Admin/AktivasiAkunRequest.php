<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PSKE-35 · Validasi penugasan saat aktivasi akun pendaftar.
 */
class AktivasiAkunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User $targetUser */
        $targetUser = $this->route('user');

        $rules = [];

        if ($targetUser && $targetUser->isKepalaWilayah()) {
            $rules['kode_wilayah'] = [
                'required',
                'string',
                'exists:wilayah,kode',
            ];
        } elseif ($targetUser && $targetUser->isBadanResmi()) {
            $rules['instansi_id'] = [
                'required',
                'integer',
                'exists:instansi,id',
            ];
            $rules['jabatan'] = [
                'required',
                'string',
                Rule::in([User::JABATAN_PENGELOLA, User::JABATAN_PETUGAS]),
            ];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var User $targetUser */
                $targetUser = $this->route('user');

                if ($targetUser && $targetUser->isKepalaWilayah() && $this->filled('kode_wilayah')) {
                    $wilayah = Wilayah::find($this->input('kode_wilayah'));
                    // Level 3 = Kecamatan, Level 4 = Desa/Kelurahan
                    if ($wilayah && ! in_array($wilayah->level, [3, 4], true)) {
                        $validator->errors()->add('kode_wilayah', 'Penugasan Kepala Wilayah harus pada tingkat kecamatan atau desa/kelurahan.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'kode_wilayah.required' => 'Wilayah penugasan wajib dipilih untuk Kepala Wilayah.',
            'kode_wilayah.exists' => 'Kode wilayah tidak ditemukan dalam basis data master.',
            'instansi_id.required' => 'Instansi penugasan wajib dipilih untuk staf Badan Resmi.',
            'instansi_id.exists' => 'Instansi tidak ditemukan dalam basis data master.',
            'jabatan.required' => 'Peran jabatan (Pengelola atau Petugas) wajib dipilih.',
            'jabatan.in' => 'Jabatan harus berupa Pengelola atau Petugas.',
        ];
    }
}
