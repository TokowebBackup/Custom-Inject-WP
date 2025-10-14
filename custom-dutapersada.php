<?php

/**
 * WooCommerce Custom Product Info + SweetAlert + Integrasi WhatsApp Chat dari Plugin Floating WhatsApp Chat
 * @author Tokoweb
 */

// =====================================================
// 1️⃣ Menu Admin: WhatsApp Chat jadi menu utama
// =====================================================
add_action('admin_menu', function () {
    add_menu_page(
        'Floating WhatsApp Chat',
        'WhatsApp Chat',
        'manage_options',
        'floating-whatsapp-chat-main',
        function () {
            if (function_exists('fwc_settings_page')) {
                fwc_settings_page();
            } else {
                echo '<div class="wrap"><h1>Floating WhatsApp Chat</h1><p>⚠️ Plugin Floating WhatsApp Chat belum aktif atau callback tidak ditemukan.</p></div>';
            }
        },
        'dashicons-format-chat',
        25
    );
}, 11);


// =====================================================
// 2️⃣ WooCommerce Custom Product Info
// =====================================================
add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';

    woocommerce_wp_select([
        'id' => '_product_status',
        'label' => 'Status Barang',
        'options' => [
            '' => 'Pilih status',
            'ready' => 'Ready Stock',
            'preorder' => 'Pre Order'
        ]
    ]);

    woocommerce_wp_text_input([
        'id' => '_estimasi_po',
        'label' => 'Estimasi PO (contoh: 2 minggu)',
    ]);

    woocommerce_wp_select([
        'id' => '_kondisi_barang',
        'label' => 'Kondisi Barang',
        'options' => [
            '' => 'Pilih kondisi',
            'baru' => 'Baru',
            'bekas' => 'Bekas'
        ]
    ]);

    woocommerce_wp_text_input([
        'id' => '_minimal_order',
        'label' => 'Minimal Order Qty',
        'type' => 'number',
        'desc_tip' => true,
        'description' => 'Jumlah minimal pembelian produk ini'
    ]);

    echo '</div>';
});

add_action('woocommerce_process_product_meta', function ($post_id) {
    $fields = ['_product_status', '_estimasi_po', '_kondisi_barang', '_minimal_order'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
});


// =====================================================
// 3️⃣ Tampilkan Info di Halaman Produk
// =====================================================
add_action('woocommerce_single_product_summary', function () {
    global $product;
    if (empty($product) || !is_object($product)) return;
    $status     = get_post_meta($product->get_id(), '_product_status', true);
    $estimasi   = get_post_meta($product->get_id(), '_estimasi_po', true);
    $kondisi    = get_post_meta($product->get_id(), '_kondisi_barang', true);
    $ekspedisi  = get_post_meta($product->get_id(), '_ekspedisi', true);
    $min_order  = get_post_meta($product->get_id(), '_minimal_order', true);

    echo '
    <style>
    .product-extra-info {
    background: #f9fafb;
    border: 1px solid #e3e3e3;
    border-radius: 8px;
    padding: 15px 18px;
    margin-top: 10px;
    font-size: 15px;
    }
    .product-extra-info p {
    margin: 6px 0;
    display: flex;
    justify-content: space-between;
    font-weight: 500;
    }
    .product-extra-info strong {
    color: #222;
    }
    .po-warning {
    background: #fff8e1;
    color: #8a6d3b;
    padding: 10px;
    border-radius: 6px;
    margin-top: 10px;
    font-weight: 500;
    }
    </style>
    ';

    echo '<div class="product-extra-info">';
    if ($status) {
        echo "<p><strong>Status Barang:</strong> " . ucfirst($status);
        if ($status === 'preorder' && $estimasi) echo " ({$estimasi})";
        echo "</p>";
    }

    if ($kondisi) echo "<p><strong>Kondisi:</strong> " . ucfirst($kondisi) . "</p>";
    if ($ekspedisi) echo "<p><strong>Ekspedisi:</strong> {$ekspedisi}</p>";
    if ($min_order) echo "<p><strong>Minimal Order:</strong> {$min_order} pcs</p>";

    if ($status === 'preorder') {
        echo '<div class="po-warning">⚠️ Barang ini <strong>Pre-Order (Full Payment)</strong>. Pengiriman akan dilakukan setelah barang tersedia.</div>';
    }

    echo '</div>';
}, 25);


// =====================================================
// 4️⃣ Validasi Minimal Order + SweetAlert (fix AJAX submit)
// =====================================================
add_action('wp_footer', function () {
    if (!is_product()) return;

    global $product;
    $product_id  = $product->get_id();
    $status      = get_post_meta($product_id, '_product_status', true);
    $min_order   = (int) get_post_meta($product_id, '_minimal_order', true);

    if (!$min_order) return;
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form.cart');
            const qtyInput = form ? form.querySelector('input.qty') : null;
            if (!form || !qtyInput) return;

            const minOrder = <?= $min_order ?>;
            const productStatus = "<?= esc_js($status) ?>";

            qtyInput.setAttribute('min', minOrder);
            qtyInput.value = minOrder;

            // 🔒 Intercept WooCommerce form submission
            jQuery('form.cart').on('submit', function(e) {
                const qty = parseInt(qtyInput.value);

                // 🔹 PRE ORDER: harus tepat sama dengan min order
                if (productStatus === 'preorder' && qty !== minOrder) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Jumlah Tidak Sesuai',
                        text: `Untuk produk Pre-Order, jumlah pembelian harus tepat ${minOrder} pcs.`,
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'Oke, saya ubah'
                    }).then(() => {
                        qtyInput.value = minOrder;
                        qtyInput.dispatchEvent(new Event('change', {
                            bubbles: true
                        })); // 🔥 trigger update ke UI
                    });

                    return false;
                }

                // 🔹 READY STOCK: minimal harus >= min order
                if (productStatus !== 'preorder' && qty < minOrder) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Minimal Order',
                        text: `Minimal pembelian untuk produk ini adalah ${minOrder} pcs.`,
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'Oke, saya ubah'
                    }).then(() => {
                        qtyInput.value = minOrder;
                        qtyInput.dispatchEvent(new Event('change', {
                            bubbles: true
                        })); // 🔥 pastikan update UI juga
                    });
                    return false;
                }

                // ✅ Lolos validasi, lanjutkan add-to-cart normal
                return true;
            });
        });
    </script>
