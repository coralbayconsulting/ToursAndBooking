<?php

// Register settings
function bst_register_settings() {
    register_setting('bst_settings_group', 'bst_company_name');
    register_setting('bst_settings_group', 'bst_company_address');
    register_setting('bst_settings_group', 'bst_company_tax_vat');
    register_setting('bst_settings_group', 'bst_vat_rate', array('default' => 22.0)); // VAT rate as decimal (e.g., 22.0 for 22%)
    register_setting('bst_settings_group', 'bst_bank_wire_discount', array('default' => 2.5)); // Bank wire discount as decimal (e.g., 2.5 for 2.5%)
    register_setting('bst_settings_group', 'bst_banner_image');
    register_setting('bst_settings_group', 'bst_ptarchive_tour_type_page_title');
    register_setting('bst_settings_group', 'bst_ptarchive_tour_type_meta_description');
    register_setting('bst_settings_group', 'bst_enable_tour_rating', array('default' => false));
    register_setting('bst_settings_group', 'bst_low_availability_threshold'); // Low availability threshold setting
    register_setting('bst_settings_group', 'bst_auto_refresh_interval', array('default' => 15)); // Auto-refresh interval in minutes
    
    // Finalization settings
    register_setting('bst_settings_group', 'bst_finalization_sent_days', array('default' => 120)); // Days before tour to send finalization
    register_setting('bst_settings_group', 'bst_finalization_overdue_grace_days', array('default' => 7)); // Days after due date before flagging as overdue
    
    // Airwallex API settings
    register_setting('bst_settings_group', 'bst_airwallex_client_id');
    register_setting('bst_settings_group', 'bst_airwallex_api_key');
    
    // Register Email Integration settings
    register_setting('bst_settings_group', 'bst_from_email_address', array('default' => 'info@bluestradatours.com'));
    register_setting('bst_settings_group', 'bst_from_email_name', array('default' => 'Blue Strada Tours'));
    register_setting('bst_settings_group', 'bst_email_signature');
    register_setting('bst_settings_group', 'bst_gmail_api_enabled', array('default' => false));
    register_setting('bst_settings_group', 'bst_gmail_credentials_uploaded', array('default' => false));
    register_setting('bst_settings_group', 'bst_email_method', array('default' => 'wp_mail'));
    
    // Register Email Automation settings
    register_setting('bst_settings_group', 'bst_email_automation_enabled', array('default' => false));
    
    // Register Gmail inbox checking settings
    register_setting('bst_settings_group', 'bst_gmail_inbox_checking_enabled', array('default' => false));
    register_setting('bst_settings_group', 'bst_gmail_inbox_label', array('default' => 'INBOX'));
    register_setting('bst_settings_group', 'bst_gmail_mark_processed_as_read', array('default' => true));
    register_setting('bst_settings_group', 'bst_gmail_processed_label', array('default' => 'BST-Processed'));
    register_setting('bst_settings_group', 'bst_gmail_matched_label', array('default' => 'BST-Matched'));
    register_setting('bst_settings_group', 'bst_gmail_unmatched_label', array('default' => 'BST-Unmatched'));
    
    // Register package settings
    for ($i = 1; $i <= 5; $i++) {
        register_setting('bst_settings_group', 'bst_package_' . $i . '_name');
        register_setting('bst_settings_group', 'bst_package_' . $i . '_people');
        register_setting('bst_settings_group', 'bst_package_' . $i . '_rooms');
        register_setting('bst_settings_group', 'bst_package_' . $i . '_vehicles');
    }

    add_settings_section(
        'bst_settings_section',
        'Settings',
        'bst_settings_section_callback',
        'bst_settings_page'
    );

    add_settings_section(
        'bst_gmail_section',
        'Email Integration',
        'bst_gmail_section_callback',
        'bst_settings_page'
    );
    
    // Order: sending method & tests first; signature editor last (tall) so it does not hide these rows.
    add_settings_field(
        'bst_email_method',
        'Email Sending Method',
        'bst_email_method_callback',
        'bst_settings_page',
        'bst_gmail_section'
    );

    add_settings_field(
        'bst_from_email_address',
        'From Email Address',
        'bst_from_email_address_callback',
        'bst_settings_page',
        'bst_gmail_section'
    );

    add_settings_field(
        'bst_from_email_name',
        'From Email Name',
        'bst_from_email_name_callback',
        'bst_settings_page',
        'bst_gmail_section'
    );

    add_settings_field(
        'bst_gmail_api_status',
        'Gmail API Status',
        'bst_gmail_api_status_callback',
        'bst_settings_page',
        'bst_gmail_section'
    );

    add_settings_field(
        'bst_gmail_credentials_upload',
        'Upload Credentials File',
        'bst_gmail_credentials_upload_callback',
        'bst_settings_page',
        'bst_gmail_section'
    );

    add_settings_field(
        'bst_gmail_authorization',
        'Authorization',
        'bst_gmail_authorization_callback',
        'bst_settings_page',
        'bst_gmail_section'
    );

    add_settings_field(
        'bst_gmail_test',
        'Test Email Integration',
        'bst_gmail_test_callback',
        'bst_settings_page',
        'bst_gmail_section'
    );

    add_settings_field(
        'bst_gmail_inbox_checking',
        'Inbox Checking',
        'bst_gmail_inbox_checking_callback',
        'bst_settings_page',
        'bst_gmail_section'
    );

    add_settings_field(
        'bst_email_automation_enabled',
        'Enable Daily Email Automation',
        'bst_email_automation_enabled_callback',
        'bst_settings_page',
        'bst_gmail_section'
    );

    add_settings_field(
        'bst_email_signature',
        'Email Signature',
        'bst_email_signature_callback',
        'bst_settings_page',
        'bst_gmail_section'
    );

    add_settings_section(
        'bst_deployment_section',
        'Deployment Tools',
        'bst_deployment_section_callback',
        'bst_tools_page'
    );

    add_settings_field(
        'bst_company_name',
        'Company Name',
        'bst_company_name_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_company_address',
        'Company Address',
        'bst_company_address_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_company_tax_vat',
        'Tax Code & VAT Number',
        'bst_company_tax_vat_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_vat_rate',
        'Current VAT Rate',
        'bst_vat_rate_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_bank_wire_discount',
        'Bank Transfer Discount',
        'bst_bank_wire_discount_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_banner_image',
        'Our Tours Banner Image',
        'bst_banner_image_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_ptarchive_tour_type_page_title',
        'Our Tours archive page title (H1 & browser tab)',
        'bst_ptarchive_tour_type_page_title_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_ptarchive_tour_type_meta_description',
        'Our Tours archive meta description',
        'bst_ptarchive_tour_type_meta_description_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_low_availability_threshold',
        'Low Availability Threshold',
        'bst_low_availability_threshold_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_enable_tour_rating',
        'Enable Tour Rating',
        'bst_enable_tour_rating_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_auto_refresh_interval',
        'Auto-Refresh Interval (minutes)',
        'bst_auto_refresh_interval_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_finalization_sent_days',
        'Finalization Email Sent (days before tour)',
        'bst_finalization_sent_days_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_finalization_overdue_grace_days',
        'Finalization Overdue Grace Period (days)',
        'bst_finalization_overdue_grace_days_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_airwallex_client_id',
        'Airwallex Client ID',
        'bst_airwallex_client_id_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_airwallex_api_key',
        'Airwallex API Key',
        'bst_airwallex_api_key_callback',
        'bst_settings_page',
        'bst_settings_section'
    );

    add_settings_field(
        'bst_version_bumper',
        'Child Theme Version Management',
        'bst_version_bumper_callback',
        'bst_tools_page',
        'bst_deployment_section'
    );

    add_settings_section(
        'bst_admin_operations_section',
        'Admin Operations',
        'bst_admin_operations_section_callback',
        'bst_tools_page'
    );

    add_settings_field(
        'bst_release_data_cleanup',
        'Release Data Cleanup',
        'bst_release_data_cleanup_callback',
        'bst_tools_page',
        'bst_admin_operations_section'
    );

}
add_action('admin_init', 'bst_register_settings');

