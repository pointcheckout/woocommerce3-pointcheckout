

jQuery('form.checkout').on('submit', function (e){
    e.preventDefault();
    e.stopImmediatePropagation();
    var paymentMethod = jQuery('input[name=payment_method]:checked').val();
    if("pointcheckout_pay" === paymentMethod ) {
        return pointcheckoutFormHandler(jQuery(this));
    }
});


function showError(form, data) {
    // Remove notices from all sources
    jQuery( '.woocommerce-error, .woocommerce-message' ).remove();

    // Add new errors returned by this event
    if ( data.messages ) {
            form.prepend( '<div class="woocommerce-NoticeGroup-updateOrderReview">' + data.messages + '</div>' );
    } else {
            form.prepend( data );
    }

    // Lose focus for all fields
    form.find( '.input-text, select, input:checkbox' ).blur();

    // Scroll to top
    jQuery( 'html, body' ).animate( {
            scrollTop: ( jQuery( form ).offset().top - 100 )
    }, 1000 );
}

function pointcheckoutFormHandler(form) {
    if (form.is(".processing")) return !1;
    initPointCheckoutPayment(form);
}


function initPointCheckoutPayment(form) {
	var data = jQuery(form).serialize();
    var ajaxUrl = wc_checkout_params.checkout_url;
    jQuery.ajax({
        'url': ajaxUrl,
        'type': 'POST',
        'dataType': 'json',
        'data': data,
        'async': false
    }).complete(function (response) {
        data = '';
        if(response.form) {
            data = response;
        }
        else{
            var code = response.responseText;
            var newstring = code.replace(/<script[^>]*>(.*)<\/script>/, "");
            if (newstring.indexOf("<!--WC_START-->") >= 0) {
                    newstring = newstring.split("<!--WC_START-->")[1];
            }
            if (newstring.indexOf("<!--WC_END-->") >= 0) {
                    newstring = newstring.split("<!--WC_END-->")[0];
            }
            try {
                data = jQuery.parseJSON( newstring );
            }
            catch(e) {}
        }
        if(data.result == 'failure') {
            showError(form, data);
            return !1;
        }
        if (data.form) {
            jQuery('#frm_pointcheckout_payment').remove();
            jQuery('body').append(data.form);
            window.success = true;
            jQuery( "#frm_pointcheckout_payment" ).submit();
        }
    });
}