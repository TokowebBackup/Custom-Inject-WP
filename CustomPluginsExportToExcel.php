// Office Excel Custom plugins
add_action('restrict_manage_posts', 'add_export_button_for_test_drive');

function add_export_button_for_test_drive() {
    global $typenow;

    if ($typenow == 'test-drive') {
        echo '<input type="submit" name="export_test_drive" class="button button-primary" value="Export to Excel">';
    }
}

add_action('init', 'handle_export_test_drive');

function handle_export_test_drive() {
    if (isset($_GET['export_test_drive']) && $_GET['export_test_drive'] == 'Export to Excel') {
        if (isset($_GET['post_type']) && $_GET['post_type'] == 'test-drive') {
            // Mengecek apakah ada post yang dipilih melalui checkbox
            if (isset($_GET['post']) && is_array($_GET['post'])) {
                $selected_post_ids = $_GET['post'];
                export_test_drive_to_excel($selected_post_ids);
            }
        }
    }
}

function export_test_drive_to_excel($post_ids) {
    require_once ABSPATH . 'excel-library/export-excel/vendor/autoload.php';
    if (count($post_ids) === 1) {
        // Jika hanya satu post, gunakan ID post tersebut sebagai nama file
        $filename = 'test-drive-export-' . $post_ids[0] . '-' . date('Y-m-d') . '.xlsx';
    } else {
        // Jika lebih dari satu post, gunakan tanggal saja sebagai nama file
        $filename = 'test-drive-export-' . date('Y-m-d') . '.xlsx';
    }

    $writer = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createXLSXWriter();
    $writer->openToBrowser($filename);
    $headerRow = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createRowFromArray(['Post ID', 'Title', 'Date', 'Time', 'First Name', 'Last Name', 'Phone', 'Email', 'Car Model', 'Dealership', 'Message']);
    $writer->addRow($headerRow);

    $args = array(
        'post_type'   => 'test-drive',
        'post__in'    => $post_ids,
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    $posts = get_posts($args);

    foreach ($posts as $post) {
        $row = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createRowFromArray([
            $post->ID,
            $post->post_title,
            $post->post_date,
            get_post_meta($post->ID, 'time', true),  // Asumsi 'time' adalah meta field
            get_post_meta($post->ID, 'first_name', true),
            get_post_meta($post->ID, 'last_name', true),
            get_post_meta($post->ID, 'phone_number', true),
            get_post_meta($post->ID, 'email', true),
            get_post_meta($post->ID, 'car_model', true),
            get_post_meta($post->ID, 'dealership', true),
            get_post_meta($post->ID, 'message', true)
        ]);
        $writer->addRow($row);
    }

    $writer->close();
    exit;
}
