<?php

/**
 * Plugin Name: WooCommerce Yard Converter
 * Description: Konversi meter ke yard dan kalkulasi harga berdasarkan harga per yard untuk produk kain (termasuk variable).
 * Version: 2.0
 * Author: Puji Ermanto <pujiermanto@gmail.com> | AKA Puji Mangku Wanito Mudo | AKA Orang paling ganteng di komplek ini
 * Author URI: https://pujiermanto-portfolio.vercel.app
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
        'label' => __('Aktifkan Satuan Yard?', 'woocommerce'),
        'description' => __('Tampilkan input satuan yard di halaman produk ini.'),
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

// Tombol beli via whatsapp
add_action('wp_enqueue_scripts', 'woo_add_custom_cart_button_style', 20);
function woo_add_custom_cart_button_style()
{
    if (!is_product()) return;

    $product_id = get_queried_object_id();
    if (!$product_id || get_post_meta($product_id, '_enable_unit_converter', true) !== 'yes') return;

    wp_register_style('woo-custom-buttons-style', false);
    wp_enqueue_style('woo-custom-buttons-style');

    $custom_css = '
        .woo-btn-wrapper {
            display: flex;
            gap: 10px;
            flex-wrap: nowrap; /* Ubah dari wrap → nowrap */
            margin-top: 21px;
            align-items: center;
        }

        .woo-btn-wrapper .button {
            flex: 1 1 auto;
            max-width: none;
        }

        @media (max-width: 600px) {
            .woo-btn-wrapper {
                flex-wrap: wrap; /* agar tetap responsif di mobile */
            }
        }

        .woo-btn-wrapper .beli-langsung-wa {
            background: #25D366 !important;
            color: white !important;
            border: none !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .woo-btn-wrapper .beli-langsung-wa:hover {
            background: #1ebe5d !important;
            box-shadow: 0 4px 10px rgba(37, 211, 102, 0.4);
        }

        .single_add_to_cart_button {
            order: 1;
            border-radius: 4px!important;
        }
    ';

    wp_add_inline_style('woo-custom-buttons-style', $custom_css);
}

function custom_enqueue_fontawesome()
{
    wp_enqueue_style(
        'font-awesome-cdn',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',
        array(),
        '6.7.2'
    );
}
add_action('wp_enqueue_scripts', 'custom_enqueue_fontawesome');

function custom_enqueue_fontawesome_scripts()
{
    wp_enqueue_script(
        'font-awesome-js',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/js/all.min.js',
        array(),
        '6.7.2',
        true
    );
}
add_action('wp_enqueue_scripts', 'custom_enqueue_fontawesome_scripts');

