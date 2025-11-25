document.addEventListener("DOMContentLoaded", () => {
    let renderTimeout;

    // 🔹 Buat input harga per topic
    const createPriceInput = (block, price = 0) => {
        if (block.querySelector(".tpt-topic-price")) return; // jangan double inject

        const titleInput = block.querySelector('input[name="title"]');
        if (!titleInput) return;

        const input = document.createElement("input");
        input.type = "number";
        input.placeholder = "Harga per topic (Rp)";
        input.className = "tpt-topic-price";
        input.value = price; // isi dengan harga existing

        // simpan topic_id supaya bisa inject ke form
        const topicId = block.dataset.topicId || null;
        if (topicId) input.dataset.topicId = topicId;

        Object.assign(input.style, {
            marginTop: "8px",
            width: "200px",
            fontSize: "13px",
            padding: "6px 12px",
            border: "1px solid #ccc",
            borderRadius: "8px",
            display: "block",
            transition: "all 0.2s ease",
        });
        titleInput.parentElement.appendChild(input);

        // Simpan saat user input
        input.addEventListener("change", () => handlePriceSave(block, input));
    };

    // 🔹 Handle simpan harga via AJAX
    const handlePriceSave = (block, input) => {
        const price = parseInt(input.value || 0);
        if (isNaN(price)) return;

        const titleWrapper = block.querySelector(".css-11s3wkf");
        if (!titleWrapper) return;

        let saveLoader = titleWrapper.querySelector(".tpt-badge-loader");
        if (!saveLoader) {
            saveLoader = document.createElement("span");
            saveLoader.className = "tpt-badge-loader";
            saveLoader.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i>`;
            Object.assign(saveLoader.style, {
                marginLeft: "10px",
                color: "#999",
                fontSize: "13px",
                opacity: "0.7",
            });
            titleWrapper.appendChild(saveLoader);
        }

        const urlParams = new URLSearchParams(window.location.search);
        const courseId = urlParams.get("course_id");
        const editableTitle = block.querySelector('input[name="title"]').value.trim();

        const waitForTopicId = (attempt = 0) => {
            if (attempt > 20) {
                saveLoader.remove();
                input.style.borderColor = "#f44336";
                input.style.boxShadow = "0 0 6px rgba(244,67,54,0.5)";
                return;
            }

            jQuery.post(TPT_Ajax.ajax_url, {
                action: "tpt_get_price",
                title: editableTitle,
                course_id: courseId,
                nonce: TPT_Ajax.nonce,
            }).done((resp) => {
                const topicId = resp.data?.topic_id || 0;
                if (topicId > 0) {
                    input.dataset.topicId = topicId; // set topic_id di input
                    jQuery.post(TPT_Ajax.ajax_url, {
                        action: "tpt_save_price",
                        title: editableTitle,
                        course_id: courseId,
                        price: price,
                        nonce: TPT_Ajax.nonce,
                    }, function (resp2) {
                        saveLoader.remove();
                        titleWrapper.querySelectorAll(".tpt-price-badge").forEach(el => el.remove());

                        if (resp2.success) {
                            const badge = document.createElement("span");
                            badge.className = "tpt-price-badge";
                            badge.innerHTML = `<i class="fa-solid fa-coins"></i> Rp ${price.toLocaleString()}`;
                            Object.assign(badge.style, {
                                marginLeft: "12px",
                                background: "#ED2D56",
                                color: "#fff",
                                fontSize: "14px",
                                padding: "6px 18px",
                                borderRadius: "20px",
                                fontWeight: "600",
                                boxShadow: "0 2px 8px rgba(237,45,86,0.4)",
                                display: "inline-flex",
                                alignItems: "center",
                                justifyContent: "center",
                                gap: "6px",
                                minWidth: "150px",
                            });
                            titleWrapper.appendChild(badge);
                        } else {
                            input.style.borderColor = "#f44336";
                            input.style.boxShadow = "0 0 6px rgba(244,67,54,0.5)";
                        }
                    });
                } else {
                    setTimeout(() => waitForTopicId(attempt + 1), 300);
                }
            });
        };

        waitForTopicId();
    };

    // 🔹 Render harga dan badge topic
    const renderTopicPrices = () => {
        clearTimeout(renderTimeout);
        renderTimeout = setTimeout(() => {
            const topicWrappers = document.querySelectorAll(".css-1g2ghqr, .css-rxenkd");
            if (!topicWrappers.length) return;

            topicWrappers.forEach(block => {
                if (block.dataset.tptRendered === "1") return;
                block.dataset.tptRendered = "1";

                const titleInput = block.querySelector('input[name="title"]');
                const titleTextEl = block.querySelector(".css-1jlm4v3");
                const topicTitle = titleTextEl ? titleTextEl.textContent.trim() : null;
                const editableTitle = titleInput ? titleInput.value.trim() : topicTitle;
                const titleWrapper = block.querySelector(".css-11s3wkf");
                if (!titleWrapper) return;

                const urlParams = new URLSearchParams(window.location.search);
                const courseId = urlParams.get("course_id");
                if (!courseId) return;

                // Loader & ambil harga existing
                if (!titleWrapper.querySelector(".tpt-badge-loader")) {
                    const loader = document.createElement("span");
                    loader.className = "tpt-badge-loader";
                    loader.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i>`;
                    Object.assign(loader.style, {
                        marginLeft: "10px",
                        color: "#999",
                        fontSize: "13px",
                        opacity: "0.7",
                    });
                    titleWrapper.appendChild(loader);
                }

                jQuery.post(TPT_Ajax.ajax_url, {
                    action: "tpt_get_price",
                    title: editableTitle,
                    course_id: courseId,
                    nonce: TPT_Ajax.nonce,
                }).done((resp) => {
                    titleWrapper.querySelectorAll(".tpt-badge-loader").forEach(el => el.remove());
                    titleWrapper.querySelectorAll(".tpt-price-badge").forEach(el => el.remove());

                    const price = parseInt(resp.data?.price || 0);

                    // Buat input dengan nilai existing
                    createPriceInput(block, price);

                    if (price > 0) {
                        const badge = document.createElement("span");
                        badge.className = "tpt-price-badge";
                        badge.innerHTML = `<i class="fa-solid fa-coins"></i> Rp ${price.toLocaleString()}`;
                        Object.assign(badge.style, {
                            marginLeft: "12px",
                            background: "#ED2D56",
                            color: "#fff",
                            fontSize: "14px",
                            padding: "6px 18px",
                            borderRadius: "20px",
                            fontWeight: "600",
                            boxShadow: "0 2px 8px rgba(237,45,86,0.4)",
                            display: "inline-flex",
                            alignItems: "center",
                            justifyContent: "center",
                            gap: "6px",
                            minWidth: "150px",
                        });
                        titleWrapper.appendChild(badge);
                    }
                });
            });
        }, 200);
    };

    // Observer untuk detect DOM update
    const observer = new MutationObserver(() => renderTopicPrices());
    observer.observe(document.querySelector("#wpbody-content") || document.body, {
        childList: true,
        subtree: true,
    });

    renderTopicPrices();

    // 🔹 Inject harga sebelum tombol "Update" React Tutor LMS
    // 🔹 Inject harga sebelum tombol "Update" React Tutor LMS
    const injectPricingBeforeReactUpdate = () => {
        const updateBtn = document.querySelector('button[data-cy="course-builder-submit-button"]');
        if (!updateBtn) return;

        updateBtn.addEventListener('click', () => {
            const courseForm = document.querySelector('form[data-course-id]');
            if (!courseForm) return;

            const topicInputs = Array.from(document.querySelectorAll('.tpt-topic-price'));
            if (!topicInputs.length) return;

            // hapus semua input lama
            courseForm.querySelectorAll('input[name^="pricing["]').forEach(el => el.remove());

            // 🔸 1️⃣ inject semua topic price (untuk plugin custom)
            topicInputs.forEach(input => {
                const topicId = input.dataset.topicId || null;
                if (!topicId) return;

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = `tpt_pricing[${topicId}]`;
                hidden.value = input.value || 0;
                courseForm.appendChild(hidden);
            });

            // 🔸 2️⃣ ambil harga topic pertama sebagai regular price utama
            const firstTopicPrice = parseInt(topicInputs[0].value || 0);
            if (!isNaN(firstTopicPrice) && firstTopicPrice > 0) {
                const mainPriceInput = document.createElement('input');
                mainPriceInput.type = 'hidden';
                mainPriceInput.name = 'pricing[regular_price]';
                mainPriceInput.value = firstTopicPrice;
                courseForm.appendChild(mainPriceInput);
            } else {
                // fallback ke 0 kalau kosong
                const dummy = document.createElement('input');
                dummy.type = 'hidden';
                dummy.name = 'pricing[regular_price]';
                dummy.value = 0;
                courseForm.appendChild(dummy);
            }

            console.log('✅ Injected pricing fields before submit');
        });
    };

    injectPricingBeforeReactUpdate();

});

