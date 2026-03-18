<?php

/**
 * BST Email Log Viewer
 * 
 * Interface for viewing and managing email logs for bookings
 */
class BST_Email_Log_Viewer {
    
    public function __construct() {
        // Initialize hooks
        add_action('wp_ajax_bst_get_booking_email_log', array($this, 'ajax_get_booking_email_log'));
        add_action('wp_ajax_bst_resend_email', array($this, 'ajax_resend_email'));
        add_action('wp_ajax_bst_get_email_content', array($this, 'ajax_get_email_content'));
        add_action('wp_ajax_bst_get_booking_email_data', array($this, 'ajax_get_booking_email_data'));
        add_action('wp_ajax_bst_send_adhoc_email_compose', array($this, 'ajax_send_adhoc_email_compose'));
        add_action('wp_ajax_bst_get_merge_fields', array($this, 'ajax_get_merge_fields'));
        add_action('wp_ajax_bst_create_email_batch', array($this, 'ajax_create_email_batch'));
        add_action('wp_ajax_bst_update_email_batch', array($this, 'ajax_update_email_batch'));
        add_action('wp_ajax_bst_delete_email', array($this, 'ajax_delete_email'));
        add_action('wp_ajax_bst_get_tour_date_bookings', array($this, 'ajax_get_tour_date_bookings'));
        
        // Enqueue editor scripts for admin pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_editor_scripts'));
    }
    
    /**
     * Enqueue editor scripts for email composition
     */
    public function enqueue_editor_scripts($hook) {
        // Only load on booking edit pages
        if (isset($_GET['page']) && $_GET['page'] === 'view_booking') {
            wp_enqueue_editor();
            wp_enqueue_script('wp-tinymce');
        }
    }
    
    /**
     * Render email log for a booking
     */
    public function render_email_log($booking_id, $include_modals = true) {
        $email_manager = new BST_Email_Manager();
        $emails = $email_manager->get_booking_email_log($booking_id);
        
        if (empty($emails)) {
            echo '<p>No emails have been sent for this booking.</p>';
            return;
        }
        
        ?>
        <div class="bst-email-log">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th style="width: 15%;">Email Type</th>
                        <th style="width: 35%;">Subject</th>
                        <th style="width: 20%;">Sent Date</th>
                        <th style="width: 5%;"></th>
                        <th style="width: 25%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emails as $email): ?>
                        <?php $success = isset($email['sent_successfully']) ? (bool)$email['sent_successfully'] : true; ?>
                        <tr class="email-log-row" data-log-id="<?php echo intval($email['id'] ?? 0); ?>">
                            <td style="width: 15%;">
                                <span class="email-type-badge <?php echo esc_attr($email['email_type'] ?? 'unknown'); ?>">
                                    <?php echo $this->format_email_type_display($email['email_type'] ?? 'Unknown'); ?>
                                </span>
                            </td>
                            <td style="width: 35%; word-wrap: break-word; white-space: normal;">
                                <?php 
                                $subject = $email['subject'] ?? 'No Subject';
                                $display_subject = esc_html(strlen($subject) > 60 ? substr($subject, 0, 60) . '...' : $subject);
                                ?>
                                <a href="#" onclick="viewEmailContent(<?php echo intval($email['id'] ?? 0); ?>); return false;" 
                                   style="color: #2271b1; text-decoration: none;">
                                    <?php echo $display_subject; ?>
                                </a>
                            </td>
                            <td style="width: 20%;"><?php echo $email['sent_date'] ? date('Y-m-d', strtotime($email['sent_date'])) : 'Unknown Date'; ?></td>
                            <td style="text-align: center; width: 5%; padding: 8px 2px;">
                                <?php if ($success): ?>
                                    <span style="color: #46b450; font-size: 18px;" title="Sent successfully">✓</span>
                                <?php else: ?>
                                    <span style="color: #dc3232; font-size: 18px;" title="Failed to send">✗</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions" style="width: 25%;">
                                <button type="button" class="button button-small resend-email" 
                                        onclick="resendEmail(<?php echo intval($email['id'] ?? 0); ?>)"
                                        title="Resend">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                <button type="button" class="button button-small delete-email" 
                                        onclick="deleteEmail(<?php echo intval($email['id'] ?? 0); ?>)"
                                        style="color: #dc3232;"
                                        title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($include_modals): ?>
        <!-- Email Content Modal -->
        <div id="email-content-modal" class="bst-modal" style="display: none;">
            <div class="bst-modal-content">
                <div class="bst-modal-header">
                    <h3>Email Content</h3>
                    <button type="button" class="bst-modal-close" onclick="closeEmailModal()">&times;</button>
                </div>
                <div class="bst-modal-body" id="email-content-body">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Send Email Modal -->
        <div id="send-email-modal" class="bst-modal" style="display: none;">
            <div class="bst-modal-content" style="max-width: 1200px;">
                <div class="bst-modal-header">
                    <h3>Send Ad Hoc Email</h3>
                    <button type="button" class="bst-modal-close" onclick="closeSendEmailModal()">&times;</button>
                </div>
                <div class="bst-modal-body" id="send-email-body">
                    <div style="display: grid; grid-template-columns: 1fr 300px; gap: 30px;">
                        <!-- Email Composition Form -->
                        <div>
                            <form id="send-email-form">
                                <div style="margin-bottom: 12px;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <label for="email-to-select" style="font-size: 12px; color: #333; font-weight: 500; white-space: nowrap;">To:</label>
                                            <select id="email-to-select" style="flex: 1; padding: 4px; font-size: 12px;" required>
                                                <option value="">Loading recipients...</option>
                                            </select>
                                        </div>
                                        <div>
                                            <input type="email" id="email-cc" style="width: 100%; padding: 4px; font-size: 12px; border: 1px solid #ddd; border-radius: 3px;" placeholder="CC (optional)">
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-bottom: 12px;">
                                    <input type="text" id="email-subject" style="width: 100%; padding: 4px; font-size: 12px; border: 1px solid #ddd; border-radius: 3px;" required placeholder="Subject: *">
                                </div>

                                <div style="margin-bottom: 12px;">
                                    <textarea id="email-content" style="width: 100%; height: 240px; padding: 6px; font-family: Arial, sans-serif; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; resize: vertical;" required placeholder="Type your message here..."></textarea>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div id="send-email-status" style="font-style: italic; color: #666; font-size: 11px;"></div>
                                    <div>
                                        <button type="button" onclick="closeSendEmailModal()" class="button" style="margin-right: 8px; font-size: 12px; padding: 4px 12px;">Cancel</button>
                                        <button type="button" id="send-email-btn" onclick="sendAdHocEmail()" class="button button-primary" style="font-size: 12px; padding: 4px 12px;">
                                            <i class="fas fa-paper-plane"></i> Send Email
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Merge Fields Sidebar -->
                        <div id="merge-fields-sidebar" style="border-left: 1px solid #ddd; padding-left: 15px; width: 240px; min-width: 240px; display: flex; flex-direction: column;">
                            <div style="position: sticky; top: 0; background: white; padding-bottom: 4px; border-bottom: 1px solid #eee; margin-bottom: 6px; z-index: 10;">
                                <h4 style="margin: 0 0 4px 0; color: #333; font-size: 12px;">Merge Fields</h4>
                                <input type="text" id="merge-fields-search" placeholder="Search fields..." style="width: 100%; padding: 4px 6px; font-size: 11px; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 4px;">
                                <p style="font-size: 10px; color: #666; margin: 0;">Click to insert</p>
                            </div>
                            
                            <!-- Scrollable Merge Fields Categories -->
                            <div id="merge-fields-categories" style="flex: 1; overflow-y: auto; max-height: calc(100vh - 400px); padding-right: 5px;">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Merge Fields Reference Modal -->
        <div id="merge-fields-modal" class="bst-modal" style="display: none;">
            <div class="bst-modal-content" style="max-width: 700px;">
                <div class="bst-modal-header">
                    <h3>Available Merge Fields</h3>
                    <button type="button" class="bst-modal-close" onclick="closeMergeFieldsModal()">&times;</button>
                </div>
                <div class="bst-modal-body">
                    <p>Click any merge field to copy it to your clipboard:</p>
                    <div id="merge-fields-list" style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; font-family: monospace; font-size: 12px;">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .bst-email-log .email-type-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .email-type-badge.reservation {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .email-type-badge.finalization {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .email-type-badge.invoice {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .email-type-badge.notification {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .email-type-badge.adhoc {
            background: #fff3cd;
            color: #856404;
        }
        
