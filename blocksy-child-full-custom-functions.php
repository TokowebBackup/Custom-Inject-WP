<?php
if (! defined('WP_DEBUG')) {
	die( 'Direct access forbidden.' );
}
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
});

// Sweetalert
function enqueue_sweetalert_and_custom_script() {
    // Enqueue SweetAlert2
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, false);
}
add_action('wp_enqueue_scripts', 'enqueue_sweetalert_and_custom_script');

// Welcome screen custom
function custom_admin_dashboard_text() {
    global $wp_version;
    
    // Ganti teks "Welcome to WordPress!" menjadi sesuai keinginan Anda
    $welcome_text = 'Selamat datang di Evadne Beauty Dashboard';
    
    // Ganti teks "Learn more about the 6.5.5 version." sesuai keinginan Anda
    $version_text = 'Pelajari lebih lanjut tentang versi %s.';
    
    // Mengganti teks menggunakan filter
    add_filter('gettext', function($translated_text, $text, $domain) use ($welcome_text, $version_text, $wp_version) {
        if ($text === 'Welcome to WordPress!') {
            $translated_text = $welcome_text;
        }
        if ($text === 'Learn more about the %s version.') {
            $translated_text = sprintf($version_text, $wp_version);
        }
        return $translated_text;
    }, 10, 3);
}
add_action('admin_init', 'custom_admin_dashboard_text');


// Cron Schedule
function custom_cron_schedules($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60,
        'display'  => esc_html__('Every Minute'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'custom_cron_schedules');

// Jalankan Action Scheduler setiap menit
if (!wp_next_scheduled('action_scheduler_run_queue')) {
    wp_schedule_event(time(), 'every_minute', 'action_scheduler_run_queue');
}
// End cron schedule

function main_styles() {
    wp_enqueue_style('main-styles', get_stylesheet_directory_uri() . '/css/main-styles.css', array(), null, 'all');
}
add_action('wp_enqueue_scripts', 'main_styles');

function add_font_awesome() {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">';
}
add_action('wp_head', 'add_font_awesome');

add_action( 'blocksy:header:inside:after_cart', 'add_wishlist_to_header' );
function add_wishlist_to_header() {
    if ( class_exists( 'YITH_WCWL' ) ) {
        ?>
        <div class="header-wishlist">
            <a href="<?php echo esc_url( YITH_WCWL()->get_wishlist_url() ); ?>">
                <i class="fa fa-heart"></i>
                <span class="wishlist-count">
                    <?php echo yith_wcwl_count_products(); ?>
                </span>
            </a>
        </div>
        <?php
    }
}

// Product lists
/**
 * @author : Puji Ermanto <puji@tokoweb.co>
 * Custom product lists [shortcode]
 * */
function custom_product_list($atts) {
    $atts = shortcode_atts(array(
        'paged' => 1,
    ), $atts, 'custom_product_list');

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => 6, 
        'paged' => $atts['paged'],
    );

    $loop = new WP_Query($args);

    if ($loop->have_posts()) {
        echo '<div id="product-list" class="product-list">';
        while ($loop->have_posts()) : $loop->the_post();
            global $product;
            ?>
            <div class="container">
                <?php if (has_post_thumbnail()) : ?>
                    <img src="<?php the_post_thumbnail_url('full'); ?>" alt="<?php the_title(); ?>" class="image">
                <?php endif; ?>
                <div class="middle">
                    <a href="<?php the_permalink(); ?>" class="add-to-cart-button">Order Now</a>
                </div>
                <div class="product-info">
                    <h2><?php the_title(); ?></h2>
                    <p><?php echo $product->get_price_html(); ?></p>
                </div>
            </div>
            <?php
        endwhile;
        echo '</div>'; 
        
        if ($loop->max_num_pages > $atts['paged']) {
            ?>
            <div class="load-more-container">
                <button class="load-more" data-page="<?php echo $atts['paged'] + 1; ?>">Load More</button>
                <!--<div class="loading-spinner" style="display:none;">Loading...</div>-->
                <br/>
                <img class="loading-spinner" src="https://evadnebeauty.com/wp-content/uploads/2024/07/foodrush-loader.gif" style="display:none;" width="150" />
            </div>
            <?php
        }
    } else {
        echo __('No products found');
    }

    wp_reset_postdata();
}
add_shortcode('custom_product_list', 'custom_product_list');

