<?php

/**
 * ============================================================
 * 🔐 Tutor Student Role & Registration Handler (Full Patch)
 * ============================================================
 */

/**
 * Debug helper
 */
if (!function_exists('tpt_debug_log')) {
    function tpt_debug_log($message, $data = null)
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) return;
        $log = "[TPT-DEBUG] " . $message;
        if ($data !== null) $log .= " => " . print_r($data, true);
        error_log($log);
    }
}

/**
 * Pastikan role tutor_student tersedia
 */
add_action('init', function () {
    if (!get_role('tutor_student')) {
        add_role('tutor_student', 'Tutor Student', [
            'read'         => true,
            'upload_files' => true,
            'level_0'      => true,
        ]);
        tpt_debug_log('Role tutor_student dibuat otomatis.');
    }
});

/**
 * REST API Endpoint untuk Aktivasi Akun
 */
add_action('rest_api_init', function () {
    register_rest_route('tpt/v1', '/activate', [
        'methods' => 'GET',
        'callback' => function ($request) {
            $key = sanitize_text_field($request->get_param('key'));
            $user_id = intval($request->get_param('user'));

            if (!$key || !$user_id) {
                return new WP_REST_Response(['error' => 'Invalid activation link'], 400);
            }

            $stored_key = get_user_meta($user_id, '_tpt_activation_key', true);
            $is_activated = get_user_meta($user_id, '_tpt_activated', true);

            if ($is_activated || $stored_key !== $key) {
                return new WP_REST_Response(['error' => 'Activation link invalid or already used'], 400);
            }

            update_user_meta($user_id, '_tpt_activated', true);
            delete_user_meta($user_id, '_tpt_activation_key');

            wp_redirect(site_url('/dashboard?activated=1'));
            exit;
        },
        'permission_callback' => '__return_true'
    ]);
});

/**
 * Saat user register via Tutor LMS
 * Generate NIM otomatis
 */