add_action('woocommerce_after_add_to_cart_button', 'woo_add_beli_langsung_button', 5);
function woo_add_beli_langsung_button()
{
    global $product;
    $product_id = $product->get_id();

    if (get_post_meta($product_id, '_enable_unit_converter', true) !== 'yes') return;

    $product_title = $product->get_title();
    $product_url   = get_permalink($product_id);
    $site_name     = get_bloginfo('name');
    $price_per_yard_raw = get_post_meta($product_id, '_price_per_yard', true);
    $price_per_yard = floatval($price_per_yard_raw);
    $harga_format = 'Rp' . number_format($price_per_yard, 0, ',', '.');
    $total_bayar_format = 'Rp' . number_format($price_per_yard, 0, ',', '.');

    // Ambil nomor WA dari custom post
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

    // HTML tombol WA
    echo '<div class="woo-btn-wrapper">';
    echo '<a href="#" data-wa="' . esc_attr($nomor_wa) . '" class="button beli-langsung-wa" style="background-color: #25D366; color: white; padding: 20px 25px; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fab fa-whatsapp" style="font-size: 18px; color: white;"></i>
            Beli Langsung
        </a>';
    echo '</div>';

    // Tambahkan JS ke footer
    add_action('wp_footer', function () use ($site_name) {
?>
        <script>
            function getSelectedUnit() {
                const selected = document.querySelector('input[name="input_unit"]:checked');
                return selected ? selected.value : 'meter';
            }

            document.addEventListener('DOMContentLoaded', function() {
                const addToCartBtn = document.querySelector('.single_add_to_cart_button');
                const waBtn = document.querySelector('.beli-langsung-wa');
                if (!waBtn) return;

                if (addToCartBtn && waBtn) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'woo-btn-wrapper';
                    addToCartBtn.parentNode.insertBefore(wrapper, addToCartBtn);
                    wrapper.appendChild(addToCartBtn);
                    wrapper.appendChild(waBtn);
                }

                waBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    const unitInput = document.querySelector('input[name="unit_satuan"]:checked');
                    const unit = unitInput ? parseFloat(unitInput.value) || 1 : 1;
                    const unitSelected = getSelectedUnit();

                    const qtyInput = document.querySelector('input[name="quantity"]');
                    const qty = qtyInput ? parseFloat(qtyInput.value) || 1 : 1;

                    const title = document.querySelector('h1.product_title')?.textContent.trim() || 'Produk';
                    const url = window.location.href;

                    const variationID = parseInt(jQuery('input[name="variation_id"]').val());
                    const variationData = jQuery('form.variations_form').data('product_variations');
                    const variation = variationData?.find(v => v.variation_id === variationID);

                    let hargaRaw = 0;
                    let gambar = '-';
                    let warna = '-';
                    for (const [key, value] of Object.entries(variation.attributes)) {
                        if (key.includes('attribute_pa_warna')) {
                            warna = value.replace(/-/g, ' ').toUpperCase();
                            break;
                        }
                    }

                    if (variation) {
                        hargaRaw = variation.display_price || 0;

                        // Ambil warna dari attributes
                        const attrWarna = Object.values(variation.attributes).find(v => v.includes('warna'));
                        if (attrWarna) {
                            warna = attrWarna.replace(/-/g, ' ').toUpperCase();
                        }

                        // Ambil gambar dari object variation.image.src
                        if (variation.image && variation.image.src) {
                            gambar = variation.image.src;
                        }
                    }

                    const total = hargaRaw * unit;
                    const hargaFormat = new Intl.NumberFormat('id-ID').format(hargaRaw);
                    const totalFormat = new Intl.NumberFormat('id-ID').format(total);

                    const pesan = `Halo Admin <?php echo $site_name; ?>, saya tertarik untuk membeli produk berikut:\n\n` +
                        `📌 *Nama Produk:* ${title}\n` +
                        `📏 *Jumlah:* ${qty} ${unitSelected}\n` +
                        `🎨 *Warna:* ${warna}\n` +
                        `🖼️ *Gambar:* ${gambar}\n` +
                        `💸 *Harga per ${unit} ${unitSelected}:* Rp${hargaFormat}\n` +
                        `💳 *Total Bayar:* Rp${totalFormat}\n\n` +
                        `🔗 *Link Produk:* ${url}\n\n` +
                        `Mohon konfirmasinya ya, terima kasih 🙏`;

                    const nomor = waBtn.getAttribute('data-wa');
                    const waLink = `https://wa.me/${nomor}?text=${encodeURIComponent(pesan)}`;
                    window.open(waLink, '_blank');
                });
            });
        </script>
    <?php
    });
}

// Input yard
add_action('wp_enqueue_scripts', 'woo_register_yard_converter_styles');
function woo_register_yard_converter_styles()
{

    // Hanya load di halaman produk
    if (! is_product()) {
        return;
    }

    $product_id = get_queried_object_id();
    if (! $product_id || get_post_meta($product_id, '_enable_unit_converter', true) !== 'yes') {
        return;
    }

    /* -----------------------------------------------------
     * 1) Register style kosong (tidak butuh file .css fisik)
     * --------------------------------------------------- */
    wp_register_style('woo-yard-converter', false /* no URL */);

    /* -----------------------------------------------------
     * 2) Enqueue supaya <link> muncul di <head>
     * --------------------------------------------------- */
    wp_enqueue_style('woo-yard-converter');

    /* -----------------------------------------------------
     * 3) Susun CSS dan sisipkan inline pada handle tsb
     * --------------------------------------------------- */
    $css = "
        /* —— Layout yang sudah ada —— */
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
        .woo-unit-converter { flex: 1 1 auto; }
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
            margin-top: 32px;
        }

        /* —— CUSTOM RADIO (seperti gambar) —— */
        input[type='radio'][name='input_unit']{
            -webkit-appearance:none;
            appearance:none;
            width:20px;
            height:20px;
            border:2px solid #8a8a8a;      /* ring luar abu */
            border-radius:50%;
            background:#fff;
            position:relative;
            cursor:pointer;
            transition:all .2s ease;
        }
        input[type='radio'][name='input_unit']:checked::before{
            content:'';
            position:absolute;
            top:3px; left:3px;
            width:12px; height:12px;
            border-radius:50%;
            background:#1e1f37; 
        }

        .woo-flex-row {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .woo-flex-row input[type='number'] {
            width: 150px;
            padding: 8px;
            border: none;
        }

        .woo-unit-box {
            display: flex;
            align-items: center;
            border: 1px solid #ccc;
            border-radius: 4px;
            overflow: hidden;
        }
    ";

    wp_add_inline_style('woo-yard-converter', $css);
}

