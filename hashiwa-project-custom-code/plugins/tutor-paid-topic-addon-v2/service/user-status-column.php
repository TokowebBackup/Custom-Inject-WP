<?php

/**
 * ============================================================
 * 🟢 Tambahkan kolom "Status Akun" di halaman Users
 * ============================================================
 */
add_filter('manage_users_columns', function ($columns) {
    $columns['tpt_activation_status'] = 'Status Akun';
    return $columns;
});

add_action('manage_users_custom_column', function ($value, $column_name, $user_id) {
    if ($column_name === 'tpt_activation_status') {
        $is_activated = get_user_meta($user_id, '_tpt_activated', true);
        $order_id = get_user_meta($user_id, '_tpt_registration_order_id', true);
        $status = '';

        if (!$order_id) {
            $status = '<span style="color:#999;">Belum Registrasi</span>';
        } else {
            $order = wc_get_order($order_id);
            if ($order) {
                $order_status = $order->get_status();

                switch ($order_status) {
                    case 'completed':
                        $status = '<span style="color:green;font-weight:600;">✅ Aktif</span>';
                        break;
                    case 'pending':
                    case 'on-hold':
                    case 'processing':
                        $status = '<span style="color:#d97706;font-weight:600;">🕒 Menunggu Pembayaran</span>';
                        break;
                    case 'cancelled':
                    case 'refunded':
                    case 'failed':
                        $status = '<span style="color:#999;">❌ Dibatalkan</span>';
                        break;
                    default:
                        $status = '<span style="color:#666;">⏳ ' . ucfirst($order_status) . '</span>';
                }
            }
        }

        return $status;
    }
    return $value;
}, 10, 3);