<?php
});



// 🔸 Validasi backend WooCommerce
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity) {
    $min_order = (int) get_post_meta($product_id, '_minimal_order', true);
    $status    = get_post_meta($product_id, '_product_status', true);

    if ($status === 'preorder' && $quantity != $min_order) {
        wc_add_notice("Untuk produk Pre-Order, pembelian harus tepat {$min_order} pcs.", 'error');
        return false;
    }

    if ($quantity < $min_order) {
        wc_add_notice("Minimal pembelian untuk produk ini adalah {$min_order} pcs.", 'error');
        return false;
    }

    return $passed;
}, 10, 3);



// =====================================================
// 5️⃣ Tombol Share Produk
// =====================================================
add_action('woocommerce_single_product_summary', function () {
    global $product;
    $url = urlencode(get_permalink($product->get_id()));
    $title = urlencode($product->get_name());

    echo '<div class="product-share" style="margin-top:20px;font-size:15px;">';
    echo '<strong>Bagikan Produk:</strong> ';
    echo '<a href="https://wa.me/?text=' . $title . '%20' . $url . '" target="_blank" style="margin-right:10px;text-decoration:none;color:#25D366;font-weight:600;">📱 WhatsApp</a>';
    echo '<a href="#" onclick="navigator.clipboard.writeText(\'' . esc_js(get_permalink($product->get_id())) . '\'); Swal.fire({icon:\'success\', title:\'Tersalin!\', text:\'Link produk disalin ke clipboard!\', timer:1500, showConfirmButton:false}); return false;" style="text-decoration:none;color:#0073aa;font-weight:600;">🔗 Salin Link</a>';
    echo '</div>';
}, 40);


// =====================================================
// 6️⃣ Ganti semua link WhatsApp front-end dengan nomor dari plugin Floating WhatsApp Chat
// =====================================================
add_action('wp_footer', function () {
    $wa_number = get_option('fwc_whatsapp_number');
    if (!$wa_number) return;

    $wa_number = preg_replace('/^0/', '62', $wa_number);
?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newNumber = '<?php echo esc_js($wa_number); ?>';
            document.querySelectorAll('a[href*="wa.me"], a[href*="api.whatsapp.com"]').forEach(link => {
                link.href = link.href.replace(/\d{8,15}/, newNumber);
            });
        });
    </script>
<?php
});

