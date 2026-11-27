<?php

namespace Tests\Feature\Stok;

use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\Komoditas;
use App\Models\MutasiStok;
use App\Models\Stok;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PSKE-107 · Form catat mutasi stok oleh pengelola instansi.
 */
class MutasiStokFormTest extends TestCase
{
    use RefreshDatabase;

    private Instansi $instansiA;
    private Gudang $gudangA;
    private Gudang $gudangB;
    private Komoditas $beras;
    private User $pengelola;

    protected function setUp(): void
    {
        parent::setUp();
        Wilayah::create(['kode' => '32', 'nama' => 'Jawa Barat']);
        Wilayah::create(['kode' => '32.04', 'nama' => 'Kabupaten Bandung', 'kode_induk' => '32']);
        Wilayah::create(['kode' => '32.04.01', 'nama' => 'Soreang', 'kode_induk' => '32.04']);

        $this->instansiA = Instansi::create(['nama' => 'Instansi A', 'alamat' => 'Jl. A', 'latitude' => -7, 'longitude' => 107, 'aktif' => true]);
        $instansiB = Instansi::create(['nama' => 'Instansi B', 'alamat' => 'Jl. B', 'latitude' => -7, 'longitude' => 107, 'aktif' => true]);
        $this->gudangA = Gudang::create(['nama' => 'Gudang Soreang', 'instansi_id' => $this->instansiA->id, 'kode_wilayah' => '32.04.01', 'alamat' => 'x', 'latitude' => -7, 'longitude' => 107, 'aktif' => true]);
        $this->gudangB = Gudang::create(['nama' => 'Gudang Lain', 'instansi_id' => $instansiB->id, 'kode_wilayah' => '32.04.01', 'alamat' => 'y', 'latitude' => -7, 'longitude' => 107, 'aktif' => true]);
        $this->beras = Komoditas::forceCreate(['nama' => 'Beras Medium', 'nama_unik' => 'beras medium', 'satuan' => 'kg']);
        $this->pengelola = User::factory()->pengelola($this->instansiA->id)->create();
    }

    private function kirim(array $ubah = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->pengelola)->post(route('panel.instansi.stok.mutasi.store'), array_merge([
            'gudang_id' => $this->gudangA->id,
            'komoditas_id' => $this->beras->id,
            'jenis' => 'PENERIMAAN',
            'jumlah' => '1000',
            'keterangan' => 'Kiriman gudang induk',
            'ref_operasi' => 'form-uji-'.uniqid(),
        ], $ubah));
    }

    private function saldo(): string
    {
        return (string) Stok::where('gudang_id', $this->gudangA->id)->where('komoditas_id', $this->beras->id)->value('jumlah_fisik_layak');
    }

    public function test_pengelola_bisa_membuka_form_dan_melihat_gudang_sendiri_saja(): void
    {
        $this->actingAs($this->pengelola)->get(route('panel.instansi.stok.mutasi.create'))
            ->assertOk()
            ->assertSee('Catat mutasi stok')
            ->assertSee('Gudang Soreang')
            ->assertDontSee('Gudang Lain')
            ->assertSee('name="ref_operasi"', false);
    }

    public function test_daftar_stok_menampilkan_tombol_catat_mutasi_hanya_untuk_pengelola(): void
    {
        $this->actingAs($this->pengelola)->get(route('panel.instansi.stok.index'))->assertSee('Catat mutasi');
        $petugas = User::factory()->petugas($this->instansiA->id)->create();
        $this->actingAs($petugas)->get(route('panel.instansi.stok.index'))->assertDontSee('Catat mutasi');
    }

    public function test_petugas_biasa_ditolak(): void
    {
        $petugas = User::factory()->petugas($this->instansiA->id)->create();
        $this->actingAs($petugas)->get(route('panel.instansi.stok.mutasi.create'))->assertForbidden();
        $this->kirim([], $petugas)->assertForbidden();
        $this->assertSame(0, MutasiStok::count());
    }

    public function test_gudang_instansi_lain_ditolak(): void
    {
        $this->kirim(['gudang_id' => $this->gudangB->id])->assertSessionHasErrors('gudang_id');
        $this->assertSame(0, MutasiStok::count());
    }

    public function test_barang_masuk_menambah_saldo_dan_mencatat_mutasi(): void
    {
        $this->kirim()->assertRedirect(route('panel.instansi.stok.index', ['gudang' => $this->gudangA->id]))
            ->assertSessionHas('sukses', 'Barang masuk tercatat. Stok Beras Medium di Gudang Soreang sekarang 1.000 kg.');
        $this->assertSame('1000.000', $this->saldo());
        $this->assertDatabaseHas('mutasi_stok', ['jenis' => 'PENERIMAAN', 'jumlah' => '1000.000', 'pencatat_id' => $this->pengelola->id, 'keterangan' => 'Kiriman gudang induk']);
    }

    public function test_penyaluran_dan_rusak_mengurangi_saldo(): void
    {
        $this->kirim();
        $this->kirim(['jenis' => 'PENYALURAN', 'jumlah' => '250.5'])->assertSessionHasNoErrors();
        $this->kirim(['jenis' => 'RUSAK', 'jumlah' => '49.5'])->assertSessionHasNoErrors();
        $this->assertSame('700.000', $this->saldo());
    }

    public function test_penyaluran_melebihi_stok_ditolak_tanpa_mengubah_saldo(): void
    {
        $this->kirim(['jumlah' => '100']);
        $this->kirim(['jenis' => 'PENYALURAN', 'jumlah' => '150'])->assertSessionHasErrors('jumlah');
        $this->assertSame('100.000', $this->saldo());
    }

    public function test_penyaluran_sebelum_ada_stok_ditolak_dengan_pesan_jelas(): void
    {
        $this->kirim(['jenis' => 'PENYALURAN', 'jumlah' => '10'])->assertSessionHasErrors('komoditas_id');
        $this->assertSame(0, Stok::count());
    }

    public function test_stock_opname_mengisi_saldo_baru_bukan_selisih(): void
    {
        $this->kirim(['jumlah' => '1000']);
        $this->kirim(['jenis' => 'PENYESUAIAN', 'jumlah' => '980'])->assertSessionHasNoErrors();
        $this->assertSame('980.000', $this->saldo());
        $this->assertDatabaseHas('mutasi_stok', ['jenis' => 'PENYESUAIAN', 'jumlah' => '20.000', 'saldo_setelah' => '980.000']);
    }

    public function test_stock_opname_tanpa_perubahan_ditolak(): void
    {
        $this->kirim(['jumlah' => '1000']);
        $this->kirim(['jenis' => 'PENYESUAIAN', 'jumlah' => '1000'])->assertSessionHasErrors('jumlah');
    }

    public function test_kirim_dua_kali_tidak_menggandakan_mutasi(): void
    {
        $data = ['ref_operasi' => 'form-klik-ganda'];
        $this->kirim($data);
        $this->kirim($data)->assertSessionHas('sukses', 'Mutasi ini sudah tercatat sebelumnya.');
        $this->assertSame(1, MutasiStok::count());
        $this->assertSame('1000.000', $this->saldo());
    }

    public function test_jumlah_nol_negatif_dan_lebih_dari_tiga_desimal_ditolak(): void
    {
        $this->kirim(['jumlah' => '0'])->assertSessionHasErrors('jumlah');
        $this->kirim(['jumlah' => '-5'])->assertSessionHasErrors('jumlah');
        $this->kirim(['jumlah' => '1.2345'])->assertSessionHasErrors('jumlah');
        $this->assertSame(0, MutasiStok::count());
    }
}
