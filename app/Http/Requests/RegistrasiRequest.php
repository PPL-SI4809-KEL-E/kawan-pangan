<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\DokumenPendukung;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * PBI-05 · Validasi formulir registrasi akun.
 */
class RegistrasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rapikan input sebelum divalidasi. Kode wilayah yang dipakai adalah level
     * terdalam yang dipilih: desa/kelurahan, atau kecamatan untuk camat.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'no_hp' => preg_replace('/[\s-]/', '', (string) $this->input('no_hp')),
            'kode_wilayah' => $this->input('wilayah_desa') ?: $this->input('wilayah_kecamatan'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // PSKE-4: hanya Kepala Wilayah dan Badan Resmi. SUPERADMIN selalu ditolak di server.
            'role' => ['required', Rule::in(Role::nilaiBolehDaftar())],
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'no_hp' => ['required', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'kode_wilayah' => ['nullable', 'required_if:role,'.Role::KepalaWilayah->value, 'string', 'max:20'],
            'instansi_id' => ['nullable', 'required_if:role,'.Role::BadanResmi->value, 'integer'],
            // PSKE-5: PDF/JPG maks 2 MB. "mimes" menebak tipe dari isi berkas, bukan dari namanya,
            // sehingga berkas .exe yang diganti nama menjadi .pdf tetap ditolak.
            'surat_tugas' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg',
                'mimetypes:application/pdf,image/jpeg',
                'max:'.DokumenPendukung::MAKS_KB,
            ],
            'setuju' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Pilih jenis akun Kepala Wilayah atau Badan Resmi.',
            'role.in' => 'Jenis akun tidak diizinkan untuk registrasi.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.min' => 'Nama lengkap minimal 3 karakter.',
            'name.max' => 'Nama lengkap maksimal 100 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
            'no_hp.regex' => 'Nomor HP tidak valid, contoh 081234567890.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.letters' => 'Kata sandi harus berisi huruf.',
            'password.numbers' => 'Kata sandi harus berisi angka.',
            'kode_wilayah.required_if' => 'Pilih wilayah tugas sampai kecamatan atau desa/kelurahan.',
            'instansi_id.required_if' => 'Pilih instansi tempat Anda bertugas.',
            'instansi_id.integer' => 'Instansi tidak valid.',
            'surat_tugas.required' => 'Surat tugas wajib diunggah.',
            'surat_tugas.file' => 'Surat tugas gagal diunggah. Silakan pilih ulang berkasnya.',
            'surat_tugas.uploaded' => 'Surat tugas gagal diunggah. Ukuran maksimal 2 MB.',
            'surat_tugas.mimes' => 'Format surat tugas harus PDF atau JPG.',
            'surat_tugas.mimetypes' => 'Format surat tugas harus PDF atau JPG.',
            'surat_tugas.max' => 'Ukuran surat tugas maksimal 2 MB.',
            'setuju.accepted' => 'Anda harus menyatakan data yang diisi benar.',
        ];
    }

    /**
     * Data akun yang aman disimpan. Kepala Wilayah hanya membawa wilayah,
     * Badan Resmi hanya membawa instansi.
     *
     * @return array<string, mixed>
     */
    public function dataAkun(): array
    {
        $role = Role::from($this->validated('role'));

        return [
            'name' => $this->validated('name'),
            'email' => $this->validated('email'),
            'no_hp' => $this->validated('no_hp'),
            'password' => $this->validated('password'),
            'role' => $role,
            'kode_wilayah' => $role === Role::KepalaWilayah ? $this->validated('kode_wilayah') : null,
            'instansi_id' => $role === Role::BadanResmi ? (int) $this->validated('instansi_id') : null,
        ];
    }
}
