jQuery(document).ready(function($) {
    console.log('BST Version Bumper script loaded');
    console.log('bst_version_ajax object:', typeof bst_version_ajax !== 'undefined' ? bst_version_ajax : 'UNDEFINED');
    
    $('#bst-bump-versions').on('click', function() {
        console.log('Bump versions button clicked');
        var button = $(this);
        var status = $('#bst-version-status');
        
        // Check if AJAX variables are available
        if (typeof bst_version_ajax === 'undefined') {
            status.html('<span style="color: #d63384; font-weight: bold;">✗ Error: AJAX variables not loaded</span>');
            return;
        }
        
        // Disable button and show loading
        button.prop('disabled', true).text('Bumping Versions...');
        status.html('<span style="color: #0073aa;">Processing...</span>');
        
        console.log('Sending AJAX request to:', bst_version_ajax.ajax_url);
        
        $.ajax({
            url: bst_version_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bst_bump_versions',
                nonce: bst_version_ajax.nonce
            },
            success: function(response) {
                console.log('AJAX success response:', response);
                if (response.success) {
                    status.html('<span style="color: #00a32a; font-weight: bold;">✓ ' + response.data.message + '</span>');
                    
                    // Show updated versions
                    if (response.data.updated_versions && response.data.updated_versions.length > 0) {
                        var versionList = '<br><strong>Updated Versions:</strong><br>';
                        response.data.updated_versions.forEach(function(version) {
                            versionList += '• ' + version.old + ' → <strong>' + version.new + '</strong><br>';
                        });
                        status.append(versionList);
                    }
                    
                    // Reload page after 3 seconds to show new versions
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    status.html('<span style="color: #d63384; font-weight: bold;">✗ Error: ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', xhr, status, error);
                status.html('<span style="color: #d63384; font-weight: bold;">✗ AJAX Error: ' + error + '</span>');
            },
            complete: function() {
                // Re-enable button
                button.prop('disabled', false).text('Bump Child Theme Versions');
            }
        });
    });
});