// =====================================================
// 7️⃣ Tambah Badge "Pre Order" di Thumbnail Produk (Shop / Loop)
// =====================================================
add_action('woocommerce_before_shop_loop_item_title', function () {
    global $product;
    $status = get_post_meta($product->get_id(), '_product_status', true);

    if ($status === 'preorder') {
        echo '<span class="preorder-badge">Pre-Order</span>';
    } elseif ($status === 'ready') {
        echo '<span class="ready-badge">Ready Stock</span>';
    }
}, 9);

// Styling badge agar tampil profesional
add_action('wp_head', function () {
    echo '
    <style>
    .preorder-badge, .ready-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 5;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    .preorder-badge {
        background: #f59e0b; /* Amber */
    }
    .ready-badge {
        background: #16a34a; /* Green */
    }

    ul.products li.product {
        position: relative;
    }
    </style>
    ';
});


// =====================================================
// 8️⃣ Kolom "Status Barang" & "Min Order" di Admin List Produk
// =====================================================
add_filter('manage_edit-product_columns', function ($columns) {
    // Sisipkan setelah kolom 'sku'
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'sku') {
            $new['product_status'] = 'Status Barang';
            $new['min_order'] = 'Min Order';
        }
    }
    return $new;
});

add_action('manage_product_posts_custom_column', function ($column, $post_id) {
    if ($column === 'product_status') {
        $status = get_post_meta($post_id, '_product_status', true);
        if ($status === 'preorder') {
            echo '<span style="color:#f59e0b;font-weight:600;">Pre-Order</span>';
        } elseif ($status === 'ready') {
            echo '<span style="color:#16a34a;font-weight:600;">Ready</span>';
        } else {
            echo '<span style="color:#999;">-</span>';
        }
    }

    if ($column === 'min_order') {
        $min = get_post_meta($post_id, '_minimal_order', true);
        echo $min ? '<span style="font-weight:600;">' . esc_html($min) . '</span>' : '<span style="color:#999;">-</span>';
    }
}, 10, 2);

// Agar bisa di-sortir
add_filter('manage_edit-product_sortable_columns', function ($columns) {
    $columns['product_status'] = 'product_status';
    $columns['min_order'] = 'min_order';
    return $columns;
});

