<?php

namespace App\Http\Requests;

class GudangRequest extends LokasiMasterRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'instansi_id' => ['required', 'integer', 'exists:instansi,id'],
            'kode_wilayah' => ['required', 'string', 'max:20', 'exists:wilayah,kode'],
            'aktif' => ['required', 'boolean'],
        ]);
    }
}
