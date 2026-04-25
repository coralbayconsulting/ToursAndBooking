<?php

/**
 * BST Email Manager Class
 * 
 * Handles email sending with CPT templates and custom table logging
 */
class BST_Email_Manager {
    
    private $gmail_api;
    
    public function __construct() {
        // Initialize Gmail API integration
        $this->gmail_api = new BST_Gmail_API();
        
        // Initialize email system
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Register the email template post type
        add_action('init', array($this, 'register_email_template_post_type'));
        
        // Add meta boxes for email templates
        add_action('add_meta_boxes', array($this, 'add_email_template_meta_boxes'));
        add_action('save_post', array($this, 'save_email_template_meta'));
        
        // Add merge field picker to admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_template_admin_scripts'));
        
        // Add custom columns to email template listing
        add_filter('manage_email-template_posts_columns', array($this, 'add_email_template_columns'));
        add_action('manage_email-template_posts_custom_column', array($this, 'populate_email_template_columns'), 10, 2);
        add_filter('manage_edit-email-template_sortable_columns', array($this, 'make_email_template_columns_sortable'));
        
        // Add AJAX handler for toggling template enabled state
        add_action('wp_ajax_bst_toggle_email_template', array($this, 'ajax_toggle_email_template'));
        
        // Add AJAX handler for sending test emails
        add_action('wp_ajax_bst_send_test_email', array($this, 'ajax_send_test_email'));
        
        // Add inline CSS for enabled toggle
        add_action('admin_head-edit.php', array($this, 'add_email_list_inline_css'));
    }
    
