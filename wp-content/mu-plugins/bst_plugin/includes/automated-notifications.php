<?php

/**
 * Automated Notification System for BST Plugin
 * Handles time-based notifications and finalization tracking
 */

// Add database column for finalization email tracking
add_action('init', 'bst_add_finalization_email_column');

function bst_add_finalization_email_column() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    
    // Check if column exists
    $column_exists = $wpdb->get_results($wpdb->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = %s 
        AND COLUMN_NAME = 'finalization_email_sent'
    ", $table_name));
    
    if (empty($column_exists)) {
        $wpdb->query("
            ALTER TABLE {$table_name} 
            ADD COLUMN finalization_email_sent BOOLEAN DEFAULT FALSE,
            ADD COLUMN finalization_email_sent_date DATETIME DEFAULT NULL
        ");
        error_log('BST: Added finalization_email_sent columns to tour booking table');
    }
}

// Schedule automated notification checks
add_action('wp_loaded', 'bst_schedule_automated_notifications');
add_action('bst_check_automated_notifications', 'bst_process_automated_notifications');

function bst_schedule_automated_notifications() {
    // Use transient to check only once per day
    if (get_transient('bst_notifications_cron_check')) {
        return;
    }
    set_transient('bst_notifications_cron_check', true, DAY_IN_SECONDS);
    
    if (!wp_next_scheduled('bst_check_automated_notifications')) {
        // Run once daily at 9 AM UTC
        wp_schedule_event(strtotime('09:00:00'), 'daily', 'bst_check_automated_notifications');
    }
}

/**
 * Main function to process all automated notifications
 */
function bst_process_automated_notifications() {
    error_log('BST: Starting automated notification checks');
    
    bst_check_bank_wire_pending();
    bst_check_reservation_not_booked();
    bst_check_tour_finalization_needed();
    
    error_log('BST: Completed automated notification checks');
}

/**
 * Check for bank wire payments pending more than 3 days
 */
function bst_check_bank_wire_pending() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    $three_days_ago = date('Y-m-d H:i:s', strtotime('-3 days'));
    
    $pending_bookings = $wpdb->get_results($wpdb->prepare("
        SELECT id, guest1_first_name, guest1_last_name, tour_id, tour_date_id,
               deposit_payment_method, balance_payment_method, created_date
        FROM {$table_name}
        WHERE booking_status = 'Pending'
        AND created_date <= %s
        AND booking_method = 'Web'
        AND (
            (deposit_payment_method IS NULL OR deposit_payment_method = '')
            OR (balance_payment_method IS NULL OR balance_payment_method = '')
        )
    ", $three_days_ago));
    
    foreach ($pending_bookings as $booking) {
        // Check if we've already notified about this booking today
        $notification_id = 'bank_wire_pending_' . $booking->id . '_' . date('Y-m-d');
        
        if (!bst_notification_exists($notification_id)) {
            $guest_name = bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '');
            $tour_info = function_exists('bst_live_booking_tour_display') ? bst_live_booking_tour_display($booking) : '';
            $days_pending = floor((time() - strtotime($booking->created_date)) / (24 * 60 * 60));
            
            $missing_payments = [];
            if (empty($booking->deposit_payment_method)) {
                $missing_payments[] = 'deposit';
            }
            if (empty($booking->balance_payment_method)) {
                $missing_payments[] = 'balance';
            }
            
            $message = sprintf(
                '%s: Bank wire pending for %d days - %s for %s (missing: %s) - <a href="%s">View Booking #%d</a>',
                date('Y-m-d'),
                $days_pending,
                esc_html($guest_name),
                esc_html($tour_info),
                implode(', ', $missing_payments),
                admin_url('admin.php?page=edit_booking&id=' . $booking->id),
                $booking->id
            );
            
            bst_create_automated_notification(
                $notification_id,
                $message,
                'bank_wire_pending',
                'error',
                $booking->id
            );
        }
    }
    
    error_log('BST: Checked ' . count($pending_bookings) . ' bookings for bank wire pending notifications');
}

/**
 * Check for reservations not booked for more than 3 days
 */
function bst_check_reservation_not_booked() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    $three_days_ago = date('Y-m-d H:i:s', strtotime('-3 days'));
    
    $reserved_bookings = $wpdb->get_results($wpdb->prepare("
        SELECT id, guest1_first_name, guest1_last_name, tour_id, tour_date_id, created_date
        FROM {$table_name}
        WHERE booking_status = 'Reserved'
        AND created_date <= %s
    ", $three_days_ago));
    
    foreach ($reserved_bookings as $booking) {
        // Check if we've already notified about this booking today
        $notification_id = 'reservation_not_booked_' . $booking->id . '_' . date('Y-m-d');
        
        if (!bst_notification_exists($notification_id)) {
            $guest_name = bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '');
            $tour_info = function_exists('bst_live_booking_tour_display') ? bst_live_booking_tour_display($booking) : '';
            $days_reserved = floor((time() - strtotime($booking->created_date)) / (24 * 60 * 60));
            
            $message = sprintf(
                '%s: Reservation not booked for %d days - %s for %s - <a href="%s">View Booking #%d</a>',
                date('Y-m-d'),
                $days_reserved,
                esc_html($guest_name),
                esc_html($tour_info),
                admin_url('admin.php?page=edit_booking&id=' . $booking->id),
                $booking->id
            );
            
            bst_create_automated_notification(
                $notification_id,
                $message,
                'reservation_not_booked',
                'warning',
                $booking->id
            );
        }
    }
    
    error_log('BST: Checked ' . count($reserved_bookings) . ' bookings for reservation not booked notifications');
}

/**
 * Check for tours needing finalization (within 120 days)
 */
function bst_check_tour_finalization_needed() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    $one_twenty_days_from_now = date('Y-m-d', strtotime('+120 days'));
    
    // Get bookings for tours starting within 120 days that haven't had finalization email sent
    $bookings_query = "
        SELECT b.id, b.guest1_first_name, b.guest1_last_name, b.tour_id, b.tour_date_id,
               td.tour_date, b.finalization_email_sent
        FROM {$table_name} b
        LEFT JOIN {$wpdb->prefix}bst_tour_dates td ON b.tour_date_id = td.id
        WHERE b.booking_status IN ('Confirmed', 'Pending')
        AND td.tour_date <= %s
        AND (b.finalization_email_sent IS NULL OR b.finalization_email_sent = 0)
    ";
    
    $finalization_bookings = $wpdb->get_results($wpdb->prepare($bookings_query, $one_twenty_days_from_now));
    
    foreach ($finalization_bookings as $booking) {
        // Check if we've already notified about this booking today
        $notification_id = 'tour_finalization_needed_' . $booking->id . '_' . date('Y-m-d');
        
        if (!bst_notification_exists($notification_id)) {
            $guest_name = bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '');
            $tour_date = $booking->tour_date ? date('M j, Y', strtotime($booking->tour_date)) : 'TBD';
            $days_until_tour = $booking->tour_date ? floor((strtotime($booking->tour_date) - time()) / (24 * 60 * 60)) : 0;
            
            $message = sprintf(
                '%s: Tour finalization needed (%d days until tour) - %s for %s on %s - <a href="%s">View Booking #%d</a>',
                date('Y-m-d'),
                $days_until_tour,
                esc_html($guest_name),
                esc_html(function_exists('bst_live_tour_title') ? bst_live_tour_title($booking->tour_id ?? 0) : ''),
                $tour_date,
                admin_url('admin.php?page=edit_booking&id=' . $booking->id),
                $booking->id
            );
            
            bst_create_automated_notification(
                $notification_id,
                $message,
                'tour_finalization_needed',
                'info',
                $booking->id
            );
        }
    }
    
    error_log('BST: Checked ' . count($finalization_bookings) . ' bookings for tour finalization notifications');
}

