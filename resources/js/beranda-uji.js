// Data uji beranda, hanya dimuat saat `npm run dev` (lihat siapkanData di beranda.js).
// Buka /?data=terburuk, /?data=satu, /?data=kosong, atau /?data=banyak untuk menguji tata letak.
// Nilai terburuk diambil dari pola data nyata: nama kecamatan dan kabupaten terpanjang, stok jutaan kg,
// hari 0, hari tepat di batas status, riwayat kurang, komoditas tanpa data, dan mutasi kosong.

const PILIHAN = [
    ['', 'Contoh'],
    ['terburuk', 'Terburuk'],
    ['satu', 'Satu'],
    ['kosong', 'Kosong'],
    ['banyak', '700 baris'],
];

const jam = (hari, j) => new Date(Date.UTC(2026, 10, 25 - hari, j, 7)).toISOString();

function terburuk() {
    return [
        {
            id: 'kep-seribu-selatan', nama: 'Kepulauan Seribu Selatan', kota: 'Kabupaten Administrasi Kepulauan Seribu',
            lat: -5.7445, lng: 106.6118, status: 'kritis', hari: 0, stok_kg: 0,
            gudang: 'Gudang Perum BULOG Kantor Cabang Pembantu Kepulauan Seribu Selatan Unit Penyimpanan 2',
            diperbarui: jam(0, 2), komoditas: [], aktivitas: [],
        },
        {
            id: 'pulau-batu-barat', nama: 'Pulau-Pulau Batu Barat', kota: 'Kabupaten Nias Selatan',
            lat: -0.4067, lng: 98.2925, status: 'riwayat_kurang', hari: null, stok_kg: 1284560,
            gudang: 'Gudang Dinas Ketahanan Pangan Nias Selatan', diperbarui: jam(1, 4),
            komoditas: [
                { nama: 'Minyak goreng kemasan sederhana (Minyakita)', jumlah: 1284560, satuan: 'liter', hari: null },
            ],
            aktivitas: [
                { jenis: 'masuk', judul: 'Penerimaan 1.284.560 liter minyak goreng kemasan sederhana dari Gudang Regional Sumatera Utara lewat Pelabuhan Teluk Dalam', waktu: jam(1, 4) },
            ],
        },
        {
            id: 'siantar-marihat', nama: 'Siantar Marihat', kota: 'Kota Pematangsiantar',
            lat: 2.9387, lng: 99.0578, status: 'kritis', hari: 13.6, stok_kg: 52880,
            gudang: 'Gudang Kecamatan Siantar Marihat', diperbarui: jam(0, 9),
            komoditas: [
                { nama: 'Beras medium', jumlah: 48000, satuan: 'kg', hari: 13.6 },
                { nama: 'Gula pasir', jumlah: 4880, satuan: 'kg', hari: 0.4 },
            ],
            aktivitas: [
                { jenis: 'keluar', judul: 'Penyaluran 3.880 kg beras ke 14 kelurahan', waktu: jam(0, 9) },
                { jenis: 'rusak', judul: '120 kg gula pasir rusak karena atap bocor', waktu: jam(2, 3) },
            ],
        },
        {
            id: 'cibeunying-kidul', nama: 'Cibeunying Kidul', kota: 'Kota Bandung',
            lat: -6.9076, lng: 107.6466, status: 'waspada', hari: 30, stok_kg: 11090,
            gudang: 'Gudang Kecamatan Cibeunying Kidul', diperbarui: jam(3, 1),
            komoditas: [{ nama: 'Beras medium', jumlah: 11090, satuan: 'kg', hari: 30 }],
            aktivitas: [{ jenis: 'keluar', judul: 'Penyaluran 370 kg beras', waktu: jam(3, 1) }],
        },
        {
            id: 'lembang', nama: 'Lembang', kota: 'Kabupaten Bandung Barat',
            lat: -6.8117, lng: 107.6175, status: 'aman', hari: 999, stok_kg: 12,
            gudang: '', diperbarui: null,
            komoditas: [{ nama: 'Beras premium', jumlah: 12, satuan: 'karung', hari: 999 }],
            aktivitas: [],
        },
        {
            id: 'x', nama: 'X', kota: '', lat: null, lng: null, status: 'aman', hari: 45, stok_kg: 900,
            gudang: 'Gudang X', diperbarui: jam(5, 5), komoditas: [], aktivitas: [],
        },
    ];
}

function banyak(buatSimulasi) {
    const dasar = buatSimulasi();
    return Array.from({ length: 700 }, (_, i) => {
        const w = dasar[i % dasar.length];
        const hari = (i * 7) % 75;
        return {
            ...w,
            id: `${w.id}-${i}`,
            nama: `${w.nama} ${i + 1}`,
            lat: w.lat + (((i * 37) % 100) - 50) / 400,
            lng: w.lng + (((i * 53) % 100) - 50) / 400,
            hari,
            status: hari < 14 ? 'kritis' : hari <= 30 ? 'waspada' : 'aman',
        };
    });
}

export function dataUji(pilihan, buatSimulasi) {
    if (pilihan === 'terburuk') return terburuk();
    if (pilihan === 'satu') return buatSimulasi().slice(3, 4);
    if (pilihan === 'kosong') return [];
    if (pilihan === 'banyak') return banyak(buatSimulasi);
    return null;
}

// Kontrol polos di bawah tengah layar. Bukan bagian desain, tidak ikut ke produksi.
export function pasangToggle(aktif) {
    const wadah = document.createElement('div');
    wadah.setAttribute('role', 'group');
    wadah.setAttribute('aria-label', 'Data uji (dev)');
    wadah.style.cssText = 'position:fixed;left:50%;bottom:12px;translate:-50% 0;z-index:5000;display:flex;gap:2px;padding:3px;border-radius:999px;background:#e5e5e5;font:12px system-ui,sans-serif;box-shadow:0 2px 8px rgb(0 0 0/.15)';
    PILIHAN.forEach(([nilai, label]) => {
        const a = document.createElement('a');
        a.textContent = label;
        a.href = nilai ? `?data=${nilai}` : window.location.pathname;
        const pilih = (aktif ?? '') === nilai;
        a.style.cssText = `padding:4px 10px;border-radius:999px;color:#111;text-decoration:none;${pilih ? 'background:#fff;font-weight:600' : ''}`;
        if (pilih) a.setAttribute('aria-current', 'true');
        wadah.append(a);
    });
    document.body.append(wadah);
}
