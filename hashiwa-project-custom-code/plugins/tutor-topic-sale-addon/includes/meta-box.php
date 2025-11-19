<?php
if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function(){
    add_meta_box(
        'ttsa_topic_price',
        __('Topic Price (WooCommerce)', 'ttsa'),
        'ttsa_render_topic_price_box',
        'tutor_topics',
        'side',
        'default'
    );
});

function ttsa_render_topic_price_box($post){
    wp_nonce_field('ttsa_save_topic_price', 'ttsa_topic_price_nonce');
    $value = get_post_meta($post->ID, '_ttsa_topic_price', true);
    $product_id = get_post_meta($post->ID, '_ttsa_wc_product_id', true);
    ?>
    <p>
        <label><?php _e('Price (decimal, e.g. 9.99)', 'ttsa'); ?></label>
        <input type="number" step="0.01" name="ttsa_topic_price" value="<?php echo esc_attr($value); ?>" style="width:100%" />
    </p>
    <p>
        <strong><?php _e('Linked WooCommerce Product ID:', 'ttsa'); ?></strong>
        <input type="text" readonly value="<?php echo esc_attr($product_id); ?>" style="width:100%" />
    </p>
    <p style="font-size:12px;color:#666"><?php _e('Save the topic to create/update linked WooCommerce product automatically.', 'ttsa'); ?></p>
    <?php
}

add_action('save_post', function($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['ttsa_topic_price_nonce']) || !wp_verify_nonce($_POST['ttsa_topic_price_nonce'], 'ttsa_save_topic_price')) return;
    if (get_post_type($post_id) !== 'tutor_topics') return;

    if (isset($_POST['ttsa_topic_price'])){
        $price = floatval($_POST['ttsa_topic_price']);
        update_post_meta($post_id, '_ttsa_topic_price', $price);

        // Create or update WooCommerce product
        ttsa_sync_wc_product_for_topic($post_id, $price);
    }
});

function ttsa_sync_wc_product_for_topic($topic_id, $price){
    if (!class_exists('WC_Product')) return;
    $product_id = get_post_meta($topic_id, '_ttsa_wc_product_id', true);

    $topic = get_post($topic_id);
    $title = $topic ? $topic->post_title : ('Topic #' . $topic_id);

    if ($product_id && get_post_status($product_id)){
        $product = wc_get_product($product_id);
        if ($product){
            $product->set_name($title . ' (Topic)');
            $product->set_price($price);
            $product->set_regular_price($price);
            $product->set_status('publish');
            $product->save();
            return $product->get_id();
        }
    }

    // create new product
    $new = new WC_Product_Simple();
    $new->set_name($title . ' (Topic)');
    $new->set_price($price);
    $new->set_regular_price($price);
    $new->set_catalog_visibility('hidden');
    $new->set_status('publish');
    $new->set_virtual(true);
    $new->set_downloadable(false);
    $new_id = $new->save();

    if ($new_id) update_post_meta($topic_id, '_ttsa_wc_product_id', $new_id);
    return $new_id;
}
