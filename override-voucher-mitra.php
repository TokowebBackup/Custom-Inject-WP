<?php

/**
 * Plugin Name: Override Voucher Mitra
 * Description: Menambahkan tipe kupon "Diskon Mitra" dengan fitur diskon khusus untuk role Mitra, serta menampilkan informasi DP dan Pelunasan di halaman cart.
 * Version: 1.0
 * Author: Puji Ermanto | UCOK AKA
 * Author URI: https://ucokaka.com
 * Text Domain: override-voucher-mitra
 */
/**
 * Plugin Name: Override Voucher Mitra
 * Description: Menambahkan tipe kupon "Diskon Mitra" dengan fitur diskon khusus untuk role Mitra, serta menampilkan informasi DP dan Pelunasan di halaman cart.
 * Version: 1.0
 * Author: Puji Ermanto | UCOK AKA
 * Author URI: https://ucokaka.com
 * Text Domain: override-voucher-mitra
 */
// ============================
// 1️⃣ Tambah field Diskon Mitra di Product Data
// ============================
add_action('woocommerce_product_options_general_product_data', function () {
    woocommerce_wp_text_input([
        'id' => '_mitra_discount',
        'label' => __('Diskon Mitra (%)', 'woocommerce'),
        'desc_tip' => true,
        'description' => __('Masukkan persentase diskon khusus untuk role Mitra. Contoh: 10 = 10%', 'woocommerce'),
        'type' => 'number',
        'custom_attributes' => [
            'step' => '0.01',
            'min' => '0'
        ],
    ]);
});

// ============================
// 2️⃣ Simpan field Diskon Mitra
// ============================
add_action('woocommerce_admin_process_product_object', function ($product) {
    if (isset($_POST['_mitra_discount'])) {
        $product->update_meta_data('_mitra_discount', sanitize_text_field($_POST['_mitra_discount']));
    }
});

// Tambahkan field "Diskon Mitra (%)" di tab General coupon
add_action('woocommerce_coupon_options', function ($coupon_id) {
    $discount_value = get_post_meta($coupon_id, '_mitra_discount', true);

    echo '<div class="options_group _mitra_discount_field" style="display:none;">';
    woocommerce_wp_text_input([
        'id' => '_mitra_discount',
        'label' => __('Diskon Mitra (%)', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => true,
        'description' => __('Masukkan persentase diskon mitra global (contoh: 10 = 10%)', 'woocommerce'),
        'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        'value' => $discount_value ?: ''
    ]);
    echo '</div>';
});



// Simpan nilai field
add_action('woocommerce_coupon_options_save', function ($post_id) {
    if (isset($_POST['_mitra_discount'])) {
        update_post_meta($post_id, '_mitra_discount', sanitize_text_field($_POST['_mitra_discount']));
    }
});

// ============================
// 3️⃣ Tambah tipe diskon baru di dropdown kupon
// ============================
add_filter('woocommerce_coupon_discount_types', function ($discount_types) {
    $discount_types['mitra_discount'] = __('Diskon Mitra', 'woocommerce');
    return $discount_types;
});

// ============================
// Tambahkan script agar field muncul otomatis
// ============================
add_action('admin_footer', function () {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'shop_coupon') :
?>
        <script>
            jQuery(document).ready(function($) {
                function toggleMitraField() {
                    var val = $('#discount_type').val();
                    if (val === 'mitra_discount') {
                        $('._mitra_discount_field').show(); // ✅ ubah jadi class
                    } else {
                        $('._mitra_discount_field').hide();
                    }
                }
                toggleMitraField();
                $(document).on('change', '#discount_type', toggleMitraField);
            });
        </script>
<?php
    endif;
});

// ============================
// 4️⃣ Simpan nilai _mitra_discount di kupon
// ============================
add_action('woocommerce_coupon_options_save', function ($post_id) {
    if (isset($_POST['_mitra_discount'])) {
        update_post_meta($post_id, '_mitra_discount', sanitize_text_field($_POST['_mitra_discount']));
    }
});

