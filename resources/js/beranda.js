// Beranda publik: daftar kecamatan + peta Leaflet + kartu detail (PSKE-90, PSKE-91).
// Data asli dikirim controller lewat <script id="peta-data">; selama belum ada, dipakai data simulasi di bawah.
// Kontrak bentuk data ada di docs/design-system.md bagian "Pola layar peta".

const WARNA = { aman: '#12A05C', waspada: '#D98A1C', kritis: '#B42318' };
const WARNA_UTAMA = '#1D4FAE';
const LABEL = { aman: 'Aman', waspada: 'Waspada', kritis: 'Kritis' };
const SKALA_HARI = 60;

const angka = new Intl.NumberFormat('id-ID');
const tanggal = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

function buatSimulasi() {
    const dasar = [
        ['coblong', 'Coblong', -6.8915, 107.6107, 46, 18420],
        ['sukajadi', 'Sukajadi', -6.8846, 107.5944, 52, 21760],
        ['cicendo', 'Cicendo', -6.9058, 107.5884, 24, 9315],
        ['bojongloa-kaler', 'Bojongloa Kaler', -6.9268, 107.5875, 9, 3180],
        ['kiaracondong', 'Kiaracondong', -6.9256, 107.6463, 17, 6940],
        ['lengkong', 'Lengkong', -6.9310, 107.6233, 38, 12605],
        ['bandung-kidul', 'Bandung Kidul', -6.9578, 107.6311, 12, 4275],
        ['rancasari', 'Rancasari', -6.9506, 107.6763, 61, 15880],
        ['arcamanik', 'Arcamanik', -6.9150, 107.6766, 28, 8430],
        ['cibeunying-kidul', 'Cibeunying Kidul', -6.9076, 107.6466, 33, 11090],
    ];

    return dasar.map(([id, nama, lat, lng, hari, stokKg], i) => {
        const status = hari < 14 ? 'kritis' : hari <= 30 ? 'waspada' : 'aman';
        const jam = (n) => new Date(Date.UTC(2026, 10, 17 - n, 1 + ((i * 3 + n * 5) % 9), (i * 17 + n * 11) % 60)).toISOString();
        return {
            id,
            nama,
            kota: 'Kota Bandung',
            lat,
            lng,
            status,
            hari,
            stok_kg: stokKg,
            gudang: `Gudang Kecamatan ${nama}`,
            diperbarui: jam(0),
            komoditas: [
                { nama: 'Beras medium', jumlah: Math.round(stokKg * 0.78), satuan: 'kg', hari },
                { nama: 'Minyak goreng', jumlah: Math.round(stokKg * 0.09), satuan: 'liter', hari: Math.round(hari * 1.3) },
                { nama: 'Gula pasir', jumlah: Math.round(stokKg * 0.13), satuan: 'kg', hari: Math.max(3, Math.round(hari * 0.8)) },
            ],
            aktivitas: [
                { jenis: 'keluar', judul: `Penyaluran ${angka.format(Math.round(stokKg * 0.06))} kg beras ke kelurahan`, waktu: jam(0) },
                { jenis: 'masuk', judul: `Penerimaan ${angka.format(Math.round(stokKg * 0.11))} kg beras dari gudang induk`, waktu: jam(1) },
                { jenis: i % 3 === 0 ? 'rusak' : 'keluar', judul: i % 3 === 0 ? `${angka.format(40 + i * 7)} kg beras rusak karena lembap` : `Penyaluran ${angka.format(Math.round(stokKg * 0.04))} kg gula pasir`, waktu: jam(3) },
            ],
        };
    });
}

function bacaData() {
    try {
        const isi = JSON.parse(document.getElementById('peta-data')?.textContent || 'null');
        if (Array.isArray(isi) && isi.length) return { data: isi, simulasi: false };
    } catch {
        // Data rusak: tetap tampilkan simulasi supaya halaman tidak kosong.
    }
    return { data: buatSimulasi(), simulasi: true };
}

function el(tag, kelas, teks) {
    const node = document.createElement(tag);
    if (kelas) node.className = kelas;
    if (teks !== undefined) node.textContent = teks;
    return node;
}

