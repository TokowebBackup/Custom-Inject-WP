# Tutor Paid Topic Addon (Rupiah)
<img width="1920" height="2367" alt="2144a658-5638-4e85-9bcb-29118a24cba0" src="https://github.com/user-attachments/assets/93f2018b-7007-4ca0-a49f-901f7d604eb9" />

<img width="1919" height="964" alt="Screenshot 2025-11-17 214448" src="https://github.com/user-attachments/assets/3a7764c2-5b0d-466c-9d42-5c832fff78cf" />

Addon kustom untuk **Tutor LMS React Course Builder** yang memungkinkan kamu menambahkan **harga per topik/bab (Topic Price)** secara dinamis menggunakan REST API, dan menyembunyikan seluruh sistem harga default (Regular/Sale Price) di tab *Basics*.
<img width="1919" height="906" alt="Screenshot 2025-11-17 220210" src="https://github.com/user-attachments/assets/d89a80a4-47d5-4311-85bd-a92bfd641f5c" />

---

## 🎯 Fitur Utama

✅ Menambahkan input harga di setiap **Topic** (Curriculum tab).  
✅ Menyimpan harga otomatis lewat **REST API (AJAX)**.  
✅ Menampilkan **harga per topik** di sebelah judul topic (UI builder).  
✅ Menyembunyikan seluruh **Regular Price & Sale Price** di tab *Basics*.  
✅ Mengubah **Pricing Model Course → Free**, agar seluruh flow harga dikontrol dari per-topic.  

---

## 🧩 Struktur File

```bash
tutor-paid-topic-addon/
├── tutor-paid-topic-addon.php # File utama plugin
├── tutor-paid-topic.js # Logika front-end untuk input & display harga per topic
└── README.md # Dokumentasi plugin ini
```  


---

## ⚙️ Instalasi

1. Masuk ke folder plugin WordPress:
   ```bash
   /wp-content/plugins/

2. Buat folder baru:
```bash
mkdir tutor-paid-topic-addon
```

3. Upload dua file berikut:

- tutor-paid-topic-addon.php

- tutor-paid-topic.js

4. Aktifkan plugin di WordPress Admin → Plugins → Tutor Paid Topic Addon (Rupiah).  

🚀 Cara Kerja
1️⃣ Menyimpan Harga Per Topic

Saat instruktur membuat atau mengedit topik di Tutor LMS → Curriculum, akan muncul field:  

```bash
Masukkan harga topik (Rp)
```  

Setiap kali tombol Save Topic (✔) diklik:

- Harga dikirim via REST API → disimpan ke tabel custom wp_tutor_topic_price.

- Data tersimpan berdasarkan judul topik (topic_title).  


2️⃣ Menampilkan Harga di Judul Topik

Begitu halaman Curriculum dimuat:

- Script otomatis memanggil endpoint /tutor-paid-topic/v1/get-price.

- Menambahkan badge harga di sebelah judul topik, contoh:  

```bash
Lesson 1: Video Intro    Rp 50.000
```  

🧠 Teknologi & Hook yang Digunakan  

| Komponen                     | Fungsi                                                       |
| ---------------------------- | ------------------------------------------------------------ |
| `register_activation_hook`   | Membuat tabel `wp_tutor_topic_price`                         |
| `rest_api_init`              | Menyediakan endpoint `save-price` dan `get-price`            |
| `admin_enqueue_scripts`      | Memuat file JS khusus di halaman Course Builder Tutor LMS    |
| `admin_print_footer_scripts` | Menyembunyikan Regular/Sale Price dan mengatur Pricing Model |


🛠️ REST API Endpoint
POST /wp-json/tutor-paid-topic/v1/save-price

Request Body  

```json
{
  "title": "Lesson 1: Intro",
  "price": 50000
}
```   

Response  
```json
{
  "success": true,
  "message": "Harga topik disimpan."
}
```  

GET /wp-json/tutor-paid-topic/v1/get-price?title=Lesson%201%3A%20Intro

Response 
```json
{
  "price": 50000
}
```  

💾 Database Structure  

| Field         | Type         | Description                |
| ------------- | ------------ | -------------------------- |
| `id`          | BIGINT(20)   | Auto increment             |
| `topic_title` | VARCHAR(255) | Judul topik unik           |
| `price`       | INT(11)      | Harga topik (dalam Rupiah) |
| `created_at`  | DATETIME     | Timestamp pembuatan        |

Tabel: wp_tutor_topic_price  

🧩 Kompatibilitas

- Tutor LMS: v3.0.0 ke atas (React Course Builder)

- WordPress: 6.0+

- PHP: 7.4+  

👨‍💻 Author

Puji Ermanto
Senior Developer
✨ Tokoweb - Indonesia

📝 Versi

v1.5.0 — Tutor Paid Topic Addon (Rupiah)
Last updated: November 2025