// 🚫 Nonaktifkan perhitungan kupon default untuk tipe 'mitra_discount'
add_filter('woocommerce_coupon_is_valid_for_cart', function ($valid, $coupon) {
    if ($coupon->get_discount_type() === 'mitra_discount') {
        return false; // biar WooCommerce tidak hitung ulang
    }
    return $valid;
}, 10, 2);

// add_action('woocommerce_cart_calculate_fees', function ($cart) {
//     if (is_admin() && !defined('DOING_AJAX')) return;

//     $user = wp_get_current_user();
//     if (!in_array('mitra', (array)$user->roles)) return;

//     $original_total = 0;
//     $total_qty = 0;

//     foreach ($cart->get_cart() as $item) {
//         $product = $item['data'];
//         $qty = $item['quantity'];
//         $price = (float) $product->get_regular_price();

//         $original_total += $price * $qty;
//         $total_qty += $qty;
//     }

//     // 🔎 Cek apakah ada kupon aktif bertipe "mitra_discount"
//     $applied_coupons = $cart->get_applied_coupons();
//     $voucher_percent = 0;

//     foreach ($applied_coupons as $code) {
//         $coupon = new WC_Coupon($code);
//         if ($coupon->get_discount_type() === 'mitra_discount') {
//             $voucher_percent = (float) get_post_meta($coupon->get_id(), '_mitra_discount', true);
//             break;
//         }
//     }

//     // 🧮 Jika tidak ada kupon aktif, pakai logika range pembelian
//     if ($voucher_percent <= 0) {
//         if ($total_qty <= 10) {
//             $voucher_percent = 50;
//         } elseif ($total_qty <= 20) {
//             $voucher_percent = 80;
//         } else {
//             $voucher_percent = 90;
//         }
//     }

//     // 💡 Cek apakah cart berisi produk dengan deposit (DP)
//     $is_deposit = false;
//     foreach ($cart->get_cart() as $item) {
//         if (!empty($item['deposit']) && $item['deposit'] === 'yes') {
//             $is_deposit = true;
//             break;
//         }
//     }

//     // 💰 Hitung total diskon sesuai mode pembayaran
//     if ($is_deposit) {
//         // 💡 Ambil DP asli dari YITH (sudah hitung harga mitra otomatis)
//         $yith_deposit_total = 0;
//         foreach ($cart->get_cart() as $item) {
//             if (!empty($item['deposit']) && $item['deposit'] === 'yes') {
//                 $yith_deposit_total += $item['line_total']; // harga DP aktual dari YITH
//             }
//         }

//         // Jika tidak ketemu nilai DP (fallback ke 50%)
//         if ($yith_deposit_total <= 0) {
//             $yith_deposit_total = ($original_total * 0.5) * 0.5; // harga mitra + DP default
//         }

//         // Terapkan diskon voucher hanya pada nilai DP aktual
//         $discount_amount = ($voucher_percent / 100) * $yith_deposit_total;
//         $final_checkout = $yith_deposit_total - $discount_amount;

//         // Pelunasan tetap nilai DP asli (tanpa potongan)
//         $pelunasan = $yith_deposit_total;
//     }


//     // 💾 Simpan data ke session (buat tampilan cart)
//     WC()->session->set('mitra_total_discount', $discount_amount);
//     WC()->session->set('mitra_dp_amount', $final_checkout);
//     WC()->session->set('mitra_pelunasan_amount', $pelunasan);

//     // ✅ Tambahkan diskon ke cart
//     $cart->add_fee(sprintf('Diskon Mitra (%d%%)', $voucher_percent), -$discount_amount);
// }, 999);
// add_action('woocommerce_cart_calculate_fees', function ($cart) {
//     if (is_admin() && !defined('DOING_AJAX')) return;

//     $user = wp_get_current_user();
//     if (!in_array('mitra', (array)$user->roles)) return;

//     $original_total = 0;
//     $total_qty = 0;
//     $has_deposit = false;

//     foreach ($cart->get_cart() as $item) {
//         $product = $item['data'];
//         $qty = $item['quantity'];
//         $price = (float)$product->get_regular_price();

