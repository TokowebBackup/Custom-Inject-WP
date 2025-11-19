document.addEventListener("DOMContentLoaded", () => {
    // Selector course card - sesuaikan theme / layout
    const courseCards = document.querySelectorAll('.tutor-course-card');

    if (!courseCards.length) return;

    courseCards.forEach(card => {
        const courseId = card.dataset.courseId; // pastikan ada data-course-id
        const titleEl = card.querySelector('.tutor-course-title'); // judul course

        if (!courseId || !titleEl) return;

        const courseTitle = titleEl.textContent.trim();

        // Ambil harga topik pertama via REST API
        fetch(`${TPT_Ajax.resturl}get-price?title=${encodeURIComponent(courseTitle)}&course_id=${courseId}`, {
            headers: { 'X-WP-Nonce': TPT_Ajax.nonce }
        })
            .then(r => r.json())
            .then(data => {
                if (data?.price && data.price > 0) {
                    const badge = document.createElement('span');
                    badge.className = 'tpt-price-badge';
                    badge.textContent = `Rp ${Number(data.price).toLocaleString()}`;
                    badge.style.cssText = `
                        margin-left:10px;
                        font-size:13px;
                        background:#ED2D56;
                        color:#fff;
                        padding:2px 8px;
                        border-radius:8px;
                        font-weight:600;
                        display:inline-block;
                        vertical-align:middle;
                    `;
                    titleEl.appendChild(badge);
                }
            })
            .catch(err => console.warn("Fetch error (get-price):", err));
    });

    // 🔹 Observer untuk update live jika ada perubahan DOM (misal Ajax load)
    const observer = new MutationObserver(() => {
        courseCards.forEach(card => {
            const titleEl = card.querySelector('.tutor-course-title');
            if (!titleEl || titleEl.querySelector('.tpt-price-badge')) return;

            const courseId = card.dataset.courseId;
            const courseTitle = titleEl.textContent.trim();

            fetch(`${TPT_Ajax.resturl}get-price?title=${encodeURIComponent(courseTitle)}&course_id=${courseId}`, {
                headers: { 'X-WP-Nonce': TPT_Ajax.nonce }
            })
                .then(r => r.json())
                .then(data => {
                    if (data?.price && data.price > 0) {
                        const badge = document.createElement('span');
                        badge.className = 'tpt-price-badge';
                        badge.textContent = `Rp ${Number(data.price).toLocaleString()}`;
                        badge.style.cssText = `
                            margin-left:10px;
                            font-size:13px;
                            background:#ED2D56;
                            color:#fff;
                            padding:2px 8px;
                            border-radius:8px;
                            font-weight:600;
                            display:inline-block;
                            vertical-align:middle;
                        `;
                        titleEl.appendChild(badge);
                    }
                })
                .catch(err => console.warn("Fetch error (get-price):", err));
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
});
