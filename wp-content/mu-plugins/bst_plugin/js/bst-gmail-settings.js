jQuery(document).ready(function($) {
    
    // Upload Gmail credentials file
    $('#bst_upload_gmail_credentials').on('click', function() {
        const fileInput = $('#bst_gmail_credentials_file')[0];
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Please select a credentials file first.');
            return;
        }
        
        if (!file.name.endsWith('.json')) {
            alert('Please select a valid JSON credentials file.');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'bst_upload_gmail_credentials');
        formData.append('credentials_file', file);
        formData.append('nonce', bst_gmail_ajax.nonce);
        
        const $result = $('#bst_gmail_upload_result');
        $result.html('<p style="color: blue;">Uploading credentials...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $result.html('<p style="color: green;"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</p>');
                    // Refresh page to update status
                    setTimeout(() => location.reload(), 1000);
                } else {
                    $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</p>');
                }
            },
            error: function() {
                $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> Upload failed. Please try again.</p>');
            }
        });
    });
    
    // Authorize Gmail access
    $('#bst_gmail_authorize').on('click', function() {
        const $result = $('#bst_gmail_auth_result');
        $result.html('<p style="color: blue;">Getting authorization URL...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bst_gmail_get_auth_url',
                nonce: bst_gmail_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const authUrl = response.data.auth_url;
                    $result.html(
                        '<div style="margin: 15px 0;">' +
                        '<p><strong>Step 1:</strong> Click the link below to authorize Gmail access:</p>' +
                        '<p><a href="' + authUrl + '" target="_blank" class="button button-primary">Authorize Gmail Access</a></p>' +
                        '<p><strong>Step 2:</strong> After authorization, paste the code here:</p>' +
                        '<input type="text" id="bst_gmail_auth_code" placeholder="Paste authorization code here" style="width: 400px; margin-right: 10px;" />' +
                        '<button type="button" id="bst_gmail_complete_auth" class="button">Complete Authorization</button>' +
                        '</div>'
                    );
                } else {
                    $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</p>');
                }
            }
        });
    });
    
    // Handle auth code completion
    $(document).on('click', '#bst_gmail_complete_auth', function() {
        const authCode = $('#bst_gmail_auth_code').val().trim();
        
        if (!authCode) {
            alert('Please enter the authorization code.');
            return;
        }
        
        const $result = $('#bst_gmail_auth_result');
        $result.html('<p style="color: blue;">Completing authorization...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bst_gmail_complete_auth',
                auth_code: authCode,
                nonce: bst_gmail_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<p style="color: green;"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</p>');
                    // Refresh page to update status
                    setTimeout(() => location.reload(), 1500);
                } else {
                    $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</p>');
                }
            }
        });
    });
    
    // Test Gmail integration
    $('#bst_gmail_test_send').on('click', function() {
        const recipient = $('#bst_test_email_recipient').val().trim();
        
        if (!recipient) {
            alert('Please enter a test email address.');
            return;
        }
        
        if (!isValidEmail(recipient)) {
            alert('Please enter a valid email address.');
            return;
        }
        
        const $result = $('#bst_gmail_test_result');
        $result.html('<p style="color: blue;">Sending test email...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bst_gmail_test_send',
                recipient: recipient,
                nonce: bst_gmail_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<p style="color: green;"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</p>');
                } else {
                    $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</p>');
                }
            },
            error: function() {
                $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> Test email failed. Please try again.</p>');
            }
        });
    });
    
    // Handle manual authorization code completion
    $('#bst_gmail_complete_manual_auth').on('click', function() {
        const authCode = $('#bst_gmail_manual_auth_code').val().trim();
        
        if (!authCode) {
            alert('Please enter the authorization code.');
            return;
        }
        
        const $result = $('#bst_gmail_manual_auth_result');
        $result.html('<p style="color: blue;">Completing authorization...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bst_gmail_complete_auth',
                auth_code: authCode,
                nonce: bst_gmail_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<p style="color: green;"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</p>');
                    // Refresh page to update status
                    setTimeout(() => location.reload(), 1500);
                } else {
                    $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</p>');
                }
            },
            error: function() {
                $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> Authorization failed. Please try again.</p>');
            }
        });
    });
    
    // Handle re-authorization button
    $('#bst_gmail_reauthorize').on('click', function() {
        // Trigger the same authorization flow as the main authorize button
        $('#bst_gmail_authorize').trigger('click');
    });
    
    // Email validation helper
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // Handle email method toggle
    function handleEmailMethodToggle() {
        const selectedMethod = $('input[name="bst_email_method"]:checked').val();
        const $gmailFields = $('.gmail-only-field');
        
        if (selectedMethod === 'gmail') {
            // Show Gmail settings
            $gmailFields.removeClass('hidden').show();
        } else {
            // Hide Gmail settings when wp_mail is selected
            $gmailFields.addClass('hidden').hide();
        }
        
        // Update test button based on method
        updateTestButton(selectedMethod);
    }
    
    function updateTestButton(method) {
        const $gmailTestBtn = $('#bst_gmail_test_send');
        const $wpmailTestBtn = $('#bst_wpmail_test_send');
        
        if (method === 'gmail') {
            $gmailTestBtn.show();
            $wpmailTestBtn.hide();
        } else {
            $gmailTestBtn.hide();
            $wpmailTestBtn.show();
        }
    }
    
    // Initialize toggle handling
    $('input[name="bst_email_method"]').on('change', handleEmailMethodToggle);
    
    // Add gmail-only-field class to Gmail specific fields on page load
    function initializeGmailFieldClasses() {
        // Find Gmail-specific setting fields by their field names
        $('tr').each(function() {
            const $row = $(this);
            const $th = $row.find('th');
            const fieldLabel = $th.find('label').text() || $th.text();
            
            // Gmail-specific fields to hide when wp_mail is selected
            if (fieldLabel.includes('Gmail API Status') || 
                fieldLabel.includes('Upload Credentials') ||
                fieldLabel.includes('Authorization')) {
                $row.addClass('gmail-only-field');
            }
        });
        
        // Initial state
        handleEmailMethodToggle();
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initializeGmailFieldClasses();
    });
    
    // Handle wp_mail test button
    $(document).on('click', '#bst_wpmail_test_send', function() {
        const email = $('#bst_test_email_recipient').val();
        const $result = $('#bst_gmail_test_result');
        
        if (!email || !isValidEmail(email)) {
            $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> Please enter a valid email address.</p>');
            return;
        }
        
        $result.html('<p style="color: blue;">Sending test email via wp_mail...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bst_wpmail_test_send',
                recipient: email,
                nonce: (typeof bst_gmail_ajax !== 'undefined' && bst_gmail_ajax.nonce) ? bst_gmail_ajax.nonce : ''
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<p style="color: green;"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</p>');
                } else {
                    $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</p>');
                }
            },
            error: function() {
                $result.html('<p style="color: red;"><span class="dashicons dashicons-warning"></span> Test failed. Please try again.</p>');
            }
        });
    });
    
});