//         $original_total += $price * $qty;
//         $total_qty += $qty;

//         // ✅ YITH menyimpan data deposit di '_deposit_info'
//         if (!empty($item['_deposit_info'])) {
//             $has_deposit = true;
//         }
//     }

//     // 🔎 Cari kupon mitra aktif
//     $voucher_percent = 0;
//     foreach ($cart->get_applied_coupons() as $code) {
//         $coupon = new WC_Coupon($code);
//         if ($coupon->get_discount_type() === 'mitra_discount') {
//             $voucher_percent = (float)get_post_meta($coupon->get_id(), '_mitra_discount', true);
//             break;
//         }
//     }

//     // default fallback
//     if ($voucher_percent <= 0) {
//         $voucher_percent = 50;
//     }

//     // 💰 Ambil total DP aktual dari YITH
//     $yith_deposit_total = 0;
//     if ($has_deposit) {
//         foreach ($cart->get_cart() as $item) {
//             if (!empty($item['_deposit_info']['deposit_amount'])) {
//                 $yith_deposit_total += (float)$item['_deposit_info']['deposit_amount'] * (int)$item['quantity'];
//             }
//         }
//     }

//     if ($yith_deposit_total <= 0) {
//         // fallback ke 50% dari total harga mitra
//         $yith_deposit_total = $original_total * 0.5;
//     }

//     // 🧮 Hitung diskon berdasarkan DP
//     $discount_amount = ($voucher_percent / 100) * $yith_deposit_total;
//     $final_checkout = $yith_deposit_total - $discount_amount;
//     $pelunasan = $yith_deposit_total; // pelunasan tetap harga DP asli

//     // 💾 simpan ke session
//     WC()->session->set('mitra_total_discount', $discount_amount);
//     WC()->session->set('mitra_dp_amount', $final_checkout);
//     WC()->session->set('mitra_pelunasan_amount', $pelunasan);

//     // ✅ tampilkan di cart
//     $cart->add_fee(sprintf('Diskon Mitra (%d%%)', $voucher_percent), -$discount_amount);
// }, 999);

// add_action('woocommerce_cart_calculate_fees', function ($cart) {
//     if (is_admin() && !defined('DOING_AJAX')) return;

//     $user = wp_get_current_user();
//     if (!in_array('mitra', (array)$user->roles)) return;

//     $original_total = 0;
//     $total_qty = 0;
//     $has_deposit = false;

//     foreach ($cart->get_cart() as $item) {
//         $product = $item['data'];
//         $qty = $item['quantity'];
//         $price = (float)$product->get_regular_price();

//         $original_total += $price * $qty;
//         $total_qty += $qty;

//         if (!empty($item['_deposit_info'])) {
//             $has_deposit = true;
//         }
//     }

//     // 🔎 Cek kupon Diskon Mitra
//     $voucher_percent = 0;
//     foreach ($cart->get_applied_coupons() as $code) {
//         $coupon = new WC_Coupon($code);
//         if ($coupon->get_discount_type() === 'mitra_discount') {
//             $voucher_percent = (float)get_post_meta($coupon->get_id(), '_mitra_discount', true);
//             break;
//         }
//     }

//     if ($voucher_percent <= 0) $voucher_percent = 50;

//     // 💰 Ambil total DP dari YITH
//     $yith_deposit_total = 0;
//     if ($has_deposit) {
//         foreach ($cart->get_cart() as $item) {
//             if (!empty($item['_deposit_info']['deposit_amount'])) {
//                 $yith_deposit_total += (float)$item['_deposit_info']['deposit_amount'] * (int)$item['quantity'];
//             }
//         }
//     }

//     if ($yith_deposit_total <= 0) {
//         $yith_deposit_total = $original_total * 0.5;
//     }

//     // 🧮 Hitung nilai total harga setelah diskon
//     $total_setelah_diskon = $original_total - ($original_total * ($voucher_percent / 100));