function ikon(nama, kelas = 'icon icon-sm') {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', kelas);
    svg.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', `#${nama}`);
    svg.append(use);
    return svg;
}

function badge(status) {
    return el('span', `badge badge-${status}`, LABEL[status] ?? status);
}

function meter(hari, status) {
    const bar = el('span', `meter meter-${status}`);
    bar.style.setProperty('--meter', `${Math.min(100, Math.round((hari / SKALA_HARI) * 100))}%`);
    bar.setAttribute('role', 'img');
    bar.setAttribute('aria-label', `Ketahanan ${hari} hari`);
    return bar;
}

const { data, simulasi } = bacaData();
if (!simulasi) document.getElementById('peta-sumber')?.setAttribute('hidden', '');

const daftar = document.getElementById('peta-list');
const kosong = document.getElementById('peta-kosong');
const cari = document.getElementById('peta-cari');
const detail = document.getElementById('peta-detail');
const tombolFilter = [...document.querySelectorAll('[data-filter]')];
const desktop = window.matchMedia('(min-width: 1024px)');

let filter = 'semua';
let terpilih = null;
let sorot = null;
const kartu = new Map();
const penanda = new Map();

// Hitung jumlah per status untuk tab filter
const jumlah = { semua: data.length, aman: 0, waspada: 0, kritis: 0 };
data.forEach((w) => { jumlah[w.status] = (jumlah[w.status] ?? 0) + 1; });
document.querySelectorAll('[data-count]').forEach((node) => { node.textContent = jumlah[node.dataset.count] ?? 0; });

// Peta
const L = window.L;
const peta = L ? L.map('map', { zoomControl: false, scrollWheelZoom: true }) : null;
if (peta) {
    L.control.zoom({ position: 'bottomright' }).addTo(peta);
    // Tile OpenStreetMap; warnanya diredam lewat CSS (.peta-canvas .leaflet-tile-pane) supaya titik status menonjol.
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(peta);

    const maksStok = Math.max(...data.map((w) => w.stok_kg || 0), 1);
    data.forEach((w) => {
        const titik = L.circleMarker([w.lat, w.lng], {
            radius: 8 + Math.sqrt((w.stok_kg || 0) / maksStok) * 10,
            color: '#FFFFFF',
            weight: 3,
            fillColor: WARNA[w.status] ?? '#5A6577',
            fillOpacity: 0.95,
            className: 'peta-titik',
        }).addTo(peta);
        titik.bindTooltip(`${w.nama} · ${LABEL[w.status] ?? w.status}`, { direction: 'top', offset: [0, -8] });
        titik.on('click', () => pilih(w.id, { dariPeta: true }));
        penanda.set(w.id, titik);
    });

    peta.fitBounds(L.latLngBounds(data.map((w) => [w.lat, w.lng])), { padding: [48, 48] });

    // Lingkaran sorot biru di bawah titik yang dipilih
    sorot = L.circleMarker([0, 0], { radius: 30, stroke: false, fillColor: WARNA_UTAMA, fillOpacity: 0.16, interactive: false });
} else {
    document.getElementById('map').append(el('p', 'peta-kosong', 'Peta belum bisa dimuat. Daftar kecamatan tetap bisa dipakai.'));
}

// Daftar kartu
function buatKartu(w) {
    const li = el('li');
    const tombol = el('button', 'item-card');
    tombol.type = 'button';
    tombol.dataset.id = w.id;

    // Isi tombol hanya elemen frasa (span) supaya HTML tetap valid
    const kepala = el('span', 'item-card-head');
    const judul = el('span');
    judul.append(el('span', 'item-card-title', w.nama), el('span', 'item-card-sub', w.kota));
    kepala.append(judul, badge(w.status));

    const meta = el('span', 'item-card-meta');
    const kiri = el('span');
    kiri.append('Estimasi habis ', el('strong', '', `${w.hari} hari`));
    const kanan = el('span');
    kanan.append(el('strong', '', `${angka.format(w.stok_kg)} kg`));
    meta.append(kiri, kanan);

    tombol.append(kepala, meter(w.hari, w.status), meta);
    tombol.addEventListener('click', () => pilih(w.id));
    li.append(tombol);
    kartu.set(w.id, li);
    return li;
}

