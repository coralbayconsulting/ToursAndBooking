jQuery(document).ready(function($) {
    'use strict';
    
    // Custom tooltip functionality for star ratings
    function initCustomTooltips() {
        const tooltipElements = $('[data-tooltip]');
        let tooltip = null;
        
        function createTooltip() {
            if (!tooltip) {
                tooltip = $('<div class="custom-tooltip"></div>').appendTo('body');
            }
            return tooltip;
        }
        
        function showTooltip(element, content) {
            const $element = $(element);
            const tooltipEl = createTooltip();
            
            // Simply set the HTML content directly
            tooltipEl.html(content);
            
            // Position tooltip
            const elementOffset = $element.offset();
            const elementWidth = $element.outerWidth();
            const elementHeight = $element.outerHeight();
            const tooltipWidth = tooltipEl.outerWidth();
            const tooltipHeight = tooltipEl.outerHeight();
            
            // Calculate position - try to center above, but adjust if it goes off screen
            let leftPos = elementOffset.left + (elementWidth / 2) - (tooltipWidth / 2);
            let topPos = elementOffset.top - tooltipHeight - 10;
            
            // Prevent tooltip from going off the left edge
            if (leftPos < 10) {
                leftPos = 10;
            }
            
            // Prevent tooltip from going off the right edge
            const windowWidth = $(window).width();
            if (leftPos + tooltipWidth > windowWidth - 10) {
                leftPos = windowWidth - tooltipWidth - 10;
            }
            
            // If tooltip would appear above the viewport, show it below instead
            if (topPos < $(window).scrollTop() + 10) {
                topPos = elementOffset.top + elementHeight + 10;
                // Adjust arrow position for bottom positioning
                tooltipEl.addClass('tooltip-below');
            } else {
                tooltipEl.removeClass('tooltip-below');
            }
            
            tooltipEl.css({
                left: leftPos,
                top: topPos
            });
            
            // Show tooltip with animation
            tooltipEl.addClass('show');
        }
        
        function hideTooltip() {
            if (tooltip) {
                tooltip.removeClass('show tooltip-below');
            }
        }
        
        // Bind events (unbind first to prevent duplicates)
        tooltipElements.off('mouseenter mouseleave click');
        
        // Click-based tooltips (mobile and desktop friendly)
        tooltipElements.on('click', function(e) {
            e.preventDefault();
            const content = $(this).attr('data-tooltip');
            
            // Check if this tooltip is already showing
            const isCurrentlyShowing = $(this).hasClass('tooltip-active');
            
            // Hide all other tooltips first
            $('.tooltip-active').removeClass('tooltip-active');
            hideTooltip();
            
            // If this one wasn't showing, show it
            if (!isCurrentlyShowing && content) {
                $(this).addClass('tooltip-active');
                showTooltip(this, content);
            }
        });
        
        // Optional: Still support hover on desktop for better UX
        tooltipElements.on('mouseenter', function() {
            // Only trigger hover if not on mobile/touch device
            if (!('ontouchstart' in window)) {
                const content = $(this).attr('data-tooltip');
                if (content && !$(this).hasClass('tooltip-active')) {
                    showTooltip(this, content);
                }
            }
        });
        
        tooltipElements.on('mouseleave', function() {
            // Only hide on mouse leave if not actively clicked
            if (!('ontouchstart' in window) && !$(this).hasClass('tooltip-active')) {
                hideTooltip();
            }
        });
        
        // Hide tooltip on scroll, resize, or click outside
        $(window).on('scroll resize', function() {
            hideTooltip();
            $('.tooltip-active').removeClass('tooltip-active');
        });
        
        // Hide tooltip when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('[data-tooltip]').length) {
                hideTooltip();
                $('.tooltip-active').removeClass('tooltip-active');
            }
        });
    }
    
    // Initialize tooltips
    initCustomTooltips();
    
    // Expose function globally for manual re-initialization
    window.initCustomTooltips = initCustomTooltips;
    
    // Re-initialize tooltips after AJAX content loads (if applicable)
    $(document).ajaxComplete(function() {
        setTimeout(initCustomTooltips, 100);
    });
});
