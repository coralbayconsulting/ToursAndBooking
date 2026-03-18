jQuery(document).ready(function($) {
    $('#tour-booking-form').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        $.post(bst_ajax_params.ajax_url, form.serialize(), function(response) {
            if (response.success) {
                alert(response.data.message);
                // After save, redirect to view mode for this booking
                var id = form.find('[name="id"]').val();
                window.location.href = bst_ajax_params.view_url_base + id;
            } else {
                alert('Error saving tour booking.');
            }
        });
    });

    // Delete button in edit mode
    $('#tour-booking-form .button-danger').click(function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this booking?')) return;
        var id = $('#tour-booking-form [name="id"]').val();
        $.post(bst_ajax_params.ajax_url, { action: 'delete_tour_booking', id: id }, function(response) {
            if (response.success) {
                alert(response.data.message);
                window.location.href = bst_ajax_params.list_url;
            } else {
                alert('Error deleting tour booking.');
            }
        });
    });
});