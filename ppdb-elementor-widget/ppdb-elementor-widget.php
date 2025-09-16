<?php

/**
 * Plugin Name: PPDB Elementor Widget
 * Description: Widget Elementor untuk menampilkan gambar dari Option Page JetEngine (PPDB).
 * Version: 1.0
 * Author: Puji Ermanto<pujiermanto@gmail.com> | AKA: Dadang Sugandi
 */
if (! defined('ABSPATH')) exit;

// Register Widgets
function ppdb_register_widgets($widgets_manager)
{
    require_once(__DIR__ . '/widgets/widget-ppdb.php');
    $widgets_manager->register(new \PPDB_Widget());
}
add_action('elementor/widgets/register', 'ppdb_register_widgets');