add_action('wp_ajax_custom_load_more', 'custom_load_more');
add_action('wp_ajax_nopriv_custom_load_more', 'custom_load_more');
function custom_load_more() {
    $paged = $_GET['paged'];

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => 6,
        'paged' => $paged,
    );

    $loop = new WP_Query($args);

    if ($loop->have_posts()) {
        ob_start();
        while ($loop->have_posts()) : $loop->the_post();
            global $product;
            ?>
            <div class="container">
                <?php if (has_post_thumbnail()) : ?>
                    <img src="<?php the_post_thumbnail_url('full'); ?>" alt="<?php the_title(); ?>" class="image">
                <?php endif; ?>
                <div class="middle">
                    <a href="<?php the_permalink(); ?>" class="add-to-cart-button">Order Now</a>
                </div>
                <div class="product-info">
                    <h2><?php the_title(); ?></h2>
                    <p><?php echo $product->get_price_html(); ?></p>
                </div>
            </div>
            <?php
        endwhile;

        $response = ob_get_clean();
        echo $response;
    }

    wp_reset_postdata();
    wp_die();
}


// Enqueue custom scripts
function enqueue_custom_scripts() {
    wp_enqueue_script('custom-script', get_stylesheet_directory_uri() . '/js/custom-script.js', array('jquery'), null, true);
    wp_localize_script('custom-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');


// Recieved order upload bukti transfer
function handle_proof_of_payment_upload() {
    if (
        isset( $_POST['upload_proof_of_payment_nonce'] ) &&
        wp_verify_nonce( $_POST['upload_proof_of_payment_nonce'], 'upload_proof_of_payment' ) &&
        isset( $_FILES['proof_of_payment'] ) &&
        ! empty( $_FILES['proof_of_payment']['name'] )
    ) {
        $order_id = intval( $_POST['order_id'] );
        $uploaded_file = $_FILES['proof_of_payment'];

        // Handle file upload
        $upload = wp_handle_upload( $uploaded_file, array( 'test_form' => false ) );

        if ( isset( $upload['file'] ) ) {
            $file_url = $upload['url'];
            update_post_meta( $order_id, '_proof_of_payment', esc_url( $file_url ) );

            wc_add_notice( __( 'Proof of payment uploaded successfully.', 'woocommerce' ), 'success' );
        } else {
            wc_add_notice( __( 'There was an error uploading your file.', 'woocommerce' ), 'error' );
        }
    }
}
add_action( 'wp_loaded', 'handle_proof_of_payment_upload' );


//Custom view product woocommerce 
// Periksa dan hapus tindakan jika sudah ada
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);

// Menghapus tombol "Add to Cart" di halaman produk tunggal
if (!function_exists('custom_remove_add_to_cart_buttons')) {
    function custom_remove_add_to_cart_buttons() {
        if (is_product()) {
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        }
    }
}
add_action('wp', 'custom_remove_add_to_cart_buttons', 99);

// Menghapus tombol "Add to Cart" di halaman arsip produk (seperti halaman kategori)
if (!function_exists('custom_remove_loop_add_to_cart_buttons')) {
    function custom_remove_loop_add_to_cart_buttons() {
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
    }
}
add_action('wp', 'custom_remove_loop_add_to_cart_buttons', 99);

// Menyembunyikan tombol "Add to Cart" dengan CSS
if (!function_exists('custom_hide_add_to_cart_buttons_with_css')) {
    function custom_hide_add_to_cart_buttons_with_css() {
        if (is_product() || is_shop() || is_product_category() || is_product_tag()) {
            ?>
            <style>
                .single_add_to_cart_button,
                .add_to_cart_button {
                    display: none !important;
                }
            </style>
            <?php
        }
    }
}
add_action('wp_head', 'custom_hide_add_to_cart_buttons_with_css');

