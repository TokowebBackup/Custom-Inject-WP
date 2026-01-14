<?php
/*
Plugin Name: Manual QRIS Payment Gateway
Description: Gateway QRIS dummy manual untuk testing Tutor Paid Topic Addon
Author: Puji Ermanto
Version: 1.1
*/

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', function () {

    add_action('wp_head', function () {
        echo '<style>
        .payment_method_manual_qris img {
            width: 80px !important;
            height: auto !important;
            vertical-align: middle;
            margin-left: 8px;
            border-radius: 4px;
        }
    </style>';
    });

    class WC_Gateway_Manual_QRIS extends WC_Payment_Gateway
    {

        public $instructions;
        public $qris_image;

        public function __construct()
        {
            $this->id                 = 'manual_qris';
            $this->method_title       = 'Manual QRIS (Dummy)';
            $this->method_description = 'Simulasi pembayaran QRIS secara manual untuk testing.';
            $this->has_fields         = true;
            $this->icon               = plugin_dir_url(__FILE__) . 'qris-icon.png';
            $this->supports           = ['products'];

            $this->init_form_fields();
            $this->init_settings();

            $this->title        = $this->get_option('title');
            $this->description  = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');
            $this->qris_image   = !empty($this->settings['qris_image'])
                ? $this->settings['qris_image']
                : plugin_dir_url(__FILE__) . 'qris-dummy.png';

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
            add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
            add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
            add_action('woocommerce_order_details_before_order_table', [$this, 'receipt_page']);
            add_action('woocommerce_before_pay_form', [$this, 'force_show_qris_on_orderpay']);
        }

        public function receipt_page($order_id)
        {
            echo '<h3>Silakan Lakukan Pembayaran via QRIS</h3>';
            echo '<p>' . esc_html($this->description) . '</p>';
            $image_url = $this->qris_image ? esc_url($this->qris_image) : esc_url(plugin_dir_url(__FILE__) . 'qris-dummy.png');
            echo '<div style="margin:15px 0;text-align:center">';
            echo '<img src="' . $image_url . '" alt="QRIS Manual" style="max-width:250px;border:1px solid #ccc;border-radius:10px;">';
            echo '</div>';
            echo '<p style="font-size:13px;color:#666;">(Ini hanya simulasi pembayaran, tidak terhubung ke sistem QRIS asli.)</p>';
        }

        public function force_show_qris_on_orderpay($order)
        {
            if (!is_a($order, 'WC_Order')) return;

            // tampilkan hanya kalau gateway ini aktif
            if ($this->enabled !== 'yes') return;

            echo '<div style="margin:20px 0;padding:20px;border:1px solid #ddd;border-radius:10px;background:#fafafa;text-align:center">';
            echo '<h3>Silakan Lakukan Pembayaran via QRIS</h3>';
            echo '<p>' . esc_html($this->description) . '</p>';
            $image_url = $this->qris_image ? esc_url($this->qris_image) : esc_url(plugin_dir_url(__FILE__) . 'qris-dummy.png');
            echo '<img src="' . $image_url . '" alt="QRIS Manual" style="max-width:250px;border:1px solid #ccc;border-radius:10px;margin-top:10px;">';
            echo '<p style="font-size:13px;color:#666;margin-top:10px">(Ini hanya simulasi pembayaran, tidak terhubung ke sistem QRIS asli.)</p>';
            echo '</div>';
        }



        public function admin_scripts($hook)
        {
            if ('woocommerce_page_wc-settings' !== $hook) {
                return;
            }

            wp_enqueue_media();
            wp_add_inline_script('jquery', "
                jQuery(document).ready(function($){
                    var frame;
                    $(document).on('click', '.upload_qris_image_button', function(e){
                        e.preventDefault();
                        var button = $(this);
                        if (frame) frame.close();
                        frame = wp.media({
                            title: 'Pilih Gambar QRIS',
                            button: { text: 'Gunakan Gambar Ini' },
                            multiple: false
                        });
                        frame.on('select', function(){
                            var attachment = frame.state().get('selection').first().toJSON();
                            button.prev('input').val(attachment.url);
                            button.closest('td').find('.qris-preview').attr('src', attachment.url).show();
                        });
                        frame.open();
                    });
                });
            ");
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title'   => 'Enable/Disable',
                    'type'    => 'checkbox',
                    'label'   => 'Aktifkan Manual QRIS Payment',
                    'default' => 'yes'
                ],
                'title' => [
                    'title'       => 'Judul',
                    'type'        => 'text',
                    'default'     => 'QRIS Manual Payment (Dummy)',
                    'desc_tip'    => true
                ],
                'description' => [
                    'title'       => 'Deskripsi',
                    'type'        => 'textarea',
                    'default'     => 'Silakan scan QRIS di bawah untuk simulasi pembayaran.',
                ],
                'instructions' => [
                    'title'       => 'Instruksi di halaman Thank You',
                    'type'        => 'textarea',
                    'default'     => 'Terima kasih telah melakukan pembayaran via QRIS. Admin akan memverifikasi dan menyelesaikan pesanan Anda secara manual.',
                ],
                'qris_image' => [
                    'title'       => 'Gambar QRIS',
                    'type'        => 'text',
                    'description' => 'Pilih atau upload gambar QRIS dari Media Library.',
                    'default'     => plugin_dir_url(__FILE__) . 'qris-icon.png',
                    'css'         => 'width:60%;',
                    'desc_tip'    => true,
                ],
            ];
        }

        // Custom render field agar ada tombol upload
        public function admin_options()
        {
            parent::admin_options();

            echo "
            <script>
                jQuery(function($){
                    // Tambah tombol upload di bawah input qris_image
                    $('input[name=\"woocommerce_manual_qris_qris_image\"]').each(function(){
                        var field = $(this);
                        if(!field.next('.upload_qris_image_button').length){
                            field.after(' <button type=\"button\" class=\"button upload_qris_image_button\">Pilih Gambar</button>');
                            var preview = $('<br><img class=\"qris-preview\" src=\"'+field.val()+'\" style=\"max-width:150px;margin-top:10px;display:block;\">');
                            field.closest('td').append(preview);
                        }
                    });
                });
            </script>
            ";
        }

        public function payment_fields()
        {
            echo '<p>' . esc_html($this->description) . '</p>';
            echo '<div style="margin:10px 0;text-align:center">';
            $image_url = $this->qris_image ? esc_url($this->qris_image) : esc_url(plugin_dir_url(__FILE__) . 'qris-icon.png');
            echo '<img src="' . $image_url . '" alt="QRIS Manual" style="max-width:350px;border:1px solid #ccc;border-radius:10px;">';
            echo '</div>';
            echo '<p style="font-size:13px;color:#666;">(Ini hanya simulasi pembayaran, tidak terhubung ke sistem QRIS asli.)</p>';
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', 'Menunggu pembayaran manual QRIS.');
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();
            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            ];
        }

        public function thankyou_page()
        {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }
    }

    // Daftarkan gateway ke WooCommerce
    add_filter('woocommerce_payment_gateways', function ($methods) {
        $methods[] = 'WC_Gateway_Manual_QRIS';
        return $methods;
    });
});