// =====================================================
// 💅 Perbaiki tampilan kolom "Status Barang" & "Min Order" di dashboard produk
// =====================================================
add_action('admin_head', function () {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'product') {
        echo '
        <style>
        .column-product_status, .column-min_order {
            width: 120px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .column-product_status span {
            display: inline-block !important;
            background: #fef3c7;
            color: #92400e;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }
        .column-product_status span[style*="color:#16a34a"] {
            background: #dcfce7;
            color: #166534;
        }
        .column-min_order span {
            display: inline-block;
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
        </style>
        ';
    }
});

// =====================================================
// 9️⃣ Validasi Minimal Order di Halaman Cart (SweetAlert + Lock Proceed Button)
// =====================================================
add_action('wp_footer', function () {
    if (!is_cart()) return;
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const qtyInputs = document.querySelectorAll('.woocommerce-cart-form input.qty');
            const checkoutBtn = document.querySelector('.checkout-button');

            let cartValid = true; // global flag

            // Fungsi untuk toggle tombol checkout
            function toggleCheckout(disable) {
                if (checkoutBtn) {
                    checkoutBtn.disabled = disable;
                    checkoutBtn.style.opacity = disable ? '0.5' : '1';
                    checkoutBtn.style.pointerEvents = disable ? 'none' : 'auto';
                }
            }

            qtyInputs.forEach(input => {
                const cartItem = input.closest('tr.cart_item');
                if (!cartItem) return;

                const productId = cartItem.querySelector('a.remove')?.getAttribute('data-product_id');
                if (!productId) return;

                // Ambil minimal order via REST API
                fetch(`/wp-json/tokoweb/v1/min-order/${productId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.min_order) return;
                        const minOrder = parseInt(data.min_order);

                        input.setAttribute('min', minOrder);

                        input.addEventListener('change', function(e) {
                            const newQty = parseInt(e.target.value);

                            if (newQty < minOrder) {
                                e.preventDefault();
                                e.target.value = minOrder;
                                cartValid = false;
                                toggleCheckout(true);

                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Minimal Order!',
                                    text: `Minimal pembelian produk ini adalah ${minOrder} pcs.`,
                                    confirmButtonColor: '#3085d6',
                                    confirmButtonText: 'Oke, ubah ke minimal'
                                }).then(() => {
                                    e.target.value = minOrder;
                                    jQuery('button[name="update_cart"]').prop('disabled', false).trigger('click');
                                });
                            } else {
                                cartValid = true;
                                toggleCheckout(false);
                            }
                        });
                    });
            });

            // Cegah checkout jika masih invalid
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', function(e) {
                    if (!cartValid) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Checkout Diblokir!',
                            text: 'Ada produk yang tidak memenuhi minimal order. Perbaiki dulu sebelum checkout.',
                            confirmButtonColor: '#3085d6'
                        });
                    }
                });
            }
        });
    </script>
<?php
});


// =====================================================
// 🔹 REST API untuk ambil minimal order produk di Cart
// =====================================================
add_action('rest_api_init', function () {
    register_rest_route('tokoweb/v1', '/min-order/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => function ($data) {
            $product_id = intval($data['id']);
            $min_order = get_post_meta($product_id, '_minimal_order', true);
            return [
                'min_order' => $min_order ? (int)$min_order : 0
            ];
        },
        'permission_callback' => '__return_true'
    ]);
});


// =====================================================
// 🔒 Validasi Server-Side saat Update Qty di Cart (tetap aman walau JS dimatikan)
// =====================================================
// --- B) Perbaiki: gunakan add_filter untuk update cart validation (bukan add_action)
add_filter('woocommerce_update_cart_validation', function ($passed, $cart_item_key, $values, $quantity) {
    $product_id = $values['product_id'];
    $min_order = (int) get_post_meta($product_id, '_minimal_order', true);
    $status = get_post_meta($product_id, '_product_status', true);

    if ($status === 'preorder' && $quantity != $min_order) {
        wc_add_notice("Untuk produk Pre-Order, pembelian harus tepat {$min_order} pcs.", 'error');
        return false;
    }

    if ($quantity < $min_order) {
        wc_add_notice("Minimal pembelian untuk produk ini adalah {$min_order} pcs.", 'error');
        return false;
    }

    return $passed;
}, 10, 4);


// =====================================================
// ✅ Gabungan: Filter Ekspedisi Sesuai Produk + Mode Preorder
// =====================================================
// --- C) Saat semua item preorder, kembalikan WC_Shipping_Rate yang benar
add_filter('woocommerce_package_rates', function ($rates, $package) {
    $allowed_shipping = [];
    $all_preorder = true;
    $has_preorder = false;

    foreach (WC()->cart->get_cart() as $item) {
        $product_id = $item['product_id'];
        $status = get_post_meta($product_id, '_product_status', true);
        $ekspedisi_str = get_post_meta($product_id, '_ekspedisi', true);

        if ($status !== 'preorder') $all_preorder = false;
        if ($status === 'preorder') $has_preorder = true;

        if ($ekspedisi_str) {
            $list = array_map('trim', explode(',', strtolower($ekspedisi_str)));
            $allowed_shipping = array_merge($allowed_shipping, $list);
        }
    }

    if ($all_preorder) {
        // buat instance WC_Shipping_Rate
        $rate = new WC_Shipping_Rate('preorder_shipping', '🚚 Pre-Order Shipping (Rp 0 – Ongkir menyusul)', 0, array(), 'preorder_shipping');
        return array('preorder_shipping' => $rate);
    }

    if ($has_preorder && !$all_preorder) {
        wc_clear_notices();
        wc_add_notice('⚠️ Keranjang Anda berisi produk <strong>Pre-Order</strong> dan <strong>Ready Stock</strong>. Pengiriman bisa terpisah.', 'notice');
    }

    if (!empty($allowed_shipping)) {
        $allowed_shipping = array_unique($allowed_shipping);
        foreach ($rates as $id => $rate) {
            if (! ($rate instanceof WC_Shipping_Rate)) continue;
            $label = strtolower($rate->label ?? '');
            $method = strtolower($rate->method_id ?? '');
            $ok = false;
            foreach ($allowed_shipping as $eksp) {
                if (strpos($label, $eksp) !== false || strpos($method, $eksp) !== false) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) unset($rates[$id]);
        }
    }

    if (empty($rates)) {
        wc_add_notice('⚠️ Tidak ada ekspedisi yang sesuai dengan pengaturan produk.', 'error');
    }

    return $rates;
}, 20, 2);
