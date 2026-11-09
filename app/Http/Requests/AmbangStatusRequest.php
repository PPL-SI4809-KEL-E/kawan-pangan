<?php

namespace App\Http\Requests;

use App\Models\AmbangStatus;
use Illuminate\Foundation\Http\FormRequest;

class AmbangStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Sama seperti seluruh master lain: middleware SuperadminAktif pada rute.
        return true;
    }

    public function rules(): array
    {
        return AmbangStatus::aturan();
    }

    public function messages(): array
    {
        return [
            'kritis_hari.required' => 'Ambang kritis wajib diisi.',
            'kritis_hari.integer' => 'Ambang kritis harus berupa bilangan bulat positif.',
            'kritis_hari.min' => 'Ambang kritis minimal 1 hari.',
            'kritis_hari.max' => 'Ambang kritis maksimal 65535 hari.',
            'aman_hari.required' => 'Ambang aman wajib diisi.',
            'aman_hari.integer' => 'Ambang aman harus berupa bilangan bulat positif.',
            'aman_hari.min' => 'Ambang aman minimal 1 hari.',
            'aman_hari.max' => 'Ambang aman maksimal 65535 hari.',
            'aman_hari.gt' => 'Ambang aman harus lebih besar daripada ambang kritis.',
        ];
    }
}