add_action('woocommerce_before_add_to_cart_quantity', 'woo_converter_input_fields_conditional', 5);
function woo_converter_input_fields_conditional()
{
    global $product;
    $product_id = $product->get_id();

    if (get_post_meta($product_id, '_enable_unit_converter', true) !== 'yes') return;
    ?>
    <div class="woo-converter-wrapper">
        <label for="input_satuan" style="font-weight: bold;">Unit Satuan</label>

        <div class="woo-converter-fields">
            <div class="woo-unit-converter">
                <div class="woo-flex-row">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="input_unit" value="yard" id="unit_yard" checked>
                        Yard
                    </label>

                    <div class="woo-unit-box">
                        <input type="number" step="0.01" min="0.1" id="input_satuan" name="input_satuan" value="1" />
                        <div id="unit_label" style="background: #1e1f37; color: #fff; padding: 8px 12px;">Yard</div>
                    </div>
                </div>
            </div>
            <!-- Quantity akan otomatis berada di sebelah kanan karena .quantity ada di form.cart -->
        </div>

        <p id="price-per-yard-display" style="margin: 5px 0; font-weight: bold;"></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const meterInput = document.querySelector('#input_satuan');
            const unitRadios = document.querySelectorAll('input[name="input_unit"]');
            const qtyInput = document.querySelector('input.qty');
            const unitLabel = document.querySelector('#unit_label');
            const priceDisplay = document.querySelector('#price-per-yard-display');
            const qtyWrapper = document.querySelector('.quantity');
            const converterFields = document.querySelector('.woo-converter-fields');
            const productForm = document.querySelector('form.variations_form');

            function convertToYard(meter) {
                return meter / 0.9144;
            }

            function getSelectedUnit() {
                const selected = document.querySelector('input[name="input_unit"]:checked');
                return selected ? selected.value : 'meter';
            }

            function updateQty() {
                const length = parseFloat(meterInput.value) || 0;
                const unit = getSelectedUnit();
                qtyInput.value = unit === 'meter' ? Math.ceil(convertToYard(length)) : Math.ceil(length);
                unitLabel.textContent = unit === 'meter' ? 'Meter' : 'Yard';
            }

            function updatePrice(variation) {
                if (!variation || !variation.display_price) return;

                const length = parseFloat(meterInput.value) || 0;
                const unit = getSelectedUnit();
                const yardValue = unit === 'meter' ? convertToYard(length) : length;
                const totalPrice = variation.display_price * yardValue;

                // Ini trik ampuh untuk menghindari overwrite WooCommerce
                // requestAnimationFrame(() => {
                //     setTimeout(() => {
                //         const priceElem = document.querySelector('.woocommerce-variation-price .price');
                //         if (priceElem) {
                //             priceElem.innerHTML = `
                //                 Rp ${Math.round(totalPrice).toLocaleString('id-ID')}, ${unit} (Bruto)
                //             `;
                //         }

                //         if (priceDisplay) {
                //             priceDisplay.textContent = 'Harga per Yard: Rp ' + Math.round(variation.display_price).toLocaleString('id-ID') + '- / Yard (Bruto)';
                //         }
                //     }, 100);
                // });
                const priceElem = document.querySelector('.woocommerce-variation-price .price');
                if (priceElem) {
                    priceElem.innerHTML = `Rp ${Math.round(totalPrice).toLocaleString('id-ID')} (${length} Yard)`;
                }
                // Update harga per yard
                if (priceDisplay) {
                    priceDisplay.textContent = 'Harga per Yard: Rp ' + Math.round(variation.display_price).toLocaleString('id-ID') + '- / Yard (Bruto)';
                }
            }

            window.updatePrice = function(variation) {
                if (!variation || !variation.display_price) return;

                const meterInput = document.querySelector('#input_satuan');
                const unit = document.querySelector('input[name="input_unit"]:checked')?.value || 'yard';
                const length = parseFloat(meterInput.value) || 1;
                const yardValue = unit === 'meter' ? (length / 0.9144) : length;
                const totalPrice = variation.display_price * yardValue;

                setTimeout(() => {
                    const priceElem = document.querySelector('.woocommerce-variation-price .price');
                    if (priceElem) {
                        priceElem.innerHTML = `Rp ${Math.round(totalPrice).toLocaleString('id-ID')} (${length} Yard)`;
                    }

                    const priceDisplay = document.querySelector('#price-per-yard-display');
                    if (priceDisplay) {
                        priceDisplay.textContent = 'Harga per Yard: Rp ' + Math.round(variation.display_price).toLocaleString('id-ID') + '- / Yard (Bruto)';
                    }
                }, 100);
            };

            // Event listeners
            meterInput.addEventListener('input', updateQty);
            unitRadios.forEach(radio => radio.addEventListener('change', updateQty));

            // Update harga saat variasi ditemukan
            jQuery('form.variations_form .reset_variations').on('click', function() {
                const priceElem = document.querySelector('.woocommerce-variation-price .price');
                if (priceElem) {
                    priceElem.innerHTML = 'Silakan pilih variasi terlebih dahulu';
                }
                priceDisplay.textContent = '';
            });

            meterInput.addEventListener('input', () => {
                const variation = jQuery('form.variations_form').data('product_variations')
                    ?.find(v => v.variation_id === parseInt(jQuery('input[name="variation_id"]').val()));
                if (variation) updatePrice(variation);
            });

            updateQty();

            const variation = jQuery('form.variations_form').data('product_variations')
                ?.find(v => v.variation_id === parseInt(jQuery('input[name="variation_id"]').val()));
            if (variation) updatePrice(variation);

            jQuery('form.variations_form').on('woocommerce_variation_has_changed', function() {
                const variationId = parseInt(jQuery('input[name="variation_id"]').val());
                const variations = jQuery('form.variations_form').data('product_variations');

                const selected = variations?.find(v => v.variation_id === variationId);
                if (selected) {
                    updatePrice(selected);
                }
            });

        });
    </script>