        .bst-modal {
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .bst-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 5px;
            width: 90%;
            max-width: 800px;
            max-height: 80%;
            overflow: hidden;
        }
        
        .bst-modal-header {
            background: #f1f1f1;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bst-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .bst-modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        /* Mobile responsive styles for modal */
        @media (max-width: 768px) {
            .bst-modal-content {
                max-width: 95%;
                max-height: 95%;
                margin: 2% auto;
            }
            
            .bst-modal-body {
                padding: 15px;
                max-height: 80vh;
            }
            
            /* Make grid single column on mobile */
            .bst-modal-body > div[style*="grid"] {
                display: block !important;
            }
            
            /* Stack form elements on mobile */
            .bst-modal-body div[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }
            
            /* Merge fields sidebar below form on mobile */
            #merge-fields-sidebar {
                border-left: none !important;
                border-top: 1px solid #ddd !important;
                padding-left: 0 !important;
                padding-top: 20px !important;
                margin-top: 20px !important;
                width: 100% !important;
                min-width: auto !important;
            }
            
            #merge-fields-categories {
                max-height: 250px !important;
            }
        }
        
        @media (max-width: 480px) {
            .bst-modal-content {
                max-width: 98%;
                margin: 1% auto;
            }
            
            .bst-modal-header {
                padding: 10px 15px;
            }
            
            .bst-modal-body {
                padding: 10px;
            }
            
            /* Smaller text inputs on very small screens */
            .bst-modal input, .bst-modal select, .bst-modal textarea {
                font-size: 16px; /* Prevent zoom on iOS */
            }
        }
        
        .email-content-viewer .email-meta {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .email-content-viewer .email-body {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            background: white;
            min-height: 200px;
            font-family: Arial, sans-serif;
            line-height: 1.5;
        }
        
        .email-content-viewer .email-body img {
            max-width: 100%;
            height: auto;
        }
        
        .email-content-viewer .email-body a {
            color: #0073aa;
            text-decoration: underline;
        }
        </style>
        
        <script>
        // Initialize TinyMCE for email content
        function initEmailContentEditor() {
            if (typeof wp !== 'undefined' && wp.editor) {
                // Use WordPress editor
                wp.editor.initialize('email-content', {
                    tinymce: {
                        wpautop: true,
                        plugins: 'charmap colorpicker lists textcolor link',
                        toolbar1: 'bold italic underline strikethrough | bullist numlist | link unlink | forecolor backcolor | undo redo',
                        height: 250
                    },
                    quicktags: true
                });
            } else if (typeof tinymce !== 'undefined') {
                // Use standalone TinyMCE
                tinymce.init({
                    selector: '#email-content',
                    height: 250,
                    menubar: false,
                    plugins: [
                        'advlist autolink lists link charmap preview anchor',
                        'searchreplace visualblocks code fullscreen',
                        'insertdatetime table paste code help wordcount'
                    ],
                    toolbar: 'bold italic underline strikethrough | bullist numlist | link unlink | forecolor backcolor | undo redo | code',
                    content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }'
                });
            }
        }
        
        // Initialize editor when modal opens
        function openSendEmailModalWithEditor(bookingId) {
            // First show the modal
            jQuery('#send-email-modal').show();
            
            // Initialize the editor after a short delay to ensure DOM is ready
            setTimeout(function() {
                initEmailContentEditor();
            }, 500);
            
            // Load booking data
            loadBookingDataForEmail(bookingId);
        }
        
        function viewEmailContent(logId) {
            jQuery.post(ajaxurl, {
                action: 'bst_get_email_content',
                log_id: logId,
                nonce: '<?php echo wp_create_nonce('bst_email_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#email-content-body').html(response.data.content);
                    jQuery('#email-content-modal').show();
                } else {
                    alert('Error loading email content: ' + response.data);
                }
            });
        }
        
        function closeEmailModal() {
            jQuery('#email-content-modal').hide();
        }
        
        function resendEmail(logId) {
            if (!confirm('Are you sure you want to resend this email?')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'bst_resend_email',
                log_id: logId,
                nonce: '<?php echo wp_create_nonce('bst_email_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Email resent successfully!');
                    location.reload(); // Refresh to show new log entry
                } else {
                    alert('Error resending email: ' + response.data);
                }
            });
        }
        
        function deleteEmail(logId) {
            if (!confirm('Are you sure you want to permanently delete this email from the log?')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'bst_delete_email',
                log_id: logId,
                nonce: '<?php echo wp_create_nonce('bst_email_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    // Remove the row from the table
                    jQuery('tr[data-log-id="' + logId + '"]').fadeOut(300, function() {
                        jQuery(this).remove();
                    });
                } else {
                    alert('Error deleting email: ' + response.data);
                }
            });
        }
        
        function closeSendEmailModal() {
            // Clean up TinyMCE editor
            if (typeof wp !== 'undefined' && wp.editor) {
                wp.editor.remove('email-content');
            } else if (typeof tinymce !== 'undefined') {
                var editor = tinymce.get('email-content');
                if (editor) {
                    editor.remove();
                }
            }
            
            // Hide modal and clear fields
            jQuery('#send-email-modal').hide();
            jQuery('#email-to-select').val('').show();
            jQuery('#email-cc').val('');
            jQuery('#email-subject').val('');
            jQuery('#email-content').val('');
            jQuery('#send-email-status').text('');
            jQuery('#merge-fields-search').val('');
            
            // Clear merge fields sidebar
            jQuery('#merge-fields-sidebar').html('');
        }
        
        function toggleMergeCategory(categoryId) {
            var content = jQuery('#' + categoryId);
            var arrow = jQuery('#' + categoryId + '-arrow');
            
            if (content.is(':visible')) {
                content.slideUp(200);
                arrow.text('▶');
            } else {
                content.slideDown(200);
                arrow.text('▼');
            }
        }
        
        // Search merge fields
        jQuery(document).on('input', '#merge-fields-search', function() {
            var searchTerm = jQuery(this).val().toLowerCase();
            
            if (!searchTerm) {
                // Show all categories and fields
                jQuery('.merge-field-category').show();
                jQuery('.merge-field-item').show();
                return;
            }
            
            // Hide all initially
            jQuery('.merge-field-category').hide();
            
            // Search through each category
            jQuery('.merge-field-category').each(function() {
                var category = jQuery(this);
                var categoryName = category.find('.merge-category-header span:first').text().toLowerCase();
                var hasMatch = false;
                
                // Check if category name matches
                if (categoryName.indexOf(searchTerm) !== -1) {
                    hasMatch = true;
                    category.find('.merge-field-item').show();
                } else {
                    // Check each field in the category
                    category.find('.merge-field-item').each(function() {
                        var fieldText = jQuery(this).text().toLowerCase();
                        if (fieldText.indexOf(searchTerm) !== -1) {
                            jQuery(this).show();
                            hasMatch = true;
                        } else {
                            jQuery(this).hide();
                        }
                    });
                }
                
                // Show category if it has matches
                if (hasMatch) {
                    category.show();
                    // Auto-expand categories with matches
                    var categoryId = category.find('.merge-category-content').attr('id');
                    if (categoryId) {
                        jQuery('#' + categoryId).slideDown(200);
                        jQuery('#' + categoryId + '-arrow').text('▼');
                    }
                }
            });
        });
        
        // Close modal when clicking outside
        jQuery(document).on('click', '.bst-modal', function(e) {
            if (e.target === this) {
                closeEmailModal();
            }
        });
        </script>
        <?php endif; // End include_modals conditional ?>
        <?php
    }
    
    /**
     * Render standalone email modals and scripts (reusable across pages)
     * Can be called from any admin page that needs email composition functionality
     */
    public function render_email_modals() {
        // Duplicate the modal HTML and scripts from render_email_log but make it standalone
        // This allows any page to include email functionality without the email log table
        ?>
        <!-- Email Content Modal -->
        <div id="email-content-modal" class="bst-modal" style="display: none;">
            <div class="bst-modal-content">
                <div class="bst-modal-header">
                    <h3>Email Content</h3>
                    <button type="button" class="bst-modal-close" onclick="closeEmailModal()">&times;</button>
                </div>
                <div class="bst-modal-body" id="email-content-body">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Send Email Modal -->
        <div id="send-email-modal" class="bst-modal" style="display: none;">
            <div class="bst-modal-content" style="max-width: 1200px;">
                <div class="bst-modal-header">
                    <h3>Send Ad Hoc Email</h3>
                    <button type="button" class="bst-modal-close" onclick="closeSendEmailModal()">&times;</button>
                </div>
                <div class="bst-modal-body" id="send-email-body">
                    <div style="display: grid; grid-template-columns: 1fr 300px; gap: 30px;">
                        <!-- Email Composition Form -->
                        <div>
                            <form id="send-email-form">
                                <div style="margin-bottom: 12px;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <label for="email-to-select" style="font-size: 12px; color: #333; font-weight: 500; white-space: nowrap;">To:</label>
                                            <select id="email-to-select" style="flex: 1; padding: 4px; font-size: 12px;" required>
                                                <option value="">Loading recipients...</option>
                                            </select>
                                        </div>
                                        <div>
                                            <input type="email" id="email-cc" style="width: 100%; padding: 4px; font-size: 12px; border: 1px solid #ddd; border-radius: 3px;" placeholder="CC (optional)">
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-bottom: 12px;">
                                    <input type="text" id="email-subject" style="width: 100%; padding: 4px; font-size: 12px; border: 1px solid #ddd; border-radius: 3px;" required placeholder="Subject: *">
                                </div>

                                <div style="margin-bottom: 12px;">
                                    <textarea id="email-content" style="width: 100%; height: 240px; padding: 6px; font-family: Arial, sans-serif; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; resize: vertical;" required placeholder="Type your message here..."></textarea>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div id="send-email-status" style="font-style: italic; color: #666; font-size: 11px;"></div>
                                    <div>
                                        <button type="button" onclick="closeSendEmailModal()" class="button" style="margin-right: 8px; font-size: 12px; padding: 4px 12px;">Cancel</button>
                                        <button type="button" id="send-email-btn" onclick="sendAdHocEmail()" class="button button-primary" style="font-size: 12px; padding: 4px 12px;">
                                            <i class="fas fa-paper-plane"></i> Send Email
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Merge Fields Sidebar -->
                        <div id="merge-fields-sidebar" style="border-left: 1px solid #ddd; padding-left: 15px; width: 240px; min-width: 240px; display: flex; flex-direction: column;">
                            <div style="position: sticky; top: 0; background: white; padding-bottom: 4px; border-bottom: 1px solid #eee; margin-bottom: 6px; z-index: 10;">
                                <h4 style="margin: 0 0 4px 0; color: #333; font-size: 12px;">Merge Fields</h4>
                                <input type="text" id="merge-fields-search" placeholder="Search fields..." style="width: 100%; padding: 4px 6px; font-size: 11px; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 4px;">
                                <p style="font-size: 10px; color: #666; margin: 0;">Click to insert</p>
                            </div>
                            
                            <!-- Scrollable Merge Fields Categories -->
                            <div id="merge-fields-categories" style="flex: 1; overflow-y: auto; max-height: calc(100vh - 400px); padding-right: 5px;">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Merge Fields Reference Modal -->
        <div id="merge-fields-modal" class="bst-modal" style="display: none;">
            <div class="bst-modal-content" style="max-width: 700px;">
                <div class="bst-modal-header">
                    <h3>Available Merge Fields</h3>
                    <button type="button" class="bst-modal-close" onclick="closeMergeFieldsModal()">&times;</button>
                </div>
                <div class="bst-modal-body">
                    <p>Click any merge field to copy it to your clipboard:</p>
                    <div id="merge-fields-list" style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; font-family: monospace; font-size: 12px;">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .bst-email-log .email-type-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .email-type-badge.reservation {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .email-type-badge.finalization {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .email-type-badge.invoice {
            background: #fffbe0;
            color: #f57c00;
        }
        
        .email-type-badge.notification {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .email-type-badge.adhoc {
            background: #fff3cd;
            color: #856404;
        }
        
        .bst-modal {
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .bst-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 5px;
            width: 90%;
            max-width: 800px;
            max-height: 80%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .bst-modal-header {
            padding: 20px;
            background-color: #f9f9f9;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bst-modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .bst-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            line-height: 1;
        }
        
        .bst-modal-close:hover {
            color: #000;
        }
        
        .bst-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        
        .email-content-viewer .email-body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
        
        /* Merge fields styling */
        #merge-fields-categories {
            scrollbar-width: thin;
            scrollbar-color: #ddd #f9f9f9;
        }
        
        #merge-fields-categories::-webkit-scrollbar {
            width: 6px;
        }
        
        #merge-fields-categories::-webkit-scrollbar-track {
            background: #f9f9f9;
        }
        
        #merge-fields-categories::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 3px;
        }
        
        #merge-fields-categories::-webkit-scrollbar-thumb:hover {
            background: #ccc;
        }
        
        .merge-field-category {
            margin-bottom: 12px;
        }
        
        .merge-field-category h5 {
            margin: 0 0 6px 0;
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #eee;
            padding-bottom: 3px;
        }
        
        .merge-field-item {
            padding: 4px 6px;
            margin: 2px 0;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 3px;
            cursor: pointer;
            font-family: monospace;
            font-size: 10px;
            color: #0066cc;
            transition: all 0.2s;
        }
        
        .merge-field-item:hover {
            background: #e3f2fd;
            border-color: #0066cc;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .bst-modal-content {
                width: 95%;
                margin: 2% auto;
                max-height: 95%;
            }
            
            #send-email-body > div {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }
            
            #merge-fields-sidebar {
                border-left: none!important;
                border-top: 1px solid #ddd;
                padding-left: 0 !important;
                padding-top: 15px;
                width: 100% !important;
                max-height: 200px;
            }
            
            #merge-fields-categories {
                max-height: 150px !important;
            }
        }
        
        @media (max-width: 480px) {
            .bst-modal-header {
                padding: 15px;
            }
            
            .bst-modal-body {
                padding: 15px;
            }
            
            #send-email-body input,
            #send-email-body textarea,
            #send-email-body select,
            #send-email-body button {
                font-size: 14px !important;
                padding: 6px !important;
            }
            
            #email-content {
                height: 180px !important;
            }
        }
        </style>
        
        <script>
        // Modal functions accessible from any page
        function closeEmailModal() {
            jQuery('#email-content-modal').hide();
        }
        
        function closeSendEmailModal() {
            jQuery('#send-email-modal').hide();
        }
        
        function closeMergeFieldsModal() {
            jQuery('#merge-fields-modal').hide();
        }
        
        function viewEmailContent(logId) {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bst_get_email_content',
                    log_id: logId,
                    nonce: jQuery('#booking_nonce').val() || jQuery('input[name="bst_tour_bookings_nonce"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        jQuery('#email-content-body').html(response.data);
                        jQuery('#email-content-modal').show();
                    } else {
                        alert('Error loading email content: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Error loading email content');
                }
            });
        }
        
        function resendEmail(logId) {
            if (!confirm('Are you sure you want to resend this email?')) {
                return;
            }
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bst_resend_email',
                    log_id: logId,
                    nonce: jQuery('#booking_nonce').val() || jQuery('input[name="bst_tour_bookings_nonce"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Email resent successfully');
                        location.reload();
                    } else {
                        alert('Error resending email: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Error resending email');
                }
            });
        }
        
        function deleteEmail(logId) {
            if (!confirm('Are you sure you want to delete this email from the log? This cannot be undone.')) {
                return;
            }
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bst_delete_email',
                    log_id: logId,
                    nonce: jQuery('#booking_nonce').val() || jQuery('input[name="bst_tour_bookings_nonce"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Email deleted successfully');
                        location.reload();
                    } else {
                        alert('Error deleting email: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Error deleting email');
                }
            });
        }
        
        // Close modal when clicking outside
        jQuery(document).on('click', '.bst-modal', function(e) {
            if (e.target === this) {
                closeEmailModal();
                closeSendEmailModal();
                closeMergeFieldsModal();
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler to get booking email log
     */
    public function ajax_get_booking_email_log() {
        // Check for either nonce - email system or tour bookings
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'bst_email_nonce') || 
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings_nonce');
        
        if (!$nonce_valid) {
            wp_die('Security check failed');
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        ob_start();
        // Don't include modals when refreshing via AJAX - they're already on the page
        $this->render_email_log($booking_id, false);
        $html = ob_get_clean();
        
        wp_send_json_success($html);
    }
    
    /**
     * AJAX handler to resend email
     */
    public function ajax_resend_email() {
        // Check for either nonce - email system or tour bookings
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'bst_email_nonce') || 
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings_nonce');
        
        if (!$nonce_valid) {
            wp_die('Security check failed');
        }
        
        $log_id = intval($_POST['log_id']);
        
        $email_manager = new BST_Email_Manager();
        $result = $email_manager->resend_email($log_id);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Email resent successfully'));
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * AJAX handler to delete an email from the log
     */
    public function ajax_delete_email() {
        // Check for either nonce - email system or tour bookings
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'bst_email_nonce') || 
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings_nonce');
        
        if (!$nonce_valid) {
            wp_die('Security check failed');
        }
        
        $log_id = intval($_POST['log_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bst_email_log';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $log_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Email deleted successfully'));
        } else {
            wp_send_json_error('Failed to delete email');
        }
    }
    
    /**
     * AJAX handler to get email content for modal viewing
     */
    public function ajax_get_email_content() {
        // Check for either nonce - email system or tour bookings
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'bst_email_nonce') || 
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings_nonce');
        
        if (!$nonce_valid) {
            wp_die('Security check failed');
        }
        
        $log_id = intval($_POST['log_id']);
        
        global $wpdb;
        $email_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_email_log WHERE id = %d",
            $log_id
        ));
        
        if (!$email_log) {
            wp_send_json_error('Email not found');
        }
        
        $content = '<div class="email-content-viewer">';
        $content .= '<div class="email-meta" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        $content .= '<h4 style="margin: 0 0 10px 0; color: #333;">' . esc_html($email_log->subject ?? 'No Subject') . '</h4>';
        $content .= '<div style="display: grid; grid-template-columns: 100px 1fr; gap: 5px 15px; font-size: 14px;">';
        $content .= '<strong>To:</strong><span>' . esc_html($email_log->recipient_email ?? 'Unknown') . '</span>';
        $content .= '<strong>Sent:</strong><span>' . ($email_log->sent_date ? date('M j, Y g:i A', strtotime($email_log->sent_date)) : 'Unknown Date') . '</span>';
        $content .= '<strong>Type:</strong><span>' . esc_html(ucfirst($email_log->email_type ?? 'Unknown')) . '</span>';
        $status = $email_log->sent_successfully ? 'Sent' : 'Failed';
        $content .= '<strong>Status:</strong><span>' . esc_html($status) . '</span>';
        if (!empty($email_log->sent_by)) {
            $content .= '<strong>Sent By:</strong><span>' . esc_html($email_log->sent_by) . '</span>';
        }
        $content .= '</div>';
        $content .= '</div>';
        
        $content .= '<div class="email-body-container">';
        $content .= '<h5 style="margin: 0 0 10px 0; color: #666;">Email Content:</h5>';
        $content .= '<div class="email-body" style="border: 1px solid #ddd; border-radius: 5px; padding: 20px; background: white; min-height: 200px; font-family: Arial, sans-serif; line-height: 1.5;">';
        
        // Process the email content to handle HTML properly
        $email_content = $email_log->content ?? 'No content available';
        
        // Clean up and process HTML content
        if (strpos($email_content, '<body') !== false) {
            // Extract content from body tag if present
            preg_match('/<body[^>]*>(.*?)<\/body>/is', $email_content, $matches);
            if (!empty($matches[1])) {
                $email_content = $matches[1];
            }
        }
        
        // Check if content contains HTML tags
        if (preg_match('/<[^>]+>/', $email_content)) {
            // Content contains HTML - display it in an iframe for safe rendering
            $content .= '<iframe srcdoc="' . esc_attr($email_content) . '" style="width: 100%; min-height: 500px; border: none; background: white;" onload="this.style.height=this.contentWindow.document.body.scrollHeight + \'px\';"></iframe>';
        } else {
            // Plain text content - convert line breaks and escape HTML
            $content .= nl2br(esc_html($email_content));
        }
        
        $content .= '</div>';
        $content .= '</div>';
        $content .= '</div>';
        
        wp_send_json_success($content);
    }
    
    /**
     * Get last sent email summary for a booking
     */
    public function get_last_email_summary($booking_id) {
        $email_manager = new BST_Email_Manager();
        
        $reservation = $email_manager->get_last_email_sent($booking_id, 'reservation');
        $finalization = $email_manager->get_last_email_sent($booking_id, 'finalization');
        $invoice = $email_manager->get_last_email_sent($booking_id, 'invoice');
        
        $summary = array();
        
        if ($reservation) {
            $summary['reservation'] = array(
                'sent_date' => $reservation['sent_date'],
                'recipient' => $reservation['recipient_email'],
                'subject' => $reservation['subject']
            );
        }
        
        if ($finalization) {
            $summary['finalization'] = array(
                'sent_date' => $finalization['sent_date'],
                'recipient' => $finalization['recipient_email'],
                'subject' => $finalization['subject']
            );
        }
        
        if ($invoice) {
            $summary['invoice'] = array(
                'sent_date' => $invoice['sent_date'],
                'recipient' => $invoice['recipient_email'],
                'subject' => $invoice['subject']
            );
        }
        
        return $summary;
    }
    
    /**
     * AJAX handler to get booking email data
     */
    public function ajax_get_booking_email_data() {
        // Check for either nonce - restored to original flexible checking
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'bst_email_nonce') || 
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings');
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        // Get only the guest data we need - much faster than SELECT *
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT guest1_first_name, guest1_last_name, guest1_email, guest2_first_name, guest2_last_name, guest2_email FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            wp_send_json_error('Booking not found');
            return;
        }
        
        // Format guest names
        $guest1_name = trim(($booking->guest1_first_name ?? '') . ' ' . ($booking->guest1_last_name ?? ''));
        $guest2_name = trim(($booking->guest2_first_name ?? '') . ' ' . ($booking->guest2_last_name ?? ''));
        
        $response_data = array(
            'guest1_email' => $booking->guest1_email ?? '',
            'guest1_name' => $guest1_name ?: 'Guest 1',
            'guest2_email' => $booking->guest2_email ?? '',
            'guest2_name' => $guest2_name ?: 'Guest 2'
        );
        
        wp_send_json_success($response_data);
    }
    
    /**
     * AJAX handler to send composed ad hoc email
     */
    public function ajax_send_adhoc_email_compose() {
        // Check for either nonce
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'bst_email_nonce') || 
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings_nonce');
        
        if (!$nonce_valid) {
            wp_die('Security check failed');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $email_to = sanitize_email($_POST['email_to']);
        $email_cc = sanitize_email($_POST['email_cc']);
        $subject = wp_unslash(sanitize_text_field($_POST['subject']));
        $message = wp_kses_post(wp_unslash($_POST['message']));
        $email_type = sanitize_text_field($_POST['email_type'] ?? 'Ad Hoc');
        $batch_id = !empty($_POST['batch_id']) ? intval($_POST['batch_id']) : null;
        
        if (!$email_to) {
            wp_send_json_error('Recipient email is required');
        }
        
        if (!$subject) {
            wp_send_json_error('Email subject is required');
        }
        
        if (!$message) {
            wp_send_json_error('Email message is required');
        }
        
        // Get booking
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            wp_send_json_error('Booking not found');
        }
        
        // Process merge fields in subject and message
        $merge_fields = new BST_Email_Merge_Fields();
        $processed_subject = $merge_fields->process_merge_fields($subject, $booking);
        $processed_message = $merge_fields->process_merge_fields($message, $booking);
        
        // Message comes from TinyMCE which outputs HTML, so we use wp_kses_post to sanitize\n        // If the message doesn't contain HTML tags, convert line breaks to <br> tags
        if (strip_tags($processed_message) === $processed_message) {
            // Plain text - convert line breaks
            $processed_message = nl2br(esc_html($processed_message));
        }
        // Otherwise it's already HTML from TinyMCE, already sanitized by wp_kses_post above
        
        // Wrap content in HTML structure
        $html_content = $this->wrap_simple_html($processed_message);
        
        // Handle file attachment
        $attachments = array();
        if (isset($_FILES['email_attachment']) && $_FILES['email_attachment']['error'] === UPLOAD_ERR_OK) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            
            $file = $_FILES['email_attachment'];
            $upload_overrides = array('test_form' => false);
            
            $movefile = wp_handle_upload($file, $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                $attachments[] = $movefile['file'];
            } else {
                error_log('BST Email: File upload error - ' . ($movefile['error'] ?? 'Unknown error'));
            }
        }
        
        // Prepare headers
        $from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
        $from_email = get_option('bst_from_email_address', 'info@bluestradatours.com');
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        
        if ($email_cc) {
            $headers[] = 'Cc: ' . $email_cc;
        }
        
        // Send email using configured method
        $email_method = get_option('bst_email_method', 'wp_mail');
        
        if ($email_method === 'gmail') {
            // Send using Gmail API
            $gmail_api = new BST_Gmail_API();
            $send_result = $gmail_api->send_email(
                $email_to, 
                $processed_subject, 
                $html_content, 
                get_bloginfo('name'),
                $booking->id,
                null,
                $attachments
            );
            $sent = $send_result['success'];
            $gmail_message_id = $send_result['gmail_message_id'];
            $gmail_thread_id = $send_result['gmail_thread_id'];
            $message_id = $send_result['message_id'] ?? null;
        } else {
            // Send using WordPress wp_mail
            $sent = wp_mail($email_to, $processed_subject, $html_content, $headers, $attachments);
            $gmail_message_id = null;
            $gmail_thread_id = null;
            $message_id = null;
        }
        
        // Clean up temporary attachment file if it exists
        if (!empty($attachments)) {
            foreach ($attachments as $attachment_path) {
                if (file_exists($attachment_path)) {
                    @unlink($attachment_path);
                }
            }
        }
        
        if ($sent) {
            // Log the email with the specified email type
            $log_id = $this->log_email_to_database(
                $booking->id,
                null, // No template ID for ad hoc emails
                $email_type,
                $email_to,
                $processed_subject,
                $html_content,
                true,
                $gmail_message_id,
                $gmail_thread_id,
                $message_id,
                $batch_id,
                null // No error message on success
            );
            
            if ($log_id === false) {
                global $wpdb;
                error_log('BST Email Log: Failed to log email to database. Error: ' . $wpdb->last_error);
                wp_send_json_error('Email sent but failed to log. Check error logs.');
            }
            
            wp_send_json_success('Email sent successfully');
        } else {
            // Determine error message
            $error_msg = 'Failed to send email';
            if ($email_method === 'gmail' && !empty($send_result['error'])) {
                $error_msg = $send_result['error'];
            } elseif ($email_method === 'wp_mail') {
                $error_msg = 'wp_mail returned false';
                // If PHPMailer is available, log its ErrorInfo for deeper diagnostics
                global $phpmailer;
                if ($phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer && !empty($phpmailer->ErrorInfo)) {
                    error_log('BST Email: wp_mail ErrorInfo: ' . $phpmailer->ErrorInfo);
                }

                // Log wp_mail arguments for debugging
                error_log('BST Email Debug: wp_mail args ' . wp_json_encode(array(
                    'to'      => $email_to,
                    'subject' => $processed_subject,
                    'headers' => $headers,
                )));
            }

            // Log detailed error for debugging
            $debug_context = array(
                'booking_id' => $booking->id,
                'email_to'   => $email_to,
                'email_type' => $email_type,
                'method'     => $email_method,
                'batch_id'   => $batch_id,
            );
            error_log('BST Email: Ad hoc send failed: ' . $error_msg . ' | Context: ' . wp_json_encode($debug_context));
            if (isset($send_result) && is_array($send_result)) {
                error_log('BST Email: Ad hoc send_result: ' . wp_json_encode($send_result));
            }

            // Log the failed email attempt
            $this->log_email_to_database(
                $booking->id,
                null,
                $email_type,
                $email_to,
                $processed_subject,
                $html_content,
                false,
                null,
                null,
                null,
                $batch_id,
                $error_msg
            );
            
            wp_send_json_error($error_msg);
        }
    }
    
    /**
     * Log email to database (helper method)
     */
    private function log_email_to_database($booking_id, $template_id, $email_type, $recipient, $subject, $content, $success, $gmail_message_id = null, $gmail_thread_id = null, $message_id = null, $batch_id = null, $error_message = null) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'bst_email_log',
            array(
                'booking_id' => $booking_id,
                'template_id' => $template_id,
                'email_type' => $email_type,
                'recipient_email' => $recipient,
                'subject' => $subject,
                'content' => $content,
                'sent_date' => current_time('mysql'),
                'sent_by' => wp_get_current_user()->display_name,
                'sent_successfully' => $success ? 1 : 0,
                'gmail_message_id' => $gmail_message_id,
                'gmail_thread_id' => $gmail_thread_id,
                'direction' => 'outbound',
                'message_id' => $message_id,
                'batch_id' => $batch_id,
                'error_message' => $error_message
            ),
            array(
                '%d',   // booking_id
                '%d',   // template_id
                '%s',   // email_type
                '%s',   // recipient_email
                '%s',   // subject
                '%s',   // content
                '%s',   // sent_date
                '%s',   // sent_by
                '%d',   // sent_successfully
                '%s',   // gmail_message_id
                '%s',   // gmail_thread_id
                '%s',   // direction
                '%s',   // message_id
                '%d',   // batch_id
                '%s'    // error_message
            )
        );
        
        if ($result === false) {
            error_log('BST Email Log Database Insert Error: ' . $wpdb->last_error);
            error_log('BST Email Log Insert Data: ' . print_r(array(
                'booking_id' => $booking_id,
                'email_type' => $email_type,
                'recipient' => $recipient,
                'subject' => $subject
            ), true));
        }
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * AJAX handler to create a new email batch
     */
    public function ajax_create_email_batch() {
        // Security check
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'bst_email_nonce') || 
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings_nonce');
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        global $wpdb;
        
        // Get parameters
        $email_type = sanitize_text_field($_POST['email_type'] ?? 'Finalization');
        $template_id = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
        $email_subject = sanitize_text_field($_POST['email_subject'] ?? '');
        $cc_emails = sanitize_text_field($_POST['cc_emails'] ?? '');
        $tour_date_id = !empty($_POST['tour_date_id']) ? intval($_POST['tour_date_id']) : null;
        $total_emails = intval($_POST['total_emails'] ?? 0);
        $is_test = intval($_POST['is_test'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        // Insert batch record
        $result = $wpdb->insert(
            $wpdb->prefix . 'bst_email_batch',
            array(
                'batch_timestamp' => current_time('mysql'),
                'sent_by_user_id' => get_current_user_id(),
                'email_type' => $email_type,
                'template_id' => $template_id,
                'email_subject' => $email_subject,
                'cc_emails' => $cc_emails,
                'tour_date_id' => $tour_date_id,
                'total_emails' => $total_emails,
                'successful_emails' => 0,
                'failed_emails' => 0,
                'is_test' => $is_test,
                'notes' => $notes
            ),
            array(
                '%s',   // batch_timestamp
                '%d',   // sent_by_user_id
                '%s',   // email_type
                '%d',   // template_id
                '%s',   // email_subject
                '%s',   // cc_emails
                '%d',   // tour_date_id
                '%d',   // total_emails
                '%d',   // successful_emails
                '%d',   // failed_emails
                '%d',   // is_test
                '%s'    // notes
            )
        );
        
        if ($result === false) {
            error_log('BST Email Batch Create Error: ' . $wpdb->last_error);
            wp_send_json_error('Failed to create email batch');
        }
        
        $batch_id = $wpdb->insert_id;
        wp_send_json_success(array('batch_id' => $batch_id));
    }
    
    /**
     * AJAX handler to update email batch counts
     */
    public function ajax_update_email_batch() {
        // Security check
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'bst_email_nonce') || 
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings_nonce');
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        global $wpdb;
        
        $batch_id = intval($_POST['batch_id']);
        $successful_count = intval($_POST['successful_emails'] ?? 0);
        $failed_count = intval($_POST['failed_emails'] ?? 0);
        
        if (!$batch_id) {
            wp_send_json_error('Batch ID is required');
        }
        
        // Update batch record
        $result = $wpdb->update(
            $wpdb->prefix . 'bst_email_batch',
            array(
                'successful_emails' => $successful_count,
                'failed_emails' => $failed_count
            ),
            array('id' => $batch_id),
            array('%d', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            error_log('BST Email Batch Update Error: ' . $wpdb->last_error);
            wp_send_json_error('Failed to update email batch');
        }
        
        wp_send_json_success();
    }
    
    /**
     * Get merge fields for email composition
     */
    public function ajax_get_merge_fields() {
        // Verify nonce - check both possible nonce values
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_email_nonce');
                      
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        try {
            // If booking_id is provided, get only fields with actual values efficiently
            if (isset($_POST['booking_id']) && !empty($_POST['booking_id'])) {
                $booking_id = intval($_POST['booking_id']);
                
                global $wpdb;
                $booking = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
                    $booking_id
                ));
                
                if ($booking) {
                    // Fast approach - only build fields that have actual values
                    $populated_fields = $this->get_populated_fields_fast($booking);
                    wp_send_json_success(array('fields' => $populated_fields));
                    return;
                }
            }
            
            // Default response - empty fields when no booking
            wp_send_json_success(array('fields' => array()));
            
        } catch (Exception $e) {
            error_log('Error getting merge fields: ' . $e->getMessage());
            wp_send_json_error('Failed to get merge fields');
        }
    }
    
    /**
     * Helper to get field value from booking
     */
    private function get_field_value($booking, $field_key) {
        // Simple field mapping - could be expanded
        switch ($field_key) {
            case 'booking_id':
                return $booking->id ?? 'undefined';
            case 'booking_status':
                return $booking->booking_status ?? 'undefined';
            case 'booking_method':
                return $booking->booking_method ?? 'undefined';
            case 'guest1_first_name':
                return $booking->guest1_first_name ?? 'undefined';
            case 'guest1_last_name':
                return $booking->guest1_last_name ?? 'undefined';
            case 'guest1_email':
                return $booking->guest1_email ?? 'undefined';
            case 'guest2_first_name':
                return $booking->guest2_first_name ?? 'undefined';
            case 'guest2_last_name':
                return $booking->guest2_last_name ?? 'undefined';
            case 'guest2_email':
                return $booking->guest2_email ?? 'undefined';
            default:
                // Check if field exists in booking object
                return $booking->$field_key ?? 'undefined';
        }
    }
    
    /**
     * Fast method to get only populated fields without processing all fields
     * Uses centralized BST_Email_Merge_Fields for consistent field definitions
     */
    private function get_populated_fields_fast($booking) {
        // Use centralized merge fields class for consistent field definitions
        $merge_fields = new BST_Email_Merge_Fields();
        $available_fields = $merge_fields->get_available_fields();
        $field_values = $merge_fields->get_field_values($booking);
        
        $populated_fields = array();
        
        // Fields that generate HTML - show description instead of actual HTML
        $html_generator_fields = array(
            'BstEmailSignature' => 'Email signature with logo and contact info',
            'BstBookingSummary' => 'Generates BST admin booking summary (HTML)',
            'CustBookingSummary' => 'Generates customer booking summary (HTML)',
            'BankWireInstructions' => 'Generates bank wire payment instructions'
        );
        
        // Fields that need URL extraction (remove HTML anchor tags for ad hoc display)
        $link_fields = array(
            'ConfirmationLink',
            'CustBookingDetailsLink',
            'BstBookingDetailsLink',
            'AccountantInvoiceLink',
            'reservation_link',
            'finalization_link'
        );
        
        // Check if guest 2 has any data
        $has_guest2 = !empty($booking->guest2_first_name) || !empty($booking->guest2_last_name) || !empty($booking->guest2_email);
        
        // Process each category and only include fields with values
        foreach ($available_fields as $category => $fields) {
            // Skip Guest 2 category if there's no guest 2 data
            if ($category === 'Guest 2 Information' && !$has_guest2) {
                continue;
            }
            
            $category_data = array();
            
            foreach ($fields as $field_key => $field_label) {
                // For HTML generator fields, always include them with descriptive text
                if (isset($html_generator_fields[$field_key])) {
                    $category_data[$field_key] = array(
                        'label' => '{' . $field_key . '}',
                        'value' => $html_generator_fields[$field_key]
                    );
                    continue;
                }
                
                // Get the actual value for this field
                $value = isset($field_values[$field_key]) ? $field_values[$field_key] : '';
                
                // Extract URL from HTML links for better ad hoc display
                if (in_array($field_key, $link_fields) && !empty($value)) {
                    if (preg_match('/href=["\']([^"\']+)["\']/', $value, $matches)) {
                        $value = $matches[1];
                    }
                }
                
                // Only include fields that have actual non-empty values
                if (!empty($value) && $value !== 'undefined' && $value !== '(empty)' && $value !== 'N/A') {
                    $category_data[$field_key] = array(
                        'label' => '{' . $field_key . '}',
                        'value' => $value
                    );
                }
            }
            
            // Only include category if it has at least one populated field
            if (!empty($category_data)) {
                $populated_fields[$category] = $category_data;
            }
        }
        
        return $populated_fields;
    }
    
    /**
     * Simple HTML wrapper for email content
     */
    private function wrap_simple_html($content) {
        // Check if content already has <html> tags
        if (stripos($content, '<html') !== false) {
            return $content;
        }
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            padding: 0; 
            line-height: 1.6; 
            color: #333; 
        }
    </style>
