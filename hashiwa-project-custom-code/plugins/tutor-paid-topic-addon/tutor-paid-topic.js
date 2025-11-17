document.addEventListener("DOMContentLoaded", () => {

    // 🧩 Fungsi untuk menambahkan badge harga ke semua judul topik
    const renderAllTopicPrices = () => {
        document.querySelectorAll('.css-1uvctym').forEach(topic => {
            const titleEl = topic.querySelector('.css-1jlm4v3');
            if (!titleEl || topic.dataset.tptRendered) return;
            topic.dataset.tptRendered = true;

            const topicTitle = titleEl.textContent.trim();
            if (!topicTitle) return;

            // Ambil harga via REST API
            fetch(`${TPT_Ajax.resturl}get-price?title=${encodeURIComponent(topicTitle)}`, {
                headers: { 'X-WP-Nonce': TPT_Ajax.nonce }
            })
                .then(res => res.json())
                .then(data => {
                    if (data?.price && data.price > 0) {
                        const badge = document.createElement('span');
                        badge.className = 'tpt-price-badge';
                        badge.textContent = `Rp ${Number(data.price).toLocaleString()}`;
                        badge.style.marginLeft = '10px';
                        badge.style.fontSize = '13px';
                        badge.style.background = '#ED2D56';
                        badge.style.color = '#fff';
                        badge.style.padding = '2px 8px';
                        badge.style.borderRadius = '8px';
                        badge.style.fontWeight = '600';
                        badge.style.display = 'inline-block';
                        badge.style.verticalAlign = 'middle';
                        titleEl.appendChild(badge);
                    }
                });
        });
    };

    // 🧭 MutationObserver: pantau munculnya elemen edit modal & daftar topik
    const observer = new MutationObserver(() => {
        // ✳️ Tambah input harga di modal editor
        document.querySelectorAll('.css-oks3g7').forEach(topicEl => {
            if (topicEl.querySelector('.tutor-topic-price')) return;

            const input = document.createElement('input');
            input.type = 'number';
            input.placeholder = 'Masukkan harga topik (Rp)';
            input.className = 'tutor-input-field tutor-topic-price';
            input.style.marginTop = '10px';
            input.style.width = '100%';
            input.style.border = '1px solid #ddd';
            input.style.padding = '6px';
            input.style.borderRadius = '8px';

            const wrapper = topicEl.querySelector('.css-15gb5bw');
            if (wrapper) wrapper.after(input);

            const titleInput = topicEl.querySelector('input[name="title"]');

            // Ambil harga lama
            if (titleInput && titleInput.value) {
                fetch(`${TPT_Ajax.resturl}get-price?title=${encodeURIComponent(titleInput.value)}`, {
                    headers: { 'X-WP-Nonce': TPT_Ajax.nonce }
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data?.price) input.value = data.price;
                    });
            }

            // Simpan harga saat klik OK
            const saveBtn = topicEl.querySelector('button[data-cy="save-topic"]');
            if (saveBtn && !saveBtn.dataset.tptBound) {
                saveBtn.dataset.tptBound = true;
                saveBtn.addEventListener('click', () => {
                    const title = titleInput.value;
                    const price = input.value;
                    if (!title) return;

                    fetch(`${TPT_Ajax.resturl}save-price`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': TPT_Ajax.nonce
                        },
                        body: JSON.stringify({ title, price })
                    })
                        .then(res => res.json())
                        .then(resp => {
                            console.log('Tutor Paid Topic:', resp.message || 'Saved');
                            // render ulang daftar harga setelah disimpan
                            setTimeout(() => {
                                document.querySelectorAll('.tpt-price-badge').forEach(b => b.remove());
                                renderAllTopicPrices();
                            }, 1000);
                        });
                });
            }
        });

        // 🔁 Pastikan badge muncul juga di daftar utama Curriculum
        renderAllTopicPrices();
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Render awal (jaga-jaga observer belum jalan)
    setTimeout(renderAllTopicPrices, 1500);
});
