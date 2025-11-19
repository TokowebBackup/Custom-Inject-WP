jQuery(function($){
    $(document).on('click', '.ttsa-buy-topic', function(e){
        e.preventDefault();
        var $a = $(this);
        var topic_id = $a.data('topic-id');
        var product_id = $a.data('product-id');
        if (!topic_id || !product_id) return alert('Invalid product');
        $a.prop('disabled', true).text('Adding...');
        $.post(TTSA.ajax_url, {
            action: 'ttsa_add_to_cart',
            nonce: TTSA.nonce,
            topic_id: topic_id,
            product_id: product_id
        }, function(resp){
            if (resp.success && resp.data.redirect){
                window.location = resp.data.redirect;
            } else {
                alert(resp.data && resp.data.message ? resp.data.message : 'Error');
                $a.prop('disabled', false).text('Buy this topic');
            }
        }, 'json').fail(function(){ alert('Request failed'); $a.prop('disabled', false).text('Buy this topic'); });
    });
});
