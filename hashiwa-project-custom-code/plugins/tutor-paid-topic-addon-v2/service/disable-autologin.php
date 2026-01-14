<?php

/**
 * 🚫 Disable total auto-login Tutor LMS (register_student override)
 */
// 🧩 Disable nonce validation from Tutor LMS
add_action('init', function () {
    if (!class_exists('TUTOR_AJAX')) return;

    remove_action('wp_ajax_tutor_register_student', [TUTOR_AJAX::class, 'register_student']);
    remove_action('wp_ajax_nopriv_tutor_register_student', [TUTOR_AJAX::class, 'register_student']);

    add_action('wp_ajax_nopriv_tutor_register_student', function () {
        require_once ABSPATH . 'wp-admin/includes/user.php';

        // ✅ Bypass or validate nonce safely
        if (isset($_POST['tutor_nonce']) && !wp_verify_nonce($_POST['tutor_nonce'], 'tutor_nonce')) {
            // bisa kirim warning friendly ke front-end
            wp_send_json_error(['message' => 'Nonce invalid, silakan refresh halaman dan coba lagi.']);
        }

        $data = $_POST;
        $email = sanitize_email($data['email'] ?? '');
        $password = sanitize_text_field($data['password'] ?? '');
        $username = sanitize_user($data['username'] ?? explode('@', $email)[0]);

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'Email dan password wajib diisi.']);
        }

        // Buat user tanpa auto-login
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        do_action('tutor_after_student_register', $user_id);
        do_action('tutor_after_student_signup', $user_id);

        wp_logout();

        // Ambil order ID dari hook signup
        $order_id = get_user_meta($user_id, '_tpt_registration_order_id', true);
        $checkout_url = $order_id
            ? wc_get_checkout_url() . "?pay_for_order=true&order_id={$order_id}"
            : site_url('/dashboard?activation_required=1');

        wp_send_json_success([
            'message' => 'Registrasi berhasil. Silakan lanjut ke pembayaran.',
            'redirect_url' => $checkout_url,
        ]);
    });
});