// ==========================================
// 🩹 Patch Tutor LMS 3.9+ Axios Intercept
// Force tambahkan dummy pricing agar tidak error 422
// ==========================================
(function interceptTutorAxios() {
    const waitAxios = () => {
        if (typeof window.axios === "undefined" || !window.axios.interceptors) {
            return setTimeout(waitAxios, 1000);
        }
        if (window.__tptAxiosIntercepted) return;
        window.__tptAxiosIntercepted = true;

        window.axios.interceptors.request.use((config) => {
            try {
                if (config.url && config.url.includes("/wp-json/tutor/v1/courses/")) {
                    if (!config.data || !config.data.pricing) {
                        const firstTopicInput = document.querySelector(".tpt-topic-price");
                        const firstPrice = firstTopicInput
                            ? parseInt(firstTopicInput.value || 0)
                            : 0;

                        config.data = config.data || {};
                        config.data.pricing = {
                            regular_price: firstPrice > 0 ? firstPrice : 0,
                        };

                        console.log("💡 [Tutor Patch] Injected dummy pricing:", config.data.pricing);
                    }
                }
            } catch (err) {
                console.warn("⚠️ Tutor Axios intercept error:", err);
            }
            return config;
        });

        console.log("✅ Tutor LMS 3.9 Axios patch active");
    };

    waitAxios();
})();


