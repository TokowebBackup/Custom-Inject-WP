<?php
/*
Plugin Name: Tutor Paid Topic Addon V2
Description: Inject harga per topic langsung di Course Builder Tutor LMS 3.x+ (React SPA) dan otomatis buat WooCommerce Product
Version: 2.3
Author: Puji Ermanto
*/

if (!defined('ABSPATH')) exit;

// Load frontend module
if (file_exists(plugin_dir_path(__FILE__) . 'module/frontend.php')) {
    require_once plugin_dir_path(__FILE__) . 'module/frontend.php';
}

// Load role & registration service
// foreach (glob(plugin_dir_path(__FILE__) . 'service/*.php') as $service_file) {
//     require_once $service_file;
// }
// =========================================================
// Load service files setelah semua plugin lain siap
// =========================================================
add_action('plugins_loaded', function () {
    $service_path = plugin_dir_path(__FILE__) . 'service/*.php';
    foreach (glob($service_path) as $service_file) {
        require_once $service_file;
    }
}, 20);

// Hanya load manual kalau ingin pastikan ini selalu terakhir
$unlock_file = plugin_dir_path(__FILE__) . 'service/tutor-enroll-unlock.php';
if (file_exists($unlock_file)) {
    require_once $unlock_file;
}

/**
 * 🔁 Patch: Auto Sync Enrolled Courses Cache (ganti template_redirect lama)
 */
// add_action('template_redirect', function () {
//     if (!is_page(['dashboard', 'enrolled-courses'])) return;

//     $user_id = get_current_user_id();
//     if (!$user_id) return;

//     global $wpdb;
//     $table = "{$wpdb->prefix}tutor_enrolled";

//     // Ambil semua course_id yang user sudah enroll
//     $enrolled_course_ids = $wpdb->get_col($wpdb->prepare("
//         SELECT course_id FROM $table WHERE user_id = %d
//     ", $user_id));

//     if (!$enrolled_course_ids) return;

//     // Update tutor_total_enroll
//     update_user_meta($user_id, 'tutor_total_enroll', count($enrolled_course_ids));

//     // Update tutor_enrolled_courses
//     update_user_meta($user_id, 'tutor_enrolled_courses', $enrolled_course_ids);

//     // Update tutor_enrolled_courses_cache
//     $cache_key = 'tutor_enrolled_courses_cache';
//     $cached = [];
//     foreach ($enrolled_course_ids as $course_id) {
//         $cached[$course_id] = [
//             'course_id' => $course_id,
//             'enrolled_date' => current_time('mysql')
//         ];
//     }
//     update_user_meta($user_id, $cache_key, $cached);

//     // Pastikan user role tutor_student
//     $user = get_userdata($user_id);
//     if ($user && !in_array('tutor_student', (array) $user->roles)) {
//         $user->set_role('tutor_student');
//     }

//     error_log("[TPT-FIX] ✅ Auto-sync Tutor enrolled cache untuk user $user_id");
// }, 20);

/**
 * 🎨 Load Cinematic Style for Tutor LMS Lessons
 */
add_action('wp_enqueue_scripts', function () {
    // hanya load di halaman lesson Tutor
    if (is_singular('lesson')) {
        wp_enqueue_style(
            'tpt-cinematic-style',
            plugin_dir_url(__FILE__) . 'assets/style.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/style.css') // versi dinamis anti-cache
        );
    }
});

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
 * 🎬 Load Cinematic Style + YouTube Player Fix
 */
