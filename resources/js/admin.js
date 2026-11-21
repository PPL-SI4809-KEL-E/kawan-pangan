const app = document.getElementById('app');
const toggle = document.getElementById('menu-toggle');
const sidebar = document.getElementById('sidebar');
const mobile = window.matchMedia('(max-width: 1023px)');

function setMenu(open) {
    app.classList.toggle('is-menu-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    sidebar.inert = mobile.matches && !open;
}

toggle.addEventListener('click', () => setMenu(!app.classList.contains('is-menu-open')));
document.getElementById('overlay').addEventListener('click', () => setMenu(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && app.classList.contains('is-menu-open')) {
        setMenu(false);
        toggle.focus();
    }
});
mobile.addEventListener('change', () => setMenu(false));
setMenu(false);

// Sidebar bisa diciutkan di desktop; pilihan disimpan per perangkat (PSKE-88).
const collapse = document.getElementById('sidebar-collapse');
const KUNCI_CIUT = 'kp-sidebar-ciut';

function setCiut(ciut) {
    app.classList.toggle('is-collapsed', ciut);
    collapse?.setAttribute('aria-pressed', String(ciut));
    collapse?.setAttribute('aria-label', ciut ? 'Lebarkan menu' : 'Ciutkan menu');
}

try {
    setCiut(window.localStorage.getItem(KUNCI_CIUT) === '1');
} catch {
    setCiut(false);
}

collapse?.addEventListener('click', () => {
    const ciut = !app.classList.contains('is-collapsed');
    setCiut(ciut);
    try {
        window.localStorage.setItem(KUNCI_CIUT, ciut ? '1' : '0');
    } catch {
        // Penyimpanan diblokir: pilihan berlaku sampai halaman dimuat ulang.
    }
});

// Cari menu di sidebar; Ctrl/Cmd + K untuk fokus ke kolom cari.
const cariMenu = document.getElementById('sidebar-search');
if (cariMenu) {
    const itemMenu = [...sidebar.querySelectorAll('[data-nav-item]')];
    const kosong = document.getElementById('sidebar-empty');

    cariMenu.addEventListener('input', () => {
        const kata = cariMenu.value.trim().toLowerCase();
        let tampil = 0;
        itemMenu.forEach((item) => {
            const cocok = !kata || item.textContent.toLowerCase().includes(kata);
            item.hidden = !cocok;
            if (cocok) tampil += 1;
        });
        sidebar.querySelectorAll('[data-nav-group]').forEach((judul) => {
            const daftar = judul.nextElementSibling;
            judul.hidden = daftar ? [...daftar.children].every((item) => item.hidden) : false;
        });
        if (kosong) kosong.hidden = tampil > 0;
    });

    cariMenu.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            sidebar.querySelector('[data-nav-item]:not([hidden]) a.nav-link')?.click();
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            if (mobile.matches) setMenu(true);
            if (app.classList.contains('is-collapsed')) setCiut(false);
            cariMenu.focus();
        }
    });
}

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

const kodeWilayah = document.querySelector('[data-opsi-wilayah]');
if (kodeWilayah) {
    let timer;
    let pending;
    const daftar = document.getElementById('opsi-wilayah');
    const hasil = document.querySelector('[data-wilayah-hasil]');
    kodeWilayah.addEventListener('input', () => {
        clearTimeout(timer);
        pending?.abort();
        daftar.replaceChildren();
        hasil.textContent = '';
        const q = kodeWilayah.value.trim();
        if (!q) return;
        timer = setTimeout(async () => {
            const controller = new AbortController();
            pending = controller;
            try {
                const url = new URL(kodeWilayah.dataset.opsiWilayah);
                url.searchParams.set('q', q);
                const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Pencarian gagal');
                const { data } = await response.json();
                if (controller.signal.aborted) return;
                daftar.replaceChildren(...data.map((wilayah) => {
                    const option = document.createElement('option');
                    option.value = wilayah.kode;
                    option.label = wilayah.nama;
                    return option;
                }));
                hasil.textContent = data.find((wilayah) => wilayah.kode === q)?.nama
                    ?? (data.length ? `${data.length} pilihan wilayah tersedia.` : 'Kode belum ditemukan. Periksa master wilayah.');
            } catch (error) {
                if (error.name !== 'AbortError') hasil.textContent = 'Pencarian belum tersedia. Isi kode dari master wilayah.';
            }
        }, 250);
    });
}

document.querySelector('[data-impor]')?.addEventListener('submit', (event) => {
    event.currentTarget.querySelector('button[type="submit"]').disabled = true;
    event.currentTarget.querySelector('[data-proses]').hidden = false;
});
