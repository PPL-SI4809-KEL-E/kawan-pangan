<?php

namespace Tests\Feature\Gudang;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\CakupanGudang;
use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CakupanGudangTest extends TestCase
{
    use RefreshDatabase;

    private Instansi $instansi;
    private Gudang $gudang;
    private Wilayah $provinsi;
    private Wilayah $kabupaten;
    private Wilayah $kecamatan1;
    private Wilayah $kecamatan2;
    private Wilayah $desa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provinsi = Wilayah::create([
            'kode' => '32',
            'nama' => 'Jawa Barat',
        ]);

        $this->kabupaten = Wilayah::create([
            'kode' => '32.04',
            'nama' => 'Kabupaten Bandung',
            'kode_induk' => '32',
        ]);

        $this->kecamatan1 = Wilayah::create([
            'kode' => '32.04.01',
            'nama' => 'Soreang',
            'kode_induk' => '32.04',
        ]);

        $this->kecamatan2 = Wilayah::create([
            'kode' => '32.04.02',
            'nama' => 'Pasirjambu',
            'kode_induk' => '32.04',
        ]);

        $this->desa = Wilayah::create([
            'kode' => '32.04.01.2001',
            'nama' => 'Soreang Kulon',
            'kode_induk' => '32.04.01',
        ]);

        $this->instansi = Instansi::create([
            'nama' => 'Dinas Ketahanan Pangan',
            'alamat' => 'Jl. Raya Soreang No. 1',
            'latitude' => -7.0305,
            'longitude' => 107.5180,
            'aktif' => true,
        ]);

        $this->gudang = Gudang::create([
            'nama' => 'Gudang Logistik Soreang',
            'instansi_id' => $this->instansi->id,
            'kode_wilayah' => '32.04.01',
            'alamat' => 'Kawasan Pergudangan Soreang',
            'latitude' => -7.0310,
            'longitude' => 107.5190,
            'aktif' => true,
        ]);
    }

    /**
     * PSKE-49: Pasangan gudang-kecamatan dapat disimpan dan terhubung melalui relasi.
     */
    public function test_cakupan_gudang_berhasil_disimpan_dan_relasi_terhubung(): void
    {
        $cakupan = CakupanGudang::create([
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => true,
        ]);

        $this->assertDatabaseHas('cakupan_gudang', [
            'id' => $cakupan->id,
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => '32.04.01',
            'is_utama' => 1,
        ]);

        // Cek relasi dari model CakupanGudang
        $this->assertEquals($this->gudang->id, $cakupan->gudang->id);
        $this->assertEquals($this->kecamatan1->kode, $cakupan->kecamatan->kode);

        // Cek relasi dari model Gudang
        $this->assertCount(1, $this->gudang->fresh()->cakupan);
        $this->assertTrue($this->gudang->fresh()->kecamatan->contains('kode', '32.04.01'));

        // Cek relasi dari model Wilayah
        $this->assertCount(1, $this->kecamatan1->fresh()->cakupanGudang);
        $this->assertTrue($this->kecamatan1->fresh()->gudang->contains('id', $this->gudang->id));
    }

    /**
     * PSKE-49: Pasangan gudang-kecamatan unik; menolak duplikasi kecamatan pada gudang yang sama.
     */
    public function test_pske_49_menolak_duplikat_kecamatan_pada_gudang_yang_sama(): void
    {
        CakupanGudang::create([
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => false,
        ]);

        // Percobaan kedua dengan model harus melempar ValidationException
        $this->expectException(ValidationException::class);
        CakupanGudang::create([
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => true,
        ]);
    }

    /**
     * PSKE-49: Database level unique key constraint menolak duplikasi pasangan gudang-kecamatan.
     */
    public function test_pske_49_database_constraint_menolak_duplikat_cakupan(): void
    {
        CakupanGudang::create([
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => false,
        ]);

        // Bypass model booted validation untuk menguji database unique constraint
        $this->expectException(QueryException::class);
        \Illuminate\Support\Facades\DB::table('cakupan_gudang')->insert([
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * PSKE-49: Wilayah cakupan wajib merupakan tingkat kecamatan (level 3).
     */
    public function test_pske_49_wilayah_bukan_kecamatan_ditolak(): void
    {
        // Coba masukkan Provinsi (level 1)
        try {
            CakupanGudang::create([
                'gudang_id' => $this->gudang->id,
                'kode_kecamatan' => $this->provinsi->kode,
                'is_utama' => false,
            ]);
            $this->fail('Harusnya gagal saat mendaftarkan wilayah provinsi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kode_kecamatan', $e->errors());
        }

        // Coba masukkan Kabupaten (level 2)
        try {
            CakupanGudang::create([
                'gudang_id' => $this->gudang->id,
                'kode_kecamatan' => $this->kabupaten->kode,
                'is_utama' => false,
            ]);
            $this->fail('Harusnya gagal saat mendaftarkan wilayah kabupaten.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kode_kecamatan', $e->errors());
        }

        // Coba masukkan Desa (level 4)
        try {
            CakupanGudang::create([
                'gudang_id' => $this->gudang->id,
                'kode_kecamatan' => $this->desa->kode,
                'is_utama' => false,
            ]);
            $this->fail('Harusnya gagal saat mendaftarkan wilayah desa.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kode_kecamatan', $e->errors());
        }
    }

    /**
     * PSKE-50: Hanya satu gudang utama per kecamatan; pelanggaran ditolak dengan pesan jelas.
     */
    public function test_pske_50_hanya_satu_gudang_utama_per_kecamatan(): void
    {
        // Gudang 1 menjadi gudang utama untuk kecamatan1
        CakupanGudang::create([
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => true,
        ]);

        // Buat Gudang 2 di bawah instansi yang sama (atau berbeda)
        $gudang2 = Gudang::create([
            'nama' => 'Gudang Logistik Ciwidey',
            'instansi_id' => $this->instansi->id,
            'kode_wilayah' => '32.04.02',
            'alamat' => 'Jl. Ciwidey No. 5',
            'latitude' => -7.0900,
            'longitude' => 107.4800,
            'aktif' => true,
        ]);

        // Gudang 2 boleh mencakup kecamatan1 asalkan BUKAN gudang utama (is_utama = false)
        $cakupanGudang2 = CakupanGudang::create([
            'gudang_id' => $gudang2->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => false,
        ]);
        $this->assertDatabaseHas('cakupan_gudang', [
            'id' => $cakupanGudang2->id,
            'gudang_id' => $gudang2->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => 0,
        ]);

        // Namun jika Gudang 2 mencoba diubah statusnya menjadi gudang utama untuk kecamatan1, harus ditolak
        try {
            $cakupanGudang2->update(['is_utama' => true]);
            $this->fail('Harusnya gagal saat menetapkan gudang utama kedua pada kecamatan yang sama.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_utama', $e->errors());
            $pesan = $e->errors()['is_utama'][0];
            $this->assertStringContainsString('sudah memiliki gudang utama', $pesan);
            $this->assertStringContainsString($this->gudang->nama, $pesan);
            $this->assertStringContainsString($this->kecamatan1->nama, $pesan);
        }

        // Pembuatan langsung dengan is_utama = true pada kecamatan yang sudah punya gudang utama juga harus ditolak
        $gudang3 = Gudang::create([
            'nama' => 'Gudang Logistik Banjaran',
            'instansi_id' => $this->instansi->id,
            'kode_wilayah' => '32.04.01',
            'alamat' => 'Jl. Banjaran No. 10',
            'latitude' => -7.0450,
            'longitude' => 107.5800,
            'aktif' => true,
        ]);

        try {
            CakupanGudang::create([
                'gudang_id' => $gudang3->id,
                'kode_kecamatan' => $this->kecamatan1->kode,
                'is_utama' => true,
            ]);
            $this->fail('Harusnya gagal saat membuat cakupan gudang utama baru pada kecamatan yang sudah memiliki gudang utama.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_utama', $e->errors());
            $pesan = $e->errors()['is_utama'][0];
            $this->assertStringContainsString('sudah memiliki gudang utama', $pesan);
            $this->assertStringContainsString($this->gudang->nama, $pesan);
        }
    }

    /**
     * PSKE-50: Status utama dapat dialihkan setelah gudang utama sebelumnya melepaskan status utama.
     */
    public function test_pske_50_dapat_mengalihkan_status_utama_setelah_dilepas(): void
    {
        $cakupan1 = CakupanGudang::create([
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => true,
        ]);

        $gudang2 = Gudang::create([
            'nama' => 'Gudang Sekunder Soreang',
            'instansi_id' => $this->instansi->id,
            'kode_wilayah' => '32.04.01',
            'alamat' => 'Jl. Soreang Timur No. 3',
            'latitude' => -7.0320,
            'longitude' => 107.5200,
            'aktif' => true,
        ]);

        $cakupan2 = CakupanGudang::create([
            'gudang_id' => $gudang2->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => false,
        ]);

        // Lepas status utama dari gudang 1
        $cakupan1->update(['is_utama' => false]);
        $this->assertFalse($cakupan1->fresh()->is_utama);

        // Sekarang alihkan status utama ke gudang 2 -> harus berhasil!
        $cakupan2->update(['is_utama' => true]);
        $this->assertTrue($cakupan2->fresh()->is_utama);
    }

    // ── Helper Pembuatan User untuk Pengujian Hak Akses PSKE-51 ────

    private function userPengelola(int $instansiId): User
    {
        $user = User::factory()->create([
            'role' => Role::BadanResmi,
            'status' => StatusAkun::Aktif,
            'instansi_id' => $instansiId,
        ]);
        $user->forceFill(['jabatan' => User::JABATAN_PENGELOLA])->save();

        return $user;
    }

    private function userPetugas(int $instansiId): User
    {
        $user = User::factory()->create([
            'role' => Role::BadanResmi,
            'status' => StatusAkun::Aktif,
            'instansi_id' => $instansiId,
        ]);
        $user->forceFill(['jabatan' => User::JABATAN_PETUGAS])->save();

        return $user;
    }

    private function userSuperadmin(): User
    {
        return User::factory()->create([
            'role' => Role::Superadmin,
            'status' => StatusAkun::Aktif,
        ]);
    }

    private function userKepalaWilayah(): User
    {
        return User::factory()->create([
            'role' => Role::KepalaWilayah,
            'status' => StatusAkun::Aktif,
            'kode_wilayah' => '32.04',
        ]);
    }

    // ── PSKE-51: Pengujian Layar M-12 & Penanda Kecamatan Tanpa Cakupan ─

    public function test_pske_51_layar_m12_dapat_diakses_oleh_pengelola_instansi(): void
    {
        $pengelola = $this->userPengelola($this->instansi->id);

        $response = $this->actingAs($pengelola)->get(route('panel.instansi.gudang.cakupan.index', $this->gudang));

        $response->assertOk();
        $response->assertSee('Atur Cakupan Wilayah Gudang');
        $response->assertSee('Layar M-12');
        $response->assertSee($this->gudang->nama);
        $response->assertSee('Kecamatan Tanpa Cakupan Gudang');
    }

    public function test_pske_51_layar_m12_dapat_diakses_oleh_superadmin(): void
    {
        $superadmin = $this->userSuperadmin();

        $response = $this->actingAs($superadmin)->get(route('admin.gudang.cakupan.index', $this->gudang));

        $response->assertOk();
        $response->assertSee('Atur Cakupan Wilayah Gudang');
        $response->assertSee($this->gudang->nama);
    }

    public function test_pske_51_layar_m12_ditolak_untuk_petugas_dan_instansi_lain(): void
    {
        // Petugas instansi yang sama (bukan pengelola) ditolak 403
        $petugas = $this->userPetugas($this->instansi->id);
        $responsePetugas = $this->actingAs($petugas)->get(route('panel.instansi.gudang.cakupan.index', $this->gudang));
        $responsePetugas->assertForbidden();

        // Pengelola instansi lain ditolak 403
        $instansiLain = Instansi::create([
            'nama' => 'Bulog Cabang Lain',
            'alamat' => 'Jl. Lain',
            'latitude' => -6.9,
            'longitude' => 107.6,
            'aktif' => true,
        ]);
        $pengelolaLain = $this->userPengelola($instansiLain->id);
        $responsePengelolaLain = $this->actingAs($pengelolaLain)->get(route('panel.instansi.gudang.cakupan.index', $this->gudang));
        $responsePengelolaLain->assertForbidden();

        // Kepala wilayah ditolak 403
        $kepalaWilayah = $this->userKepalaWilayah();
        $responseKepala = $this->actingAs($kepalaWilayah)->get(route('panel.instansi.gudang.cakupan.index', $this->gudang));
        $responseKepala->assertForbidden();
    }

    public function test_pske_51_tambah_cakupan_via_layar_m12(): void
    {
        $pengelola = $this->userPengelola($this->instansi->id);

        $response = $this->actingAs($pengelola)->post(route('panel.instansi.gudang.cakupan.store', $this->gudang), [
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('sukses');

        $this->assertDatabaseHas('cakupan_gudang', [
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => 1,
        ]);
    }

    public function test_pske_51_toggle_status_utama_via_layar_m12(): void
    {
        $pengelola = $this->userPengelola($this->instansi->id);

        $cakupan = CakupanGudang::create([
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => false,
        ]);

        $response = $this->actingAs($pengelola)->patch(
            route('panel.instansi.gudang.cakupan.toggle-utama', [$this->gudang, $cakupan])
        );

        $response->assertRedirect();
        $response->assertSessionHas('sukses');
        $this->assertTrue($cakupan->fresh()->is_utama);

        // Toggle kembali ke false
        $response2 = $this->actingAs($pengelola)->patch(
            route('panel.instansi.gudang.cakupan.toggle-utama', [$this->gudang, $cakupan])
        );
        $response2->assertRedirect();
        $this->assertFalse($cakupan->fresh()->is_utama);
    }

    public function test_pske_51_hapus_cakupan_via_layar_m12(): void
    {
        $pengelola = $this->userPengelola($this->instansi->id);

        $cakupan = CakupanGudang::create([
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => true,
        ]);

        $response = $this->actingAs($pengelola)->delete(
            route('panel.instansi.gudang.cakupan.destroy', [$this->gudang, $cakupan])
        );

        $response->assertRedirect();
        $response->assertSessionHas('sukses');
        $this->assertDatabaseMissing('cakupan_gudang', ['id' => $cakupan->id]);
    }

    public function test_pske_51_kecamatan_tanpa_cakupan_ditandai_di_antarmuka(): void
    {
        $pengelola = $this->userPengelola($this->instansi->id);

        // Hanya kecamatan1 yang dicakup
        CakupanGudang::create([
            'gudang_id' => $this->gudang->id,
            'kode_kecamatan' => $this->kecamatan1->kode,
            'is_utama' => true,
        ]);

        // kecamatan2 (Pasirjambu) belum dicakup sama sekali oleh gudang mana pun
        $response = $this->actingAs($pengelola)->get(route('panel.instansi.gudang.cakupan.index', $this->gudang));

        $response->assertOk();
        // Pastikan kecamatan2 tampil pada bagian peringatan 'Kecamatan Tanpa Cakupan Gudang'
        $response->assertSee('Kecamatan Tanpa Cakupan Gudang');
        $response->assertSee('Pasirjambu');
        $response->assertSee('Tanpa Cakupan');
        $response->assertSee('+ Layani (Gudang Utama)');
    }
}
