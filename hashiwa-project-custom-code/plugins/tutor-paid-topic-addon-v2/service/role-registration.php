<?php

/**
 * ============================================================
 *  🔐 Tutor Student Role & Registration Handler
 *  Terintegrasi untuk Tutor Paid Topic Addon V2
 * ============================================================
 */

/**
 * Debug helper (aktif hanya jika WP_DEBUG true)
 */
if (!function_exists('tpt_debug_log')) {
    function tpt_debug_log($message, $data = null)
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) return;

        $log = "[TPT-DEBUG] " . $message;
        if ($data !== null) $log .= " => " . print_r($data, true);
        error_log($log);
    }
}

/**
 * Pastikan role tutor_student tersedia
 */
add_action('init', function () {
    if (!get_role('tutor_student')) {
        add_role('tutor_student', 'Tutor Student', [
            'read'         => true,
            'upload_files' => true,
            'level_0'      => true,
        ]);
        tpt_debug_log('Role tutor_student dibuat otomatis.');
    }
});

/**
 * Saat user register via form Tutor LMS
 */
add_action('tutor_after_student_signup', function ($user_id) {
    tpt_debug_log('HOOK tutor_after_student_signup dipanggil', ['user_id' => $user_id]);

    $user = get_userdata($user_id);
    if (!$user) return;

    // set role
    wp_update_user([
        'ID'   => $user_id,
        'role' => 'tutor_student',
    ]);

    // set usermeta wajib
    update_user_meta($user_id, 'tutor_profile_completed', 1);
    update_user_meta($user_id, 'tutor_last_activity', current_time('mysql'));
    update_user_meta($user_id, 'tutor_total_enroll', 0);

    // daftar di sistem Tutor LMS
    if (function_exists('tutor_utils')) {
        tutor_utils()->register_student($user_id);
        tpt_debug_log('Tutor LMS register_student dijalankan');
    } else {
        tpt_debug_log('tutor_utils tidak ditemukan');
    }
});

/**
 * Assign role tutor_student untuk user baru via registrasi umum / WooCommerce
 */
add_action('user_register', function ($user_id) {
    $user = get_userdata($user_id);
    if (empty($user->roles)) {
        $user->set_role('tutor_student');
        tpt_debug_log('Role tutor_student di-assign ke user baru', ['user_id' => $user_id]);
    }
});

add_action('woocommerce_created_customer', function ($customer_id) {
    $user = new WP_User($customer_id);
    if (!$user->has_role('tutor_student')) {
        $user->add_role('tutor_student');
        tpt_debug_log('Role tutor_student di-assign ke WooCommerce user', ['user_id' => $customer_id]);
    }
});

/**
 * 🔓 Unlock otomatis Topic Pertama setelah pembayaran WooCommerce selesai
 */
add_action('woocommerce_order_status_completed', function ($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_user_id();
    if (!$user_id) return;

    tpt_debug_log('🧾 Woo Order Completed', ['order_id' => $order_id, 'user_id' => $user_id]);

    $user = get_userdata($user_id);
    if (!$user || !in_array('tutor_student', (array) $user->roles)) {
        tpt_debug_log('User bukan tutor_student, skip unlock');
        return;
    }

    global $wpdb;
    $table_price = "{$wpdb->prefix}tutor_topic_price";

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $amount_paid = floatval($item->get_total());
        $course_id = get_post_meta($product_id, '_tutor_course_id', true);

        if (!$course_id) {
            $post_type = get_post_type($product_id);
            if ($post_type === 'courses') $course_id = $product_id;
        }

        if (!$course_id) continue;

        // Ambil topic pertama
        $topic_1 = $wpdb->get_row($wpdb->prepare("
            SELECT ID, post_title FROM {$wpdb->posts}
            WHERE post_type = 'topics'
              AND post_parent = %d
              AND post_status = 'publish'
            ORDER BY menu_order ASC, ID ASC
            LIMIT 1
        ", $course_id));

        if (!$topic_1) continue;

        // Ambil harga dari meta / tabel custom
        $topic_price = floatval(get_post_meta($topic_1->ID, '_tpt_price', true));
        if (!$topic_price && $table_price) {
            $topic_price = floatval($wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $table_price WHERE course_id = %d AND topic_title = %s",
                $course_id,
                $topic_1->post_title
            )));
        }

        if (abs($amount_paid - $topic_price) < 1) {
            tpt_debug_log('✅ Unlock semua lesson topic pertama', [
                'course_id' => $course_id,
                'topic_id' => $topic_1->ID
            ]);

            $lessons = $wpdb->get_results($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'lesson'
                  AND post_parent = %d
                  AND post_status = 'publish'
            ", $topic_1->ID));

            foreach ($lessons as $lesson) {
                if (function_exists('tutor_utils')) {
                    tutor_utils()->mark_lesson_complete($lesson->ID, $user_id);
                    tpt_debug_log('📘 Lesson unlocked', ['lesson_id' => $lesson->ID]);
                }
            }
        }
    }
});

