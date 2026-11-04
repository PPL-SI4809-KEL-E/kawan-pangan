<?php

namespace Tests\Feature\Wilayah;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\ImporWilayah;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterWilayahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Superadmin, 'status' => StatusAkun::Aktif]);
    }

    private function csv(string $isi, string $nama = 'wilayah.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    private function hierarki(): void
    {
        foreach ([['32', 'Provinsi Contoh', null], ['32.04', 'Kabupaten Contoh', '32'], ['32.04.12', 'Kecamatan Contoh', '32.04'], ['32.04.12.2001', 'Desa Contoh', '32.04.12']] as [$kode, $nama, $induk]) {
            Wilayah::create(['kode' => $kode, 'nama' => $nama, 'kode_induk' => $induk]);
        }
    }

    public function test_tamu_dan_akun_tanpa_izin_ditolak_pada_semua_endpoint(): void
    {
        $this->hierarki();
        $endpoints = [
            ['GET', '/admin/wilayah'], ['GET', '/admin/wilayah/template'],
            ['POST', '/admin/wilayah/impor'], ['GET', '/admin/wilayah/32/edit'], ['PATCH', '/admin/wilayah/32'],
        ];
        foreach ($endpoints as [$method, $url]) {
            $this->call($method, $url)->assertUnauthorized();
        }
        foreach ([
            [Role::KepalaWilayah, StatusAkun::Aktif], [Role::BadanResmi, StatusAkun::Aktif],
            [Role::Superadmin, StatusAkun::Menunggu], [Role::Superadmin, StatusAkun::Ditolak], [Role::Superadmin, StatusAkun::Nonaktif],
        ] as [$role, $status]) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => $status]));
            foreach ($endpoints as [$method, $url]) {
                $this->call($method, $url)->assertForbidden();
            }
        }
        $this->assertDatabaseCount('wilayah', 4);
        $this->assertNull(Wilayah::findOrFail('32')->jumlah_penduduk);
    }

    public function test_impor_empat_level_urutan_acak_dan_dropdown_registrasi(): void
    {
        $this->actingAs($this->admin())->post('/admin/wilayah/impor', ['berkas' => $this->csv(
            "kode,nama,kode_induk,jumlah_penduduk\n32.04.12.2001,Desa Contoh,32.04.12,1200\n32.04,Kabupaten Contoh,32,\n32,Provinsi Contoh,,\n32.04.12,Kecamatan Contoh,32.04,0\n"
        )])->assertRedirect('/admin/wilayah')->assertSessionHasNoErrors()->assertSessionHas('sukses', 'Impor berhasil: 4 wilayah baru, 0 wilayah diperbarui.');
        $this->assertDatabaseCount('wilayah', 4);
        $this->assertDatabaseHas('wilayah', ['kode' => '32', 'level' => 1, 'kode_induk' => null, 'jumlah_penduduk' => null]);
        $this->assertDatabaseHas('wilayah', ['kode' => '32.04.12.2001', 'level' => 4, 'kode_induk' => '32.04.12', 'jumlah_penduduk' => 1200]);
        $this->assertSame('32.04', Wilayah::findOrFail('32.04.12')->induk->kode);
        $this->assertSame(1, Wilayah::findOrFail('32')->anak()->count());

        // Rute opsi bersifat guest; gunakan session baru setelah impor admin.
        $this->app['auth']->forgetGuards();
        $this->getJson('/registrasi/opsi/wilayah')->assertOk()->assertExactJson(['data' => [['kode' => '32', 'nama' => 'Provinsi Contoh']], 'contoh' => false]);
        $this->getJson('/registrasi/opsi/wilayah?induk=32.04.12')->assertOk()->assertExactJson(['data' => [['kode' => '32.04.12.2001', 'nama' => 'Desa Contoh']], 'contoh' => false]);
    }

    public function test_impor_ulang_tidak_duplikat_dan_mempertahankan_penduduk_yang_kosong(): void
    {
        $this->hierarki();
        Wilayah::findOrFail('32.04.12.2001')->update(['jumlah_penduduk' => 321]);
        $this->actingAs($this->admin());
        $berkas = "\xEF\xBB\xBFkode;nama;kode_induk;jumlah_penduduk\r\n32.04.12.2001;\"Desa; Nama Baru\";32.04.12;\r\n";
        for ($i = 0; $i < 2; $i++) {
            $this->post('/admin/wilayah/impor', ['berkas' => $this->csv($berkas)])->assertSessionHasNoErrors()->assertSessionHas('sukses', 'Impor berhasil: 0 wilayah baru, 1 wilayah diperbarui.');
        }
        $this->assertDatabaseCount('wilayah', 4);
        $this->assertDatabaseHas('wilayah', ['kode' => '32.04.12.2001', 'nama' => 'Desa; Nama Baru', 'jumlah_penduduk' => 321]);
        $this->post('/admin/wilayah/impor', ['berkas' => $this->csv("kode,nama,kode_induk\n32.04.12.2001,Desa Tanpa Populasi,32.04.12\n")])->assertSessionHasNoErrors();
        $this->assertSame(321, Wilayah::findOrFail('32.04.12.2001')->jumlah_penduduk);
        $this->post('/admin/wilayah/impor', ['berkas' => $this->csv("kode,nama,kode_induk,jumlah_penduduk\n32.04.12.2001,Desa Nol,32.04.12,0\n")])->assertSessionHasNoErrors();
        $this->assertSame(0, Wilayah::findOrFail('32.04.12.2001')->jumlah_penduduk);
    }

    public static function csvTidakValid(): array
    {
        return [
            'tanpa induk' => ["kode,nama,kode_induk\n32.04,Kabupaten,\n"],
            'induk belum tersedia' => ["kode,nama,kode_induk\n33.01.01,Kecamatan,33.01\n"],
            'induk silang provinsi' => ["kode,nama,kode_induk\n32.04,Kabupaten,31\n"],
            'induk lompat level' => ["kode,nama,kode_induk\n32.04.12,Kecamatan,32\n"],
            'provinsi punya induk' => ["kode,nama,kode_induk\n32,Provinsi,32\n"],
            'kode tanpa titik' => ["kode,nama,kode_induk\n3204,Kabupaten,32\n"],
            'kode desa salah' => ["kode,nama,kode_induk\n32.04.12.001,Desa,32.04.12\n"],
            'duplikat' => ["kode,nama,kode_induk\n32,Satu,\n32,Dua,\n"],
            'nama kosong' => ["kode,nama,kode_induk\n32, ,\n"],
            'nama terlalu panjang' => ["kode,nama,kode_induk\n32,".str_repeat('a', 151).",\n"],
            'UTF-8 tidak valid' => ["kode,nama,kode_induk\n32,\xFF,\n"],
            'penduduk negatif' => ["kode,nama,kode_induk,jumlah_penduduk\n32,Provinsi,,-1\n"],
            'penduduk pecahan' => ["kode,nama,kode_induk,jumlah_penduduk\n32,Provinsi,,1.5\n"],
            'penduduk terlalu besar' => ["kode,nama,kode_induk,jumlah_penduduk\n32,Provinsi,,2147483648\n"],
            'header salah' => ["kode,nama,parent\n32,Provinsi,\n"],
            'header duplikat' => ["kode,nama,kode_induk,nama\n32,Provinsi,,Nama\n"],
            'kolom berlebih' => ["kode,nama,kode_induk\n32,Provinsi,,tambahan\n"],
            'hanya header' => ["kode,nama,kode_induk\n"],
            'kosong' => [''],
        ];
    }

    #[DataProvider('csvTidakValid')]
    public function test_csv_tidak_valid_ditolak_tanpa_mengubah_master(string $isi): void
    {
        $this->hierarki();
        $sebelum = Wilayah::orderBy('kode')->get()->toArray();
        $this->actingAs($this->admin())->from('/admin/wilayah')->post('/admin/wilayah/impor', ['berkas' => $this->csv($isi)])
            ->assertRedirect('/admin/wilayah')->assertSessionHasErrors('berkas');
        $this->assertSame($sebelum, Wilayah::orderBy('kode')->get()->toArray());
    }

    public function test_galat_di_baris_akhir_membatalkan_semua_perubahan(): void
    {
        $this->hierarki();
        $this->actingAs($this->admin())->post('/admin/wilayah/impor', ['berkas' => $this->csv("kode,nama,kode_induk\n32,Nama Baru,\n33,Provinsi Baru,\n33.01.01,Kecamatan Yatim,33.01\n")])->assertSessionHasErrors('berkas');
        $this->assertDatabaseMissing('wilayah', ['kode' => '33']);
        $this->assertSame('Provinsi Contoh', Wilayah::findOrFail('32')->nama);
    }

    public function test_kegagalan_database_di_batch_berikutnya_rollback_batch_sebelumnya(): void
    {
        $asli = DB::getQueryGrammar();
        $grammar = \Mockery::mock($asli)->makePartial();
        $grammar->shouldReceive('compileUpsert')->once()->passthru();
        $grammar->shouldReceive('compileUpsert')->once()->andThrow(new \RuntimeException('Simulasi gagal batch kedua'));
        DB::connection()->setQueryGrammar($grammar);
        try {
            app(ImporWilayah::class)->impor($this->csv("kode,nama,kode_induk\n32,Provinsi,\n32.04,Kabupaten,32\n"));
            $this->fail('Impor seharusnya gagal.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulasi gagal batch kedua', $e->getMessage());
        } finally {
            DB::connection()->setQueryGrammar($asli);
        }
        $this->assertDatabaseCount('wilayah', 0);
    }

    public function test_validasi_berkas_dan_template(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/wilayah/impor')->assertSessionHasErrors('berkas');
        $this->post('/admin/wilayah/impor', ['berkas' => $this->csv('kode,nama,kode_induk', 'wilayah.txt')])->assertSessionHasErrors('berkas');
        $this->post('/admin/wilayah/impor', ['berkas' => UploadedFile::fake()->create('wilayah.csv', 20481, 'text/csv')])->assertSessionHasErrors('berkas');
        $this->get('/admin/wilayah/template')->assertOk()->assertDownload('template-wilayah.csv')->assertStreamedContent("kode,nama,kode_induk,jumlah_penduduk\r\n");
    }

    public function test_kegagalan_penyimpanan_memberi_pesan_tanpa_detail_database(): void
    {
        $this->mock(ImporWilayah::class, function ($mock) {
            $mock->shouldReceive('impor')->once()->andThrow(new QueryException('sqlite', 'insert into wilayah ...', [], new \RuntimeException('Database internal gagal')));
        });
        $this->actingAs($this->admin())->from('/admin/wilayah')->post('/admin/wilayah/impor', ['berkas' => $this->csv("kode,nama,kode_induk\n32,Provinsi,\n")])
            ->assertRedirect('/admin/wilayah')->assertSessionHasErrors(['berkas' => 'Impor gagal disimpan. Tidak ada perubahan data. Silakan coba lagi.']);
        $this->assertDatabaseCount('wilayah', 0);
    }

    public function test_model_menolak_wilayah_tanpa_induk_dan_database_melindungi_relasi(): void
    {
        try {
            Wilayah::create(['kode' => '32.04', 'nama' => 'Kabupaten', 'kode_induk' => null]);
            $this->fail('Wilayah tanpa induk harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kode_induk', $e->errors());
        }
        $this->hierarki();
        $this->expectException(QueryException::class);
        Wilayah::findOrFail('32')->delete();
    }

    public function test_model_menolak_perubahan_kode_yang_dapat_memutus_relasi(): void
    {
        $this->hierarki();
        $this->expectException(ValidationException::class);
        Wilayah::findOrFail('32')->update(['kode' => '33']);
    }

    public function test_pencarian_filter_anak_paginasi_dan_tampilan_kosong(): void
    {
        $this->actingAs($this->admin())->get('/admin/wilayah')->assertOk()->assertSee('Belum ada master wilayah');
        $this->hierarki();
        for ($i = 1; $i <= 27; $i++) {
            Wilayah::create(['kode' => sprintf('32.04.12.%04d', 2100 + $i), 'nama' => 'Desa Tambahan '.$i, 'kode_induk' => '32.04.12']);
        }
        $this->get('/admin/wilayah?level=4')->assertOk()->assertViewHas('wilayah', fn ($p) => $p->total() === 28 && $p->count() === 25 && str_contains($p->nextPageUrl(), 'level=4'));
        $this->get('/admin/wilayah?q=32.04.12.2001')->assertOk()->assertViewHas('wilayah', fn ($p) => $p->total() === 1)->assertSee('Desa Contoh');
        $this->get('/admin/wilayah?induk=32')->assertOk()->assertViewHas('wilayah', fn ($p) => $p->total() === 1 && $p->first()->kode === '32.04');
        $this->get('/admin/wilayah?q=TidakAda')->assertOk()->assertSee('Tidak ada wilayah yang sesuai filter.');
        $this->getJson('/admin/wilayah?level=9')->assertUnprocessable();
        $this->getJson('/admin/wilayah?induk=99')->assertUnprocessable();
    }

    public function test_penduduk_dapat_diubah_ke_nol_dan_hanya_field_itu_yang_diperbarui(): void
    {
        $this->hierarki();
        $this->actingAs($this->admin())->get('/admin/wilayah/32.04.12.2001/edit')->assertOk()->assertSee('Desa Contoh');
        foreach ([2500, 0] as $jumlah) {
            $this->patch('/admin/wilayah/32.04.12.2001', ['jumlah_penduduk' => $jumlah, 'nama' => 'Disusupi', 'kode_induk' => '31', 'kode' => '31'])
                ->assertRedirect('/admin/wilayah/32.04.12.2001/edit')->assertSessionHasNoErrors();
            $this->assertDatabaseHas('wilayah', ['kode' => '32.04.12.2001', 'nama' => 'Desa Contoh', 'kode_induk' => '32.04.12', 'jumlah_penduduk' => $jumlah]);
        }
        $this->patch('/admin/wilayah/99', ['jumlah_penduduk' => 100])->assertNotFound();
    }

    public function test_penduduk_tidak_valid_tidak_mengubah_data_dan_input_tetap_tersedia(): void
    {
        $this->hierarki();
        $this->actingAs($this->admin());
        foreach ([-1, '1.5', 'abc', '', 2147483648] as $nilai) {
            $this->from('/admin/wilayah/32/edit')->patch('/admin/wilayah/32', ['jumlah_penduduk' => $nilai])
                ->assertRedirect('/admin/wilayah/32/edit')->assertSessionHasErrors('jumlah_penduduk');
            $this->assertNull(Wilayah::findOrFail('32')->jumlah_penduduk);
        }
    }
}
