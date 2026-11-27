# Data GeoJSON Batas Wilayah Peta Choropleth (PSKE-69)

Direktori ini menyimpan batas poligon wilayah yang disederhanakan (*simplified*) dan di-join ke data stok backend menggunakan properti `kode_wilayah` (sesuai kolom `wilayah.kode` Kemendagri):

- `provinsi.json`: Batas 38 provinsi (`properties.kode_wilayah` = `PP`, mis. `"32"`).
- `kabupaten/{kode_prov}.json`: Batas kabupaten/kota per provinsi (`properties.kode_wilayah` = `PP.KK`, mis. `"32.04"`), dimuat per level saat provinsi dibuka (`PSKE-70`).
- `kecamatan/{kode_kab}.json` (atau `kecamatan.json`): Batas kecamatan yang disederhanakan (`properties.kode_wilayah` = `PP.KK.CC`, mis. `"32.04.12"`), dimuat saat kabupaten/kota dibuka.

Bila berkas GeoJSON poligon untuk kecamatan tertentu belum tersedia, peta otomatis menampilkan titik status berdasarkan koordinat `lat`/`lng` wilayah agar navigasi tetap berfungsi.