/**
 * 🧩 Enroll user ke course secara otomatis setelah order Completed
 */
// add_action('woocommerce_order_status_completed', function ($order_id) {
//     $order = wc_get_order($order_id);
//     if (!$order) return;

//     $user_id = $order->get_user_id();
//     if (!$user_id) return;

//     foreach ($order->get_items() as $item) {
//         $product_id = $item->get_product_id();
//         $course_id = get_post_meta($product_id, '_tutor_course_id', true);

//         // Jika produk terhubung ke course
//         if ($course_id) {
//             // 🔹 Enroll user ke course jika belum
//             if (function_exists('tutor_utils')) {
//                 $is_enrolled = tutor_utils()->is_enrolled($course_id, $user_id);
//                 if (!$is_enrolled) {
//                     tutor_utils()->do_enroll($course_id, $user_id);
//                     error_log("[TPT] User $user_id otomatis di-enroll ke course $course_id dari order #$order_id");
//                 }
//             }
//         }
//     }
// });


/**
 * 🧩 Auto-fix meta product lama yang belum punya _tutor_course_id
 * Jalankan sekali saja — aman untuk dibiarkan aktif.
 */
add_action('admin_init', function () {
    global $wpdb;

    // Ambil semua product yang punya _tpt_topic_id tapi belum punya _tutor_course_id
    $products = $wpdb->get_results("
        SELECT pm1.post_id, pm1.meta_value AS topic_id
        FROM {$wpdb->postmeta} pm1
        LEFT JOIN {$wpdb->postmeta} pm2
            ON pm1.post_id = pm2.post_id AND pm2.meta_key = '_tutor_course_id'
        WHERE pm1.meta_key = '_tpt_topic_id'
          AND (pm2.meta_value IS NULL OR pm2.meta_value = '')
    ");

    if (!$products) return;

    foreach ($products as $p) {
        $course_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_parent FROM {$wpdb->posts}
            WHERE ID = %d
        ", $p->topic_id));

        if ($course_id) {
            update_post_meta($p->post_id, '_tutor_course_id', $course_id);
            error_log("[TPT-FIX] ✅ Set _tutor_course_id={$course_id} untuk product {$p->post_id}");
        } else {
            error_log("[TPT-FIX] ⚠️ Gagal deteksi course untuk topic {$p->topic_id} (product {$p->post_id})");
        }
    }
});

/**
 * 🧩 PATCH: Sinkronisasi enroll manual agar muncul di dashboard Tutor Student
 */
// add_action('init', function () {
//     if (!current_user_can('administrator')) return;

//     global $wpdb;
//     $user_id  = 19;      // ganti sesuai user
//     $course_id = 17012;  // ganti sesuai course

//     // pastikan data enroll ada di DB
//     $enroll_exists = $wpdb->get_var($wpdb->prepare(
//         "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrolled WHERE user_id = %d AND course_id = %d",
//         $user_id,
//         $course_id
//     ));

//     if (!$enroll_exists) return;

//     // 1️⃣ update total enroll count
//     update_user_meta($user_id, 'tutor_total_enroll', (int) get_user_meta($user_id, 'tutor_total_enroll', true) + 1);

//     // 2️⃣ tambah ke daftar enrolled courses cache
//     $enrolled_courses = get_user_meta($user_id, 'tutor_enrolled_courses', true);
//     if (!is_array($enrolled_courses)) $enrolled_courses = [];

//     if (!in_array($course_id, $enrolled_courses)) {
//         $enrolled_courses[] = $course_id;
//         update_user_meta($user_id, 'tutor_enrolled_courses', $enrolled_courses);
//     }

//     // 3️⃣ tambah cache JSON (Tutor 3.x pakai ini juga)
//     $cache_key = 'tutor_enrolled_courses_cache';
//     $cached = get_user_meta($user_id, $cache_key, true);
//     if (empty($cached) || !is_array($cached)) $cached = [];
//     $cached[$course_id] = [
//         'course_id' => $course_id,
//         'enrolled_date' => current_time('mysql')
//     ];
//     update_user_meta($user_id, $cache_key, $cached);

//     // 4️⃣ pastikan user terdaftar sebagai tutor_student
//     $user = get_userdata($user_id);
//     if ($user && !in_array('tutor_student', (array) $user->roles)) {
//         $user->set_role('tutor_student');
//     }

//     error_log("[TPT-FIX] ✅ Sinkronisasi cache Tutor untuk user {$user_id}, course {$course_id}");
// });
/**
 * 🧩 Auto Sync Enrolled Courses Cache untuk Tutor LMS
 */

// add_action('template_redirect', function () {
//     if (!is_page('dashboard') && !is_page('enrolled-courses')) return;

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

//     error_log("[TPT-FIX] ✅ Sinkronisasi otomatis Tutor enrolled cache untuk user $user_id");
// });
