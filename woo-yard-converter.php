<?php

/**
 * Plugin Name: WooCommerce Yard Converter
 * Description: Konversi meter ke yard dan kalkulasi harga berdasarkan harga per yard untuk produk kain (termasuk variable).
 * Version: 2.0
 * Author: Puji Ermanto <pujiermanto@gmail.com> | AKA Puji Mangku Wanito Mudo | AKA The Ghost Of You
 * Requires at least: 5.5
 * Requires PHP: 7.2
 */


defined('ABSPATH') || exit;

// ====== TAMBAH FIELD DI ADMIN PRODUK ======
add_action('woocommerce_product_options_general_product_data', 'woo_add_unit_converter_admin_field');
function woo_add_unit_converter_admin_field()
{
    echo '<div class="options_group">';

    woocommerce_wp_checkbox([
        'id' => '_enable_unit_converter',
        'label' => __('Aktifkan Konversi Satuan?', 'woocommerce'),
        'description' => __('Tampilkan input konversi meter ↔ yard di halaman produk ini.'),
    ]);

    woocommerce_wp_text_input([
        'id' => '_price_per_yard',
        'label' => __('Harga per Yard', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => 'true',
        'description' => __('Masukkan harga per yard untuk produk ini.', 'woocommerce'),
        'custom_attributes' => [
            'step' => '0.01',
            'min' => '0'
        ]
    ]);

    echo '</div>';
}

// ====== SIMPAN METADATA ADMIN PRODUK ======
add_action('woocommerce_process_product_meta', 'woo_save_unit_converter_admin_field');
function woo_save_unit_converter_admin_field($post_id)
{
    $enabled = isset($_POST['_enable_unit_converter']) ? 'yes' : 'no';
    update_post_meta($post_id, '_enable_unit_converter', $enabled);

    if (isset($_POST['_price_per_yard'])) {
        update_post_meta($post_id, '_price_per_yard', wc_clean($_POST['_price_per_yard']));
    }
}

// ====== TAMPILKAN INPUT DI HALAMAN PRODUK ======
add_action('wp_enqueue_scripts', 'woo_converter_inline_style');
function woo_converter_inline_style()
{
    if (is_product()) {
        $product_id = get_queried_object_id();
        if ($product_id && get_post_meta($product_id, '_enable_unit_converter', true) === 'yes') {
            wp_register_style('woo-yard-converter-inline', false);
            wp_enqueue_style('woo-yard-converter-inline');
            wp_add_inline_style('woo-yard-converter-inline', '
                form.cart {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 20px;
                    padding: 0;
                    align-items: flex-start;
                }

                form.cart .woo-converter-wrapper {
                    flex: 1 1 300px;
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }

                .woo-converter-fields {
                    display: flex;
                    gap: 15px;
                    align-items: flex-end;
                    flex-wrap: wrap;
                }

                .woo-converter-fields > * {
                    flex: 1 1 120px;
                }

                .woo-unit-converter {
                    flex: 1 1 auto;
                }

                .woo-btn-wrapper {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                    margin-top: 10px;
                }

                .woo-btn-wrapper .button {
                    flex: 1 1 200px;
                    max-width: 250px;
                }

                .woo-converter-fields .quantity {
                    flex: 1 1 100px;
                    align-self: flex-start;
                    margin-top: 32px; /* atur agar sejajar dengan input satuan */
                }

                @media(min-width: 600px) {
                    .woo-btn-wrapper .button {
                        width: auto;
                    }
                }
            ');
        }
    }
}

add_action('woocommerce_after_add_to_cart_button', 'woo_add_beli_langsung_button', 5);
function woo_add_beli_langsung_button()
{
    global $product;
    $product_id = $product->get_id();

    // ✅ Tambahkan pengecekan ini:
    if (get_post_meta($product_id, '_enable_unit_converter', true) !== 'yes') return;

    // ... lanjutkan kode seperti sebelumnya:
    $product_title = $product->get_title();
    $product_url   = get_permalink($product_id);
    $site_name     = get_bloginfo('name');
    $price_per_yard_raw = get_post_meta($product_id, '_price_per_yard', true);
    $price_per_yard = floatval($price_per_yard_raw);
    $harga_format = 'Rp' . number_format($price_per_yard, 0, ',', '.');
    $total_bayar_format = 'Rp' . number_format($price_per_yard, 0, ',', '.');

    $args = array(
        'post_type'      => 'whatsapp_admin',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
    );

    $wa_query = new WP_Query($args);
    $nomor_wa = '6281234567890';
    if ($wa_query->have_posts()) {
        while ($wa_query->have_posts()) {
            $wa_query->the_post();
            $nomor = get_field('nomor_whatsapp_admin');
            if (!empty($nomor)) {
                $nomor_bersih = preg_replace('/[^0-9]/', '', $nomor);
                if (strpos($nomor_bersih, '0') === 0) {
                    $nomor_bersih = '62' . substr($nomor_bersih, 1);
                }
                $nomor_wa = $nomor_bersih;
            }
        }
        wp_reset_postdata();
    }

    $pesan_text = "Halo Admin {$site_name}, saya tertarik untuk membeli produk berikut:\n\n"
        . "🧵 *Nama Produk:* {$product_title}\n"
        . "📦 *Jumlah:* 1 yard\n"
        . "💰 *Harga per yard:* {$harga_format}\n"
        . "🧾 *Total Bayar:* {$total_bayar_format}\n\n"
        . "🔗 *Link Produk:* {$product_url}\n\n"
        . "Mohon konfirmasinya ya, terima kasih 🙏";

    $link_wa = 'https://wa.me/' . $nomor_wa . '?text=' . urlencode($pesan_text);


    echo '<div class="woo-btn-wrapper">';
    echo '<a href="' . htmlspecialchars($link_wa, ENT_QUOTES, 'UTF-8') . '" class="button beli-langsung-wa" target="_blank" style="background-color: #25D366; color: white;">Beli Langsung via WhatsApp</a>';
    echo '</div>';
}


add_action('woocommerce_before_add_to_cart_quantity', 'woo_converter_input_fields_conditional', 5);
function woo_converter_input_fields_conditional()
{
    global $product;
    $product_id = $product->get_id();

    if (get_post_meta($product_id, '_enable_unit_converter', true) !== 'yes') return;
?>
    <div class="woo-converter-wrapper">
        <label for="input_meter" style="font-weight: bold;">Panjang</label>

        <div class="woo-converter-fields">
            <div class="woo-unit-converter">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="input_unit" value="meter" id="unit_meter" checked>
                        Meter
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="input_unit" value="yard" id="unit_yard">
                        Yard
                    </label>
                </div>

                <div style="display: flex; align-items: center; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; width: fit-content;">
                    <input type="number" step="0.01" min="0.1" id="input_meter" name="input_meter" value="1"
                        style="padding: 8px; border: none; width: 100px;" />
                    <div id="unit_label" style="background: #1e1f37; color: #fff; padding: 8px 12px;">Meter</div>
                </div>
            </div>

            <!-- Quantity akan otomatis berada di sebelah kanan karena .quantity ada di form.cart -->
        </div>

        <p id="price-per-yard-display" style="margin: 5px 0; font-weight: bold;"></p>
    </div>



    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const meterInput = document.querySelector('#input_meter');
            const unitRadios = document.querySelectorAll('input[name="input_unit"]');
            const qtyInput = document.querySelector('input.qty');
            const unitLabel = document.querySelector('#unit_label');
            const priceDisplay = document.querySelector('#price-per-yard-display');
            const qtyWrapper = document.querySelector('.quantity');
            const converterFields = document.querySelector('.woo-converter-fields');

            if (qtyWrapper && converterFields) {
                converterFields.appendChild(qtyWrapper);
            }

            function convertToYard(meter) {
                return Math.ceil(meter / 0.9144);
            }

            function getSelectedUnit() {
                const selected = document.querySelector('input[name="input_unit"]:checked');
                return selected ? selected.value : 'meter';
            }

            function updateQty() {
                const length = parseFloat(meterInput.value);
                const unit = getSelectedUnit();

                if (!isNaN(length)) {
                    let qty = 0;
                    if (unit === 'meter') {
                        qty = convertToYard(length);
                        unitLabel.textContent = 'Meter';
                    } else {
                        qty = Math.ceil(length);
                        unitLabel.textContent = 'Yard';
                    }
                    qtyInput.value = qty;
                }
            }

            function updatePricePerYardDisplay(variation) {
                if (!variation || !variation.variation_id) return;
                const pricePerYard = variation.price_per_yard;
                if (priceDisplay) {
                    if (pricePerYard !== undefined) {
                        priceDisplay.innerText = 'Harga per Yard: Rp ' + parseInt(pricePerYard).toLocaleString('id-ID');
                    } else {
                        priceDisplay.innerText = '';
                    }
                }
            }

            const productForm = document.querySelector('form.variations_form');
            if (productForm) {
                productForm.addEventListener('woocommerce_update_variation_values', () => {
                    priceDisplay.innerText = '';
                });

                productForm.addEventListener('found_variation', function(event) {
                    const variation = event.detail.variation;
                    updatePricePerYardDisplay(variation);
                });
            }

            meterInput.addEventListener('input', updateQty);
            unitRadios.forEach(r => r.addEventListener('change', updateQty));
            updateQty();
        });
    </script>
<?php
}

