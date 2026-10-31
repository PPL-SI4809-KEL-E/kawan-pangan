<?php

namespace Tests\Feature\Registrasi;

use App\Models\DokumenPendukung;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * PSKE-2 · Regresi: bila surat tugas gagal disimpan, akun tidak boleh terbentuk
 * dan pendaftar harus melihat galat, bukan halaman "Pendaftaran Terkirim".
 */
class GagalSimpanSuratTugasTest extends TestCase
{
    use DataRegistrasi, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Ganti disk "local" dengan disk tiruan yang gagal menyimpan berkas.
     * Disk dengan 'throw' => false mengembalikan false, bukan melempar exception.
     */
    private function diskGagalMenyimpan(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->andReturnFalse();
        $disk->shouldReceive('putFile')->andReturnFalse();
        $disk->shouldNotReceive('delete');
        Storage::set('local', $disk);
    }

    public function test_penyimpanan_berkas_gagal_membatalkan_akun_dan_menampilkan_galat(): void
    {
        $this->diskGagalMenyimpan();

        $response = $this->from('/registrasi')->post('/registrasi', $this->dataValid());

        $response->assertRedirect('/registrasi')
            ->assertSessionHasErrors(['surat_tugas' => 'Surat tugas gagal disimpan. Silakan coba lagi.'])
            ->assertSessionMissing('registrasi_email');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('dokumen_pendukung', 0);
    }

    public function test_isian_dikembalikan_tanpa_kata_sandi_saat_penyimpanan_gagal(): void
    {
        $this->diskGagalMenyimpan();

        $this->from('/registrasi')->post('/registrasi', $this->dataValid())
            ->assertSessionHasInput('email', 'budi@desa.id')
            ->assertSessionHasInput('wilayah_desa', '32.04.12.2001')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
    }

    public function test_pencatatan_dokumen_gagal_menghapus_berkas_dan_membatalkan_akun(): void
    {
        Storage::fake('local');

        // Paksa penyimpanan baris dokumen_pendukung gagal setelah berkas tersimpan.
        DokumenPendukung::creating(function () {
            throw new RuntimeException('Simulasi kegagalan basis data');
        });

        $this->from('/registrasi')->post('/registrasi', $this->dataValid())
            ->assertRedirect('/registrasi')
            ->assertSessionHasErrors('surat_tugas');

        $this->assertDatabaseCount('users', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(), 'Berkas yatim harus dihapus.');
    }

    public function test_registrasi_normal_tetap_berhasil(): void
    {
        Storage::fake('local');

        $this->post('/registrasi', $this->dataValid())
            ->assertRedirect(route('registrasi.terkirim'))
            ->assertSessionHas('registrasi_email', 'budi@desa.id');

        $this->assertSame(1, User::count());
        Storage::disk('local')->assertExists(DokumenPendukung::firstOrFail()->path);
    }
}