add_action('tutor_after_student_signup', function ($user_id) {
    $user = get_userdata($user_id);
    if (!$user) return;

    // Set role tutor_student
    wp_update_user([
        'ID'   => $user_id,
        'role' => 'tutor_student',
    ]);

    // Update meta Tutor LMS
    update_user_meta($user_id, 'tutor_profile_completed', 1);
    update_user_meta($user_id, 'tutor_last_activity', current_time('mysql'));
    update_user_meta($user_id, 'tutor_total_enroll', 0);

    // Set pending activation
    update_user_meta($user_id, '_tpt_activated', false);
    $activation_key = wp_generate_password(32, false);
    update_user_meta($user_id, '_tpt_activation_key', $activation_key);

    // 🔹 Generate NIM otomatis format HJA-YY-XXXX
    $current_year = date('Y'); // 2 digit tahun sekarang
    $prefix = 'HJA-' . $current_year . '-';

    // Ambil tutor_student terakhir dengan NIM tahun ini
    $users_with_nim = get_users([
        'role'        => 'tutor_student',
        'meta_key'    => 'nim',
        'meta_value'  => $prefix,
        'meta_compare' => 'LIKE',
        'orderby'     => 'ID',
        'order'       => 'DESC',
        'number'      => 1,
    ]);

    $last_nim = $users_with_nim ? get_user_meta($users_with_nim[0]->ID, 'nim', true) : '';
    $next_number = $last_nim ? intval(substr($last_nim, -4)) + 1 : 1;
    $nim = $prefix . str_pad($next_number, 4, '0', STR_PAD_LEFT);

    update_user_meta($user_id, 'nim', $nim);
    tpt_debug_log("NIM otomatis disimpan untuk user_id $user_id", $nim);

    // Kirim email aktivasi
    // $activation_link = site_url('/wp-json/tpt/v1/activate?key=' . $activation_key . '&user=' . $user_id);
    // $subject = 'Aktivasi Akun Tutor Student - ' . get_bloginfo('name');
    // $message = "
    // Halo {$user->display_name},

    // Terima kasih telah mendaftar sebagai Tutor Student di " . get_bloginfo('name') . ".

    // Untuk mengaktifkan akun Anda dan mulai belajar, klik link berikut:
    // {$activation_link}

    // Jika Anda tidak mendaftar, abaikan email ini.

    // Salam,
    // Tim " . get_bloginfo('name') . "
    // ";
    // wp_mail($user->user_email, $subject, $message);
    // 🕒 Email aktivasi akan dikirim setelah pembayaran Completed (lihat hook di bawah)
    update_user_meta($user_id, '_tpt_pending_activation_email', [
        'key' => $activation_key,
        'sent' => false
    ]);

    // ==========================================
    // 🔹 BUAT ORDER PEMBAYARAN REGISTRASI
    // ==========================================
    $registration_product_id = intval(get_option('tpt_registration_product_id'));

    // ✅ Tambahkan fallback jika produk belum diset di pengaturan
    if (!$registration_product_id) {
        error_log('[TPT-REG] ❌ Produk biaya registrasi belum diatur di settings.');
        wp_logout();
        wp_redirect(site_url('/dashboard?activation_required=1'));
        exit;
    }

    // if (class_exists('WC_Order')) {
    //     $order = wc_create_order([
    //         'customer_id' => $user_id,
    //         'status'      => 'pending',
    //     ]);

    //     $order->add_product(wc_get_product($registration_product_id), 1);
    //     $order->calculate_totals();

    //     // simpan user meta untuk tracking
    //     update_user_meta($user_id, '_tpt_registration_order_id', $order->get_id());

    //     // redirect user ke halaman checkout
    //     $checkout_url = $order->get_checkout_payment_url();
    //     wp_redirect($checkout_url);
    //     exit;
    // }

    if (class_exists('WC_Order')) {
        // Buat order TANPA customer_id agar WooCommerce tidak auto-login
        $order = wc_create_order(['status' => 'pending']);

        $order->add_product(wc_get_product($registration_product_id), 1);
        $order->calculate_totals();

        // ✅ Tambahkan info billing agar Midtrans valid
        $user_email = $user->user_email ?? '';
        $user_name = $user->display_name ?? $user->user_login;
        $order->set_billing_email($user_email);
        $order->set_billing_first_name($user_name ?: 'Student');
        $order->set_billing_last_name('');
        $order->set_billing_phone(get_user_meta($user_id, 'billing_phone', true) ?: '081234567890');
        $order->save();

        // Simpan user_id manual TANPA auto-login
        update_post_meta($order->get_id(), '_customer_user', $user_id);

        // Simpan juga data order di user meta untuk tracking
        update_user_meta($user_id, '_tpt_registration_order_id', $order->get_id());

        // Pastikan semua session WooCommerce dibersihkan
        if (class_exists('WC_Session_Handler') && function_exists('WC')) {
            if (WC()->session) {
                WC()->session->destroy_session();
            }
            if (WC()->cart) {
                WC()->cart->empty_cart();
            }
        }

        // Logout paksa agar tidak ada cookie login tersisa
        wp_logout();

        // Redirect user ke halaman pembayaran (order-pay)
        $checkout_url = $order->get_checkout_payment_url();
        wp_redirect($checkout_url);
        exit;
    }

    // Redirect ke dashboard activation_required
    // wp_logout();
    // wp_redirect(site_url('/dashboard?activation_required=1'));
    // exit;
});

/**
 * ============================================================
 * ✉️ Kirim Email Aktivasi Setelah Order Completed
 * ============================================================
 */
// add_action('woocommerce_order_status_completed', function ($order_id) {
//     $order = wc_get_order($order_id);
//     if (!$order) return;

//     $user_id = $order->get_user_id();
//     if (!$user_id) return;

//     $pending = get_user_meta($user_id, '_tpt_pending_activation_email', true);
//     $is_activated = get_user_meta($user_id, '_tpt_activated', true);

//     // Pastikan user belum diaktivasi dan email belum terkirim
//     if (is_array($pending) && !$is_activated && empty($pending['sent'])) {
//         $activation_link = site_url('/wp-json/tpt/v1/activate?key=' . $pending['key'] . '&user=' . $user_id);
//         $user = get_userdata($user_id);

//         $subject = 'Aktivasi Akun Tutor Student - ' . get_bloginfo('name');
//         $subject = 'Aktivasi Akun Tutor Student - ' . get_bloginfo('name');

