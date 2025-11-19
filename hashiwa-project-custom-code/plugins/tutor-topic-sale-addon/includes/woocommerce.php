<?php
if (!defined('ABSPATH')) exit;

add_action('woocommerce_order_status_completed', 'ttsa_handle_order_completed', 10, 1);
function ttsa_handle_order_completed($order_id){
    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach ($order->get_items() as $item){
        $product_id = $item->get_product_id();
        $topics = ttsa_get_topics_by_product($product_id);
        foreach ($topics as $topic_id){
            $user_id = $order->get_user_id();
            if ($user_id){
                update_user_meta($user_id, '_ttsa_topic_access_' . $topic_id, '1');
            }
        }
    }
}

function ttsa_get_topics_by_product($product_id){
    global $wpdb;
    $results = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d", '_ttsa_wc_product_id', $product_id));
    return array_map('intval', $results ?: array());
}

// Admin columns
add_filter('manage_edit-tutor_topics_columns', function($cols){
    $cols['ttsa_price'] = __('Price', 'ttsa');
    return $cols;
});
add_action('manage_tutor_topics_posts_custom_column', function($column, $post_id){
    if ($column === 'ttsa_price'){
        $p = get_post_meta($post_id, '_ttsa_topic_price', true);
        $pid = get_post_meta($post_id, '_ttsa_wc_product_id', true);
        echo $p ? wc_price($p) : '-';
        if ($pid) echo '<br/><small>ID:' . esc_html($pid) . '</small>';
    }
}, 10, 2);