const urut = [...data].sort((a, b) => a.hari - b.hari);
daftar.replaceChildren(...urut.map(buatKartu));

function terapkanFilter() {
    const kata = cari.value.trim().toLowerCase();
    let tampil = 0;
    urut.forEach((w) => {
        const cocok = (filter === 'semua' || w.status === filter) && (!kata || w.nama.toLowerCase().includes(kata));
        kartu.get(w.id).hidden = !cocok;
        const titik = penanda.get(w.id);
        if (titik && peta) {
            if (cocok && !peta.hasLayer(titik)) titik.addTo(peta);
            if (!cocok && peta.hasLayer(titik)) titik.remove();
        }
        if (cocok) tampil += 1;
    });
    kosong.hidden = tampil > 0;
}

tombolFilter.forEach((tombol) => {
    tombol.addEventListener('click', () => {
        filter = tombol.dataset.filter;
        tombolFilter.forEach((t) => t.setAttribute('aria-pressed', String(t === tombol)));
        terapkanFilter();
    });
});
cari.addEventListener('input', terapkanFilter);

// Kartu detail
function isiDetail(w) {
    const bagian = (nama) => detail.querySelector(`[data-detail="${nama}"]`);
    bagian('nama').textContent = w.nama;
    bagian('lokasi').textContent = w.kota;
    bagian('badge').replaceChildren(badge(w.status));
    bagian('diperbarui').textContent = w.diperbarui ? `Diperbarui ${tanggal.format(new Date(w.diperbarui))}` : '';
    bagian('hari').textContent = `${w.hari} hari`;
    bagian('stok').textContent = `${angka.format(w.stok_kg)} kg`;

    bagian('komoditas').replaceChildren(...(w.komoditas ?? []).map((k) => {
        const status = k.hari < 14 ? 'kritis' : k.hari <= 30 ? 'waspada' : 'aman';
        const li = el('li');
        li.append(el('span', '', k.nama), el('strong', '', `${angka.format(k.jumlah)} ${k.satuan}`), meter(k.hari, status));
        return li;
    }));

    const gudang = bagian('gudang');
    gudang.replaceChildren(ikon('i-warehouse'), document.createTextNode(w.gudang ?? '-'));

    bagian('aktivitas').replaceChildren(...(w.aktivitas ?? []).map((a) => {
        const li = el('li', `is-${a.jenis}`);
        li.append(el('p', 'timeline-title', a.judul), el('p', 'timeline-time', tanggal.format(new Date(a.waktu))));
        return li;
    }));
}

function pilih(id, { dariPeta = false } = {}) {
    const w = data.find((x) => x.id === id);
    if (!w) return;

    if (terpilih) {
        kartu.get(terpilih)?.querySelector('.item-card')?.removeAttribute('aria-current');
        penanda.get(terpilih)?.setStyle({ color: '#FFFFFF' });
    }
    terpilih = id;
    const tombol = kartu.get(id)?.querySelector('.item-card');
    tombol?.setAttribute('aria-current', 'true');
    if (dariPeta) tombol?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

    const titik = penanda.get(id);
    if (titik && peta) {
        sorot?.setLatLng([w.lat, w.lng]).addTo(peta).bringToBack();
        titik.setStyle({ color: WARNA_UTAMA }).bringToFront();
        peta.flyTo([w.lat, w.lng], Math.max(peta.getZoom(), 14), { duration: 0.6 });
    }

    isiDetail(w);
    detail.hidden = false;
    if (!desktop.matches && !dariPeta) detail.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function tutupDetail() {
    detail.hidden = true;
    if (terpilih) {
        const tombol = kartu.get(terpilih)?.querySelector('.item-card');
        tombol?.removeAttribute('aria-current');
        penanda.get(terpilih)?.setStyle({ color: '#FFFFFF' });
        sorot?.remove();
        tombol?.focus();
    }
    terpilih = null;
}

document.getElementById('peta-tutup').addEventListener('click', tutupDetail);
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !detail.hidden) tutupDetail();
});

// Buka kecamatan paling kritis supaya kartu detail langsung terlihat di desktop
if (desktop.matches && urut.length) pilih(urut[0].id);
