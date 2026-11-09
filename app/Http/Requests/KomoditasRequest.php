<?php

namespace App\Http\Requests;

use App\Models\Komoditas;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class KomoditasRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Semua rute dilindungi middleware SuperadminAktif.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nama'))) {
            $this->merge(['nama' => Komoditas::rapikanNama($this->input('nama'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nama' => ['bail', 'required', 'string', 'max:150', function (string $attribute, mixed $value, Closure $fail) {
                $id = $this->route('komoditas')?->id;
                if (Komoditas::where('nama_unik', mb_strtolower($value, 'UTF-8'))
                    ->when($id, fn ($query) => $query->where('id', '!=', $id))->exists()) {
                    $fail('Nama komoditas sudah digunakan.');
                }
            }],
            'satuan' => ['required', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama komoditas wajib diisi.',
            'nama.string' => 'Nama komoditas harus berupa teks.',
            'nama.max' => 'Nama komoditas maksimal 150 karakter.',
            'satuan.required' => 'Satuan wajib diisi.',
            'satuan.string' => 'Satuan harus berupa teks.',
            'satuan.max' => 'Satuan maksimal 30 karakter.',
        ];
    }
}
