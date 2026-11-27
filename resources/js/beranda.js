// Beranda publik v3 + PBI-15 Choropleth Drill-down (PSKE-69, lanjutan PSKE-96 & PSKE-106).
// Data diambil bertahap per level (Provinsi -> Kab/Kota -> Kecamatan) dari #peta-awal atau /api/peta;
// jika basis data belum siap, dipakai data simulasi agar pratinjau beranda tetap berjalan.

const LABEL = { aman: 'Aman', waspada: 'Waspada', kritis: 'Kritis', netral: 'Riwayat kurang' };
const LEVEL_UNIT = { 1: 'provinsi', 2: 'kabupaten/kota', 3: 'kecamatan' };
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

function bacaPayloadAwal() {
    try {
        const awal = JSON.parse(document.getElementById('peta-awal')?.textContent || 'null');
        if (awal && Array.isArray(awal.wilayah)) return awal;
    } catch {
        // Lanjut periksa fallback #peta-data.
    }
    try {
        const isi = JSON.parse(document.getElementById('peta-data')?.textContent || 'null');
        if (Array.isArray(isi)) {
            return {
                level: 3,
                level_nama: 'Kecamatan',
                kode_induk: null,
                breadcrumbs: [],
                daftar_komoditas: [],
                geojson_url: '/geojson/kecamatan/32.73.json',
                wilayah: isi,
            };
        }
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

function normalisasiNama(nama) {
    return String(nama || '')
        .toLocaleLowerCase('id')
        .replace(/^(kabupaten|kab\.?|kota\s+adm\.?|kota|kecamatan|kec\.?|provinsi|prov\.?)\s+/i, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

function cariWilayahUntukFeature(feature, byKode, byNama) {
    const props = feature?.properties ?? {};
    const kodeKandidat = [props.kode_wilayah, props.KODE_WILAYAH, props.kode, props.KDPKAB, props.KODE_PROV, props.KDCPUM];
    for (const k of kodeKandidat) {
        if (!k) continue;
        const hit = byKode.get(String(k).trim());
        if (hit) return hit;
    }

    const namaKandidat = [props.nama, props.NAMOBJ, props.NAME_3, props.NAME_2, props.NAME_1, props.WADMKC, props.WADMKK, props.WADMPR];
    for (const n of namaKandidat) {
        if (!n) continue;
        const hit = byNama.get(normalisasiNama(n));
        if (hit) return hit;
    }
    return null;
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

function bungkusSimulasi(daftar, geojsonUrl = '/geojson/kecamatan/32.73.json') {
    return {
        level: 3,
        level_nama: 'Kecamatan',
        kode_induk: null,
        breadcrumbs: [],
        daftar_komoditas: [],
        geojson_url: geojsonUrl,
        wilayah: daftar,
    };
}

async function siapkanData() {
    const asli = bacaPayloadAwal();
    if (asli && asli.wilayah.length) return { payload: asli, simulasi: false };

    // Toggle data uji hanya ada saat `npm run dev`, tidak ikut ke build produksi.
    if (import.meta.env.DEV) {
        const { dataUji, pasangToggle } = await import('./beranda-uji.js');
        const pilihan = new URLSearchParams(window.location.search).get('data');
        pasangToggle(pilihan);
        const uji = dataUji(pilihan, buatSimulasi);
        if (uji) {
            return {
                payload: bungkusSimulasi(uji, pilihan === 'banyak' ? null : '/geojson/kecamatan/32.73.json'),
                simulasi: true,
            };
        }
    }
    if (asli) return { payload: asli, simulasi: false };
    return { payload: bungkusSimulasi(buatSimulasi()), simulasi: true };
}

async function mulai() {
    const awal = await siapkanData();
    const simulasi = awal.simulasi;
    if (!simulasi) document.getElementById('peta-sumber')?.setAttribute('hidden', '');

    const daftar = document.getElementById('peta-list');
    const kosong = document.getElementById('peta-kosong');
    const cari = document.getElementById('peta-cari');
    const cariLabel = document.getElementById('peta-cari-label');
    const detail = document.getElementById('peta-detail');
    const breadcrumbNav = document.getElementById('peta-breadcrumb');
    const levelLabel = document.getElementById('peta-level-label');
    const cakupanEl = document.getElementById('peta-cakupan');
    const komoditasWrap = document.getElementById('peta-komoditas-wrap');
    const komoditasSelect = document.getElementById('peta-komoditas');
    const drillWrap = document.getElementById('peta-drill-wrap');
    const drillBtn = document.getElementById('peta-drill-btn');
    const drillLabel = document.getElementById('peta-drill-label');
    const tombolFilter = [...document.querySelectorAll('[data-filter]')];
    const desktop = window.matchMedia('(min-width: 1024px)');

    const cacheApi = new Map();
    const cacheGeo = new Map();
    if (!simulasi && awal.payload?.level === 1 && !awal.payload?.kode_induk) {
        cacheApi.set('1::', awal.payload);
    }

    let filter = 'semua';
    let terpilih = null;
    let data = [];
    let urut = [];
    let payloadAktif = awal.payload;
    let komoditasAktif = awal.payload?.komoditas_id ? String(awal.payload.komoditas_id) : '';
    let renderToken = 0;

    const baris = new Map();
    const penanda = new Map();

    // Peta dasar OSM; warnanya dibuat abu lewat CSS (.atlas-canvas .leaflet-tile-pane) supaya warna status paling menonjol.
    const L = window.L;
    const peta = L ? L.map('map', { zoomControl: false, scrollWheelZoom: true, zoomSnap: 0.5 }) : null;
    const grupLapisan = peta && L ? L.featureGroup().addTo(peta) : null;
    if (peta) {
        L.control.zoom({ position: 'bottomright' }).addTo(peta);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(peta);
    } else {
        const gagal = el('p', 'atlas-gagal', 'Peta belum bisa dimuat. Daftar wilayah di samping tetap bisa dipakai.');
        document.getElementById('map').append(gagal);
    }

    async function ambilGeoJson(url) {
        if (!url) return null;
        if (cacheGeo.has(url)) return cacheGeo.get(url);
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) return null;
            const json = await res.json();
            cacheGeo.set(url, json);
            return json;
        } catch {
            return null;
        }
    }

    function gayaLapisan(w, aktif) {
        const poligon = penanda.get(w?.id)?.options?.className === 'peta-polygon';
        if (poligon) {
            return aktif
                ? { color: TINTA, weight: 2.8, fillOpacity: 0.92 }
                : { color: '#FFFFFF', weight: 1.6, fillOpacity: 0.78 };
        }
        return aktif
            ? { color: TINTA, weight: 3, fillOpacity: 1 }
            : { color: '#FFFFFF', weight: 2, fillOpacity: 1 };
    }

    function tandai(id, aktif) {
        const lapis = penanda.get(id);
        if (!lapis) return;
        const w = data.find((x) => x.id === id);
        lapis.setStyle(gayaLapisan(w, aktif));
        if (aktif) lapis.bringToFront?.();
    }

    async function gambarWilayah(map, Leaflet, payload, tokenRender) {
        if (!map || !grupLapisan) return;
        grupLapisan.clearLayers();
        penanda.clear();

        const byKode = new Map();
        const byNama = new Map();
        data.forEach((w) => {
            if (w.kode_wilayah) byKode.set(String(w.kode_wilayah).trim(), w);
            byKode.set(String(w.id).trim(), w);
            const kunciNama = normalisasiNama(w.nama);
            if (kunciNama && !byNama.has(kunciNama)) byNama.set(kunciNama, w);
        });

        const matchedIds = new Set();
        const matchedLayers = [];
        const geo = await ambilGeoJson(payload?.geojson_url);
        if (tokenRender !== renderToken) return;

        let geoBounds = null;
        if (geo && Array.isArray(geo.features)) {
            const geoLapis = Leaflet.geoJSON(geo, {
                style(feature) {
                    const w = cariWilayahUntukFeature(feature, byKode, byNama);
                    if (w) {
                        return {
                            color: '#FFFFFF',
                            weight: 1.6,
                            opacity: 1,
                            fillColor: WARNA[w.status],
                            fillOpacity: 0.78,
                            className: 'peta-polygon',
                        };
                    }
                    return {
                        color: '#C9CEC6',
                        weight: 1,
                        opacity: 0.75,
                        fillColor: '#E8ECE6',
                        fillOpacity: 0.35,
                        className: 'peta-polygon-kosong',
                    };
                },
                onEachFeature(feature, layer) {
                    const w = cariWilayahUntukFeature(feature, byKode, byNama);
                    if (w) {
                        matchedIds.add(w.id);
                        matchedLayers.push(layer);
                        penanda.set(w.id, layer);

                        const hintDrill = w.bisa_drill_down ? ' (klik ganda untuk buka)' : '';
                        layer.bindTooltip(`${w.nama}, ${LABEL[w.status]}${hintDrill}`, { sticky: true });
                        layer.on('click', () => pilih(w.id, { dariPeta: true }));
                        layer.on('mouseover', () => {
                            if (terpilih !== w.id) layer.setStyle({ fillOpacity: 0.9, weight: 2.2 });
                        });
                        layer.on('mouseout', () => {
                            if (terpilih !== w.id) layer.setStyle({ fillOpacity: 0.78, weight: 1.6 });
                        });
                        if (w.bisa_drill_down) {
                            layer.on('dblclick', (e) => {
                                Leaflet.DomEvent.stop(e);
                                bukaLevel(w.level + 1, w.kode_wilayah, komoditasAktif);
                            });
                        }
                    } else {
                        const namaGeo = feature?.properties?.nama || feature?.properties?.NAMOBJ || feature?.properties?.NAME_3 || 'Wilayah';
                        layer.bindTooltip(`${namaGeo} (belum ada data)`, { sticky: true });
                    }
                },
            }).addTo(grupLapisan);

            const b = geoLapis.getBounds();
            if (b.isValid()) geoBounds = b;
        }

        // Fallback titik koordinat bagi wilayah yang belum terpetakan di berkas GeoJSON
        const tanpaPolygon = data.filter((w) => !matchedIds.has(w.id) && Number.isFinite(w.lat) && Number.isFinite(w.lng));
        if (tanpaPolygon.length) {
            const maksStok = Math.max(...data.map((w) => w.stok_kg), 1);
            tanpaPolygon.forEach((w) => {
                const lapis = Leaflet.circleMarker([w.lat, w.lng], {
                    radius: 7 + Math.sqrt(w.stok_kg / maksStok) * 9,
                    color: '#FFFFFF',
                    weight: 2,
                    fillColor: WARNA[w.status],
                    fillOpacity: 1,
                    className: 'peta-titik',
                }).addTo(grupLapisan);
                lapis.bindTooltip(`${w.nama}, ${LABEL[w.status]}`, { direction: 'top', offset: [0, -8] });
                lapis.on('click', () => pilih(w.id, { dariPeta: true }));
                if (w.bisa_drill_down) {
                    lapis.on('dblclick', (e) => {
                        Leaflet.DomEvent.stop(e);
                        bukaLevel(w.level + 1, w.kode_wilayah, komoditasAktif);
                    });
                }
                penanda.set(w.id, lapis);
                matchedLayers.push(lapis);
            });
        }

        // Terapkan filter aktif dan tandai item terpilih jika masih ada
        terapkanFilter();
        if (terpilih && penanda.has(terpilih)) tandai(terpilih, true);

        // Bingkai peta sesuai cakupan level yang sedang dibuka
        const matchedBounds = matchedLayers.length ? Leaflet.featureGroup(matchedLayers).getBounds() : null;
        if ((payload?.level ?? 3) === 1 && matchedBounds?.isValid() && matchedLayers.length <= 5) {
            map.fitBounds(matchedBounds, { padding: [48, 48], maxZoom: 8 });
        } else if (geoBounds?.isValid()) {
            map.fitBounds(geoBounds, { padding: [40, 40], maxZoom: 13 });
        } else if (matchedBounds?.isValid()) {
            map.fitBounds(matchedBounds, { padding: [48, 48], maxZoom: 13 });
        } else {
            map.setView([-2.5, 118], 5);
        }
    }

    function buatBaris(w) {
        const li = el('li');
        const tombol = el('button', 'wil-row');
        tombol.type = 'button';
        tombol.dataset.status = w.status;
        tombol.dataset.id = w.id;

        const utama = el('span', 'wil-row-main');
        const subText = w.bisa_drill_down
            ? `${w.kota ? `${w.kota} · ` : ''}${angka.format(w.jumlah_anak)} ${LEVEL_UNIT[w.level + 1] ?? 'wilayah'}`
            : (w.kota ?? '');
        const sub = el('span', 'wil-row-sub', subText);
        if (subText) sub.title = subText;
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

    function terapkanFilter() {
        const unit = LEVEL_UNIT[payloadAktif?.level ?? 3] ?? 'wilayah';
        const kata = cari.value.trim().toLocaleLowerCase('id');
        let tampil = 0;
        urut.forEach((w) => {
            const cocok = (filter === 'semua' || w.status === filter) && (!kata || w.nama.toLocaleLowerCase('id').includes(kata));
            const li = baris.get(w.id);
            if (li) li.hidden = !cocok;
            const lapis = penanda.get(w.id);
            if (lapis && grupLapisan) {
                if (cocok && !grupLapisan.hasLayer(lapis)) lapis.addTo(grupLapisan);
                if (!cocok && grupLapisan.hasLayer(lapis)) lapis.removeFrom(grupLapisan);
            }
            if (cocok) tampil += 1;
        });
        document.querySelectorAll('[data-bar]').forEach((seg) => seg.classList.toggle('is-redup', filter !== 'semua' && seg.dataset.bar !== filter));
        if (data.length) {
            kosong.hidden = tampil > 0;
            document.getElementById('peta-kosong-judul').textContent = `Tidak ada ${unit} yang cocok`;
            document.getElementById('peta-kosong-teks').textContent = filter === 'semua'
                ? `Coba nama lain. Daftar hanya memuat ${unit} yang tercatat di level ini.`
                : 'Coba nama lain, atau pilih status Semua.';
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

    komoditasSelect?.addEventListener('change', () => {
        komoditasAktif = komoditasSelect.value;
        if (!simulasi) {
            bukaLevel(payloadAktif?.level ?? 1, payloadAktif?.kode_induk ?? null, komoditasAktif);
        }
    });

    function renderBreadcrumbs(payload) {
        if (!breadcrumbNav) return;
        const crumbs = payload?.breadcrumbs ?? [];
        if (simulasi || !crumbs.length) {
            breadcrumbNav.hidden = true;
            breadcrumbNav.replaceChildren();
            return;
        }

        const frag = document.createDocumentFragment();
        crumbs.forEach((c, idx) => {
            if (idx > 0) frag.append(el('span', 'atlas-crumb-sep', '/'));
            const terakhir = idx === crumbs.length - 1;
            if (terakhir) {
                const span = el('span', 'atlas-crumb-current', c.nama);
                span.setAttribute('aria-current', 'page');
                frag.append(span);
            } else {
                const btn = el('button', 'atlas-crumb-btn', c.nama);
                btn.type = 'button';
                btn.addEventListener('click', () => bukaLevel(c.level, c.kode ?? null, komoditasAktif));
                frag.append(btn);
            }
        });
        breadcrumbNav.replaceChildren(frag);
        breadcrumbNav.hidden = false;
    }

    function renderFilterKomoditas(payload) {
        if (!komoditasWrap || !komoditasSelect) return;
        const daftarK = payload?.daftar_komoditas ?? [];
        if (simulasi || !daftarK.length) {
            komoditasWrap.hidden = true;
            return;
        }
        const opsi = [new Option('Semua komoditas', '')];
        daftarK.forEach((k) => {
            opsi.push(new Option(k.nama, String(k.id)));
        });
        komoditasSelect.replaceChildren(...opsi);
        komoditasSelect.value = komoditasAktif;
        komoditasWrap.hidden = false;
    }

    function isiDetail(w) {
        const unit = LEVEL_UNIT[w.level ?? payloadAktif?.level ?? 3] ?? 'wilayah';
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

        if (drillWrap && drillBtn && drillLabel) {
            if (!simulasi && w.bisa_drill_down) {
                const anakUnit = LEVEL_UNIT[(w.level ?? 1) + 1] ?? 'wilayah';
                drillLabel.textContent = `Buka ${angka.format(w.jumlah_anak)} ${anakUnit}`;
                drillBtn.onclick = () => bukaLevel((w.level ?? 1) + 1, w.kode_wilayah, komoditasAktif);
                drillWrap.hidden = false;
            } else {
                drillWrap.hidden = true;
                drillBtn.onclick = null;
            }
        }

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
            const td = el('td', 'detail-kosong', `Belum ada stok tercatat di gudang ${unit} ini.`);
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

    function pilih(id, { dariPeta = false, tanpaFly = false } = {}) {
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
        if (peta && !tanpaFly && penanda.has(id)) {
            const lapis = penanda.get(id);
            const sisiKanan = desktop.matches ? detail.offsetWidth || 380 : 0;
            const bawah = desktop.matches ? 0 : Math.round(window.innerHeight * 0.4);
            const opsiFly = {
                paddingTopLeft: [40, 40],
                paddingBottomRight: [sisiKanan + 40, bawah + 40],
                maxZoom: (w.level ?? 3) === 1 ? 8 : (w.level ?? 3) === 2 ? 11 : 14,
                duration: 0.45,
            };
            if (typeof lapis.getBounds === 'function') {
                const bounds = lapis.getBounds();
                if (bounds?.isValid()) peta.flyToBounds(bounds, opsiFly);
            } else if (Number.isFinite(w.lat) && Number.isFinite(w.lng)) {
                peta.flyToBounds(L.latLng(w.lat, w.lng).toBounds(1600), opsiFly);
            }
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

    async function terapkanPayload(payload) {
        renderToken += 1;
        const tokenIni = renderToken;
        payloadAktif = payload;
        data = (payload?.wilayah ?? []).map(rapikan);
        terpilih = null;
        detail.hidden = true;
        baris.clear();

        const level = payload?.level ?? 3;
        const unit = LEVEL_UNIT[level] ?? 'wilayah';
        if (levelLabel) levelLabel.textContent = `per ${unit}`;
        if (cariLabel) cariLabel.textContent = `Cari ${unit}`;
        if (cari) {
            cari.placeholder = `Cari ${unit}`;
            cari.value = '';
        }
        daftar?.setAttribute('aria-label', `Daftar ${unit}, urut dari stok paling cepat habis`);

        renderBreadcrumbs(payload);
        renderFilterKomoditas(payload);

        // Ringkasan: jumlah per status untuk filter, bar sebaran, dan kalimat cakupan.
        const jumlah = { semua: data.length, kritis: 0, waspada: 0, aman: 0, netral: 0 };
        data.forEach((w) => { jumlah[w.status] += 1; });
        document.querySelectorAll('[data-count]').forEach((node) => { node.textContent = angka.format(jumlah[node.dataset.count] ?? 0); });
        const sebaranBar = document.getElementById('sebaran-bar');
        document.querySelectorAll('[data-bar]').forEach((seg) => {
            seg.style.setProperty('--n', jumlah[seg.dataset.bar] ?? 0);
            seg.hidden = !jumlah[seg.dataset.bar];
        });
        const catatanNetral = document.getElementById('sebaran-netral');
        if (catatanNetral) {
            if (jumlah.netral) {
                catatanNetral.textContent = `${angka.format(jumlah.netral)} ${unit} berwarna abu: riwayat penyaluran belum 30 hari atau belum ada gudang terhubung.`;
                catatanNetral.hidden = false;
            } else {
                catatanNetral.hidden = true;
            }
        }

        if (cakupanEl) {
            if (simulasi) {
                const kota = [...new Set(data.map((w) => w.kota).filter(Boolean))];
                if (data.length) {
                    cakupanEl.textContent = `${angka.format(data.length)} kecamatan${kota.length === 1 ? ` di ${kota[0]}` : ''}. Diurutkan dari stok yang paling cepat habis.`;
                }
            } else if (data.length) {
                const indukNama = payload?.induk?.nama;
                cakupanEl.textContent = `${angka.format(data.length)} ${unit}${indukNama ? ` di ${indukNama}` : ''}. Diurutkan dari stok yang paling cepat habis.`;
            } else {
                cakupanEl.textContent = `Belum ada data ${unit} pada cakupan ini.`;
            }
        }

        urut = [...data].sort((a, b) => (a.hari ?? Infinity) - (b.hari ?? Infinity) || a.nama.localeCompare(b.nama, 'id'));
        daftar.replaceChildren(...urut.map(buatBaris));

        const kosongSemua = !data.length;
        tombolFilter.forEach((t) => { t.disabled = kosongSemua; });
        if (cari) cari.disabled = kosongSemua;
        if (sebaranBar) sebaranBar.hidden = kosongSemua;
        if (kosongSemua) {
            document.getElementById('peta-kosong-judul').textContent = `Belum ada data ${unit}`;
            document.getElementById('peta-kosong-teks').textContent = 'Status muncul setelah gudang di wilayah ini mulai mencatat stok.';
            kosong.hidden = false;
        } else {
            kosong.hidden = true;
        }

        filter = 'semua';
        tombolFilter.forEach((t) => t.setAttribute('aria-pressed', String(t.dataset.filter === 'semua')));

        await gambarWilayah(peta, L, payload, tokenIni);
        if (tokenIni !== renderToken) return;

        // Di desktop, wilayah paling kritis langsung tampil di panel detail tanpa menggeser bingkai level.
        if (desktop.matches && urut.length) pilih(urut[0].id, { tanpaFly: true });
    }

    async function bukaLevel(level, kodeInduk = null, komoditasId = '') {
        if (simulasi) return;
        const kunci = `${level}:${kodeInduk ?? ''}:${komoditasId ?? ''}`;
        if (cacheApi.has(kunci)) {
            await terapkanPayload(cacheApi.get(kunci));
            return;
        }

        const params = new URLSearchParams({ level: String(level) });
        if (kodeInduk) params.set('kode_induk', kodeInduk);
        if (komoditasId) params.set('komoditas_id', String(komoditasId));

        try {
            const res = await fetch(`/api/peta?${params.toString()}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const payload = await res.json();
            cacheApi.set(kunci, payload);
            await terapkanPayload(payload);
        } catch {
            // Biarkan tampilan level sebelumnya tetap aktif jika jaringan gagal.
        }
    }

    await terapkanPayload(awal.payload);
}

mulai();
