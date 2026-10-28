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

    // ──────────────────────────────────────────────────────────────
    //  Helper otorisasi wilayah, instansi, dan penugasan
    // ──────────────────────────────────────────────────────────────

    public function isSuperadmin(): bool
    {
        return $this->role === Role::Superadmin;
    }

    public function isKepalaWilayah(): bool
    {
        return $this->role === Role::KepalaWilayah;
    }

    public function isBadanResmi(): bool
    {
        return $this->role === Role::BadanResmi;
    }

    /**
     * Periksa apakah kode wilayah tertentu berada dalam cakupan pengguna ini.
     * Menggunakan pencocokan prefix hierarki: kode_wilayah `32.04` mencakup
     * `32.04`, `32.04.12`, `32.04.12.2001`, dan seterusnya.
     *
     * Mengembalikan false jika pengguna tidak memiliki penugasan wilayah.
     */
    public function menguasaiWilayah(string $kodeWilayah): bool
    {
        if ($this->kode_wilayah === null) {
            return false;
        }

        return $kodeWilayah === $this->kode_wilayah
            || str_starts_with($kodeWilayah, $this->kode_wilayah.'.');
    }

    /**
     * Periksa apakah instansi tertentu sesuai dengan penugasan pengguna ini.
     *
     * Mengembalikan false jika pengguna tidak memiliki penugasan instansi.
     */
    public function menguasaiInstansi(int $instansiId): bool
    {
        if ($this->instansi_id === null) {
            return false;
        }

        return (int) $this->instansi_id === $instansiId;
    }
}
