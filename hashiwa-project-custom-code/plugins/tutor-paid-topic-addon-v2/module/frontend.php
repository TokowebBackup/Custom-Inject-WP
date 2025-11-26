<?php
add_filter('tutor_course_card_price_html', function ($price_html, $course_id) {
    $response = wp_remote_get(rest_url("tpt/v1/get-topic-prices?course_id={$course_id}"));
    if (is_wp_error($response)) return $price_html;

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!$data || !isset($data['price_min'], $data['price_max'])) return $price_html;

    $min = intval($data['price_min']);
    $max = intval($data['price_max']);

    if ($min && $max) {
        $price_html = ($min === $max)
            ? 'Rp ' . number_format($min, 0, ',', '.')
            : 'Rp ' . number_format($min, 0, ',', '.') . ' – Rp ' . number_format($max, 0, ',', '.');
    } elseif ($min && !$max) {
        $price_html = 'Rp ' . number_format($min, 0, ',', '.');
    }

    return $price_html;
}, 10, 2);

add_action('wp_footer', function () {
    if (!is_singular('courses') && !is_singular('tutor_course')) return;
?>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const courseId = <?php echo get_the_ID(); ?>;
            const restURL = `<?php echo rest_url('tpt/v1/get-topic-prices'); ?>?course_id=${courseId}`;

            // 🔹 Tambahkan loader kecil ke setiap header topic
            document.querySelectorAll('.tutor-accordion-item-header').forEach(header => {
                const loader = document.createElement('span');
                loader.className = 'tpt-topic-loader';
                loader.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i>`;
                loader.style.cssText = "color:#999;margin-left:10px;font-size:0.8rem;opacity:0.7;";
                header.appendChild(loader);
            });

            fetch(restURL)
                .then(res => res.json())
                .then(data => {
                    if (data.status_code !== 200 || !data.data?.topics) {
                        console.warn("⚠️ Tidak ada topic data dari REST API");
                        return;
                    }
                    const topics = data.data.topics;

                    document.querySelectorAll('.tutor-accordion-item').forEach(item => {
                        const header = item.querySelector('.tutor-accordion-item-header');
                        if (!header) return;

                        // hapus loader
                        const loader = header.querySelector('.tpt-topic-loader');
                        if (loader) loader.remove();

                        // ambil judul topic bersih (tanpa child element)
                        const headerClone = header.cloneNode(true);
                        headerClone.querySelectorAll('*').forEach(el => el.remove());
                        const topicTitle = headerClone.textContent.trim().toLowerCase();

                        // cari data topic di API
                        const topicData = topics.find(t => t.title.trim().toLowerCase() === topicTitle);
                        if (!topicData) {
                            console.log("⚠️ Tidak ketemu match topic untuk:", topicTitle);
                            return;
                        }

                        // 🔹 buat badge harga
                        const badge = document.createElement('span');
                        const hargaText = topicData.price > 0 ?
                            `Rp ${topicData.price.toLocaleString('id-ID')}` :
                            'Gratis';

                        badge.innerHTML = `<i class="fa-solid fa-coins" style="margin-right:4px;"></i>${hargaText}`;
                        badge.style.cssText = `
                    background:#ED2D56;
                    color:#fff;
                    padding:2px 8px;
                    border-radius:6px;
                    margin-left:8px;
                    font-size:0.8rem;
                    font-weight:600;
                    display:inline-flex;
                    align-items:center;
                    gap:4px;
                    animation: fadeIn 0.3s ease-in-out;
                `;
                        header.appendChild(badge);
                    });
                })
                .catch(err => {
                    console.error("❌ Gagal load harga topic:", err);
                });

            // animasi
            const style = document.createElement('style');
            style.textContent = `
        @keyframes fadeIn {
            from {opacity: 0; transform: scale(0.9);}
            to {opacity: 1; transform: scale(1);}
        }
    `;
            document.head.appendChild(style);
        });
    </script>
<?php
});
