document.addEventListener("DOMContentLoaded", () => {
    // 🔹 Fungsi untuk update badge harga
    const updatePriceBadge = (card) => {
        const titleEl = card.querySelector('.tutor-course-title');
        if (!titleEl || titleEl.querySelector('.tpt-price-badge')) return;

        // Ambil topic_id dari class card
        const topicClass = card.className.match(/tutor-course-topic-(\d+)/);
        const topicId = topicClass ? topicClass[1] : null;
        if (!topicId) return;

        fetch(`${TPT_Ajax.resturl}get-price?topic_id=${topicId}`, {
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
    };

    // 🔹 Initial load
    const courseCards = document.querySelectorAll('.tutor-course-card');
    courseCards.forEach(updatePriceBadge);

    // 🔹 Observer untuk live update jika ada card baru dimuat via Ajax
    const observer = new MutationObserver(() => {
        const cards = document.querySelectorAll('.tutor-course-card');
        cards.forEach(updatePriceBadge);
    });

    observer.observe(document.body, { childList: true, subtree: true });
});