//     // 💰 DP dan Pelunasan dari harga setelah diskon
//     $deposit_amount = $total_setelah_diskon * 0.5;
//     $pelunasan = $total_setelah_diskon * 0.5;
//     $discount_amount = $original_total * ($voucher_percent / 100);

//     // 💾 Simpan data
//     WC()->session->set('mitra_total_discount', $discount_amount);
//     WC()->session->set('mitra_dp_amount', $deposit_amount);
//     WC()->session->set('mitra_pelunasan_amount', $pelunasan);

//     // ✅ Tampilkan diskon di cart
//     $cart->add_fee(sprintf('Diskon Mitra (%d%%)', $voucher_percent), -$discount_amount);
// }, 999);

add_action('woocommerce_cart_calculate_fees', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $user = wp_get_current_user();
    if (!in_array('mitra', (array)$user->roles)) return;

    $original_total = 0;
    $has_deposit = false;

    foreach ($cart->get_cart() as $item) {
        $product = $item['data'];
        $price = (float)$product->get_regular_price();
        $qty = $item['quantity'];
        $original_total += $price * $qty;

        if (!empty($item['_deposit_info'])) $has_deposit = true;
    }

    // Ambil diskon kupon mitra
    $voucher_percent = 0;
    foreach ($cart->get_applied_coupons() as $code) {
        $coupon = new WC_Coupon($code);
        if ($coupon->get_discount_type() === 'mitra_discount') {
            $voucher_percent = (float)get_post_meta($coupon->get_id(), '_mitra_discount', true);
            break;
        }
    }

    if ($voucher_percent <= 0) $voucher_percent = 25; // default

    // 💰 Langkah 1: harga mitra 50% dari harga normal
    $harga_mitra = $original_total * 0.5;

    // 💰 Langkah 2: DP 50% dari harga mitra
    $dp_mitra = $harga_mitra * 0.5;

    // 💰 Langkah 3: diskon kupon dari nilai DP
    $discount_amount = ($voucher_percent / 100) * $dp_mitra;

    // 💰 Langkah 4: nilai akhir checkout (DP setelah diskon)
    $final_checkout = $dp_mitra - $discount_amount;

    // 💰 Langkah 5: Pelunasan tetap DP asli (tanpa potongan)
    $pelunasan = $dp_mitra;

    // Simpan ke session
    WC()->session->set('mitra_total_discount', $discount_amount);
    WC()->session->set('mitra_dp_amount', $final_checkout);
    WC()->session->set('mitra_pelunasan_amount', $pelunasan);

    // Tambahkan fee negatif (diskon)
    $cart->add_fee(sprintf('Diskon Mitra (%d%%)', $voucher_percent), -$discount_amount);
}, 999);




// ============================
// 💡 Terapkan juga diskon Mitra ke harga DEPOSIT YITH
// ============================
add_filter('yith_wcdp_get_deposit_amount', function ($deposit_amount, $product_id, $cart_item) {
    if (!is_user_logged_in()) return $deposit_amount;

    $user = wp_get_current_user();
    if (!in_array('mitra', (array)$user->roles)) return $deposit_amount;

    // Ambil harga produk asli (bukan harga deposit)
    $product = wc_get_product($product_id);
    $price = (float)$product->get_regular_price();

    // Terapkan diskon mitra 50% dari total harga produk
    $discount_percent = 50;
    $discounted_total = $price - ($price * ($discount_percent / 100));

    // Ambil rate DP (default 50%)
    $deposit_rate = (float)get_post_meta($product_id, '_yith_wcdp_deposit_rate', true);
    if ($deposit_rate <= 0) $deposit_rate = 50;

    // Hitung DP dari total setelah diskon (bukan dari deposit)
    $deposit_amount = ($deposit_rate / 100) * $discounted_total;

    return $deposit_amount;
}, 10, 3);