    /**
     * Register the email template custom post type
     */
    public function register_email_template_post_type() {
        $labels = array(
            'name'               => 'Email Templates',
            'singular_name'      => 'Email Template',
            'menu_name'          => 'Email Templates',
            'name_admin_bar'     => 'Email Template',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Email Template',
            'new_item'           => 'New Email Template',
            'edit_item'          => 'Edit Email Template',
            'view_item'          => 'View Email Template',
            'all_items'          => 'Email Templates',
            'search_items'       => 'Search Email Templates',
            'parent_item_colon'  => 'Parent Email Templates:',
            'not_found'          => 'No email templates found.',
            'not_found_in_trash' => 'No email templates found in Trash.',
        );

        $args = array(
            'labels'             => $labels,
            'description'        => 'Email templates for tour booking communications',
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'bst-plugin',
            'query_var'          => true,
            'rewrite'            => array('slug' => 'email-template'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-email-alt',
            'supports'           => array('title', 'editor'),
        );

        register_post_type('email-template', $args);
    }
    
    /**
     * Add meta boxes for email template post type
     */
    public function add_email_template_meta_boxes() {
        add_meta_box(
            'email_template_settings',
            'Email Template Settings',
            array($this, 'render_email_template_meta_box'),
            'email-template',
            'normal',
            'high'
        );
        
        add_meta_box(
            'email_merge_fields',
            'Available Merge Fields',
            array($this, 'render_merge_fields_picker'),
            'email-template',
            'side',
            'default'
        );
        
        add_meta_box(
            'email_test_sender',
            'Send Test Email',
            array($this, 'render_test_email_meta_box'),
            'email-template',
            'side',
            'default'
        );
    }
    
    /**
     * Render email template meta box
     */
    public function render_email_template_meta_box($post) {
        wp_nonce_field('bst_email_template_meta', 'bst_email_template_nonce');
        
        $email_type = get_post_meta($post->ID, '_bst_email_type', true);
        $subject = get_post_meta($post->ID, '_bst_email_subject', true);
        $trigger = get_post_meta($post->ID, '_bst_email_trigger', true) ?: 'on_demand';
        $trigger_days = get_post_meta($post->ID, '_bst_trigger_days', true) ?: '';
        $trigger_status = get_post_meta($post->ID, '_bst_trigger_status', true) ?: '';
        $trigger_payment_method = get_post_meta($post->ID, '_bst_trigger_payment_method', true) ?: '';
        
        // Recipient settings
        $to_type = get_post_meta($post->ID, '_bst_email_to_type', true) ?: 'booking_field';
        $to_custom = get_post_meta($post->ID, '_bst_email_to_custom', true) ?: '';
        $to_field = get_post_meta($post->ID, '_bst_email_to_field', true) ?: 'guest1_email';
        $cc_type = get_post_meta($post->ID, '_bst_email_cc_type', true) ?: 'none';
        $cc_custom = get_post_meta($post->ID, '_bst_email_cc_custom', true) ?: '';
        $cc_field = get_post_meta($post->ID, '_bst_email_cc_field', true) ?: 'guest2_email';
        $bcc_type = get_post_meta($post->ID, '_bst_email_bcc_type', true) ?: 'none';
        $bcc_custom = get_post_meta($post->ID, '_bst_email_bcc_custom', true) ?: '';
        $bcc_field = get_post_meta($post->ID, '_bst_email_bcc_field', true) ?: '';
        ?>
        <table class="form-table">
            <tr>
                <th><label for="email_type">Email Type</label></th>
                <td>
                    <select name="email_type" id="email_type" required>
                        <option value="">Select Email Type</option>
                        <option value="ad hoc" <?php selected($email_type, 'ad hoc'); ?>>Ad Hoc</option>
                        <option value="finalization" <?php selected($email_type, 'finalization'); ?>>Finalization</option>
                        <option value="invoice" <?php selected($email_type, 'invoice'); ?>>Invoice</option>
                        <option value="notification" <?php selected($email_type, 'notification'); ?>>Notification</option>
                        <option value="planning" <?php selected($email_type, 'planning'); ?>>Planning</option>
                        <option value="reservation" <?php selected($email_type, 'reservation'); ?>>Reservation</option>
                    </select>
                    <p class="description">What type of email is this template for?</p>
                </td>
            </tr>
            <tr>
                <th><label for="email_subject">Email Subject</label></th>
                <td>
                    <input type="text" name="email_subject" id="email_subject" value="<?php echo esc_attr($subject); ?>" class="widefat" required>
                    <p class="description">Subject line for the email. You can use merge fields like {booking_id}</p>
                </td>
            </tr>
            <tr>
                <th><label for="email_trigger">Email Trigger</label></th>
                <td>
                    <select name="email_trigger" id="email_trigger" onchange="toggleTriggerOptions()">
                        <option value="on_demand" <?php selected($trigger, 'on_demand'); ?>>On Demand (Manual Only)</option>
                        <option value="booking_completed" <?php selected($trigger, 'booking_completed'); ?>>Booking Completed</option>
                        <option value="booking_pending" <?php selected($trigger, 'booking_pending'); ?>>Booking Pending</option>
                        <option value="booking_pending_reminder" <?php selected($trigger, 'booking_pending_reminder'); ?>>Booking Pending Reminder (TBD)</option>
                        <option value="reservation_created" <?php selected($trigger, 'reservation_created'); ?>>Reservation Created</option>
                        <option value="reservation_reminder" <?php selected($trigger, 'reservation_reminder'); ?>>Reservation Reminder (TBD)</option>
                        <option value="waiting_list_created" <?php selected($trigger, 'waiting_list_created'); ?>>Waiting List Created</option>
                        <option value="finalization_needed" <?php selected($trigger, 'finalization_needed'); ?>>Finalization Needed (TBD)</option>
                        <option value="finalization_reminder" <?php selected($trigger, 'finalization_reminder'); ?>>Finalization Reminder (TBD)</option>
                        <option value="finalization_pending" <?php selected($trigger, 'finalization_pending'); ?>>Finalization Pending</option>
                        <option value="finalization_pending_reminder" <?php selected($trigger, 'finalization_pending_reminder'); ?>>Finalization Pending Reminder (TBD)</option>
                        <option value="finalization_completed" <?php selected($trigger, 'finalization_completed'); ?>>Finalization Completed</option>
                    </select>
                    <p class="description">When should this email be automatically sent?</p>
                </td>
            </tr>
        </table>
        
        <!-- Email Recipients Configuration -->
        <h3 style="margin-top: 25px; margin-bottom: 10px;">Email Recipients</h3>
        <table class="form-table">
            <!-- TO Field -->
            <tr>
                <th><label for="email_to_type">To</label></th>
                <td>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="radio" name="email_to_type" value="booking_field" <?php checked($to_type, 'booking_field'); ?> onchange="toggleRecipientOptions('to')"> 
                        Use booking field:
                        <select name="email_to_field" id="email_to_field" style="margin-left: 10px;">
                            <option value="guest1_email" <?php selected($to_field, 'guest1_email'); ?>>Guest 1 Email</option>
                            <option value="guest2_email" <?php selected($to_field, 'guest2_email'); ?>>Guest 2 Email</option>
                        </select>
                    </label>
                    <label style="display: block;">
                        <input type="radio" name="email_to_type" value="custom" <?php checked($to_type, 'custom'); ?> onchange="toggleRecipientOptions('to')"> 
                        Custom email address:
                        <input type="email" name="email_to_custom" id="email_to_custom" value="<?php echo esc_attr($to_custom); ?>" style="margin-left: 10px; width: 250px;" placeholder="email@example.com">
                    </label>
                    <p class="description">Primary recipient of the email.</p>
                </td>
            </tr>
            
            <!-- CC Field -->
            <tr>
                <th><label for="email_cc_type">CC</label></th>
                <td>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="radio" name="email_cc_type" value="none" <?php checked($cc_type, 'none'); ?> onchange="toggleRecipientOptions('cc')"> 
                        No CC
                    </label>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="radio" name="email_cc_type" value="booking_field" <?php checked($cc_type, 'booking_field'); ?> onchange="toggleRecipientOptions('cc')"> 
                        Use booking field:
                        <select name="email_cc_field" id="email_cc_field" style="margin-left: 10px;">
                            <option value="guest1_email" <?php selected($cc_field, 'guest1_email'); ?>>Guest 1 Email</option>
                            <option value="guest2_email" <?php selected($cc_field, 'guest2_email'); ?>>Guest 2 Email</option>
                        </select>
                    </label>
                    <label style="display: block;">
                        <input type="radio" name="email_cc_type" value="custom" <?php checked($cc_type, 'custom'); ?> onchange="toggleRecipientOptions('cc')"> 
                        Custom email address:
                        <input type="email" name="email_cc_custom" id="email_cc_custom" value="<?php echo esc_attr($cc_custom); ?>" style="margin-left: 10px; width: 250px;" placeholder="email@example.com">
                    </label>
                    <p class="description">Optional carbon copy recipient.</p>
                </td>
            </tr>
            
            <!-- BCC Field -->
            <tr>
                <th><label for="email_bcc_type">BCC</label></th>
                <td>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="radio" name="email_bcc_type" value="none" <?php checked($bcc_type, 'none'); ?> onchange="toggleRecipientOptions('bcc')"> 
                        No BCC
                    </label>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="radio" name="email_bcc_type" value="booking_field" <?php checked($bcc_type, 'booking_field'); ?> onchange="toggleRecipientOptions('bcc')"> 
                        Use booking field:
                        <select name="email_bcc_field" id="email_bcc_field" style="margin-left: 10px;">
                            <option value="guest1_email" <?php selected($bcc_field, 'guest1_email'); ?>>Guest 1 Email</option>
                            <option value="guest2_email" <?php selected($bcc_field, 'guest2_email'); ?>>Guest 2 Email</option>
                        </select>
                    </label>
                    <label style="display: block;">
                        <input type="radio" name="email_bcc_type" value="custom" <?php checked($bcc_type, 'custom'); ?> onchange="toggleRecipientOptions('bcc')"> 
                        Custom email address:
                        <input type="email" name="email_bcc_custom" id="email_bcc_custom" value="<?php echo esc_attr($bcc_custom); ?>" style="margin-left: 10px; width: 250px;" placeholder="email@example.com">
                    </label>
                    <p class="description">Optional blind carbon copy recipient.</p>
                </td>
            </tr>
        </table>
        
        <!-- Attachments Section -->
        <h3 style="margin-top: 25px; margin-bottom: 10px;">Email Attachments</h3>
        <?php
        $attachment_id = get_post_meta($post->ID, '_bst_email_attachment', true);
        if ($attachment_id) {
            $attachment_url = wp_get_attachment_url($attachment_id);
            $attachment_filename = basename($attachment_url);
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="email_attachment">Attachment</label></th>
                <td>
                    <input type="hidden" name="email_attachment_id" id="email_attachment_id" value="<?php echo esc_attr($attachment_id); ?>">
                    <button type="button" class="button" id="upload_attachment_button">
                        <span class="dashicons dashicons-paperclip" style="vertical-align: middle;"></span> Upload Attachment
                    </button>
                    <button type="button" class="button" id="remove_attachment_button" style="<?php echo $attachment_id ? '' : 'display:none;'; ?>margin-left: 10px;">
                        <span class="dashicons dashicons-no" style="vertical-align: middle;"></span> Remove
                    </button>
                    <div id="attachment_preview" style="margin-top: 10px; <?php echo $attachment_id ? '' : 'display:none;'; ?>">
                        <span class="dashicons dashicons-media-document" style="color: #0073aa;"></span>
                        <span id="attachment_filename"><?php echo isset($attachment_filename) ? esc_html($attachment_filename) : ''; ?></span>
                    </div>
                    <p class="description">Optional file to attach to this email (e.g., PDF, image). Maximum file size: <?php echo wp_max_upload_size() / (1024 * 1024); ?>MB</p>
                </td>
            </tr>
        </table>
        
        <!-- Trigger Configuration Options -->
        <div id="trigger-options" style="margin-top: 20px;">
            <div id="days-option" style="display: none;">
                <label for="trigger_days"><strong>Days:</strong></label>
                <input type="number" name="trigger_days" id="trigger_days" value="<?php echo esc_attr($trigger_days); ?>" min="1" max="365" style="width: 80px;">
                <span id="days-help-text"></span>
            </div>
            
            <div id="status-option" style="display: none; margin-top: 10px;">
                <label for="trigger_status"><strong>Booking Status:</strong></label>
                <select name="trigger_status" id="trigger_status">
                    <option value="">Any Status</option>
                    <option value="Booked" <?php selected($trigger_status, 'Booked'); ?>>Booked</option>
                    <option value="Finalized" <?php selected($trigger_status, 'Finalized'); ?>>Finalized</option>
                    <option value="Pending" <?php selected($trigger_status, 'Pending'); ?>>Pending</option>
                    <option value="Reserved" <?php selected($trigger_status, 'Reserved'); ?>>Reserved</option>
                    <option value="Waiting List" <?php selected($trigger_status, 'Waiting List'); ?>>Waiting List</option>
                </select>
            </div>
            
            <div id="payment-method-option" style="display: none; margin-top: 10px;">
                <label for="trigger_payment_method"><strong>Payment Method:</strong></label>
                <select name="trigger_payment_method" id="trigger_payment_method">
                    <option value="">Any Payment Method</option>
                    <option value="Bank Wire" <?php selected($trigger_payment_method, 'Bank Wire'); ?>>Bank Transfer</option>
                    <option value="Credit Card" <?php selected($trigger_payment_method, 'Credit Card'); ?>>Credit Card</option>
                    <option value="PayPal" <?php selected($trigger_payment_method, 'PayPal'); ?>>PayPal</option>
                </select>
            </div>
        </div>
        
        <style>
        .bst-email-template-meta {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .bst-email-template-meta h4 {
            margin-top: 0;
            color: #23282d;
        }
        
        .trigger-config-section {
            background: #fff;
            padding: 10px;
            border-left: 3px solid #0073aa;
            margin-top: 10px;
        }
        
        .help-text {
            font-style: italic;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        </style>
        
        <script>
        function toggleTriggerOptions() {
            var trigger = document.getElementById('email_trigger').value;
            var daysOption = document.getElementById('days-option');
            var statusOption = document.getElementById('status-option');
            var paymentOption = document.getElementById('payment-method-option');
            var daysHelp = document.getElementById('days-help-text');
            
            // Hide all options first
            daysOption.style.display = 'none';
            statusOption.style.display = 'none';
            paymentOption.style.display = 'none';
            
            // Show relevant options based on trigger
            switch(trigger) {
                case 'booking_pending_reminder':
                    daysOption.style.display = 'block';
                    daysHelp.textContent = 'Send reminder X days after booking created with pending status';
                    paymentOption.style.display = 'block';
                    break;
                    
                case 'reservation_reminder':
                    daysOption.style.display = 'block';
                    daysHelp.textContent = 'Send reminder X days after reservation created but not booked';
                    break;
                    
                case 'finalization_needed':
                    daysOption.style.display = 'block';
                    daysHelp.textContent = 'Send when tour start date is X days away (default 70)';
                    break;
                    
                case 'finalization_reminder':
                    daysOption.style.display = 'block';
                    daysHelp.textContent = 'Send reminder X days after finalization email sent but not completed';
                    break;
                    
                case 'finalization_pending_reminder':
                    daysOption.style.display = 'block';
                    daysHelp.textContent = 'Send reminder X days after finalization submitted but payment pending';
                    paymentOption.style.display = 'block';
                    break;
                    
                case 'booking_pending':
                case 'finalization_pending':
                    paymentOption.style.display = 'block';
                    break;
            }
        }
        
        function toggleRecipientOptions(type) {
            var radioValue = document.querySelector('input[name="email_' + type + '_type"]:checked').value;
            var fieldSelect = document.getElementById('email_' + type + '_field');
            var customInput = document.getElementById('email_' + type + '_custom');
            
            // Enable/disable appropriate fields
            if (radioValue === 'booking_field') {
                fieldSelect.disabled = false;
                customInput.disabled = true;
                fieldSelect.style.opacity = '1';
                customInput.style.opacity = '0.5';
            } else if (radioValue === 'custom') {
                fieldSelect.disabled = true;
                customInput.disabled = false;
                fieldSelect.style.opacity = '0.5';
                customInput.style.opacity = '1';

                // Focus custom input so merge fields insert on first attempt
                setTimeout(function() {
                    if (customInput && !customInput.disabled) {
                        customInput.focus();
                        customInput.select();
                    }
                }, 0);
            } else { // none
                fieldSelect.disabled = true;
                customInput.disabled = true;
                fieldSelect.style.opacity = '0.5';
                customInput.style.opacity = '0.5';
            }
        }
        
        // Initialize on page load
        jQuery(document).ready(function($) {
            toggleTriggerOptions();
            toggleRecipientOptions('to');
            toggleRecipientOptions('cc'); 
            toggleRecipientOptions('bcc');
            
            // Attachment uploader
            var mediaUploader;
            $('#upload_attachment_button').click(function(e) {
                e.preventDefault();
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                mediaUploader = wp.media({
                    title: 'Select Email Attachment',
                    button: {
                        text: 'Use this file'
                    },
                    multiple: false
                });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#email_attachment_id').val(attachment.id);
                    $('#attachment_filename').text(attachment.filename);
                    $('#attachment_preview').show();
                    $('#remove_attachment_button').show();
                });
                mediaUploader.open();
            });
            
            $('#remove_attachment_button').click(function(e) {
                e.preventDefault();
                $('#email_attachment_id').val('');
                $('#attachment_filename').text('');
                $('#attachment_preview').hide();
                $(this).hide();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save email template meta data
     */
    public function save_email_template_meta($post_id) {
        if (!isset($_POST['bst_email_template_nonce']) || !wp_verify_nonce($_POST['bst_email_template_nonce'], 'bst_email_template_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (get_post_type($post_id) !== 'email-template') {
            return;
        }
        
        if (isset($_POST['email_type'])) {
            update_post_meta($post_id, '_bst_email_type', sanitize_text_field($_POST['email_type']));
        }
        
        if (isset($_POST['email_subject'])) {
            update_post_meta($post_id, '_bst_email_subject', sanitize_text_field($_POST['email_subject']));
        }
        
        if (isset($_POST['email_trigger'])) {
            update_post_meta($post_id, '_bst_email_trigger', sanitize_text_field($_POST['email_trigger']));
        }
        
        if (isset($_POST['trigger_days'])) {
            update_post_meta($post_id, '_bst_trigger_days', intval($_POST['trigger_days']));
        }
        
        if (isset($_POST['trigger_status'])) {
            update_post_meta($post_id, '_bst_trigger_status', sanitize_text_field($_POST['trigger_status']));
        }
        
        if (isset($_POST['trigger_payment_method'])) {
            update_post_meta($post_id, '_bst_trigger_payment_method', sanitize_text_field($_POST['trigger_payment_method']));
        }
        
        // Save recipient settings
        if (isset($_POST['email_to_type'])) {
            update_post_meta($post_id, '_bst_email_to_type', sanitize_text_field($_POST['email_to_type']));
        }
        
        if (isset($_POST['email_to_custom'])) {
            update_post_meta($post_id, '_bst_email_to_custom', sanitize_email($_POST['email_to_custom']));
        }
        
        if (isset($_POST['email_to_field'])) {
            update_post_meta($post_id, '_bst_email_to_field', sanitize_text_field($_POST['email_to_field']));
        }
        
        if (isset($_POST['email_cc_type'])) {
            update_post_meta($post_id, '_bst_email_cc_type', sanitize_text_field($_POST['email_cc_type']));
        }
        
        if (isset($_POST['email_cc_custom'])) {
            update_post_meta($post_id, '_bst_email_cc_custom', sanitize_email($_POST['email_cc_custom']));
        }
        
        if (isset($_POST['email_cc_field'])) {
            update_post_meta($post_id, '_bst_email_cc_field', sanitize_text_field($_POST['email_cc_field']));
        }
        
        if (isset($_POST['email_bcc_type'])) {
            update_post_meta($post_id, '_bst_email_bcc_type', sanitize_text_field($_POST['email_bcc_type']));
        }
        
        if (isset($_POST['email_bcc_custom'])) {
            update_post_meta($post_id, '_bst_email_bcc_custom', sanitize_email($_POST['email_bcc_custom']));
        }
        
        if (isset($_POST['email_bcc_field'])) {
            update_post_meta($post_id, '_bst_email_bcc_field', sanitize_text_field($_POST['email_bcc_field']));
        }
        
        // Save attachment
        if (isset($_POST['email_attachment_id'])) {
            $attachment_id = intval($_POST['email_attachment_id']);
            if ($attachment_id) {
                update_post_meta($post_id, '_bst_email_attachment', $attachment_id);
            } else {
                delete_post_meta($post_id, '_bst_email_attachment');
            }
        }
    }
    
    /**
     * Send reservation email with booking details and payment link
     */
    public function send_reservation_email($booking_id, $template_id = null) {
        return $this->send_email('reservation', $booking_id, $template_id);
    }
    
    /**
     * Send finalization email with final tour details
     */
    public function send_finalization_email($booking_id, $template_id = null) {
        return $this->send_email('finalization', $booking_id, $template_id);
    }
    
    /**
     * Send invoice email with booking invoice
     */
    public function send_invoice_email($booking_id, $template_id = null) {
        return $this->send_email('invoice', $booking_id, $template_id);
    }
    
    /**
     * Send notification email (general purpose)
     */
    public function send_notification_email($booking_id, $template_id = null) {
        return $this->send_email('notification', $booking_id, $template_id);
    }
    
    /**
     * Send planning email with tour planning details
     */
    public function send_planning_email($booking_id, $template_id = null) {
        return $this->send_email('planning', $booking_id, $template_id);
    }
    
    /**
     * Generic email sending method
     */
    private function send_email($email_type, $booking_id, $template_id = null) {
        // Get booking data
        $booking = $this->get_booking_data($booking_id);
        if (!$booking) {
            return array(
                'success' => false,
                'error' => 'Booking not found'
            );
        }
        
        // Get template from CPT
        $template = $this->get_email_template($email_type, $template_id);
        if (!$template) {
            return array(
                'success' => false,
                'error' => 'Template not found'
            );
        }
        
        // Process merge fields
        $merge_fields = new BST_Email_Merge_Fields();
        $subject = $merge_fields->process_merge_fields($template['subject'], $booking);
        $content = $merge_fields->process_merge_fields($template['content'], $booking);
        
        // Wrap content in proper HTML document structure
        $html_content = $this->wrap_email_html($content);
        
        // Convert relative image URLs to absolute URLs for email compatibility
        $html_content = $this->convert_image_urls_to_absolute($html_content);
        
        // Get recipients based on template configuration
        $recipients = $this->get_template_recipients($template['id'], $booking);
        if (!$recipients['to']) {
            return array(
                'success' => false,
                'error' => 'No TO email address configured for template'
            );
        }
        
        // Prepare headers for CC and BCC
        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($recipients['cc']) {
            $headers[] = 'Cc: ' . $recipients['cc'];
        }
        if ($recipients['bcc']) {
            $headers[] = 'Bcc: ' . $recipients['bcc'];
        }
        
        // Create preliminary log entry to get an ID for tracking
        $preliminary_log_id = $this->create_preliminary_email_log(
            $booking_id, 
            $template['id'], 
            $email_type, 
            $recipients['to'], 
            $subject, 
            $html_content
        );
        
        // Get attachment if configured
        $attachment_id = get_post_meta($template['id'], '_bst_email_attachment', true);
        $attachments = array();
        if ($attachment_id) {
            $attachment_path = get_attached_file($attachment_id);
            if ($attachment_path && file_exists($attachment_path)) {
                $attachments[] = $attachment_path;
            }
        }
        
        // Send email using configured method
        $email_method = get_option('bst_email_method', 'wp_mail');
        
        if ($email_method === 'gmail') {
            // Send using Gmail API with configured from name
            $from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
            $send_result = $this->gmail_api->send_email(
                $recipients['to'], 
                $subject, 
                $html_content, 
                $from_name,
                $booking_id,
                $preliminary_log_id,
                $attachments
            );
        } else {
            // Send using WordPress wp_mail - need to add tracking headers
            $message_id = $this->generate_message_id();
            $from_email = get_option('bst_from_email_address', 'info@bluestradatours.com');
            $from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
            $headers[] = "From: {$from_name} <{$from_email}>";
            $headers[] = "Message-ID: {$message_id}";
            $headers[] = "X-BST-Booking-ID: {$booking_id}";
            $headers[] = "X-BST-Email-Log-ID: {$preliminary_log_id}";
            
            $sent = wp_mail(
                $recipients['to'], 
                $subject, 
                $html_content, 
                $headers,
                $attachments
            );
            
            $send_result = array(
                'success' => $sent,
                'gmail_message_id' => null,
                'gmail_thread_id' => null,
                'message_id' => $message_id,
                'message' => $sent ? 'Email sent successfully via wp_mail' : 'Email send failed via wp_mail'
            );
        }
        
        // Update the log entry with final results
        $this->update_email_log_with_results(
            $preliminary_log_id,
            $send_result['success'],
            $send_result['gmail_message_id'],
            $send_result['gmail_thread_id'],
            $send_result['message_id']
        );
        
        $result = array(
            'success' => $send_result['success'],
            'recipient' => $recipients['to'],
            'cc' => $recipients['cc'],
            'bcc' => $recipients['bcc'],
            'subject' => $subject,
            'log_id' => $preliminary_log_id,
            'gmail_message_id' => $send_result['gmail_message_id'],
            'gmail_thread_id' => $send_result['gmail_thread_id'],
            'message_id' => $send_result['message_id'],
            'message' => $send_result['message']
        );
        
        if (!$send_result['success']) {
            $result['error'] = $send_result['message'] ?? 'Email sending failed';
        }
        
        return $result;
    }
    
    /**
     * Get email template from CPT
     */
    public function get_email_template($email_type, $template_id = null) {
        if ($template_id) {
            // Get specific template
            $template_post = get_post($template_id);
            if (!$template_post || $template_post->post_type !== 'email-template') {
                return false;
            }
        } else {
            // Get default template for email type
            $templates = get_posts(array(
                'post_type' => 'email-template',
                'meta_key' => '_bst_email_type',
                'meta_value' => $email_type,
                'posts_per_page' => 1,
                'post_status' => 'publish'
            ));
            
            if (empty($templates)) {
                return false;
            }
            
            $template_post = $templates[0];
        }
        
        return array(
            'id' => $template_post->ID,
            'subject' => wp_unslash(get_post_meta($template_post->ID, '_bst_email_subject', true)),
            'content' => $template_post->post_content,
            'type' => get_post_meta($template_post->ID, '_bst_email_type', true)
        );
    }
    
    /**
     * Get email template by ID
     */
    public function get_email_template_by_id($template_id) {
        $template_post = get_post($template_id);
        
        if (!$template_post || $template_post->post_type !== 'email-template') {
            return false;
        }
        
        return array(
            'id' => $template_post->ID,
            'subject' => wp_unslash(get_post_meta($template_post->ID, '_bst_email_subject', true)),
            'content' => $template_post->post_content,
            'type' => get_post_meta($template_post->ID, '_bst_email_type', true)
        );
    }
    
    /**
     * Get booking data including tour and tour-date information
     */
    private function get_booking_data($booking_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bst_tour_booking';
        
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name WHERE id = %d
        ", $booking_id));
        
        return $booking;
    }
    
    /**
     * Get email address from booking
     */
    private function get_booking_email($booking) {
        // Try guest1_email first, then fallback to customer email if available
        if (!empty($booking->guest1_email)) {
            return $booking->guest1_email;
        }
        
        // If no guest email, try to get customer email
        if (!empty($booking->customer_id)) {
            global $wpdb;
            $customer_table = $wpdb->prefix . 'bst_customers';
            $customer_email = $wpdb->get_var($wpdb->prepare("
                SELECT email FROM $customer_table WHERE id = %d
            ", $booking->customer_id));
            
            if ($customer_email) {
                return $customer_email;
            }
        }
        
        return false;
    }
    
    /**
     * Get template recipients based on template configuration
     */
    private function get_template_recipients($template_id, $booking) {
        $recipients = array(
            'to' => null,
            'cc' => null,
            'bcc' => null
        );
        
        // Get TO recipient
        $to_type = get_post_meta($template_id, '_bst_email_to_type', true) ?: 'booking_field';
        if ($to_type === 'custom') {
            $recipients['to'] = get_post_meta($template_id, '_bst_email_to_custom', true);
        } else {
            $to_field = get_post_meta($template_id, '_bst_email_to_field', true) ?: 'guest1_email';
            $recipients['to'] = $this->get_email_from_booking_field($booking, $to_field);
        }
        
        // Get CC recipient
        $cc_type = get_post_meta($template_id, '_bst_email_cc_type', true) ?: 'none';
        if ($cc_type === 'custom') {
            $recipients['cc'] = get_post_meta($template_id, '_bst_email_cc_custom', true);
        } elseif ($cc_type === 'booking_field') {
            $cc_field = get_post_meta($template_id, '_bst_email_cc_field', true) ?: 'guest2_email';
            $recipients['cc'] = $this->get_email_from_booking_field($booking, $cc_field);
        }
        
        // Get BCC recipient
        $bcc_type = get_post_meta($template_id, '_bst_email_bcc_type', true) ?: 'none';
        if ($bcc_type === 'custom') {
            $recipients['bcc'] = get_post_meta($template_id, '_bst_email_bcc_custom', true);
        } elseif ($bcc_type === 'booking_field') {
            $bcc_field = get_post_meta($template_id, '_bst_email_bcc_field', true) ?: '';
            $recipients['bcc'] = $this->get_email_from_booking_field($booking, $bcc_field);
        }
        
        // Clean up empty recipients
        $recipients['cc'] = !empty($recipients['cc']) ? $recipients['cc'] : null;
        $recipients['bcc'] = !empty($recipients['bcc']) ? $recipients['bcc'] : null;
        
        return $recipients;
    }
    
    /**
     * Get email address from specific booking field
     */
    private function get_email_from_booking_field($booking, $field) {
        switch ($field) {
            case 'guest1_email':
                return !empty($booking->guest1_email) ? $booking->guest1_email : null;
            case 'guest2_email':
                return !empty($booking->guest2_email) ? $booking->guest2_email : null;
            default:
                // Fallback to guest1 email
                return !empty($booking->guest1_email) ? $booking->guest1_email : null;
        }
    }
    
    /**
     * Wrap email content in proper HTML document structure
     */
    private function wrap_email_html($content) {
        // Check if content already has <html> tags
        if (stripos($content, '<html') !== false) {
            return $content;
        }
        
        // Check if content has <body> tags
        if (stripos($content, '<body') !== false) {
            return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Email</title></head>' . $content . '</html>';
        }
        
        // Process content only if it's mostly plain text
        $processed_content = $this->should_process_content($content) ? $this->format_email_content($content) : $content;
        
        // Wrap in complete HTML document
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 20px; 
        }
        h1, h2, h3 { color: #2c3e50; }
        p { margin-bottom: 15px; }
        a { color: #3498db; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .email-content { background: #fff; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <div class="email-content">
        ' . $processed_content . '
    </div>
</body>
</html>';
    }
    
    /**
     * Convert relative image URLs to absolute URLs for email compatibility
     * 
     * WordPress media uploader creates relative paths (/wp-content/uploads/...)
     * which work in email preview but fail in sent emails to external clients
     */
    private function convert_image_urls_to_absolute($html_content) {
        $home_url = get_home_url();
        
        // Convert relative image src paths to absolute URLs
        // Pattern matches: <img ... src="/path/to/image" ... >
        $html_content = preg_replace(
            '/<img([^>]*?)src=["\']\/([^"\']*?)["\']([^>]*?)>/i',
            '<img$1src="' . $home_url . '/$2"$3>',
            $html_content
        );
        
        return $html_content;
    }
    
    /**
     * Determine if content should be processed or left as-is
     */
    private function should_process_content($content) {
        // Check if content is mostly rich HTML (multiple complex tags or structured HTML)
        $complex_tag_count = 0;
        $complex_tags = ['div', 'table', 'figure', 'section', 'article', 'header', 'footer', 'nav'];
        
        foreach ($complex_tags as $tag) {
            if (stripos($content, '<' . $tag) !== false) {
                $complex_tag_count++;
            }
        }
        
        // If it has multiple complex tags or looks like structured HTML, don't process
        if ($complex_tag_count > 1) {
            return false;
        }
        
        // Check if it's mostly plain text with just a few HTML elements mixed in
        // Count total lines vs HTML tags
        $total_lines = substr_count($content, "\n") + 1;
        $html_tag_count = preg_match_all('/<[^>]+>/', $content);
        
        // If it's mostly text with just a few HTML elements, we can process it carefully
        return ($html_tag_count <= 3 && $total_lines > 2);
    }
    
    /**
     * Format email content for better HTML display (hybrid processing)
     */
    private function format_email_content($content) {
        // For mixed content (plain text + some HTML), we need careful processing
        
        // First, protect existing HTML tags by temporarily replacing them
        $protected_html = array();
        $placeholder_prefix = '___HTML_PLACEHOLDER_';
        $counter = 0;
        
        // Find and protect HTML tags
        $content = preg_replace_callback('/<[^>]+>/', function($matches) use (&$protected_html, $placeholder_prefix, &$counter) {
            $placeholder = $placeholder_prefix . $counter . '___';
            $protected_html[$placeholder] = $matches[0];
            $counter++;
            return $placeholder;
        }, $content);
        
        // Now process as plain text
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        
        // Convert line breaks to HTML
        $content = nl2br($content);
        
        // Convert URLs to clickable links
        $content = $this->make_clickable_links($content);
        
        // Restore protected HTML tags
        foreach ($protected_html as $placeholder => $original_html) {
            $content = str_replace(htmlspecialchars($placeholder), $original_html, $content);
        }
        
        return $content;
    }
    
    /**
     * Convert URLs in plain text to clickable HTML links
     */
    private function make_clickable_links($text) {
        // Simple URL pattern for plain text only
        $url_pattern = '#\b(?:https?://|www\.)[^\s<>"\']+#i';
        
        return preg_replace_callback($url_pattern, function($matches) {
            $url = $matches[0];
            
            // Add http:// if it starts with www.
            if (strpos($url, 'www.') === 0) {
                $url = 'http://' . $url;
            }
            
            return '<a href="' . $url . '">' . $matches[0] . '</a>';
        }, $text);
    }
    
    /**
     * Log email to custom database table
     */
    private function log_email_to_database($booking_id, $template_id, $email_type, $recipient, $subject, $content, $success, $gmail_message_id = null, $gmail_thread_id = null, $message_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bst_email_log';
        $current_user = get_current_user_id();
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'booking_id' => $booking_id,
                'template_id' => $template_id,
                'email_type' => $email_type,
                'direction' => 'outbound',
                'recipient_email' => $recipient,
                'subject' => $subject,
                'content' => $content,
                'sent_date' => current_time('mysql'),
                'sent_by' => get_userdata($current_user)->display_name ?? 'System',
                'sent_successfully' => $success ? 1 : 0,
                'gmail_message_id' => $gmail_message_id,
                'gmail_thread_id' => $gmail_thread_id,
                'message_id' => $message_id
            ),
            array(
                '%d', // booking_id
                '%d', // template_id
                '%s', // email_type
                '%s', // direction
                '%s', // recipient_email
                '%s', // subject
                '%s', // content
                '%s', // sent_date
                '%s', // sent_by
                '%d', // sent_successfully
                '%s', // gmail_message_id
                '%s', // gmail_thread_id
                '%s'  // message_id
            )
        );
        
        if ($result === false) {
            error_log('BST Email Log: Failed to log email - ' . $wpdb->last_error);
        }
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get email log for a booking
     */
    public function get_booking_email_log($booking_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bst_email_log';
        
        $emails = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE booking_id = %d 
            ORDER BY sent_date DESC
        ", $booking_id), ARRAY_A);
        
        return $emails;
    }
    
    /**
     * Get last sent email of specific type for a booking
     */
    public function get_last_email_sent($booking_id, $email_type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bst_email_log';
        
        $email = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE booking_id = %d AND email_type = %s AND sent_successfully = 1
            ORDER BY sent_date DESC 
            LIMIT 1
        ", $booking_id, $email_type), ARRAY_A);
        
        return $email;
    }
    
    /**
     * Resend an email from the log
     */
    public function resend_email($log_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bst_email_log';
        
        // Get original email log entry
        $original = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name WHERE id = %d
        ", $log_id), ARRAY_A);
        
        if (!$original) {
            return array(
                'success' => false,
                'error' => 'Original email not found'
            );
        }
        
        // Send the email again using configured method
        $email_method = get_option('bst_email_method', 'wp_mail');
        
        $resend_error = '';

        if ($email_method === 'gmail') {
            // Send using Gmail API with configured from name
            $from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
            $send_result = $this->gmail_api->send_email(
                $original['recipient_email'], 
                $original['subject'], 
                $original['content'], 
                $from_name,
                $original['booking_id']
            );
            $sent = $send_result['success'];
            $gmail_message_id = $send_result['gmail_message_id'];
            $gmail_thread_id = $send_result['gmail_thread_id'];
            if (!$sent) {
                $resend_error = !empty($send_result['error'])
                    ? (string) $send_result['error']
                    : 'Failed to resend email via Gmail API.';
            }
        } else {
            // Send using WordPress wp_mail with configured from email
            $from_email = get_option('bst_from_email_address', 'info@bluestradatours.com');
            $from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                "From: {$from_name} <{$from_email}>"
            );
            $sent = wp_mail(
                $original['recipient_email'], 
                $original['subject'], 
                $original['content'], 
                $headers
            );
            $gmail_message_id = null;
            $gmail_thread_id = null;
            if (!$sent) {
                $resend_error = 'Failed to resend email via wp_mail.';
            }
        }
        
        // Log the resend
        $new_log_id = $this->log_email_to_database(
            $original['booking_id'],
            $original['template_id'],
            $original['email_type'],
            $original['recipient_email'],
            $original['subject'],
            $original['content'],
            $sent,
            $gmail_message_id,
            $gmail_thread_id
        );
        
        // Note: resent_from field removed - no longer tracking resend linkage
        
        return array(
            'success' => $sent,
            'new_log_id' => $new_log_id,
            'error' => $resend_error
        );
    }
    
    /**
     * Get available templates for an email type
     */
    public function get_templates_by_type($email_type) {
        $templates = get_posts(array(
            'post_type' => 'email-template',
            'meta_key' => '_bst_email_type',
            'meta_value' => $email_type,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $formatted_templates = array();
        foreach ($templates as $template) {
            $formatted_templates[] = array(
                'id' => $template->ID,
                'title' => $template->post_title,
                'subject' => get_post_meta($template->ID, '_bst_email_subject', true)
            );
        }
        
        return $formatted_templates;
    }
    
    /**
     * Enqueue scripts for email template admin pages
     */
    public function enqueue_template_admin_scripts($hook) {
        global $post_type, $post;
        
        if ($post_type !== 'email-template') {
            return;
        }
        
        // Add inline CSS for better styling
        wp_add_inline_style('wp-admin', '
            #email_template_settings .form-table th {
                width: 150px;
                vertical-align: top;
                padding-top: 15px;
            }
            
            #trigger-options > div {
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 3px;
                margin-bottom: 10px;
            }
            
            #trigger-options label {
                display: inline-block;
                width: 120px;
                font-weight: 600;
            }
            
            #trigger-options input, #trigger-options select {
                margin-left: 10px;
            }
            
            #days-help-text {
                font-style: italic;
                color: #666;
                margin-left: 10px;
            }
            
            .trigger-description {
                background: #e7f3ff;
                border-left: 4px solid #0073aa;
                padding: 10px 15px;
                margin: 15px 0;
            }
            
            #bst-test-email-container {
                padding: 10px;
            }
            
            #bst-test-email-input {
                width: 100%;
                margin-bottom: 10px;
            }
            
            #bst-test-email-result {
                margin-top: 10px;
                padding: 10px;
                border-radius: 4px;
                display: none;
            }
            
            #bst-test-email-result.success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }
            
            #bst-test-email-result.error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }
        ');
        
        // Add JavaScript for test email functionality
        if ($post && $post->post_status !== 'auto-draft') {
            wp_add_inline_script('jquery', '
                jQuery(document).ready(function($) {
                    $("#bst-send-test-email").on("click", function(e) {
                        e.preventDefault();
                        
                        var button = $(this);
                        var email = $("#bst-test-email-input").val();
                        var templateId = $("#post_ID").val();
                        var resultDiv = $("#bst-test-email-result");
                        
                        if (!email) {
                            resultDiv.removeClass("success").addClass("error").text("Please enter an email address").show();
                            return;
                        }
                        
                        button.prop("disabled", true).text("Sending...");
                        resultDiv.hide();
                        
                        $.ajax({
                            url: ajaxurl,
                            type: "POST",
                            data: {
                                action: "bst_send_test_email",
                                template_id: templateId,
                                test_email: email,
                                _wpnonce: $("#bst_test_email_nonce").val()
                            },
                            success: function(response) {
                                if (response.success) {
                                    resultDiv.removeClass("error").addClass("success").html(response.data.message).show();
                                } else {
                                    resultDiv.removeClass("success").addClass("error").text(response.data.message || "Failed to send test email").show();
                                }
                            },
                            error: function() {
                                resultDiv.removeClass("success").addClass("error").text("Network error occurred").show();
                            },
                            complete: function() {
                                button.prop("disabled", false).text("Send Test Email");
                            }
                        });
                    });
                });
            ');
        }
    }
    
    /**
     * Add custom columns to email template listing
     */
    public function add_email_template_columns($columns) {
        // Insert new columns after title
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'title') {
                $new_columns['email_enabled'] = 'Auto Send';
                $new_columns['email_type'] = 'Type';
                $new_columns['email_trigger'] = 'Trigger';
                $new_columns['trigger_config'] = 'Configuration';
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Populate custom columns with data
     */
    public function populate_email_template_columns($column, $post_id) {
        switch ($column) {
            case 'email_enabled':
                $automation_enabled = get_option('bst_email_automation_enabled', false);
                $enabled = get_post_meta($post_id, '_bst_email_enabled', true);
                $is_enabled = ($enabled === '' || $enabled === '1' || $enabled === 'yes'); // Default to enabled
                $trigger = get_post_meta($post_id, '_bst_email_trigger', true);
                
                // Define time-based triggers that are affected by global setting
                $time_based_triggers = array(
                    'booking_pending_reminder',
                    'reservation_reminder',
                    'finalization_needed',
                    'finalization_reminder',
                    'finalization_pending_reminder'
                );
                
                // Only show toggle for automated emails
                if ($trigger && $trigger !== 'on_demand') {
                    // Check if global automation is disabled AND this is a time-based trigger
                    if (!$automation_enabled && in_array($trigger, $time_based_triggers)) {
                        echo '<span style="color: #999; font-size: 11px; font-style: italic;">System Disabled</span>';
                    } else {
                        $status_text = $is_enabled ? 'ON' : 'OFF';
                        $status_class = $is_enabled ? 'enabled' : 'disabled';
                        $toggle_url = wp_nonce_url(
                            admin_url('admin-ajax.php?action=bst_toggle_email_template&post_id=' . $post_id),
                            'toggle_email_template_' . $post_id
                        );
                        
                        echo '<div class="email-toggle-wrapper">';
                        echo '<a href="' . esc_url($toggle_url) . '" class="email-toggle-link ' . $status_class . '" data-post-id="' . $post_id . '">';
                        echo '<span class="toggle-switch"></span>';
                        echo '<span class="toggle-label">' . $status_text . '</span>';
                        echo '</a>';
                        echo '</div>';
                    }
                } else {
                    echo '<span style="color: #ccc; font-size: 11px;">Manual</span>';
                }
                break;
                
            case 'email_type':
                $type = get_post_meta($post_id, '_bst_email_type', true);
                if ($type) {
                    $type_labels = array(
                        'reservation' => 'Reservation',
                        'finalization' => 'Finalization', 
                        'invoice' => 'Invoice',
                        'notification' => 'Notification',
                        'planning' => 'Planning'
                    );
                    $label = isset($type_labels[$type]) ? $type_labels[$type] : ucfirst($type);
                    echo '<span class="email-type-badge" style="background: #0073aa; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">' . esc_html($label) . '</span>';
                } else {
                    echo '<span style="color: #999;">Not Set</span>';
                }
                break;
                
            case 'email_trigger':
                $trigger = get_post_meta($post_id, '_bst_email_trigger', true);
                if ($trigger && $trigger !== 'on_demand') {
                    $trigger_labels = array(
                        'booking_completed' => 'Booking Completed',
                        'booking_pending' => 'Booking Pending',
                        'booking_pending_reminder' => 'Booking Pending Reminder',
                        'reservation_created' => 'Reservation Created',
                        'reservation_reminder' => 'Reservation Reminder',
                        'waiting_list_created' => 'Waiting List Created',
                        'finalization_needed' => 'Finalization Needed',
                        'finalization_reminder' => 'Finalization Reminder',
                        'finalization_pending' => 'Finalization Pending',
                        'finalization_pending_reminder' => 'Finalization Pending Reminder',
                        'finalization_completed' => 'Finalization Completed'
                    );
                    $label = isset($trigger_labels[$trigger]) ? $trigger_labels[$trigger] : ucfirst(str_replace('_', ' ', $trigger));
                    echo '<span class="trigger-badge" style="background: #00a32a; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">🤖 ' . esc_html($label) . '</span>';
                } else {
                    echo '<span class="manual-badge" style="background: #999; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">📝 Manual Only</span>';
                }
                break;
                
            case 'trigger_config':
                $trigger = get_post_meta($post_id, '_bst_email_trigger', true);
                $days = get_post_meta($post_id, '_bst_trigger_days', true);
                $status = get_post_meta($post_id, '_bst_trigger_status', true);
                $payment_method = get_post_meta($post_id, '_bst_trigger_payment_method', true);
                
                $config_parts = array();
                
                if ($days) {
                    $config_parts[] = $days . ' days';
                }
                
                if ($status) {
                    $config_parts[] = 'Status: ' . $status;
                }
                
                if ($payment_method) {
                    $config_parts[] = 'Payment: ' . $payment_method;
                }
                
                if (!empty($config_parts)) {
                    echo '<small style="color: #666;">' . implode(' | ', $config_parts) . '</small>';
                } else if ($trigger && $trigger !== 'on_demand') {
                    echo '<small style="color: #999;">No config needed</small>';
                } else {
                    echo '<small style="color: #ccc;">—</small>';
                }
                break;
        }
    }
    
    /**
     * Make custom columns sortable
     */
    public function make_email_template_columns_sortable($columns) {
        $columns['email_type'] = 'email_type';
        $columns['email_trigger'] = 'email_trigger';
        return $columns;
    }
    
    /**
     * AJAX handler for toggling email template enabled state
     */
    public function ajax_toggle_email_template() {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        
        if (!$post_id || !check_ajax_referer('toggle_email_template_' . $post_id, '_wpnonce', false)) {
            wp_send_json_error(array('message' => 'Invalid request'));
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $enabled = get_post_meta($post_id, '_bst_email_enabled', true);
        $is_enabled = ($enabled === '' || $enabled === '1' || $enabled === 'yes');
        
        // Toggle the state
        $new_state = $is_enabled ? '0' : '1';
        update_post_meta($post_id, '_bst_email_enabled', $new_state);
        
        wp_send_json_success(array(
            'enabled' => $new_state === '1',
            'message' => 'Email template ' . ($new_state === '1' ? 'enabled' : 'disabled')
        ));
    }
    
    /**
     * AJAX handler for sending test emails
     */
    public function ajax_send_test_email() {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bst_test_email')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }
        
        // Verify permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
        
        if (!$template_id || !$test_email) {
            wp_send_json_error(array('message' => 'Missing required fields'));
        }
        
        // Get template
        $template = get_post($template_id);
        if (!$template || $template->post_type !== 'email-template') {
            wp_send_json_error(array('message' => 'Template not found'));
        }
        
        // Get template content
        $subject = get_post_meta($template_id, '_bst_email_subject', true) ?: $template->post_title;
        $content = $template->post_content;
        
        // Generate sample booking data
        $sample_booking = $this->generate_sample_booking_data();
        
        // Process merge fields
        $merge_fields = new BST_Email_Merge_Fields();
        $processed_subject = $merge_fields->process_merge_fields($subject, $sample_booking);
        $processed_content = $merge_fields->process_merge_fields($content, $sample_booking);
        
        // Wrap content in HTML structure
        $html_content = $this->wrap_email_html($processed_content);
        
        // Convert relative image URLs to absolute URLs for email compatibility
        $html_content = $this->convert_image_urls_to_absolute($html_content);
        
        // Prepare headers
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $from_email = get_option('bst_from_email_address', 'info@bluestradatours.com');
        $from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
        $headers[] = "From: {$from_name} <{$from_email}>";
        $headers[] = "X-BST-Test-Email: true";
        
        // Prepend warning message to content
        $test_warning = '<div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <strong style="color: #856404;">⚠️ TEST EMAIL</strong><br>
            <span style="color: #856404; font-size: 14px;">This is a test email using sample booking data. Actual emails will use real booking information.</span>
        </div>';
        $html_content = str_replace('<div class="email-content">', '<div class="email-content">' . $test_warning, $html_content);
        
        // Send email
        $sent = wp_mail($test_email, '[TEST] ' . $processed_subject, $html_content, $headers);
        
        if ($sent) {
            wp_send_json_success(array(
                'message' => '<strong>✅ Test email sent successfully!</strong><br>Check your inbox at: ' . esc_html($test_email)
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to send test email. Check your WordPress email configuration.'));
        }
    }
    
    /**
     * Generate sample booking data for test emails
     */
    private function generate_sample_booking_data() {
        $sample_data = new stdClass();
        
        // Booking details
        $sample_data->id = 12345;
        $sample_data->booking_number = 'BST-2026-001';
        $sample_data->status = 'confirmed';
        $sample_data->created_date = date('Y-m-d H:i:s');
        
        // Tour details
        $sample_data->tour_name = 'Amalfi Coast & Tuscany Tour';
        $sample_data->tour_text = 'Amalfi Coast & Tuscany Tour';
        $sample_data->tour_start_date = '2026-05-15';
        $sample_data->tour_end_date = '2026-05-22';
        $sample_data->tour_date_start = '2026-05-15';
        $sample_data->tour_date_end = '2026-05-22';
        $sample_data->tour_days = '8';
        $sample_data->tour_nights = '7';
        
        // Guest details
        $sample_data->guest1_first_name = 'John';
        $sample_data->guest1_last_name = 'Smith';
        $sample_data->guest1_email = 'john.smith@example.com';
        $sample_data->guest1_phone = '+1 (555) 123-4567';
        $sample_data->guest1_nationality = 'United States';
        $sample_data->guest1_passport_number = 'AB1234567';
        $sample_data->guest1_date_of_birth = '1980-03-15';
        
        $sample_data->guest2_first_name = 'Jane';
        $sample_data->guest2_last_name = 'Smith';
        $sample_data->guest2_email = 'jane.smith@example.com';
        $sample_data->guest2_phone = '+1 (555) 123-4568';
        $sample_data->guest2_nationality = 'United States';
        
        // Pricing
        $sample_data->base_price = 4500.00;
        $sample_data->deposit_amount = 900.00;
        $sample_data->balance_due = 3600.00;
        $sample_data->total_price = 4500.00;
        $sample_data->currency = 'USD';
        $sample_data->payment_status = 'deposit_paid';
        
        // Add-ons / Extensions
        $sample_data->extensions = 'Airport Transfer, Extra Night in Rome';
        $sample_data->extension_total = 450.00;
        
        // Motor club
        $sample_data->motor_club = 'Ducati Owners Club';
        
        // Commission
        $sample_data->commission_agent = 'Travel Agency XYZ';
        $sample_data->commission_amount = 450.00;
        $sample_data->commission_rate = 10;
        
        // Payment details
        $sample_data->payment_method = 'credit_card';
        $sample_data->stripe_payment_intent = 'pi_test_1234567890';
        
        // Finalization details
        $sample_data->finalization_status = 'pending';
        $sample_data->balance_due_date = '2026-04-15';
        
        // Bank account details (for wire transfer instructions)
        $sample_data->bank_account_name = 'Blue Strada Tours LLC';
        $sample_data->bank_name = 'Airwallex';
        $sample_data->bank_swift = 'AIRWUS33';
        $sample_data->bank_account_number = '1234567890';
        $sample_data->bank_routing = '084009519';
        
        return $sample_data;
    }
    
    /**
     * Add inline CSS for email template list
     */
    public function add_email_list_inline_css() {
        global $post_type;
        if ($post_type !== 'email-template') {
            return;
        }
        ?>
        <style>
            .email-toggle-wrapper {
                display: flex;
                align-items: center;
            }
            .email-toggle-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                padding: 4px 8px;
                border-radius: 4px;
                transition: all 0.2s;
            }
            .email-toggle-link:hover {
                opacity: 0.8;
            }
            .toggle-switch {
                position: relative;
                width: 40px;
                height: 20px;
                background: #ddd;
                border-radius: 10px;
                transition: background 0.3s;
            }
            .toggle-switch::after {
                content: '';
                position: absolute;
                width: 16px;
                height: 16px;
                background: white;
                border-radius: 50%;
                top: 2px;
                left: 2px;
                transition: left 0.3s;
            }
            .email-toggle-link.enabled .toggle-switch {
                background: #00a32a;
            }
            .email-toggle-link.enabled .toggle-switch::after {
                left: 22px;
            }
            .toggle-label {
                font-weight: 600;
                font-size: 11px;
                min-width: 28px;
            }
            .email-toggle-link.enabled .toggle-label {
                color: #00a32a;
            }
            .email-toggle-link.disabled .toggle-label {
                color: #999;
            }
        </style>
        <script>
        jQuery(document).ready(function($) {
            $('.email-toggle-link').on('click', function(e) {
                e.preventDefault();
                var $link = $(this);
                var url = $link.attr('href');
                
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            // Toggle the visual state
                            if (response.data.enabled) {
                                $link.removeClass('disabled').addClass('enabled');
                                $link.find('.toggle-label').text('ON');
                            } else {
                                $link.removeClass('enabled').addClass('disabled');
                                $link.find('.toggle-label').text('OFF');
                            }
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Enqueue admin scripts for email templates
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if ($hook == 'post.php' || $hook == 'post-new.php') {
            if ($post_type == 'email-template') {
                // Use content_url for reliable URL construction
                $script_url = content_url('mu-plugins/bst_plugin/js/merge-fields.js');
                
                wp_enqueue_script(
                    'bst-email-merge-fields', 
                    $script_url, 
                    array('jquery'), 
                    '1.0.1', 
                    true
                );
                
                // Get available merge fields for JavaScript
                $merge_fields = new BST_Email_Merge_Fields();
                wp_localize_script('bst-email-merge-fields', 'bstMergeFields', array(
                    'fields' => $merge_fields->get_available_fields()
                ));
            }
        }
    }
    
    /**
     * Render merge fields picker meta box
     */
    public function render_merge_fields_picker($post) {
        $merge_fields = new BST_Email_Merge_Fields();
        $fields = $merge_fields->get_available_fields();
        ?>
        <div id="bst-merge-fields-picker">
            <p class="description">Click any field below to insert it at cursor position in the email content.</p>
            
            <div class="bst-merge-fields-search">
                <input type="text" id="merge-field-search" placeholder="Search merge fields..." style="width: 100%; margin-bottom: 10px;">
            </div>
            
            <div class="bst-merge-fields-categories">
                <?php foreach ($fields as $category => $category_fields): ?>
                    <div class="bst-merge-category" data-category="<?php echo esc_attr($category); ?>">
                        <h4 class="bst-category-title" style="margin: 10px 0 5px; cursor: pointer; border-bottom: 1px solid #ddd;">
                            <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; width: 14px; height: 14px;"></span>
                            <?php echo esc_html($category); ?>
                        </h4>
                        <div class="bst-category-fields" style="margin-left: 20px;">
                            <?php foreach ($category_fields as $field => $description): ?>
                                <div class="bst-merge-field-item" 
                                     data-field="<?php echo esc_attr($field); ?>"
                                     data-description="<?php echo esc_attr($description); ?>"
                                     data-item-id="<?php echo esc_attr($field . '-' . md5($description)); ?>"
                                     style="margin: 3px 0; padding: 3px 8px; cursor: pointer; border-radius: 3px; border: 1px solid transparent;">
                                    <code style="font-size: 11px; background: #f9f9f9; padding: 2px 4px; border-radius: 2px;">
                                        {<?php echo esc_html($field); ?>}
                                    </code>
                                    <div class="field-description" style="font-size: 11px; color: #666; margin-top: 2px; display: block;">
                                        <?php echo esc_html($description); ?>
                                    </div>
                                </div><!-- End bst-merge-field-item: <?php echo esc_html($field); ?> -->
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="bst-quick-actions" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd;">
                <h4 style="margin: 0 0 5px; font-size: 12px; color: #666;">Quick Actions</h4>
                <button type="button" class="button button-small" onclick="bstInsertConditional()">Insert Conditional Block</button>
                <p class="description" style="margin: 5px 0 0; font-size: 11px;">
                    Use conditional blocks to show content based on field values:<br>
                    <code style="font-size: 10px;">{#field_name}content{/field_name}</code> - Has value<br>
                    <code style="font-size: 10px;">{#booking_status=Booked}content{/booking_status}</code> - Equals<br>
                    <code style="font-size: 10px;">{#booking_status!=Reserved}content{/booking_status}</code> - Not equals<br>
                    <code style="font-size: 10px;">{#guest_count>1}content{/guest_count}</code> - Greater/less than<br>
                    Operators: <strong>=</strong> != &gt; &lt; &gt;= &lt;=
                </p>
            </div>
        </div>
        
        <style>
        #bst-merge-fields-picker .bst-merge-field-item:hover {
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
        }
        #bst-merge-fields-picker .bst-category-title:hover {
            background: #f6f7f7;
        }
        #bst-merge-fields-picker .bst-category-fields.collapsed {
            display: none;
        }
        #bst-merge-fields-picker .bst-category-title .dashicons.collapsed {
            transform: rotate(-90deg);
        }
        .bst-merge-field-item.hidden {
            display: none;
        }
        </style>
        <?php
    }
    
    /**
     * Render test email meta box
     */
    public function render_test_email_meta_box($post) {
        // Only show if post is published
        if ($post->post_status === 'auto-draft') {
            ?>
            <p class="description">Save the template first to enable test emails.</p>
            <?php
            return;
        }
        
        wp_nonce_field('bst_test_email', 'bst_test_email_nonce');
        ?>
        <div id="bst-test-email-container">
            <p class="description">Send a test email with sample data to verify formatting and merge fields.</p>
            <label for="bst-test-email-input" style="font-weight: 600;">Test Email Address:</label>
            <input type="email" 
                   id="bst-test-email-input" 
                   placeholder="your@email.com" 
                   value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
            <button type="button" id="bst-send-test-email" class="button button-primary" style="width: 100%;">
                Send Test Email
            </button>
            <div id="bst-test-email-result"></div>
        </div>
        <?php
    }
    
    /**
     * Send template email with custom recipients
     */
    public function send_template_email($template_id, $booking, $options = array()) {
        try {
            // Get template
            $template = get_post($template_id);
            if (!$template || $template->post_type !== 'email_template') {
                return array('success' => false, 'error' => 'Template not found');
            }
            
            // Get template content
            $subject = get_post_meta($template_id, '_email_subject', true) ?: $template->post_title;
            $content = $template->post_content;
            
            // Get template recipients if not provided in options
            $to_email = $options['to'] ?? get_post_meta($template_id, '_email_to', true);
            $cc_email = $options['cc'] ?? get_post_meta($template_id, '_email_cc', true);
            $bcc_email = $options['bcc'] ?? get_post_meta($template_id, '_email_bcc', true);
            
            if (!$to_email) {
                return array('success' => false, 'error' => 'No recipient email specified');
            }
            
            // Process merge fields
            $merge_fields = new BST_Email_Merge_Fields();
            $processed_subject = $merge_fields->process_merge_fields($subject, $booking);
            $processed_content = $merge_fields->process_merge_fields($content, $booking);
            
            // Wrap content in HTML structure
            $html_content = $this->wrap_email_html($processed_content);
            
            // Convert relative image URLs to absolute URLs for email compatibility
            $html_content = $this->convert_image_urls_to_absolute($html_content);
            
            // Get attachment if configured
            $attachment_id = get_post_meta($template_id, '_bst_email_attachment', true);
            $attachments = array();
            if ($attachment_id) {
                $attachment_path = get_attached_file($attachment_id);
                if ($attachment_path && file_exists($attachment_path)) {
                    $attachments[] = $attachment_path;
                }
            }
            
            // Prepare headers
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            if ($cc_email) {
                $headers[] = 'Cc: ' . $cc_email;
            }
            if ($bcc_email) {
                $headers[] = 'Bcc: ' . $bcc_email;
            }
            
            // Send email using configured method
            $email_method = get_option('bst_email_method', 'wp_mail');
            
            if ($email_method === 'gmail') {
                // Send using Gmail API with configured from name
                $from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
                $send_result = $this->gmail_api->send_email(
                    $to_email, 
                    $processed_subject, 
                    $html_content, 
                    $from_name,
                    $booking->id ?? null,
                    null,
                    $attachments
                );
                $sent = $send_result['success'];
                $gmail_message_id = $send_result['gmail_message_id'];
                $gmail_thread_id = $send_result['gmail_thread_id'];
            } else {
                // Send using WordPress wp_mail with configured from email
                $from_email = get_option('bst_from_email_address', 'info@bluestradatours.com');
                $from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
                $headers[] = "From: {$from_name} <{$from_email}>";
                $sent = wp_mail($to_email, $processed_subject, $html_content, $headers, $attachments);
                $gmail_message_id = null;
                $gmail_thread_id = null;
            }
            
            if ($sent) {
                // Log the email
                $log_id = $this->log_email_to_database(
                    $booking->id,
                    $template_id,
                    $options['email_type'] ?? 'notification',
                    $to_email,
                    $processed_subject,
                    $html_content,
                    true,
                    $gmail_message_id,
                    $gmail_thread_id
                );
                
                return array('success' => true, 'log_id' => $log_id);
            } else {
                // Log failed email
                $this->log_email_to_database(
                    $booking->id,
                    $template_id,
                    $options['email_type'] ?? 'notification',
                    $to_email,
                    $processed_subject,
                    $html_content,
                    false,
                    $gmail_message_id,
                    $gmail_thread_id
                );
                
                return array('success' => false, 'error' => 'Failed to send email');
            }
            
        } catch (Exception $e) {
            error_log('BST Email Manager: Send template email error - ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Generate a unique Message-ID for wp_mail
     */
    private function generate_message_id() {
        $domain = str_replace(['http://', 'https://'], '', home_url());
        $domain = explode('/', $domain)[0]; // Remove any path
        return '<bst-' . uniqid() . '-' . time() . '@' . $domain . '>';
    }

    /**
     * Create a preliminary email log entry to get an ID for tracking
     */
    private function create_preliminary_email_log($booking_id, $template_id, $email_type, $to_email, $subject, $html_content) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bst_email_log';

        $result = $wpdb->insert(
            $table_name,
            array(
                'booking_id' => $booking_id,
                'template_id' => $template_id,
                'email_type' => $email_type,
                'recipient_email' => $to_email,
                'subject' => $subject,
                'content' => $html_content,
                'sent_date' => current_time('mysql'),
                'sent_by' => 'automation',
                'sent_successfully' => 0
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );

        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        error_log('BST Email Log: Failed to create log entry - ' . $wpdb->last_error);
        return false;
    }

    /**
     * Update the email log entry with send results
     */
    private function update_email_log_with_results($log_id, $success, $gmail_message_id, $gmail_thread_id, $message_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bst_email_log';

        $update_data = array(
            'sent_successfully' => $success ? 1 : 0
        );
        $format_array = array('%d');
        
        if ($gmail_message_id) {
            $update_data['gmail_message_id'] = $gmail_message_id;
            $format_array[] = '%s';
        }
        
        if ($gmail_thread_id) {
            $update_data['gmail_thread_id'] = $gmail_thread_id;
            $format_array[] = '%s';
        }
        
        if ($message_id) {
            $update_data['message_id'] = $message_id;
            $format_array[] = '%s';
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $log_id),
            $format_array,
            array('%d')
        );

        if ($result === false) {
            error_log('BST Email Log: Failed to update log entry ' . $log_id . ' - ' . $wpdb->last_error);
        }

        return $result !== false;
    }
}