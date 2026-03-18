jQuery(document).ready(function($) {
    'use strict';
    
    // Rating help popup functionality
    function initRatingHelp() {
        const helpBtn = $('#rating-help-btn');
        const popup = $('#rating-help-popup');
        const closeBtn = $('#rating-help-close');
        const backdrop = $('.rating-help-backdrop');
        
        // Show popup
        helpBtn.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            popup.fadeIn(300);
            $('body').addClass('rating-help-open');
        });
        
        // Hide popup when clicking close button
        closeBtn.on('click', function(e) {
            e.preventDefault();
            hidePopup();
        });
        
        // Hide popup when clicking backdrop
        backdrop.on('click', function(e) {
            if (e.target === this) {
                hidePopup();
            }
        });
        
        // Hide popup on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && popup.is(':visible')) {
                hidePopup();
            }
        });
        
        function hidePopup() {
            popup.fadeOut(300);
            $('body').removeClass('rating-help-open');
        }
    }
    
    // Initialize rating help
    initRatingHelp();
});

// Prevent body scroll when popup is open
jQuery(document).ready(function($) {
    const style = $('<style>').text(`
        body.rating-help-open {
            overflow: hidden;
        }
    `).appendTo('head');
});
