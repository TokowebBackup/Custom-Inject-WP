<?php


/**
 * Plugin Name: Override Voucher Mitra (v3.3.3 AutoFix)
 * Description: Versi otomatis yang mendeteksi kupon mitra tanpa perlu ubah tipe kupon di dashboard. Tampilkan breakdown harga Mitra, DP, Pelunasan, dan simpan meta order.
 * Version: 3.2
 * Author: Puji Dev From Tokoweb <pujiermanto@gmail.com> | AKA Dadang Sukanagara | Alias Sugandi Hieroglyph
 */

if (! defined('ABSPATH')) exit;
/**
 * 1️⃣ Tambahkan jenis kupon khusus (untuk kompatibilitas)
 */
add_filter('woocommerce_coupon_discount_types', function ($types) {
    $types['mitra_discount'] = __('Diskon Mitra (persentase)', 'mitra');
    return $types;
});

/**
 * NOTE IMPORTANT:
 * - Kita TIDAK ubah harga cart item => A dan B terpenuhi.
 * - Kupon bertipe 'mitra_discount' dinonaktifkan dari perhitungan WC standar dan dihitung manual (C).
 * - Tampilan breakdown (D) dibuat di cart & review order.
 *
 * Jika di masa depan ingin otomatis terapkan diskon 50% ke total/invoice untuk role 'mitra',
 * ubah $apply_auto_50_percent = false -> true di bagian kalkulasi.
 */

// -----------------------------
// 1) Tambah field persen di halaman kupon (opsional)
// -----------------------------
add_action('woocommerce_coupon_options', function () {
    woocommerce_wp_text_input([
        'id' => '_mitra_discount',
        'label' => __('Persentase Diskon Mitra (%)', 'woocommerce'),
        'desc_tip' => true,
        'description' => __('Contoh: 25 untuk 25%', 'woocommerce'),
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0']
    ]);
});
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

// -----------------------------
// 2) Pastikan WooCommerce TIDAK menghitung otomatis kupon 'mitra_discount'
// -----------------------------
add_filter('woocommerce_coupon_is_valid', function ($valid, $coupon) {
    if (!$valid) return false;
    $code = strtolower($coupon->get_code());

    // Jika nama kupon mengandung "mitra", paksa jadikan mitra_discount (autofix)
    if (strpos($code, 'mitra') !== false) {
        add_filter('woocommerce_coupon_get_discount_type', function ($type) use ($coupon) {
            $code_inner = strtolower($coupon->get_code());
            if (strpos($code_inner, 'mitra') !== false) {
                return 'mitra_discount'; // paksa jadikan tipe mitra_discount
            }
            return $type;
        });

        // Jika kupon belum punya meta _mitra_discount, deteksi angka dari nama kupon (mitra25 → 25%)
        $meta = get_post_meta($coupon->get_id(), '_mitra_discount', true);
        if ($meta === '') {
            if (preg_match('/mitra(\d+)/i', $code, $m)) {
                update_post_meta($coupon->get_id(), '_mitra_discount', floatval($m[1]));
            }
        }
    }

    return true;
}, 10, 2);


/**
 * 3️⃣ Sembunyikan baris "Kupon: mitra5 –Rpxxx" dari WooCommerce default
 */
add_filter('woocommerce_cart_totals_coupon_label', function ($label, $coupon) {
    if (strpos(strtolower($coupon->get_code()), 'mitra') !== false) {
        return ''; // sembunyikan
    }
    return $label;
}, 10, 2);

add_filter('woocommerce_cart_totals_coupon_html', function ($value, $coupon) {
    if (strpos(strtolower($coupon->get_code()), 'mitra') !== false) {
        return ''; // sembunyikan nilai kupon mitra
    }
    return $value;
}, 10, 2);

