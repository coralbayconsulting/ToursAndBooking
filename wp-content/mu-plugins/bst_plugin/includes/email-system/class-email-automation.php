<?php

/**
 * BST Email Automation Engine
 * 
 * Handles automated email triggers and processing
 */
class BST_Email_Automation {
    
    public function __construct() {
        // Hook into booking status changes and creation
        add_action('bst_booking_status_changed', array($this, 'process_status_change_triggers'), 10, 3);
        add_action('bst_booking_created', array($this, 'process_booking_created_triggers'), 10, 2);
        
        // Daily cron for reminder emails
        add_action('wp', array($this, 'schedule_daily_automation'));
        add_action('bst_daily_email_automation', array($this, 'process_daily_triggers'));
        
        // Hourly cron for inbox checking (when Gmail is enabled)
        add_action('wp', array($this, 'schedule_inbox_checking'));
        add_action('bst_hourly_inbox_check', array($this, 'process_inbox_checking'));
    }
    
    /**
     * Schedule daily automation check
     */
    public function schedule_daily_automation() {
        if (!wp_next_scheduled('bst_daily_email_automation')) {
            wp_schedule_event(time(), 'daily', 'bst_daily_email_automation');
        }
    }
    
    /**
     * Schedule hourly inbox checking (only when Gmail API is enabled)
     */
    public function schedule_inbox_checking() {
        $email_method = get_option('bst_email_method', 'wp_mail');
        $gmail_enabled = get_option('bst_gmail_api_enabled', false);
        $inbox_enabled = get_option('bst_gmail_inbox_checking_enabled', false);
        
        if ($email_method === 'gmail' && $gmail_enabled && $inbox_enabled) {
            if (!wp_next_scheduled('bst_hourly_inbox_check')) {
                wp_schedule_event(time(), 'hourly', 'bst_hourly_inbox_check');
            }
        } else {
            // Remove scheduled event if Gmail is disabled or inbox checking is off
            $timestamp = wp_next_scheduled('bst_hourly_inbox_check');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'bst_hourly_inbox_check');
            }
        }
    }
    
    /**
     * Process triggers when booking status changes
     */
    public function process_status_change_triggers($booking_id, $old_status, $new_status) {
        $this->process_triggers('booking_status_changed', array(
            'booking_id' => $booking_id,
            'old_status' => $old_status,
            'new_status' => $new_status
        ));
    }
    
    /**
     * Process triggers when booking is created
     */
    public function process_booking_created_triggers($booking_id, $booking_data) {
        $this->process_triggers('booking_created', array(
            'booking_id' => $booking_id,
            'booking_data' => $booking_data
        ));
    }
    
    /**
     * Process daily automation triggers (reminders, etc.)
     */
    public function process_daily_triggers() {
        // Check if email automation is enabled
        $automation_enabled = get_option('bst_email_automation_enabled', false);
        if (!$automation_enabled) {
            error_log('BST Email Automation: Daily triggers disabled via settings');
            return;
        }
        
        error_log('BST Email Automation: Running daily triggers');
        
        // Process all time-based triggers
        $this->process_booking_pending_reminders();
        $this->process_reservation_reminders();
        $this->process_finalization_needed();
        $this->process_finalization_reminders();
        $this->process_finalization_pending_reminders();
    }
    
    /**
     * Process inbox checking for new customer emails (runs hourly)
     */
    public function process_inbox_checking() {
        error_log('BST Email Automation: Running inbox check');
        
        $email_method = get_option('bst_email_method', 'wp_mail');
        $gmail_enabled = get_option('bst_gmail_api_enabled', false);
        $inbox_enabled = get_option('bst_gmail_inbox_checking_enabled', false);
        
        // Only proceed if Gmail API is enabled and inbox checking is on
        if ($email_method !== 'gmail' || !$gmail_enabled || !$inbox_enabled) {
            error_log('BST Email Automation: Inbox checking not enabled, skipping');
            return;
        }
        
        try {
            // Initialize Gmail API
            require_once BST_PLUGIN_DIR . 'includes/email-system/class-gmail-api.php';
            $gmail_api = new BST_Gmail_API();
            
            if (!$gmail_api->is_authenticated()) {
                error_log('BST Email Automation: Gmail API not authenticated, skipping inbox check');
                return;
            }
            
            // Check for new emails
            $this->check_gmail_inbox($gmail_api);
            
        } catch (Exception $e) {
            error_log('BST Email Automation: Inbox checking error - ' . $e->getMessage());
        }
    }
    
    /**
     * Generic trigger processing
     */
    private function process_triggers($trigger_event, $context) {
        // Get all active email templates with triggers
        $templates = $this->get_templates_by_trigger_event($trigger_event);
        
        foreach ($templates as $template) {
            if ($this->should_trigger_email($template, $context)) {
                $this->send_automated_email($template['id'], $context['booking_id']);
            }
        }
    }
    
    /**
     * Get templates that match a trigger event
     */
    private function get_templates_by_trigger_event($trigger_event) {
        $templates = array();
        
        // Map trigger events to template triggers
        $trigger_mapping = array(
            'booking_created' => array('booking_completed', 'booking_pending', 'reservation_created', 'waiting_list_created'),
            'booking_status_changed' => array('booking_completed', 'reservation_created', 'finalization_completed')
        );
        
        if (!isset($trigger_mapping[$trigger_event])) {
            return $templates;
        }
        
        $template_triggers = $trigger_mapping[$trigger_event];
        
        foreach ($template_triggers as $trigger) {
            $posts = get_posts(array(
                'post_type' => 'email-template',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => '_bst_email_trigger',
                        'value' => $trigger,
                        'compare' => '='
                    ),
                    array(
                        'relation' => 'OR',
                        array(
                            'key' => '_bst_email_enabled',
                            'value' => '1',
                            'compare' => '='
                        ),
                        array(
                            'key' => '_bst_email_enabled',
                            'compare' => 'NOT EXISTS'  // Default to enabled if not set
                        )
                    )
                )
            ));
            
            foreach ($posts as $post) {
                $templates[] = array(
                    'id' => $post->ID,
                    'trigger' => get_post_meta($post->ID, '_bst_email_trigger', true),
                    'trigger_days' => get_post_meta($post->ID, '_bst_trigger_days', true),
                    'trigger_status' => get_post_meta($post->ID, '_bst_trigger_status', true),
                    'trigger_payment_method' => get_post_meta($post->ID, '_bst_trigger_payment_method', true),
                    'email_type' => get_post_meta($post->ID, '_bst_email_type', true)
                );
            }
        }
        
        return $templates;
    }
    
    /**
     * Check if email should be triggered based on conditions
     */
    private function should_trigger_email($template, $context) {
        $booking_id = $context['booking_id'];
        $booking = $this->get_booking_data($booking_id);
        
        if (!$booking) {
            return false;
        }
        
        // Check if this email has already been sent to prevent duplicates
        if ($this->has_email_been_sent($booking_id, $template['id'])) {
            return false;
        }
        
        switch ($template['trigger']) {
            case 'booking_completed':
                return $this->check_booking_completed($booking, $template, $context);
                
            case 'booking_pending':
                return $this->check_booking_pending($booking, $template, $context);
                
            case 'reservation_created':
                return $this->check_reservation_created($booking, $template, $context);
                
            case 'waiting_list_created':
                return $this->check_waiting_list_created($booking, $template, $context);
                
            case 'finalization_completed':
                return $this->check_finalization_completed($booking, $template, $context);
        }
        
        return false;
    }
    
    /**
     * Check booking completed trigger
     */
    private function check_booking_completed($booking, $template, $context) {
        // Check if status changed to Booked or booking created with Booked status
        if (isset($context['new_status'])) {
            return $context['new_status'] === 'Booked';
        }
        
        if (isset($context['booking_data'])) {
            return $booking->booking_status === 'Booked';
        }
        
        return false;
    }
    
    /**
     * Check booking pending trigger
     */
    private function check_booking_pending($booking, $template, $context) {
        // Check if booking is in pending status
        $is_pending = $booking->booking_status === 'Pending';
        
        // Check payment method if specified
        if (!empty($template['trigger_payment_method'])) {
            $payment_method = $booking->deposit_payment_method ?? '';
            if ($payment_method !== $template['trigger_payment_method']) {
                return false;
            }
        }
        
        return $is_pending;
    }
    
    /**
     * Check reservation created trigger
     */
    private function check_reservation_created($booking, $template, $context) {
        // Check if status changed to Reserved or booking created with Reserved status
        if (isset($context['new_status'])) {
            return $context['new_status'] === 'Reserved' || 
                   ($context['old_status'] === 'Waiting List' && $context['new_status'] === 'Reserved');
        }
        
        if (isset($context['booking_data'])) {
            return $booking->booking_status === 'Reserved';
        }
        
        return false;
    }
    
    /**
     * Check waiting list created trigger
     */
    private function check_waiting_list_created($booking, $template, $context) {
        if (isset($context['booking_data'])) {
            return $booking->booking_status === 'Waiting List';
        }
        
        return false;
    }
    
    /**
     * Check finalization completed trigger
     */
    private function check_finalization_completed($booking, $template, $context) {
        if (isset($context['new_status'])) {
            return $context['new_status'] === 'Finalized';
        }
        
        return false;
    }
    
    /**
     * Process booking pending reminders
     */
    private function process_booking_pending_reminders() {
        $templates = get_posts(array(
            'post_type' => 'email-template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_bst_email_trigger',
                    'value' => 'booking_pending_reminder',
                    'compare' => '='
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_bst_email_enabled',
                        'value' => '1',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_bst_email_enabled',
                        'compare' => 'NOT EXISTS'
                    )
                )
            )
        ));
        
        foreach ($templates as $template) {
            $days = get_post_meta($template->ID, '_bst_trigger_days', true) ?: 3;
            $payment_method = get_post_meta($template->ID, '_bst_trigger_payment_method', true);
            
            $this->process_reminder_trigger($template->ID, 'Pending', $days, array(
                'payment_method' => $payment_method
            ));
        }
    }
    
    /**
     * Process reservation reminders
     */
    private function process_reservation_reminders() {
        $templates = get_posts(array(
            'post_type' => 'email-template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_bst_email_trigger',
                    'value' => 'reservation_reminder',
                    'compare' => '='
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_bst_email_enabled',
                        'value' => '1',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_bst_email_enabled',
                        'compare' => 'NOT EXISTS'
                    )
                )
            )
        ));
        
        foreach ($templates as $template) {
            $days = get_post_meta($template->ID, '_bst_trigger_days', true) ?: 7;
            $this->process_reminder_trigger($template->ID, 'Reserved', $days);
        }
    }
    
    /**
     * Process finalization needed
     */
    private function process_finalization_needed() {
        $templates = get_posts(array(
            'post_type' => 'email-template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_bst_email_trigger',
                    'value' => 'finalization_needed',
                    'compare' => '='
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_bst_email_enabled',
                        'value' => '1',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_bst_email_enabled',
                        'compare' => 'NOT EXISTS'
                    )
                )
            )
        ));
        
        foreach ($templates as $template) {
            $days = get_post_meta($template->ID, '_bst_trigger_days', true) ?: 70;
            
            global $wpdb;
            $booking_table = $wpdb->prefix . 'bst_tour_booking';
            
            // Find bookings that need finalization
            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT b.id
                 FROM $booking_table b
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = b.tour_date_id AND pm.meta_key = 'start_date'
                 WHERE b.booking_status = 'Booked'
                 AND b.tour_date_id IS NOT NULL AND b.tour_date_id != 0
                 AND pm.meta_value IS NOT NULL AND pm.meta_value != ''
                 AND DATEDIFF(pm.meta_value, CURDATE()) <= %d",
                $days
            ));
            
            foreach ($bookings as $booking) {
                if (!$this->has_email_been_sent($booking->id, $template->ID)) {
                    $this->send_automated_email($template->ID, $booking->id);
                }
            }
        }
    }
    
    /**
     * Process finalization reminders
     */
    private function process_finalization_reminders() {
        $templates = get_posts(array(
            'post_type' => 'email-template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_bst_email_trigger',
                    'value' => 'finalization_reminder',
                    'compare' => '='
                ),
                array(
                    'key' => '_bst_email_enabled',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));
        
        foreach ($templates as $template) {
            $days = get_post_meta($template->ID, '_bst_trigger_days', true) ?: 7;
            
            // Find bookings that received finalization email but haven't been finalized
            $this->process_follow_up_reminder($template->ID, 'finalization_needed', $days, 'Booked');
        }
    }
    
    /**
     * Process finalization pending reminders
     */
    private function process_finalization_pending_reminders() {
        $templates = get_posts(array(
            'post_type' => 'email-template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_bst_email_trigger',
                    'value' => 'finalization_pending_reminder',
                    'compare' => '='
                ),
                array(
                    'key' => '_bst_email_enabled',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));
        
        foreach ($templates as $template) {
            $days = get_post_meta($template->ID, '_bst_trigger_days', true) ?: 3;
            $payment_method = get_post_meta($template->ID, '_bst_trigger_payment_method', true);
            
            // Find bookings with finalization submitted but payment pending
            $this->process_reminder_trigger($template->ID, 'Booked', $days, array(
                'payment_method' => $payment_method,
                'finalization_submitted' => true
            ));
        }
    }
    
    /**
     * Generic reminder trigger processor
     */
    private function process_reminder_trigger($template_id, $status, $days, $conditions = array()) {
        global $wpdb;
        $booking_table = $wpdb->prefix . 'bst_tour_booking';
        
        $where_clauses = array("booking_status = %s");
        $where_params = array($status);
        
        // Add payment method condition if specified
        if (!empty($conditions['payment_method'])) {
            $where_clauses[] = "deposit_payment_method = %s";
            $where_params[] = $conditions['payment_method'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT id, created_date 
            FROM $booking_table 
            WHERE $where_sql
            AND DATEDIFF(CURDATE(), created_date) >= %d
        ", array_merge($where_params, array($days))));
        
        foreach ($bookings as $booking) {
            // Check if reminder has already been sent
            if (!$this->has_recent_email_been_sent($booking->id, $template_id, $days)) {
                $this->send_automated_email($template_id, $booking->id);
            }
        }
    }
    
    /**
     * Process follow-up reminders
     */
    private function process_follow_up_reminder($template_id, $original_trigger, $days, $required_status) {
        global $wpdb;
        $email_log_table = $wpdb->prefix . 'bst_email_log';
        $booking_table = $wpdb->prefix . 'bst_tour_booking';
        
        // Find bookings that received the original email but haven't progressed
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT l.booking_id, l.sent_date
            FROM $email_log_table l
            JOIN $booking_table b ON l.booking_id = b.id
            JOIN {$wpdb->posts} p ON l.template_id = p.ID
            WHERE p.post_type = 'email-template'
            AND p.meta_value = %s
            AND b.booking_status = %s
            AND DATEDIFF(CURDATE(), l.sent_date) >= %d
            AND l.sent_successfully = 1
        ", $original_trigger, $required_status, $days));
        
        foreach ($bookings as $booking) {
            if (!$this->has_recent_email_been_sent($booking->booking_id, $template_id, $days)) {
                $this->send_automated_email($template_id, $booking->booking_id);
            }
        }
    }
    
    /**
     * Send automated email
     */
    private function send_automated_email($template_id, $booking_id) {
        $email_manager = new BST_Email_Manager();
        $template = $email_manager->get_email_template_by_id($template_id);
        
        if (!$template) {
            error_log("BST Email Automation: Template $template_id not found");
            return false;
        }
        
        $email_type = $template['type'];
        
        switch ($email_type) {
            case 'reservation':
                $result = $email_manager->send_reservation_email($booking_id, $template_id);
                break;
            case 'finalization':
                $result = $email_manager->send_finalization_email($booking_id, $template_id);
                break;
            case 'invoice':
                $result = $email_manager->send_invoice_email($booking_id, $template_id);
                break;
            case 'notification':
                $result = $email_manager->send_notification_email($booking_id, $template_id);
                break;
            default:
                error_log("BST Email Automation: Unknown email type $email_type");
                return false;
        }
        
        if ($result['success']) {
            error_log("BST Email Automation: Sent $email_type email to booking $booking_id using template $template_id");
        } else {
            $error_message = isset($result['error']) ? $result['error'] : 'Unknown error';
            error_log("BST Email Automation: Failed to send email to booking $booking_id: " . $error_message);
        }
        
        return $result['success'];
    }
    
    /**
     * Check if email has been sent for this template and booking
     */
    private function has_email_been_sent($booking_id, $template_id) {
        global $wpdb;
        $email_log_table = $wpdb->prefix . 'bst_email_log';
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $email_log_table 
            WHERE booking_id = %d 
            AND template_id = %d 
            AND sent_successfully = 1
        ", $booking_id, $template_id));
        
        return $count > 0;
    }
    
    /**
     * Check if email has been sent recently (for reminders)
     */
    private function has_recent_email_been_sent($booking_id, $template_id, $days) {
        global $wpdb;
        $email_log_table = $wpdb->prefix . 'bst_email_log';
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $email_log_table 
            WHERE booking_id = %d 
            AND template_id = %d 
            AND sent_successfully = 1
            AND sent_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $booking_id, $template_id, $days));
        
        return $count > 0;
    }
    
    /**
     * Get booking data
     */
    private function get_booking_data($booking_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bst_tour_booking';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name WHERE id = %d
        ", $booking_id));
    }
    
    /**
     * Check Gmail inbox for new customer emails
     */
    private function check_gmail_inbox($gmail_api) {
        try {
            // Get timestamp of last check
            $last_check = get_option('bst_last_inbox_check', strtotime('-1 hour'));
            
            // Get the inbox checking label preference and processed label
            $inbox_label = get_option('bst_gmail_inbox_label', 'INBOX');
            $processed_label = get_option('bst_gmail_processed_label', 'BST-Processed');
            
            // Query for emails that haven't been processed yet
            $query = "newer_than:1h";
            
            // Add inbox label if specified
            if ($inbox_label && $inbox_label !== 'INBOX') {
                $query .= " label:$inbox_label";
            } else {
                $query .= " in:inbox";
            }
            
            // Exclude already processed emails by label
            $query .= " -label:$processed_label";
            
            error_log("BST Inbox Check: Using query: $query");
            
            $messages = $gmail_api->list_messages($query);
            
            if (empty($messages)) {
                error_log('BST Inbox Check: No new unprocessed messages found');
                update_option('bst_last_inbox_check', time());
                return;
            }
            
            error_log('BST Inbox Check: Found ' . count($messages) . ' unprocessed messages');
            
            foreach ($messages as $message) {
                $this->process_incoming_message($gmail_api, $message['id']);
            }
            
            // Update last check timestamp
            update_option('bst_last_inbox_check', time());
            
        } catch (Exception $e) {
            error_log('BST Inbox Check Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Process a single incoming email message
     */
    private function process_incoming_message($gmail_api, $message_id) {
        try {
            // Get full message details
            $message = $gmail_api->get_message($message_id);
            
            if (!$message) {
                error_log('BST Inbox: Failed to get message ' . $message_id);
                return;
            }
            
            // Extract headers
            $headers = $this->extract_message_headers($message);
            $booking_id = null;
            $email_log_id = null;
            
            // Check for our custom headers first
            if (isset($headers['X-BST-Booking-ID'])) {
                $booking_id = intval($headers['X-BST-Booking-ID']);
            }
            
            if (isset($headers['X-BST-Email-Log-ID'])) {
                $email_log_id = intval($headers['X-BST-Email-Log-ID']);
            }
            
            // If no custom headers, try to match by References or In-Reply-To
            if (!$booking_id && !$email_log_id) {
                $booking_id = $this->find_booking_by_thread($headers);
            }
            
            // If still no match, try to find by sender email
            if (!$booking_id) {
                $from_email = $this->extract_sender_email($headers);
                if ($from_email) {
                    $booking_id = $this->find_booking_by_email($from_email);
                }
            }
            
            if ($booking_id) {
                // Check if we've already processed this message (by labels, not database)
                if ($this->has_message_been_processed_by_labels($gmail_api, $message_id)) {
                    error_log("BST Inbox: Message $message_id already has processed label, skipping");
                    return;
                }
                
                // Log the incoming email
                $this->log_incoming_email($booking_id, $message, $headers, $email_log_id);
                
                // Mark as processed with appropriate labels
                $this->mark_message_processed_with_labels($gmail_api, $message_id, true); // true = matched
                
                error_log("BST Inbox: Processed message $message_id for booking $booking_id");
            } else {
                // Check if already processed (avoid processing unmatched emails repeatedly)
                if ($this->has_message_been_processed_by_labels($gmail_api, $message_id)) {
                    error_log("BST Inbox: Message $message_id already has processed label, skipping");
                    return;
                }
                
                // Mark as processed but unmatched
                $this->mark_message_processed_with_labels($gmail_api, $message_id, false); // false = unmatched
                
                error_log("BST Inbox: Could not match message $message_id to any booking");
            }
            
        } catch (Exception $e) {
            error_log('BST Inbox: Error processing message ' . $message_id . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Extract headers from Gmail message
     */
    private function extract_message_headers($message) {
        $headers = array();
        
        if (isset($message['payload']['headers'])) {
            foreach ($message['payload']['headers'] as $header) {
                $headers[$header['name']] = $header['value'];
            }
        }
        
        return $headers;
    }
    
    /**
     * Find booking by thread (References/In-Reply-To headers)
     */
    private function find_booking_by_thread($headers) {
        global $wpdb;
        
        $message_ids = array();
        
        // Check References header
        if (isset($headers['References'])) {
            $refs = preg_split('/\s+/', trim($headers['References']));
            $message_ids = array_merge($message_ids, $refs);
        }
        
        // Check In-Reply-To header
        if (isset($headers['In-Reply-To'])) {
            $message_ids[] = trim($headers['In-Reply-To']);
        }
        
        if (empty($message_ids)) {
            return null;
        }
        
        // Clean up message IDs (remove < > brackets)
        $message_ids = array_map(function($id) {
            return trim($id, '<>');
        }, $message_ids);
        
        // Look for any of these message IDs in our email log
        $email_log_table = $wpdb->prefix . 'bst_email_log';
        $placeholders = implode(',', array_fill(0, count($message_ids), '%s'));
        
        $booking_id = $wpdb->get_var($wpdb->prepare("
            SELECT booking_id 
            FROM $email_log_table 
            WHERE message_id IN ($placeholders) 
            OR gmail_message_id IN ($placeholders)
            ORDER BY sent_date DESC 
            LIMIT 1
        ", array_merge($message_ids, $message_ids)));
        
        return $booking_id ? intval($booking_id) : null;
    }
    
    /**
     * Find booking by sender email
     */
    private function find_booking_by_email($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bst_tour_booking';
        
        $booking_id = $wpdb->get_var($wpdb->prepare("
            SELECT id 
            FROM $table_name 
            WHERE guest1_email = %s OR guest2_email = %s 
            ORDER BY created_date DESC 
            LIMIT 1
        ", $email, $email));
        
        return $booking_id ? intval($booking_id) : null;
    }
    
    /**
     * Extract sender email from headers
     */
    private function extract_sender_email($headers) {
        if (isset($headers['From'])) {
            // Extract email from "Name <email@domain.com>" format
            if (preg_match('/<([^>]+)>/', $headers['From'], $matches)) {
                return $matches[1];
            }
            // If no angle brackets, assume the whole thing is the email
            return trim($headers['From']);
        }
        return null;
    }
    
    /**
     * Log incoming email to database
     */
    private function log_incoming_email($booking_id, $message, $headers, $reply_to_log_id = null) {
        global $wpdb;
        $email_log_table = $wpdb->prefix . 'bst_email_log';
        
        // Extract message content
        $content = $this->extract_message_content($message);
        $subject = isset($headers['Subject']) ? $headers['Subject'] : 'No Subject';
        $from_email = $this->extract_sender_email($headers);
        
        $wpdb->insert(
            $email_log_table,
            array(
                'booking_id' => $booking_id,
                'template_id' => null, // Incoming emails don't use templates
                'email_type' => 'adhoc', // Incoming customer emails
                'direction' => 'inbound',
                'recipient_email' => $from_email,
                'subject' => $subject,
                'content' => $content,
                'sent_date' => current_time('mysql'),
                'sent_by' => 'Customer',
                'gmail_message_id' => $message['id'],
                'gmail_thread_id' => isset($message['threadId']) ? $message['threadId'] : null,
                'message_id' => isset($headers['Message-ID']) ? trim($headers['Message-ID'], '<>') : null
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        error_log("BST Inbox: Logged incoming email from $from_email for booking $booking_id");
    }
    
    /**
     * Extract text content from Gmail message
     */
    private function extract_message_content($message) {
        // This is a simplified version - you might want to improve this
        // to handle multipart messages better
        
        if (isset($message['payload']['body']['data'])) {
            return base64_decode(str_replace(array('-', '_'), array('+', '/'), $message['payload']['body']['data']));
        }
        
        if (isset($message['payload']['parts'])) {
            foreach ($message['payload']['parts'] as $part) {
                if ($part['mimeType'] === 'text/plain' && isset($part['body']['data'])) {
                    return base64_decode(str_replace(array('-', '_'), array('+', '/'), $part['body']['data']));
                }
            }
        }
        
        return 'Could not extract message content';
    }
    
    /**
     * Mark message as processed with appropriate labels
     */
    private function mark_message_processed_with_labels($gmail_api, $message_id, $matched = true) {
        $processed_label = get_option('bst_gmail_processed_label', 'BST-Processed');
        $matched_label = get_option('bst_gmail_matched_label', 'BST-Matched');
        $unmatched_label = get_option('bst_gmail_unmatched_label', 'BST-Unmatched');
        $mark_as_read = get_option('bst_gmail_mark_processed_as_read', true);
        
        try {
            // Always add the main processed label
            $gmail_api->add_label_to_message($message_id, $processed_label);
            
            // Add specific match status label
            if ($matched) {
                $gmail_api->add_label_to_message($message_id, $matched_label);
            } else {
                $gmail_api->add_label_to_message($message_id, $unmatched_label);
            }
            
            // Optionally mark as read
            if ($mark_as_read) {
                $gmail_api->mark_message_as_read($message_id);
            }
            
        } catch (Exception $e) {
            error_log('BST Inbox: Failed to apply labels to message: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if message has already been processed by checking for processed label
     */
    private function has_message_been_processed_by_labels($gmail_api, $message_id) {
        $processed_label = get_option('bst_gmail_processed_label', 'BST-Processed');
        
        try {
            // Get the message to check its labels
            $message = $gmail_api->get_message($message_id);
            
            if (isset($message['labelIds'])) {
                // Check if the processed label is already applied
                return in_array($processed_label, $message['labelIds']);
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('BST Inbox: Error checking message labels: ' . $e->getMessage());
            return false; // If we can't check, assume not processed to avoid skipping
        }
    }
    
    /**
     * Check if message has already been processed to prevent duplicates
     */
    private function has_message_been_processed($gmail_message_id, $headers) {
        global $wpdb;
        $email_log_table = $wpdb->prefix . 'bst_email_log';
        
        // Check by Gmail message ID first (most reliable)
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $email_log_table 
            WHERE gmail_message_id = %s 
            AND direction = 'inbound'
        ", $gmail_message_id));
        
        if ($count > 0) {
            return true;
        }
        
        // Fallback: Check by Message-ID header (for non-Gmail messages or edge cases)
        if (isset($headers['Message-ID'])) {
            $message_id = trim($headers['Message-ID'], '<>');
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM $email_log_table 
                WHERE message_id = %s 
                AND direction = 'inbound'
            ", $message_id));
            
            if ($count > 0) {
                return true;
            }
        }
        
        return false;
    }
}

// Automation is initialized in class-bst-plugin.php to prevent duplicate instances