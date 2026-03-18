/**
 * Tour Bookings Admin JavaScript
 * 
 * This file provides additional JavaScript functionality for the tour bookings admin interface.
 * Most of the form handling JavaScript is inline in the templates for better maintainability.
 */

// Cache for source code titles to avoid repeated AJAX calls
var sourceCodeCache = {};

/**
 * Get source code title by code via AJAX
 * @param {string} sourceCode - The source code to look up
 * @param {function} callback - Callback function to receive the title
 */
function getSourceCodeTitle(sourceCode, callback) {
    if (!sourceCode) {
        callback('');
        return;
    }
    
    // Check cache first
    if (sourceCodeCache[sourceCode]) {
        callback(sourceCodeCache[sourceCode]);
        return;
    }
    
    // Make AJAX call - use the same nonce pattern as other calls in the page
    jQuery.ajax({
        url: window.ajaxurl,
        type: 'POST',
        data: {
            action: 'bst_get_source_code_title',
            source_code: sourceCode,
            nonce: jQuery('input[name="bst_tour_bookings_nonce"]').val() || window.bstTourBookingsNonce || ''
        },
        success: function(response) {
            if (response.success) {
                sourceCodeCache[sourceCode] = response.data;
                callback(response.data);
            } else {
                callback('');
            }
        },
        error: function() {
            callback('');
        }
    });
}

/**
 * Format source display with title and code
 * @param {string} sourceCode - The source code
 * @param {string} title - The source title (optional)
 * @returns {string} Formatted display string
 */
function formatSourceDisplay(sourceCode, title) {
    if (!sourceCode) return '';
    if (!title || title === '') return sourceCode;
    return title + ' (' + sourceCode + ')';
}

console.log('Tour Bookings Admin JS loaded');