// Menghapus tombol "Add to Cart" di seluruh situs dengan cara yang lebih kuat
if (!function_exists('remove_add_to_cart_buttons_everywhere')) {
    function remove_add_to_cart_buttons_everywhere() {
        // Single product pages
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);

        // Archive pages
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);

        // Remove from AJAX fragments (for updated fragments in mini-cart, etc.)
        add_filter('woocommerce_add_to_cart_fragments', function($fragments) {
            unset($fragments['.woocommerce-message']);
            return $fragments;
        });
    }
}
add_action('template_redirect', 'remove_add_to_cart_buttons_everywhere', 99);

function custom_remove_product_meta() {
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 10);
}
add_action('wp', 'custom_remove_product_meta');

// Menambahkan CSS untuk menyembunyikan tombol Wishlist
function custom_hide_wishlist_button_with_css() {
    ?>
    <style>
        .yith-wcwl-add-to-wishlist {
            display: none !important;
        }
    </style>
    <?php
}
add_action('wp_head', 'custom_hide_wishlist_button_with_css');


// Menyembunyikan input jumlah dengan CSS
function custom_hide_quantity_input() {
    if ( is_product() ) {
        ?>
        <style>
            form.cart .quantity {
                display: none;
            }
        </style>
        <?php
    }
}
add_action( 'wp_head', 'custom_hide_quantity_input' );


// custom menu baru
add_action('admin_menu', 'register_custom_menus');
function register_custom_menus() {
    $icon_url = 'https://evadnebeauty.com/wp-content/uploads/2024/07/cropped-Layer-2.png';
    $icon = 'dashicons-cart';  //from dashicons wordpress
    $position = 6;

    add_menu_page(
        'Link Order',                      // Judul halaman
        'Link Order',                      // Nama menu
        'manage_options',                  // Kemampuan pengguna
        'whatsapp_marketplace_settings',   // Slug menu
        'whatsapp_marketplace_settings_page', // Fungsi tampilan halaman
        $icon,                         // URL gambar logo sebagai ikon menu
        $position                          // Posisi menu
    );
}

add_action('admin_head', 'custom_admin_css');
function custom_admin_css() {
    echo '<style>
        #toplevel_page_whatsapp_marketplace_settings .wp-menu-image img {
            width: 20px;
            height: 20px;
        }
    </style>';
}

function whatsapp_marketplace_settings_page() {
    ?>
    <div class="wrap">
        <h1>Setting Link WhatsApp & Marketplace</h1>
        <blockquote>Create BY <a href="https://tokoweb.co">TokoWeb Team</a></blockquote>
        <form method="post" action="options.php">
            <?php settings_fields('whatsapp_marketplace_group'); ?>
            <?php do_settings_sections('whatsapp_marketplace_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">WhatsApp Order Number 1</th>
                    <td>
                        <?php
                        $whatsapp_number1 = esc_attr(get_option('whatsapp_order_number1'));
                        // Jika nomor tidak kosong, konversi ke format internasional
                        if (!empty($whatsapp_number1)) {
                            // Hapus karakter non-numeric
                            $whatsapp_number1 = preg_replace('/\D/', '', $whatsapp_number1);
                            // Tambahkan awalan +62 jika perlu
                            if (substr($whatsapp_number1, 0, 1) === '0') {
                                $whatsapp_number1 = '62' . substr($whatsapp_number1, 1);
                            } else {
                                $whatsapp_number1 = '62' . $whatsapp_number1;
                            }
                        }
                        ?>
                        <input type="text" name="whatsapp_order_number1" value="<?php echo $whatsapp_number1; ?>" /> <br/>
                        <small style="color: #ED2D56;">Contoh format pengisian nomor Whatsapp : 08xxxxxxxxx</small>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">WhatsApp Order Number 2</th>
                    <td>
                        <?php
                        $whatsapp_number2 = esc_attr(get_option('whatsapp_order_number2'));
                        // Jika nomor tidak kosong, konversi ke format internasional
                        if (!empty($whatsapp_number2)) {
                            // Hapus karakter non-numeric
                            $whatsapp_number2 = preg_replace('/\D/', '', $whatsapp_number2);
                            // Tambahkan awalan +62 jika perlu
                            if (substr($whatsapp_number2, 0, 1) === '0') {
                                $whatsapp_number2 = '62' . substr($whatsapp_number2, 1);
                            } else {
                                $whatsapp_number2 = '62' . $whatsapp_number2;
                            }
                        }
                        ?>
                        <input type="text" name="whatsapp_order_number2" value="<?php echo $whatsapp_number2; ?>" /> <br/>
                        <small style="color: #ED2D56;">Contoh format pengisian nomor Whatsapp : 08xxxxxxxxx</small>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Additional WhatsApp Numbers</th>
                    <td>
                        <div id="whatsapp-numbers-container">
                            <!-- Existing numbers will be injected here -->
                        </div>
                        <button type="button" id="add-whatsapp-number" class="button">Tambah Nomor WhatsApp</button>
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
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.getElementById('whatsapp-numbers-container');
                const addButton = document.getElementById('add-whatsapp-number');
                let counter = 3; // Start from 3 assuming 2 numbers are already there
    
                addButton.addEventListener('click', function() {
                    const newNumber = document.createElement('div');
                    newNumber.innerHTML = `
                        <input type="text" name="whatsapp_order_number${counter}" placeholder="Nomor WhatsApp ${counter}" />
                        <br/><small style="color: #ED2D56;">Contoh format pengisian nomor Whatsapp : 08xxxxxxxxx</small>
                        <br/>
                    `;
                    container.appendChild(newNumber);
                    counter++;
                });
            });
        </script>
    </div>

    <?php
}