// ============================
// 💡 Terapkan diskon Mitra ke tampilan harga DEPOSIT di halaman produk
// ============================
add_filter('yith_wcdp_get_product_price', function ($price, $product) {
    if (!is_user_logged_in()) return $price;

    $user = wp_get_current_user();
    if (!in_array('mitra', (array) $user->roles)) return $price;

    // Cek apakah kupon mitra aktif
    $applied_coupons = WC()->cart ? WC()->cart->get_applied_coupons() : [];
    $mitra_coupon = null;

    foreach ($applied_coupons as $code) {
        $coupon = new WC_Coupon($code);
        if ($coupon->get_discount_type() === 'mitra_discount') {
            $mitra_coupon = $coupon;
            break;
        }
    }

    if (!$mitra_coupon) return $price;

    $discount_percent = (float) get_post_meta($mitra_coupon->get_id(), '_mitra_discount', true);
    if ($discount_percent <= 0) return $price;

    // Terapkan potongan ke harga dasar produk
    $discounted_price = $price - ($price * ($discount_percent / 100));

    return $discounted_price;
}, 10, 2);

// ============================
// 💡 Tampilkan harga deposit yang sudah kena diskon di halaman produk (kompatibel semua versi YITH)
// ============================
add_filter('yith_wcdp_single_product_price_html', function ($html, $product) {
    if (!is_user_logged_in()) return $html;

    $user = wp_get_current_user();
    if (!in_array('mitra', (array) $user->roles)) return $html;

    // Cari kupon mitra aktif
    $applied_coupons = WC()->cart ? WC()->cart->get_applied_coupons() : [];
    $mitra_coupon = null;

    foreach ($applied_coupons as $code) {
        $coupon = new WC_Coupon($code);
        if ($coupon->get_discount_type() === 'mitra_discount') {
            $mitra_coupon = $coupon;
            break;
        }
    }

    if (!$mitra_coupon) return $html;

    $discount_percent = (float) get_post_meta($mitra_coupon->get_id(), '_mitra_discount', true);
    if ($discount_percent <= 0) return $html;

    // Ambil harga produk asli
    $price = (float) $product->get_price();
    $discounted_price = $price - ($price * ($discount_percent / 100));

    // Ambil nilai deposit rate dari meta produk
    $deposit_rate = (float) get_post_meta($product->get_id(), '_yith_wcdp_deposit_rate', true);
    if ($deposit_rate <= 0) $deposit_rate = 30;

    $deposit_amount = ($deposit_rate / 100) * $discounted_price;
    $balance_amount = $discounted_price - $deposit_amount;

    // 🔽 Bangun ulang tampilan HTML agar mencerminkan harga baru
    $html  = '<div class="yith-wcdp-product-prices">';
    $html .= '<p><strong>Pay full amount</strong><br>' . wc_price($discounted_price) . '</p>';
    $html .= '<p><strong>Pay deposit</strong><br>' . wc_price($deposit_amount) . '<br>';
    $html .= '<small>Balance payment will be required on ' . date_i18n('F j, Y', strtotime('+10 days')) . '</small></p>';
    $html .= '</div>';

    return $html;
}, 10, 2);

// ============================
// 💡 Terapkan diskon Mitra langsung ke harga regular & sale (agar muncul di single product YITH)
// ============================
add_filter('woocommerce_product_get_regular_price', 'mitra_apply_price_discount', 99, 2);
add_filter('woocommerce_product_get_sale_price', 'mitra_apply_price_discount', 99, 2);
add_filter('woocommerce_product_get_price', 'mitra_apply_price_discount', 99, 2);

// ============================
// 💡 Terapkan diskon langsung untuk user role 'mitra' di single product (tanpa butuh kupon aktif)
// ============================
add_filter('woocommerce_get_price_html', function ($price_html, $product) {
    if (!is_user_logged_in()) return $price_html;

    $user = wp_get_current_user();
    if (!in_array('mitra', (array)$user->roles)) return $price_html;

    // Ambil diskon default (misalnya dari opsi global atau meta kupon pertama)
    $discount_percent = 50; // Ganti sesuai nilai diskon mitra default kamu

    $regular_price = (float) $product->get_regular_price();
    if ($regular_price <= 0) return $price_html;

    $discounted_price = $regular_price - ($regular_price * ($discount_percent / 100));

    // Ganti tampilan HTML harga
    $price_html = '<span class="price"><del>' . wc_price($regular_price) . '</del> <ins>' . wc_price($discounted_price) . '</ins></span>';

    return $price_html;
}, 20, 2);

