<?php

// Register widget
add_action('elementor/widgets/register', function ($widgets_manager) {
    require_once get_stylesheet_directory() . '/custom-elementor-widget/produk-by-kategori-widget.php';
    $widgets_manager->register(new \Produk_By_Kategori_ACF_Widget());
});
