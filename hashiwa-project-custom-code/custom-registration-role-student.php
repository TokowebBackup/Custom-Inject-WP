<?php

/**
 * Debug helper
 */
function tutor_debug_log($message, $data = null)
{
    if (!defined('WP_DEBUG') || !WP_DEBUG) return;

    $log = "[TUTOR-DEBUG] " . $message;

    if ($data !== null) {
        $log .= " => " . print_r($data, true);
    }

    error_log($log);
}

/**
 * Buat role tutor_student bila belum ada
 */
add_action('init', function () {
    if (!get_role('tutor_student')) {
        add_role('tutor_student', 'Tutor Student', [
            'read'     => true,
            'level_0'  => true,
        ]);
        tutor_debug_log('Role tutor_student dibuat.');
    } else {
        tutor_debug_log('Role tutor_student SUDAH ADA.');
    }
});


/**
 * HOOK RESMI Tutor LMS
 * Dipanggil setelah student selesai registrasi lewat form Tutor LMS
 */
add_action('tutor_after_student_signup', function ($user_id) {

    tutor_debug_log('HOOK tutor_after_student_signup DIPANGGIL', ['user_id' => $user_id]);

    // --- SET ROLE
    wp_update_user([
        'ID'   => $user_id,
        'role' => 'tutor_student',
    ]);
    tutor_debug_log('Role diubah menjadi tutor_student');

    // --- SET USERMETA WAJIB UNTUK MUNCUL DI STUDENT LIST
    update_user_meta($user_id, 'tutor_profile_completed', 1);
    update_user_meta($user_id, 'tutor_last_activity', current_time('mysql'));
    update_user_meta($user_id, 'tutor_total_enroll', 0);

    tutor_debug_log('Meta tutor_profile_completed, last_activity, total_enroll di-set.');

    // Tutor LMS register
    if (function_exists('tutor_utils')) {
        tutor_utils()->register_student($user_id);
        tutor_debug_log('Tutor LMS register_student DIPANGGIL');
    } else {
        tutor_debug_log('ERROR: tutor_utils TIDAK DITEMUKAN');
    }

    // --- DEBUG USERMETA
    $meta = [
        'tutor_profile_completed' => get_user_meta($user_id, 'tutor_profile_completed', true),
        'tutor_last_activity'     => get_user_meta($user_id, 'tutor_last_activity', true),
        'tutor_total_enroll'      => get_user_meta($user_id, 'tutor_total_enroll', true)
    ];
    tutor_debug_log('CEK USERMETA SETELAH UPDATE', $meta);
});



// add_action('init', function() {
//     echo '<pre>';
//     print_r( wp_roles()->roles );
//     echo '</pre>';
// });