add_action('wp_enqueue_scripts', function () {
    if (is_singular('lesson')) {
        // CSS cinematic
        wp_enqueue_style(
            'tpt-cinematic-style',
            plugin_dir_url(__FILE__) . 'assets/style.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/style.css')
        );

        // JS player auto-convert
        wp_enqueue_script(
            'tpt-player-fix',
            plugin_dir_url(__FILE__) . 'assets/player.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/player.js'),
            true
        );
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
    // $wc_id = get_post_meta($topic_post_id, '_tpt_wc_id', true);

    // $course_title = get_the_title($course_id);
    // $topic_title  = get_the_title($topic_post_id);
    // $product_name = $course_title . ' – ' . $topic_title;

    // if (!$wc_id) {
    //     $product = new WC_Product_Simple();
    //     $product->set_name($product_name);
    //     $product->set_regular_price($price);
    //     $product->set_status('publish'); // publish agar muncul
    //     $product->set_catalog_visibility('hidden');
    //     $product->save();

    //     update_post_meta($topic_post_id, '_tpt_wc_id', $product->get_id());
    //     update_post_meta($product->get_id(), '_tpt_topic_id', $topic_post_id);
    //     update_post_meta($product->get_id(), '_tutor_course_id', $course_id); // 🔹 tambahkan juga course ID

    //     $wc_id = $product->get_id();
    // } else {
    //     $product = wc_get_product($wc_id);
    //     if ($product) {
    //         $product->set_name($product_name);
    //         $product->set_regular_price($price);
    //         $product->set_status('publish');
    //         $product->save();
    //     }
    // }
    // buat / update produk WooCommerce
    $wc_id = get_post_meta($topic_post_id, '_tpt_wc_id', true);

    $course_title = get_the_title($course_id);
    $topic_title  = get_the_title($topic_post_id);
    $product_name = $course_title . ' – ' . $topic_title;

    if (!$wc_id) {
        $product = new WC_Product_Simple();
        $product->set_name($product_name);
        $product->set_regular_price($price);
        $product->set_status('publish'); // publish agar muncul
        $product->set_catalog_visibility('hidden');
        $product->save();

        update_post_meta($topic_post_id, '_tpt_wc_id', $product->get_id());
        update_post_meta($product->get_id(), '_tpt_topic_id', $topic_post_id);
        update_post_meta($product->get_id(), '_tutor_course_id', $course_id); // 🔹 tambahkan juga course ID

        $wc_id = $product->get_id();
    } else {
        $product = wc_get_product($wc_id);
        if ($product) {
            $product->set_name($product_name);
            $product->set_regular_price($price);
            $product->set_status('publish');
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
// Hook: Save price & WC product
// =====================
add_action('save_post', function ($post_id, $post, $update) {
    if ($post->post_type !== 'tutor_lesson') return;

    $course_id = get_post_meta($post_id, '_tutor_course_id', true);
    if (!$course_id) return;

    $price = isset($_POST['_tpt_price']) ? floatval($_POST['_tpt_price']) : 0;
    update_post_meta($post_id, '_tpt_price', $price);

    // Patch: buat / update WC product unik per course
    tpt_create_or_update_wc_product($post_id, $price, $course_id);
}, 10, 3);

// =====================
// Function: Create / update WooCommerce product per topic
// =====================
// function tpt_create_or_update_wc_product($topic_id, $price, $course_id)
// {
//     if (!$topic_id || !$course_id) return false;

//     $topic_title  = get_the_title($topic_id);
//     $course_title = get_the_title($course_id);

//     $product_name = $course_title . ' – ' . $topic_title;

//     $wc_id = get_post_meta($topic_id, '_tpt_wc_id', true);

//     if (!$wc_id) {
//         $product = new WC_Product_Simple();
//         $product->set_name($product_name);
//         $product->set_regular_price($price);
//         $product->set_status('publish');
//         $product->set_catalog_visibility('hidden');
//         $product->save();

//         update_post_meta($topic_id, '_tpt_wc_id', $product->get_id());
//         update_post_meta($product->get_id(), '_tpt_topic_id', $topic_id);

//         $wc_id = $product->get_id();
//     } else {
//         $product = wc_get_product($wc_id);
//         if ($product) {
//             $product->set_name($product_name);
//             $product->set_regular_price($price);
//             $product->set_status('publish');
//             $product->save();
//         }
//     }

//     return $wc_id;
// }
// Versi patch :
function tpt_create_or_update_wc_product($topic_id, $price, $course_id)
{
    if (!$topic_id || !$course_id) return false;

    $topic_title  = get_the_title($topic_id);
    $course_title = get_the_title($course_id);

    $product_name = $course_title . ' – ' . $topic_title;

    $wc_id = get_post_meta($topic_id, '_tpt_wc_id', true);

    if (!$wc_id) {
        // 🔹 Buat produk baru
        $product = new WC_Product_Simple();
        $product->set_name($product_name);
        $product->set_regular_price($price);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->save();

        // 🔹 Simpan relasi meta
        update_post_meta($topic_id, '_tpt_wc_id', $product->get_id());
        update_post_meta($product->get_id(), '_tpt_topic_id', $topic_id);
        update_post_meta($product->get_id(), '_tutor_course_id', $course_id); // ✅ Tambahkan baris ini

        $wc_id = $product->get_id();
    } else {
        // 🔹 Update produk lama
        $product = wc_get_product($wc_id);
        if ($product) {
            $product->set_name($product_name);
            $product->set_regular_price($price);
            $product->set_status('publish');
            $product->save();

            // ✅ Pastikan meta selalu sinkron
            update_post_meta($product->get_id(), '_tutor_course_id', $course_id);
            update_post_meta($product->get_id(), '_tpt_topic_id', $topic_id);
        }
    }

    return $wc_id;
}


// =====================
// REST API endpoint: Buy topic
// =====================
add_action('rest_api_init', function () {
    register_rest_route('tutor-paid-topic/v2', '/buy', [
        'methods' => 'POST',
        'callback' => function ($request) {
            $topic_id  = $request->get_param('topic_id');
            $course_id = $request->get_param('course_id');
            $user_id   = get_current_user_id();

            if (!$topic_id || !$course_id || !$user_id) {
                return ['status' => 'error', 'message' => 'Missing parameter'];
            }

            $wc_id = get_post_meta($topic_id, '_tpt_wc_id', true);
            if (!$wc_id) return ['status' => 'error', 'message' => 'WC Product not found'];

            // Tambahkan ke cart
            WC()->cart->add_to_cart($wc_id);

            return ['status' => 'success', 'message' => 'Topic added to cart'];
        },
        'permission_callback' => '__return_true', // <=== ini tambahan
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('tpt/v1', '/user-progress', [
        'methods' => 'GET',
        'callback' => function (WP_REST_Request $r) {
            $user_id = intval($r->get_param('user_id')) ?: get_current_user_id();
            return [
                'completed' => get_user_meta($user_id, '_tpt_completed_topics', true),
                'purchased' => get_user_meta($user_id, '_tpt_purchased_topics', true),
            ];
        },
        'permission_callback' => '__return_true'
    ]);
});


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

            // Ambil semua topic dengan post_type = 'topics' (fix untuk DB kamu)
            $topics = $wpdb->get_results($wpdb->prepare("
                SELECT ID, post_title
                FROM {$wpdb->posts}
                WHERE post_parent = %d
                AND post_type = 'topics'
                AND post_status = 'publish'
                ORDER BY menu_order ASC
            ", $course_id));

            if (!$topics) return new WP_REST_Response([
                'status_code' => 404,
                'message'     => 'No topics found for this course',
            ], 404);

            $data = [];
            $prices = [];
            foreach ($topics as $topic) {
                $price = intval(get_post_meta($topic->ID, '_tpt_price', true) ?: 0);
                $data[] = [
                    'id'    => intval($topic->ID),
                    'title' => $topic->post_title,
                    'price' => $price
                ];
                if ($price > 0) $prices[] = $price;
            }

            return new WP_REST_Response([
                'status_code' => 200,
                'message'     => 'Course topics fetched successfully',
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

    // Route tpt-card/v2/get-course-prices
    register_rest_route('tpt-card/v2', '/get-course-prices', [
        'methods' => 'GET',
        'callback' => function ($request) {
            $course_id = $request->get_param('course_id');
            if (!$course_id) return [];

            $topics = tutor_utils()->get_course_topics($course_id);
            $data = [];

            foreach ($topics as $topic) {
                $price = get_post_meta($topic->ID, '_tpt_price', true);
                $data[] = [
                    'topic_id' => $topic->ID,
                    'title' => get_the_title($topic->ID),
                    'price' => $price ? $price : 0
                ];
            }

            return $data;
        },
        'permission_callback' => '__return_true', // <=== ini tambahan
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


/**
 * 🛠 PATCH: Auto Sync Tutor LMS enrolled courses cache
 * Bisa dipanggil via URL / cron / template_redirect
 */
add_action('init', function () {
    global $wpdb;
    $table_enroll = $wpdb->prefix . 'tutor_enrolled';

    $user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM $table_enroll");
    if (!$user_ids) return;

    foreach ($user_ids as $user_id) {
        $enrolled_course_ids = $wpdb->get_col($wpdb->prepare("
            SELECT course_id FROM $table_enroll WHERE user_id = %d
        ", $user_id)) ?: [];

        update_user_meta($user_id, 'tutor_total_enroll', count($enrolled_course_ids));
        update_user_meta($user_id, 'tutor_enrolled_courses', $enrolled_course_ids);

        $cached = [];
        foreach ($enrolled_course_ids as $course_id) {
            $cached[$course_id] = [
                'course_id' => $course_id,
                'enrolled_date' => current_time('mysql')
            ];
        }
        update_user_meta($user_id, 'tutor_enrolled_courses_cache', $cached);

        $user = get_userdata($user_id);
        if ($user && !in_array('tutor_student', (array)$user->roles)) {
            $user->set_role('tutor_student');
        }

        error_log("[TPT-PATCH] ✅ Sync enroll cache untuk user $user_id selesai, courses: " . implode(',', $enrolled_course_ids));
    }
});

/**
 * 🧩 PATCH: Sinkronisasi otomatis ketika status order berubah
 * - Tambah topic ke _tpt_purchased_topics saat completed
 * - Hapus topic dari _tpt_purchased_topics kalau order dibatalkan / on-hold
 */
add_action('woocommerce_order_status_changed', function ($order_id, $old_status, $new_status) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    $user_id = $order->get_user_id();
    if (!$user_id) return;

    global $wpdb;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        if (!$product_id) continue;

        $topic_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_tpt_wc_id' AND meta_value = %d
        ", $product_id));

        if (!$topic_id) continue;

        // Ambil meta lama
        $purchased = get_user_meta($user_id, '_tpt_purchased_topics', true);
        if (!is_array($purchased)) $purchased = [];

        if ($new_status === 'completed') {
            // ✅ Tambah topic ke daftar purchased
            if (!in_array($topic_id, $purchased)) {
                $purchased[] = $topic_id;
                update_user_meta($user_id, '_tpt_purchased_topics', array_unique($purchased));
                error_log("[TPT] ✅ Topic $topic_id DITAMBAHKAN ke _tpt_purchased_topics (order completed)");
            }
        } elseif (in_array($new_status, ['on-hold', 'pending', 'cancelled', 'refunded'])) {
            // ❌ Hapus topic dari daftar purchased
            $new_purchased = array_diff($purchased, [$topic_id]);
            update_user_meta($user_id, '_tpt_purchased_topics', $new_purchased);
            error_log("[TPT] ❌ Topic $topic_id DIHAPUS dari _tpt_purchased_topics (order $new_status)");
        }
    }
}, 10, 3);

/**
 * 🔁 Force refresh _tpt_purchased_topics setiap kali user buka halaman course
 * agar status On Hold -> Completed langsung ter-update tanpa logout.
 */
add_action('template_redirect', function () {
    if (!is_singular(['courses', 'tutor_course', 'lesson'])) return;

    $user_id = get_current_user_id();
    if (!$user_id) return;

    // Ambil semua order WooCommerce user
    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status'      => ['completed'],
        'limit'       => -1,
    ]);

    if (!$orders) return;

    global $wpdb;
    $topics = [];

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            $tid = $wpdb->get_var($wpdb->prepare("
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_tpt_wc_id' AND meta_value = %d
            ", $pid));
            if ($tid) $topics[] = intval($tid);
        }
    }

    if (!empty($topics)) {
        $existing = get_user_meta($user_id, '_tpt_purchased_topics', true);
        if (!is_array($existing)) $existing = [];

        $merged = array_unique(array_merge($existing, $topics));
        update_user_meta($user_id, '_tpt_purchased_topics', $merged);
    }
}, 30);