add_action('admin_init', 'register_whatsapp_marketplace_settings');
add_action('admin_init', 'register_whatsapp_marketplace_settings');
function register_whatsapp_marketplace_settings() {
    register_setting('whatsapp_marketplace_group', 'whatsapp_order_number1', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'validate_callback' => 'validate_whatsapp_number'
    ));
    register_setting('whatsapp_marketplace_group', 'whatsapp_order_number2', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'validate_callback' => 'validate_whatsapp_number'
    ));
    register_setting('whatsapp_marketplace_group', 'shopee_link');
    register_setting('whatsapp_marketplace_group', 'tiktok_link');
    register_setting('whatsapp_marketplace_group', 'lazada_link');
    register_setting('whatsapp_marketplace_group', 'tokopedia_link');
}

function validate_whatsapp_number($input) {
    // Hanya terima nomor WhatsApp dengan format yang diizinkan
    if (preg_match('/^(?:\+62|62|0)\d{9,12}$/', $input)) {
        return $input;
    } else {
        add_settings_error('whatsapp_order_number', 'invalid_whatsapp_number', 'Nomor WhatsApp tidak valid. Gunakan format yang benar.', 'error');
        return get_option('whatsapp_order_number');
    }
}


// Single product order to
function custom_single_product_buttons() {
    global $product;

    if ( ! $product->is_purchasable() ) {
        return;
    }

    $product_url = $product->get_permalink();
    $product_name = $product->get_name();
    $whatsapp_number1 = esc_attr(get_option('whatsapp_order_number1'));
    $whatsapp_number2 = esc_attr(get_option('whatsapp_order_number2'));
    $message = urlencode('Saya tertarik dengan produk ' . $product_name . ' (' . $product_url . ')');

    // Style untuk tombol WhatsApp
    $whatsapp_style = 'background-color: #25D366; color: #FFFFFF; margin-bottom: 1rem;';

    echo '<div class="single-product-buttons">';
    echo '<div class="whatsapp-buttons">';
    
    // Tombol WhatsApp 1
    if ( ! empty($whatsapp_number1) ) {
        echo '<a href="https://api.whatsapp.com/send?phone=' . $whatsapp_number1 . '&text=' . $message . '" class="button alt full-width" style="' . $whatsapp_style . '"><i class="fab fa-whatsapp fa-lg"></i> Order via WhatsApp (Admin 1)</a>';
    }

    // Tombol WhatsApp 2
    if ( ! empty($whatsapp_number2) ) {
        echo '<a href="https://api.whatsapp.com/send?phone=' . $whatsapp_number2 . '&text=' . $message . '" class="button alt full-width" style="' . $whatsapp_style . '"><i class="fab fa-whatsapp fa-lg"></i> Order via WhatsApp (Admin 2)</a>';
    }

    echo '</div>';

    // Teks dan Tombol Marketplaces
    echo '<div class="marketplace-section">';
    echo "<h5 style='font-family: Poppins;'>Order Via Marketplace Kesayangan Anda :</h5>";

    $marketplaces = array(
        array(
            'name' => 'Shopee',
            'link' => esc_url(get_option('shopee_link')),
            'color' => '#FF957E',
            'icon' => "<img class='marketplace-icon' src='https://evadnebeauty.com/wp-content/uploads/2024/07/Proyek-Baru.webp'/>"
        ),
        array(
            'name' => 'TikTokShop',
            'link' => esc_url(get_option('tiktok_link')),
            'color' => '#000000',
            'icon' => "<img class='marketplace-icon' src='https://evadnebeauty.com/wp-content/uploads/2024/07/tiktok-new.webp'/>",
        ),
        array(
            'name' => 'Lazada',
            'link' => esc_url(get_option('lazada_link')),
            'color' => '#6BA1FF',
            'icon' => "<img class='marketplace-icon' src='https://evadnebeauty.com/wp-content/uploads/2024/07/lazada-new.webp'/>",
        ),
        array(
            'name' => 'Tokopedia',
            'link' => esc_url(get_option('tokopedia_link')),
            'color' => '#7BFFA1',
            'icon' => "<img class='marketplace-icon' src='https://evadnebeauty.com/wp-content/uploads/2024/07/tokopedia-new.webp'/>",
        ),
    );

    echo '<div class="marketplace-buttons">';
    foreach ( $marketplaces as $marketplace ) {
        echo '<a href="' . $marketplace['link'] . '" class="button alt marketplace-button" style="background-color:' . $marketplace['color'] . ';" target="_blank">' . $marketplace['icon'] . '</a>';
    }
    echo '</div>';
    echo '</div>'; // End of .marketplace-section

    echo '</div>'; // End of .single-product-buttons
}




