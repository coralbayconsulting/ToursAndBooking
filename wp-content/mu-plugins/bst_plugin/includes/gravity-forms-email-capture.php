<?php
/**
 * Gravity Forms Email Capture
 * 
 * Captures GF9 and GF10 notification emails and logs them to the email log
 */

if (!defined('ABSPATH')) exit;

/**
 * Hook into wp_mail to capture GF9 and GF10 emails
 */
add_filter('wp_mail', 'bst_capture_gravity_forms_emails', 10, 1);

function bst_capture_gravity_forms_emails($args) {
    // Only process if we're in a Gravity Forms context
    if (!class_exists('GFForms')) {
        return $args;
    }
    
    // Check if this is triggered by GF notification
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 30);
    $is_gf_notification = false;
    $form_id = null;
    $entry_id = null;
    $entry = null;
    
    foreach ($backtrace as $trace) {
        if (isset($trace['class']) && strpos($trace['class'], 'GF') === 0) {
            $is_gf_notification = true;
        }
        // Try to find send_notifications method with entry in args
        if (isset($trace['function']) && ($trace['function'] === 'send_notifications' || $trace['function'] === 'send_notification')) {
            $is_gf_notification = true;
            // send_notification signature: send_notification($notification, $form, $entry, ...)
            // args[0] = notification
            // args[1] = form (has 'id' = form_id)
            // args[2] = entry (has 'id' = entry_id)
            if (isset($trace['args']) && count($trace['args']) >= 3) {
                $notification = $trace['args'][0];
                $form = $trace['args'][1];
                $entry = $trace['args'][2];
                
                if (is_array($form) && isset($form['id']) && is_array($entry) && isset($entry['id'])) {
                    $form_id = $form['id'];
                    $entry_id = $entry['id'];
                    break;
                }
            }
        }
    }
    
    if (!$is_gf_notification) {
        return $args;
    }
    
    // Try to get entry from global context if not found in backtrace
    if (!$entry_id) {
        global $bst_current_gf_entry;
        if (isset($bst_current_gf_entry)) {
            $entry = $bst_current_gf_entry;
            $entry_id = $entry['id'];
            $form_id = $entry['form_id'];
        }
    }
    
    // If no entry found, can't determine form - skip
    if (!$entry_id) {
        return $args;
    }
    
    // Only process GF9 and GF10
    if (!in_array($form_id, ['9', '10', 9, 10])) {
        return $args;
    }
    
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    // Extract email data
    $to = $args['to'];
    $subject = $args['subject'];
    $message = $args['message'];
    $headers = isset($args['headers']) ? $args['headers'] : '';
    
    // Handle array of recipients (take first one)
    if (is_array($to)) {
        $to = $to[0];
    }
    
    // Extract From address from headers
    $from_address = 'Gravity Forms'; // Default fallback
    if (!empty($headers)) {
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (stripos($header, 'From:') === 0) {
                    $from_address = trim(substr($header, 5));
                    break;
                }
            }
        } elseif (is_string($headers)) {
            if (preg_match('/From:\s*(.+?)(?:\r?\n|$)/i', $headers, $matches)) {
                $from_address = trim($matches[1]);
            }
        }
    }
    
    // Look up booking by entry_id
    $booking = null;
    if ($form_id == 9) {
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $booking_table WHERE booking_entry_id = %d LIMIT 1",
            $entry_id
        ));
    } elseif ($form_id == 10) {
        // Try finalization_entry_id first (for re-sent notifications after finalization)
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $booking_table WHERE finalization_entry_id = %d LIMIT 1",
            $entry_id
        ));
        
        // If not found, try field 261 (booking_update_id) - the booking exists but hasn't been updated yet
        if (!$booking && $entry) {
            $booking_id = intval(rgar($entry, '261'));
            if ($booking_id > 0) {
                $booking = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $booking_table WHERE id = %d LIMIT 1",
                    $booking_id
                ));
            }
        }
    }
    
    // Check if this email is to guest1 (customer-facing email)
    $is_customer_email = false;
    if ($booking && !empty($booking->guest1_email)) {
        $is_customer_email = (strtolower(trim($to)) === strtolower(trim($booking->guest1_email)));
    }
    
    // Only log customer-facing emails
    if (!$is_customer_email) {
        return $args;
    }
    
    // If booking exists, log immediately
    if ($booking) {
        bst_log_gravity_forms_email($booking->id, $entry_id, $form_id, $to, $subject, $message, $from_address);
    } else {
        // Booking doesn't exist yet (initial GF9 submission)
        // Store in transient for 30 seconds
        $transient_key = 'bst_gf_email_' . $entry_id;
        $email_data = array(
            'entry_id' => $entry_id,
            'form_id' => $form_id,
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'from_address' => $from_address,
            'timestamp' => current_time('mysql')
        );
        set_transient($transient_key, $email_data, 30); // 30 seconds
    }
    
    return $args;
}

