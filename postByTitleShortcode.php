<?php
// @author: Puji Ermanto <pujiermanto@gmail.com>
function display_post_by_title($atts) {
    $atts = shortcode_atts(
        array(
            'title' => '',
        ),
        $atts,
        'post_by_title'
    );

    if (!$atts['title']) {
        return '<p>No title provided.</p>';
    }

    $post = get_page_by_title($atts['title'], OBJECT, 'post');

    if (!$post) {
        return '<p>No post found with the given title.</p>';
    }

    // Get the post thumbnail (featured image)
    $featured_image = '';
    if (has_post_thumbnail($post->ID)) {
        $featured_image = get_the_post_thumbnail($post->ID, 'full');
    }

    $content = '<h2>' . esc_html($post->post_title) . '</h2>';
    $content .= $featured_image; // Add featured image here
    $content .= '<div>' . apply_filters('the_content', $post->post_content) . '</div>';

    return $content;
}
add_shortcode('post_by_title', 'display_post_by_title');
