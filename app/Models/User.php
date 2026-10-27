<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Enums\StatusAkun;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'no_hp',
        'kode_wilayah',
        'instansi_id',
        // "status" sengaja tidak fillable: akun baru selalu MENUNGGU dan hanya
        // Superadmin yang mengubahnya saat verifikasi (PBI-06).
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'status' => StatusAkun::class,
            'diverifikasi_pada' => 'datetime',
        ];
    }

    /**
     * Dokumen pendukung registrasi, misalnya surat tugas (PSKE-5).
     */
    public function dokumenPendukung(): HasMany
    {
        return $this->hasMany(DokumenPendukung::class);
    }

    /**
     * PSKE-6: hanya akun AKTIF yang boleh login.
     */
    public function bolehLogin(): bool
    {
        return $this->status === StatusAkun::Aktif;
    }

    /**
     * Pesan untuk halaman login bila akun belum atau tidak boleh masuk (dipakai PBI-04).
     */
    public function pesanLoginDitolak(): ?string
    {
        return match ($this->status) {
            StatusAkun::Aktif => null,
            StatusAkun::Menunggu => 'Akun Anda masih menunggu verifikasi Superadmin. Anda belum dapat masuk.',
            StatusAkun::Ditolak => 'Pendaftaran Anda ditolak. Alasan: '.($this->alasan_penolakan ?: '-'),
            StatusAkun::Nonaktif => 'Akun Anda telah dinonaktifkan.',
            default => 'Status akun tidak valid.',
        };
    }
}