console.log("🔥 [TPT] admin.js loaded");

// Tunggu tombol OK di modal edit topic
(function interceptTutorSaveTopic() {
    const observe = () => {
        const btns = document.querySelectorAll('button[data-cy="save-topic"], .css-u5asr1');
        if (!btns.length) return setTimeout(observe, 1000);

        btns.forEach(btn => {
            if (btn.dataset.tptBound === "1") return;
            btn.dataset.tptBound = "1";

            btn.addEventListener('click', () => {
                console.log("🟡 [TPT] Tombol OK diklik...");
                const modal = btn.closest('.css-oks3g7');
                if (!modal) {
                    console.warn("⚠️ [TPT] Modal tidak ditemukan.");
                    return;
                }

                // tunggu title input muncul
                const waitForTitle = (attempt = 0) => {
                    const titleInput = modal.querySelector('input[name="title"]');
                    const title = titleInput ? titleInput.value.trim() : null;

                    if (!title) {
                        if (attempt < 10) {
                            return setTimeout(() => waitForTitle(attempt + 1), 100);
                        }
                        console.warn("⚠️ [TPT] Gagal: title tidak ditemukan di modal (setelah 1s).");
                        return;
                    }

                    // cari input harga di DOM utama yang cocok dengan judul topic
                    const priceInput = [...document.querySelectorAll('.tpt-topic-price')].find(el => {
                        const topicBlock = el.closest('.css-1g2ghqr, .css-rxenkd');
                        const inputTitle = topicBlock?.querySelector('input[name="title"]')?.value.trim();
                        const labelTitle = topicBlock?.querySelector('.css-1jlm4v3')?.textContent.trim();
                        return inputTitle === title || labelTitle === title;
                    });

                    if (!priceInput) {
                        console.warn("⚠️ [TPT] Tidak menemukan input harga untuk:", title);
                        return;
                    }

                    const price = parseInt(priceInput.value || 0);
                    const urlParams = new URLSearchParams(window.location.search);
                    const course_id = urlParams.get("course_id");

                    if (!course_id) {
                        console.warn("⚠️ [TPT] course_id tidak ditemukan.");
                        return;
                    }

                    console.log("📦 [TPT] Mengirim AJAX ke server...", { title, price, course_id });

                    jQuery.post(TPT_Ajax.ajax_url, {
                        action: "tpt_save_price",
                        title,
                        course_id,
                        price,
                        nonce: TPT_Ajax.nonce
                    }).done(resp => {
                        if (resp.success) {
                            console.log("✅ Topic price saved & WC product created:", resp.data);

                            // 🧩 Tambahan Baru: Auto-select product ke tab Basics
                            const { wc_id, topic_id } = resp.data;
                            if (!wc_id || !topic_id) return;

                            // 🔹 hanya untuk topic pertama (paling awal)
                            jQuery.post(TPT_Ajax.ajax_url, {
                                action: "tpt_get_price",
                                title,
                                course_id,
                                nonce: TPT_Ajax.nonce
                            }).done(resp2 => {
                                if (resp2.success && parseInt(resp2.data.topic_id) === parseInt(topic_id)) {
                                    const waitForSelect = (attempt = 0) => {
                                        const input = document.querySelector('input[name="course_product_id"]');
                                        if (!input) {
                                            if (attempt < 20) return setTimeout(() => waitForSelect(attempt + 1), 300);
                                            return;
                                        }

                                        console.log("🔧 [TPT] Auto-select product:", wc_id);
                                        input.value = wc_id;

                                        // trigger event agar React Tutor mendeteksi perubahan
                                        input.dispatchEvent(new Event("input", { bubbles: true }));
                                        input.dispatchEvent(new Event("change", { bubbles: true }));

                                        // feedback visual
                                        input.style.borderColor = "#4CAF50";
                                        input.style.boxShadow = "0 0 6px rgba(76,175,80,0.5)";
                                        setTimeout(() => {
                                            input.style.borderColor = "";
                                            input.style.boxShadow = "";
                                        }, 1000);
                                    };
                                    waitForSelect();
                                }
                            });
                        } else {
                            console.warn("❌ Gagal simpan harga:", resp);
                        }
                    }).fail(err => {
                        console.error("⚠️ AJAX gagal:", err);
                    });
                };

                waitForTitle();
            });
        });
    };

    observe();
})();