<?php
}

add_action('woocommerce_after_single_product', function () {
    global $product;
    if (!is_product() || get_post_meta($product->get_id(), '_enable_unit_converter', true) !== 'yes') return;

?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quantityDiv = document.querySelector('.single-product .quantity');
            const cartForm = document.querySelector('form.cart');
            if (quantityDiv && cartForm) {
                quantityDiv.remove();
            }
        });
    </script>
    <?php
});

// Added variation
add_filter('woocommerce_add_cart_item_data', function ($data, $product_id, $variation_id) {
    if (!empty($_POST['input_satuan'])) {
        $yard = floatval($_POST['input_satuan']);
        if ($yard > 0) {
            $data['input_satuan'] = $yard;
        }
    }

    // Cari attribute warna apa pun secara dinamis
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'attribute_pa_warna') !== false && !empty($value)) {
            $data['variation_warna'] = sanitize_text_field($value);
            break;
        }
    }

    return $data;
}, 10, 3);


add_action('woocommerce_variation_options', 'add_image_variation_field', 10, 3);
function add_image_variation_field($loop, $variation_data, $variation)
{
    // Ambil ID gambar yang sudah ada
    $image_id = get_post_meta($variation->ID, 'upload_image_id', true);

    // Tampilkan input untuk ID gambar
    woocommerce_wp_hidden_input([
        'id' => 'upload_image_id[' . $loop . ']',
        'value' => $image_id
    ]);

    // Tampilkan gambar jika ada
    if ($image_id) {
        echo wp_get_attachment_image($image_id, 'thumbnail');
    }
}

add_action('woocommerce_save_product_variation', 'save_image_variation_field', 10, 2);
function save_image_variation_field($variation_id, $i)
{
    // Simpan ID gambar
    if (isset($_POST['upload_image_id'][$i])) {
        update_post_meta($variation_id, 'upload_image_id', intval($_POST['upload_image_id'][$i]));
    }
}


add_filter('woocommerce_cart_item_display', function ($item_name, $cart_item, $cart_item_key) {
    if (isset($cart_item['variation_warna'])) {
        $item_name .= '<br><small>Warna: ' . esc_html($cart_item['variation_warna']) . '</small>';
    }
    return $item_name;
}, 10, 3);