//         $message = '
// <html>
// <head>
//   <style>
//     @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap");
//   </style>
// </head>
// <body style="font-family:Poppins,Arial,sans-serif;background:#f5f5f5;margin:0;padding:40px;">
//   <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.05);overflow:hidden;">
//     <div style="background:#ED2D56;color:#fff;padding:20px 30px;text-align:center;">
//       <h2 style="margin:0;font-size:22px;">Aktivasi Akun Anda</h2>
//     </div>
//     <div style="padding:30px 40px;color:#333;">
//       <p>Halo <strong>' . esc_html($user->display_name) . '</strong>,</p>
//       <p>Pembayaran registrasi Anda telah <strong>dikonfirmasi</strong>. Akun Anda hampir siap digunakan untuk belajar di <strong>' . get_bloginfo('name') . '</strong>.</p>
//       <p style="margin-top:20px;margin-bottom:20px;text-align:center;">
//         <a href="' . esc_url($activation_link) . '" style="background:#ED2D56;color:#fff;text-decoration:none;padding:12px 25px;border-radius:6px;font-weight:600;display:inline-block;">Aktifkan Akun Saya</a>
//       </p>
//       <p>Jika tombol di atas tidak berfungsi, salin dan tempel tautan berikut ke browser Anda:</p>
//       <p style="word-break:break-all;color:#ED2D56;">' . esc_url($activation_link) . '</p>
//       <p style="margin-top:30px;">Terima kasih,<br>Tim ' . get_bloginfo('name') . '</p>
//     </div>
//     <div style="background:#f1f1f1;color:#777;text-align:center;padding:12px;font-size:13px;">
//       &copy; ' . date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.
//     </div>
//   </div>
// </body>
// </html>
// ';

//         add_filter('wp_mail_content_type', function () {
//             return 'text/html';
//         });
//         wp_mail($user->user_email, $subject, $message);
//         remove_filter('wp_mail_content_type', 'set_html_content_type');


//         wp_mail($user->user_email, $subject, $message);

//         // Tandai sudah dikirim
//         $pending['sent'] = true;
//         update_user_meta($user_id, '_tpt_pending_activation_email', $pending);

//         error_log("[TPT-REG] ✉️ Email aktivasi dikirim untuk user_id {$user_id} setelah order {$order_id} completed.");
//     }
// });
/**
 * ✉️ Kirim Email Aktivasi Setelah Order Completed (Final - HTML Template + Fallback)
 */
add_action('woocommerce_order_status_completed', function ($order_id) {
    error_log("[TPT-REG] 🔔 Hook woocommerce_order_status_completed dipanggil untuk order {$order_id}");

    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_user_id();

    // 🩹 Fallback: cari user dari meta kalau order belum terhubung ke user
    if (!$user_id) {
        global $wpdb;
        $user_id = $wpdb->get_var($wpdb->prepare("
            SELECT user_id FROM {$wpdb->usermeta}
            WHERE meta_key = '_tpt_registration_order_id' AND meta_value = %d
        ", $order_id));

        if ($user_id) {
            update_post_meta($order_id, '_customer_user', $user_id);
            error_log("[TPT-REG] 🩹 Fallback: Hubungkan order {$order_id} dengan user_id {$user_id}");
        } else {
            error_log("[TPT-REG] ⚠️ Tidak bisa temukan user untuk order {$order_id}");
            return;
        }
    }

    $pending = get_user_meta($user_id, '_tpt_pending_activation_email', true);

    // 🩹 Fallback jika meta kosong
    if (!is_array($pending) || empty($pending['key'])) {
        $activation_key = wp_generate_password(32, false);
        update_user_meta($user_id, '_tpt_activation_key', $activation_key);
        $pending = ['key' => $activation_key, 'sent' => false];
        update_user_meta($user_id, '_tpt_pending_activation_email', $pending);
        error_log("[TPT-REG] 🩹 Buat meta aktivasi baru untuk user_id {$user_id}.");
    }

    // ✅ Kirim email kalau belum dikirim
    if (is_array($pending) && empty($pending['sent'])) {
        $activation_link = site_url('/wp-json/tpt/v1/activate?key=' . $pending['key'] . '&user=' . $user_id);
        $user = get_userdata($user_id);

        $subject = 'Aktivasi Akun Tutor Student - ' . get_bloginfo('name');

        $message = '
<html>
<head>
  <style>
    @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap");
  </style>
</head>
<body style="font-family:Poppins,Arial,sans-serif;background:#f5f5f5;margin:0;padding:40px;">
  <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.05);overflow:hidden;">
    <div style="background:#ED2D56;color:#fff;padding:20px 30px;text-align:center;">
      <h2 style="margin:0;font-size:22px;">Aktivasi Akun Anda</h2>
    </div>
    <div style="padding:30px 40px;color:#333;">
      <p>Halo <strong>' . esc_html($user->display_name) . '</strong>,</p>
      <p>Pembayaran registrasi Anda telah <strong>dikonfirmasi</strong>. Akun Anda hampir siap digunakan untuk belajar di <strong>' . get_bloginfo('name') . '</strong>.</p>
      <p style="margin-top:20px;margin-bottom:20px;text-align:center;">
        <a href="' . esc_url($activation_link) . '" style="background:#ED2D56;color:#fff;text-decoration:none;padding:12px 25px;border-radius:6px;font-weight:600;display:inline-block;">Aktifkan Akun Saya</a>
      </p>
      <p>Jika tombol di atas tidak berfungsi, salin dan tempel tautan berikut ke browser Anda:</p>
      <p style="word-break:break-all;color:#ED2D56;">' . esc_url($activation_link) . '</p>
      <p style="margin-top:30px;">Terima kasih,<br>Tim ' . get_bloginfo('name') . '</p>
    </div>
    <div style="background:#f1f1f1;color:#777;text-align:center;padding:12px;font-size:13px;">
      &copy; ' . date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.
    </div>
  </div>
</body>
</html>
';

        add_filter('wp_mail_content_type', fn() => 'text/html');
        $sent = wp_mail($user->user_email, $subject, $message);
        remove_filter('wp_mail_content_type', 'set_html_content_type');

        if ($sent) {
            $pending['sent'] = true;
            update_user_meta($user_id, '_tpt_pending_activation_email', $pending);
            error_log("[TPT-REG] ✉️ Email aktivasi dikirim untuk user_id {$user_id}.");
        } else {
            error_log("[TPT-REG] ❌ Gagal kirim email aktivasi untuk user_id {$user_id}.");
        }
    } else {
        error_log("[TPT-REG] ⚠️ Email aktivasi dilewati (sudah terkirim) untuk user_id {$user_id}.");
    }
}, 999);

add_action('woocommerce_payment_complete', function ($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_status() !== 'completed') {
        $order->update_status('completed', 'Auto-completed by Tutor Paid Topic Addon V2');
    }
});