add_filter('woocommerce_get_price_html', 'custom_variable_price_per_yard_display', 100, 2);
function custom_variable_price_per_yard_display($price_html, $product)
{
    if (!is_product() || is_admin()) {
        return $price_html;
    }

    if (!$product->is_type('variable')) {
        return $price_html;
    }

    $product_id = $product->get_id();

    // Cek apakah konversi aktif
    if (get_post_meta($product_id, '_enable_unit_converter', true) !== 'yes') {
        return $price_html;
    }

    // Ambil semua variasi
    $available_variations = $product->get_available_variations();
    $prices = [];

    foreach ($available_variations as $variation) {
        $vid = $variation['variation_id'];
        $custom_price = get_post_meta($vid, '_price_per_yard', true);
        if (is_numeric($custom_price)) {
            $prices[] = floatval($custom_price);
        }
    }

    if (empty($prices)) {
        return $price_html;
    }

    $min = min($prices);
    $max = max($prices);

    if ($min == $max) {
        return '<p class="price">Harga per yard: ' . wc_price($min) . '</p>';
    } else {
        return '<p class="price">Harga per yard: ' . wc_price($min) . ' – ' . wc_price($max) . '</p>';
    }
}



// ====== SIMPAN KE CART ======
function woo_save_converter_to_cart($cart_item_data, $product_id, $variation_id)
{
    if (isset($_POST['input_meter'], $_POST['input_unit'])) {
        $unit = wc_clean($_POST['input_unit']);
        $length = floatval($_POST['input_meter']);

        // Cek variasi
        $price_per_yard = $variation_id ? get_post_meta($variation_id, '_price_per_yard', true) : get_post_meta($product_id, '_price_per_yard', true);

        if ($length <= 0 || !in_array($unit, ['meter', 'yard'])) {
            wc_add_notice(__('Panjang atau satuan tidak valid.'), 'error');
            return false;
        }

        $cart_item_data['converter_input'] = [
            'unit' => $unit,
            'length' => $length,
            'price_per_yard' => $price_per_yard,
        ];
    }
    return $cart_item_data;
}


