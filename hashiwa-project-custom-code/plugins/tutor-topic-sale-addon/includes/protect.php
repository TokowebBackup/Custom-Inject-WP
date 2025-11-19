<?php
if (!defined('ABSPATH')) exit;

// Protect single lesson/topic content: redirect unauthorized users to purchase page.
add_action('template_redirect', function(){
    if (is_singular('tutor_lesson')) {
        $lesson_id = get_the_ID();
        // find parent topic by meta (Tutor stores relation differently sometimes)
        $topic_id = get_post_meta($lesson_id, '_tutor_topic_id', true);
        if (!$topic_id){
            // fallback: try to find via post_parent or taxonomy
            $topic_id = wp_get_post_parent_id($lesson_id);
        }
        if ($topic_id){
            $user_id = get_current_user_id();
            if (!ttsa_user_has_topic_access($user_id, $topic_id)){
                // allow course author and admins
                if (current_user_can('edit_post', $lesson_id)) return;
                // redirect to course page with notice and anchor to topic
                $course_id = get_post_meta($topic_id, '_tutor_course_id', true) ?: '';
                $course_url = $course_id ? get_permalink($course_id) : home_url('/');
                wp_redirect(add_query_arg('ttsa_need_purchase', $topic_id, $course_url));
                exit;
            }
        }
    }
});
