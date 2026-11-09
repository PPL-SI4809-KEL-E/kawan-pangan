<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LokasiMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'alamat' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama wajib diisi.',
            'nama.string' => 'Nama harus berupa teks.',
            'nama.max' => 'Nama maksimal 150 karakter.',
            'alamat.required' => 'Alamat wajib diisi.',
            'alamat.string' => 'Alamat harus berupa teks.',
            'alamat.max' => 'Alamat maksimal 500 karakter.',
            'latitude.required' => 'Latitude wajib diisi.',
            'latitude.numeric' => 'Latitude harus berupa angka desimal dengan titik.',
            'latitude.between' => 'Latitude harus antara -90 dan 90.',
            'longitude.required' => 'Longitude wajib diisi.',
            'longitude.numeric' => 'Longitude harus berupa angka desimal dengan titik.',
            'longitude.between' => 'Longitude harus antara -180 dan 180.',
            'instansi_id.required' => 'Pilih instansi pengelola.',
            'instansi_id.integer' => 'Instansi tidak valid.',
            'instansi_id.exists' => 'Instansi tidak ditemukan. Pilih instansi yang tersedia.',
            'kode_wilayah.required' => 'Kode wilayah wajib diisi.',
            'kode_wilayah.string' => 'Kode wilayah harus berupa teks.',
            'kode_wilayah.max' => 'Kode wilayah maksimal 20 karakter.',
            'kode_wilayah.exists' => 'Kode wilayah tidak ditemukan di master wilayah.',
            'aktif.required' => 'Pilih status gudang.',
            'aktif.boolean' => 'Status gudang tidak valid.',
        ];
    }
}