</head>
<body>
    ' . $content . '
</body>
</html>';
    }
    
    /**
     * Render last email summary badges
     */
    public function render_last_email_badges($booking_id) {
        $summary = $this->get_last_email_summary($booking_id);
        
        if (empty($summary)) {
            echo '<span class="no-emails">No emails sent</span>';
            return;
        }
        
        foreach ($summary as $type => $data) {
            $days_ago = floor((time() - strtotime($data['sent_date'])) / (60 * 60 * 24));
            $time_text = $days_ago == 0 ? 'Today' : $days_ago . ' days ago';
            
            echo '<span class="email-summary-badge ' . $type . '" title="Last ' . $type . ' email sent ' . $time_text . ' to ' . esc_attr($data['recipient']) . '">';
            echo ucfirst(substr($type, 0, 1)) . ' - ' . $time_text;
            echo '</span> ';
        }
    }

    /**
     * Helper methods for calculated fields
     */
    private function get_combined_guest_names($booking) {
        $names = array();
        if (!empty($booking->guest1_first_name) || !empty($booking->guest1_last_name)) {
            $names[] = trim(($booking->guest1_first_name ?? '') . ' ' . ($booking->guest1_last_name ?? ''));
        }
        if (!empty($booking->guest2_first_name) || !empty($booking->guest2_last_name)) {
            $names[] = trim(($booking->guest2_first_name ?? '') . ' ' . ($booking->guest2_last_name ?? ''));
        }
        return implode(' & ', array_filter($names));
    }

    private function get_both_emails($booking) {
        $emails = array();
        if (!empty($booking->guest1_email)) {
            $emails[] = $booking->guest1_email;
        }
        if (!empty($booking->guest2_email) && $booking->guest2_email !== $booking->guest1_email) {
            $emails[] = $booking->guest2_email;
        }
        return implode(', ', $emails);
    }

    private function get_guest_count($booking) {
        $count = 0;
        if (!empty($booking->guest1_first_name) || !empty($booking->guest1_last_name)) {
            $count++;
        }
        if (!empty($booking->guest2_first_name) || !empty($booking->guest2_last_name)) {
            $count++;
        }
        return $count;
    }

    private function get_deposit_required($booking) {
        // If there's a deposit payment amount, use that
        if (!empty($booking->deposit_payment_amount) && $booking->deposit_payment_amount > 0) {
            return number_format($booking->deposit_payment_amount, 2);
        }
        
        // Otherwise calculate 15% of net tour price
        $net_tour_price = $booking->net_tour_price ?? 0;
        $deposit = $net_tour_price * 0.15;
        
        return number_format($deposit, 2);
    }

    private function get_balance_after_deposit($booking) {
        // If there's a balance payment amount, use that
        if (!empty($booking->balance_payment_amount) && $booking->balance_payment_amount > 0) {
            return number_format($booking->balance_payment_amount, 2);
        }
        
        // Otherwise calculate net tour price minus deposit required
        $net_tour_price = $booking->net_tour_price ?? 0;
        $deposit_required = $this->get_deposit_required_amount($booking); // Get actual number, not formatted
        $balance = $net_tour_price - $deposit_required;
        
        return number_format($balance, 2);
    }

    private function get_deposit_required_amount($booking) {
        // Helper method that returns numeric value for calculations
        if (!empty($booking->deposit_payment_amount) && $booking->deposit_payment_amount > 0) {
            return $booking->deposit_payment_amount;
        }
        
        $net_tour_price = $booking->net_tour_price ?? 0;
        return $net_tour_price * 0.15;
    }
    
    /**
     * AJAX handler to get all bookings for a tour date (for bulk emailing)
     */
    public function ajax_get_tour_date_bookings() {
        // Verify nonce
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_tour_bookings') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'bst_email_nonce');
                      
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $tour_date_id = intval($_POST['tour_date_id'] ?? 0);
        
        if (!$tour_date_id) {
            wp_send_json_error('Tour date ID is required');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bst_tour_booking';
        
        // Get all bookings for this tour date with guest email info
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT id, 
                    guest1_first_name, guest1_last_name, guest1_email,
                    guest2_first_name, guest2_last_name, guest2_email,
                    booking_status
             FROM $table_name 
             WHERE tour_date_id = %d 
             AND booking_status IN ('Booked', 'Finalized', 'Waiting List', 'Reserved')
             ORDER BY guest1_last_name, guest1_first_name",
            $tour_date_id
        ), ARRAY_A);
        
        if (empty($bookings)) {
            wp_send_json_error('No bookings found for this tour date');
            return;
        }
        
        // Format the booking data for frontend
        $formatted_bookings = array();
        foreach ($bookings as $booking) {
            $guest1_name = trim(($booking['guest1_first_name'] ?? '') . ' ' . ($booking['guest1_last_name'] ?? ''));
            $guest2_name = trim(($booking['guest2_first_name'] ?? '') . ' ' . ($booking['guest2_last_name'] ?? ''));
            
            $formatted_bookings[] = array(
                'id' => $booking['id'],
                'guest1_name' => $guest1_name ?: 'Guest 1',
                'guest1_email' => $booking['guest1_email'] ?? '',
                'guest2_name' => $guest2_name ?: 'Guest 2',
                'guest2_email' => $booking['guest2_email'] ?? '',
                'status' => $booking['booking_status']
            );
        }
        
        wp_send_json_success(array('bookings' => $formatted_bookings));
    }

    /**
     * Format email type for display
     */
    private function format_email_type_display($email_type) {
        switch ($email_type) {
            case 'adhoc':
                return 'Ad Hoc';
            case 'reservation':
                return 'Reservation';
            case 'finalization':
                return 'Finalization';
            case 'invoice':
                return 'Invoice';
            case 'notification':
                return 'Notification';
            default:
                return ucfirst($email_type);
        }
    }
}

// Initialize the email log viewer
new BST_Email_Log_Viewer();