function replace_add_to_cart_button() {
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
    add_action( 'woocommerce_single_product_summary', 'custom_single_product_buttons', 30 );
}
add_action( 'woocommerce_init', 'replace_add_to_cart_button' );


// Custom menu for evadne access
add_action('admin_menu', 'remove_elementor_submissions_submenu', 999);
function remove_elementor_submissions_submenu() {
    remove_submenu_page('elementor', 'e-form-submissions');
}

add_action('admin_head', 'custom_admin_css_hide_submenu');
function custom_admin_css_hide_submenu() {
    echo '<style>
        #toplevel_page_elementor ul.wp-submenu li:nth-child(6) {
            display: none;
        }
    </style>';
}

add_filter('gettext', 'change_submissions_title', 20, 3);
function change_submissions_title($translated_text, $text, $domain) {
    global $pagenow;
    
    if ($pagenow == 'admin.php' && $_GET['page'] == 'e-form-submissions') {
        if ($translated_text == 'Submissions') {
            $translated_text = 'Evadne Access';
        }
    }
    return $translated_text;
}

add_action('admin_menu', 'add_evadne_access');
function add_evadne_access() {
    $icon_url = 'https://evadnebeauty.com/wp-content/uploads/2024/07/cropped-Layer-2.png';
    add_menu_page(
        'Evadne Access',
        'Evadne Access',
        'manage_options',
        'e-form-submissions',
        '', 
        'dashicons-megaphone',
        6
    );
}

