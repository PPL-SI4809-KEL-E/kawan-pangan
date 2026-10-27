// PBI-05 · Interaksi formulir registrasi (M-07).
// Validasi utama tetap di server (RegistrasiRequest); skrip ini hanya membantu pengisian.

const form = document.getElementById('form-registrasi');

if (form) {
    const grupWilayah = document.getElementById('grup-wilayah');
    const grupInstansi = document.getElementById('grup-instansi');
    const selWilayah = [...grupWilayah.querySelectorAll('select')];
    const selInstansi = document.getElementById('instansi_id');
    let instansiDimuat = false;

    // ---------- Galat ----------
    const aturGalat = (key, pesan) => {
        const el = form.querySelector(`[data-error="${key}"]`);
        if (!el) return;
        el.textContent = pesan;
        el.closest('.field')?.classList.toggle('has-error', Boolean(pesan));
    };

    // Galat suatu field hilang begitu pengguna memperbaikinya.
    const hapusGalat = (e) => {
        const el = e.target;
        if (!el.name || el.type === 'file') return;
        const key = el.name.startsWith('wilayah_') ? 'kode_wilayah' : el.name;
        aturGalat(key, '');
    };
    form.addEventListener('input', hapusGalat);
    form.addEventListener('change', hapusGalat);

    // ---------- Dropdown ----------
    const ambil = async (url) => {
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return (await res.json()).data ?? [];
    };

    const isiOpsi = (select, daftar, nilai, teks) => {
        const label = select.dataset.label || 'Pilih instansi';
        select.replaceChildren(new Option(label, ''), ...daftar.map((d) => new Option(d[teks], d[nilai])));
    };

    async function muatWilayah(level, induk) {
        const sel = selWilayah[level];
        sel.disabled = true;
        sel.replaceChildren(new Option('Memuat…', ''));
        try {
            const url = new URL(grupWilayah.dataset.url, location.origin);
            if (induk) url.searchParams.set('induk', induk);
            const daftar = await ambil(url);
            isiOpsi(sel, daftar, 'kode', 'nama');
            sel.disabled = daftar.length === 0;
            // Kembalikan pilihan lama setelah validasi server gagal
            const lama = sel.dataset.old;
            if (lama && daftar.some((d) => String(d.kode) === lama)) {
                sel.value = lama;
                sel.dataset.old = '';
                if (level < selWilayah.length - 1) await muatWilayah(level + 1, lama);
            }
        } catch {
            sel.replaceChildren(new Option(`Gagal memuat ${sel.dataset.label}`, ''));
        }
    }

    selWilayah.forEach((sel, level) => {
        sel.addEventListener('change', () => {
            for (let i = level + 1; i < selWilayah.length; i++) {
                isiOpsi(selWilayah[i], [], 'kode', 'nama');
                selWilayah[i].disabled = true;
            }
            if (sel.value && level < selWilayah.length - 1) muatWilayah(level + 1, sel.value);
        });
    });

    async function muatInstansi() {
        selInstansi.replaceChildren(new Option('Memuat…', ''));
        try {
            isiOpsi(selInstansi, await ambil(grupInstansi.dataset.url), 'id', 'nama');
            instansiDimuat = true;
            if (selInstansi.dataset.old) selInstansi.value = selInstansi.dataset.old;
        } catch {
            selInstansi.replaceChildren(new Option('Gagal memuat instansi', ''));
        }
    }

    // ---------- PSKE-4: jenis akun ----------
    function aturRole(role) {
        grupWilayah.hidden = role !== 'KEPALA_WILAYAH';
        grupInstansi.hidden = role !== 'BADAN_RESMI';
        if (role === 'KEPALA_WILAYAH' && selWilayah[0].options.length <= 1) muatWilayah(0, null);
        if (role === 'BADAN_RESMI' && !instansiDimuat) muatInstansi();
    }

    form.querySelectorAll('input[name="role"]').forEach((r) => r.addEventListener('change', () => aturRole(r.value)));
    const terpilih = form.querySelector('input[name="role"]:checked');
    if (terpilih) aturRole(terpilih.value);

    // ---------- PSKE-5: unggah surat tugas ----------
    const fileInput = document.getElementById('surat_tugas');
    const dropzone = document.getElementById('dropzone');
    const maksByte = Number(fileInput.dataset.maksKb) * 1024;
    const EKSTENSI = ['pdf', 'jpg', 'jpeg'];
    const MIME = ['application/pdf', 'image/jpeg'];

    const formatUkuran = (b) =>
        b < 1024 * 1024 ? `${Math.max(1, Math.round(b / 1024))} KB` : `${(b / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;

    function periksaBerkas(file) {
        if (!file) return 'Surat tugas wajib diunggah.';
        const ext = file.name.split('.').pop().toLowerCase();
        if (!EKSTENSI.includes(ext) || (file.type && !MIME.includes(file.type))) return 'Format surat tugas harus PDF atau JPG.';
        if (file.size > maksByte) return `Ukuran surat tugas ${formatUkuran(file.size)}, maksimal 2 MB.`;
        return '';
    }

    function tampilkanBerkas() {
        const file = fileInput.files[0];
        const pesan = file ? periksaBerkas(file) : '';
        aturGalat('surat_tugas', pesan);
        const valid = file && !pesan;
        document.getElementById('dz-empty').hidden = Boolean(valid);
        document.getElementById('dz-file').hidden = !valid;
        if (valid) {
            document.getElementById('dz-nama').textContent = file.name;
            document.getElementById('dz-ukuran').textContent = formatUkuran(file.size);
            document.getElementById('dz-ext').textContent = file.name.toLowerCase().endsWith('.pdf') ? 'PDF' : 'JPG';
        } else if (file) {
            fileInput.value = '';
        }
    }

    fileInput.addEventListener('change', tampilkanBerkas);
    ['dragenter', 'dragover'].forEach((ev) => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.add('is-drag'); }));
    ['dragleave', 'drop'].forEach((ev) => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.remove('is-drag'); }));
    dropzone.addEventListener('drop', (e) => {
        if (!e.dataTransfer.files.length) return;
        fileInput.files = e.dataTransfer.files;
        tampilkanBerkas();
    });

    form.addEventListener('registrasi:validasi', (e) => {
        const pesan = periksaBerkas(fileInput.files[0]);
        if (pesan) {
            aturGalat('surat_tugas', pesan);
            e.preventDefault();
        }
    });

    // ---------- Kirim ----------
    form.addEventListener('submit', (e) => {
        const btn = document.getElementById('btn-daftar');
        // Pemeriksaan tambahan (misalnya berkas surat tugas) memanggil preventDefault() pada event ini.
        const lolos = form.dispatchEvent(new CustomEvent('registrasi:validasi', { cancelable: true }));
        if (!lolos) {
            e.preventDefault();
            form.querySelector('.has-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        btn.disabled = true;
        btn.textContent = 'Mengirim…';
    });
}