// -----------------------------
// 3) Kalkulasi manual: ambil subtotal normal, hitung diskon kupon, hitung shipping, total_tagihan, dp, pelunasan
// -----------------------------
add_action('woocommerce_cart_calculate_fees', function ($cart) {
    if (is_admin() && ! defined('DOING_AJAX')) return;
    // hindari loop
    if (did_action('woocommerce_cart_calculate_fees') > 2) return;

    // Ambil subtotal normal dari regular_price produk (menghindari price overrides)
    $raw_subtotal = 0;
    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        // prefer regular price; fallback ke get_price()
        $unit = (float) $product->get_regular_price();
        if ($unit <= 0) $unit = (float) $product->get_price();
        $raw_subtotal += $unit * $cart_item['quantity'];
    }

    // Ambil kupon mitra (jika ada) dan hitung diskon dari raw_subtotal
    $voucher_percent = 0;
    foreach ($cart->get_applied_coupons() as $code) {
        $coupon = new WC_Coupon($code);
        if ($coupon && $coupon->get_discount_type() === 'mitra_discount') {
            $voucher_percent = floatval(get_post_meta($coupon->get_id(), '_mitra_discount', true));
            // jangan "break" karena user bisa apply >1 kupon (kita akumulasi jika perlu)
            // break;
        }
    }
    $diskon_kupon = ($voucher_percent > 0) ? ($raw_subtotal * ($voucher_percent / 100)) : 0;

    /**
     * AUTO 50% FOR MITRA (OPTIONAL)
     * Secara default kita TIDAK memotong invoice otomatis 50% untuk role mitra
     * karena permintaanmu lebih menekankan agar cart/subtotal tetap menampilkan harga normal
     * dan kupon mitra yang dipakai mengurangi invoice.
     *
     * Jika ingin menerapkan auto 50% ke invoice juga, set $apply_auto_50_percent = true.
     */
    $apply_auto_50_percent = false; // <- ubah ke true jika ingin diskon 50% otomatis mempengaruhi invoice
    $diskon_mitra_auto = 0;
    if ($apply_auto_50_percent) {
        // misal berlaku untuk role 'mitra' saja:
        $user = wp_get_current_user();
        if (in_array('mitra', (array) $user->roles)) {
            $diskon_mitra_auto = $raw_subtotal * 0.5;
        }
    }

    // Shipping total yang sudah dipilih
    $shipping_total = (WC()->cart) ? floatval(WC()->cart->get_shipping_total()) : 0.0;

    // Total invoice = raw_subtotal - diskon_kupon - diskon_mitra_auto + shipping
    $total_tagihan = max(0, $raw_subtotal - $diskon_kupon - $diskon_mitra_auto + $shipping_total);

    // Hitung DP / pelunasan (50/50)
    $dp_amount = $total_tagihan * 0.5;
    $pelunasan_amount = $total_tagihan - $dp_amount;

    // Simpan ke session agar bisa ditampilkan di cart/checkout/gateway
    WC()->session->set('ovm_raw_subtotal', $raw_subtotal);
    WC()->session->set('ovm_diskon_kupon', $diskon_kupon);
    WC()->session->set('ovm_diskon_mitra_auto', $diskon_mitra_auto);
    WC()->session->set('ovm_shipping_total', $shipping_total);
    WC()->session->set('ovm_total_tagihan', $total_tagihan);
    WC()->session->set('ovm_dp_amount', $dp_amount);
    WC()->session->set('ovm_pelunasan_amount', $pelunasan_amount);
    WC()->session->set('ovm_voucher_percent', $voucher_percent);

    // -- Opsional: tambahkan fee negatif agar WooCommerce totals juga terpengaruh --
    // Jika kamu ingin invoice WooCommerce (total order) benar-benar mencerminkan potongan kupon mitra,
    // tambahkan fee negatif di sini. Pastikan nama fee unik agar tidak duplikasi.
    // Karena kita sudah menonaktifkan perhitungan bawaan kupon mitra di WC, jika ingin total aktual terpengaruh:
    // - tambahkan fee untuk kupon (-$diskon_kupon)
    // - tambahkan fee untuk auto mitra (jika ada)
    //
    // Hati-hati: jika kamu tidak ingin mempengaruhi total hash/order (misal hanya untuk tampilan),
    // jangan tambahkan fees ini. Aku akan tambahkan tapi dinonaktifkan default (komentar).
    //
    // Contoh jika ingin mengaktifkan:
    // if ($diskon_kupon > 0) $cart->add_fee(sprintf('Diskon Kupon Mitra (%s%%)', $voucher_percent), -1 * $diskon_kupon);
    // if ($diskon_mitra_auto > 0) $cart->add_fee('Diskon Mitra (Auto 50%)', -1 * $diskon_mitra_auto);

}, 20, 1);

