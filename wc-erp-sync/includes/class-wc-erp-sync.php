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
            .erp-sync-table td input {
                width: 100%;
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
        ');

        wp_add_inline_script('jquery-core', '
            jQuery(document).ready(function($) {
                // Generate API Key
                $("#generate-api-key").on("click", function(e){
                    e.preventDefault();
                    const randomKey = Array.from(crypto.getRandomValues(new Uint8Array(24)))
                        .map(b => ("0" + b.toString(16)).slice(-2))
                        .join("");
                    $("input[name=\'api_key\']").val(randomKey);
                });

                // Simple toast notification
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
    public function register_rest_routes()
    {
        register_rest_route('erp/v1', '/product', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_product_sync'],
            'permission_callback' => [$this, 'check_api_key_permission'],
        ]);
    }

    public function check_api_key_permission($request)
    {
        $settings = get_option('wc_erp_sync_settings');
        $key = $request->get_header('x-erp-key');
        return $key && $key === ($settings['api_key'] ?? '');
    }

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
