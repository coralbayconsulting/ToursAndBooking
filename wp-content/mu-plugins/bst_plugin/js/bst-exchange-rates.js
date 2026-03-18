jQuery(document).ready(function ($) {
    $('#bst_update_exchange_rates').on('click', function (e) {
        e.preventDefault();
        
        // Show loading state
        $(this).prop('disabled', true).val('Updating...');
        
        $.post(ajaxurl, { action: 'bst_update_exchange_rates', nonce: (typeof bstExchangeRates !== 'undefined' && bstExchangeRates.nonce) ? bstExchangeRates.nonce : '' }, function (response) {
            if (response.success) {
                // Update EUR-based rates
                $('#bst_eur_usd').val(response.data.eur_usd);
                $('#bst_eur_cad').val(response.data.eur_cad);
                $('#bst_eur_aud').val(response.data.eur_aud);
                $('#bst_eur_gbp').val(response.data.eur_gbp);
                $('#bst_eur_nzd').val(response.data.eur_nzd);
                $('#bst_eur_jpy').val(response.data.eur_jpy);
                $('#bst_eur_zar').val(response.data.eur_zar);
                
                // Update USD-based rates
                $('#bst_usd_eur').val(response.data.usd_eur);
                $('#bst_usd_cad').val(response.data.usd_cad);
                $('#bst_usd_aud').val(response.data.usd_aud);
                $('#bst_usd_gbp').val(response.data.usd_gbp);
                $('#bst_usd_nzd').val(response.data.usd_nzd);
                $('#bst_usd_jpy').val(response.data.usd_jpy);
                $('#bst_usd_zar').val(response.data.usd_zar);
                
                $('#bst_last_updated').val(response.data.last_updated);
                
                // Show success message
                var successMsg = $('<div class="notice notice-success is-dismissible"><p><strong>Exchange rates updated successfully!</strong> Both EUR and USD-based rates have been refreshed.</p></div>');
                $('.wrap h1').after(successMsg);
                
                // Auto-dismiss success message after 5 seconds
                setTimeout(function() {
                    successMsg.fadeOut();
                }, 5000);
                
            } else {
                alert('Failed to update exchange rates: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            alert('Failed to update exchange rates: Network error');
        }).always(function() {
            // Reset button state
            $('#bst_update_exchange_rates').prop('disabled', false).val('Update Exchange Rates');
        });
    });
});