// -----------------------------
// 4) Tampilkan breakdown di cart + checkout (sesuai format D)
// -----------------------------
add_action('woocommerce_cart_totals_before_order_total', 'ovm_print_breakdown');
add_action('woocommerce_review_order_before_order_total', 'ovm_print_breakdown');

function ovm_print_breakdown()
{
    $raw_subtotal = WC()->session->get('ovm_raw_subtotal') ?: 0;
    $diskon_kupon = WC()->session->get('ovm_diskon_kupon') ?: 0;
    $diskon_mitra_auto = WC()->session->get('ovm_diskon_mitra_auto') ?: 0;
    $shipping_total = WC()->session->get('ovm_shipping_total') ?: 0;
    $total_tagihan = WC()->session->get('ovm_total_tagihan') ?: 0;
    $dp_amount = WC()->session->get('ovm_dp_amount') ?: 0;
    $pelunasan_amount = WC()->session->get('ovm_pelunasan_amount') ?: 0;
    $voucher_percent = WC()->session->get('ovm_voucher_percent') ?: 0;

    // Subtotal (harga normal)
    echo '<tr class="custom-subtotal"><th>Subtotal</th><td>' . wc_price($raw_subtotal) . '</td></tr>';

    // Diskon kupon mitra (ini sesuai C: dihitung dari subtotal normal)
    if ($diskon_kupon > 0) {
        echo '<tr class="custom-kupon-mitra"><th>Diskon Kupon Mitra (' . esc_html($voucher_percent) . '%)</th><td>–' . wc_price($diskon_kupon) . '</td></tr>';
    }

    // Jika auto 50% diaktifkan (opsional), tampilkan baris Diskon Mitra
    if ($diskon_mitra_auto > 0) {
        echo '<tr class="custom-diskon-mitra-auto"><th>Diskon Mitra (50%)</th><td>–' . wc_price($diskon_mitra_auto) . '</td></tr>';
    }

    // Ongkos kirim
    echo '<tr class="custom-shipping"><th>Ongkos Kirim</th><td>' . wc_price($shipping_total) . '</td></tr>';

    // Total Tagihan Invoice (setelah potongan yang kita hitung)
    echo '<tr class="custom-total-invoice"><th><strong>Total Tagihan Invoice</strong></th><td><strong>' . wc_price($total_tagihan) . '</strong></td></tr>';

    // DP & Pelunasan 50%
    echo '<tr class="custom-dp"><th>DP 50%</th><td>' . wc_price($dp_amount) . '</td></tr>';
    echo '<tr class="custom-pelunasan"><th>Pelunasan 50%</th><td>' . wc_price($pelunasan_amount) . '</td></tr>';

    // Total Bayar (Checkout DP) -- menampilkan DP sebagai nilai yang harus dibayar saat checkout (sesuai request)
    // Simpan juga ke session agar gateway dapat membaca.
    WC()->session->set('final_checkout_dp_amount', $dp_amount);

    echo '<tr class="custom-total-bayar"><th style="text-transform:uppercase;font-weight:800;">Total Bayar (Checkout DP)</th><td style="font-size:1.5em;font-weight:900;color:#3A0BF4;">' . wc_price($dp_amount) . '</td></tr>';
}

/**
 * 4b️⃣ Styling tambahan agar tampilan breakdown rapi di tabel WooCommerce
 */
