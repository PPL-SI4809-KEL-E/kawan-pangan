<?php

namespace Tests\Feature\Registrasi;

use App\Models\DokumenPendukung;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PSKE-5 · Unggah surat tugas PDF/JPG maksimal 2 MB.
 */
class UnggahSuratTugasTest extends TestCase
{
    use DataRegistrasi, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_form_menampilkan_input_surat_tugas_pdf_jpg(): void
    {
        $this->get('/registrasi')
            ->assertOk()
            ->assertSee('name="surat_tugas"', false)
            ->assertSee('accept=".pdf,.jpg,.jpeg', false)
            ->assertSee('PDF atau JPG, maksimal 2 MB.');
    }

    public function test_surat_tugas_pdf_tersimpan_di_disk_privat_dan_tercatat(): void
    {
        $this->post('/registrasi', $this->dataValid(['surat_tugas' => $this->suratTugasPdf(500, 'ST Kades Citeureup.pdf')]))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'budi@desa.id')->firstOrFail();
        $dokumen = $user->dokumenPendukung()->firstOrFail();

        $this->assertSame(DokumenPendukung::JENIS_SURAT_TUGAS, $dokumen->jenis);
        $this->assertSame('ST Kades Citeureup.pdf', $dokumen->nama_file);
        $this->assertSame('application/pdf', $dokumen->mime);
        $this->assertStringStartsWith("surat-tugas/{$user->id}/", $dokumen->path);
        Storage::disk('local')->assertExists($dokumen->path);
    }

    public function test_surat_tugas_jpg_diterima(): void
    {
        $this->post('/registrasi', $this->dataValid(['surat_tugas' => $this->suratTugasJpg(1500)]))
            ->assertSessionHasNoErrors();

        $this->assertSame('image/jpeg', DokumenPendukung::firstOrFail()->mime);
    }

    public function test_surat_tugas_tepat_2_mb_diterima(): void
    {
        $this->post('/registrasi', $this->dataValid(['surat_tugas' => $this->suratTugasPdf(2048)]))
            ->assertSessionHasNoErrors();
    }

    public function test_surat_tugas_lebih_dari_2_mb_ditolak(): void
    {
        $this->post('/registrasi', $this->dataValid(['surat_tugas' => $this->suratTugasPdf(2049)]))
            ->assertSessionHasErrors(['surat_tugas' => 'Ukuran surat tugas maksimal 2 MB.']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('dokumen_pendukung', 0);
    }

    public function test_format_selain_pdf_jpg_ditolak(): void
    {
        $png = $this->berkasDenganIsi("\x89PNG\r\n\x1a\n".str_repeat("\0", 100), 'surat.png', 'image/png');
        $docx = UploadedFile::fake()->create('surat.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->post('/registrasi', $this->dataValid(['surat_tugas' => $png]))
            ->assertSessionHasErrors(['surat_tugas' => 'Format surat tugas harus PDF atau JPG.']);
        $this->post('/registrasi', $this->dataValid(['surat_tugas' => $docx]))
            ->assertSessionHasErrors('surat_tugas');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_berkas_exe_yang_diganti_nama_menjadi_pdf_ditolak(): void
    {
        $palsu = $this->berkasDenganIsi("MZ\x90\x00".str_repeat("\0", 200), 'surat-tugas.pdf', 'application/pdf');

        $this->post('/registrasi', $this->dataValid(['surat_tugas' => $palsu]))
            ->assertSessionHasErrors('surat_tugas');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registrasi_tanpa_surat_tugas_ditolak(): void
    {
        $data = $this->dataValid();
        unset($data['surat_tugas']);

        $this->post('/registrasi', $data)
            ->assertSessionHasErrors(['surat_tugas' => 'Surat tugas wajib diunggah.']);
    }
}
