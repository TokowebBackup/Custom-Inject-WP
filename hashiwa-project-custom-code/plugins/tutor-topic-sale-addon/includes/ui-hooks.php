<?php
if (!defined('ABSPATH')) exit;

// Render Buy button in curriculum list items. We attach to a common Tutor hook; if not present, use fallback JS.
add_action('tutor_course/single/after/lesson_title', 'ttsa_render_buy_button', 10, 2);

function ttsa_render_buy_button($topic_id = 0, $args = array()){
    $topic_id = intval($topic_id) ?: (isset($args->ID) ? intval($args->ID) : 0);
    if (!$topic_id) return;

    $price = get_post_meta($topic_id, '_ttsa_topic_price', true);
    $wc_id = get_post_meta($topic_id, '_ttsa_wc_product_id', true);
    if (!$price || !$wc_id) return;

    $user_has = ttsa_user_has_topic_access(get_current_user_id(), $topic_id);
    if ($user_has){
        echo '<div class="ttsa-topic-access granted">' . __('Purchased', 'ttsa') . '</div>';
        return;
    }

    // Use AJAX add-to-cart by default for better UX.
    printf('<a href="#" data-topic-id="%d" data-product-id="%d" class="ttsa-buy-topic button">%s - %s</a>',
        $topic_id, $wc_id, __('Buy this topic', 'ttsa'), wc_price($price));
}

// AJAX handler to add product to cart
add_action('wp_ajax_ttsa_add_to_cart', 'ttsa_ajax_add_to_cart');
add_action('wp_ajax_nopriv_ttsa_add_to_cart', 'ttsa_ajax_add_to_cart');
function ttsa_ajax_add_to_cart(){
    check_ajax_referer('ttsa_buy_topic_nonce', 'nonce');
    $topic_id = intval($_POST['topic_id'] ?? 0);
    $product_id = intval($_POST['product_id'] ?? 0);
    if (!$topic_id || !$product_id){
        wp_send_json_error(array('message' => 'Invalid data'));
    }
    if (!class_exists('WC_Cart')){
        wp_send_json_error(array('message' => 'WooCommerce not available'));
    }
    WC()->cart->add_to_cart($product_id);
    wp_send_json_success(array('redirect' => wc_get_checkout_url()));
}

function ttsa_user_has_topic_access($user_id, $topic_id){
    if (!$user_id) return false;
    return get_user_meta($user_id, '_ttsa_topic_access_' . $topic_id, true) === '1';
}
