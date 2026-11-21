<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PSKE-36 · Validasi penolakan pendaftaran akun wajib menyertakan alasan.
 */
class TolakAkunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alasan_penolakan' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi agar pendaftar mengetahui penyebab penolakan.',
            'alasan_penolakan.min' => 'Alasan penolakan minimal 5 karakter.',
            'alasan_penolakan.max' => 'Alasan penolakan maksimal 500 karakter.',
        ];
    }
}