add_action(
    'admin_notices',
    function () {
        if ( empty( $_GET['page'] ) || 'bst_tools_page' !== $_GET['page'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $k = 'bst_tools_notice_' . get_current_user_id();
        $msg = get_transient( $k );
        if ( ! is_string( $msg ) || '' === $msg ) {
            return;
        }
        delete_transient( $k );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
    }
);

function bst_settings_section_callback() {
    echo '<p>Set your global settings below:</p>';
}

function bst_deployment_section_callback() {
    echo '<p>Tools to help with Azure deployment cache busting:</p>';
}

function bst_admin_operations_section_callback() {
    echo '<p>Administrative operations and bulk data management:</p>';
}

function bst_gmail_section_callback() {
    echo '<p>Configure email settings for sending emails via WordPress wp_mail() or Gmail API. <strong>Sending method, From address, and test email</strong> are at the top; the signature editor is below.</p>';
    echo '<div class="notice notice-info"><p><strong>Note:</strong> On production Azure, the From address must match Communication Services MailFrom. On local dev, use Mailpit/your host mail; if bulk send fails, use <em>Send Test Email (wp_mail)</em> here to see the underlying error.</p></div>';
}

function bst_email_automation_enabled_callback() {
    $enabled = get_option('bst_email_automation_enabled', false);
    echo '<label><input type="checkbox" name="bst_email_automation_enabled" value="1" ' . checked($enabled, true, false) . ' /> Enable scheduled email automation</label>';
    echo '<p class="description">When enabled, the daily cron will process time-based email triggers (reminders, follow-ups, etc.). The cron will remain scheduled but inactive when disabled.</p>';
}

function bst_from_email_address_callback() {
    $email = get_option('bst_from_email_address', 'info@bluestradatours.com');
    echo '<input type="email" id="bst_from_email_address" name="bst_from_email_address" value="' . esc_attr($email) . '" style="width: 400px;" />';
    echo '<p class="description">The email address that outgoing emails will be sent from. Must be configured in Azure Communication Services MailFrom addresses. Use merge tag <code>{BstEmail}</code> in Gravity Forms.</p>';
}

function bst_from_email_name_callback() {
    $name = get_option('bst_from_email_name', 'Blue Strada Tours');
    echo '<input type="text" id="bst_from_email_name" name="bst_from_email_name" value="' . esc_attr($name) . '" style="width: 400px;" />';
    echo '<p class="description">The display name that appears with the from email address (e.g., "Blue Strada Tours").</p>';
}

function bst_email_signature_callback() {
    $signature = get_option('bst_email_signature', '');
    wp_editor($signature, 'bst_email_signature', array(
        'textarea_name' => 'bst_email_signature',
        'textarea_rows' => 10,
        'media_buttons' => true,
        'teeny' => false,
        'tinymce' => array(
            'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,image',
        )
    ));
    echo '<p class="description">HTML email signature to append to outgoing emails. Use merge tag <code>{BstEmailSignature}</code> in email templates and Gravity Forms notifications.</p>';
}

function bst_company_name_callback() {
    $company_name = get_option('bst_company_name', '');
    echo '<input type="text" id="bst_company_name" name="bst_company_name" value="' . esc_attr($company_name) . '" style="width: 400px;" />';
    echo '<p class="description">Your company name as it appears on invoices.</p>';
}

function bst_company_address_callback() {
    $company_address = get_option('bst_company_address', '');
    echo '<textarea id="bst_company_address" name="bst_company_address" rows="3" style="width: 400px;">' . esc_textarea($company_address) . '</textarea>';
    echo '<p class="description">Your company address for invoices (use multiple lines if needed).</p>';
}

function bst_company_tax_vat_callback() {
    $company_tax_vat = get_option('bst_company_tax_vat', '');
    echo '<input type="text" id="bst_company_tax_vat" name="bst_company_tax_vat" value="' . esc_attr($company_tax_vat) . '" style="width: 400px;" />';
    echo '<p class="description">Your company Codice Fiscale and Partita IVA (typically the same number for Italian companies).</p>';
}

function bst_vat_rate_callback() {
    $vat_rate = get_option('bst_vat_rate', 22.0);
    echo '<input type="number" step="0.1" min="0" max="100" id="bst_vat_rate" name="bst_vat_rate" value="' . esc_attr($vat_rate) . '" style="width: 100px;" />';
    echo '<span style="margin-left: 10px;">%</span>';
    echo '<p class="description">Enter VAT rate as a decimal (e.g., 22.0 for 22%). Divide by 100 before using in calculations.</p>';
}

function bst_bank_wire_discount_callback() {
    $discount = get_option('bst_bank_wire_discount', 2.5);
    echo '<input type="number" step="0.1" min="0" max="100" id="bst_bank_wire_discount" name="bst_bank_wire_discount" value="' . esc_attr($discount) . '" style="width: 100px;" />';
    echo '<span style="margin-left: 10px;">%</span>';
    echo '<p class="description">Enter bank transfer discount rate as a decimal (e.g., 2.5 for 2.5%). Divide by 100 before using in calculations.</p>';
}

function bst_banner_image_callback() {
    $banner_image = get_option('bst_banner_image');
    echo '<img id="bst_banner_image_preview" src="' . esc_attr($banner_image) . '" style="max-width: 300px; display: ' . ($banner_image ? 'block' : 'none') . ';" />';
    echo '<input type="text" id="bst_banner_image" name="bst_banner_image" value="' . esc_attr($banner_image) . '" style="display:none;" />';
    echo '<input type="button" id="upload_image_button" class="button" value="Upload Image" />';
    echo '<p class="description">Use the upload button to select an image from the media library.</p>';
}

function bst_ptarchive_tour_type_page_title_callback() {
    $val = get_option('bst_ptarchive_tour_type_page_title', '');
    echo '<input type="text" id="bst_ptarchive_tour_type_page_title" name="bst_ptarchive_tour_type_page_title" value="' . esc_attr($val) . '" style="width: 100%; max-width: 560px;" maxlength="120" />';
    echo '<p class="description">Shown as the main heading on <code>/tour-types/</code> and as the first part of the page title (site name is added automatically). Leave empty to use the default <strong>Our Tours</strong>. Yoast does not provide a per-page SEO box for this archive URL.</p>';
}

function bst_ptarchive_tour_type_meta_description_callback() {
    $val = get_option('bst_ptarchive_tour_type_meta_description', '');
    echo '<textarea id="bst_ptarchive_tour_type_meta_description" name="bst_ptarchive_tour_type_meta_description" rows="3" style="width: 100%; max-width: 560px;" maxlength="320">' . esc_textarea($val) . '</textarea>';
    echo '<p class="description">Optional meta description for <code>/tour-types/</code> (search engines and Open Graph). If empty, a generic description is used.</p>';
}

function bst_low_availability_threshold_callback() {
    $threshold = get_option('bst_low_availability_threshold', 2);
    echo '<input type="number" min="1" max="10" id="bst_low_availability_threshold" name="bst_low_availability_threshold" value="' . esc_attr($threshold) . '" />';
    echo '<p class="description">Show the orange "X LEFT" badge when availability is at or below this number.</p>';
}

function bst_enable_tour_rating_callback() {
    $enabled = get_option('bst_enable_tour_rating', false);
    echo '<label for="bst_enable_tour_rating">';
    echo '<input type="checkbox" id="bst_enable_tour_rating" name="bst_enable_tour_rating" value="1" ' . checked(1, $enabled, false) . ' />';
    echo ' Enable tour rating taxonomy filters and UI.';
    echo '</label>';
    echo '<p class="description">When enabled, tour type pages will show a "Tour Class" filter based on the tour-rating taxonomy terms you define (e.g., Platinum, Gold, etc.).</p>';
}

function bst_auto_refresh_interval_callback() {
    $interval = get_option('bst_auto_refresh_interval', 15);
    echo '<input type="number" min="1" max="60" id="bst_auto_refresh_interval" name="bst_auto_refresh_interval" value="' . esc_attr($interval) . '" />';
    echo '<p class="description">Number of minutes before pages showing tour availability automatically refresh to keep data current. Minimum: 1 minute, Maximum: 60 minutes.</p>';
}

function bst_finalization_sent_days_callback() {
    $days = get_option('bst_finalization_sent_days', 120);
    echo '<input type="number" min="60" max="180" id="bst_finalization_sent_days" name="bst_finalization_sent_days" value="' . esc_attr($days) . '" style="width: 80px;" />';
    echo '<span style="margin-left: 10px;">days</span>';
    echo '<p class="description">Dashboard widget displays bookings that need finalization emails sent within this window. Finalization emails should be sent to guests this many days before their tour starts.</p>';
}

function bst_finalization_overdue_grace_days_callback() {
    $days = get_option('bst_finalization_overdue_grace_days', 7);
    echo '<input type="number" min="0" max="30" id="bst_finalization_overdue_grace_days" name="bst_finalization_overdue_grace_days" value="' . esc_attr($days) . '" style="width: 80px;" />';
    echo '<span style="margin-left: 10px;">days</span>';
    echo '<p class="description">Number of days after the balance payment due date before showing the booking as overdue. The due date is calculated from the payment terms (e.g., "60 days before tour"). Set to 0 to show overdue immediately after the due date passes.</p>';
}

function bst_airwallex_client_id_callback() {
    $client_id = get_option('bst_airwallex_client_id', '');
    echo '<input type="text" id="bst_airwallex_client_id" name="bst_airwallex_client_id" value="' . esc_attr($client_id) . '" style="width: 400px;" />';
    echo '<p class="description">Your Airwallex Client ID for API authentication. Find this in the Airwallex Webapp under API menu.</p>';
}

function bst_airwallex_api_key_callback() {
    $api_key = get_option('bst_airwallex_api_key', '');
    echo '<input type="password" id="bst_airwallex_api_key" name="bst_airwallex_api_key" value="' . esc_attr($api_key) . '" style="width: 400px;" />';
    echo '<p class="description">Your Airwallex API Key for authentication. Keep this secure and do not share it. Used for FX quote API.</p>';
}

function bst_airwallex_environment_callback() {
    $environment = get_option('bst_airwallex_environment', 'sandbox');
    echo '<select id="bst_airwallex_environment" name="bst_airwallex_environment">';
    echo '<option value="sandbox"' . selected($environment, 'sandbox', false) . '>Sandbox (Testing)</option>';
    echo '<option value="production"' . selected($environment, 'production', false) . '>Production (Live)</option>';
    echo '</select>';
    echo '<p class="description">Choose between sandbox (testing) or production (live) environment for Airwallex API.</p>';
}

function bst_email_method_callback() {
    $current_method = get_option('bst_email_method', 'wp_mail');
    ?>
    <style>
        .gmail-only-field {
            transition: opacity 0.3s ease;
        }
        .gmail-only-field.hidden {
            display: none !important;
        }
    </style>
    <div style="margin-bottom: 15px;">
        <label>
            <input type="radio" name="bst_email_method" value="wp_mail" <?php checked($current_method, 'wp_mail'); ?> />
            <strong>WordPress wp_mail()</strong> - Default WordPress email system
        </label>
        <br><br>
        <label>
            <input type="radio" name="bst_email_method" value="gmail" <?php checked($current_method, 'gmail'); ?> />
            <strong>Gmail API</strong> - Send emails through Gmail API (requires configuration below)
        </label>
    </div>
    <p class="description">
        Choose how emails are sent. WordPress wp_mail() uses your server's default mail settings, 
        while Gmail API provides better deliverability and tracking through Google's infrastructure.
    </p>
    <?php
}

function bst_gmail_api_status_callback() {
    try {
        $gmail_api = new BST_Gmail_API();
        $is_configured = $gmail_api->is_configured();
        
        if ($is_configured) {
            echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> <strong>Gmail API is configured and ready</strong>';
        } else {
            echo '<span class="dashicons dashicons-warning" style="color: orange;"></span> <strong>Gmail API needs configuration</strong>';
            echo '<p class="description">Upload credentials file and authorize access to enable Gmail integration.</p>';
        }
    } catch (Exception $e) {
        echo '<span class="dashicons dashicons-warning" style="color: red;"></span> <strong>Gmail API not available</strong>';
        echo '<p class="description">Google API Client library is required. Run: <code>composer require google/apiclient</code></p>';
    }
}

function bst_gmail_credentials_upload_callback() {
    $credentials_uploaded = get_option('bst_gmail_credentials_uploaded', false);
    
    echo '<input type="file" id="bst_gmail_credentials_file" accept=".json" style="margin-bottom: 10px;" />';
    echo '<button type="button" id="bst_upload_gmail_credentials" class="button">Upload Credentials</button>';
    
    if ($credentials_uploaded) {
        echo '<p style="color: green;"><span class="dashicons dashicons-yes-alt"></span> Credentials file uploaded</p>';
    }
    
    echo '<div id="bst_gmail_upload_result"></div>';
    echo '<p class="description">Upload the <code>credentials.json</code> file from your Google Cloud Console. 
          <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Get credentials here</a></p>';
    
    // Add detailed setup instructions
    echo '<div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">';
    echo '<h4 style="margin-top: 0;">Setup Instructions:</h4>';
    echo '<ol style="margin-left: 20px;">';
    echo '<li><strong>Create Google Cloud Project:</strong> Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> and create a new project</li>';
    echo '<li><strong>Enable Gmail API:</strong> <a href="https://console.cloud.google.com/apis/library/gmail.googleapis.com" target="_blank">Enable Gmail API</a> for your project</li>';
    echo '<li><strong>Create OAuth 2.0 Credentials:</strong>';
    echo '<ul style="margin-left: 20px; margin-top: 5px;">';
    echo '<li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Credentials page</a></li>';
    echo '<li>Click "Create Credentials" → "OAuth 2.0 Client IDs"</li>';
    echo '<li><strong>Application type:</strong> Web application</li>';
    echo '<li><strong>Name:</strong> Blue Strada Tours Email System (or similar)</li>';
    echo '<li><strong>Authorized JavaScript origins:</strong> Add these URLs:</li>';
    echo '<ul style="margin-left: 20px; margin-top: 5px; font-family: monospace; font-size: 12px;">';
    echo '<li>' . home_url() . '</li>';
    echo '<li>' . admin_url() . '</li>';
    echo '</ul>';
    echo '<li><strong>Authorized redirect URIs:</strong> Add these URLs:</li>';
    echo '<ul style="margin-left: 20px; margin-top: 5px; font-family: monospace; font-size: 12px;">';
    echo '<li>' . admin_url('admin.php?page=bst_settings&gmail_callback=1') . '</li>';
    echo '<li>' . home_url() . '</li>';
    echo '</ul>';
    echo '</ul>';
    echo '</li>';
    echo '<li><strong>Download credentials.json:</strong> After creating, download the credentials file and upload it above</li>';
    echo '</ol>';
    echo '</div>';
}

function bst_gmail_authorization_callback() {
    try {
        $gmail_api = new BST_Gmail_API();
        $credentials_uploaded = get_option('bst_gmail_credentials_uploaded', false);
        $api_enabled = get_option('bst_gmail_api_enabled', false);
        
        if (!$credentials_uploaded) {
            echo '<p><em>Upload credentials file first to enable authorization.</em></p>';
            return;
        }
        
        if ($api_enabled) {
            echo '<p style="color: green;"><span class="dashicons dashicons-yes-alt"></span> <strong>Gmail API is authorized and ready!</strong></p>';
            echo '<button type="button" id="bst_gmail_reauthorize" class="button">Re-authorize Access</button>';
        } else {
            echo '<button type="button" id="bst_gmail_authorize" class="button button-primary">Authorize Gmail Access</button>';
        }
        
        echo '<div id="bst_gmail_auth_result"></div>';
        echo '<p class="description">Click to authorize the application to send emails through your Gmail account.</p>';
        
    } catch (Exception $e) {
        echo '<p><em>Google API Client library is required for authorization.</em></p>';
    }
}

function bst_gmail_test_callback() {
    $email_method = get_option('bst_email_method', 'wp_mail');
    
    if ($email_method === 'gmail') {
        // Gmail API test
        try {
            $gmail_api = new BST_Gmail_API();
            $is_configured = $gmail_api->is_configured();
            
            if (!$is_configured) {
                echo '<p><em>Complete Gmail API setup first to enable testing.</em></p>';
                return;
            }
            
            echo '<div style="margin-bottom: 15px;">';
            echo '<label for="bst_test_email_recipient">Test Email Address:</label><br>';
            echo '<input type="email" id="bst_test_email_recipient" placeholder="test@example.com" style="width: 300px; margin-top: 5px;" />';
            echo '</div>';
            echo '<button type="button" id="bst_gmail_test_send" class="button button-secondary">Send Test Email (Gmail API)</button>';
            echo '<div id="bst_gmail_test_result"></div>';
            echo '<p class="description">Send a test email to verify Gmail API integration is working correctly.</p>';
        } catch (Exception $e) {
            echo '<p><em>Google API Client library is required for testing Gmail API.</em></p>';
        }
    } else {
        // wp_mail test
        echo '<div style="margin-bottom: 15px;">';
        echo '<label for="bst_test_email_recipient">Test Email Address:</label><br>';
        echo '<input type="email" id="bst_test_email_recipient" placeholder="test@example.com" style="width: 300px; margin-top: 5px;" />';
        echo '</div>';
        echo '<button type="button" id="bst_wpmail_test_send" class="button button-secondary">Send Test Email (wp_mail)</button>';
        echo '<div id="bst_gmail_test_result"></div>';
        echo '<p class="description">Send a test email to verify wp_mail() is working correctly.</p>';
    }
}

function bst_gmail_inbox_checking_callback() {
    $email_method = get_option('bst_email_method', 'wp_mail');
    $gmail_enabled = get_option('bst_gmail_api_enabled', false);
    $inbox_enabled = get_option('bst_gmail_inbox_checking_enabled', false);
    $inbox_label = get_option('bst_gmail_inbox_label', 'INBOX');
    $mark_read = get_option('bst_gmail_mark_processed_as_read', true);
    $processed_label = get_option('bst_gmail_processed_label', 'BST-Processed');
    $matched_label = get_option('bst_gmail_matched_label', 'BST-Matched');
    $unmatched_label = get_option('bst_gmail_unmatched_label', 'BST-Unmatched');
    
    if ($email_method !== 'gmail' || !$gmail_enabled) {
        echo '<p><em>Gmail API must be enabled and configured to use inbox checking.</em></p>';
        return;
    }
    
    echo '<div class="gmail-setting" style="' . ($email_method !== 'gmail' ? 'display:none;' : '') . '">';
    
    // Enable inbox checking
    echo '<div style="margin-bottom: 15px;">';
    echo '<label>';
    echo '<input type="checkbox" name="bst_gmail_inbox_checking_enabled" value="1" ' . checked($inbox_enabled, 1, false) . ' />';
    echo ' <strong>Enable inbox checking</strong>';
    echo '</label>';
    echo '<p class="description">Automatically check Gmail inbox for customer replies and associate them with bookings.</p>';
    echo '</div>';
    
    // Inbox label/folder
    echo '<div style="margin-bottom: 15px;">';
    echo '<label for="bst_gmail_inbox_label"><strong>Monitor Gmail Label:</strong></label><br>';
    echo '<input type="text" id="bst_gmail_inbox_label" name="bst_gmail_inbox_label" value="' . esc_attr($inbox_label) . '" style="width: 300px; margin-top: 5px;" placeholder="INBOX" />';
    echo '<p class="description">Gmail label/folder to monitor. Use "INBOX" for main inbox, or specific label name like "Support".</p>';
    echo '</div>';
    
    // Processing labels section
    echo '<h4>Processing Labels</h4>';
    echo '<p class="description">These labels will be applied to emails to track processing status:</p>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label for="bst_gmail_processed_label"><strong>Processed Label:</strong></label><br>';
    echo '<input type="text" id="bst_gmail_processed_label" name="bst_gmail_processed_label" value="' . esc_attr($processed_label) . '" style="width: 200px; margin-top: 5px;" placeholder="BST-Processed" />';
    echo '<p class="description">Applied to all emails that have been processed by the system.</p>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label for="bst_gmail_matched_label"><strong>Matched Label:</strong></label><br>';
    echo '<input type="text" id="bst_gmail_matched_label" name="bst_gmail_matched_label" value="' . esc_attr($matched_label) . '" style="width: 200px; margin-top: 5px;" placeholder="BST-Matched" />';
    echo '<p class="description">Applied to emails successfully matched to a booking.</p>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 15px;">';
    echo '<label for="bst_gmail_unmatched_label"><strong>Unmatched Label:</strong></label><br>';
    echo '<input type="text" id="bst_gmail_unmatched_label" name="bst_gmail_unmatched_label" value="' . esc_attr($unmatched_label) . '" style="width: 200px; margin-top: 5px;" placeholder="BST-Unmatched" />';
    echo '<p class="description">Applied to emails that could not be matched to any booking.</p>';
    echo '</div>';
    
    // Mark as read option
    echo '<div style="margin-bottom: 15px;">';
    echo '<label>';
    echo '<input type="checkbox" name="bst_gmail_mark_processed_as_read" value="1" ' . checked($mark_read, 1, false) . ' />';
    echo ' Mark processed emails as read (optional)';
    echo '</label>';
    echo '<p class="description">Also mark emails as read after processing (in addition to labels).</p>';
    echo '</div>';
    
    // Status and test
    if ($inbox_enabled) {
        $last_check = get_option('bst_last_inbox_check', 0);
        echo '<div style="padding: 10px; background: #f0f8ff; border: 1px solid #0073aa; margin-bottom: 15px;">';
        echo '<p><strong>Status:</strong> Inbox checking is active (runs hourly)</p>';
        if ($last_check) {
            echo '<p><strong>Last Check:</strong> ' . date('Y-m-d H:i:s', $last_check) . '</p>';
        }
        echo '</div>';
        
        echo '<button type="button" id="bst_gmail_test_inbox" class="button button-secondary">Test Inbox Check Now</button>';
        echo '<div id="bst_gmail_inbox_result"></div>';
        echo '<p class="description">Manually trigger an inbox check to test the functionality.</p>';
    } else {
        echo '<p><em>Enable inbox checking and save settings to activate hourly email monitoring.</em></p>';
    }
    
    echo '</div>';
}

// Enqueue the media uploader script
function bst_enqueue_admin_javascript($hook) {
    wp_enqueue_media();
    // Use a more reliable URL path for Azure
    $script_url = content_url('mu-plugins/bst_plugin/js/bst-media-uploader.js');
    $script_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/js/bst-media-uploader.js';
    $script_version = file_exists($script_path) ? filemtime($script_path) : time();
    wp_enqueue_script('bst-media-uploader', $script_url, array(), $script_version, true);
    
    // Add Gmail settings script on BST settings pages
    if (strpos($hook, 'bst') !== false) {
        $gmail_script_url = content_url('mu-plugins/bst_plugin/js/bst-gmail-settings.js');
        $gmail_script_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/js/bst-gmail-settings.js';
        $gmail_script_version = file_exists($gmail_script_path) ? filemtime($gmail_script_path) : time();
        wp_enqueue_script('bst-gmail-settings', $gmail_script_url, array('jquery'), $gmail_script_version, true);
        
        // Localize script for AJAX
        wp_localize_script('bst-gmail-settings', 'bst_gmail_ajax', array(
            'nonce' => wp_create_nonce('bst_gmail_nonce')
        ));
    }
    
    // Add version bumper script only on settings pages - Use a more reliable URL path for Azure
    $version_script_url = content_url('mu-plugins/bst_plugin/js/bst-version-bumper.js');
    $version_script_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/js/bst-version-bumper.js';
    $version_script_version = file_exists($version_script_path) ? filemtime($version_script_path) : time();
    
    wp_enqueue_script('bst-version-bumper', $version_script_url, array('jquery'), $version_script_version, true);
    wp_localize_script('bst-version-bumper', 'bst_version_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bst_version_bumper_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'bst_enqueue_admin_javascript');

// Version bumper callback function for the tools page
function bst_version_bumper_callback() {
    echo '<div id="version-bumper-operations">';
    echo '<p>Update version numbers in child theme functions.php to bust browser cache.</p>';
    echo '<p>This tool will automatically increment version numbers for enqueued stylesheets and scripts.</p>';
    
    // Get current versions from child theme functions.php
    $functions_php_path = get_stylesheet_directory() . '/functions.php';
    $versions_to_bump = array();
    $reference_versions = array();
    
    if (file_exists($functions_php_path)) {
        $content = file_get_contents($functions_php_path);
        
        // Patterns for versions that will be incremented (theme files you modify)
        $bump_patterns = array(
            'child_style' => "/wp_enqueue_style\(\s*'chld_thm_cfg_child'.*?array\([^)]*\).*?'([^']+)'/",
            'single_tour_script' => "/wp_enqueue_script\(\s*'single-tour-script'.*?array\([^)]*\).*?'([^']+)'/",
            'gravity_forms_css' => "/wp_enqueue_style\(\s*'gravity-forms-custom-styles'.*?array\([^)]*\).*?'([^']+)'/",
            'gravity_forms_js' => "/wp_enqueue_script\(\s*'gravity-forms-custom-scripts'.*?array\([^)]*\).*?'([^']+)'/"
        );
        
        // Patterns for reference only (won't be incremented)
        $reference_patterns = array(
            'parent_style' => "/wp_enqueue_style\(\s*'chld_thm_cfg_parent'.*?array\([^)]*\).*?'([^']+)'/",
            'font_awesome' => "/wp_enqueue_style\(\s*'font-awesome'.*?array\([^)]*\).*?'([^']+)'/"
        );
        
        foreach ($bump_patterns as $type => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $version = $matches[1];
                // Skip if this is already a timestamp
                if (!is_numeric($version) || strlen($version) <= 5) {
                    switch ($type) {
                        case 'child_style':
                            $versions_to_bump[] = array('version' => $version, 'description' => 'Child Theme CSS');
                            break;
                        case 'single_tour_script':
                            $versions_to_bump[] = array('version' => $version, 'description' => 'Single Tour JavaScript');
                            break;
                        case 'gravity_forms_css':
                            $versions_to_bump[] = array('version' => $version, 'description' => 'Gravity Forms Custom CSS');
                            break;
                        case 'gravity_forms_js':
                            $versions_to_bump[] = array('version' => $version, 'description' => 'Gravity Forms Custom JS');
                            break;
                    }
                }
            }
        }
        
        foreach ($reference_patterns as $type => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $version = $matches[1];
                switch ($type) {
                    case 'parent_style':
                        $reference_versions[] = array('version' => $version, 'description' => 'Parent Theme CSS (managed by theme author)');
                        break;
                    case 'font_awesome':
                        $reference_versions[] = array('version' => $version, 'description' => 'Font Awesome Icons (external CDN)');
                        break;
                }
            }
        }
    }
    
    // Add plugin version and auto-versioned files for reference
    $reference_versions[] = array('version' => BST_PLUGIN_VERSION, 'description' => 'BST Plugin (main version)');
    
    // Check some plugin files to show their automatic file-based versions
    $plugin_files = array(
        'admin-date-format.css' => 'Admin Date Format CSS',
        'admin-date-format.js' => 'Admin Date Format JS',
        'tour-dates-admin.js' => 'Tour Dates Admin JS',
        'tour-bookings-admin.js' => 'Tour Bookings Admin JS',
        'bst-version-bumper.js' => 'Version Bumper JS'
    );
    
    foreach ($plugin_files as $file => $description) {
        $file_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/js/' . $file;
        if (!file_exists($file_path)) {
            $file_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/css/' . $file;
        }
        if (file_exists($file_path)) {
            $version = filemtime($file_path);
            $reference_versions[] = array(
                'version' => date('Y-m-d H:i:s', $version), 
                'description' => $description . ' (auto-versioned)'
            );
        }
    }
    
    // Display current versions
    if (!empty($versions_to_bump) || !empty($reference_versions)) {
        echo '<div style="margin: 15px 0; padding: 10px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">';
        
        if (!empty($versions_to_bump)) {
            echo '<h4 style="margin-top: 0; color: #0073aa;">Versions to be incremented:</h4>';
            echo '<ul style="margin: 0 0 15px 0; padding-left: 20px;">';
            foreach ($versions_to_bump as $item) {
                echo '<li><strong>' . esc_html($item['description']) . ':</strong> <code>' . esc_html($item['version']) . '</code></li>';
            }
            echo '</ul>';
        }
        
        if (!empty($reference_versions)) {
            echo '<h4 style="margin-top: 0; color: #666;">Other versions (auto-managed or external):</h4>';
            echo '<ul style="margin: 0; padding-left: 20px;">';
            foreach ($reference_versions as $item) {
                echo '<li><strong>' . esc_html($item['description']) . ':</strong> <code>' . esc_html($item['version']) . '</code></li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';
    }
    
    echo '<button type="button" id="bst-bump-versions" class="button button-primary" style="margin-top: 15px;" title="Increment version numbers in child theme">';
    echo '<span class="dashicons dashicons-update" style="margin-right: 5px;"></span>';
    echo 'Bump Child Theme Version';
    echo '</button>';
    echo '<span id="bst-version-spinner" class="spinner" style="margin-left: 10px; display: none;"></span>';
    echo '<div id="bst-version-status" style="margin-top: 10px;"></div>';
    
    echo '<div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa;">';
    echo '<p><strong>Note:</strong> This will increment version numbers for wp_enqueue_style and wp_enqueue_script calls in your child theme functions.php. This helps force browsers to reload cached CSS and JS files after deployments.</p>';
    echo '</div>';
    echo '</div>';
}

// AJAX handler for version bumping
function bst_bump_child_theme_versions() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'bst_version_bumper_nonce')) {
        wp_die('Security check failed');
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $functions_php_path = get_stylesheet_directory() . '/functions.php';
    
    if (!file_exists($functions_php_path)) {
        wp_send_json_error('Child theme functions.php not found');
        return;
    }

    if (!is_writable($functions_php_path)) {
        wp_send_json_error('Child theme functions.php is not writable');
        return;
    }

    $content = file_get_contents($functions_php_path);
    $original_content = $content;
    
    // Define patterns for files we actually want to version bump (excluding external CDN resources)
    $patterns_to_bump = array(
        'parent_style' => "/wp_enqueue_style\(\s*'chld_thm_cfg_parent'.*?(array\([^)]*\).*?')([^']+)(')/",
        'child_style' => "/wp_enqueue_style\(\s*'chld_thm_cfg_child'.*?(array\([^)]*\).*?')([^']+)(')/",
        'single_tour_script' => "/wp_enqueue_script\(\s*'single-tour-script'.*?(array\([^)]*\).*?')([^']+)(')/",
        'gravity_forms_css' => "/wp_enqueue_style\(\s*'gravity-forms-custom-styles'.*?(array\([^)]*\).*?')([^']+)(')/",
        'gravity_forms_js' => "/wp_enqueue_script\(\s*'gravity-forms-custom-scripts'.*?(array\([^)]*\).*?')([^']+)(')/",
    );
    
    $updated_versions = array();
    $changes_made = false;
    
    foreach ($patterns_to_bump as $type => $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            $full_match = $matches[0];
            $before = $matches[1];
            $version = $matches[2];
            $after = $matches[3];
            
            // Skip if this is already a timestamp (likely from plugin files)
            if (is_numeric($version) && strlen($version) > 5) {
                continue;
            }
            
            // Increment semantic version
            $version_parts = explode('.', $version);
            if (count($version_parts) >= 2) {
                // Increment the minor version
                $version_parts[count($version_parts) - 1] = (int)$version_parts[count($version_parts) - 1] + 1;
                $new_version = implode('.', $version_parts);
                
                $new_match = str_replace($before . $version . $after, $before . $new_version . $after, $full_match);
                $content = str_replace($full_match, $new_match, $content);
                
                $type_description = '';
                switch ($type) {
                    case 'parent_style':
                        $type_description = 'Parent Theme CSS (reference only)';
                        break;
                    case 'child_style':
                        $type_description = 'Child Theme CSS';
                        break;
                    case 'single_tour_script':
                        $type_description = 'Single Tour JavaScript';
                        break;
                    case 'gravity_forms_css':
                        $type_description = 'Gravity Forms Custom CSS';
                        break;
                    case 'gravity_forms_js':
                        $type_description = 'Gravity Forms Custom JS';
                        break;
                }
                
                $updated_versions[] = array(
                    'old' => $version,
                    'new' => $new_version,
                    'description' => $type_description
                );
                $changes_made = true;
            }
        }
    }
    
    if (!$changes_made) {
        wp_send_json_error('No version numbers found to update');
        return;
    }
    
    // Write the updated content back to the file
    $result = file_put_contents($functions_php_path, $content);
    
    if ($result === false) {
        wp_send_json_error('Failed to write updated functions.php');
        return;
    }
    
    wp_send_json_success(array(
        'message' => 'Child theme versions updated successfully!',
        'updated_versions' => $updated_versions
    ));
}
add_action('wp_ajax_bst_bump_versions', 'bst_bump_child_theme_versions');



// Register settings for package names, rooms, and vehicles
function bst_register_package_settings() {
    for ($i = 1; $i <= 5; $i++) {
        register_setting('bst_settings_group', 'bst_package_' . $i . '_name');
        register_setting('bst_settings_group', 'bst_package_' . $i . '_people');
        register_setting('bst_settings_group', 'bst_package_' . $i . '_rooms');
        register_setting('bst_settings_group', 'bst_package_' . $i . '_vehicles');
    }
}
add_action('admin_init', 'bst_register_package_settings');

// Add settings fields for package names, rooms, and vehicles
function bst_add_package_settings_fields() {
    for ($i = 1; $i <= 5; $i++) {
        add_settings_field(
            'bst_package_' . $i . '_name',
            'Package ' . $i . ' Name',
            'bst_package_name_callback',
            'bst_settings_page',
            'bst_package_settings_section',
            array('label_for' => 'bst_package_' . $i . '_name')
        );
        add_settings_field(
            'bst_package_' . $i . '_people',
            'Package ' . $i . ' People',
            'bst_package_people_callback',
            'bst_settings_page',
            'bst_package_settings_section',
            array('label_for' => 'bst_package_' . $i . '_people')
        );
        add_settings_field(
            'bst_package_' . $i . '_rooms',
            'Package ' . $i . ' Rooms',
            'bst_package_rooms_callback',
            'bst_settings_page',
            'bst_package_settings_section',
            array('label_for' => 'bst_package_' . $i . '_rooms')
        );
        add_settings_field(
            'bst_package_' . $i . '_vehicles',
            'Package ' . $i . ' Vehicles',
            'bst_package_vehicles_callback',
            'bst_settings_page',
            'bst_package_settings_section',
            array('label_for' => 'bst_package_' . $i . '_vehicles')
        );
    }
}
add_action('admin_init', 'bst_add_package_settings_fields');

function bst_package_name_callback($args) {
    $option = get_option($args['label_for']);
    echo '<input type="text" id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($args['label_for']) . '" value="' . esc_attr($option) . '" />';
}

function bst_package_people_callback($args) {
    $option = get_option($args['label_for']);
    echo '<input type="number" step="1" id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($args['label_for']) . '" value="' . esc_attr($option) . '" />';
}

function bst_package_rooms_callback($args) {
    $option = get_option($args['label_for']);
    echo '<input type="number" step="1" id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($args['label_for']) . '" value="' . esc_attr($option) . '" />';
}

function bst_package_vehicles_callback($args) {
    $option = get_option($args['label_for']);
    echo '<input type="number" step="1" id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($args['label_for']) . '" value="' . esc_attr($option) . '" />';
}

// Function to get package settings
function get_package_settings() {
    $package_settings = array();
    for ($i = 1; $i <= 5; $i++) {
        $package_settings[$i] = array(
            'name' => get_option('bst_package_' . $i . '_name'),
            'people' => get_option('bst_package_' . $i . '_people'),
            'rooms' => get_option('bst_package_' . $i . '_rooms'),
            'vehicles' => get_option('bst_package_' . $i . '_vehicles')
        );
    }
    return $package_settings;
}

// Replace package pricing table headers with package names using JavaScript
function bst_replace_package_pricing_headers() {
    // Retrieve package names
    $package_names = array();
    for ($i = 1; $i <= 5; $i++) {
        $package_names[$i] = get_option('bst_package_' . $i . '_name', 'Package ' . $i);
    }

    // Output JavaScript to replace header labels
    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'var packageNames = ' . json_encode($package_names) . ';';
    
    // Package pricing field keys
    echo 'var packageFieldKeys = ["field_67b8c66591aee", "field_67b8c6a191aef", "field_67b8c6b091af0", "field_67b8c6ca91af1", "field_67b8c6d791af2"];';
    echo 'packageFieldKeys.forEach(function(fieldKey, index) {';
    echo 'var header = document.querySelector("th[data-key=\'" + fieldKey + "\'] label");';
    echo 'if (header) {';
    echo 'header.innerHTML = packageNames[index + 1];';
    echo '}';
    echo '});';
    
    // Extension pricing field keys
    echo 'var extensionFieldKeys = ["field_6951d7fd40986", "field_6951d7fd40987", "field_6951d7fd40988", "field_6951d7fd40989", "field_6951d7fd4098a"];';
    echo 'extensionFieldKeys.forEach(function(fieldKey, index) {';
    echo 'var header = document.querySelector("th[data-key=\'" + fieldKey + "\'] label");';
    echo 'if (header) {';
    echo 'header.innerHTML = packageNames[index + 1];';
    echo '}';
    echo '});';
    
    echo '});';
    echo '</script>';
}
add_action('admin_head', 'bst_replace_package_pricing_headers');

// AJAX handler for SMS testing
add_action('wp_ajax_bst_send_test_sms', 'bst_handle_test_sms');

function bst_handle_test_sms() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'bst_send_sms')) {
        wp_die('Invalid nonce');
    }
    
    $phone = sanitize_text_field($_POST['phone']);
    $message = sanitize_textarea_field($_POST['message']);
    
    if (empty($phone) || empty($message)) {
        wp_send_json_error('Phone number and message are required');
        return;
    }
    
    // Validate phone number format (basic check)
    if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
        wp_send_json_error('Invalid phone number format. Please use international format (e.g., +1234567890)');
        return;
    }
    
    // Get Twilio settings
    $account_sid = get_option('bst_twilio_account_sid', '');
    $auth_token = get_option('bst_twilio_auth_token', '');
    $from_number = get_option('bst_twilio_phone_number', '');
    
    if (empty($account_sid)) {
        wp_send_json_error('Twilio Account SID not configured. Please go to BST Settings to configure.');
        return;
    }
    
    if (empty($auth_token)) {
        wp_send_json_error('Twilio Auth Token not configured. Please go to BST Settings to configure.');
        return;
    }
    
    if (empty($from_number)) {
        wp_send_json_error('Twilio phone number not configured. Please go to BST Settings to configure.');
        return;
    }
    
    // Send SMS using Twilio
    $result = bst_send_twilio_sms($from_number, $phone, $message, $account_sid, $auth_token);
    
    if ($result['success']) {
        wp_send_json_success("SMS sent successfully to {$phone}. Message: \"{$message}\"");
    } else {
        wp_send_json_error($result['error']);
    }
}

