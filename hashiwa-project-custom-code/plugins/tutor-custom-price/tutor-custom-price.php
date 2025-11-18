<?php

/**
 * Plugin Name: Tutor LMS Custom Topic Price Override (Final)
 * Plugin URI:  https://tokoweb.co.id/
 * Description: Override semua harga Tutor LMS berdasarkan tabel custom tutor_topic_price. Harga mengikuti MIN(price) per course_id. Menjamin subtotal/total tersimpan sesuai.
 * Version:     1.2
 * Author:      Puji Ermanto
 * Author URI:  https://pujiermanto-blog.vercel.app
 * License:     GPLv2 or later
 * Text Domain: tutor-topic-price-override
 */

if (!defined('ABSPATH')) exit;

/**
 * Ambil harga topic terkecil
 */
function tpt_get_min_topic_price($course_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'tutor_topic_price';
    return floatval(
        $wpdb->get_var(
            $wpdb->prepare("SELECT MIN(price) FROM $table WHERE course_id=%d", $course_id)
        )
    );
}

/**
 * 🔥 1. Override harga SEBELUM order dibuat
 * Hook resmi Tutor LMS 3.x
 */
add_filter('tutor_ecommerce_before_order_create', function ($order_data) {

    error_log("[TPT] tutor_ecommerce_before_order_create CALLED");

    if (empty($order_data['items'])) {
        error_log("[TPT] NO ITEMS FOUND");
        return $order_data;
    }

    $new_total = 0;

    foreach ($order_data['items'] as $i => $item) {

        $course_id = $item['product_id'];
        $price     = tpt_get_min_topic_price($course_id);

        if (!$price) continue;

        $order_data['items'][$i]['price'] = $price;
        $order_data['items'][$i]['subtotal'] = $price;

        $new_total += $price;

        error_log("[TPT] Updated item #{$course_id} → {$price}");
    }

    $order_data['total']    = $new_total;
    $order_data['subtotal'] = $new_total;

    error_log("[TPT] New total = {$new_total}");

    return $order_data;
}, 10, 1);

/**
 * 🔥 2. Override order items setelah dibuat
 */
add_action('tutor_ecommerce_after_order_create', function ($order_id, $order_data) {

    global $wpdb;

    error_log("[TPT] tutor_ecommerce_after_order_create → ORDER {$order_id}");

    $items = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}tutor_order_items
        WHERE order_id = {$order_id}
    ");

    $new_total = 0;

    foreach ($items as $item) {

        $price = tpt_get_min_topic_price($item->product_id);
        if (!$price) continue;

        $wpdb->update(
            $wpdb->prefix . "tutor_order_items",
            [
                'price'    => $price,
                'subtotal' => $price
            ],
            ['id' => $item->id]
        );

        $new_total += $price;
    }

    // update total di order
    $wpdb->update(
        $wpdb->prefix . "tutor_orders",
        [
            'subtotal' => $new_total,
            'total'    => $new_total
        ],
        ['id' => $order_id]
    );

    var_dump($new_total);
    die;

    error_log("[TPT] Order {$order_id} FINAL TOTAL = {$new_total}");
}, 10, 2);
