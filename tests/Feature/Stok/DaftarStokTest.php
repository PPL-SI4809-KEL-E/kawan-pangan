<?php

namespace Tests\Feature\Stok;

use App\Models\Cadangan;
use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\Komoditas;
use App\Models\Stok;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PBI-13 · Layar M-11 daftar stok: kolom stok, filter, paginasi, dan batas akses instansi.
 */
class DaftarStokTest extends TestCase
{
    use RefreshDatabase;

    private Instansi $instansi;

    private Instansi $instansiLain;

    private Gudang $gudangA;

    private Gudang $gudangB;

    private Gudang $gudangLain;

    private Komoditas $beras;

    private Komoditas $minyak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Wilayah::create(['kode' => '32', 'nama' => 'Jawa Barat']);
        Wilayah::create(['kode' => '32.04', 'nama' => 'Kab. Bandung', 'kode_induk' => '32']);
        Wilayah::create(['kode' => '32.04.01', 'nama' => 'Soreang', 'kode_induk' => '32.04']);

        $this->instansi = $this->buatInstansi('Dinas Ketahanan Pangan Kab. Bandung');
        $this->instansiLain = $this->buatInstansi('Perum Bulog Kanwil Jawa Barat');

        $this->gudangA = $this->buatGudang('Gudang Soreang', $this->instansi);
        $this->gudangB = $this->buatGudang('Gudang Banjaran', $this->instansi);
        $this->gudangLain = $this->buatGudang('Gudang Bulog Gedebage', $this->instansiLain);

        $this->beras = Komoditas::create(['nama' => 'Beras', 'satuan' => 'kg']);
        $this->minyak = Komoditas::create(['nama' => 'Minyak goreng', 'satuan' => 'liter']);
    }

    private function buatInstansi(string $nama): Instansi
    {
        return Instansi::create([
            'nama' => $nama,
            'alamat' => 'Jl. Contoh No. 1',
            'latitude' => -7.03,
            'longitude' => 107.51,
            'aktif' => true,
        ]);
    }

    private function buatGudang(string $nama, Instansi $instansi): Gudang
    {
        return Gudang::create([
            'nama' => $nama,
            'instansi_id' => $instansi->id,
            'kode_wilayah' => '32.04.01',
            'alamat' => 'Kawasan gudang',
            'latitude' => -7.03,
            'longitude' => 107.51,
            'aktif' => true,
        ]);
    }

    private function buatStok(Gudang $gudang, Komoditas $komoditas, string $fisik, ?string $diperbarui = '2026-11-20 08:30:00'): Stok
    {
        return Stok::create([
            'gudang_id' => $gudang->id,
            'komoditas_id' => $komoditas->id,
            'jumlah_fisik_layak' => $fisik,
            'diperbarui_pada' => $diperbarui,
        ]);
    }

    private function buatCadangan(Stok $stok, string $jumlah, string $status, User $petugas): void
    {
        $pengirimanId = DB::table('pengiriman')->insertGetId([
            'no_resi' => 'RESI-UJI-'.uniqid(),
            'gudang_asal_id' => $stok->gudang_id,
            'kode_wilayah_tujuan' => '32.04.01',
            'petugas_id' => $petugas->id,
            'status' => 'DIRENCANAKAN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cadangan::create([
            'stok_id' => $stok->id,
            'pengiriman_id' => $pengirimanId,
            'jumlah' => $jumlah,
            'status' => $status,
            'dibuat_pada' => Carbon::now(),
        ]);
    }

    private function pengelola(?int $instansiId = null): User
    {
        return User::factory()->badanResmi($instansiId ?? $this->instansi->id)->create();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('panel.instansi.stok.index'))->assertRedirect(route('login'));
    }

    public function test_kepala_wilayah_ditolak(): void
    {
        $this->actingAs(User::factory()->kepalaWilayah('32.04.01')->create())
            ->get(route('panel.instansi.stok.index'))
            ->assertForbidden();
    }

    public function test_kolom_stok_fisik_cadangan_aktif_dan_tersedia(): void
    {
        $user = $this->pengelola();
        $stok = $this->buatStok($this->gudangA, $this->beras, '1250.500');
        $this->buatCadangan($stok, '200.000', Cadangan::STATUS_AKTIF, $user);
        $this->buatCadangan($stok, '50.000', Cadangan::STATUS_AKTIF, $user);
        // Cadangan yang sudah selesai atau batal tidak mengurangi stok tersedia.
        $this->buatCadangan($stok, '999.000', Cadangan::STATUS_SELESAI, $user);
        $this->buatCadangan($stok, '888.000', Cadangan::STATUS_BATAL, $user);

        $this->actingAs($user)->get(route('panel.instansi.stok.index'))
            ->assertOk()
            ->assertSeeInOrder(['Komoditas', 'Gudang', 'Satuan', 'Stok fisik', 'Cadangan', 'Tersedia',
                'Penggunaan harian', 'Hari tersisa', 'Perkiraan habis', 'Status'])
            ->assertSeeInOrder(['Beras', 'Gudang Soreang', 'kg', '1.250,5', '250', '1.000,5'])
            ->assertDontSee('999')
            ->assertDontSee('888');
    }

    public function test_tersedia_tidak_pernah_negatif(): void
    {
        $user = $this->pengelola();
        $stok = $this->buatStok($this->gudangA, $this->beras, '100');
        $this->buatCadangan($stok, '150.000', Cadangan::STATUS_AKTIF, $user);

        $respons = $this->actingAs($user)->get(route('panel.instansi.stok.index'))->assertOk();
        $baris = $respons->viewData('stok')->first();
        $this->assertSame(0.0, $baris->tersedia);
    }

    public function test_hanya_stok_gudang_instansi_sendiri_yang_tampil(): void
    {
        $this->buatStok($this->gudangA, $this->beras, '10');
        $this->buatStok($this->gudangLain, $this->minyak, '20');

        $this->actingAs($this->pengelola())->get(route('panel.instansi.stok.index'))
            ->assertOk()
            ->assertSee('Gudang Soreang')
            ->assertDontSee('Gudang Bulog Gedebage');
    }

    public function test_akun_tanpa_instansi_melihat_pesan_bukan_data(): void
    {
        $this->buatStok($this->gudangA, $this->beras, '10');
        $user = User::factory()->badanResmi()->create();
        $user->forceFill(['instansi_id' => null])->save();

        $this->actingAs($user)->get(route('panel.instansi.stok.index'))
            ->assertOk()
            ->assertSee('belum ditugaskan ke instansi')
            ->assertDontSee('Gudang Soreang');
    }

    public function test_menu_daftar_stok_ada_di_sidebar_badan_resmi(): void
    {
        $this->actingAs($this->pengelola())->get(route('panel.instansi'))
            ->assertOk()
            ->assertSee(route('panel.instansi.stok.index'), false);
    }
}
