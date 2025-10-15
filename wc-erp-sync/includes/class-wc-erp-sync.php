<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_ERP_Sync
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_settings_page'], 99);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_checkout_order_processed', [$this, 'send_order_to_erp'], 10, 1);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public static function activate()
    {
        if (!get_option('wc_erp_sync_settings')) {
            update_option('wc_erp_sync_settings', [
                'api_key' => '',
                'endpoint' => '',
            ]);
        }
    }

    public static function deactivate() {}

    public function enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_wc-erp-sync') return;

        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);

        wp_add_inline_style('wp-admin', '
        .erp-sync-card {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            max-width: 700px;
            margin-top: 20px;
        }
        .erp-sync-card h1 {
            font-size: 24px;
            margin-bottom: 15px;
        }
        .erp-sync-table th {
            width: 200px;
            text-align: left;
            padding-top: 10px;
        }
        .api-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .api-input-wrapper input {
            width: 100%;
            padding-right: 40px !important;
            box-sizing: border-box;
        }
        .lock-btn {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }
        .lock-btn:hover {
            opacity: 0.7;
        }
        .generate-btn {
            background-color: #2271b1;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        .generate-btn:hover {
            background-color: #135e96;
        }
        /* 👁️ Ikon show password di dalam SweetAlert */
        .swal2-input-wrapper {
            position: relative;
        }
        .swal2-showpass-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
    ');

        wp_add_inline_script('jquery-core', '
        jQuery(document).ready(function($) {

            const apiField = $("input[name=\'api_key\']");
            apiField.wrap("<div class=\'api-input-wrapper\'></div>");
            const lockBtn = $("<button>")
                .attr("type","button")
                .addClass("lock-btn")
                .text("🔒")
                .insertAfter(apiField);

            apiField.prop("disabled", true);

            // 🔐 Toggle lock/unlock
            lockBtn.on("click", function() {
                const locked = apiField.prop("disabled");
                apiField.prop("disabled", !locked);
                $(this).text(locked ? "🔓" : "🔒");
            });

            // 🧠 Generate API Key with password check
            $("#generate-api-key").on("click", function(e) {
                e.preventDefault();

                Swal.fire({
                    title: "Masukkan Password Admin",
                    input: "password",
                    inputPlaceholder: "Masukkan password...",
                    showCancelButton: true,
                    confirmButtonText: "Konfirmasi",
                    cancelButtonText: "Batal",
                    inputAttributes: { autocapitalize: "off" },
                    didOpen: () => {
                        // Tambahkan tombol 👁️ show/hide password
                        const input = Swal.getInput();
                        const wrapper = input.parentElement;
                        const btn = document.createElement("button");
                        btn.type = "button";
                        btn.innerHTML = "👁️";
                        btn.className = "swal2-showpass-btn";
                        btn.addEventListener("click", () => {
                            input.type = input.type === "password" ? "text" : "password";
                            btn.innerHTML = input.type === "password" ? "👁️" : "🙈";
                        });
                        wrapper.appendChild(btn);
                    },
                    preConfirm: (value) => {
                        if (value !== "@#$duta28S") {
                            Swal.showValidationMessage("❌ Password salah!");
                        }
                        return value;
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value === "@#$duta28S") {
                        const randomKey = Array.from(crypto.getRandomValues(new Uint8Array(24)))
                            .map(b => ("0" + b.toString(16)).slice(-2))
                            .join("");
                        apiField.val(randomKey).prop("disabled", true);
                        lockBtn.text("🔒");
                        Swal.fire("✅ Berhasil", "API Key baru berhasil digenerate!", "success");
                    }
                });
            });

            // ✅ Toast on save
            if (window.location.href.includes("settings-updated=true")) {
                const toast = $("<div>")
                    .text("Settings Saved Successfully ✅")
                    .css({
                        position: "fixed",
                        bottom: "20px",
                        right: "20px",
                        background: "#2271b1",
                        color: "#fff",
                        padding: "10px 15px",
                        borderRadius: "6px",
                        boxShadow: "0 2px 6px rgba(0,0,0,0.2)",
                        zIndex: "9999"
                    });
                $("body").append(toast);
                setTimeout(() => toast.fadeOut(500, () => toast.remove()), 3000);
            }
        });
    ');
    }

    public function register_settings_page()
    {
        add_menu_page(
            'ERP Sync',
            'ERP Sync',
            'manage_options',
            'wc-erp-sync',
            [$this, 'settings_page_html'],
            'dashicons-randomize',
            56
        );
    }

    public function settings_page_html()
    {
        if (!current_user_can('manage_options')) return;

        $settings = get_option('wc_erp_sync_settings');

        // 🚀 Auto-set endpoint jika kosong
        if (empty($settings['endpoint'])) {
            $settings['endpoint'] = esc_url_raw(rest_url('erp/v1/product'));
            update_option('wc_erp_sync_settings', $settings);
        }

        // ✅ Handle Save
        if (isset($_POST['save_erp_sync'])) {
            check_admin_referer('save_erp_sync');
            update_option('wc_erp_sync_settings', [
                'api_key'  => sanitize_text_field($_POST['api_key']),
                'endpoint' => esc_url_raw($_POST['endpoint']),
            ]);
            echo '<div class="updated"><p><strong>Settings saved successfully!</strong></p></div>';
        }

?>
        <div class="wrap">
            <div class="erp-sync-card">
                <h1><span class="dashicons dashicons-randomize"></span> ERP Sync Integration</h1>
                <p class="description">Connect your WooCommerce store with your ERP system using secure REST API integration.</p>
                <hr>

                <form method="post">
                    <?php wp_nonce_field('save_erp_sync'); ?>
                    <table class="form-table erp-sync-table">
                        <tr>
                            <th scope="row">ERP API Key</th>
                            <td>
                                <input type="text" name="api_key" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" class="regular-text" placeholder="Click Generate Key below">
                                <p><button id="generate-api-key" class="generate-btn" type="button">Generate Key 🔑</button></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">ERP Endpoint URL</th>
                            <td>
                                <input type="url" name="endpoint" value="<?php echo esc_attr($settings['endpoint']); ?>" class="regular-text">
                                <p class="description">This URL is automatically generated based on your site domain.</p>
                            </td>
                        </tr>
                    </table>

                    <p style="margin-top: 20px;">
                        <button type="submit" name="save_erp_sync" class="button button-primary button-large">💾 Save Changes</button>
                    </p>
                </form>
            </div>
        </div>
<?php
    }

    // === REST API & ERP Sync Core ===
    // public function register_rest_routes()
    // {
    //     register_rest_route('erp/v1', '/product', [
    //         'methods' => 'POST',
    //         'callback' => [$this, 'handle_product_sync'],
    //         'permission_callback' => [$this, 'check_api_key_permission'],
    //     ]);
    // }

    public function register_rest_routes()
    {
        // === Endpoint utama ===
        register_rest_route('erp/v1', '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products_list'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);

        // === Endpoint schema (Swagger UI) ===
        register_rest_route('erp/v1', '/schema', [
            'methods' => 'GET',
            'callback' => [$this, 'get_api_schema'],
            'permission_callback' => '__return_true', // publik
        ]);

        // === Endpoint daftar order ===
        register_rest_route('erp/v1', '/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_orders_list'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);

        // === Endpoint detail order ===
        register_rest_route('erp/v1', '/orders/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_order_detail'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);

        // =====================================================================
        // Arah: ERP → WooCommerce
        // =====================================================================
        register_rest_route('erp/v1', '/product-sync', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_product_sync'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);

        // === Update produk dari ERP ke WooCommerce ===
        register_rest_route('erp/v1', '/product-sync', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_product_from_erp'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);

        // === Hapus produk berdasarkan SKU ERP ===
        register_rest_route('erp/v1', '/product-sync/(?P<sku_erp>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_product_from_erp'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);

        // === Informasi Toko WooCommerce ===
        register_rest_route('erp/v1', '/store-info', [
            'methods' => 'GET',
            'callback' => [$this, 'get_store_info'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);
    }

    public function get_api_schema()
    {
        return [
            'openapi' => '3.0.1',
            'info' => [
                'title' => 'WC ERP API',
                'version' => '1.1.0',
                'description' => 'Dokumentasi API integrasi WooCommerce & ERP (CRUD Produk + Orders)',
            ],
            'servers' => [
                ['url' => esc_url_raw(rest_url('erp/v1'))],
            ],
            'paths' => [
                '/store-info' => [
                    'get' => [
                        'summary' => 'Ambil Informasi Toko WooCommerce',
                        'description' => 'Mengambil informasi umum toko seperti nama, alamat, nomor telepon, dan koordinat lokasi.',
                        'parameters' => [[
                            'name' => 'X-ERP-KEY',
                            'in' => 'header',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                            'description' => 'API key ERP untuk autentikasi'
                        ]],
                        'responses' => [
                            '200' => [
                                'description' => 'Informasi toko berhasil diambil',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'success' => ['type' => 'boolean'],
                                                'data' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'store_name' => ['type' => 'string'],
                                                        'store_url' => ['type' => 'string'],
                                                        'store_email' => ['type' => 'string'],
                                                        'address' => ['type' => 'string'],
                                                        'address_2' => ['type' => 'string'],
                                                        'city' => ['type' => 'string'],
                                                        'postcode' => ['type' => 'string'],
                                                        'country' => ['type' => 'string'],
                                                        'phone' => ['type' => 'string'],
                                                        'latitude' => ['type' => 'string'],
                                                        'longitude' => ['type' => 'string']
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '401' => ['description' => 'API key tidak valid']
                        ]
                    ]
                ],

                '/products' => [
                    'get' => [
                        'summary' => 'Ambil Daftar Produk',
                        'description' => 'Menarik data produk dari WooCommerce untuk sinkronisasi ERP.',
                        'parameters' => [[
                            'name' => 'X-ERP-KEY',
                            'in' => 'header',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                            'description' => 'API key ERP untuk autentikasi'
                        ]],
                        'responses' => [
                            '200' => ['description' => 'Daftar produk berhasil diambil'],
                            '401' => ['description' => 'API key tidak valid'],
                        ]
                    ]
                ],

                '/orders' => [
                    'get' => [
                        'summary' => 'Ambil Daftar Order',
                        'description' => 'ERP menarik daftar order terbaru dari WooCommerce.',
                        'parameters' => [[
                            'name' => 'X-ERP-KEY',
                            'in' => 'header',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ]],
                        'responses' => [
                            '200' => ['description' => 'Daftar order berhasil diambil'],
                            '401' => ['description' => 'API key tidak valid']
                        ]
                    ]
                ],

                '/orders/{id}' => [
                    'get' => [
                        'summary' => 'Ambil Detail Order',
                        'description' => 'Menampilkan detail order tertentu berdasarkan ID.',
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer']
                            ],
                            [
                                'name' => 'X-ERP-KEY',
                                'in' => 'header',
                                'required' => true,
                                'schema' => ['type' => 'string']
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Detail order ditemukan'],
                            '404' => ['description' => 'Order tidak ditemukan'],
                            '401' => ['description' => 'API key tidak valid']
                        ]
                    ]
                ],

                '/product-sync' => [
                    'post' => [
                        'summary' => 'Tambah Produk dari ERP ke WooCommerce',
                        'description' => 'ERP mengirimkan data produk baru ke WooCommerce.',
                        'parameters' => [[
                            'name' => 'X-ERP-KEY',
                            'in' => 'header',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'sku_erp' => ['type' => 'string'],
                                            'name'    => ['type' => 'string'],
                                            'price'   => ['type' => 'number'],
                                            'stock'   => ['type' => 'integer']
                                        ],
                                        'required' => ['sku_erp', 'name']
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Produk berhasil ditambahkan'],
                            '400' => ['description' => 'Data tidak lengkap'],
                            '401' => ['description' => 'API key tidak valid']
                        ]
                    ]
                ],

                '/product-update' => [
                    'put' => [
                        'summary' => 'Update Produk dari ERP ke WooCommerce',
                        'description' => 'ERP memperbarui data produk yang sudah ada berdasarkan SKU ERP.',
                        'parameters' => [[
                            'name' => 'X-ERP-KEY',
                            'in' => 'header',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'sku_erp' => ['type' => 'string'],
                                            'name'    => ['type' => 'string'],
                                            'price'   => ['type' => 'number'],
                                            'stock'   => ['type' => 'integer']
                                        ],
                                        'required' => ['sku_erp']
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Produk berhasil diperbarui'],
                            '404' => ['description' => 'Produk tidak ditemukan'],
                            '401' => ['description' => 'API key tidak valid']
                        ]
                    ]
                ],

                '/product-delete/{sku_erp}' => [
                    'delete' => [
                        'summary' => 'Hapus Produk di WooCommerce dari ERP',
                        'description' => 'ERP menghapus produk di WooCommerce berdasarkan SKU ERP.',
                        'parameters' => [
                            [
                                'name' => 'sku_erp',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string']
                            ],
                            [
                                'name' => 'X-ERP-KEY',
                                'in' => 'header',
                                'required' => true,
                                'schema' => ['type' => 'string']
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Produk berhasil dihapus'],
                            '404' => ['description' => 'Produk tidak ditemukan'],
                            '401' => ['description' => 'API key tidak valid']
                        ]
                    ]
                ],
            ]
        ];
    }

    // ==========================================================================
    // Handler api-docs
    // ==========================================================================
    public function get_store_info($request)
    {
        $store_info = [
            'store_name'   => get_bloginfo('name'),
            'store_url'    => get_bloginfo('url'),
            'store_email'  => get_bloginfo('admin_email'),
            'address'      => get_option('woocommerce_store_address'),
            'address_2'    => get_option('woocommerce_store_address_2'),
            'city'         => get_option('woocommerce_store_city'),
            'postcode'     => get_option('woocommerce_store_postcode'),
            'country'      => get_option('woocommerce_default_country'),
            'phone'        => get_option('woocommerce_store_phone'),
            'latitude'     => get_option('woocommerce_store_latitude'),
            'longitude'    => get_option('woocommerce_store_longitude'),
        ];

        return rest_ensure_response([
            'success' => true,
            'data'    => $store_info,
        ]);
    }


    public function get_products_list($request)
    {
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 100,
            'post_status'    => 'publish',
        ];

        $products = get_posts($args);
        $data = [];

        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            $data[] = [
                'id'       => $product->ID,
                'sku'      => $wc_product->get_sku(),
                'sku_erp'  => get_post_meta($product->ID, '_sku_erp', true),
                'name'     => $product->post_title,
                'price'    => $wc_product->get_price(),
                'stock'    => $wc_product->get_stock_quantity(),
            ];
        }

        return rest_ensure_response($data);
    }


    public function get_orders_list($request)
    {
        $args = [
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['completed', 'processing'],
        ];

        $orders = wc_get_orders($args);
        $data = [];

        foreach ($orders as $order) {
            $data[] = [
                'order_id'   => $order->get_id(),
                'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'customer'   => [
                    'id'      => $order->get_user_id(),
                    'name'    => $order->get_formatted_billing_full_name(),
                    'email'   => $order->get_billing_email(),
                    'address' => $order->get_billing_address_1(),
                ],
                'total'      => $order->get_total(),
                'status'     => $order->get_status(),
            ];
        }

        return rest_ensure_response($data);
    }

    public function get_order_detail($request)
    {
        $order_id = intval($request['id']);
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('not_found', 'Order not found', ['status' => 404]);
        }

        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = [
                'sku_erp'   => get_post_meta($product->get_id(), '_sku_erp', true),
                'product'   => $item->get_name(),
                'qty'       => $item->get_quantity(),
                'unit_price' => $product ? $product->get_price() : 0,
                'subtotal'  => $item->get_total(),
            ];
        }

        $shipping = $order->get_shipping_methods();
        $shipping_data = [];
        foreach ($shipping as $method) {
            $shipping_data[] = [
                'courier_name' => $method->get_name(),
                'total_weight' => $order->get_meta('_weight'),
                'shipping_fee' => $method->get_total(),
            ];
        }

        $payment = [
            'method' => $order->get_payment_method_title(),
            'amount' => $order->get_total(),
        ];

        $data = [
            'order_id'    => $order->get_id(),
            'transaction_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'customer'    => [
                'id'      => $order->get_user_id(),
                'name'    => $order->get_formatted_billing_full_name(),
                'address' => $order->get_billing_address_1(),
            ],
            'items'       => $items,
            'shipping'    => $shipping_data,
            'payment'     => $payment,
        ];

        return rest_ensure_response($data);
    }

    public function get_categories($request)
    {
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $data = [];

        foreach ($terms as $term) {
            $data[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }

        return rest_ensure_response($data);
    }

    public function get_brands($request)
    {
        if (!taxonomy_exists('product_brand')) {
            return new WP_Error('not_found', 'Brand taxonomy not found', ['status' => 404]);
        }

        $terms = get_terms(['taxonomy' => 'product_brand', 'hide_empty' => false]);
        $data = [];

        foreach ($terms as $term) {
            $data[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }

        return rest_ensure_response($data);
    }


    public function check_api_key_permission($request)
    {
        $settings = get_option('wc_erp_sync_settings');
        $key = $request->get_header('x-erp-key');
        return $key && $key === ($settings['api_key'] ?? '');
    }

    // ================================================================================
    // Product sync
    // ================================================================================
    public function handle_product_sync($request)
    {
        $params = $request->get_json_params();
        if (empty($params['sku_erp']) || empty($params['name'])) {
            return new WP_Error('missing_data', 'Missing SKU_ERP or Name', ['status' => 400]);
        }

        $product_id = wc_get_product_id_by_sku($params['sku_erp']);
        if ($product_id) {
            wp_update_post([
                'ID' => $product_id,
                'post_title' => sanitize_text_field($params['name']),
            ]);
        } else {
            $product_id = wp_insert_post([
                'post_title'  => sanitize_text_field($params['name']),
                'post_type'   => 'product',
                'post_status' => 'publish',
            ]);
            update_post_meta($product_id, '_sku', sanitize_text_field($params['sku_erp']));
        }

        return ['success' => true, 'product_id' => $product_id];
    }

    /**
     * 🧩 UPDATE Produk (ERP -> WooCommerce)
     */
    public function update_product_from_erp($request)
    {
        $params = $request->get_json_params();
        $sku_erp = sanitize_text_field($params['sku_erp'] ?? '');
        if (empty($sku_erp)) {
            return new WP_Error('missing_sku', 'Missing sku_erp', ['status' => 400]);
        }

        $product_id = wc_get_product_id_by_sku($sku_erp);
        if (!$product_id) {
            return new WP_Error('not_found', 'Product not found', ['status' => 404]);
        }

        $update_data = [];

        if (!empty($params['name'])) {
            $update_data['post_title'] = sanitize_text_field($params['name']);
        }

        if (!empty($update_data)) {
            $update_data['ID'] = $product_id;
            wp_update_post($update_data);
        }

        if (isset($params['price'])) {
            update_post_meta($product_id, '_price', floatval($params['price']));
            update_post_meta($product_id, '_regular_price', floatval($params['price']));
        }

        if (isset($params['stock'])) {
            update_post_meta($product_id, '_stock', intval($params['stock']));
            wc_update_product_stock_status($product_id, intval($params['stock']) > 0 ? 'instock' : 'outofstock');
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Product updated successfully',
            'product_id' => $product_id,
        ]);
    }


    /**
     * 🗑️ DELETE Produk (ERP -> WooCommerce)
     */
    public function delete_product_from_erp($request)
    {
        $sku_erp = sanitize_text_field($request['sku_erp']);
        if (empty($sku_erp)) {
            return new WP_Error('missing_sku', 'Missing SKU ERP', ['status' => 400]);
        }

        $product_id = wc_get_product_id_by_sku($sku_erp);
        if (!$product_id) {
            return new WP_Error('not_found', 'Product not found', ['status' => 404]);
        }

        $deleted = wp_delete_post($product_id, true);

        if (!$deleted) {
            return new WP_Error('delete_failed', 'Failed to delete product', ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Product deleted successfully',
            'deleted_id' => $product_id,
        ]);
    }


    public function send_order_to_erp($order_id)
    {
        $settings = get_option('wc_erp_sync_settings');
        if (empty($settings['endpoint']) || empty($settings['api_key'])) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $data = [
            'order_id'   => $order->get_id(),
            'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'total'      => $order->get_total(),
            'status'     => $order->get_status(),
            'billing'    => $order->get_address('billing'),
            'items'      => [],
        ];

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $data['items'][] = [
                'sku_erp' => $product ? $product->get_sku() : '',
                'name'    => $item->get_name(),
                'qty'     => $item->get_quantity(),
                'price'   => $item->get_total(),
            ];
        }

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-ERP-KEY'    => $settings['api_key'],
            ],
            'body'    => wp_json_encode($data),
            'timeout' => 20,
        ];

        $response = wp_remote_post($settings['endpoint'], $args);
        update_post_meta($order_id, '_erp_sync_response', wp_remote_retrieve_body($response));
    }
}