function add_whatsapp_floating_button_script() {
    ?>
    <script>
    function toggleWhatsappMenu() {
        var menu = document.getElementById('whatsapp-menu');
        var spinner = document.getElementById('spinner');
        
        // Menampilkan spinner
        spinner.style.display = 'block';
    
        // Mengatur waktu delay untuk menghilangkan spinner dan menampilkan menu
        setTimeout(function() {
            spinner.style.display = 'none';
            menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'flex' : 'none';
        }, 1000); // Ubah waktu delay sesuai kebutuhan
    }

    function closeWhatsappMenu(event) {
        var menu = document.getElementById('whatsapp-menu');
        var button = document.getElementById('whatsapp-button');
        if (event.target !== button && !button.contains(event.target) && !menu.contains(event.target)) {
            menu.style.display = 'none';
        }
    }
    
    function selectWhatsApp(linkId) {
        var message = document.getElementById('whatsapp-message').value;
        var links = {
            'link1': "<?php echo esc_attr(get_option('whatsapp_order_number1')); ?>",
            'link2': "<?php echo esc_attr(get_option('whatsapp_order_number2')); ?>"
        };
        var number = links[linkId];
        var formattedNumber = convertToInternationalFormat(number);
        var whatsappLink = "https://wa.me/" + formattedNumber + "?text=" + encodeURIComponent(message);
        window.open(whatsappLink, '_blank');
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Ambil nomor dari pengaturan
        var whatsappNumber1 = "<?php echo esc_attr(get_option('whatsapp_order_number1')); ?>";
        var whatsappNumber2 = "<?php echo esc_attr(get_option('whatsapp_order_number2')); ?>";

        // Fungsi untuk mengonversi nomor ke format internasional
        function convertToInternationalFormat(number) {
            number = number.replace(/\D/g, ''); // Hapus semua karakter non-numeric
            if (number.startsWith('0')) {
                number = '62' + number.substring(1); // Tambahkan awalan +62 jika nomor dimulai dengan 0
            } else if (!number.startsWith('62')) {
                number = '62' + number; // Tambahkan awalan +62 jika nomor tidak diawali dengan 62
            }
            return number;
        }
        
        function sendMessage() {
            var selected = document.querySelector('#whatsapp-options button.active');
            if (selected) {
                var linkId = selected.getAttribute('onclick').match(/'(.*?)'/)[1];
                selectWhatsApp(linkId);
            } else {
                alert("Please select an admin to chat with.");
            }
        }

        // Konversi nomor ke format internasional
        var formattedNumber1 = convertToInternationalFormat(whatsappNumber1);
        var formattedNumber2 = convertToInternationalFormat(whatsappNumber2);
        
        // Ubah nomor ke format URL WhatsApp
        var message = encodeURIComponent("Halo Evadne Beauty, saya ingin menanyakan tentang produk Evadne Beauty");
        var whatsappLink1 = "https://wa.me/" + formattedNumber1 + "?text=" + message;
        var whatsappLink2 = "https://wa.me/" + formattedNumber2 + "?text=" + message;
        
        // Atur tautan pada menu
        document.getElementById('whatsapp-link1').href = whatsappLink1;
        document.getElementById('whatsapp-link2').href = whatsappLink2;
        
        // Tambahkan event listener untuk klik di luar tombol
        document.addEventListener('click', function(event) {
            var button = document.getElementById('whatsapp-button');
            var menu = document.getElementById('whatsapp-menu');
        
            if (!button.contains(event.target) && !menu.contains(event.target)) {
                menu.style.display = 'none';
            }
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_whatsapp_floating_button_script');

function whatsapp_widget_shortcode() {
    ob_start();
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .contact-list {
            list-style: none;
            margin-top: -1.5rem;
            padding: 0;
            color: #fff;
        }

        .contact-item {
            display: flex;
            align-items: center;
            background: transparent;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }

        .contact-item i {
            font-size: 24px;
            margin-right: 10px;
        }

        .contact-item a {
            text-decoration: none;
            color: #fff;
            font-size: 16px;
        }

        .contact-item a:hover {
            text-decoration: underline;
            color: #bbbbbb;
        }
    </style>
    <ul class="contact-list">
        <li class="contact-item">
            <i class="fab fa-whatsapp" style="color: #25D366;"></i>
            <?php
            // Ambil nomor dari pengaturan
            $whatsapp_number1 = esc_attr(get_option('whatsapp_order_number1'));
            
            // Fungsi untuk mengonversi nomor ke format internasional
            function convert_to_international_format($number) {
                $number = preg_replace('/\D/', '', $number); // Hapus semua karakter non-numeric
                if (substr($number, 0, 1) === '0') {
                    $number = '62' . substr($number, 1); // Tambahkan awalan +62 jika nomor dimulai dengan 0
                } elseif (!preg_match('/^62/', $number)) {
                    $number = '62' . $number; // Tambahkan awalan +62 jika nomor tidak diawali dengan 62
                }
                return $number;
            }
            
            $formatted_number1 = convert_to_international_format($whatsapp_number1);
            $message = urlencode("Halo Evadne Beauty, saya ingin menanyakan tentang produk Evadne Beauty");
            $whatsapp_link1 = "https://wa.me/" . $formatted_number1 . "?text=" . $message;
            ?>
            <a href="<?php echo $whatsapp_link1; ?>" target="_blank">
                <?php echo substr($formatted_number1, 0, 4) . " " . substr($formatted_number1, 4, 4) . " " . substr($formatted_number1, 8); ?>
            </a>
        </li>

        <li class="contact-item">
            <i class="fab fa-whatsapp" style="color: #25D366;"></i>
            <?php
            // Ambil nomor dari pengaturan
            $whatsapp_number2 = esc_attr(get_option('whatsapp_order_number2'));
            $formatted_number2 = convert_to_international_format($whatsapp_number2);
            $whatsapp_link2 = "https://wa.me/" . $formatted_number2 . "?text=" . $message;
            ?>
            <a href="<?php echo $whatsapp_link2; ?>" target="_blank">
                <?php echo substr($formatted_number2, 0, 4) . " " . substr($formatted_number2, 4, 4) . " " . substr($formatted_number2, 8); ?>
            </a>
        </li>
        <li class="contact-item">
            <i class="fas fa-envelope" style="color: #D44638;"></i>
            <a href="mailto:admin_evadnebeauty@evadnebeauty.com">admin_evadnebeauty@evadnebeauty.com</a>
        </li>
    </ul>
    <?php
    return ob_get_clean();
}
add_shortcode('whatsapp_widget', 'whatsapp_widget_shortcode');

// Alert for form submission popup
function add_custom_script() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('popupFormSubscribe');

        if (form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault(); 
                const formData = new FormData(form);

                fetch(form.action, {
                    method: form.method,
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log(data); 

                    if (data.success) { 
                        Swal.fire({
                            title: 'Success!',
                            text: 'Your form has been submitted successfully.',
                            icon: 'success',
                            confirmButtonText: 'Okay'
                        }).then(() => {
                            // Hapus popup setelah menutup SweetAlert
                            const popup = document.querySelector('.elementor-popup-modal');
                            if (popup) {
                                popup.style.display = 'none';
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Oops!',
                            text: 'There was a problem with your submission.',
                            icon: 'error',
                            confirmButtonText: 'Try Again'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Something went wrong. Please try again later.',
                        icon: 'error',
                        confirmButtonText: 'Okay'
                    });
                });
            });
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_custom_script');


// Tambahkan form pencarian produk di bawah konten produk tunggal
function add_search_form_to_single_product() {
    if (is_product()) { // Pastikan kita hanya menambahkan form di halaman produk tunggal
        ?>
        <div id="product-search-container">
            <form action="<?php echo esc_url(home_url('/')); ?>" method="get">
                <input type="hidden" name="post_type" value="product">
                <input type="text" name="s" placeholder="Cari produk..." required>
                <button type="submit">
                    <i class="fas fa-search"></i> Cari
                </button>
            </form>
        </div>
        <style>
            #product-search-container {
                margin-top: 20px; /* Jarak atas dari elemen lain */
                padding: 10px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }

            #product-search-container input[type="text"] {
                width: calc(100% - 20px); /* Mengurangi lebar input untuk menghindari overflow */
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 5px;
            }

            #product-search-container button {
                background-color: #ffffff; /* Warna latar belakang tombol submit */
                color: #000000; /* Warna ikon tombol submit */
                border: 1px solid #ddd;
                cursor: pointer;
                padding: 8px;
                border-radius: 4px;
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #product-search-container button i {
                margin-right: 5px; /* Jarak antara ikon dan teks */
            }
        </style>
        <?php
    }
}
add_action('woocommerce_after_single_product', 'add_search_form_to_single_product', 20);

// Tambahkan form pencarian produk ke halaman arsip produk
// Tambahkan form pencarian produk ke halaman arsip produk
// Tambahkan form pencarian produk ke halaman arsip produk
function add_search_form_above_results() {
    if (is_post_type_archive('product')) { // Pastikan kita hanya menambahkan form di halaman arsip produk
        ?>
        <div id="product-search-container" style="margin-bottom: 20px;">
            <button id="toggle-search-form">
                <i class="fas fa-search"></i> Cari
            </button>
            <div id="search-form-container" style="display: none;">
                <form action="<?php echo esc_url(home_url('/')); ?>" method="get" style="display: flex; align-items: center;">
                    <input type="hidden" name="post_type" value="product" />
                    <input type="text" name="s" placeholder="Cari produk..." required style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-right: 5px;">
                    <button type="submit" style="background-color: #ffffff; color: #000000; border: 1px solid #ddd; cursor: pointer; padding: 8px; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                </form>
            </div>
        </div>
        <style>
            #toggle-search-form {
                left: 20px; /* Jarak dari kiri layar */
                top: 20px; /* Jarak dari atas layar */
                background-color: #ED2D56; /* Warna latar belakang */
                color: white; /* Warna teks */
                border: none; /* Menghilangkan border default */
                padding: 10px 20px; /* Spacing di dalam tombol */
                border-radius: 5px; /* Membuat sudut tombol membulat */
                display: flex;
                align-items: center;
                gap: 8px; /* Jarak antara ikon dan teks */
                font-size: 16px; /* Ukuran font */
                cursor: pointer; /* Pointer saat hover */
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Efek bayangan */
                transition: background-color 0.3s, transform 0.3s; /* Transisi smooth */
            }
            
            #toggle-search-form:hover {
                background-color: #d13a5b; /* Warna latar belakang saat hover */
                transform: scale(1.05); /* Efek zoom saat hover */
            }
            
            #toggle-search-form i {
                font-size: 18px; /* Ukuran ikon */
            }
            #product-search-container {
                padding: 10px;
                border-radius: 8px;
                text-align: center; /* Memusatkan form secara horizontal */
                margin-bottom: 10px; /* Mengurangi jarak dengan elemen berikutnya */
            }

            #search-form-container {
                margin-top: 10px;
                padding: 10px;
                border-radius: 8px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                display: flex;
                flex-direction: column;
                align-items: center;
                width: 100%; /* Agar form pencarian menyesuaikan lebar container */
            }

            #search-form-container form {
                display: flex;
                align-items: center;
                width: 100%; /* Menyediakan lebar penuh untuk form */
            }

            #search-form-container input[type="text"] {
                flex: 1; /* Membuat input menyesuaikan lebar yang tersedia */
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-right: 5px; /* Jarak antara input dan tombol */
            }

            #search-form-container button {
                background-color: #ffffff; /* Warna latar belakang tombol submit */
                color: #000000; /* Warna ikon tombol submit */
                border: 1px solid #ddd;
                cursor: pointer;
                padding: 8px;
                border-radius: 4px;
                display: flex;
                align-items: center; /* Posisi vertikal tombol */
                justify-content: center; /* Posisi horizontal tombol */
            }

            #search-form-container button i {
                margin-right: 5px; /* Jarak antara ikon dan teks */
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var toggleButton = document.getElementById('toggle-search-form');
                var searchFormContainer = document.getElementById('search-form-container');

                toggleButton.addEventListener('click', function(event) {
                    event.preventDefault(); // Mencegah aksi default tombol jika ada
                    // Toggle display dari form pencarian
                    if (searchFormContainer.style.display === 'none') {
                        searchFormContainer.style.display = 'flex'; // Mengubah dari 'none' ke 'flex'
                    } else {
                        searchFormContainer.style.display = 'none';
                    }
                });

                // Menyembunyikan form pencarian ketika klik di luar
                document.addEventListener('click', function(event) {
                    if (!toggleButton.contains(event.target) && !searchFormContainer.contains(event.target)) {
                        searchFormContainer.style.display = 'none';
                    }
                });
            });
        </script>
        <?php
    }
}
add_action('woocommerce_before_shop_loop', 'add_search_form_above_results', 10);