// 🔁 Fallback auto-complete ketika Midtrans kirim status on-hold
add_action('woocommerce_order_status_on-hold', function ($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->needs_payment() === false) {
        $order->update_status('completed', 'Auto-completed after manual settlement simulation.');
    }
});


/**
 * Cegah login jika akun belum aktivasi
 */
// add_filter('authenticate', function ($user, $username, $password) {
//     if (is_wp_error($user)) return $user;
//     if (!$username) return $user;

//     $user_obj = get_user_by('login', $username);
//     if (!$user_obj) return $user;

//     $is_activated = get_user_meta($user_obj->ID, '_tpt_activated', true);
//     if ($is_activated != '1') {
//         add_filter('login_redirect', function () {
//             return site_url('/dashboard?activation_required=1');
//         }, 10, 3);

//         return new WP_Error('activation_required', 'Akun Anda belum diaktifkan. Periksa email untuk link aktivasi.');
//     }

//     return $user;
// }, 20, 3);
add_filter('authenticate', function ($user, $username, $password) {
    if (is_wp_error($user)) return $user;
    if (!$username) return $user;

    $user_obj = get_user_by('login', $username);
    if (!$user_obj) return $user;

    // ✅ Tambahkan filter: hanya berlaku untuk role tutor_student
    if (!in_array('tutor_student', (array) $user_obj->roles)) {
        return $user; // lewati validasi aktivasi
    }

    $is_activated = get_user_meta($user_obj->ID, '_tpt_activated', true);
    if ($is_activated != '1') {
        add_filter('login_redirect', function () {
            return site_url('/dashboard?activation_required=1');
        }, 10, 3);

        return new WP_Error('activation_required', 'Akun Anda belum diaktifkan. Periksa email untuk link aktivasi.');
    }

    return $user;
}, 20, 3);


/**
 * Force logout & redirect jika user login tapi belum aktivasi
 */
// add_action('init', function () {
//     if (is_user_logged_in()) {
//         $user_id = get_current_user_id();
//         $is_activated = get_user_meta($user_id, '_tpt_activated', true);
//         $current_uri = $_SERVER['REQUEST_URI'];

