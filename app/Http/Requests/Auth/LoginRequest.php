<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PSKE-33 · Validasi login dan penguncian akun 15 menit setelah 5 kali gagal.
 */
class LoginRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diizinkan membuat permintaan ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi untuk permintaan login.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Pesan kesalahan khusus untuk validasi.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ];
    }

    /**
     * Otentikasi kredensial pengguna, verifikasi status akun, dan hitung rate limiting.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = Str::lower($this->input('email'));
        $password = (string) $this->input('password');

        $user = User::where('email', $email)->first();

        // Kredensial salah (user tidak ditemukan atau password tidak cocok).
        // Tampilkan pesan umum tanpa membocorkan apakah email terdaftar atau tidak.
        if (! $user || ! Hash::check($password, $user->password)) {
            // Hitung gagal per email + IP. Lockout 15 menit = 900 detik.
            RateLimiter::hit($this->throttleKey(), 900);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        // Kredensial benar tapi akun belum/tidak diizinkan masuk (Menunggu, Ditolak, Nonaktif).
        // Jangan buat sesi dan tampilkan pesan penolakan yang sesuai.
        if (! $user->bolehLogin()) {
            RateLimiter::hit($this->throttleKey(), 900);

            throw ValidationException::withMessages([
                'email' => $user->pesanLoginDitolak() ?? 'Status akun tidak valid.',
            ]);
        }

        // Login berhasil: buat sesi pengguna dan bersihkan batas laju (RateLimiter).
        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Pastikan permintaan login tidak melampaui batas percobaan.
     * Akun dikunci 15 menit setelah 5 kali gagal login berturut-turut.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());
        $minutes = (int) ceil($seconds / 60);

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan masuk yang gagal. Akun dikunci sementara. Silakan coba lagi dalam {$minutes} menit ({$seconds} detik).",
        ]);
    }

    /**
     * Dapatkan throttle key untuk rate limiting berdasarkan email (lowercase) + IP.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }
}
