<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\DokumenPendukung;
use App\Models\Instansi;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PBI-06 / PSKE-17 · Pengujian verifikasi akun dan penugasan Superadmin.
 * Mencakup subtask PSKE-35, PSKE-36, dan PSKE-37.
 */
class VerifikasiAkunTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');

        $this->superadmin = User::factory()->superadmin()->create();
    }

    /**
     * Halaman verifikasi akun dilindungi dan hanya boleh diakses oleh Superadmin.
     */
    public function test_halaman_verifikasi_akun_hanya_bisa_diakses_superadmin(): void
    {
        // Tamu dialihkan ke login
        $this->get(route('admin.akun.index'))->assertRedirect(route('login'));

        // Kepala Wilayah & Badan Resmi ditolak (403 Forbidden)
        $kw = User::factory()->kepalaWilayah()->create();
        $this->actingAs($kw)->get(route('admin.akun.index'))->assertForbidden();

        $br = User::factory()->badanResmi()->create();
        $this->actingAs($br)->get(route('admin.akun.index'))->assertForbidden();

        // Superadmin dapat mengakses
        $this->actingAs($this->superadmin)
            ->get(route('admin.akun.index'))
            ->assertOk()
            ->assertSee('Verifikasi & Manajemen Akun', false);
    }

    /**
     * PSKE-35 · Aktivasi Kepala Wilayah wajib disertai penugasan wilayah level kecamatan/desa.
     */
    public function test_aktivasi_kepala_wilayah_wajib_disertai_wilayah_kecamatan_atau_desa(): void
    {
        $provinsi = Wilayah::create(['kode' => '32', 'nama' => 'Jawa Barat', 'jumlah_penduduk' => 50000000]);
        $kabupaten = Wilayah::create(['kode' => '32.04', 'nama' => 'Kab. Bandung', 'kode_induk' => '32', 'jumlah_penduduk' => 3000000]);
        $kecamatan = Wilayah::create(['kode' => '32.04.12', 'nama' => 'Kec. Soreang', 'kode_induk' => '32.04', 'jumlah_penduduk' => 100000]);

        $pendaftarKW = User::factory()->create([
            'role' => Role::KepalaWilayah,
            'status' => StatusAkun::Menunggu,
            'kode_wilayah' => null,
        ]);

        // 1. Aktivasi tanpa memilih kode_wilayah harus ditolak
        $this->actingAs($this->superadmin)
            ->post(route('admin.akun.aktivasi', $pendaftarKW), [])
            ->assertSessionHasErrors('kode_wilayah');

        // 2. Aktivasi dengan wilayah level 1 (Provinsi) harus ditolak
        $this->actingAs($this->superadmin)
            ->post(route('admin.akun.aktivasi', $pendaftarKW), ['kode_wilayah' => $provinsi->kode])
            ->assertSessionHasErrors('kode_wilayah');

        // 3. Aktivasi dengan wilayah level 3 (Kecamatan) harus berhasil
        $response = $this->actingAs($this->superadmin)
            ->post(route('admin.akun.aktivasi', $pendaftarKW), ['kode_wilayah' => $kecamatan->kode]);

        $response->assertRedirect(route('admin.akun.show', $pendaftarKW));

        $pendaftarKW->refresh();
        $this->assertSame(StatusAkun::Aktif, $pendaftarKW->status);
        $this->assertSame($kecamatan->kode, $pendaftarKW->kode_wilayah);
        $this->assertSame($this->superadmin->id, $pendaftarKW->verifikator_id);
        $this->assertNotNull($pendaftarKW->diverifikasi_pada);
        $this->assertTrue($pendaftarKW->bolehLogin());
    }

    /**
     * PSKE-35 · Aktivasi Badan Resmi wajib disertai instansi dan peran jabatan (Pengelola/Petugas).
     */
    public function test_aktivasi_badan_resmi_wajib_disertai_instansi_dan_jabatan(): void
    {
        $instansi = Instansi::create([
            'nama' => 'Perum Bulog Kanwil Jabar',
            'alamat' => 'Jl. Soekarno Hatta Bandung',
            'latitude' => -6.9400000,
            'longitude' => 107.6300000,
        ]);

        $pendaftarBR = User::factory()->create([
            'role' => Role::BadanResmi,
            'status' => StatusAkun::Menunggu,
            'instansi_id' => null,
        ]);

        // 1. Tanpa instansi dan tanpa jabatan ditolak
        $this->actingAs($this->superadmin)
            ->post(route('admin.akun.aktivasi', $pendaftarBR), [])
            ->assertSessionHasErrors(['instansi_id', 'jabatan']);

        // 2. Dengan instansi_id dan jabatan PENGELOLA berhasil
        $response = $this->actingAs($this->superadmin)
            ->post(route('admin.akun.aktivasi', $pendaftarBR), [
                'instansi_id' => $instansi->id,
                'jabatan' => User::JABATAN_PENGELOLA,
            ]);

        $response->assertRedirect(route('admin.akun.show', $pendaftarBR));

        $pendaftarBR->refresh();
        $this->assertSame(StatusAkun::Aktif, $pendaftarBR->status);
        $this->assertSame($instansi->id, $pendaftarBR->instansi_id);
        $this->assertSame(User::JABATAN_PENGELOLA, $pendaftarBR->getAttribute('jabatan'));
        $this->assertTrue($pendaftarBR->isPengelolaInstansi());
        $this->assertTrue($pendaftarBR->bolehLogin());
    }

    /**
     * PSKE-36 · Penolakan akun wajib ada alasan dan alasannya tampil ke pendaftar saat login.
     */
    public function test_penolakan_akun_wajib_ada_alasan_dan_tampil_saat_login(): void
    {
        $pendaftar = User::factory()->create([
            'email' => 'ditolak.test@desa.id',
            'password' => Hash::make('password123'),
            'status' => StatusAkun::Menunggu,
        ]);

        // 1. Penolakan tanpa alasan ditolak oleh validasi
        $this->actingAs($this->superadmin)
            ->post(route('admin.akun.tolak', $pendaftar), ['alasan_penolakan' => ''])
            ->assertSessionHasErrors('alasan_penolakan');

        // 2. Penolakan dengan alasan yang jelas
        $alasan = 'Berkas surat tugas buram dan tidak terbaca stempel dinasnya.';
        $response = $this->actingAs($this->superadmin)
            ->post(route('admin.akun.tolak', $pendaftar), ['alasan_penolakan' => $alasan]);

        $response->assertRedirect(route('admin.akun.show', $pendaftar));

        $pendaftar->refresh();
        $this->assertSame(StatusAkun::Ditolak, $pendaftar->status);
        $this->assertSame($alasan, $pendaftar->alasan_penolakan);
        $this->assertFalse($pendaftar->bolehLogin());

        // 3. Saat pendaftar mencoba login, alasan penolakan muncul ke layar
        \Illuminate\Support\Facades\Auth::logout();
        $this->post(route('login'), [
            'email' => 'ditolak.test@desa.id',
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => "Pendaftaran Anda ditolak. Alasan: {$alasan}",
        ]);
    }

    /**
     * PSKE-37 · Akun dapat dinonaktifkan tanpa menghapus data atau riwayatnya.
     */
    public function test_akun_dapat_dinonaktifkan_tanpa_menghapus_riwayat(): void
    {
        $userAktif = User::factory()->kepalaWilayah('32.04.12')->create([
            'email' => 'nonaktif.test@desa.id',
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($this->superadmin)
            ->post(route('admin.akun.nonaktifkan', $userAktif))
            ->assertRedirect(route('admin.akun.show', $userAktif));

        $userAktif->refresh();
        $this->assertSame(StatusAkun::Nonaktif, $userAktif->status);
        $this->assertSame('32.04.12', $userAktif->kode_wilayah); // Data tidak hilang
        $this->assertDatabaseHas('users', ['id' => $userAktif->id]); // Record tidak terhapus

        // Akun nonaktif tidak bisa login
        \Illuminate\Support\Facades\Auth::logout();
        $this->assertFalse($userAktif->bolehLogin());
        $this->post(route('login'), [
            'email' => 'nonaktif.test@desa.id',
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => 'Akun Anda telah dinonaktifkan.',
        ]);
    }

    /**
     * Surat tugas di penyimpanan privat hanya bisa diunduh oleh Superadmin.
     */
    public function test_surat_tugas_di_disk_privat_hanya_bisa_diunduh_superadmin(): void
    {
        $user = User::factory()->create();
        $berkas = UploadedFile::fake()->create('surat-tugas.pdf', 500, 'application/pdf');
        $path = $berkas->store("surat-tugas/{$user->id}", 'local');

        $user->dokumenPendukung()->create([
            'jenis' => DokumenPendukung::JENIS_SURAT_TUGAS,
            'nama_file' => 'surat-tugas.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'ukuran' => 500 * 1024,
        ]);

        // Tamu dialihkan ke login
        $this->get(route('admin.akun.unduh-surat-tugas', $user))->assertRedirect(route('login'));

        // Kepala wilayah ditolak
        $kw = User::factory()->kepalaWilayah()->create();
        $this->actingAs($kw)->get(route('admin.akun.unduh-surat-tugas', $user))->assertForbidden();

        // Superadmin dapat mengunduh berkas
        $this->actingAs($this->superadmin)
            ->get(route('admin.akun.unduh-surat-tugas', $user))
            ->assertOk()
            ->assertDownload('surat-tugas.pdf');
    }

    /**
     * Akun Superadmin tidak dapat dimodifikasi (403 Forbidden).
     */
    public function test_akun_superadmin_tidak_bisa_dimodifikasi(): void
    {
        $superadminLain = User::factory()->superadmin()->create(['email' => 'other.sa@pangan.go.id']);

        // Show
        $this->actingAs($this->superadmin)
            ->get(route('admin.akun.show', $superadminLain))
            ->assertForbidden();

        // Aktivasi
        $this->actingAs($this->superadmin)
            ->post(route('admin.akun.aktivasi', $superadminLain), ['kode_wilayah' => '32.04.12'])
            ->assertForbidden();

        // Tolak
        $this->actingAs($this->superadmin)
            ->post(route('admin.akun.tolak', $superadminLain), ['alasan_penolakan' => 'Ditolak'])
            ->assertForbidden();

        // Nonaktifkan
        $this->actingAs($this->superadmin)
            ->post(route('admin.akun.nonaktifkan', $superadminLain))
            ->assertForbidden();
    }

    /**
     * Aktivasi dan tolak hanya bisa untuk akun berstatus MENUNGGU (422 Unprocessable).
     */
    public function test_aktivasi_dan_tolak_hanya_untuk_akun_menunggu(): void
    {
        $provinsi = Wilayah::create(['kode' => '32', 'nama' => 'Jawa Barat']);
        $kabupaten = Wilayah::create(['kode' => '32.04', 'nama' => 'Kab. Bandung', 'kode_induk' => '32']);
        $kecamatan = Wilayah::create(['kode' => '32.04.12', 'nama' => 'Kec. Soreang', 'kode_induk' => '32.04']);

        $statuses = [StatusAkun::Aktif, StatusAkun::Ditolak, StatusAkun::Nonaktif];

        foreach ($statuses as $status) {
            $user = User::factory()->create([
                'role' => Role::KepalaWilayah,
                'status' => $status,
                'kode_wilayah' => '32.04.12',
            ]);

            // Aktivasi pada status non-MENUNGGU ditolak 422
            $this->actingAs($this->superadmin)
                ->post(route('admin.akun.aktivasi', $user), ['kode_wilayah' => '32.04.12'])
                ->assertStatus(422);

            // Tolak pada status non-MENUNGGU ditolak 422
            $this->actingAs($this->superadmin)
                ->post(route('admin.akun.tolak', $user), ['alasan_penolakan' => 'Alasan coba'])
                ->assertStatus(422);
        }
    }

    /**
     * Nonaktifkan hanya bisa untuk akun berstatus AKTIF (422 Unprocessable).
     */
    public function test_nonaktifkan_hanya_untuk_akun_aktif(): void
    {
        $statuses = [StatusAkun::Menunggu, StatusAkun::Ditolak, StatusAkun::Nonaktif];

        foreach ($statuses as $status) {
            $user = User::factory()->create([
                'role' => Role::KepalaWilayah,
                'status' => $status,
            ]);

            // Nonaktifkan pada status non-AKTIF ditolak 422
            $this->actingAs($this->superadmin)
                ->post(route('admin.akun.nonaktifkan', $user))
                ->assertStatus(422);
        }
    }

    /**
     * Dropdown wilayah di halaman show selalu menyertakan wilayah pilihan pendaftar.
     */
    public function test_dropdown_wilayah_menyertakan_wilayah_pilihan_pendaftar(): void
    {
        $provinsi = Wilayah::create(['kode' => '32', 'nama' => 'Jawa Barat']);
        $kabupaten = Wilayah::create(['kode' => '32.04', 'nama' => 'Kab. Bandung', 'kode_induk' => '32']);
        $kecamatan = Wilayah::create(['kode' => '32.04.12', 'nama' => 'Kec. Soreang', 'kode_induk' => '32.04']);
        $desa = Wilayah::create(['kode' => '32.04.12.2001', 'nama' => 'Desa Khusus Pendaftar', 'kode_induk' => '32.04.12']);

        $pendaftar = User::factory()->create([
            'role' => Role::KepalaWilayah,
            'status' => StatusAkun::Menunggu,
            'kode_wilayah' => $desa->kode,
        ]);

        $this->actingAs($this->superadmin)
            ->get(route('admin.akun.show', $pendaftar))
            ->assertOk()
            ->assertSee('Desa Khusus Pendaftar')
            ->assertSee('32.04.12.2001');
    }

    /**
     * Endpoint pencarian wilayah AJAX mengembalikan data yang cocok.
     */
    public function test_endpoint_cari_wilayah_mengembalikan_json(): void
    {
        $provinsi = Wilayah::create(['kode' => '32', 'nama' => 'Jawa Barat']);
        $kabupaten = Wilayah::create(['kode' => '32.04', 'nama' => 'Kab. Bandung', 'kode_induk' => '32']);
        $kec1 = Wilayah::create(['kode' => '32.04.13', 'nama' => 'Kec. Pasirjambu', 'kode_induk' => '32.04']);
        $kec2 = Wilayah::create(['kode' => '32.04.14', 'nama' => 'Kec. Ciwidey', 'kode_induk' => '32.04']);

        $response = $this->actingAs($this->superadmin)
            ->getJson(route('admin.akun.cari-wilayah', ['q' => 'Ciwidey']));

        $response->assertOk()
            ->assertJsonFragment(['kode' => '32.04.14', 'nama' => 'Kec. Ciwidey'])
            ->assertJsonMissing(['kode' => '32.04.13']);
    }
}
