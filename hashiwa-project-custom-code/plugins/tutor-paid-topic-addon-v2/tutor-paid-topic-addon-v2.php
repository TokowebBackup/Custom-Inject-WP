<?php
/*
Plugin Name: Tutor Paid Topic Addon V2
Description: Inject harga per topic langsung di Course Builder Tutor LMS 3.x+ (React SPA) dan otomatis buat WooCommerce Product
Version: 2.3
Author: Puji Ermanto
*/

if (!defined('ABSPATH')) exit;

// =====================
// 1️⃣ Load JS admin (fix untuk Tutor React Builder)
// =====================
add_action('admin_enqueue_scripts', function ($hook) {
    $is_tutor_builder = isset($_GET['page']) && (
        $_GET['page'] === 'tutor-course-builder' ||
        $_GET['page'] === 'tutor' ||
        strpos($hook, 'tutor') !== false
    );

    if ($is_tutor_builder) {
        wp_enqueue_script(
            'tpt-admin-js',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery'],
            time(), // agar tidak kena cache
            true
        );
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'
        );
        wp_localize_script('tpt-admin-js', 'TPT_Ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tpt_nonce')
        ]);
    }
});

/**
 * 2️⃣ AJAX - Simpan harga per topic & buat produk WooCommerce
 */
add_action('wp_ajax_tpt_save_price', function () {
    check_ajax_referer('tpt_nonce', 'nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized');

    $title     = sanitize_text_field($_POST['title']);
    $course_id = intval($_POST['course_id']);
    $price     = intval($_POST['price']);

    if (!$title || !$course_id) wp_send_json_error('Invalid data');

    global $wpdb;
    $posts_table = $wpdb->prefix . 'posts';

    $topic_post_id = null;
    $attempts = 0;

    while (!$topic_post_id && $attempts < 3) {
        // cari topic langsung di course
        $topic_post_id = $wpdb->get_var($wpdb->prepare("
            SELECT ID FROM $posts_table
            WHERE post_title = %s
            AND post_parent = %d
            AND post_type IN ('topics','topic')
            LIMIT 1
        ", $title, $course_id));

        // cari lewat section parent kalau belum ketemu
        if (!$topic_post_id) {
            $topic_post_id = $wpdb->get_var($wpdb->prepare("
                SELECT t1.ID
                FROM {$wpdb->posts} t1
                INNER JOIN {$wpdb->posts} t2 ON t1.post_parent = t2.ID
                WHERE t1.post_title = %s
                AND t2.post_parent = %d
                AND t1.post_type IN ('topics','topic')
                LIMIT 1
            ", $title, $course_id));
        }

        if (!$topic_post_id) {
            usleep(300000);
            $attempts++;
        }
    }

    if (!$topic_post_id) wp_send_json_error('Topic not found in DB');

    update_post_meta($topic_post_id, '_tpt_price', $price);

    // buat / update produk WooCommerce
    $wc_id = get_post_meta($topic_post_id, '_tpt_wc_id', true);
    if (!$wc_id) {
        $title = get_the_title($topic_post_id);
        $product = new WC_Product_Simple();
        $product->set_name($title);
        $product->set_regular_price($price);
        $product->set_status('publish'); // ✅ publish agar muncul di WooCommerce
        $product->set_catalog_visibility('hidden');
        $product->save();

        update_post_meta($topic_post_id, '_tpt_wc_id', $product->get_id());
        update_post_meta($product->get_id(), '_tpt_topic_id', $topic_post_id);
        $wc_id = $product->get_id();
    } else {
        $product = wc_get_product($wc_id);
        if ($product) {
            $product->set_regular_price($price);
            $product->set_status('publish'); // pastikan tetap publish
            $product->save();
        }
    }

    wp_send_json_success([
        'topic_id' => $topic_post_id,
        'price'    => $price,
        'wc_id'    => $wc_id,
    ]);
});


/**
 * 🔧  Fix Tutor LMS "Price is required" error
 *  Inject dummy pricing before Tutor validates input
 */
add_filter('tutor_course_builder_save_data_before_validation', function ($data) {
    // Jika tidak ada field pricing dari React, tambahkan dummy
    if (empty($data['pricing']) || empty($data['pricing']['regular_price'])) {

        // Cari harga topic pertama (kalau ada)
        if (!empty($data['course_id'])) {
            global $wpdb;
            $topics = $wpdb->get_results($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts}
                WHERE post_parent = %d
                AND post_type IN ('topics','topic')
                ORDER BY menu_order ASC LIMIT 1
            ", intval($data['course_id'])));

            if ($topics) {
                $first_topic_id = $topics[0]->ID;
                $first_price = intval(get_post_meta($first_topic_id, '_tpt_price', true));
                if ($first_price > 0) {
                    $data['pricing']['regular_price'] = $first_price;
                } else {
                    $data['pricing']['regular_price'] = 0;
                }
            } else {
                $data['pricing']['regular_price'] = 0;
            }
        } else {
            $data['pricing']['regular_price'] = 0;
        }
    }

    return $data;
}, 10, 1);


// =====================
// 3️⃣ Hook: buat/update WC product setelah course disimpan
// =====================
add_action('tutor_course_after_save', function ($course_id, $request_data) {
    $topics = tutor_utils()->get_course_topics($course_id);

    foreach ($topics as $topic_id) {
        $price = get_post_meta($topic_id, '_tpt_price', true);
        if (!$price) continue;

        $wc_id = get_post_meta($topic_id, '_tpt_wc_id', true);

        if (!$wc_id) {
            $title = get_the_title($topic_id);
            if (!$title) continue;

            $product = new WC_Product_Simple();
            $product->set_name($title);
            $product->set_regular_price($price);
            $product->set_catalog_visibility('hidden');
            $product->save();

            update_post_meta($topic_id, '_tpt_wc_id', $product->get_id());
            update_post_meta($product->get_id(), '_tpt_topic_id', $topic_id);
        } else {
            $product = wc_get_product($wc_id);
            if ($product) $product->set_regular_price($price)->save();
        }
    }
}, 10, 2);

// =====================
// 4️⃣ AJAX get price
// =====================
add_action('wp_ajax_tpt_get_price', function () {
    check_ajax_referer('tpt_nonce', 'nonce');

    $title     = sanitize_text_field($_POST['title']);
    $course_id = intval($_POST['course_id']);

    global $wpdb;
    $posts_table = $wpdb->prefix . 'posts';

    $topic_post_id = $wpdb->get_var($wpdb->prepare("
        SELECT ID FROM $posts_table
        WHERE post_title = %s
        AND post_parent = %d
        AND post_type IN ('topics','topic')
        LIMIT 1
    ", $title, $course_id));

    if (!$topic_post_id) {
        $topic_post_id = $wpdb->get_var($wpdb->prepare("
            SELECT l.ID
            FROM $posts_table l
            INNER JOIN $posts_table t ON t.ID = l.post_parent
            WHERE l.post_title = %s
            AND t.post_parent = %d
            AND l.post_type = 'lesson'
            LIMIT 1
        ", $title, $course_id));
    }

    $price = $topic_post_id ? get_post_meta($topic_post_id, '_tpt_price', true) : 0;
    wp_send_json_success([
        'price'    => intval($price),
        'topic_id' => intval($topic_post_id)
    ]);
});

// =====================
// 5️⃣ AJAX add to cart
// =====================
add_action('wp_ajax_tpt_add_to_cart', 'tpt_add_to_cart');
add_action('wp_ajax_nopriv_tpt_add_to_cart', 'tpt_add_to_cart');
function tpt_add_to_cart()
{
    $product_id = intval($_POST['product_id'] ?? 0);
    if (!$product_id) wp_send_json_error('Invalid product');

    if (WC()->cart->add_to_cart($product_id)) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to add to cart');
    }
}

// =====================
// 6️⃣ Update completed topic saat order completed
// =====================
add_action('woocommerce_order_status_completed', function ($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    foreach ($order->get_items() as $item) {
        $topic_id = get_post_meta($item->get_product_id(), '_tpt_topic_id', true);
        if ($topic_id) {
            $completed = get_user_meta($user_id, '_tpt_completed_topics', true) ?: [];
            if (!in_array($topic_id, $completed)) {
                $completed[] = $topic_id;
                update_user_meta($user_id, '_tpt_completed_topics', $completed);
            }
        }
    }
});

// =====================
// 7️⃣ REST API: get all topic prices
// =====================
add_action('rest_api_init', function () {
    register_rest_route('tpt/v1', '/get-topic-prices', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            $course_id = intval($request->get_param('course_id'));
            if (!$course_id) return new WP_REST_Response([
                'status_code' => 400,
                'message'     => 'Missing or invalid course_id',
            ], 400);

            global $wpdb;

            $topics = $wpdb->get_results($wpdb->prepare("
                SELECT p.ID, p.post_title
                FROM {$wpdb->posts} p
                WHERE p.post_parent IN (
                    SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d
                )
                AND p.post_type IN ('lesson','topic')
                AND p.post_status = 'publish'
                ORDER BY p.menu_order ASC
            ", $course_id));

            if (!$topics) return new WP_REST_Response([
                'status_code' => 404,
                'message'     => 'No topics found for this course',
            ], 404);

            $data = [];
            $prices = [];
            foreach ($topics as $topic) {
                $price = intval(get_post_meta($topic->ID, '_tpt_price', true) ?: 0);
                $data[] = ['id' => intval($topic->ID), 'title' => $topic->post_title, 'price' => $price];
                if ($price > 0) $prices[] = $price;
            }

            return new WP_REST_Response([
                'status_code' => 200,
                'message'     => 'Course contents fetched successfully',
                'data'        => [
                    'course_id' => $course_id,
                    'topics'    => $data,
                    'price_min' => $prices ? min($prices) : 0,
                    'price_max' => $prices ? max($prices) : 0
                ]
            ], 200);
        },
        'permission_callback' => '__return_true'
    ]);
});

// =====================
// 8️⃣ Frontend: inject Buy Topic button dengan loader
// =====================
add_action('wp_footer', function () {
    if (!is_singular('courses')) return;
    $completed = get_user_meta(get_current_user_id(), '_tpt_completed_topics', true) ?: [];
?>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const userCompleted = <?php echo json_encode($completed); ?>;

            document.querySelectorAll('.tutor-accordion-item').forEach(item => {
                const topicId = parseInt(item.dataset.topicId);
                const wcId = parseInt(item.dataset.wcId);
                if (!wcId || !topicId) return;

                const prevTopicId = item.dataset.prevTopicId ? parseInt(item.dataset.prevTopicId) : 0;
                const canBuy = !prevTopicId || userCompleted.includes(prevTopicId);

                const btn = document.createElement('button');
                btn.textContent = canBuy ? "Buy Topic" : "Selesaikan Bab sebelumnya";
                btn.disabled = !canBuy;
                btn.className = "tpt-btn-buy-topic";
                Object.assign(btn.style, {
                    marginTop: "8px",
                    padding: "6px 18px",
                    borderRadius: "6px",
                    border: "none",
                    cursor: canBuy ? "pointer" : "not-allowed",
                    background: canBuy ? "#ED2D56" : "#ccc",
                    color: "#fff",
                    fontWeight: "600"
                });

                if (canBuy) {
                    btn.addEventListener('click', () => {
                        const originalText = btn.textContent;
                        btn.textContent = "Loading...";
                        btn.disabled = true;
                        jQuery.post('<?php echo admin_url("admin-ajax.php"); ?>', {
                            action: "tpt_add_to_cart",
                            product_id: wcId
                        }).done(() => {
                            btn.textContent = "Added!";
                            btn.style.background = "#999";
                        }).fail(() => {
                            btn.textContent = originalText;
                            btn.disabled = false;
                        });
                    });
                }

                item.querySelector('.tutor-course-content-list')?.appendChild(btn);
            });
        });
    </script>
<?php
});
