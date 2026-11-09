// PSKE-81 · Peta beranda. Leaflet dimuat dari CDN di welcome.blade.php.
// Belum ada data stok; warna wilayah menyusul di PBI-15 (peta choropleth).

const elemen = document.getElementById('peta-beranda');

if (elemen && window.L) {
    const peta = window.L.map(elemen, { scrollWheelZoom: false }).setView([-2.5, 118], 4);

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(peta);
}
