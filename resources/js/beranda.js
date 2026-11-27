// Beranda publik v3: atlas stok pangan per kecamatan (PSKE-96, lanjutan PSKE-90/91).
// Data asli dikirim controller lewat <script id="peta-data">; selama belum ada, dipakai data simulasi di bawah.
// Kontrak bentuk data ada di docs/design-system.md bagian "Pola layar peta".
// Gambar wilayah di peta ada di satu fungsi (gambarWilayah) supaya mudah diganti polygon (PSKE-69).

const LABEL = { aman: 'Aman', waspada: 'Waspada', kritis: 'Kritis', netral: 'Riwayat kurang' };
const STATUS_VALID = ['kritis', 'waspada', 'aman'];
const gaya = getComputedStyle(document.documentElement);
const token = (nama, cadangan) => gaya.getPropertyValue(nama).trim() || cadangan;
const WARNA = {
    kritis: token('--fill-kritis', '#D2452B'),
    waspada: token('--fill-waspada', '#E2A23A'),
    aman: token('--fill-aman', '#5B9A4C'),
    netral: token('--fill-netral', '#A3ABA6'),
};
const TINTA = token('--color-text', '#17201C');

// Ambang status dari Superadmin (PSKE-106): Kritis < kritis, Aman > aman, sisanya Waspada.
const AMBANG = (() => {
    try {
        const isi = JSON.parse(document.getElementById('peta-ambang')?.textContent || 'null');
        if (Number.isFinite(isi?.kritis) && Number.isFinite(isi?.aman)) return isi;
    } catch {
        // Pakai nilai awal di bawah.
    }
    return { kritis: 14, aman: 30 };
})();

const angka = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const tanggal = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

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
        const status = statusDariHari(hari);
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

function bacaDataAsli() {
    try {
        const isi = JSON.parse(document.getElementById('peta-data')?.textContent || 'null');
        if (Array.isArray(isi)) return isi;
    } catch {
        // Data rusak: tetap tampilkan simulasi supaya halaman tidak kosong.
    }
    return null;
}

// Status dan hari selalu dibaca bersama: status di luar tiga nilai atau hari kosong dianggap riwayat kurang.
function rapikan(w) {
    const hari = Number.isFinite(Number(w.hari)) && w.hari !== null && w.hari !== '' ? Number(w.hari) : null;
    const status = hari !== null && STATUS_VALID.includes(w.status) ? w.status : 'netral';
    return { ...w, hari, status, stok_kg: Number(w.stok_kg) || 0, komoditas: w.komoditas ?? [], aktivitas: w.aktivitas ?? [] };
}

function statusDariHari(hari) {
    if (hari === null || hari === undefined || !Number.isFinite(Number(hari))) return 'netral';
    return hari < AMBANG.kritis ? 'kritis' : hari > AMBANG.aman ? 'aman' : 'waspada';
}

// Dibulatkan ke bawah supaya 13,6 hari tidak tampil "14 hari" dengan status Kritis.
function teksHari(hari) {
    if (hari === null) return null;
    if (hari < 1) return '< 1';
    return angka.format(Math.floor(hari));
}

function el(tag, kelas, teks) {
    const node = document.createElement(tag);
    if (kelas) node.className = kelas;
    if (teks !== undefined && teks !== null) node.textContent = teks;
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
    return el('span', `badge badge-${status === 'netral' ? 'netral' : status}`, LABEL[status] ?? status);
}

async function siapkanData() {
    const asli = bacaDataAsli();
    if (asli && asli.length) return { data: asli.map(rapikan), simulasi: false };

    // Toggle data uji hanya ada saat `npm run dev`, tidak ikut ke build produksi.
    if (import.meta.env.DEV) {
        const { dataUji, pasangToggle } = await import('./beranda-uji.js');
        const pilihan = new URLSearchParams(window.location.search).get('data');
        pasangToggle(pilihan);
        const uji = dataUji(pilihan, buatSimulasi);
        if (uji) return { data: uji.map(rapikan), simulasi: true };
    }
    if (asli) return { data: [], simulasi: false };
    return { data: buatSimulasi().map(rapikan), simulasi: true };
}