// ==========================================
// ⚙️ Tutor LMS React Select Fix (auto-fill produk baru langsung ke input Pricing Model)
// ==========================================
(function tptSelectProductFix() {
    const observer = new MutationObserver(() => {
        const okBtns = document.querySelectorAll('button[data-cy="save-topic"]');
        okBtns.forEach(btn => {
            if (btn.dataset.tptHooked === "1") return;
            btn.dataset.tptHooked = "1";

            btn.addEventListener("click", async () => {
                console.log("🟡 [TPT] OK button clicked → auto-fill product input");

                const modal = btn.closest(".css-oks3g7, [role='dialog']");
                if (!modal) return;

                // ambil title topic dari modal
                let title = null;
                for (let i = 0; i < 20; i++) {
                    const t = modal.querySelector("input[name='title']");
                    if (t && t.value.trim()) {
                        title = t.value.trim();
                        break;
                    }
                    await new Promise(r => setTimeout(r, 100));
                }
                if (!title) return console.warn("⚠️ [TPT] Tidak menemukan title topic di modal");

                // ambil price
                const priceInput = [...document.querySelectorAll(".tpt-topic-price")].find(el => {
                    const block = el.closest(".css-1g2ghqr, .css-rxenkd");
                    const titleEl = block?.querySelector("input[name='title']")?.value.trim() || "";
                    const labelEl = block?.querySelector(".css-1jlm4v3")?.textContent.trim() || "";
                    return titleEl === title || labelEl === title;
                });
                if (!priceInput) return;

                const price = parseInt(priceInput.value || 0);
                const urlParams = new URLSearchParams(window.location.search);
                const course_id = urlParams.get("course_id");
                if (!course_id) return;

                // kirim AJAX ke server
                jQuery.post(TPT_Ajax.ajax_url, {
                    action: "tpt_save_price",
                    title,
                    course_id,
                    price,
                    nonce: TPT_Ajax.nonce,
                }).done(async (resp) => {
                    if (!resp.success) return console.warn("❌ Gagal simpan harga", resp);
                    const { wc_id } = resp.data;
                    if (!wc_id) return;

                    console.log("✅ Topic price saved & WC product created:", resp.data);

                    // pastikan tab Basics terbuka
                    const basicsTabBtn = [...document.querySelectorAll("button, a")]
                        .find(el => /Basics/i.test(el.textContent || ""));
                    if (basicsTabBtn) {
                        basicsTabBtn.click();
                        console.log("📂 [TPT] Tab 'Basics' dibuka otomatis");
                        await new Promise(r => setTimeout(r, 1000));
                    }

                    // cari input Pricing Model
                    let productInput = null;
                    for (let i = 0; i < 30; i++) {
                        productInput = document.querySelector("input[name='course_product_id'][data-select='true']");
                        if (productInput) break;
                        await new Promise(r => setTimeout(r, 300));
                    }

                    if (!productInput) {
                        console.warn("⚠️ [TPT] Input product belum ditemukan setelah 9 detik");
                        return;
                    }

                    // langsung isi value
                    // gunakan properti setter asli agar React detect
                    const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value").set;
                    nativeInputValueSetter.call(productInput, title);

                    // kirim event seperti input user asli
                    const inputEvent = new Event("input", { bubbles: true });
                    productInput.dispatchEvent(inputEvent);

                    console.log("🧩 [TPT] Input course_product_id diset (React-safe):", title);

                    // efek visual
                    productInput.style.borderColor = "#4CAF50";
                    productInput.style.boxShadow = "0 0 6px rgba(76,175,80,0.5)";
                    setTimeout(() => {
                        productInput.style.borderColor = "";
                        productInput.style.boxShadow = "";
                    }, 1000);

                });
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
})();