/**
 * Create an automated notification and send immediate emails
 */
function bst_create_automated_notification($notification_id, $message, $context, $type, $booking_id = null) {
    // Add the notification using the BST_Plugin method
    if (class_exists('BST_Plugin')) {
        BST_Plugin::add_notification(
            $notification_id,
            $message,
            $type,
            true,
            array('manage_options'),
            7 // Expires in 7 days
        );
        
        // Send immediate email notifications if enabled
        if (function_exists('bst_get_users_for_immediate_notification')) {
            $notification_data = array(
                'message' => $message,
                'context' => $context,
                'type' => $type,
                'booking_id' => $booking_id
            );
            
            $email_users = bst_get_users_for_immediate_notification($context);
            foreach ($email_users as $user_id) {
                bst_send_immediate_notification($user_id, $notification_data);
            }
            
            if (!empty($email_users)) {
                error_log('BST: Automated notification sent to ' . count($email_users) . ' users for context: ' . $context);
            }
        }
    }
}

/**
 * Check if a notification already exists today
 */
function bst_notification_exists($notification_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bst_notifications';
    $today = date('Y-m-d');
    
    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$table_name} 
        WHERE notification_id = %s 
        AND DATE(created_at) = %s
    ", $notification_id, $today));
    
    return $count > 0;
}

/**
 * Mark finalization email as sent for a booking
 */
function bst_mark_finalization_email_sent($booking_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    
    $updated = $wpdb->update(
        $table_name,
        array(
            'finalization_email_sent' => 1,
            'finalization_email_sent_date' => current_time('mysql')
        ),
        array('id' => $booking_id),
        array('%d', '%s'),
        array('%d')
    );
    
    if ($updated) {
        error_log("BST: Marked finalization email as sent for booking {$booking_id}");
    }
    
    return $updated;
}

/**
 * Reset finalization email status (for testing or re-sending)
 */
function bst_reset_finalization_email_status($booking_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    
    $updated = $wpdb->update(
        $table_name,
        array(
            'finalization_email_sent' => 0,
            'finalization_email_sent_date' => null
        ),
        array('id' => $booking_id),
        array('%d', '%s'),
        array('%d')
    );
    
    if ($updated) {
        error_log("BST: Reset finalization email status for booking {$booking_id}");
    }
    
    return $updated;
}

// Add admin tools for testing automated notifications
add_action('wp_ajax_bst_test_automated_notifications', 'bst_handle_test_automated_notifications');

function bst_handle_test_automated_notifications() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'bst_test_automated')) {
        wp_die('Invalid nonce');
    }
    
    if (function_exists('bst_process_automated_notifications')) {
        bst_process_automated_notifications();
        wp_send_json_success('Automated notification check completed. Check server logs for details.');
    } else {
        wp_send_json_error('Automated notification function not available');
    }
}
