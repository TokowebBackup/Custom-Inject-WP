<?php
/*
Plugin Name: Tutor Paid Topic Addon (Rupiah)
Description: Tambah harga per topic/bab di Tutor LMS React Builder. Otomatis simpan via AJAX (REST API).
Version: 1.5.0
Author: Puji Ermanto
*/

if (!defined('ABSPATH')) exit;

/* =======================================================
   1️⃣  Buat tabel custom untuk menyimpan harga per topik
======================================================= */
register_activation_hook(__FILE__, function() {
    global $wpdb;
    $table = "{$wpdb->prefix}tutor_topic_price";
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        topic_title VARCHAR(255) NOT NULL,
        price INT(11) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY topic_title (topic_title)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

/* =======================================================
   2️⃣  Enqueue JavaScript di halaman Course Builder Tutor LMS
======================================================= */
add_action('admin_enqueue_scripts', function($hook) {
    // Pastikan hanya di page builder Tutor LMS
    if (strpos($hook, 'tutor') === false) return;

    wp_enqueue_script(
        'tpt-addon-script',
        plugin_dir_url(__FILE__) . 'tutor-paid-topic.js',
        ['jquery'],
        '1.5.0',
        true
    );

    wp_localize_script('tpt-addon-script', 'TPT_Ajax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'resturl' => esc_url(rest_url('tutor-paid-topic/v1/')),
        'nonce'   => wp_create_nonce('wp_rest')
    ]);
});

/* =======================================================
   3️⃣  Register REST API endpoint untuk Save & Get harga
======================================================= */
add_action('rest_api_init', function() {
    register_rest_route('tutor-paid-topic/v1', '/save-price', [
        'methods' => 'POST',
        'callback' => function($req) {
            global $wpdb;
            $data = $req->get_json_params();
            $title = sanitize_text_field($data['title'] ?? '');
            $price = intval($data['price'] ?? 0);

            if (!$title) {
                return new WP_Error('no_title', 'Judul topik kosong.', ['status' => 400]);
            }

            $table = "{$wpdb->prefix}tutor_topic_price";
            $wpdb->replace($table, [
                'topic_title' => $title,
                'price' => $price
            ]);

            return ['success' => true, 'message' => 'Harga topik disimpan.'];
        },
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ]);

    register_rest_route('tutor-paid-topic/v1', '/get-price', [
        'methods' => 'GET',
        'callback' => function($req) {
            global $wpdb;
            $title = sanitize_text_field($req['title'] ?? '');
            $table = "{$wpdb->prefix}tutor_topic_price";
            $data = $wpdb->get_row($wpdb->prepare("SELECT price FROM $table WHERE topic_title = %s", $title));
            return $data ?: ['price' => ''];
        },
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ]);
});
