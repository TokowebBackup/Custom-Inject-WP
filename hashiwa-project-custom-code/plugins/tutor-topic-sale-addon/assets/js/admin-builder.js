(function(){
  // Wait for DOM mutations — the Tutor builder is SPA-ish
  function onModalOpen(cb){
    var observer = new MutationObserver(function(mutations){
      mutations.forEach(function(m){
        if (m.addedNodes && m.addedNodes.length){
          m.addedNodes.forEach(function(node){
            if (node.nodeType === 1){
              // Detect topic edit modal by class names or data attributes
              if (node.querySelector && (node.querySelector('.tutor-modal') || node.classList.contains('tutor-modal') || node.querySelector('[data-tutor="topic-editor"]'))){
                cb(node);
              }
              // Also detect inline panels
              if (node.querySelector && node.querySelector('[data-field-key="topic_title"]')){
                cb(node);
              }
            }
          });
        }
      });
    });
    observer.observe(document.body, {childList:true, subtree:true});
  }

  function addPriceFieldToModal(modal){
    // Try to find a footer or actions area
    if (!modal) return;
    // Avoid duplicate
    if (modal.querySelector('.ttsa-price-row')) return;

    // Create field
    var wrapper = document.createElement('div');
    wrapper.className = 'ttsa-price-row';
    wrapper.style.marginTop = '12px';
    wrapper.innerHTML = '<label style="display:block;font-weight:600;margin-bottom:6px">Topic Price (TTSA)</label>' +
      '<input type="number" step="0.01" class="ttsa-price-input" placeholder="e.g. 9.99" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px" />' +
      '<div class="ttsa-price-save" style="margin-top:6px;display:flex;gap:8px">' +
      '<button class="ttsa-save-price button button-primary">Save Price</button>' +
      '<span class="ttsa-save-status" style="line-height:32px;margin-left:8px;color:#666"></span>' +
      '</div>';

    // Insert near modal content
    var target = modal.querySelector('.tutor-modal-body') || modal.querySelector('.tutor-field-wrap') || modal;
    if (target) target.appendChild(wrapper);

    // Prefill if topic id present
    var topicId = getTopicIdFromModal(modal);
    if (topicId){
      // fetch existing meta by reading inline data or REST if needed
      fetch('/wp-json/ttsa/v1/get_topic_price?topic_id='+topicId).then(function(){/*ignore*/}).catch(function(){/*ignore*/});
      // try read from DOM data attributes
      var existing = modal.querySelector('.ttsa-existing-price');
      if (existing) document.querySelector('.ttsa-price-input').value = existing.textContent || '';
    }

    // wire save button
    wrapper.querySelector('.ttsa-save-price').addEventListener('click', function(e){
      e.preventDefault();
      var price = wrapper.querySelector('.ttsa-price-input').value;
      if (price === '') { alert('Enter price'); return; }
      var t = getTopicIdFromModal(modal);
      if (!t){ alert('Cannot detect topic id'); return; }
      wrapper.querySelector('.ttsa-save-status').textContent = 'Saving...';
      fetch('/wp-json/ttsa/v1/save_topic_price', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': (typeof TTSA !== 'undefined' && TTSA.nonce) ? TTSA.nonce : ''
        },
        body: JSON.stringify({topic_id: t, price: price})
      }).then(function(r){ return r.json(); }).then(function(json){
        if (json && json.ok){
          wrapper.querySelector('.ttsa-save-status').textContent = 'Saved';
          setTimeout(function(){ wrapper.querySelector('.ttsa-save-status').textContent = ''; }, 2000);
        } else {
          wrapper.querySelector('.ttsa-save-status').textContent = 'Error';
        }
      }).catch(function(err){
        console.error(err);
        wrapper.querySelector('.ttsa-save-status').textContent = 'Error';
      });
    });
  }

  function getTopicIdFromModal(modal){
    // Try common places Tutor stores ids: data attributes, hidden inputs
    var input = modal.querySelector('input[name="topic_id"]') || modal.querySelector('input[data-topic-id]') || modal.querySelector('[data-topic-id]');
    if (input){
      var v = input.value || input.getAttribute('data-topic-id') || input.getAttribute('data-id');
      return v ? parseInt(v) : 0;
    }
    // try dataset on modal
    if (modal.dataset && modal.dataset.id) return parseInt(modal.dataset.id);
    // fallback: try to find edit link with topic id
    var editLink = modal.querySelector('a[href*="topic_id="]');
    if (editLink){
      var m = editLink.href.match(/topic_id=(\d+)/);
      if (m) return parseInt(m[1]);
    }
    return 0;
  }

  // Start observer
  document.addEventListener('DOMContentLoaded', function(){
    onModalOpen(function(modal){
      try{ addPriceFieldToModal(modal); }catch(e){console.error(e);}
    });
  });
})();
