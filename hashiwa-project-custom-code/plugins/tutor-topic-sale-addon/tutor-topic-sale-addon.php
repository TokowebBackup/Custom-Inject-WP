<?php

/**
 * Plugin Name: Tutor LMS - Topic Based Payment Addon
 * Description: Adds per-topic pricing and purchase flow for Tutor LMS using WooCommerce.
 * Version: 1.0.0
 * Author: Puji Ermanto <pujiermanto@gmail.com> | AKA Jhony Rotten
 * Text Domain: ttsa
 */
if (!defined('ABSPATH')) exit;

define('TTSA_PATH', plugin_dir_path(__FILE__));
define('TTSA_URL', plugin_dir_url(__FILE__));
define('TTSA_VERSION', '1.0.0');

// Includes
require_once TTSA_PATH . 'includes/meta-box.php';
require_once TTSA_PATH . 'includes/ui-hooks.php';
require_once TTSA_PATH . 'includes/woocommerce.php';
require_once TTSA_PATH . 'includes/protect.php';
require_once TTSA_PATH . 'includes/admin-rest.php';


// Assets
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('ttsa-frontend', TTSA_URL . 'assets/css/style.css', array(), TTSA_VERSION);
    wp_enqueue_script('ttsa-frontend', TTSA_URL . 'assets/js/ttsa-frontend.js', array('jquery'), TTSA_VERSION, true);
    wp_localize_script('ttsa-frontend', 'TTSA', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ttsa_buy_topic_nonce'),
    ));
});

// Activation checks
register_activation_hook(__FILE__, function () {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    if (!is_plugin_active('tutor/tutor.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This addon requires Tutor LMS plugin to be active.');
    }
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This addon requires WooCommerce plugin to be active.');
    }
});

// Admin notice if requirements missing (in case activation bypass)
add_action('admin_notices', function () {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    if (!is_plugin_active('tutor/tutor.php') || !is_plugin_active('woocommerce/woocommerce.php')) {
        echo '<div class="notice notice-error"><p>';
        echo __('TTSA requires Tutor LMS and WooCommerce to be active.', 'ttsa');
        echo '</p></div>';
    }
});


add_action('admin_enqueue_scripts', function ($hook) {
    // Only enqueue on Tutor builder pages (best-effort check)
    wp_enqueue_script('ttsa-admin-builder', TTSA_URL . 'assets/js/admin-builder.js', array('jquery'), TTSA_VERSION, true);
    wp_localize_script('ttsa-admin-builder', 'TTSA', array(
        'nonce' => wp_create_nonce('wp_rest'),
    ));
});