add_action('wp_head', function () {
    ?>
    <style>
        /* Ratakan kolom kiri dan kanan */
        tr.custom-subtotal th,
        tr.custom-kupon-mitra th,
        tr.custom-diskon-mitra-auto th,
        tr.custom-shipping th,
        tr.custom-total-invoice th,
        tr.custom-dp th,
        tr.custom-pelunasan th,
        tr.custom-total-bayar th {
            text-align: left !important;
            padding-left: 0 !important;
        }

        tr.custom-subtotal td,
        tr.custom-kupon-mitra td,
        tr.custom-diskon-mitra-auto td,
        tr.custom-shipping td,
        tr.custom-total-invoice td,
        tr.custom-dp td,
        tr.custom-pelunasan td,
        tr.custom-total-bayar td {
            text-align: right !important;
            padding-right: 0 !important;
        }

        /* Tambahan estetika */
        tr.custom-total-invoice th,
        tr.custom-total-invoice td {
            border-top: 2px solid #000;
            font-weight: 700;
        }

        tr.custom-total-bayar th,
        tr.custom-total-bayar td {
            border-top: 2px solid #000;
            padding-top: 6px;
        }

        tr.custom-total-bayar td {
            color: #3A0BF4 !important;
            font-size: 1.5em;
            font-weight: 900;
        }

        @media (max-width:768px) {

            tr[class^="custom-"] th,
            tr[class^="custom-"] td {
                font-size: 0.9em !important;
            }
        }
    </style>
    <?php
});


/**
 * 5️⃣ Sembunyikan baris Total default WooCommerce (opsional)
 */
add_filter('woocommerce_cart_totals_order_total_html', function ($value) {
    $user = wp_get_current_user();
    if (in_array('mitra', (array) $user->roles)) {
        return ''; // hilangkan "Total" default
    }
    return $value;
}, 20);

add_filter('woocommerce_cart_totals_order_total_label', function ($label) {
    $user = wp_get_current_user();
    if (in_array('mitra', (array) $user->roles)) {
        return ''; // sembunyikan label "Total"
    }
    return $label;
});


// -----------------------------
// 6) Ketika membuat order, jika kamu ingin menyimpan breakdown sebagai meta order,
//    kita bisa inject meta (raw_subtotal, diskon_kupon, dp_amount) agar diadministrasi mudah.
// -----------------------------
add_action('woocommerce_checkout_create_order', function ($order, $data) {
    $raw_subtotal = WC()->session->get('ovm_raw_subtotal');
    $diskon_kupon = WC()->session->get('ovm_diskon_kupon');
    $dp_amount = WC()->session->get('ovm_dp_amount');
    $pelunasan = WC()->session->get('ovm_pelunasan_amount');
    $total_tagihan = WC()->session->get('ovm_total_tagihan');

    if ($raw_subtotal !== null) $order->update_meta_data('_ovm_raw_subtotal', wc_format_decimal($raw_subtotal, 2));
    if ($diskon_kupon !== null) $order->update_meta_data('_ovm_diskon_kupon', wc_format_decimal($diskon_kupon, 2));
    if ($dp_amount !== null) $order->update_meta_data('_ovm_dp_amount', wc_format_decimal($dp_amount, 2));
    if ($pelunasan !== null) $order->update_meta_data('_ovm_pelunasan', wc_format_decimal($pelunasan, 2));
    if ($total_tagihan !== null) $order->update_meta_data('_ovm_total_tagihan', wc_format_decimal($total_tagihan, 2));
}, 20, 2);

/**
 * 7️⃣ Hapus baris "Total" kosong bawaan tema (Flatsome/Tokoweb)
 */
add_action('wp_footer', function () {
    if (is_cart() || is_checkout()) :
    ?>
        <script>
            jQuery(function($) {
                // Target baris dengan class order-total ATAU label "Total"
                $('tr.order-total, .c-cart__sub-sub-header').each(function() {
                    var $row = $(this).closest('tr');
                    var label = $(this).text().trim().toLowerCase();
                    var val = $row.find('td').text().trim();

                    // Jika label mengandung "total" dan value kosong, sembunyikan baris
                    if (label.includes('total') && val === '') {
                        $row.hide();
                    }
                });

                $('tr.order-total, tr.cart-subtotal').filter(function() {
                    return $(this).text().trim() === '' || $(this).find('td').text().trim() === '';
                }).hide();

                $(document.body).on('updated_checkout updated_cart_totals', function() {
                    $('tr.order-total, .c-cart__sub-sub-header').each(function() {
                        var $row = $(this).closest('tr');
                        var label = $(this).text().trim().toLowerCase();
                        var val = $row.find('td').text().trim();
                        if (label.includes('total') && val === '') {
                            $row.hide();
                        }
                    });
                });

            });
        </script>
<?php
    endif;
});
