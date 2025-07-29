# Produk by Kategori ACF – Elementor Widget

**Versi:** 1.2.0  
**Author:** Puji Ermanto | AKA Maman Salajami | AKA Deden Inyuuus | AKA TATANG
**Email:** pujiermanto@gmail.com  
**Portfolio:** [https://pujiermanto-portfolio.vercel.app](https://pujiermanto-portfolio.vercel.app)

## Deskripsi

Widget Elementor kustom yang menampilkan produk WooCommerce berdasarkan:

- Kategori produk (manual atau otomatis dari judul post saat ini)
- Jumlah produk ditampilkan
- Urutan produk (ASC / DESC)
- Role user (misalnya: mitra, customer, dll)
- ACF field `_visible_for_role` untuk menyaring produk berdasarkan role
- Tombol "View All Products" menuju halaman kategori

## Fitur Utama

✅ Select kategori produk manual atau otomatis dari judul post  
✅ Filter berdasarkan role user menggunakan ACF  
✅ Kontrol jumlah dan urutan produk  
✅ Teks tombol & gaya dapat dikustomisasi  
✅ Hindari duplikat produk antar instance widget  
✅ Kompatibel dengan Elementor dan WooCommerce

---

## Instalasi

1. Upload folder plugin ke dalam `/wp-content/plugins/`.
2. Aktifkan plugin dari **Dashboard → Plugins**.
3. Pastikan Elementor dan WooCommerce sudah aktif.
4. Tambahkan widget "Produk by Kategori ACF" di dalam editor Elementor.
5. Pilih kategori atau gunakan opsi "Gunakan Judul Post Saat Ini".

---

## Penggunaan

Widget ini dapat digunakan dalam:

- Halaman koleksi produk (seperti "Zeya Series", "Kamila Series", dll).
- Loop Post Elementor dengan judul post sebagai acuan kategori.
- Role-based produk view: hanya produk dengan `_visible_for_role` cocok yang tampil.

### Tips

- Untuk otomatisasi kategori dari post, pilih:  
  **"Gunakan Judul Post Saat Ini (Post Title)"** di pengaturan kategori.
- Untuk membatasi produk ke role tertentu, gunakan ACF key:  
  `_visible_for_role` dengan nilai seperti: `user`, `mitra`, dll.

---

## Screenshot

![Screenshot Widget](assets/screenshot-1.png)  
_Tampilan widget di Elementor dengan pilihan kategori_

---

## Changelog

### 1.2.0 – Juli 2025

- 🔄 Tambahan opsi "Gunakan Judul Post Saat Ini"
- 🔐 Filter berdasarkan role user via ACF
- 🛠️ Hindari duplikat produk antar widget
- 🎨 Kontrol styling tombol dan heading

---

## Kebutuhan

- PHP 7.4+
- WordPress 5.8+
- Elementor 3.0+
- WooCommerce 5.0+
- ACF (Advanced Custom Fields) jika menggunakan filter `_visible_for_role`

---

## Credits

Puji Ermanto  
[Portfolio Developer](https://pujiermanto-portfolio.vercel.app)  
