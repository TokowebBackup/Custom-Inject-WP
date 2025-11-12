# Plugin: Kontak Informasi – Integrasi Tutor LMS

**Versi:** 1.2  
**Penulis:** Puji Ermanto  
**Deskripsi:**  
Plugin ini menambahkan *Custom Post Type* bernama **"Kontak Informasi"** ke dalam menu **Tutor LMS** di dashboard WordPress.  
Digunakan untuk menyimpan informasi kontak lembaga atau institusi, seperti alamat, email, telepon, dan media sosial.  
Terdapat fitur **alamat dinamis**, memungkinkan admin menambahkan lebih dari satu alamat dengan mudah.

---

## 🎯 Fitur Utama

✅ Menambahkan menu **Kontak Informasi** di bawah menu **Tutor LMS**  
✅ Input manual untuk:
- Alamat (bisa lebih dari satu)
- Email
- Nomor Telepon
- Instagram
- Twitter (X)
- YouTube  
✅ Mendukung penyimpanan otomatis menggunakan WordPress Meta API  
✅ Interface input yang rapi dan mudah digunakan (menggunakan HTML & JavaScript sederhana)

---

## 🧩 Struktur Meta Field

| Field | Deskripsi |
|-------|------------|
| `alamat[]` | Input dinamis untuk alamat (bisa lebih dari satu) |
| `email` | Email kontak utama |
| `telepon` | Nomor telepon |
| `instagram` | URL akun Instagram |
| `twitter` | URL akun Twitter/X |
| `youtube` | URL channel YouTube |

---

## 🪄 Cara Menggunakan

### 1️⃣ Instalasi
1. Salin file plugin ini ke folder:
```bash
/wp-content/plugins/kontak-informasi/
```  

2. Pastikan nama file utamanya misalnya:
kontak-informasi.php  

3. Aktifkan plugin melalui menu **Plugins > Installed Plugins** di dashboard WordPress.

---

### 2️⃣ Menambahkan Data Kontak
1. Setelah aktif, buka menu:  
Tutor LMS > Kontak Informasi  

2. Klik **Tambah Informasi**.
3. Isi data kontak sesuai kebutuhan.
4. Untuk menambahkan alamat lebih dari satu, klik tombol ➕ **Tambah Alamat**.
5. Simpan dengan menekan **Publish / Update**.

---

### 3️⃣ Menampilkan di Front-End
Gunakan kode PHP berikut di template tema kamu:

```php
protected function render()
    {
        $settings = $this->get_settings_for_display();
        $post_id = $settings['kontak_id'];

        if (!$post_id) {
            echo '<p>Pilih data kontak terlebih dahulu.</p>';
            return;
        }

        $email      = get_post_meta($post_id, 'email', true);
        $telepon    = get_post_meta($post_id, 'telepon', true);
        $instagram  = get_post_meta($post_id, 'instagram', true);
        $alamat_negara = get_post_meta($post_id, 'alamat_negara', true);

        $user_country = $this->get_user_country();
        if (!in_array($user_country, ['indonesia', 'japan'])) {
            $user_country = 'indonesia';
        }

        $alamat_indonesia = '';
        $alamat_japan = '';
        if (!empty($alamat_negara) && is_array($alamat_negara)) {
            foreach ($alamat_negara as $item) {
                $negara = strtolower($item['negara']);
                if ($negara === 'indonesia') $alamat_indonesia = $item['alamat'];
                if ($negara === 'japan') $alamat_japan = $item['alamat'];
            }
        }

        $alamat_user = ($user_country === 'japan') ? $alamat_japan : $alamat_indonesia;
        $city = 'Unknown';
        if (!empty($alamat_user)) {
            $parts = preg_split('/\s+/', trim($alamat_user));
            $city = ucfirst(strtolower(end($parts)));
        }

        // ==== STYLE GLOBAL (inline) ====
        echo '<style>
        .contact-wrapper {
            display: flex;
            flex-direction: column;
            gap: 20px;
            font-family: Poppins, sans-serif;
            color: #1e1e1e;
            line-height: 1.8;
            background: #f9fafb;
            padding: 24px 28px;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .contact-wrapper h3 {
            font-size: 20px;
            font-weight: 700;
            color: #111;
            margin-bottom: 4px;
        }
        .contact-office {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 40px;
        }
        .contact-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 24px;
            border-top: 1px solid #e5e7eb;
            padding-top: 14px;
            margin-top: 8px;
            font-size: 16px;
        }
        .contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        .contact-item i {
            font-size: 20px;
            transition: transform 0.3s ease, color 0.3s ease;
        }
        .contact-item:hover i {
            transform: scale(1.15);
        }
        .contact-item span {
            color: #222;
        }
        .fa-instagram { color: #E4405F; }
        .fa-phone { color: #1D9BF0; }
        .fa-location-dot { color: #34D399; }
    </style>';

        // ==== WRAPPER START ====
        echo '<div class="contact-wrapper">';

        // === BARIS 1 — OFFICE ===
        echo '<div class="contact-office">';
        echo '<div style="flex:1;min-width:300px;">';
        echo '<h3>INDONESIA OFFICE</h3>';
        echo '<p style="margin:0;font-size:16px;">' . esc_html($alamat_indonesia ?: 'Alamat belum diisi') . '</p>';
        echo '</div>';

        echo '<div style="flex:1;min-width:300px;text-align:left;">';
        echo '<h3>JAPAN OFFICE</h3>';
        echo '<p style="margin:0;font-size:16px;">' . esc_html($alamat_japan ?: 'Alamat belum diisi') . '</p>';
        echo '</div>';
        echo '</div>';

        // === BARIS 2 — KONTAK & SOSIAL ===
        echo '<div class="contact-info">';

        echo '<div class="contact-item">
        <i class="fa-brands fa-instagram"></i>
        <span>' . esc_html($instagram ?: '@hashiwaacademy') . '</span>
    </div>';

        if ($telepon) {
            echo '<div class="contact-item">
            <i class="fa-solid fa-phone"></i>
            <span>' . esc_html($telepon) . '</span>
        </div>';
        }

        echo '<div class="contact-item">
        <i class="fa-solid fa-location-dot"></i>
        <span>' . esc_html($city) . ', ' . ucfirst($user_country) . '</span>
    </div>';

        echo '</div>'; // contact-info
        echo '</div>'; // wrapper
    }
```  

⚙️ Kompatibilitas

WordPress: 5.5 – 6.x

PHP: 7.4 atau lebih baru

Tutor LMS: v2.0+

Tema yang direkomendasikan: Flatsome, Astra, Blocksy

🧠 Catatan Tambahan

Semua data disimpan menggunakan fungsi update_post_meta().

Untuk keamanan, semua field dilindungi menggunakan wp_nonce_field() dan sanitize_text_field().

Field alamat disimpan sebagai array, memudahkan pengambilan data untuk front-end.

🧑‍💻 Kontributor

Ditulis dan dibuat oleh:
Puji Ermanto
📧 Email: pujiermanto@gmail.com