//         // Block semua akses jika belum aktivasi, kecuali halaman activation_required
//         if ($is_activated === false && !str_contains($current_uri, 'activation_required=1')) {
//             wp_logout();
//             wp_safe_redirect(site_url('/dashboard?activation_required=1'));
//             exit;
//         }
//     }
// });
add_action('init', function () {
    if (!is_user_logged_in()) return;

    $user = wp_get_current_user();

    // ✅ Hanya untuk role tutor_student
    if (!in_array('tutor_student', (array) $user->roles)) {
        return;
    }

    $is_activated = get_user_meta($user->ID, '_tpt_activated', true);
    $current_uri = $_SERVER['REQUEST_URI'];

    if ($is_activated === false && !str_contains($current_uri, 'activation_required=1')) {
        wp_logout();
        wp_safe_redirect(site_url('/dashboard?activation_required=1'));
        exit;
    }
});

/**
 * ============================================================
 * 💳 Override Snap Midtrans onClose Event (UX Friendly)
 * ============================================================
 */
add_action('wp_footer', function () {
    if (!is_checkout_pay_page()) return; // hanya di halaman order-pay

?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // pastikan snap.js sudah ada
            if (typeof window.snap === 'undefined' || typeof window.snap.pay === 'undefined') return;

            // backup original snap.pay
            const originalSnapPay = window.snap.pay;

            // override snap.pay
            window.snap.pay = function(token, options) {
                const originalOnClose = options?.onClose;

                options.onClose = function() {
                    Swal.fire({
                        title: 'Tutup Pembayaran?',
                        text: 'Apakah Anda ingin melanjutkan pembayaran nanti?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Buka Lagi Pembayaran',
                        cancelButtonText: 'Batalkan',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // reload halaman untuk membuka kembali snap
                            location.reload();
                        } else {
                            // redirect ke halaman aman (misalnya dashboard)
                            window.location.href = '/dashboard?payment_cancelled=1';
                        }
                    });

                    if (typeof originalOnClose === 'function') {
                        originalOnClose();
                    }
                };

                // jalankan snap asli
                originalSnapPay(token, options);
            };
        });
    </script>
<?php
});

/**
 * Cegah akses dashboard Tutor LMS sebelum konten load
 */
add_action('tutor_dashboard_after_page_load', function () {
    if (!is_user_logged_in()) return;

    $user_id = get_current_user_id();
    $is_activated = get_user_meta($user_id, '_tpt_activated', true);

    if ($is_activated != 1) {
        wp_logout();
        wp_redirect(site_url('/dashboard?activation_required=1'));
        exit;
    }
});

/**
 * SweetAlert + Flash text alert di login form
 */
add_action('wp_footer', function () {
    if (isset($_GET['activated']) && $_GET['activated'] == 1) {
        echo <<<HTML
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    icon: 'success',
    title: 'Akun diaktifkan!',
    html: 'Akun Anda telah berhasil diaktifkan.<br>Silakan login sekarang untuk mengakses dashboard dan mulai belajar.',
    confirmButtonText: 'OK'
});
</script>
HTML;
    }

    if (isset($_GET['activation_required']) && $_GET['activation_required'] == 1) {
        echo <<<HTML
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    icon: 'warning',
    title: 'Aktivasi Diperlukan!',
    html: 'Akun Anda belum diaktifkan.<br>Silakan cek email dan klik link aktivasi sebelum login.',
    confirmButtonText: 'OK'
});
</script>
HTML;

        echo <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginFormWrapper = document.querySelector('.tutor-login-form-wrapper');
    if (loginFormWrapper) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-warning';
        alertDiv.role = 'alert';
        alertDiv.innerHTML = '<strong>Info:</strong> Akun Anda belum diaktifkan. Periksa email untuk link aktivasi sebelum login.';
        loginFormWrapper.prepend(alertDiv);
    }
});
</script>
HTML;
    }
});

/**
 * ============================================================
 * 🔹 Tampilkan kolom NIM di list table Users
 * ============================================================
 */
add_filter('manage_users_columns', function ($columns) {
    $columns['nim'] = 'NIM';
    return $columns;
});

add_action('manage_users_custom_column', function ($value, $column_name, $user_id) {
    if ($column_name === 'nim') {
        $nim = get_user_meta($user_id, 'nim', true);
        return $nim ? esc_html($nim) : '-';
    }
    return $value;
}, 10, 3);

add_filter('manage_users_sortable_columns', function ($columns) {
    $columns['nim'] = 'nim';
    return $columns;
});

add_action('pre_get_users', function ($query) {
    if (!is_admin()) return;
    if (isset($_GET['orderby']) && $_GET['orderby'] === 'nim') {
        $query->query_vars['meta_key'] = 'nim';
        $query->query_vars['orderby'] = 'meta_value';
    }
});
