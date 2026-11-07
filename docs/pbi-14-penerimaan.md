# PBI-14 · Desain alur penerimaan dan keadaan khusus (PSKE-65)

Rujukan: KF-34, UC39, layar M-18. Epic EP-06 Pengiriman dan Penerimaan. Dependensi: PBI-02 (design system).

Desain ini menjadi acuan saat layar penerimaan dibangun di Laravel. Semua contoh memakai kelas dari design system, jadi HTML-nya bisa langsung dipindah ke Blade.

| Berkas | Subtask |
| --- | --- |
| `design-system/penerimaan-demo.html` | PSKE-66 Mockup M-18 versi ponsel |
| `design-system/keadaan-khusus.html` | PSKE-67 Keadaan khusus |

## Alur penerimaan (M-18)

Pengguna: Kepala Wilayah tujuan pengiriman. Layar dirancang untuk HP mulai 360px.

1. Kepala Wilayah membuka pengiriman berstatus **Tiba di tujuan** dari daftar pengiriman masuk.
2. Setiap item menampilkan jumlah dikirim. Kepala Wilayah mengisi **jumlah diterima**.
3. Sistem menghitung **selisih = diterima − dikirim** dan menampilkan badge:
   - `badge-aman` "Sesuai" bila selisih 0
   - `badge-waspada` "Selisih ±n satuan" bila selisih bukan 0
   - `badge-netral` "Belum diisi" bila jumlah kosong
4. Bila ada selisih, **alasan wajib dipilih**: Rusak saat pengiriman, Kurang dari surat jalan, Lebih dari surat jalan, atau Lainnya. Catatan opsional, maksimal 500 karakter.
5. **Bukti foto wajib**: minimal 1, maksimal 3 foto JPG/PNG, masing-masing maksimal 5 MB. Di HP tombolnya membuka kamera (`capture="environment"`).
6. Ringkasan menampilkan jumlah item dan jumlah item dengan selisih.
7. **Kirim konfirmasi** bersifat final. Stok wilayah tujuan bertambah sesuai jumlah diterima, bukan jumlah dikirim. **Simpan sebagai draf** belum mengubah stok.

Aturan isian:
- Jumlah diterima ≥ 0, desimal sampai 3 angka (`decimal(14,3)`, sama dengan kolom stok).
- Nilai 0 berarti barang tidak diterima sama sekali dan tetap perlu alasan.
- Validasi utama tetap di server. Skrip di halaman hanya membantu menghitung selisih.

## Keadaan khusus

| Keadaan | Kapan muncul | Komponen | Kalimat |
| --- | --- | --- | --- |
| Data kosong | Daftar belum berisi data | `.card` tengah + tombol `btn-secondary` | Jelaskan kenapa kosong dan langkah berikutnya |
| Riwayat kurang | Riwayat penyaluran < 30 hari, ketahanan tidak bisa dihitung | `badge-netral` "Riwayat kurang" + `alert-warning` | "Estimasi belum dapat dihitung. Riwayat penyaluran baru n dari 30 hari." |
| Validasi gagal | Form ditolak server | `alert-error` di atas form + `.has-error` dan `.field-error` per isian | Pesan per field menyebut cara memperbaikinya |
| Menunggu verifikasi | Akun berstatus MENUNGGU | `.card` + `badge-info` | Sama dengan pesan login: "Akun Anda masih menunggu verifikasi Superadmin. Anda belum dapat masuk." |
| Akses ditolak | Respons 403 dari policy (PBI-07) | `.card` + tombol kembali | Jangan menyebut isi atau nama data wilayah lain |

## Persetujuan (PSKE-68)

Desain ditunjukkan ke Product Owner (Pak Gelar) dan harus disetujui sebelum Sprint 3. Catat hasil persetujuan atau revisi di komentar tiket PSKE-68.
