<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatusAkun;
use App\Models\Cadangan;
use App\Models\Gudang;
use App\Models\Instansi;
use App\Models\Komoditas;
use App\Models\MutasiStok;
use App\Models\Stok;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * PSKE-29 · Seeder deterministik data simulasi 60 hari.
 *
 * Aman dijalankan berulang (idempotent) dan menandai seluruh baris simulasi
 * dengan `is_contoh = true` (PSKE-30). Menghasilkan tiga skenario wilayah:
 * - Kab. Bandung (32.04)       : AMAN (45 hari > 30)
 * - Kota Bandung (32.73)       : WASPADA (21 hari, berada di 14–30)
 * - Kab. Bandung Barat (32.17) : KRITIS (9 hari < 14)
 */
class DataSimulasiSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $hariIni = Carbon::today();
            $mulaiPencatatan = $hariIni->copy()->subDays(59)->toDateString();

            // 1. Wilayah Contoh (4 tingkat Kemendagri: PP, PP.KK, PP.KK.CC, PP.KK.CC.DDDD)
            $daftarWilayah = [
                ['kode' => '32', 'nama' => 'Jawa Barat', 'kode_induk' => null, 'jumlah_penduduk' => 49900000, 'lat' => '-6.9174639', 'lng' => '107.6191228'],
                ['kode' => '32.04', 'nama' => 'Kab. Bandung', 'kode_induk' => '32', 'jumlah_penduduk' => 3720000, 'lat' => '-7.0252000', 'lng' => '107.5197000'],
                ['kode' => '32.73', 'nama' => 'Kota Bandung', 'kode_induk' => '32', 'jumlah_penduduk' => 2500000, 'lat' => '-6.9175000', 'lng' => '107.6191000'],
                ['kode' => '32.17', 'nama' => 'Kab. Bandung Barat', 'kode_induk' => '32', 'jumlah_penduduk' => 1850000, 'lat' => '-6.8406000', 'lng' => '107.5123000'],
                ['kode' => '32.04.12', 'nama' => 'Dayeuhkolot', 'kode_induk' => '32.04', 'jumlah_penduduk' => 115000, 'lat' => '-6.9881000', 'lng' => '107.6263000'],
                ['kode' => '32.04.13', 'nama' => 'Bojongsoang', 'kode_induk' => '32.04', 'jumlah_penduduk' => 112000, 'lat' => '-6.9814000', 'lng' => '107.6542000'],
                ['kode' => '32.73.01', 'nama' => 'Sukasari', 'kode_induk' => '32.73', 'jumlah_penduduk' => 82000, 'lat' => '-6.8722000', 'lng' => '107.5855000'],
                ['kode' => '32.17.01', 'nama' => 'Lembang', 'kode_induk' => '32.17', 'jumlah_penduduk' => 198000, 'lat' => '-6.8117000', 'lng' => '107.6175000'],
                ['kode' => '32.04.12.2001', 'nama' => 'Citeureup', 'kode_induk' => '32.04.12', 'jumlah_penduduk' => 18500, 'lat' => '-6.9830000', 'lng' => '107.6240000'],
                ['kode' => '32.04.12.2002', 'nama' => 'Sukapura', 'kode_induk' => '32.04.12', 'jumlah_penduduk' => 14200, 'lat' => '-6.9720000', 'lng' => '107.6310000'],
                ['kode' => '32.04.13.2001', 'nama' => 'Lengkong', 'kode_induk' => '32.04.13', 'jumlah_penduduk' => 16800, 'lat' => '-6.9760000', 'lng' => '107.6490000'],
                ['kode' => '32.73.01.1001', 'nama' => 'Gegerkalong', 'kode_induk' => '32.73.01', 'jumlah_penduduk' => 19400, 'lat' => '-6.8680000', 'lng' => '107.5910000'],
                ['kode' => '32.17.01.2001', 'nama' => 'Jayagiri', 'kode_induk' => '32.17.01', 'jumlah_penduduk' => 21300, 'lat' => '-6.8040000', 'lng' => '107.6160000'],
            ];

            foreach ($daftarWilayah as $item) {
                $wilayah = Wilayah::firstOrNew(['kode' => $item['kode']]);
                $wilayah->fill([
                    'kode' => $item['kode'],
                    'nama' => $item['nama'],
                    'kode_induk' => $item['kode_induk'],
                    'jumlah_penduduk' => $item['jumlah_penduduk'],
                ]);
                $wilayah->forceFill([
                    'lat' => $item['lat'],
                    'lng' => $item['lng'],
                    'is_contoh' => true,
                ])->save();
            }

            // 2. Instansi Contoh
            $daftarInstansi = [
                'bulog_jabar' => [
                    'nama' => 'Perum Bulog Kanwil Jawa Barat',
                    'jenis' => 'BUMN Pangan',
                    'alamat' => 'Jl. Soekarno-Hatta No. 711A, Kota Bandung',
                    'telepon' => '022-7303094',
                    'latitude' => '-6.9389000',
                    'longitude' => '107.6684000',
                ],
                'dkpp_kab_bdg' => [
                    'nama' => 'Dinas Ketahanan Pangan dan Pertanian Kab. Bandung',
                    'jenis' => 'Dinas Daerah',
                    'alamat' => 'Jl. Raya Soreang KM 17, Kab. Bandung',
                    'telepon' => '022-5891691',
                    'latitude' => '-7.0252000',
                    'longitude' => '107.5197000',
                ],
                'dkpp_kota_bdg' => [
                    'nama' => 'Dinas Ketahanan Pangan dan Pertanian Kota Bandung',
                    'jenis' => 'Dinas Daerah',
                    'alamat' => 'Jl. Arjuna No. 45, Kota Bandung',
                    'telepon' => '022-6015102',
                    'latitude' => '-6.9126000',
                    'longitude' => '107.5928000',
                ],
            ];

            $instansiMap = [];
            foreach ($daftarInstansi as $kunci => $item) {
                $instansi = Instansi::firstOrNew(['nama' => $item['nama']]);
                $instansi->fill([
                    'nama' => $item['nama'],
                    'alamat' => $item['alamat'],
                    'latitude' => $item['latitude'],
                    'longitude' => $item['longitude'],
                ]);
                $instansi->forceFill([
                    'jenis' => $item['jenis'],
                    'telepon' => $item['telepon'],
                    'aktif' => true,
                    'is_contoh' => true,
                ])->save();
                $instansiMap[$kunci] = $instansi;
            }

            // 3. Akun Petugas Simulasi (pencatat mutasi & petugas pengiriman contoh; Superadmin terpisah di PSKE-16)
            $petugasSimulasi = User::firstOrNew(['email' => 'petugas.simulasi@kawanpangan.id']);
            $petugasSimulasi->forceFill([
                'name' => 'Petugas Gudang Simulasi',
                'email' => 'petugas.simulasi@kawanpangan.id',
                'password' => Hash::make('SimulasiPangan123'),
                'role' => Role::BadanResmi,
                'no_hp' => '081200001111',
                'kode_wilayah' => null,
                'instansi_id' => $instansiMap['bulog_jabar']->id,
                'status' => StatusAkun::Aktif,
                'is_contoh' => true,
            ])->save();

            // 4. Komoditas Contoh
            $daftarKomoditas = [
                'beras' => ['nama' => 'Beras Medium', 'satuan' => 'kg', 'kebutuhan' => '0.250'],
                'jagung' => ['nama' => 'Jagung Pipilan', 'satuan' => 'kg', 'kebutuhan' => '0.050'],
                'minyak' => ['nama' => 'Minyak Goreng', 'satuan' => 'liter', 'kebutuhan' => '0.035'],
                'gula' => ['nama' => 'Gula Pasir', 'satuan' => 'kg', 'kebutuhan' => '0.030'],
            ];

            $komoditasMap = [];
            foreach ($daftarKomoditas as $kunci => $item) {
                $namaUnik = mb_strtolower(Komoditas::rapikanNama($item['nama']), 'UTF-8');
                $komoditas = Komoditas::where('nama_unik', $namaUnik)->first() ?? new Komoditas;
                $komoditas->fill([
                    'nama' => $item['nama'],
                    'satuan' => $item['satuan'],
                ]);
                $komoditas->forceFill([
                    'kebutuhan_per_kapita_harian' => $item['kebutuhan'],
                    'aktif' => true,
                    'is_contoh' => true,
                ])->save();
                $komoditasMap[$kunci] = $komoditas;
            }

            // 5. Gudang & Cakupan Gudang Contoh
            $daftarGudang = [
                'aman' => [
                    'kode_gudang' => 'GDG-BDG-01',
                    'nama' => 'Gudang Bulog Dayeuhkolot',
                    'instansi_id' => $instansiMap['bulog_jabar']->id,
                    'kode_wilayah' => '32.04.12',
                    'alamat' => 'Jl. Raya Dayeuhkolot No. 120, Kab. Bandung',
                    'latitude' => '-6.9881000',
                    'longitude' => '107.6263000',
                    'radius_km' => '15.00',
                    'cakupan' => [
                        ['kode_kecamatan' => '32.04.12', 'is_utama' => true],
                        ['kode_kecamatan' => '32.04.13', 'is_utama' => false],
                    ],
                ],
                'waspada' => [
                    'kode_gudang' => 'GDG-KBDG-01',
                    'nama' => 'Gudang Pangan Sukasari',
                    'instansi_id' => $instansiMap['dkpp_kota_bdg']->id,
                    'kode_wilayah' => '32.73.01',
                    'alamat' => 'Jl. Setiabudi No. 190, Kota Bandung',
                    'latitude' => '-6.8722000',
                    'longitude' => '107.5855000',
                    'radius_km' => '10.00',
                    'cakupan' => [
                        ['kode_kecamatan' => '32.73.01', 'is_utama' => true],
                    ],
                ],
                'kritis' => [
                    'kode_gudang' => 'GDG-KBB-01',
                    'nama' => 'Gudang Pangan Lembang',
                    'instansi_id' => $instansiMap['bulog_jabar']->id,
                    'kode_wilayah' => '32.17.01',
                    'alamat' => 'Jl. Raya Lembang No. 88, Kab. Bandung Barat',
                    'latitude' => '-6.8117000',
                    'longitude' => '107.6175000',
                    'radius_km' => '12.50',
                    'cakupan' => [
                        ['kode_kecamatan' => '32.17.01', 'is_utama' => true],
                    ],
                ],
            ];

            $gudangMap = [];
            foreach ($daftarGudang as $kunci => $item) {
                $gudang = Gudang::where('kode_gudang', $item['kode_gudang'])->first()
                    ?? Gudang::where('nama', $item['nama'])->where('kode_wilayah', $item['kode_wilayah'])->first()
                    ?? new Gudang;

                $gudang->fill([
                    'nama' => $item['nama'],
                    'instansi_id' => $item['instansi_id'],
                    'kode_wilayah' => $item['kode_wilayah'],
                    'alamat' => $item['alamat'],
                    'latitude' => $item['latitude'],
                    'longitude' => $item['longitude'],
                    'aktif' => true,
                ]);
                $gudang->forceFill([
                    'kode_gudang' => $item['kode_gudang'],
                    'radius_km' => $item['radius_km'],
                    'is_contoh' => true,
                ])->save();

                foreach ($item['cakupan'] as $cakupan) {
                    DB::table('cakupan_gudang')->updateOrInsert(
                        [
                            'gudang_id' => $gudang->id,
                            'kode_kecamatan' => $cakupan['kode_kecamatan'],
                        ],
                        [
                            'is_utama' => $cakupan['is_utama'],
                            'updated_at' => $hariIni,
                            'created_at' => $hariIni,
                        ]
                    );
                }

                $gudangMap[$kunci] = $gudang;
            }

            // 6. Skenario Stok, Pengiriman, Cadangan Aktif, & Riwayat Mutasi 60 Hari
            // Rumus:
            // - Penyaluran 60 hari: 100 kg/hari -> rata-rata 30 hari = 100 kg/hari
            // - Mutasi non-penyaluran (H-20 RETUR +20, H-15 RUSAK -30, H-10 PENYESUAIAN -40) = net -50 kg
            // - Total pengurangan 60 hari = 6050 kg
            $skenarioBeras = [
                'aman' => [
                    'gudang' => $gudangMap['aman'],
                    'wilayah_tujuan' => '32.04.12.2001',
                    'no_resi' => 'RESI-SIM-BDG-001',
                    'penerimaan_awal' => 10650.000,
                    'penyaluran_harian' => 100.000,
                    'cadangan_aktif' => 100.000,
                    // Saldo fisik akhir = 10650 - 6050 = 4600 kg
                    // Tersedia = 4600 - 100 = 4500 kg -> Ketahanan = 4500 / 100 = 45 hari (AMAN > 30)
                ],
                'waspada' => [
                    'gudang' => $gudangMap['waspada'],
                    'wilayah_tujuan' => '32.73.01.1001',
                    'no_resi' => 'RESI-SIM-KBDG-001',
                    'penerimaan_awal' => 8200.000,
                    'penyaluran_harian' => 100.000,
                    'cadangan_aktif' => 50.000,
                    // Saldo fisik akhir = 8200 - 6050 = 2150 kg
                    // Tersedia = 2150 - 50 = 2100 kg -> Ketahanan = 2100 / 100 = 21 hari (WASPADA 14..30)
                ],
                'kritis' => [
                    'gudang' => $gudangMap['kritis'],
                    'wilayah_tujuan' => '32.17.01.2001',
                    'no_resi' => 'RESI-SIM-KBB-001',
                    'penerimaan_awal' => 7000.000,
                    'penyaluran_harian' => 100.000,
                    'cadangan_aktif' => 50.000,
                    // Saldo fisik akhir = 7000 - 6050 = 950 kg
                    // Tersedia = 950 - 50 = 900 kg -> Ketahanan = 900 / 100 = 9 hari (KRITIS < 14)
                ],
            ];

            foreach ($skenarioBeras as $kunci => $cfg) {
                $gudang = $cfg['gudang'];
                $kodeGudang = $gudang->kode_gudang;

                $stok = Stok::updateOrCreate(
                    [
                        'gudang_id' => $gudang->id,
                        'komoditas_id' => $komoditasMap['beras']->id,
                    ],
                    [
                        'jumlah_fisik_layak' => $cfg['penerimaan_awal'],
                        'mulai_pencatatan_lengkap' => $mulaiPencatatan,
                        'diperbarui_pada' => $hariIni->copy()->setTime(16, 0, 0),
                        'is_contoh' => true,
                    ]
                );

                $saldo = 0.0;

                // H-59 Penerimaan awal
                $waktuAwal = $hariIni->copy()->subDays(59)->setTime(8, 0, 0);
                $saldo += $cfg['penerimaan_awal'];
                MutasiStok::updateOrCreate(
                    ['ref_operasi' => "SIM-{$kodeGudang}-BERAS-H59-AWAL"],
                    [
                        'stok_id' => $stok->id,
                        'jenis' => MutasiStok::JENIS_PENERIMAAN,
                        'jumlah' => number_format($cfg['penerimaan_awal'], 3, '.', ''),
                        'saldo_setelah' => number_format($saldo, 3, '.', ''),
                        'waktu' => $waktuAwal->format('Y-m-d H:i:s.000'),
                        'pengiriman_id' => null,
                        'pencatat_id' => $petugasSimulasi->id,
                        'keterangan' => 'Penerimaan stok awal simulasi 60 hari',
                        'is_contoh' => true,
                    ]
                );

                // Loop 60 hari: H-59 s.d. H-0
                for ($hari = 59; $hari >= 0; $hari--) {
                    $tanggalHari = $hariIni->copy()->subDays($hari);
                    $saldo -= $cfg['penyaluran_harian'];

                    MutasiStok::updateOrCreate(
                        ['ref_operasi' => sprintf('SIM-%s-BERAS-H%02d-SALUR', $kodeGudang, $hari)],
                        [
                            'stok_id' => $stok->id,
                            'jenis' => MutasiStok::JENIS_PENYALURAN,
                            'jumlah' => number_format($cfg['penyaluran_harian'], 3, '.', ''),
                            'saldo_setelah' => number_format($saldo, 3, '.', ''),
                            'waktu' => $tanggalHari->copy()->setTime(10, 0, 0)->format('Y-m-d H:i:s.000'),
                            'pengiriman_id' => null,
                            'pencatat_id' => $petugasSimulasi->id,
                            'keterangan' => 'Penyaluran harian rutin simulasi',
                            'is_contoh' => true,
                        ]
                    );

                    // Mutasi non-penyaluran (tidak dihitung dalam rata-rata pemakaian harian)
                    if ($hari === 20) {
                        $saldo += 20.000;
                        MutasiStok::updateOrCreate(
                            ['ref_operasi' => "SIM-{$kodeGudang}-BERAS-H20-RETUR"],
                            [
                                'stok_id' => $stok->id,
                                'jenis' => MutasiStok::JENIS_RETUR,
                                'jumlah' => '20.000',
                                'saldo_setelah' => number_format($saldo, 3, '.', ''),
                                'waktu' => $tanggalHari->copy()->setTime(14, 0, 0)->format('Y-m-d H:i:s.000'),
                                'pengiriman_id' => null,
                                'pencatat_id' => $petugasSimulasi->id,
                                'keterangan' => 'Retur kelebihan kemasan utuh',
                                'is_contoh' => true,
                            ]
                        );
                    } elseif ($hari === 15) {
                        $saldo -= 30.000;
                        MutasiStok::updateOrCreate(
                            ['ref_operasi' => "SIM-{$kodeGudang}-BERAS-H15-RUSAK"],
                            [
                                'stok_id' => $stok->id,
                                'jenis' => MutasiStok::JENIS_RUSAK,
                                'jumlah' => '30.000',
                                'saldo_setelah' => number_format($saldo, 3, '.', ''),
                                'waktu' => $tanggalHari->copy()->setTime(14, 0, 0)->format('Y-m-d H:i:s.000'),
                                'pengiriman_id' => null,
                                'pencatat_id' => $petugasSimulasi->id,
                                'keterangan' => 'Beras lembap dikeluarkan dari stok layak',
                                'is_contoh' => true,
                            ]
                        );
                    } elseif ($hari === 10) {
                        $saldo -= 40.000;
                        MutasiStok::updateOrCreate(
                            ['ref_operasi' => "SIM-{$kodeGudang}-BERAS-H10-SESUAI"],
                            [
                                'stok_id' => $stok->id,
                                'jenis' => MutasiStok::JENIS_PENYESUAIAN,
                                'jumlah' => '40.000',
                                'saldo_setelah' => number_format($saldo, 3, '.', ''),
                                'waktu' => $tanggalHari->copy()->setTime(14, 0, 0)->format('Y-m-d H:i:s.000'),
                                'pengiriman_id' => null,
                                'pencatat_id' => $petugasSimulasi->id,
                                'keterangan' => 'Penyesuaian stok opname bulanan',
                                'is_contoh' => true,
                            ]
                        );
                    }
                }

                // Perbarui saldo fisik akhir sesuai mutasi terakhir
                $stok->update([
                    'jumlah_fisik_layak' => number_format($saldo, 3, '.', ''),
                    'diperbarui_pada' => $hariIni->copy()->setTime(16, 0, 0),
                ]);

                // Buat pengiriman aktif & cadangan aktif untuk menguji rumus tersedia = fisik - cadangan aktif
                DB::table('pengiriman')->updateOrInsert(
                    ['no_resi' => $cfg['no_resi']],
                    [
                        'laporan_id' => null,
                        'gudang_asal_id' => $gudang->id,
                        'kode_wilayah_tujuan' => $cfg['wilayah_tujuan'],
                        'pembuat_id' => $petugasSimulasi->id,
                        'penyetuju_id' => $petugasSimulasi->id,
                        'petugas_id' => $petugasSimulasi->id,
                        'jadwal_berangkat' => $hariIni->copy()->addDay()->setTime(8, 0, 0),
                        'estimasi_tiba' => $hariIni->copy()->addDay()->setTime(13, 0, 0),
                        'status' => 'DISETUJUI',
                        'disetujui_pada' => $hariIni->copy()->setTime(15, 0, 0),
                        'berangkat_pada' => null,
                        'alasan_batal' => null,
                        'is_contoh' => true,
                        'updated_at' => $hariIni,
                        'created_at' => $hariIni,
                    ]
                );

                $pengirimanId = (int) DB::table('pengiriman')->where('no_resi', $cfg['no_resi'])->value('id');

                DB::table('detail_pengiriman')->updateOrInsert(
                    [
                        'pengiriman_id' => $pengirimanId,
                        'komoditas_id' => $komoditasMap['beras']->id,
                    ],
                    [
                        'jumlah_dikirim' => number_format($cfg['cadangan_aktif'], 3, '.', ''),
                        'updated_at' => $hariIni,
                        'created_at' => $hariIni,
                    ]
                );

                Cadangan::updateOrCreate(
                    [
                        'stok_id' => $stok->id,
                        'pengiriman_id' => $pengirimanId,
                    ],
                    [
                        'jumlah' => number_format($cfg['cadangan_aktif'], 3, '.', ''),
                        'status' => Cadangan::STATUS_AKTIF,
                        'dibuat_pada' => $hariIni->copy()->setTime(15, 0, 0),
                        'selesai_pada' => null,
                        'is_contoh' => true,
                    ]
                );
            }

            // 7. Tambahan Komoditas Satuan Berbeda (Minyak Goreng - liter) di Gudang Bulog Dayeuhkolot
            $stokMinyak = Stok::updateOrCreate(
                [
                    'gudang_id' => $gudangMap['aman']->id,
                    'komoditas_id' => $komoditasMap['minyak']->id,
                ],
                [
                    'jumlah_fisik_layak' => '1800.000',
                    'mulai_pencatatan_lengkap' => $mulaiPencatatan,
                    'diperbarui_pada' => $hariIni->copy()->setTime(16, 0, 0),
                    'is_contoh' => true,
                ]
            );

            $saldoMinyak = 3000.000;
            MutasiStok::updateOrCreate(
                ['ref_operasi' => 'SIM-GDG-BDG-01-MINYAK-H59-AWAL'],
                [
                    'stok_id' => $stokMinyak->id,
                    'jenis' => MutasiStok::JENIS_PENERIMAAN,
                    'jumlah' => '3000.000',
                    'saldo_setelah' => '3000.000',
                    'waktu' => $hariIni->copy()->subDays(59)->setTime(8, 30, 0)->format('Y-m-d H:i:s.000'),
                    'pengiriman_id' => null,
                    'pencatat_id' => $petugasSimulasi->id,
                    'keterangan' => 'Penerimaan awal minyak goreng simulasi',
                    'is_contoh' => true,
                ]
            );

            for ($hari = 59; $hari >= 0; $hari--) {
                $saldoMinyak -= 20.000;
                MutasiStok::updateOrCreate(
                    ['ref_operasi' => sprintf('SIM-GDG-BDG-01-MINYAK-H%02d-SALUR', $hari)],
                    [
                        'stok_id' => $stokMinyak->id,
                        'jenis' => MutasiStok::JENIS_PENYALURAN,
                        'jumlah' => '20.000',
                        'saldo_setelah' => number_format($saldoMinyak, 3, '.', ''),
                        'waktu' => $hariIni->copy()->subDays($hari)->setTime(11, 0, 0)->format('Y-m-d H:i:s.000'),
                        'pengiriman_id' => null,
                        'pencatat_id' => $petugasSimulasi->id,
                        'keterangan' => 'Penyaluran harian minyak goreng simulasi',
                        'is_contoh' => true,
                    ]
                );
            }
        });
    }
}

