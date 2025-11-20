document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('course_id');
    if (!courseId) return;

    const topicFetchedIds = new WeakSet(); // tandai topic yang sudah fetch ID
    let renderTimeout;

    // 🔹 Tambahkan overlay loading
    const showLoading = () => {
        let overlay = document.querySelector('#tpt-loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'tpt-loading-overlay';
            overlay.style.cssText = `
                position: fixed;top:0;left:0;width:100%;height:100%;
                background: rgba(0,0,0,0.2);z-index:9999;
                display:flex;align-items:center;justify-content:center;
            `;
            overlay.innerHTML = `<div style="padding:10px 20px;background:#fff;border-radius:10px;font-weight:600;">Loading...</div>`;
            document.body.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    };
    const hideLoading = () => {
        const overlay = document.querySelector('#tpt-loading-overlay');
        if (overlay) overlay.style.display = 'none';
    };

    // 🔹 Fetch harga dan render badge
    const renderAllTopicPrices = () => {
        clearTimeout(renderTimeout);
        renderTimeout = setTimeout(() => {
            showLoading();
            const promises = Array.from(document.querySelectorAll('.css-1jlm4v3')).map(titleEl => {
                if (titleEl.dataset.priceBadgeAttached) return Promise.resolve();

                const topicTitle = titleEl.textContent.trim().replace(/Rp \d+(?:,\d{3})*$/, '').trim();
                if (!topicTitle) return Promise.resolve();

                return fetch(`${TPT_Ajax.resturl}get-price?title=${encodeURIComponent(topicTitle)}&course_id=${courseId}`, {
                    headers: { 'X-WP-Nonce': TPT_Ajax.nonce }
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data?.price && data.price > 0) {
                            const badge = document.createElement('span');
                            badge.className = 'tpt-price-badge';
                            badge.textContent = `Rp ${Number(data.price).toLocaleString()}`;
                            badge.style.cssText = `
                            margin-left:10px;font-size:13px;background:#ED2D56;color:#fff;
                            padding:2px 8px;border-radius:8px;font-weight:600;
                            display:inline-block;vertical-align:middle;
                        `;
                            titleEl.appendChild(badge);
                            titleEl.dataset.priceBadgeAttached = true;
                        }
                    })
                    .catch(err => console.warn("Fetch error (get-price):", err));
            });

            Promise.all(promises).finally(() => hideLoading());
        }, 500);
    };

    // 🔹 Attach input editor modal
    const attachTopicEditor = () => {
        document.querySelectorAll('.css-oks3g7').forEach(topicEl => {
            if (topicEl.dataset.editorAttached) return;

            const input = document.createElement('input');
            input.type = 'number';
            input.placeholder = 'Masukkan harga topik (Rp)';
            input.className = 'tutor-input-field tutor-topic-price';
            input.style.cssText = 'margin-top:10px;width:100%;border:1px solid #ddd;padding:6px;border-radius:8px;';

            const wrapper = topicEl.querySelector('.css-15gb5bw');
            if (wrapper) wrapper.after(input);

            const titleInput = topicEl.querySelector('input[name="title"]');
            if (titleInput?.value) {
                fetch(`${TPT_Ajax.resturl}get-price?title=${encodeURIComponent(titleInput.value)}&course_id=${courseId}`, {
                    headers: { 'X-WP-Nonce': TPT_Ajax.nonce }
                })
                    .then(r => r.json())
                    .then(data => { if (data?.price) input.value = data.price; });
            }

            const saveBtn = topicEl.querySelector('button[data-cy="save-topic"]');
            if (saveBtn && !saveBtn.dataset.tptBound) {
                saveBtn.dataset.tptBound = true;
                saveBtn.addEventListener('click', () => {
                    const title = titleInput?.value?.trim();
                    const price = input.value;
                    const accordionItem = Array.from(document.querySelectorAll('[role="button"]')).find(item => {
                        const itemTitle = item.querySelector('.css-1jlm4v3')?.textContent?.replace(/Rp \d+(?:,\d{3})*/, '').trim();
                        return itemTitle === title;
                    });
                    const topicId = accordionItem?.dataset.lessonId;
                    if (!title || !courseId || !topicId) {
                        console.warn('❌ Data incomplete, fetch dibatalkan');
                        return;
                    }

                    fetch(`${TPT_Ajax.resturl}save-price`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': TPT_Ajax.nonce },
                        body: JSON.stringify({ title, price, course_id: courseId, topic_id: topicId })
                    })
                        .then(r => r.json())
                        .then(resp => {
                            console.log('💾 Saved:', resp);
                            if (accordionItem) {
                                const titleEl = accordionItem.querySelector('.css-1jlm4v3');
                                if (titleEl) {
                                    titleEl.dataset.priceBadgeAttached = false;
                                    renderAllTopicPrices();
                                }
                            }
                        })
                        .catch(err => console.error('REST Error:', err));
                });
            }

            topicEl.dataset.editorAttached = true;
        });
    };

    // 🔹 Fetch topic ID (aman, parse JSON manual)
    const fetchTopicIds = () => {
        document.querySelectorAll('.css-1jlm4v3').forEach(titleEl => {
            if (topicFetchedIds.has(titleEl)) return;

            const title = titleEl.textContent.replace(/Rp \d+(?:,\d{3})*/, '').trim();
            fetch(`${TPT_Ajax.resturl}get-topic-id?title=${encodeURIComponent(title)}`, {
                headers: { 'X-WP-Nonce': TPT_Ajax.nonce }
            })
                .then(res => res.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        const accordionItem = titleEl.closest('[role="button"]');
                        if (accordionItem) accordionItem.dataset.lessonId = data.topic_id;
                        topicFetchedIds.add(titleEl);
                    } catch (e) {
                        console.warn("⚠️ Tidak bisa parse JSON:", text);
                    }
                })
                .catch(err => console.error("Fetch topic ID error:", err));
        });
    };

    // 🔹 Observer utama
    const observer = new MutationObserver(() => {
        attachTopicEditor();
        renderAllTopicPrices();
        fetchTopicIds();
    });
    observer.observe(document.body, { childList: true, subtree: true });

    // 🔹 Initial run
    setTimeout(() => {
        attachTopicEditor();
        renderAllTopicPrices();
        fetchTopicIds();
    }, 1500);
});