// ============================
// 6️⃣ Override tampilan kupon Mitra di cart
// ============================
add_filter('woocommerce_cart_totals_coupon_html', function ($html, $coupon) {
    if ($coupon->get_discount_type() === 'mitra_discount') {
        $percent = get_post_meta($coupon->get_id(), '_mitra_discount', true);
        $discount = WC()->session->get('mitra_total_discount') ?: 0;

        $custom_row = sprintf(
            '<tr class="c-cart__totals-discount cart-discount coupon-mitra">
                <th class="c-cart__sub-sub-header" style="color:#3A0BF4;">Diskon Mitra (%s%%)</th>
                <td class="c-cart__totals-price" style="color:#3A0BF4;">–<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">Rp</span>%s</span></td>
            </tr>',
            esc_html($percent ?: '0'),
            number_format($discount, 0, ',', '.')
        );
        return $custom_row;
    }
    return $html;
}, 10, 2);


// ============================
// 💡 Perbaiki tampilan harga di single product (pilihan full & deposit) agar ikut Diskon Mitra
// ============================
add_filter('yith_wcdp_single_add_to_cart_fields_html', function ($html, $product) {
    if (!is_user_logged_in()) return $html;

    $user = wp_get_current_user();
    if (!in_array('mitra', (array)$user->roles)) return $html;

    // Cari kupon mitra aktif
    $applied_coupons = WC()->cart ? WC()->cart->get_applied_coupons() : [];
    $mitra_coupon = null;
    foreach ($applied_coupons as $code) {
        $coupon = new WC_Coupon($code);
        if ($coupon->get_discount_type() === 'mitra_discount') {
            $mitra_coupon = $coupon;
            break;
        }
    }
    if (!$mitra_coupon) return $html;

    $discount_percent = (float) get_post_meta($mitra_coupon->get_id(), '_mitra_discount', true);
    if ($discount_percent <= 0) return $html;

    // Ambil harga produk asli
    $price = (float) $product->get_regular_price();
    if ($price <= 0) return $html;

    // 💡 Terapkan diskon ke total harga dulu (baru dihitung DP & pelunasan)
    $discounted_total = $price - ($price * ($discount_percent / 100));

    // Ambil nilai deposit rate (default 50%)
    $deposit_rate = (float) get_post_meta($product->get_id(), '_yith_wcdp_deposit_rate', true);
    if ($deposit_rate <= 0) $deposit_rate = 50;

    // 💡 DP & Pelunasan dihitung dari total setelah diskon
    $deposit_amount = ($deposit_rate / 100) * $discounted_total;
    $balance_amount = $discounted_total - $deposit_amount;

    // 🔁 Bangun ulang tampilan harga
    $html = '
    <div class="yith-wcdp-single-add-to-cart-fields" data-deposit-type="rate" data-deposit-amount="' . esc_attr($deposit_amount) . '" data-deposit-rate="' . esc_attr($deposit_rate) . '">
        <label class="full">
            <input type="radio" name="payment_type" value="full" checked="checked">
            <span class="label">
                Pay full amount
                <span class="price-label full-price">
                    <span class="price"><del>' . wc_price($price) . '</del> <ins>' . wc_price($discounted_total) . '</ins></span>
                </span>
            </span>
        </label>
        <label class="deposit">
            <input type="radio" name="payment_type" value="deposit">
            <span class="label">
                Pay deposit
                <span class="price-label deposit-price">' . wc_price($deposit_amount) . '</span>
                <small class="yith-wcdp-expiration-notice">
                    Balance payment will be required on <span class="expiration-date">' . date_i18n('F j, Y', strtotime('+10 days')) . '</span> (' . wc_price($balance_amount) . ')
                </small>
            </span>
        </label>
    </div>';

    return $html;
}, 20, 2);