add_filter('woocommerce_cart_item_name', function ($item_name, $cart_item, $cart_item_key) {
    if (isset($cart_item['variation_id'])) {
        // Ambil ID variasi dari item
        $variation_id = $cart_item['variation_id'];
        // Ambil ID gambar dari metadata
        $image_id = get_post_meta($variation_id, 'upload_image_id', true);
        // Jika ada gambar, tambahkan ke nama item
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
            $item_name = '<img src="' . esc_url($image_url) . '" alt="Warna" style="width: 50px; height: auto; margin-right: 5px;">' . $item_name;
        }
    }
    return $item_name;
}, 10, 3);


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

add_action('woocommerce_variation_options', 'add_default_variation_radio_button', 10, 3);
function add_default_variation_radio_button($loop, $variation_data, $variation)
{
    $checked = get_post_meta($variation->ID, '_is_default_variation', true) === 'yes' ? 'checked' : '';
    echo '<p class="form-row form-row-full">
        <label>' . __('Jadikan sebagai default', 'woocommerce') . '</label><br>
        <input type="radio" name="_is_default_variation_radio" value="' . esc_attr($variation->ID) . '" ' . $checked . '> ' . __('Ya', 'woocommerce') . '
    </p>';
}

add_action('woocommerce_save_product_variation', 'save_default_variation_radio', 10, 2);
function save_default_variation_radio($variation_id, $i)
{
    $is_default = isset($_POST['_is_default_variation_radio']) && $_POST['_is_default_variation_radio'] == $variation_id ? 'yes' : 'no';

    if ($is_default === 'yes') {
        $parent_id = wp_get_post_parent_id($variation_id);
        $children = get_children(['post_parent' => $parent_id, 'post_type' => 'product_variation']);
        foreach ($children as $child) {
            update_post_meta($child->ID, '_is_default_variation', $child->ID == $variation_id ? 'yes' : 'no');
        }
    } else {
        update_post_meta($variation_id, '_is_default_variation', 'no');
    }
}

add_action('admin_footer', 'limit_default_variation_checkbox_selection');
function limit_default_variation_checkbox_selection()
{
    global $pagenow;
    if ($pagenow === 'post.php' && get_post_type() === 'product') {
    ?>
        <script>
            jQuery(document).ready(function($) {
                function enforceSingleDefault() {
                    $('input[name^="_is_default_variation"]').on('change', function() {
                        if ($(this).is(':checked')) {
                            $('input[name^="_is_default_variation"]').not(this).prop('checked', false);
                        }
                    });
                }
                enforceSingleDefault();
            });
        </script>
    <?php
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

add_action('wp_footer', 'woo_autoselect_default_variation');
function woo_autoselect_default_variation()
{
    if (!is_product()) return;

    global $product;

    if (!$product->is_type('variable')) return;

    $variations = $product->get_available_variations();
    $attributes = $product->get_variation_attributes();

    // Cari ID variasi yang default
    foreach ($variations as $variation) {
        $variation_id = $variation['variation_id'];
        $is_default = get_post_meta($variation_id, '_is_default_variation', true);

        if ($is_default === 'yes') {
            $default_attrs = $variation['attributes']; // ex: ['attribute_pa_color' => 'blue']
            break;
        }
    }

    if (empty($default_attrs)) return;

    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($default_attrs as $name => $value): ?>
                const select = document.querySelector('[name="<?php echo esc_js($name); ?>"]');
                if (select) {
                    select.value = "<?php echo esc_js($value); ?>";
                    select.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            <?php endforeach; ?>

            // Setelah WooCommerce update DOM, panggil updatePrice
            setTimeout(() => {
                const vid = document.querySelector('input[name="variation_id"]')?.value;
                const vars = jQuery('form.variations_form').data('product_variations');
                const sel = vars?.find(v => v.variation_id == vid);
                if (sel && typeof updatePrice === 'function') updatePrice(sel);
            }, 600);

            // Pastikan harga tetap sinkron saat variasi berubah manual
            jQuery('form.variations_form').on('woocommerce_variation_has_changed', function() {
                const vid2 = jQuery('input[name="variation_id"]').val();
                const vars2 = jQuery(this).data('product_variations');
                const sel2 = vars2?.find(v => v.variation_id == vid2);
                if (sel2 && typeof updatePrice === 'function') updatePrice(sel2);
            });
        });
    </script>
<?php
}
