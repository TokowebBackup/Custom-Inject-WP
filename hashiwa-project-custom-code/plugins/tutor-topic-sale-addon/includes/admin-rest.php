<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function(){
    register_rest_route('ttsa/v1', '/save_topic_price', array(
        'methods' => 'POST',
        'callback' => 'ttsa_rest_save_topic_price',
        'permission_callback' => function(){ return current_user_can('edit_posts'); }
    ));
});

function ttsa_rest_save_topic_price($request){
    $params = $request->get_json_params();
    $topic_id = isset($params['topic_id']) ? intval($params['topic_id']) : 0;
    $price = isset($params['price']) ? floatval($params['price']) : null;
    if (!$topic_id) return new WP_REST_Response(array('error'=>'invalid_topic'), 400);
    if ($price === null) return new WP_REST_Response(array('error'=>'invalid_price'), 400);

    update_post_meta($topic_id, '_ttsa_topic_price', $price);
    // Sync WC product
    if (function_exists('ttsa_sync_wc_product_for_topic')) {
        ttsa_sync_wc_product_for_topic($topic_id, $price);
    }
    return new WP_REST_Response(array('ok'=>true,'topic_id'=>$topic_id,'price'=>$price), 200);
}