// ============================
// 7️⃣ Tampilkan DP & Pelunasan di cart (hanya jika ada produk dengan deposit aktif)
// ============================
add_action('woocommerce_cart_totals_before_order_total', function () {
    $total_discount = WC()->session->get('mitra_total_discount') ?: 0;
    $dp = WC()->session->get('mitra_dp_amount') ?: 0;
    $pelunasan = WC()->session->get('mitra_pelunasan_amount') ?: 0;

    if ($total_discount <= 0) return;

    // Hitung kembali harga mitra berdasarkan session DP dan diskon
    $harga_mitra = $dp + $total_discount; // DP asli sebelum diskon
    $pelunasan = $harga_mitra; // pelunasan sama dengan DP asli

    echo '<tr class="fee cart-mitra-harga">
        <th class="c-cart__sub-header" style="color:#3A0BF4;">Harga Mitra (50%)</th>
        <td class="c-cart__totals-price" style="color:#3A0BF4;">' . wc_price($harga_mitra * 2) . '</td>
    </tr>';

    echo '<tr class="fee cart-mitra-dp" style="border-top:10px solid transparent;">
        <th class="c-cart__sub-header" style="color:#3A0BF4;">DP (50%)</th>
        <td class="c-cart__totals-price" style="color:#3A0BF4;">' . wc_price($dp) . '</td>
    </tr>';

    echo '<tr class="fee cart-mitra-pelunasan">
        <th class="c-cart__sub-header" style="color:#3A0BF4;">Pelunasan (50%)</th>
        <td class="c-cart__totals-price" style="color:#3A0BF4;">' . wc_price($pelunasan) . '</td>
    </tr>';
});



// ============================
// 💡 Tampilkan harga diskon di MINI CART (fix untuk theme yang override template)
// ============================
add_filter('woocommerce_widget_cart_item_quantity', function ($quantity_html, $cart_item, $cart_item_key) {
    if (!is_user_logged_in()) return $quantity_html;

    $user = wp_get_current_user();
    if (!in_array('mitra', (array)$user->roles)) return $quantity_html;

    $product = $cart_item['data'];
    $qty = $cart_item['quantity'];
    $regular_price = (float) $product->get_regular_price();
    if ($regular_price <= 0) return $quantity_html;

    // Ambil diskon mitra (dari kupon atau default 50%)
    $discount_percent = 50;
    $applied_coupons = WC()->cart ? WC()->cart->get_applied_coupons() : [];
    foreach ($applied_coupons as $code) {
        $coupon = new WC_Coupon($code);
        if ($coupon->get_discount_type() === 'mitra_discount') {
            $discount_percent = (float) get_post_meta($coupon->get_id(), '_mitra_discount', true);
            break;
        }
    }

    // Hitung harga diskon
    $discounted_price = $regular_price - ($regular_price * ($discount_percent / 100));

    // Jika pakai deposit
    if (!empty($cart_item['deposit']) && $cart_item['deposit'] === 'yes') {
        $deposit_rate = (float) get_post_meta($product->get_id(), '_yith_wcdp_deposit_rate', true);
        if ($deposit_rate <= 0) $deposit_rate = 50;
        $discounted_price = ($deposit_rate / 100) * $discounted_price;
    }

    $discounted_total = $discounted_price * $qty;

    // 🔁 Ganti tampilan "1 × RpXXX"
    $quantity_html = sprintf(
        '<span class="quantity">%d × <del>%s</del> <ins>%s</ins></span>',
        $qty,
        wc_price($regular_price),
        wc_price($discounted_price)
    );

    return $quantity_html;
}, 20, 3);


