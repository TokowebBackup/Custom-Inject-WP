<?php
/*
Plugin Name: Tutor Paid Topic Addon (Rupiah) - Stable v1.6.1
Description: Simpan harga per topic per course di Tutor LMS React Builder. Fix REST + visual badge.
Version: 1.6.2
Author: Puji Ermanto
*/

if (!defined('ABSPATH')) exit;

/* =======================================================
   1️⃣ Buat tabel custom
======================================================= */
register_activation_hook(__FILE__, function () {
    global $wpdb;
    // $table = "{$wpdb->prefix}tutor_topic_price";
    $table = "wpsu_tutor_topic_price"; // hardcode ke tabel yang benar

    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        course_id BIGINT(20) UNSIGNED NOT NULL,
        topic_title VARCHAR(255) NOT NULL,
        price INT(11) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_topic (course_id, topic_title)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

/* =======================================================
   2️⃣ Enqueue JS
======================================================= */
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'tutor') === false) return;

    wp_enqueue_script(
        'tpt-addon-script',
        plugin_dir_url(__FILE__) . 'tutor-paid-topic.js',
        ['jquery'],
        '1.6.2', // ⬅️ versi baru, ubah dari 1.6.1 ke 1.6.2
        true
    );

    wp_localize_script('tpt-addon-script', 'TPT_Ajax', [
        'resturl' => esc_url(rest_url('tutor-paid-topic/v1/')),
        'nonce'   => wp_create_nonce('wp_rest')
    ]);
});


/* =======================================================
   3️⃣ REST API
======================================================= */
add_action('rest_api_init', function () {
    global $wpdb;
    $table = "{$wpdb->prefix}tutor_topic_price";

    register_rest_route('tutor-paid-topic/v1', '/save-price', [
        'methods' => 'POST',
        'callback' => function ($req) {
            global $wpdb;
            $table = "wpsu_tutor_topic_price";
            $data = $req->get_json_params();
            $title = trim(sanitize_text_field($data['title'] ?? ''));
            $price = intval($data['price'] ?? 0);
            $course_id = intval($data['course_id'] ?? 0);

            if (!$title || !$course_id) {
                return new WP_Error('invalid_data', 'Judul atau Course ID kosong.', ['status' => 400]);
            }

            // Simpan harga ke tabel custom
            $wpdb->replace($table, [
                'course_id'   => $course_id,
                'topic_title' => $title,
                'price'       => $price
            ]);

            // ✅ Ambil topic pertama berdasarkan post_type "topics"
            $first_topic_title = $wpdb->get_var($wpdb->prepare(
                "SELECT post_title FROM {$wpdb->prefix}posts 
             WHERE post_type = 'topics' AND post_parent = %d 
             ORDER BY menu_order ASC LIMIT 1",
                $course_id
            ));

            $is_first = false;
            if ($first_topic_title && strtolower(trim($first_topic_title)) === strtolower($title)) {
                $is_first = true;

                // 🧭 Update meta regular price Tutor LMS
                update_post_meta($course_id, '_tutor_course_price_type', 'paid');
                update_post_meta($course_id, '_tutor_course_price', $price);
                update_post_meta($course_id, '_regular_price', $price);
                update_post_meta($course_id, '_sale_price', '');
            }

            error_log("Tutor Paid Topic | Save: {$title} (Rp {$price}) | FirstTopicInTutor={$first_topic_title} | isFirst=" . ($is_first ? 'YES' : 'NO'));

            return [
                'success' => true,
                'message' => "Harga topik '{$title}' disimpan (Rp {$price})",
                'synced'  => $is_first
            ];
        },
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('tutor-paid-topic/v1', '/get-price', [
        'methods' => 'GET',
        'callback' => function ($req) use ($wpdb, $table) {
            $title = sanitize_text_field($req['title'] ?? '');
            $course_id = intval($req['course_id'] ?? 0);

            if (!$title || !$course_id) return ['price' => 0];

            $data = $wpdb->get_row($wpdb->prepare(
                "SELECT price FROM $table WHERE course_id = %d AND topic_title = %s",
                $course_id,
                $title
            ));
            return $data ?: ['price' => 0];
        },
        'permission_callback' => '__return_true'
    ]);
});

/* =======================================================
   4️⃣ Sembunyikan harga bawaan Tutor LMS
======================================================= */
add_action('admin_print_footer_scripts', function () {
?>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const hidePriceFields = () => {
                document.querySelectorAll('.css-1xhi066 [data-cy="form-field-wrapper"]').forEach(wrapper => {
                    const label = wrapper.querySelector('label');
                    if (!label) return;
                    const text = label.textContent.trim();
                    // if (text === 'Sale Price') wrapper.style.display = 'none';
                });
            };
            const obs = new MutationObserver(() => hidePriceFields());
            obs.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    </script>
<?php
});

/* =======================================================
   5️⃣ Batasi akses topic berdasarkan pembayaran
======================================================= */
add_action('template_redirect', function () {
    if (!is_user_logged_in()) return;

    global $post, $wpdb;

    // Pastikan kita ada di halaman lesson
    if (!is_singular('lesson')) return;

    $user_id = get_current_user_id();
    $lesson_id = $post->ID;
    $topic_id  = $post->post_parent;
    $course_id = tutor_utils()->get_course_id_by_lesson($lesson_id);

    if (!$course_id || !$topic_id) return;

    // Ambil judul topik
    $topic_title = get_the_title($topic_id);

    // Cek apakah topik ini berbayar
    $price_row = $wpdb->get_row($wpdb->prepare("
        SELECT price FROM wpsu_tutor_topic_price
        WHERE course_id = %d AND topic_title = %s
    ", $course_id, $topic_title));

    // Jika topik tidak berharga (gratis), lanjut saja
    if (!$price_row || $price_row->price <= 0) return;

    // 🔍 Cek apakah user sudah punya order "completed" untuk course ini
    $order = $wpdb->get_var($wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_course
            ON p.ID = pm_course.post_id
        INNER JOIN {$wpdb->postmeta} pm_user
            ON p.ID = pm_user.post_id
        WHERE p.post_type = 'tutor_order'
          AND p.post_status = 'completed'
          AND pm_course.meta_key = '_tutor_order_course_id'
          AND pm_course.meta_value = %d
          AND pm_user.meta_key = '_tutor_order_user_id'
          AND pm_user.meta_value = %d
        LIMIT 1
    ", $course_id, $user_id));

    // ⚠️ Kalau belum ada order → redirect ke cart
    if (!$order) {
        wp_redirect(add_query_arg([
            'locked_topic' => urlencode($topic_title),
            'course_id'    => $course_id
        ], site_url('/cart-2')));
        exit;
    }
});