/**
 * Log Gravity Forms email to database
 */
function bst_log_gravity_forms_email($booking_id, $entry_id, $form_id, $to, $subject, $message, $from_address = 'Gravity Forms') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bst_email_log';
    
    // Determine email type - GF notifications are logged as 'Gravity'
    $email_type = 'Gravity';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'booking_id' => $booking_id,
            'template_id' => null, // GF notifications don't use our templates
            'email_type' => $email_type,
            'recipient_email' => $to,
            'subject' => $subject,
            'content' => $message,
            'sent_date' => current_time('mysql'),
            'sent_by' => $from_address,
            'sent_successfully' => 1, // If wp_mail was called, we assume it succeeded
            'direction' => 'outbound',
            'batch_id' => null, // Gravity Forms emails are not part of batches
            'error_message' => null
        ),
        array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s')
    );
    
    return $result;
}

/**
 * Set global entry context before GF sends notifications
 */
add_action('gform_pre_send_email', 'bst_set_gf_entry_context', 10, 4);

function bst_set_gf_entry_context($is_disabled, $form, $entry, $notification) {
    global $bst_current_gf_entry, $bst_current_gf_form;
    $bst_current_gf_entry = $entry;
    $bst_current_gf_form = $form;
    return $is_disabled;
}

/**
 * Clear global entry context after email is sent
 */
add_action('gform_after_email', 'bst_clear_gf_entry_context', 10, 0);

function bst_clear_gf_entry_context() {
    global $bst_current_gf_entry, $bst_current_gf_form;
    unset($bst_current_gf_entry);
    unset($bst_current_gf_form);
}

/**
 * Retrieve and log any pending GF9 emails after booking creation
 * Call this after creating a booking from GF9 submission
 */
function bst_process_pending_gf9_email($booking_id, $entry_id) {
    $transient_key = 'bst_gf_email_' . $entry_id;
    $email_data = get_transient($transient_key);
    
    if ($email_data) {
        // Log the email now that we have the booking_id
        bst_log_gravity_forms_email(
            $booking_id,
            $email_data['entry_id'],
            $email_data['form_id'],
            $email_data['to'],
            $email_data['subject'],
            $email_data['message'],
            isset($email_data['from_address']) ? $email_data['from_address'] : 'Gravity Forms'
        );
        
        // Clean up transient
        delete_transient($transient_key);
    }
}

/**
 * Retrieve and log any pending GF10 emails after finalization update
 * Call this after updating a booking from GF10 submission
 */
function bst_process_pending_gf10_email($booking_id, $entry_id) {
    $transient_key = 'bst_gf_email_' . $entry_id;
    $email_data = get_transient($transient_key);
    
    if ($email_data) {
        // Log the email now that we have the booking_id
        bst_log_gravity_forms_email(
            $booking_id,
            $email_data['entry_id'],
            $email_data['form_id'],
            $email_data['to'],
            $email_data['subject'],
            $email_data['message'],
            isset($email_data['from_address']) ? $email_data['from_address'] : 'Gravity Forms'
        );
        
        // Clean up transient
        delete_transient($transient_key);
    }
}
