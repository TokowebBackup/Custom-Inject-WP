<?php

/**
 * 🧩 Integrasi Registrasi + Payment (Midtrans via WooCommerce)
 * - Buat akun user + order otomatis + redirect ke checkout
 * - Auto-activate akun setelah pembayaran sukses
 */

// add_action('tutor_after_student_signup', function ($user_id) {

//     // 🔹 Ambil course_id dari session atau URL
//     $course_id = intval($_GET['enrol_course_id'] ?? ($_SESSION['tpt_enrol_course_id'] ?? 0));
//     if (!$course_id) {
//         error_log('[TPT] ❌ enrol_course_id tidak ditemukan di session.');
//         return;
//     }

//     $user = get_userdata($user_id);
//     if (!$user) return;

//     // Logout auto-login Tutor bawaan
//     wp_logout();

//     // Set meta aktivasi
//     update_user_meta($user_id, '_tpt_activated', false);

//     global $wpdb;

//     /**
//      * 🔍 Ambil topic pertama dari course
//      */
//     $first_topic_id = $wpdb->get_var($wpdb->prepare("
//         SELECT ID FROM {$wpdb->posts}
//         WHERE post_parent = %d
//         AND post_type IN ('topics', 'topic')
//         AND post_status = 'publish'
//         ORDER BY menu_order ASC
//         LIMIT 1
//     ", $course_id));

//     if (!$first_topic_id) {
//         error_log("[TPT] ❌ Topic pertama tidak ditemukan untuk course ID $course_id");
//         return;
//     }

//     /**
//      * 🔍 Ambil produk WooCommerce dari topic pertama
//      */
//     $wc_product_id = get_post_meta($first_topic_id, '_tpt_wc_id', true);
//     if (!$wc_product_id) {
//         error_log("[TPT] ❌ Meta _tpt_wc_id tidak ditemukan pada topic ID $first_topic_id");
//         return;
//     }

//     $product = wc_get_product($wc_product_id);
//     if (!$product) {
//         error_log("[TPT] ❌ Produk WooCommerce ID $wc_product_id tidak valid.");
//         return;
//     }

//     /**
//      * 🔹 Buat order WooCommerce baru
//      */
//     $order = wc_create_order(['customer_id' => $user_id]);
//     $order->add_product($product, 1);
//     $order->calculate_totals();
//     $order->save();

//     // Simpan order untuk tracking
//     update_user_meta($user_id, '_tpt_pending_order', $order->get_id());
//     update_user_meta($user_id, '_tpt_first_topic', $first_topic_id);

//     // 🧾 Log aktivitas
//     error_log("[TPT] 🛒 Order dibuat untuk user #$user_id | course_id=$course_id | topic_id=$first_topic_id | product_id=$wc_product_id | order_id=" . $order->get_id());

//     /**
//      * 🔁 Redirect ke checkout
//      */
//     wp_safe_redirect(wc_get_checkout_url() . '?tpt_order=' . $order->get_id());
//     exit;
// }, 999);