async function mulai() {
    const { data, simulasi } = await siapkanData();
    if (!simulasi) document.getElementById('peta-sumber')?.setAttribute('hidden', '');

    const daftar = document.getElementById('peta-list');
    const kosong = document.getElementById('peta-kosong');
    const cari = document.getElementById('peta-cari');
    const detail = document.getElementById('peta-detail');
    const tombolFilter = [...document.querySelectorAll('[data-filter]')];
    const desktop = window.matchMedia('(min-width: 1024px)');

    let filter = 'semua';
    let terpilih = null;
    const baris = new Map();
    const penanda = new Map();

    // Ringkasan: jumlah per status untuk filter, bar sebaran, dan kalimat cakupan
    const jumlah = { semua: data.length, kritis: 0, waspada: 0, aman: 0, netral: 0 };
    data.forEach((w) => { jumlah[w.status] += 1; });
    document.querySelectorAll('[data-count]').forEach((node) => { node.textContent = angka.format(jumlah[node.dataset.count] ?? 0); });
    document.querySelectorAll('[data-bar]').forEach((seg) => {
        seg.style.setProperty('--n', jumlah[seg.dataset.bar] ?? 0);
        seg.hidden = !jumlah[seg.dataset.bar];
    });
    const catatanNetral = document.getElementById('sebaran-netral');
    if (jumlah.netral) {
        catatanNetral.textContent = `${angka.format(jumlah.netral)} kecamatan berwarna abu: riwayat penyaluran belum 30 hari, jadi ketahanan belum bisa dihitung.`;
        catatanNetral.hidden = false;
    }
    const kota = [...new Set(data.map((w) => w.kota).filter(Boolean))];
    const cakupan = document.getElementById('peta-cakupan');
    if (data.length) {
        cakupan.textContent = `${angka.format(data.length)} kecamatan${kota.length === 1 ? ` di ${kota[0]}` : ''}. Diurutkan dari stok yang paling cepat habis.`;
    }

    // Peta dasar OSM; warnanya dibuat abu lewat CSS (.atlas-canvas .leaflet-tile-pane) supaya warna status paling menonjol
    const L = window.L;
    const peta = L ? L.map('map', { zoomControl: false, scrollWheelZoom: true, zoomSnap: 0.5 }) : null;
    if (peta) {
        L.control.zoom({ position: 'bottomright' }).addTo(peta);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(peta);
        gambarWilayah(peta, L);
        const titik = data.filter((w) => Number.isFinite(w.lat) && Number.isFinite(w.lng)).map((w) => [w.lat, w.lng]);
        if (titik.length) peta.fitBounds(L.latLngBounds(titik), { padding: [56, 56], maxZoom: 13 });
        else peta.setView([-2.5, 118], 5);
    } else {
        const gagal = el('p', 'atlas-gagal', 'Peta belum bisa dimuat. Daftar kecamatan di samping tetap bisa dipakai.');
        document.getElementById('map').append(gagal);
    }

    // Titik per kecamatan. PSKE-69 menggantinya dengan polygon batas kecamatan (warna = status).
    function gambarWilayah(map, Leaflet) {
        const maksStok = Math.max(...data.map((w) => w.stok_kg), 1);
        data.forEach((w) => {
            if (!Number.isFinite(w.lat) || !Number.isFinite(w.lng)) return;
            const lapis = Leaflet.circleMarker([w.lat, w.lng], {
                radius: 7 + Math.sqrt(w.stok_kg / maksStok) * 9,
                color: '#FFFFFF',
                weight: 2,
                fillColor: WARNA[w.status],
                fillOpacity: 1,
                className: 'peta-titik',
            }).addTo(map);
            lapis.bindTooltip(`${w.nama}, ${LABEL[w.status]}`, { direction: 'top', offset: [0, -8] });
            lapis.on('click', () => pilih(w.id, { dariPeta: true }));
            penanda.set(w.id, lapis);
        });
    }

    function tandai(id, aktif) {
        penanda.get(id)?.setStyle(aktif ? { color: TINTA, weight: 3 } : { color: '#FFFFFF', weight: 2 });
        if (aktif) penanda.get(id)?.bringToFront();
    }

    // Daftar baris kecamatan
    function buatBaris(w) {
        const li = el('li');
        const tombol = el('button', 'wil-row');
        tombol.type = 'button';
        tombol.dataset.status = w.status;
        tombol.dataset.id = w.id;

        const utama = el('span', 'wil-row-main');
        const sub = el('span', 'wil-row-sub', w.kota ?? '');
        if (w.kota) sub.title = w.kota;
        utama.append(el('span', 'wil-row-name', w.nama), sub);

        const angkaHari = teksHari(w.hari);
        const kanan = el('span', 'wil-row-figure');
        const hari = el('span', 'wil-row-days');
        if (angkaHari === null) hari.append('Belum dihitung');
        else if (w.stok_kg === 0) hari.append(el('b', '', 'Habis'));
        else hari.append(el('b', '', angkaHari), 'hari');
        kanan.append(hari, el('span', 'wil-row-sub', `${angka.format(w.stok_kg)} kg`));

        tombol.append(utama, kanan);
        const ringkas = angkaHari === null ? 'ketahanan belum bisa dihitung' : w.stok_kg === 0 ? 'stok habis' : `stok cukup ${angkaHari} hari`;
        tombol.setAttribute('aria-label', `${w.nama}, ${LABEL[w.status]}, ${ringkas}`);
        tombol.addEventListener('click', () => pilih(w.id));
        li.append(tombol);
        baris.set(w.id, li);
        return li;
    }

    // Urut dari yang paling cepat habis; yang belum bisa dihitung di paling bawah
    const urut = [...data].sort((a, b) => (a.hari ?? Infinity) - (b.hari ?? Infinity) || a.nama.localeCompare(b.nama, 'id'));
    daftar.replaceChildren(...urut.map(buatBaris));

    if (!data.length) {
        document.getElementById('peta-kosong-judul').textContent = 'Belum ada data kecamatan';
        document.getElementById('peta-kosong-teks').textContent = 'Status muncul setelah gudang di wilayah ini mulai mencatat stok.';
        kosong.hidden = false;
        tombolFilter.forEach((t) => { t.disabled = true; });
        cari.disabled = true;
        document.getElementById('sebaran-bar').hidden = true;
    }

    function terapkanFilter() {
        const kata = cari.value.trim().toLocaleLowerCase('id');
        let tampil = 0;
        urut.forEach((w) => {
            const cocok = (filter === 'semua' || w.status === filter) && (!kata || w.nama.toLocaleLowerCase('id').includes(kata));
            baris.get(w.id).hidden = !cocok;
            const lapis = penanda.get(w.id);
            if (lapis && peta) {
                if (cocok && !peta.hasLayer(lapis)) lapis.addTo(peta);
                if (!cocok && peta.hasLayer(lapis)) lapis.remove();
            }
            if (cocok) tampil += 1;
        });
        document.querySelectorAll('[data-bar]').forEach((seg) => seg.classList.toggle('is-redup', filter !== 'semua' && seg.dataset.bar !== filter));
        if (data.length) {
            kosong.hidden = tampil > 0;
            document.getElementById('peta-kosong-teks').textContent = filter === 'semua'
                ? 'Coba nama lain. Daftar hanya memuat kecamatan yang gudangnya sudah tercatat.'
                : `Coba nama lain, atau pilih status Semua.`;
        }
    }

    tombolFilter.forEach((tombol) => {
        tombol.addEventListener('click', () => {
            filter = tombol.dataset.filter;
            tombolFilter.forEach((t) => t.setAttribute('aria-pressed', String(t === tombol)));
            terapkanFilter();
        });
    });
    cari.addEventListener('input', terapkanFilter);

    // Detail kecamatan
    function isiDetail(w) {
        const bagian = (nama) => detail.querySelector(`[data-detail="${nama}"]`);
        bagian('nama').textContent = w.nama;
        bagian('lokasi').textContent = w.kota ?? '';
        bagian('figure').dataset.status = w.status;
        bagian('badge').replaceChildren(badge(w.status));

        const angkaHari = teksHari(w.hari);
        const nilai = bagian('hari');
        if (angkaHari === null) {
            bagian('hari-label').textContent = 'Ketahanan stok';
            nilai.replaceChildren('Belum bisa dihitung');
            nilai.style.fontSize = 'var(--font-size-xl)';
        } else {
            bagian('hari-label').textContent = w.stok_kg === 0 ? 'Stok gudang' : 'Perkiraan stok habis dalam';
            nilai.style.fontSize = '';
            if (w.stok_kg === 0) nilai.replaceChildren('Habis');
            else nilai.replaceChildren(angkaHari, el('small', '', 'hari'));
        }
        bagian('stok').textContent = `${angka.format(w.stok_kg)} kg tersedia`;
        bagian('diperbarui').textContent = w.diperbarui ? `Diperbarui ${tanggal.format(new Date(w.diperbarui))}` : '';

        const badan = bagian('komoditas');
        if (w.komoditas.length) {
            badan.replaceChildren(...w.komoditas.map((k) => {
                const hariK = k.hari === null || k.hari === undefined ? null : Number(k.hari);
                const statusK = statusDariHari(hariK);
                const tr = el('tr');
                const teksK = teksHari(hariK);
                tr.append(
                    el('td', '', k.nama),
                    el('td', '', `${angka.format(k.jumlah)} ${k.satuan}`),
                    el('td', `is-${statusK}`, teksK === null ? 'belum dihitung' : `${teksK} hari`),
                );
                return tr;
            }));
        } else {
            const tr = el('tr');
            const td = el('td', 'detail-kosong', 'Belum ada stok tercatat di gudang kecamatan ini.');
            td.colSpan = 3;
            tr.append(td);
            badan.replaceChildren(tr);
        }

        const gudang = bagian('gudang');
        gudang.replaceChildren(ikon('i-warehouse'), document.createTextNode(w.gudang || 'Belum ada gudang terdaftar'));

        const jenisIkon = { masuk: 'i-arrow-in', keluar: 'i-arrow-out', rusak: 'i-alert' };
        const catatan = bagian('aktivitas');
        if (w.aktivitas.length) {
            catatan.replaceChildren(...w.aktivitas.map((a) => {
                const li = el('li', `is-${a.jenis}`);
                li.append(ikon(jenisIkon[a.jenis] ?? 'i-history'), el('p', 'ledger-text', a.judul), el('p', 'ledger-time', tanggal.format(new Date(a.waktu))));
                return li;
            }));
        } else {
            catatan.replaceChildren(el('li', 'ledger-kosong', 'Belum ada mutasi tercatat.'));
        }
    }

    function pilih(id, { dariPeta = false } = {}) {
        const w = data.find((x) => x.id === id);
        if (!w) return;

        if (terpilih) {
            baris.get(terpilih)?.querySelector('.wil-row')?.removeAttribute('aria-current');
            tandai(terpilih, false);
        }
        terpilih = id;
        const tombol = baris.get(id)?.querySelector('.wil-row');
        tombol?.setAttribute('aria-current', 'true');
        if (dariPeta && desktop.matches) tombol?.scrollIntoView({ block: 'nearest' });

        tandai(id, true);
        if (peta && penanda.has(id)) {
            const sisiKanan = desktop.matches ? detail.offsetWidth || 380 : 0;
            const bawah = desktop.matches ? 0 : Math.round(window.innerHeight * 0.4);
            peta.flyToBounds(L.latLng(w.lat, w.lng).toBounds(1600), {
                paddingTopLeft: [40, 40],
                paddingBottomRight: [sisiKanan + 40, bawah + 40],
                maxZoom: 14,
                duration: 0.5,
            });
        }

        isiDetail(w);
        detail.hidden = false;
        detail.scrollTop = 0;
    }

    function tutupDetail() {
        detail.hidden = true;
        if (terpilih) {
            const tombol = baris.get(terpilih)?.querySelector('.wil-row');
            tombol?.removeAttribute('aria-current');
            tandai(terpilih, false);
            tombol?.focus({ preventScroll: !desktop.matches });
        }
        terpilih = null;
    }

    document.getElementById('peta-tutup').addEventListener('click', tutupDetail);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !detail.hidden) tutupDetail();
    });

    // Di desktop, kecamatan paling kritis langsung terbuka supaya panel detail tidak kosong
    if (desktop.matches && urut.length) pilih(urut[0].id);
}

mulai();