/**
 * Send SMS using Twilio
 */
function bst_send_twilio_sms($from, $to, $message, $account_sid = null, $auth_token = null) {
    if (!$account_sid) {
        $account_sid = get_option('bst_twilio_account_sid', '');
    }
    
    if (!$auth_token) {
        $auth_token = get_option('bst_twilio_auth_token', '');
    }
    
    if (empty($account_sid) || empty($auth_token)) {
        return array('success' => false, 'error' => 'Twilio credentials not configured');
    }
    
    // Prepare the SMS payload
    $sms_data = array(
        'From' => $from,
        'To' => $to,
        'Body' => $message
    );
    
    // Twilio API endpoint
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";
    
    // Prepare headers with basic auth
    $headers = array(
        'Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token),
        'Content-Type' => 'application/x-www-form-urlencoded',
        'User-Agent' => 'BST-WordPress-Plugin/1.0'
    );
    
    // Send the request
    $response = wp_remote_post($url, array(
        'headers' => $headers,
        'body' => http_build_query($sms_data),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return array('success' => false, 'error' => 'Request failed: ' . $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);
    
    if ($response_code >= 200 && $response_code < 300) {
        return array(
            'success' => true, 
            'message_sid' => $response_data['sid'],
            'response' => $response_data
        );
    } else {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        $error_code = isset($response_data['code']) ? $response_data['code'] : $response_code;
        return array('success' => false, 'error' => "Twilio API error ({$error_code}): {$error_message}");
    }
}

/**
 * Send WhatsApp message using Twilio (for future use)
 */
function bst_send_twilio_whatsapp($to, $message, $account_sid = null, $auth_token = null) {
    if (!$account_sid) {
        $account_sid = get_option('bst_twilio_account_sid', '');
    }
    
    if (!$auth_token) {
        $auth_token = get_option('bst_twilio_auth_token', '');
    }
    
    if (empty($account_sid) || empty($auth_token)) {
        return array('success' => false, 'error' => 'Twilio credentials not configured');
    }
    
    // Prepare WhatsApp message payload
    $whatsapp_data = array(
        'From' => 'whatsapp:+14155238886', // Twilio WhatsApp sandbox number (update when you get approved)
        'To' => 'whatsapp:' . $to,
        'Body' => $message
    );
    
    // Twilio API endpoint
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";
    
    // Prepare headers with basic auth
    $headers = array(
        'Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token),
        'Content-Type' => 'application/x-www-form-urlencoded',
        'User-Agent' => 'BST-WordPress-Plugin/1.0'
    );
    
    // Send the request
    $response = wp_remote_post($url, array(
        'headers' => $headers,
        'body' => http_build_query($whatsapp_data),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return array('success' => false, 'error' => 'Request failed: ' . $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);
    
    if ($response_code >= 200 && $response_code < 300) {
        return array(
            'success' => true, 
            'message_sid' => $response_data['sid'],
            'response' => $response_data
        );
    } else {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        $error_code = isset($response_data['code']) ? $response_data['code'] : $response_code;
        return array('success' => false, 'error' => "Twilio WhatsApp error ({$error_code}): {$error_message}");
    }
}

function bst_release_data_cleanup_callback() {
    echo '<div id="release-data-cleanup-operations">';
    echo '<p>' . esc_html__( 'Execute data cleanup tasks for the current release.', 'bst-plugin' ) . '</p>';
    echo '<div style="padding: 15px; background: #f0f0f0; border-left: 4px solid #ccc; color: #666; max-width: 960px;">';
    echo '<p><strong>' . esc_html__( 'No cleanup tasks are currently defined for this release.', 'bst-plugin' ) . '</strong></p>';
    echo '<p>' . esc_html__( 'This section is intentionally kept as a placeholder. When a future release needs a one-time data cleanup, tasks can be added back here.', 'bst-plugin' ) . '</p>';
    echo '</div>';
    echo '</div>';
}

/**
 * Get cleanup tasks defined for the current release
 * Returns array of tasks or empty array if no cleanup needed
 */
function bst_get_release_cleanup_tasks() {
    // Intentionally empty for current release.
    // Add version-specific tasks here in a future release when needed.
    return array();
}

// AJAX handler for wp_mail test
function bst_wpmail_test_send_ajax() {
    check_ajax_referer('bst_gmail_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    $recipient = isset($_POST['recipient']) ? sanitize_email($_POST['recipient']) : '';
    
    if (empty($recipient)) {
        wp_send_json_error(array('message' => 'Please provide a valid email address.'));
    }
    
    $subject = 'Test Email from Blue Strada Tours';
    $message = '<html><body>';
    $message .= '<h2>Test Email</h2>';
    $message .= '<p>This is a test email sent via WordPress wp_mail() function.</p>';
    $message .= '<p>If you received this email, your WordPress email configuration is working correctly.</p>';
    $message .= '<p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';
    $message .= '</body></html>';
    
    // Use configured from email settings
    $from_email = get_option('bst_from_email_address', 'info@bluestradatours.com');
    $from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        "From: {$from_name} <{$from_email}>"
    );

    $failure_detail = '';
    $on_mail_failed = function ( $wp_error ) use ( &$failure_detail ) {
        if ( ! is_wp_error( $wp_error ) ) {
            return;
        }
        $failure_detail = $wp_error->get_error_message();
        $data = $wp_error->get_error_data();
        if ( is_array( $data ) && ! empty( $data['phpmailer'] ) && is_object( $data['phpmailer'] ) && isset( $data['phpmailer']->ErrorInfo ) ) {
            $ei = trim( (string) $data['phpmailer']->ErrorInfo );
            if ( $ei !== '' ) {
                $failure_detail .= ( $failure_detail !== '' ? ' — ' : '' ) . $ei;
            }
        }
    };
    add_action( 'wp_mail_failed', $on_mail_failed, 10, 1 );
    $sent = wp_mail( $recipient, $subject, $message, $headers );
    remove_action( 'wp_mail_failed', $on_mail_failed, 10 );
    
    if ($sent) {
        wp_send_json_success(array('message' => 'Test email sent successfully to ' . $recipient));
    } else {
        $msg = 'Failed to send test email.';
        if ( $failure_detail !== '' ) {
            $msg .= ' ' . $failure_detail;
        } else {
            $msg .= ' On LocalWP, confirm Mailpit is running and SMTP is configured (wp_mail often needs an SMTP plugin or host mail).';
        }
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'BST Email: wp_mail test failed | ' . $failure_detail );
        }
        wp_send_json_error(array('message' => $msg));
    }
}
add_action('wp_ajax_bst_wpmail_test_send', 'bst_wpmail_test_send_ajax');

// AJAX handler for Gmail API test
function bst_gmail_test_send_ajax() {
    check_ajax_referer('bst_gmail_nonce', 'nonce');
    
    $recipient = isset($_POST['recipient']) ? sanitize_email($_POST['recipient']) : '';
    
    if (empty($recipient)) {
        wp_send_json_error(array('message' => 'Please provide a valid email address.'));
    }
    
    try {
        $gmail_api = new BST_Gmail_API();
        
        if (!$gmail_api->is_configured()) {
            wp_send_json_error(array('message' => 'Gmail API is not configured. Please upload credentials and authorize access.'));
        }
        
        $subject = 'Test Email from Blue Strada Tours (Gmail API)';
        $body = '<html><body>';
        $body .= '<h2>Test Email via Gmail API</h2>';
        $body .= '<p>This is a test email sent via Gmail API.</p>';
        $body .= '<p>If you received this email, your Gmail API integration is working correctly.</p>';
        $body .= '<p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';
        $body .= '</body></html>';
        
        $result = $gmail_api->send_email($recipient, $subject, $body);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Test email sent successfully via Gmail API to ' . $recipient));
        } else {
            wp_send_json_error(array('message' => 'Failed to send test email via Gmail API.'));
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_bst_gmail_test_send', 'bst_gmail_test_send_ajax');

// AJAX handler for uploading Gmail credentials
function bst_upload_gmail_credentials_ajax() {
    check_ajax_referer('bst_gmail_nonce', 'nonce');
    
    if (!isset($_FILES['credentials_file'])) {
        wp_send_json_error(array('message' => 'No credentials file uploaded.'));
    }
    
    $file = $_FILES['credentials_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(array('message' => 'File upload error.'));
    }
    
    // Read the JSON file
    $credentials_json = file_get_contents($file['tmp_name']);
    $credentials = json_decode($credentials_json, true);
    
    if (!$credentials || !isset($credentials['web'])) {
        wp_send_json_error(array('message' => 'Invalid credentials file format.'));
    }
    
    // Save credentials to wp-content directory
    $upload_dir = WP_CONTENT_DIR . '/gmail-credentials';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $credentials_path = $upload_dir . '/credentials.json';
    file_put_contents($credentials_path, $credentials_json);
    
    // Update option to mark credentials as uploaded
    update_option('bst_gmail_credentials_uploaded', true);
    
    wp_send_json_success(array('message' => 'Credentials uploaded successfully.'));
}
add_action('wp_ajax_bst_upload_gmail_credentials', 'bst_upload_gmail_credentials_ajax');

// AJAX handler for getting Gmail authorization URL
function bst_gmail_get_auth_url_ajax() {
    check_ajax_referer('bst_gmail_nonce', 'nonce');
    
    try {
        $gmail_api = new BST_Gmail_API();
        $auth_url = $gmail_api->get_authorization_url();
        
        if ($auth_url) {
            wp_send_json_success(array('auth_url' => $auth_url));
        } else {
            wp_send_json_error(array('message' => 'Failed to generate authorization URL.'));
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_bst_gmail_get_auth_url', 'bst_gmail_get_auth_url_ajax');

// AJAX handler for completing Gmail authorization
function bst_gmail_complete_auth_ajax() {
    check_ajax_referer('bst_gmail_nonce', 'nonce');
    
    $auth_code = isset($_POST['auth_code']) ? sanitize_text_field($_POST['auth_code']) : '';
    
    if (empty($auth_code)) {
        wp_send_json_error(array('message' => 'Authorization code is required.'));
    }
    
    try {
        $gmail_api = new BST_Gmail_API();
        $result = $gmail_api->complete_authorization($auth_code);
        
        if ($result) {
            update_option('bst_gmail_api_enabled', true);
            wp_send_json_success(array('message' => 'Gmail API authorized successfully!'));
        } else {
            wp_send_json_error(array('message' => 'Failed to complete authorization.'));
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_bst_gmail_complete_auth', 'bst_gmail_complete_auth_ajax');

// AJAX handler for testing Gmail inbox checking
function bst_gmail_test_inbox_ajax() {
    check_ajax_referer('bst_gmail_nonce', 'nonce');
    
    try {
        $automation = new BST_Email_Automation();
        $result = $automation->check_inbox_manually();
        
        if ($result) {
            $message = sprintf('Inbox check completed successfully. Processed %d emails.', $result['processed']);
            if ($result['matched'] > 0) {
                $message .= sprintf(' Matched %d emails to bookings.', $result['matched']);
            }
            if ($result['unmatched'] > 0) {
                $message .= sprintf(' %d emails could not be matched.', $result['unmatched']);
            }
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => 'Inbox check completed but no new emails found.'));
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_bst_gmail_test_inbox', 'bst_gmail_test_inbox_ajax');


