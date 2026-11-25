<?php

namespace Tests\Feature\Stok;

use App\Models\Cadangan;
use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\Komoditas;
use App\Models\MutasiStok;
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

    public function test_estimasi_pske_60_membedakan_hari_stok_fisik_dan_jumlah_tersedia(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 12:00:00'));
        $user = $this->pengelola();
        $stok = $this->buatStok($this->gudangA, $this->beras, '300');
        $stok->update(['mulai_pencatatan_lengkap' => today()->subDays(29)]);
        $this->buatCadangan($stok, '60', Cadangan::STATUS_AKTIF, $user);

        foreach ([
            [MutasiStok::JENIS_PENYALURAN, '2026-10-27 00:00:00.000', 200],
            [MutasiStok::JENIS_PENYALURAN, '2026-11-25 11:59:59.999', 250],
            [MutasiStok::JENIS_PENYALURAN, '2026-10-26 23:59:59.999', 20],
            [MutasiStok::JENIS_PENYALURAN, '2026-11-26 00:00:00.000', 20],
            [MutasiStok::JENIS_RETUR, '2026-11-24', 20],
            [MutasiStok::JENIS_RUSAK, '2026-11-24', 20],
            [MutasiStok::JENIS_PENYESUAIAN, '2026-11-24', 20],
        ] as [$jenis, $waktu, $jumlah]) {
            $stok->mutasi()->create([
                'jenis' => $jenis, 'waktu' => $waktu, 'jumlah' => $jumlah,
                'saldo_setelah' => 300, 'pencatat_id' => $user->id, 'ref_operasi' => $jenis.$waktu,
            ]);
        }

        $respons = $this->actingAs($user)->get(route('panel.instansi.stok.index'))->assertOk()
            ->assertSeeInOrder(['300', '60', '240', '15', '20', '2026-12-15', 'Waspada']);
        $hasil = $respons->viewData('estimasi')[$stok->id];
        $this->assertSame(240.0, $hasil['stok_tersedia']);
        $this->assertSame(15.0, $hasil['pemakaian_harian']);
        $this->assertSame(20.0, $hasil['hari']);
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

    public function test_stok_positif_tanpa_penggunaan_menghasilkan_estimasi_json_dengan_hari_null(): void
    {
        $stok = $this->buatStok($this->gudangA, $this->beras, '300');
        $stok->update(['mulai_pencatatan_lengkap' => today()->subDays(29)]);
        $respons = $this->actingAs($this->pengelola())->get(route('panel.instansi.stok.index'))
            ->assertOk()->assertSee('Tidak ada penyaluran dalam 30 hari terakhir.');
        $hasil = json_decode($respons->viewData('estimasi')->toJson(JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNull($hasil[$stok->id]['hari']);
        $this->assertNull($hasil[$stok->id]['perkiraan_habis']);
        $this->assertSame('AMAN', $hasil[$stok->id]['status']);
    }

    public function test_filter_gudang_instansi_lain_tidak_membocorkan_data(): void
    {
        $this->buatStok($this->gudangLain, $this->minyak, '20');

        $respons = $this->actingAs($this->pengelola())
            ->get(route('panel.instansi.stok.index', ['gudang' => $this->gudangLain->id]))
            ->assertOk()
            ->assertSee('Tidak ada stok yang sesuai filter.');
        $this->assertSame(0, $respons->viewData('stok')->total());
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

    public function test_filter_gudang_dan_komoditas(): void
    {
        $this->buatStok($this->gudangA, $this->beras, '10');
        $this->buatStok($this->gudangA, $this->minyak, '20');
        $this->buatStok($this->gudangB, $this->beras, '30');
        $user = $this->pengelola();

        $perGudang = $this->actingAs($user)->get(route('panel.instansi.stok.index', ['gudang' => $this->gudangB->id]));
        $this->assertSame(1, $perGudang->viewData('stok')->total());

        $perKomoditas = $this->actingAs($user)->get(route('panel.instansi.stok.index', ['komoditas' => $this->beras->id]));
        $this->assertSame(2, $perKomoditas->viewData('stok')->total());

        $keduanya = $this->actingAs($user)->get(route('panel.instansi.stok.index', ['gudang' => $this->gudangA->id, 'komoditas' => $this->minyak->id]));
        $this->assertSame(1, $keduanya->viewData('stok')->total());
        $this->assertSame($this->minyak->id, $keduanya->viewData('stok')->first()->komoditas_id);
    }

    public function test_filter_bukan_angka_ditolak(): void
    {
        $this->actingAs($this->pengelola())
            ->get(route('panel.instansi.stok.index', ['gudang' => 'abc']))
            ->assertSessionHasErrors('gudang');
    }

    public function test_paginasi_50_baris_dan_filter_ikut_ke_halaman_berikutnya(): void
    {
        for ($i = 1; $i <= 51; $i++) {
            $komoditas = Komoditas::create(['nama' => sprintf('Komoditas %02d', $i), 'satuan' => 'kg']);
            $this->buatStok($this->gudangA, $komoditas, (string) $i);
        }

        $user = $this->pengelola();
        $halaman1 = $this->actingAs($user)->get(route('panel.instansi.stok.index', ['gudang' => $this->gudangA->id]));
        $paginator = $halaman1->viewData('stok');

        $this->assertSame(50, $paginator->count());
        $this->assertSame(51, $paginator->total());
        $this->assertStringContainsString('gudang='.$this->gudangA->id, $paginator->nextPageUrl());

        $halaman2 = $this->actingAs($user)->get($paginator->nextPageUrl());
        $this->assertSame(1, $halaman2->viewData('stok')->count());
    }

    public function test_waktu_pembaruan_ditampilkan(): void
    {
        $this->buatStok($this->gudangA, $this->beras, '10', '2026-11-20 08:30:00');
        $this->buatStok($this->gudangB, $this->beras, '10', '2026-11-22 14:05:00');
        $this->buatStok($this->gudangB, $this->minyak, '10', null);

        $this->actingAs($this->pengelola())->get(route('panel.instansi.stok.index'))
            ->assertOk()
            ->assertSee('Terakhir diperbarui')
            ->assertSee('22 Nov 2026 14:05')
            ->assertSee('Belum ada mutasi');
    }

    public function test_menu_daftar_stok_ada_di_sidebar_badan_resmi(): void
    {
        $this->actingAs($this->pengelola())->get(route('panel.instansi'))
            ->assertOk()
            ->assertSee(route('panel.instansi.stok.index'), false);
    }
}
