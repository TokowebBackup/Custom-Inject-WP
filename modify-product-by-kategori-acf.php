<?php

/**
 * @modify Puji Ermanto<pujiermanto@gmail.com> | AKA Maman Salajami | AKA Deden Inyuuus
 * @params _filter_role
 * @return kategori with role
 **/
add_shortcode('produk_by_kategori_acf', function () {
    $kategori = get_field('kategori_produk');
    if (!$kategori || !is_a($kategori, 'WP_Term')) {
        return 'Kategori tidak ditemukan';
    }

    if (!is_user_logged_in()) {
        return '<p>Silakan login untuk melihat produk yang tersedia.</p>';
    }

    $user = wp_get_current_user();
    $user_role = $user->roles[0] ?? '';

    $role_meta = [
        'relation' => 'OR',
        [
            'key'     => '_visible_for_role',
            'compare' => 'NOT EXISTS',
        ],
        [
            'key'     => '_visible_for_role',
            'value'   => '',
            'compare' => '=',
        ],
    ];

    if ($user_role === 'mitra') {
        $role_meta[] = [
            'relation' => 'OR',
            [
                'key'     => '_visible_for_role',
                'value'   => 'mitra',
                'compare' => '=',
            ],
            [
                'key'     => '_visible_for_role',
                'value'   => 'user',
                'compare' => '=',
            ],
        ];
    } else {
        $role_meta[] = [
            'key'     => '_visible_for_role',
            'value'   => $user_role,
            'compare' => '=',
        ];
    }

    $query = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => 8,
        'tax_query'      => [[
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $kategori->slug,
        ]],
        'meta_query'     => $role_meta,
        'no_found_rows'  => true,
        'cache_results'  => false,
    ]);

    error_log("WP_Query SQL: " . $query->request);
    error_log("Jumlah produk: " . $query->found_posts);

    ob_start();

    if ($query->have_posts()) {
        echo '<div class="c-product-grid__wrap">';
        woocommerce_product_loop_start();
        while ($query->have_posts()) {
            $query->the_post();
            wc_get_template_part('content', 'product');
        }
        woocommerce_product_loop_end();
        echo '</div>';
    } else {
        echo '<p>Tidak ada produk yang sesuai dengan role Anda.</p>';
    }
    wp_reset_postdata();

    echo '<div style="text-align:center;margin-top:20px;">';
    echo '<a href="' . esc_url(get_term_link($kategori)) . '" style="display:inline-block;background:#000;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;">View All Products</a>';
    echo '</div>';

    return ob_get_clean();
});
