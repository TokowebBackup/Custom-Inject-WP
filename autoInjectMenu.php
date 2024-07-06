<?php
/*
Plugin Name: WhatsApp & Marketplace Settings
Description: Plugin untuk mengatur link WhatsApp dan marketplace.
Version: 1.0
Author: Puji Ermanto<pujiermanto@gmail.com>|<puji@tokoweb.co>
*/

// Menu Setting
add_action('admin_menu', 'register_custom_menus');
function register_custom_menus() {
    add_menu_page('Link Order', 'Link Order', 'manage_options', 'whatsapp_marketplace_settings', 'whatsapp_marketplace_settings_page');
}

// Halaman Setting
function whatsapp_marketplace_settings_page() {
    ?>
    <div class="wrap">
        <h1>Setting Link WhatsApp & Marketplace</h1>
        <form method="post" action="options.php">
            <?php settings_fields('whatsapp_marketplace_group'); ?>
            <?php do_settings_sections('whatsapp_marketplace_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">WhatsApp Order Number</th>
                    <td>
                        <?php
                        $whatsapp_number = esc_attr(get_option('whatsapp_order_number'));
                        // Jika nomor tidak kosong, konversi ke format internasional
                        if (!empty($whatsapp_number)) {
                            // Hapus karakter non-numeric
                            $whatsapp_number = preg_replace('/\D/', '', $whatsapp_number);
                            // Tambahkan awalan +62
                            if (substr($whatsapp_number, 0, 1) === '0') {
                                $whatsapp_number = '62' . substr($whatsapp_number, 1);
                            } else {
                                $whatsapp_number = '62' . $whatsapp_number;
                            }
                        }
                        ?>
                        <input type="text" name="whatsapp_order_number" value="<?php echo $whatsapp_number; ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Link Shopee</th>
                    <td><input type="text" name="shopee_link" value="<?php echo esc_attr(get_option('shopee_link')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Link TikTokShop</th>
                    <td><input type="text" name="tiktok_link" value="<?php echo esc_attr(get_option('tiktok_link')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Link Lazada</th>
                    <td><input type="text" name="lazada_link" value="<?php echo esc_attr(get_option('lazada_link')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Link Tokopedia</th>
                    <td><input type="text" name="tokopedia_link" value="<?php echo esc_attr(get_option('tokopedia_link')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Registrasi Setting
add_action('admin_init', 'register_whatsapp_marketplace_settings');
function register_whatsapp_marketplace_settings() {
    register_setting('whatsapp_marketplace_group', 'whatsapp_order_number', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'validate_callback' => 'validate_whatsapp_number'
    ));
    register_setting('whatsapp_marketplace_group', 'shopee_link');
    register_setting('whatsapp_marketplace_group', 'tiktok_link');
    register_setting('whatsapp_marketplace_group', 'lazada_link');
    register_setting('whatsapp_marketplace_group', 'tokopedia_link');
}

// Validasi Nomor WhatsApp
function validate_whatsapp_number($input) {
    // Hanya terima nomor WhatsApp dengan format yang diizinkan
    if (preg_match('/^(?:\+62|62|0)\d{9,12}$/', $input)) {
        return $input;
    } else {
        add_settings_error('whatsapp_order_number', 'invalid_whatsapp_number', 'Nomor WhatsApp tidak valid. Gunakan format yang benar.', 'error');
        return get_option('whatsapp_order_number');
    }
}
