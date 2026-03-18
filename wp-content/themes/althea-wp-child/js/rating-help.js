jQuery(document).ready(function($) {
    
    // Open rating help popup
    $('#rating-help-btn').on('click', function(e) {
        e.preventDefault();
        $('#rating-help-popup').show().css({
            'display': 'flex !important',
            'z-index': '999999999 !important',
            'position': 'fixed !important'
        });
        $('body').addClass('rating-help-open').css('overflow', 'hidden');
    });
    
    // Close rating help popup - close button
    $('#rating-help-close').on('click', function(e) {
        e.preventDefault();
        console.log('Close button clicked');
        closeRatingHelp();
    });
    
    // Close rating help popup - backdrop click
    $('.rating-help-backdrop').on('click', function(e) {
        console.log('Backdrop clicked');
        closeRatingHelp();
    });
    
    // Close rating help popup - ESC key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('#rating-help-popup').is(':visible')) {
            console.log('ESC key pressed');
            closeRatingHelp();
        }
    });
    
    // Function to close rating help popup
    function closeRatingHelp() {
        $('#rating-help-popup').hide();
        $('body').removeClass('rating-help-open').css('overflow', '');
    }
    
    // Prevent popup content clicks from closing the popup
    $('.rating-help-content').on('click', function(e) {
        e.stopPropagation();
    });
});
