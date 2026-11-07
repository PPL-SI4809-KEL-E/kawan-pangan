<?php

namespace Tests\Feature\MasterData;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\Komoditas;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterDataTest extends TestCase
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

    private function lokasi(array $ubah = []): array
    {
        return array_merge(['nama' => 'Instansi Contoh', 'alamat' => 'Jalan Contoh 1', 'latitude' => '-6.9175000', 'longitude' => '107.6191000'], $ubah);
    }

    private function instansi(): Instansi
    {
        return Instansi::create($this->lokasi());
    }

    private function dataGudang(array $ubah = []): array
    {
        if (! Wilayah::find('32')) {
            Wilayah::create(['kode' => '32', 'nama' => 'Provinsi Contoh']);
        }

        return $this->lokasi(array_merge(['nama' => 'Gudang Contoh', 'instansi_id' => $this->instansi()->id, 'kode_wilayah' => '32', 'aktif' => 1], $ubah));
    }

    public function test_semua_endpoint_hanya_untuk_superadmin_aktif(): void
    {
        $komoditas = Komoditas::create(['nama' => 'Beras', 'satuan' => 'kg']);
        $gudang = Gudang::create($this->dataGudang());
        $endpoints = [];
        foreach (['komoditas' => $komoditas->id, 'instansi' => $gudang->instansi_id, 'gudang' => $gudang->id] as $resource => $id) {
            $endpoints = array_merge($endpoints, [
                ['GET', "/admin/$resource"], ['GET', "/admin/$resource/create"], ['POST', "/admin/$resource"],
                ['GET', "/admin/$resource/$id/edit"], ['PATCH', "/admin/$resource/$id"], ['PUT', "/admin/$resource/$id"], ['DELETE', "/admin/$resource/$id"],
            ]);
        }
        $endpoints[] = ['PATCH', "/admin/gudang/{$gudang->id}/status"];
        $endpoints[] = ['GET', '/admin/gudang/opsi-wilayah?q=32'];
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
        $this->assertDatabaseCount('komoditas', 1);
        $this->assertDatabaseCount('instansi', 1);
        $this->assertDatabaseCount('gudang', 1);
        $this->assertTrue($gudang->fresh()->aktif);
    }

    public function test_crud_komoditas_dan_mass_assignment_aman(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/komoditas')->assertOk()->assertSee('Belum ada komoditas');
        $this->get('/admin/komoditas/create')->assertOk();
        $this->post('/admin/komoditas', ['nama' => ' Beras   Putih ', 'satuan' => 'kg', 'id' => 999, 'nama_unik' => 'disusupi'])
            ->assertRedirect('/admin/komoditas')->assertSessionHasNoErrors();
        $komoditas = Komoditas::sole();
        $this->assertSame('Beras Putih', $komoditas->nama);
        $this->assertNotSame(999, $komoditas->id);
        $this->assertDatabaseHas('komoditas', ['nama_unik' => 'beras putih']);
        $this->get("/admin/komoditas/{$komoditas->id}/edit")->assertOk()->assertSee('Beras Putih');
        $this->patch("/admin/komoditas/{$komoditas->id}", ['nama' => 'BERAS PUTIH', 'satuan' => 'ton'])
            ->assertRedirect('/admin/komoditas')->assertSessionHasNoErrors();
        $this->assertSame('ton', $komoditas->fresh()->satuan);
        $this->delete("/admin/komoditas/{$komoditas->id}")->assertRedirect('/admin/komoditas');
        $this->assertDatabaseCount('komoditas', 0);
    }

    public function test_duplikat_komoditas_ditolak_saat_tambah_dan_ubah_dan_input_dipertahankan(): void
    {
        Komoditas::create(['nama' => 'Beras Putih', 'satuan' => 'kg']);
        $lain = Komoditas::create(['nama' => 'Jagung', 'satuan' => 'kg']);
        $this->actingAs($this->admin())->from('/admin/komoditas/create')
            ->post('/admin/komoditas', ['nama' => ' beras   PUTIH ', 'satuan' => 'ton'])
            ->assertRedirect('/admin/komoditas/create')->assertSessionHasErrors('nama')->assertSessionHasInput('satuan', 'ton');
        $this->patch("/admin/komoditas/{$lain->id}", ['nama' => 'BERAS PUTIH', 'satuan' => 'ton'])->assertSessionHasErrors('nama');
        $this->assertSame('Jagung', $lain->fresh()->nama);
        $this->assertSame('kg', $lain->fresh()->satuan);
        $this->assertDatabaseCount('komoditas', 2);
    }

    public function test_unique_index_mencegah_duplikat_langsung_di_database(): void
    {
        Komoditas::create(['nama' => 'Beras', 'satuan' => 'kg']);
        $this->expectException(QueryException::class);
        Komoditas::create(['nama' => ' BERAS ', 'satuan' => 'ton']);
    }

    public function test_nama_dan_satuan_komoditas_divalidasi(): void
    {
        $this->actingAs($this->admin());
        foreach ([['nama' => '', 'satuan' => ''], ['nama' => str_repeat('x', 151), 'satuan' => str_repeat('x', 31)], ['nama' => [], 'satuan' => []]] as $data) {
            $this->post('/admin/komoditas', $data)->assertSessionHasErrors(['nama', 'satuan']);
        }
        $this->assertDatabaseCount('komoditas', 0);
    }

    public function test_crud_instansi_dan_dropdown_registrasi_memakai_master(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/instansi')->assertOk()->assertSee('Belum ada instansi');
        $this->get('/admin/instansi/create')->assertOk();
        $this->post('/admin/instansi', $this->lokasi())->assertRedirect('/admin/instansi')->assertSessionHasNoErrors();
        $instansi = Instansi::sole();
        $this->assertSame('-6.9175000', $instansi->latitude);
        $this->get("/admin/instansi/{$instansi->id}/edit")->assertOk()->assertSee('Instansi Contoh');
        $this->patch("/admin/instansi/{$instansi->id}", $this->lokasi(['nama' => 'Instansi Baru', 'latitude' => 0, 'longitude' => 0]))
            ->assertRedirect('/admin/instansi')->assertSessionHasNoErrors();
        $this->assertSame('0.0000000', $instansi->fresh()->latitude);
        $this->app['auth']->forgetGuards();
        $this->getJson('/registrasi/opsi/instansi')->assertOk()->assertExactJson(['data' => [['id' => $instansi->id, 'nama' => 'Instansi Baru']], 'contoh' => false]);
        $this->actingAs($this->admin())->delete("/admin/instansi/{$instansi->id}")->assertRedirect('/admin/instansi');
        $this->assertDatabaseCount('instansi', 0);
    }

    public function test_instansi_yang_dipakai_gudang_nonaktif_atau_akun_tidak_bisa_dihapus(): void
    {
        $gudang = Gudang::create($this->dataGudang(['aktif' => 0]));
        $instansiAkun = $this->instansi();
        User::factory()->create(['instansi_id' => $instansiAkun->id]);
        $this->actingAs($this->admin());
        foreach ([$gudang->instansi_id, $instansiAkun->id] as $id) {
            $this->delete("/admin/instansi/$id")->assertSessionHasErrors('instansi');
            $this->assertDatabaseHas('instansi', ['id' => $id]);
        }
    }

    public function test_crud_gudang_dan_relasi_lokasinya(): void
    {
        $data = $this->dataGudang();
        $this->actingAs($this->admin());
        $this->get('/admin/gudang')->assertOk()->assertSee('Belum ada gudang');
        $this->get('/admin/gudang/create')->assertOk()->assertSee('Instansi Contoh');
        $this->post('/admin/gudang', $data)->assertRedirect('/admin/gudang')->assertSessionHasNoErrors();
        $gudang = Gudang::sole();
        $this->assertSame($data['instansi_id'], $gudang->instansi->id);
        $this->assertSame('32', $gudang->wilayah->kode);
        $this->get("/admin/gudang/{$gudang->id}/edit")->assertOk()->assertSee('Gudang Contoh');
        $this->get('/admin/gudang')->assertOk()->assertSee('Provinsi Contoh')->assertSee('Aktif');
        $this->patch("/admin/gudang/{$gudang->id}", array_merge($data, ['nama' => 'Gudang Baru', 'aktif' => 0]))
            ->assertRedirect('/admin/gudang')->assertSessionHasNoErrors();
        $this->assertSame('Gudang Baru', $gudang->fresh()->nama);
        $this->assertFalse($gudang->fresh()->aktif);
        $this->delete("/admin/gudang/{$gudang->id}")->assertRedirect('/admin/gudang');
        $this->assertDatabaseCount('gudang', 0);
    }

    public function test_nonaktif_dan_aktif_ulang_gudang_mempertahankan_lokasi_dan_relasi(): void
    {
        $gudang = Gudang::create($this->dataGudang());
        $sebelum = $gudang->only(['nama', 'instansi_id', 'kode_wilayah', 'alamat', 'latitude', 'longitude']);
        $this->actingAs($this->admin());
        foreach ([0, 0, 1] as $aktif) {
            $this->patch("/admin/gudang/{$gudang->id}/status", ['aktif' => $aktif, 'nama' => 'Disusupi', 'instansi_id' => 999])
                ->assertRedirect('/admin/gudang')->assertSessionHasNoErrors();
            $this->assertSame((bool) $aktif, $gudang->fresh()->aktif);
            $this->assertSame($sebelum, $gudang->fresh()->only(array_keys($sebelum)));
        }
        foreach (['', 'nonaktif', 2] as $aktif) {
            $this->patch("/admin/gudang/{$gudang->id}/status", ['aktif' => $aktif])->assertSessionHasErrors('aktif');
            $this->assertTrue($gudang->fresh()->aktif);
        }
    }

    public static function lokasiTidakValid(): array
    {
        return [
            'latitude terlalu kecil' => ['latitude', -90.0000001], 'latitude terlalu besar' => ['latitude', 90.0000001],
            'longitude terlalu kecil' => ['longitude', -180.0000001], 'longitude terlalu besar' => ['longitude', 180.0000001],
            'latitude bukan angka' => ['latitude', 'abc'], 'longitude koma' => ['longitude', '107,6'],
            'latitude kosong' => ['latitude', ''], 'longitude kosong' => ['longitude', ''],
            'nama kosong' => ['nama', ''], 'nama panjang' => ['nama', str_repeat('x', 151)],
            'alamat kosong' => ['alamat', ''], 'alamat panjang' => ['alamat', str_repeat('x', 501)],
        ];
    }

    #[DataProvider('lokasiTidakValid')]
    public function test_lokasi_tidak_valid_ditolak_saat_tambah_dan_ubah(string $field, mixed $nilai): void
    {
        $instansi = $this->instansi();
        $data = $this->dataGudang();
        $gudang = Gudang::create($data);
        $this->actingAs($this->admin());
        $this->post('/admin/instansi', $this->lokasi([$field => $nilai]))->assertSessionHasErrors($field);
        $this->patch("/admin/instansi/{$instansi->id}", $this->lokasi([$field => $nilai]))->assertSessionHasErrors($field);
        $this->post('/admin/gudang', array_merge($data, [$field => $nilai]))->assertSessionHasErrors($field);
        $this->patch("/admin/gudang/{$gudang->id}", array_merge($data, [$field => $nilai]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('instansi', 2);
        $this->assertDatabaseCount('gudang', 1);
        $this->assertSame($this->lokasi()[$field], $instansi->fresh()->$field);
        $this->assertSame($data[$field], $gudang->fresh()->$field);
    }

    public function test_gudang_menolak_relasi_dan_status_tidak_valid(): void
    {
        $data = $this->dataGudang();
        $this->actingAs($this->admin());
        foreach (['instansi_id' => 999, 'kode_wilayah' => '99', 'aktif' => 2] as $field => $nilai) {
            $this->post('/admin/gudang', array_merge($data, [$field => $nilai]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('gudang', 0);
    }

    public function test_foreign_key_melindungi_instansi_yang_masih_dipakai(): void
    {
        $gudang = Gudang::create($this->dataGudang());
        $this->expectException(QueryException::class);
        $gudang->instansi->delete();
    }

    public function test_foreign_key_melindungi_wilayah_yang_masih_dipakai(): void
    {
        $gudang = Gudang::create($this->dataGudang());
        $this->expectException(QueryException::class);
        $gudang->wilayah->delete();
    }

    public function test_koordinat_batas_dan_nol_dapat_disimpan_serta_relasi_dapat_diubah(): void
    {
        $data = $this->dataGudang();
        $gudang = Gudang::create($data);
        $instansi = $this->instansi();
        Wilayah::create(['kode' => '33', 'nama' => 'Provinsi Lain']);
        $this->actingAs($this->admin());
        foreach ([[-90, -180], [90, 180], [0, 0]] as [$latitude, $longitude]) {
            $this->patch('/admin/gudang/'.$gudang->id, array_merge($data, [
                'instansi_id' => $instansi->id, 'kode_wilayah' => '33', 'latitude' => $latitude, 'longitude' => $longitude,
            ]))->assertSessionHasNoErrors();
            $this->assertSame((float) $latitude, (float) $gudang->fresh()->latitude);
            $this->assertSame((float) $longitude, (float) $gudang->fresh()->longitude);
            $this->assertSame($instansi->id, $gudang->fresh()->instansi_id);
            $this->assertSame('33', $gudang->fresh()->kode_wilayah);
        }
    }

    public function test_pencarian_paginasi_dan_filter_nonaktif(): void
    {
        $data = $this->dataGudang();
        $instansiLain = $this->instansi();
        for ($i = 1; $i <= 27; $i++) {
            Komoditas::create(['nama' => 'Komoditas '.$i, 'satuan' => 'kg']);
            Instansi::create($this->lokasi(['nama' => 'Instansi '.$i]));
            Gudang::create(array_merge($data, ['nama' => 'Gudang '.$i, 'aktif' => 0]));
        }
        Gudang::create(array_merge($data, ['nama' => 'Gudang Aktif', 'instansi_id' => $instansiLain->id]));
        $this->actingAs($this->admin());
        $this->get('/admin/komoditas?q=Komoditas')->assertOk()->assertViewHas('komoditas', fn ($p) => $p->total() === 27 && $p->count() === 25 && str_contains($p->nextPageUrl(), 'q=Komoditas'));
        $this->get('/admin/instansi?q=Instansi')->assertOk()->assertViewHas('instansi', fn ($p) => $p->total() === 29 && $p->count() === 25);
        $this->get('/admin/gudang?aktif=0&instansi_id='.$data['instansi_id'])->assertOk()->assertViewHas('gudang', fn ($p) => $p->total() === 27 && $p->count() === 25 && str_contains($p->nextPageUrl(), 'aktif=0'));
        $this->get('/admin/gudang?aktif=1')->assertOk()->assertSee('Gudang Aktif')->assertViewHas('gudang', fn ($p) => $p->total() === 1);
        foreach (['komoditas', 'instansi', 'gudang'] as $resource) {
            $this->get("/admin/$resource?q=TidakAda")->assertOk()->assertSee('Tidak ada');
            $this->getJson("/admin/$resource?q[]=x")->assertUnprocessable();
            $this->get("/admin/$resource/99999/edit")->assertNotFound();
            $this->delete("/admin/$resource/99999")->assertNotFound();
        }
    }

    public function test_autocomplete_wilayah_dibatasi_dan_tanpa_data_contoh(): void
    {
        $this->actingAs($this->admin());
        $this->getJson('/admin/gudang/opsi-wilayah?q=32')->assertOk()->assertExactJson(['data' => []]);
        for ($i = 10; $i <= 40; $i++) {
            Wilayah::create(['kode' => (string) $i, 'nama' => 'Provinsi Contoh '.$i]);
        }
        $this->getJson('/admin/gudang/opsi-wilayah?q=Contoh')->assertOk()->assertJsonCount(20, 'data');
        $this->getJson('/admin/gudang/opsi-wilayah?q=32')->assertOk()->assertExactJson(['data' => [['kode' => '32', 'nama' => 'Provinsi Contoh 32']]]);
        $this->getJson('/admin/gudang/opsi-wilayah')->assertOk()->assertExactJson(['data' => []]);
    }
}
