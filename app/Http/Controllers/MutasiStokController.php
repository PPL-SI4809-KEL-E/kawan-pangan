<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use App\Models\Komoditas;
use App\Models\MutasiStok;
use App\Models\Stok;
use App\Services\LayananStok;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * PSKE-107 · Form catat mutasi stok untuk pengelola instansi.
 * Semua perubahan saldo lewat LayananStok (PSKE-52), jadi aturan saldo tidak negatif,
 * batas stok tersedia, dan ref_operasi unik tetap dijaga di satu tempat.
 */
class MutasiStokController extends Controller
{
    /** Jenis mutasi yang boleh dicatat dari form, beserta label untuk petugas. */
    public const JENIS = [
        MutasiStok::JENIS_PENERIMAAN => ['label' => 'Barang masuk', 'bantuan' => 'Stok bertambah, misalnya kiriman dari gudang induk.'],
        MutasiStok::JENIS_PENYALURAN => ['label' => 'Penyaluran', 'bantuan' => 'Stok berkurang karena disalurkan ke warga atau kelurahan.'],
        MutasiStok::JENIS_RUSAK => ['label' => 'Rusak', 'bantuan' => 'Stok berkurang karena rusak, basah, atau kedaluwarsa.'],
        MutasiStok::JENIS_RETUR => ['label' => 'Retur', 'bantuan' => 'Stok bertambah karena barang dikembalikan ke gudang.'],
        MutasiStok::JENIS_PENYESUAIAN => ['label' => 'Stock opname', 'bantuan' => 'Isi jumlah fisik hasil hitung. Sistem mencatat selisihnya.'],
    ];

    private const JENIS_KELUAR = [MutasiStok::JENIS_PENYALURAN, MutasiStok::JENIS_RUSAK];

    public function create(Request $request): View
    {
        $this->pastikanPengelola($request);

        return view('panel.stok.mutasi', [
            'pilihanGudang' => Gudang::where('instansi_id', $request->user()->instansi_id)->orderBy('nama')->get(['id', 'nama']),
            'pilihanKomoditas' => Komoditas::orderBy('nama')->get(['id', 'nama', 'satuan']),
            'jenisMutasi' => self::JENIS,
            'pilih' => [
                'gudang' => $request->integer('gudang') ?: null,
                'komoditas' => $request->integer('komoditas') ?: null,
            ],
            // Token sekali pakai: klik ganda atau kirim ulang tidak menggandakan mutasi.
            'refOperasi' => 'form-'.Str::uuid(),
        ]);
    }

    public function store(Request $request, LayananStok $layanan): RedirectResponse
    {
        $this->pastikanPengelola($request);

        $data = $request->validate([
            'gudang_id' => ['required', 'integer', Rule::exists('gudang', 'id')->where('instansi_id', $request->user()->instansi_id)],
            'komoditas_id' => ['required', 'integer', 'exists:komoditas,id'],
            'jenis' => ['required', Rule::in(array_keys(self::JENIS))],
            'jumlah' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:99999999999'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'ref_operasi' => ['required', 'string', 'max:191'],
        ], [
            'gudang_id.required' => 'Pilih gudang.',
            'gudang_id.exists' => 'Gudang tidak ditemukan di instansi Anda.',
            'komoditas_id.required' => 'Pilih komoditas.',
            'komoditas_id.exists' => 'Komoditas tidak ditemukan.',
            'jenis.required' => 'Pilih jenis mutasi.',
            'jenis.in' => 'Jenis mutasi tidak dikenal.',
            'jumlah.required' => 'Isi jumlahnya.',
            'jumlah.numeric' => 'Jumlah harus berupa angka.',
            'jumlah.decimal' => 'Jumlah paling banyak 3 angka di belakang koma.',
            'jumlah.min' => 'Jumlah tidak boleh negatif.',
            'jumlah.max' => 'Jumlah terlalu besar.',
            'keterangan.max' => 'Catatan maksimal 255 karakter.',
            'ref_operasi.required' => 'Form kedaluwarsa. Muat ulang halaman lalu coba lagi.',
        ]);

        $gudang = Gudang::findOrFail($data['gudang_id']);
        Gate::authorize('updateStok', $gudang);
        $komoditas = Komoditas::findOrFail($data['komoditas_id']);
        $jenis = $data['jenis'];
        $ref = trim($data['ref_operasi']);
        $kembali = ['gudang' => $gudang->id];

        // Form yang sama terkirim dua kali: mutasi pertama sudah tercatat, cukup arahkan kembali.
        if ($layanan->refSudahAda($ref)) {
            return redirect()->route('panel.instansi.stok.index', $kembali)
                ->with('sukses', 'Mutasi ini sudah tercatat sebelumnya.');
        }

        if ($jenis !== MutasiStok::JENIS_PENYESUAIAN && (float) $data['jumlah'] <= 0) {
            throw ValidationException::withMessages(['jumlah' => 'Jumlah harus lebih dari 0.']);
        }

        $stok = Stok::where('gudang_id', $gudang->id)->where('komoditas_id', $komoditas->id)->first();
        if (! $stok && in_array($jenis, self::JENIS_KELUAR, true)) {
            throw ValidationException::withMessages([
                'komoditas_id' => "Belum ada stok {$komoditas->nama} di {$gudang->nama}. Catat barang masuk dulu.",
            ]);
        }
        $stok ??= $layanan->ambilAtauBuatStok($gudang, $komoditas);

        try {
            $mutasi = $jenis === MutasiStok::JENIS_PENYESUAIAN
                ? $layanan->sesuaikanSaldo($stok, $data['jumlah'], $request->user(), $ref, $data['keterangan'] ?? null)
                : $layanan->catatMutasiIdempoten($stok, $jenis, $data['jumlah'], $request->user(), $ref, $data['keterangan'] ?? null);
        } catch (ValidationException $e) {
            // Pesan dari LayananStok memakai nama field internal (saldo_setelah); tampilkan di isian jumlah.
            throw ValidationException::withMessages(['jumlah' => collect($e->errors())->flatten()->first()]);
        }

        $saldo = rtrim(rtrim(number_format((float) $mutasi->saldo_setelah, 3, ',', '.'), '0'), ',');

        return redirect()->route('panel.instansi.stok.index', $kembali)
            ->with('sukses', self::JENIS[$jenis]['label']." tercatat. Stok {$komoditas->nama} di {$gudang->nama} sekarang {$saldo} {$komoditas->satuan}.");
    }

    private function pastikanPengelola(Request $request): void
    {
        abort_unless($request->user()->isPengelolaInstansi(), 403, 'Hanya pengelola instansi yang dapat mencatat mutasi stok.');
    }
}