// ====== TAMPILKAN DI CART/CHECKOUT ======
add_filter('woocommerce_get_item_data', 'woo_display_converter_in_cart', 10, 2);
function woo_display_converter_in_cart($item_data, $cart_item)
{
    if (isset($cart_item['converter_input'])) {
        $item_data[] = ['key' => 'Panjang Asli', 'value' => $cart_item['converter_input']['length'] . ' ' . $cart_item['converter_input']['unit']];
        $item_data[] = ['key' => 'Harga per Yard', 'value' => 'Rp ' . number_format($cart_item['converter_input']['price_per_yard'], 0, ',', '.')];
    }
    return $item_data;
}

// ====== SIMPAN KE ORDER ITEM ======
add_action('woocommerce_checkout_create_order_line_item', 'woo_add_converter_to_order_items', 10, 4);
function woo_add_converter_to_order_items($item, $cart_item_key, $values, $order)
{
    if (isset($values['converter_input'])) {
        $item->add_meta_data('Panjang Asli', $values['converter_input']['length'] . ' ' . $values['converter_input']['unit']);
        $item->add_meta_data('Harga per Yard', 'Rp ' . number_format($values['converter_input']['price_per_yard'], 0, ',', '.'));
    }
}


// ====== TAMBAH FIELD HARGA PER YARD DI VARIASI PRODUK ======
add_action('woocommerce_variation_options_pricing', 'woo_add_price_per_yard_variation_field', 10, 3);
function woo_add_price_per_yard_variation_field($loop, $variation_data, $variation)
{
    woocommerce_wp_text_input([
        'id' => '_price_per_yard[' . $loop . ']',
        'class' => 'short',
        'label' => __('Harga per Yard', 'woocommerce'),
        'desc_tip' => 'true',
        'type' => 'number',
        'custom_attributes' => [
            'step' => '0.01',
            'min' => '0'
        ],
        'value' => get_post_meta($variation->ID, '_price_per_yard', true)
    ]);
}

add_action('woocommerce_save_product_variation', 'woo_save_price_per_yard_variation_field', 10, 2);
function woo_save_price_per_yard_variation_field($variation_id, $i)
{
    if (isset($_POST['_price_per_yard'][$i])) {
        update_post_meta($variation_id, '_price_per_yard', wc_clean($_POST['_price_per_yard'][$i]));
    }
}


add_filter('woocommerce_available_variation', 'woo_add_price_per_yard_to_variation_json');
function woo_add_price_per_yard_to_variation_json($variation_data)
{
    $variation_id = $variation_data['variation_id'];
    $price_per_yard = get_post_meta($variation_id, '_price_per_yard', true);
    $variation_data['price_per_yard'] = $price_per_yard;
    return $variation_data;
}

add_filter('woocommerce_get_price_html', 'custom_price_per_yard_output', 10, 2);
function custom_price_per_yard_output($price, $product)
{
    if (is_product() && get_post_meta($product->get_id(), '_enable_unit_converter', true) === 'yes') {
        $custom_price = get_post_meta($product->get_id(), '_price_per_yard', true);
        if (!empty($custom_price)) {
            return wc_price($custom_price) . ' <small>/ yard</small>';
        }
    }
    return $price;
}
