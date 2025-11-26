<?php

/**
 * 🔓 Tutor LMS Free Auto-Enroll & Unlock First Topic Lesson
 */

if (!defined('ABSPATH')) exit;

add_action('woocommerce_order_status_completed', function ($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_user_id();
    if (!$user_id) return;

    $user = get_userdata($user_id);
    if (!$user || !in_array('tutor_student', (array) $user->roles)) {
        error_log("[TPT] User $user_id bukan tutor_student, skip enroll/unlock");
        return;
    }

    global $wpdb;
    $table_enrolled = $wpdb->prefix . 'tutor_enrolled';
    $table_price = $wpdb->prefix . 'tutor_topic_price';

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $amount_paid = floatval($item->get_total());
        $course_id = get_post_meta($product_id, '_tutor_course_id', true);

        if (!$course_id) {
            $post_type = get_post_type($product_id);
            if ($post_type === 'courses') $course_id = $product_id;
        }

        if (!$course_id) continue;

        // Enroll user manual ke DB Tutor LMS Free
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_enrolled WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));

        if (!$exists) {
            $wpdb->insert($table_enrolled, [
                'user_id'     => $user_id,
                'course_id'   => $course_id,
                'enrolled_on' => current_time('mysql')
            ]);

            // Update meta cache
            $enrolled_courses = get_user_meta($user_id, 'tutor_enrolled_courses', true);
            if (!is_array($enrolled_courses)) $enrolled_courses = [];
            if (!in_array($course_id, $enrolled_courses)) {
                $enrolled_courses[] = $course_id;
                update_user_meta($user_id, 'tutor_enrolled_courses', $enrolled_courses);
            }

            update_user_meta($user_id, 'tutor_total_enroll', count($enrolled_courses));
            error_log("[TPT] User $user_id manual di-enroll ke course $course_id");
        }

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

        // Ambil harga topic
        $topic_price = floatval(get_post_meta($topic_1->ID, '_tpt_price', true));
        if (!$topic_price && $table_price) {
            $topic_price = floatval($wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $table_price WHERE course_id = %d AND topic_title = %s",
                $course_id,
                $topic_1->post_title
            )));
        }

        // Unlock lesson pertama jika bayar sesuai harga
        if (abs($amount_paid - $topic_price) < 1) {
            $lessons = $wpdb->get_results($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'lesson'
                  AND post_parent = %d
                  AND post_status = 'publish'
            ", $topic_1->ID));

            foreach ($lessons as $lesson) {
                if (function_exists('tutor_utils')) {
                    tutor_utils()->mark_lesson_complete($lesson->ID, $user_id);
                    error_log("[TPT] Lesson {$lesson->ID} unlocked untuk user $user_id");
                }
            }
        }
    }
});
