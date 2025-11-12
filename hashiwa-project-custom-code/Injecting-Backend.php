<?php
/**
 * Add Custom Post Type "Kontak Informasi" inside Tutor LMS menu
 * 
 * @author Puji Ermanto <pujiermanto@gmail.com> | AKA Jhony Rotten
 * @version 1.1
 * @description Customisasi dashboard admin.
 */

function newsletter_signup_form_shortcode() {
    return '
    <form action="https://your-newsletter-service.com/subscribe" method="post" target="_blank" novalidate>
      <label for="email" style="display:block; margin-bottom: 8px;">Subscribe to our newsletter:</label>
      <input type="email" id="email" name="email" placeholder="Your email address" required style="padding: 8px; width: 250px; max-width: 100%;">
      <button type="submit" style="padding: 8px 16px; background-color: #ED2D56; color: white; border: none; cursor: pointer; margin-left: 8px;">Subscribe</button>
    </form>';
}
add_shortcode('newsletter_signup', 'newsletter_signup_form_shortcode');

function register_custom_menu_location() {
    register_nav_menu('bottom-menu', 'Bottom Navbar Menu');
}
add_action('after_setup_theme', 'register_custom_menu_location');

function custom_admin_dashboard_text() {
    global $wp_version;
    
    // Ganti teks "Welcome to WordPress!" menjadi sesuai keinginan Anda
    $welcome_text = 'HASHIWA JAPANESE ACADEMY';
    
    // Ganti teks "Learn more about the 6.5.5 version." sesuai keinginan Anda
    $version_text = 'Bridge Beyond Border';
    
    // Mengganti teks menggunakan filter
    add_filter('gettext', function($translated_text, $text, $domain) use ($welcome_text, $version_text, $wp_version) {
        if ($text === 'Welcome to WordPress!') {
            $translated_text = $welcome_text;
        }
        if ($text === 'Learn more about the %s version.') {
            $translated_text = sprintf($version_text, $wp_version);
        }
        return $translated_text;
    }, 10, 3);
}
add_action('admin_init', 'custom_admin_dashboard_text');

function move_menus_to_top() {
    global $menu;

    $snippets_key = null;
    foreach ($menu as $key => $menu_item) {
        if ($menu_item[2] === 'snippets' && $menu_item[2] === 'music_review' && $menu_item[2] === 'film_review') {
            $snippets_key = $key;
        }
    }

    $new_menu = [];
    if ($snippets_key !== null) {
        $new_menu[] = $menu[$snippets_key];
        unset($menu[$snippets_key]);
    }

    $menu = array_merge($new_menu, $menu);
}
add_action('admin_menu', 'move_menus_to_top', 9);

function replace_admin_menu_icons() {
	$base_url = esc_url( home_url() ); 
    $icon_path = '/wp-content/uploads/2025/11/fav-1-1-2.webp'; // path relatif ke root
    ?>
    <style>
        /* Hapus dashicon bawaan */
        #toplevel_page_snippets .wp-menu-image.dashicons-before::before {
            content: none !important;
        }

        /* Ganti dengan ikon custom */
        #toplevel_page_snippets .wp-menu-image {
            background-image: url('<?php echo $base_url . $icon_path; ?>') !important;
            background-size: 20px 20px !important;
            background-repeat: no-repeat !important;
            background-position: center center !important;
            width: 30px !important;
            height: 30px !important;
        }

        /* Sembunyikan tag <img> kalau ada */
        #toplevel_page_snippets .wp-menu-image img {
            display: none !important;
        }
    </style>
    <?php
}
add_action('admin_head', 'replace_admin_menu_icons');

function enqueue_sweetalert_admin()
{
    wp_enqueue_script('sweetalert', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, true);
}
add_action('admin_enqueue_scripts', 'enqueue_sweetalert_admin');
/**
 * Proteksi halaman Code Snippets dengan password + SweetAlert2
 */
add_action('admin_init', 'restrict_snippets_access_by_password');
function restrict_snippets_access_by_password()
{
    $allowed_password = '123';

    // Halaman Code Snippets yang dibatasi
    $restricted_pages = [
        'snippets',
        'edit-snippet',
        'add-snippet',
        'import-code-snippets',
        'snippets-settings',
        'code-snippets-welcome',
        'code_snippets_upgrade',
    ];

    // Cek jika sedang di halaman yang dibatasi
    if (is_admin() && isset($_GET['page']) && in_array($_GET['page'], $restricted_pages, true)) {

        // Muat SweetAlert2
        add_action('admin_enqueue_scripts', function () {
            wp_enqueue_script(
                'sweetalert2',
                'https://cdn.jsdelivr.net/npm/sweetalert2@11',
                [],
                null,
                true
            );
        });

        $password = isset($_GET['password']) ? sanitize_text_field($_GET['password']) : '';

        if ($password !== $allowed_password) {
            add_action('admin_footer', function () use ($password) {
                $wrong_pw = $password !== '';
            ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {

                        <?php if ($wrong_pw) : ?>
                            Swal.fire({
                                icon: 'error',
                                title: 'Password Salah!',
                                text: 'Silakan coba lagi.',
                                confirmButtonText: 'Ulangi'
                            }).then(() => {
                                window.location.href = '<?php echo admin_url(); ?>';
                            });
                        <?php else : ?>
                            Swal.fire({
                                title: 'Masukkan Password',
                                html: `
									<div style="position:relative;margin-bottom:6px;">
										<input id="swal-input-password" type="password" class="swal2-input" placeholder="Password">
										<button type="button" id="toggle-password" style="position:absolute;top:8px;right:8px;background:transparent;border:none;cursor:pointer;">
											👁️
										</button>
									</div>
									<small id="password-hint" style="display:block;font-size:13px;color:#888;margin-top:-8px;">
										Hint: 3 digit angka favoritmu 😉
									</small>
								`,
                                focusConfirm: false,
                                showCancelButton: true,
                                confirmButtonText: 'Submit',
                                cancelButtonText: 'Batal',
                                preConfirm: () => {
                                    const pw = document.getElementById('swal-input-password').value;
                                    if (!pw) {
                                        Swal.showValidationMessage('Password tidak boleh kosong');
                                        return false;
                                    }
                                    return pw;
                                },
                                didOpen: () => {
                                    const btn = document.getElementById('toggle-password');
                                    const input = document.getElementById('swal-input-password');
                                    btn.addEventListener('click', () => {
                                        const type = input.type === 'password' ? 'text' : 'password';
                                        input.type = type;
                                        btn.textContent = type === 'password' ? '👁️' : '🙈';
                                    });
                                }
                            }).then((result) => {
                                if (result.isConfirmed && result.value) {
                                    const baseURL = window.location.href.split('&password=')[0];
                                    window.location.href = baseURL + '&password=' + encodeURIComponent(result.value);
                                } else {
                                    window.location.href = '<?php echo admin_url(); ?>';
                                }
                            });
                        <?php endif; ?>

                    });
                </script>
            <?php
            });
        }
    }
}