// ============================
// 💡 Ubah subtotal mini cart agar ikut diskon mitra
// ============================
add_filter('woocommerce_widget_shopping_cart_total', function ($subtotal_html) {
    if (!is_user_logged_in()) return $subtotal_html;

    $user = wp_get_current_user();
    if (!in_array('mitra', (array)$user->roles)) return $subtotal_html;

    $cart_total = WC()->cart->get_subtotal();
    $discount_percent = 50;

    $applied_coupons = WC()->cart ? WC()->cart->get_applied_coupons() : [];
    foreach ($applied_coupons as $code) {
        $coupon = new WC_Coupon($code);
        if ($coupon->get_discount_type() === 'mitra_discount') {
            $discount_percent = (float) get_post_meta($coupon->get_id(), '_mitra_discount', true);
            break;
        }
    }

    $discounted_total = $cart_total - ($cart_total * ($discount_percent / 100));

    $subtotal_html = sprintf(
        '<span class="c-product-list-widget__total-title">Subtotal</span> <del>%s</del> <ins>%s</ins>',
        wc_price($cart_total),
        wc_price($discounted_total)
    );

    return $subtotal_html;
}, 20);


/**
 * ✅ Koreksi total bayar agar mengikuti DP setelah diskon
 * (tanpa double potongan dari YITH Deposit)
 */
add_action('woocommerce_cart_totals_after_order_total', function () {
    $user = wp_get_current_user();
    if (!in_array('mitra', (array)$user->roles)) return;

    $dp = WC()->session->get('mitra_dp_amount') ?: 0;
    if ($dp <= 0) return;

    echo '<tr class="fee mitra-total-bayar" style="border-top: 2px solid #000;">
        <th class="c-cart__sub-header" style="
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 800;
            color: #000;
            font-size: 1.1em;
            padding-top: 12px;
        ">
            Total Bayar (Checkout DP)
        </th>
        <td class="c-cart__totals-price" style="
            font-size: 1.8em;
            font-weight: 900;
            color: #3A0BF4;
            text-shadow: 0 0 3px rgba(58, 11, 244, 0.2);
            padding-top: 12px;
        ">
            ' . wc_price($dp) . '
        </td>
    </tr>';
});

// 🔧 Sembunyikan baris "order-total" WooCommerce bawaan di cart
add_filter('woocommerce_cart_totals_order_total_html', function ($value) {
    $user = wp_get_current_user();
    if (in_array('mitra', (array)$user->roles)) {
        return ''; // kosongkan baris total default
    }
    return $value;
});

/**
 * 🎯 Tambah CSS hanya untuk user role mitra & jika ada diskon mitra
 */
add_action('wp_head', function () {
    if (!is_user_logged_in()) return;

    $user = wp_get_current_user();
    if (!in_array('mitra', (array)$user->roles)) return;

    // cek apakah session diskon mitra aktif
    $total_discount = WC()->session->get('mitra_total_discount') ?: 0;
    if ($total_discount <= 0) return;

    echo '<style>
        /* 🔒 Sembunyikan total bawaan WooCommerce + YITH Deposit hanya untuk mitra */
        .cart_totals .order-total,
        .cart_totals small.yith-wcdp-balance-info,
        .cart_totals .order-total td small {
            display: none !important;
        }

        /* 💡 Tampilkan hanya baris "Total Bayar (Checkout DP)" */
        .cart_totals .mitra-total-bayar {
            display: table-row !important;
        }

        /* 🎨 Style baru agar sejajar dengan DISKON MITRA */
        .cart_totals .cart-mitra-dp th,
        .cart_totals .cart-mitra-pelunasan th {
            padding-left: 0;
            font-weight: 600;
            color: #000;
            text-transform: none;
        }

        .cart_totals .cart-mitra-dp td,
        .cart_totals .cart-mitra-pelunasan td {
            text-align: right;
            font-weight: 600;
            color: #3A0BF4;
        }

        /* 💰 Style Total Bayar (Checkout DP) seperti bawaan YITH */
        .cart_totals .mitra-total-bayar th {
            text-transform: uppercase;
            font-weight: 700;
            color: #000;
        }

        .cart_totals .mitra-total-bayar td {
            font-size: 1.5em;
            font-weight: 800;
            color: #000;
            text-align: right;
        }

        /* ✨ Tambahkan sedikit jarak agar lebih elegan */
        .cart_totals .cart-mitra-dp,
        .cart_totals .cart-mitra-pelunasan,
        .cart_totals .mitra-total-bayar {
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
    </style>';
});
