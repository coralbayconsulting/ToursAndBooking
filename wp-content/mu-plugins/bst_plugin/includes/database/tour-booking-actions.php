<?php
// filepath: c:\Users\wayne\Local Sites\bluestradatours-staging\app\public\wp-content\mu-plugins\bst_plugin\includes\database\tour-booking-actions.php

// Require the rendering functions
require_once dirname(__FILE__) . '/../tour-booking-renderers.php';

/**
 * Generate the next invoice number for the current year
 * Format: YYYY-#### (e.g., 2026-0001, 2026-0002)
 * 
 * @return string The next invoice number
 */
function bst_generate_invoice_number() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    $current_year = date('Y');
    
    // Get the highest invoice number for the current year
    // Using CAST to convert the numeric part to integer for proper max calculation
    $last_number = $wpdb->get_var($wpdb->prepare(
        "SELECT CAST(SUBSTRING(booking_invoice_number, 6) AS UNSIGNED) as num 
        FROM $table_name 
        WHERE booking_invoice_number LIKE %s 
        ORDER BY num DESC 
        LIMIT 1",
        $current_year . '-%'
    ));
    
    $next_number = $last_number ? intval($last_number) + 1 : 1;
    
    // Format as YYYY-####
    return sprintf('%s-%04d', $current_year, $next_number);
}

/**
 * Calculate invoice fields (EU percent and vehicle amounts) for a booking
 * 
 * @param object $booking The booking object from database
 * @return array Array with keys: eu_percent, vehicle_1_amount, vehicle_2_amount
 */
function bst_calculate_invoice_fields($booking) {
    // Initialize return values
    $booking_eu_percent = 0;
    $booking_vehicle_1_use_amount = 0;
    $booking_vehicle_2_use_amount = 0;

    $tour_id = $booking->tour_id;
    $tour_extension_added = $booking->tour_extension_added;
    $vehicle1 = $booking->vehicle1;
    $vehicle2 = $booking->vehicle2;
    $package_vehicles = intval($booking->package_vehicles ?? 0);

    if ($tour_id) {
        $tour_post = get_post($tour_id);

        if ($tour_post) {
            $using_bst_owned_vehicles = get_post_meta($tour_id, 'using_bst_owned_vehicles', true);

            if ($tour_extension_added == 1) {
                // Extension added - use extension fields
                $booking_eu_percent = floatval(get_post_meta($tour_id, 'tour_with_extension_eu_percent', true));

                if ($using_bst_owned_vehicles == 1 || $using_bst_owned_vehicles === true) {
                    $vehicle_use_cost       = floatval(get_post_meta($tour_id, 'tour_with_extension_vehicle_use_cost', true));
                    $tour_driving_days      = intval(get_post_meta($tour_id, 'tour_driving_days', true));
                    $extension_driving_days = intval(get_post_meta($tour_id, 'extension_driving_days', true));

                    // Vehicle 1: Always charge base cost if package includes vehicles
                    $booking_vehicle_1_use_amount = $vehicle_use_cost;

                    // If vehicle1 is selected, extract and add upcharge
                    if (!empty($vehicle1)) {
                        $vehicle1_upcharge = bst_extract_vehicle_price($vehicle1);
                        if ($vehicle1_upcharge > 0) {
                            // Add full upcharge for the base tour
                            $booking_vehicle_1_use_amount += $vehicle1_upcharge;
                            // Add prorated extension cost: (upcharge / tour_driving_days) * extension_driving_days
                            if ($tour_driving_days > 0 && $extension_driving_days > 0) {
                                $extension_upgrade_cost = round(($vehicle1_upcharge / $tour_driving_days) * $extension_driving_days, 2);
                                $booking_vehicle_1_use_amount += $extension_upgrade_cost;
                            }
                        }
                    }

                    // Vehicle 2: Always charge base cost if package includes 2 vehicles
                    if ($package_vehicles == 2) {
                        $booking_vehicle_2_use_amount = $vehicle_use_cost;

                        // If vehicle2 is selected, extract and add upcharge
                        if (!empty($vehicle2)) {
                            $vehicle2_upcharge = bst_extract_vehicle_price($vehicle2);
                            if ($vehicle2_upcharge > 0) {
                                // Add full upcharge for the base tour
                                $booking_vehicle_2_use_amount += $vehicle2_upcharge;
                                // Add prorated extension cost: (upcharge / tour_driving_days) * extension_driving_days
                                if ($tour_driving_days > 0 && $extension_driving_days > 0) {
                                    $extension_upgrade_cost = round(($vehicle2_upcharge / $tour_driving_days) * $extension_driving_days, 2);
                                    $booking_vehicle_2_use_amount += $extension_upgrade_cost;
                                }
                            }
                        }
                    }
                }
            } else {
                // No extension - use base tour fields
                $booking_eu_percent = floatval(get_post_meta($tour_id, 'tour_eu_percent', true));

                if ($using_bst_owned_vehicles == 1 || $using_bst_owned_vehicles === true) {
                    $vehicle_use_cost = floatval(get_post_meta($tour_id, 'tour_vehicle_use_cost', true));

                    // Vehicle 1: Always charge base cost if package includes vehicles
                    $booking_vehicle_1_use_amount = $vehicle_use_cost;

                    // If vehicle1 is selected, extract and add upcharge
                    if (!empty($vehicle1)) {
                        $vehicle1_upcharge = bst_extract_vehicle_price($vehicle1);
                        if ($vehicle1_upcharge > 0) {
                            $booking_vehicle_1_use_amount += $vehicle1_upcharge;
                        }
                    }

                    // Vehicle 2: Always charge base cost if package includes 2 vehicles
                    if ($package_vehicles == 2) {
                        $booking_vehicle_2_use_amount = $vehicle_use_cost;

                        // If vehicle2 is selected, extract and add upcharge
                        if (!empty($vehicle2)) {
                            $vehicle2_upcharge = bst_extract_vehicle_price($vehicle2);
                            if ($vehicle2_upcharge > 0) {
                                $booking_vehicle_2_use_amount += $vehicle2_upcharge;
                            }
                        }
                    }
                }
            }
        }
    }

    return array(
        'eu_percent'       => $booking_eu_percent,
        'vehicle_1_amount' => $booking_vehicle_1_use_amount,
        'vehicle_2_amount' => $booking_vehicle_2_use_amount,
    );
}

/**
 * Create a booking notification for admin users
 * 
 * @param int $booking_id The booking ID
 * @param array $booking_data The booking data
 * @param string $action The action performed (created, added_to_waiting_list, etc.)
 * @param string $source The source context
 */
function bst_create_booking_notification($booking_id, $booking_data, $action, $source = '') {
    // Format guest name like the tour bookings list
    $guest1_first = $booking_data['guest1_first_name'] ?? '';
    $guest1_last = $booking_data['guest1_last_name'] ?? '';
    $guest2_first = $booking_data['guest2_first_name'] ?? '';
    $guest2_last = $booking_data['guest2_last_name'] ?? '';
    
    if (empty($guest2_first)) {
        $guest_name = $guest1_first . ' ' . $guest1_last;
    } else {
        if (empty($guest2_last) || $guest1_last === $guest2_last) {
            $guest_name = $guest1_first . ' & ' . $guest2_first . ' ' . $guest1_last;
        } else {
            $guest_name = $guest1_first . ' ' . $guest1_last . ' & ' . $guest2_first . ' ' . $guest2_last;
        }
    }
    
    // Format tour info
    $tour_info = ($booking_data['tour_text'] ?? 'Tour') . ' (' . ($booking_data['tour_date_text'] ?? 'Date') . ') - ' . ($booking_data['tour_package_text'] ?? 'Package');
    
    // Add pending status highlighting if applicable
    $status_note = '';
    if (!empty($booking_data['booking_status']) && $booking_data['booking_status'] === 'Pending') {
        $status_note = ' [PENDING]';
    }
    
    $booking_link = admin_url('admin.php?page=view_booking&id=' . $booking_id);
    
    // Create message based on action
    $message = '';
    $notification_type = 'info';
    
    switch ($action) {
        case 'created':
            $message = sprintf(
                '%s: New booking: %s for %s%s - <a href="%s">View Booking #%d</a>',
                date('Y-m-d'),
                esc_html($guest_name),
                esc_html($tour_info),
                esc_html($status_note),
                esc_url($booking_link),
                $booking_id
            );
            // Use warning color for pending bookings, success for others
            $notification_type = (!empty($booking_data['booking_status']) && $booking_data['booking_status'] === 'Pending') ? 'warning' : 'success';
            break;
            
        case 'added_to_waiting_list':
            $message = sprintf(
                '%s: %s was added to the waiting list for %s - <a href="%s">View Booking #%d</a>',
                date('Y-m-d'),
                esc_html($guest_name),
                esc_html($tour_info),
                esc_url($booking_link),
                $booking_id
            );
            $notification_type = 'warning';
            break;
            
        case 'finalized':
            $message = sprintf(
                '%s: %s finalized their booking for %s%s - <a href="%s">View Booking #%d</a>',
                date('Y-m-d'),
                esc_html($guest_name),
                esc_html($tour_info),
                esc_html($status_note),
                esc_url($booking_link),
                $booking_id
            );
            $notification_type = 'success';
            break;
            
        default:
            $message = sprintf(
                '%s: Booking updated: %s for %s - <a href="%s">View Booking #%d</a>',
                date('Y-m-d'),
                esc_html($guest_name),
                esc_html($tour_info),
                esc_url($booking_link),
                $booking_id
            );
            break;
    }
    
    // Add the notification using the BST_Plugin method
    if (class_exists('BST_Plugin')) {
        BST_Plugin::add_notification(
            $action . '_booking_' . $booking_id,
            $message,
            $notification_type,
            true,
            array('manage_options'),
            7 // Expires in 7 days
        );
        // Send immediate email notifications if enabled
        if (function_exists('bst_get_users_for_immediate_notification')) {
            $notification_data = array(
                'message' => $message,
                'context' => $source, // Use the source as context (gf9_submission, gf10_finalization, waiting_list)
                'type' => $notification_type,
                'booking_id' => $booking_id
            );
            
            $email_users = bst_get_users_for_immediate_notification($source);
            foreach ($email_users as $user_id) {
                bst_send_immediate_notification($user_id, $notification_data);
            }
            
        }
    }
}

/**
 * Helper function to convert empty strings to null values for consistent database storage
 * 
 * @param array $data Data array to process
 * @return array Data array with empty strings converted to null
 */
function bst_convert_empty_to_null($data) {
    foreach ($data as $key => $value) {
        // Convert empty strings to null, but preserve 0 values for numeric fields
        if ($value === '' || $value === null) {
            $data[$key] = null;
        }
        // Also handle '0000-00-00' dates that should be null
        if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            $data[$key] = null;
        }
    }
    return $data;
}

/**
 * Centralized function to create a new tour booking
 * 
 * @param array $data Booking data to insert
 * @param string $context Context for auto-sync logging
 * @return array Result with success status, booking ID, and any error messages
 */
function bst_create_tour_booking($data, $context = 'manual') {
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    $result = array(
        'success' => false,
        'booking_id' => 0,
        'error' => ''
    );
    
    try {
        // Add creation audit fields using shared utility
        $data = bst_add_audit_fields_create($data);
        
        // Insert the booking
        $insert_result = $wpdb->insert($booking_table, $data);
        
        if ($insert_result !== false) {
            $booking_id = $wpdb->insert_id;
            $result['success'] = true;
            $result['booking_id'] = $booking_id;
            
            // Fire booking created hook for email automation
            do_action('bst_booking_created', $booking_id, $data);
            
            // Add notifications for web-based bookings only
            if ($context === 'gf9_submission') {
                // GF9 submissions are new bookings (Pending or Booked)
                $action = 'created';
                bst_create_booking_notification($booking_id, $data, $action, $context);
            } elseif ($context === 'gf10_finalization') {
                // GF10 is for finalization of existing bookings
                $action = 'finalized';
                bst_create_booking_notification($booking_id, $data, $action, $context);
            } elseif ($context === 'waiting_list') {
                // ALL waiting list additions (web form AND admin interface)
                $action = 'added_to_waiting_list';
                bst_create_booking_notification($booking_id, $data, $action, $context);
            }
            
            // Trigger auto-sync if tour_date_id is present
            // Skip for Waiting List bookings since they don't affect availability
            if (!empty($data['tour_date_id']) && !empty($data['booking_status']) && $data['booking_status'] !== 'Waiting List') {
                do_action('bst_booking_saved', $data['tour_date_id'], $context);
            }
        } else {
            $result['error'] = 'Database insert failed: ' . $wpdb->last_error;
            
            // Add notification for database insert failures
            if ($context === 'gf9_submission' || $context === 'gf10_finalization') {
                $booking_entry_id = isset($data['booking_entry_id']) ? $data['booking_entry_id'] : 'unknown';
                $guest_name = (isset($data['guest1_first_name']) ? $data['guest1_first_name'] : '') . ' ' . 
                             (isset($data['guest1_last_name']) ? $data['guest1_last_name'] : '');
                $tour_info = isset($data['tour_text']) ? $data['tour_text'] : 'Unknown Tour';
                
                BST_Plugin::add_notification(
                    'database_insert_failed_' . $booking_entry_id . '_' . time(),
                    sprintf(
                        '<strong>Database Insert Failed</strong><br>Entry ID: %s<br>Guest: %s<br>Tour: %s<br>Error: %s<br>Data: <details><summary>View Data</summary><pre>%s</pre></details>',
                        esc_html($booking_entry_id),
                        esc_html(trim($guest_name)),
                        esc_html($tour_info),
                        esc_html($wpdb->last_error),
                        esc_html(print_r($data, true))
                    ),
                    'error',
                    true,
                    array('manage_options'),
                    30 // Expires in 30 days
                );
            }
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Create Booking Exception: ' . $e->getMessage());
        
        // Add notification for exceptions during booking creation
        if ($context === 'gf9_submission' || $context === 'gf10_finalization') {
            $booking_entry_id = isset($data['booking_entry_id']) ? $data['booking_entry_id'] : 'unknown';
            $guest_name = (isset($data['guest1_first_name']) ? $data['guest1_first_name'] : '') . ' ' . 
                         (isset($data['guest1_last_name']) ? $data['guest1_last_name'] : '');
            $tour_info = isset($data['tour_text']) ? $data['tour_text'] : 'Unknown Tour';
            
            // Get stack trace
            $stack_trace = $e->getTraceAsString();
            
            BST_Plugin::add_notification(
                'booking_creation_exception_' . $booking_entry_id . '_' . time(),
                sprintf(
                    '<strong>Booking Creation Exception</strong><br>Entry ID: %s<br>Guest: %s<br>Tour: %s<br>Exception: %s<br>Stack Trace: <details><summary>View Stack Trace</summary><pre>%s</pre></details>',
                    esc_html($booking_entry_id),
                    esc_html(trim($guest_name)),
                    esc_html($tour_info),
                    esc_html($e->getMessage()),
                    esc_html($stack_trace)
                ),
                'error',
                true,
                array('manage_options'),
                30 // Expires in 30 days
            );
        }
    }
    
    return $result;
}

/**
 * Create a tour booking with custom audit date (for paper bookings with deposit date)
 * This bypasses the standard audit field creation to use a custom create date
 */
function bst_create_tour_booking_with_custom_date($data, $context = 'manual') {
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    $result = array(
        'success' => false,
        'booking_id' => 0,
        'error' => ''
    );
    
    try {
        // Don't add audit fields - they're already in $data with custom date
        
        // Insert the booking
        $insert_result = $wpdb->insert($booking_table, $data);
        
        if ($insert_result !== false) {
            $booking_id = $wpdb->insert_id;
            $result['success'] = true;
            $result['booking_id'] = $booking_id;
            
            // Trigger auto-sync if tour_date_id is present
            if (!empty($data['tour_date_id'])) {
                do_action('bst_booking_saved', $data['tour_date_id'], $context);
            }
        } else {
            $result['error'] = 'Database insert failed: ' . $wpdb->last_error;
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Create Booking Exception: ' . $e->getMessage());
    }
    
    return $result;
}

/**
 * Centralized function to update an existing tour booking
 * 
 * @param int $booking_id The booking ID to update
 * @param array $data Booking data to update
 * @param string $context Context for auto-sync logging
 * @return array Result with success status and any error messages
 */
function bst_update_tour_booking($booking_id, $data, $context = 'manual') {
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    $result = array(
        'success' => false,
        'error' => ''
    );
    
    try {
        // Get tour_date_id before update for sync
        $tour_date_id = $wpdb->get_var($wpdb->prepare(
            "SELECT tour_date_id FROM $booking_table WHERE id = %d",
            $booking_id
        ));
        
        // Get old status for status change detection
        $old_status = $wpdb->get_var($wpdb->prepare(
            "SELECT booking_status FROM $booking_table WHERE id = %d",
            $booking_id
        ));
        
        // Generate invoice number during GF10 finalization if not already set
        if ($context === 'gf10_finalization') {
            $existing_invoice = $wpdb->get_var($wpdb->prepare(
                "SELECT booking_invoice_number FROM $booking_table WHERE id = %d",
                $booking_id
            ));
            if (empty($existing_invoice)) {
                // Try up to 3 times in case of race condition
                $max_attempts = 3;
                for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
                    $invoice_number = bst_generate_invoice_number();
                    // Check if this invoice number already exists
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $booking_table WHERE booking_invoice_number = %s",
                        $invoice_number
                    ));
                    if (!$exists) {
                        $data['booking_invoice_number'] = $invoice_number;
                        break;
                    }
                    // If last attempt failed, log error but continue with update
                    if ($attempt === $max_attempts) {
                        error_log("BST: Failed to generate unique invoice number after $max_attempts attempts for booking $booking_id");
                    }
                }
            }
        }
        
        // Add update audit fields using shared utility
        $data = bst_add_audit_fields_update($data);
        
        // Update the booking
        $update_result = $wpdb->update($booking_table, $data, array('id' => $booking_id));
        
        if ($update_result !== false) {
            $result['success'] = true;
            
            // Fire status changed hook for email automation if status changed
            if (isset($data['booking_status']) && $data['booking_status'] !== $old_status) {
                do_action('bst_booking_status_changed', $booking_id, $old_status, $data['booking_status']);
            }
            
            // Trigger auto-sync if critical fields involved
            $critical_fields = array('tour_date_id', 'package_vehicles', 'booking_status');
            $has_critical_field = false;
            foreach ($critical_fields as $field) {
                if (isset($data[$field])) {
                    $has_critical_field = true;
                    break;
                }
            }
            
            if ($has_critical_field) {
                // Skip availability sync if both old and new status are 'Waiting List'
                // (Waiting List bookings don't affect availability)
                $new_status = isset($data['booking_status']) ? $data['booking_status'] : $old_status;
                $skip_sync = ($old_status === 'Waiting List' && $new_status === 'Waiting List');
                
                if (!$skip_sync) {
                    // If tour_date_id was changed, sync both old and new tour dates
                    if (isset($data['tour_date_id']) && $data['tour_date_id'] != $tour_date_id) {
                        // Sync the old tour date first
                        if ($tour_date_id) {
                            do_action('bst_booking_saved', $tour_date_id, $context . '_old_date');
                        }
                        // Sync the new tour date
                        if ($data['tour_date_id']) {
                            do_action('bst_booking_saved', $data['tour_date_id'], $context . '_new_date');
                        }
                    } else {
                        // Use updated tour_date_id if it was changed, otherwise use original
                        $sync_tour_date_id = isset($data['tour_date_id']) ? $data['tour_date_id'] : $tour_date_id;
                        if ($sync_tour_date_id) {
                            do_action('bst_booking_saved', $sync_tour_date_id, $context);
                        }
                    }
                }
            }
        } else {
            $result['error'] = 'Database update failed: ' . $wpdb->last_error;
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Update Booking Exception: ' . $e->getMessage());
    }
    
    return $result;
}

/**
 * Centralized function to delete a tour booking
 * 
 * @param int $booking_id The booking ID to delete
 * @param string $context Context for auto-sync logging
 * @return array Result with success status and any error messages
 */
function bst_delete_tour_booking_by_id($booking_id, $context = 'manual') {
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    $result = array(
        'success' => false,
        'error' => ''
    );
    
    try {
        // Get tour_date_id and booking_status before deletion for sync
        $booking_data = $wpdb->get_row($wpdb->prepare(
            "SELECT tour_date_id, booking_status FROM $booking_table WHERE id = %d",
            $booking_id
        ));
        
        $tour_date_id = $booking_data ? $booking_data->tour_date_id : null;
        $booking_status = $booking_data ? $booking_data->booking_status : null;
        
        // Delete the booking
        $delete_result = $wpdb->delete($booking_table, array('id' => $booking_id));
        
        if ($delete_result !== false) {
            $result['success'] = true;
            
            // Trigger auto-sync for the affected tour date
            // Skip for Waiting List bookings since they don't affect availability
            if ($tour_date_id && $booking_status !== 'Waiting List') {
                do_action('bst_booking_deleted', $tour_date_id, $context);
            }
        } else {
            $result['error'] = 'Database delete failed: ' . $wpdb->last_error;
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Delete Booking Exception: ' . $e->getMessage());
    }
    
    return $result;
}

/**
 * Centralized function for bulk delete operations
 * 
 * @param string $where_clause WHERE clause for deletion (without WHERE keyword)
 * @param array $where_params Parameters for prepared statement
 * @param string $context Context for auto-sync logging
 * @return array Result with success status, affected tour dates, and any error messages
 */
function bst_bulk_delete_tour_bookings($where_clause, $where_params = array(), $context = 'bulk_delete') {
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    $result = array(
        'success' => false,
        'deleted_count' => 0,
        'affected_tour_dates' => array(),
        'error' => ''
    );
    
    try {
        // Get affected tour_date_ids before deletion for sync
        $tour_date_query = "SELECT DISTINCT tour_date_id FROM $booking_table WHERE $where_clause AND tour_date_id IS NOT NULL AND tour_date_id != ''";
        
        if (!empty($where_params)) {
            $affected_tour_dates = $wpdb->get_col($wpdb->prepare($tour_date_query, $where_params));
        } else {
            $affected_tour_dates = $wpdb->get_col($tour_date_query);
        }
        
        // Perform the deletion
        $delete_query = "DELETE FROM $booking_table WHERE $where_clause";
        
        if (!empty($where_params)) {
            $deleted_count = $wpdb->query($wpdb->prepare($delete_query, $where_params));
        } else {
            $deleted_count = $wpdb->query($delete_query);
        }
        
        if ($deleted_count !== false) {
            $result['success'] = true;
            $result['deleted_count'] = $deleted_count;
            $result['affected_tour_dates'] = $affected_tour_dates;
            
            // Trigger bulk auto-sync for affected tour dates
            if (!empty($affected_tour_dates)) {
                do_action('bst_bookings_bulk_updated', $affected_tour_dates, $context);
            }
        } else {
            $result['error'] = 'Database bulk delete failed: ' . $wpdb->last_error;
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Bulk Delete Booking Exception: ' . $e->getMessage());
    }
    
    return $result;
}

function bst_save_tour_booking() {
    check_ajax_referer('bst_tour_bookings_nonce', 'nonce'); // Verify the nonce

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // Prepare data array from POST
    $data = array(
        // System IDs
        'booking_entry_id' => intval($_POST['booking_entry_id'] ?? 0),
        'finalization_entry_id' => intval($_POST['finalization_entry_id'] ?? 0),
        'additional_payment_entry_id' => intval($_POST['additional_payment_entry_id'] ?? 0),
        'customer_id' => intval($_POST['customer_id'] ?? 0),
        
        // Guest 1 Information
        'guest1_first_name' => sanitize_text_field($_POST['guest1_first_name'] ?? ''),
        'guest1_last_name' => sanitize_text_field($_POST['guest1_last_name'] ?? ''),
        'guest1_nickname' => sanitize_text_field($_POST['guest1_nickname'] ?? ''),
        'guest1_phone' => sanitize_text_field($_POST['guest1_phone'] ?? ''),
        'guest1_email' => sanitize_email($_POST['guest1_email'] ?? ''),
        'guest1_address_line1' => sanitize_text_field($_POST['guest1_address_line1'] ?? ''),
        'guest1_address_line2' => sanitize_text_field($_POST['guest1_address_line2'] ?? ''),
        'guest1_city' => sanitize_text_field($_POST['guest1_city'] ?? ''),
        'guest1_state_province' => sanitize_text_field($_POST['guest1_state_province'] ?? ''),
        'guest1_postal_code' => sanitize_text_field($_POST['guest1_postal_code'] ?? ''),
        'guest1_country' => sanitize_text_field($_POST['guest1_country'] ?? ''),
        'guest1_shirt_size' => sanitize_text_field($_POST['guest1_shirt_size'] ?? ''),
        'guest1_driving_status' => sanitize_text_field($_POST['guest1_driving_status'] ?? ''),
        'guest1_travel_details' => sanitize_textarea_field($_POST['guest1_travel_details'] ?? ''),
        'guest1_dietary_restrictions' => sanitize_textarea_field($_POST['guest1_dietary_restrictions'] ?? ''),
        'guest1_emergency_contact' => sanitize_textarea_field($_POST['guest1_emergency_contact'] ?? ''),
        
        // Guest 2 Information
        'guest2_first_name' => sanitize_text_field($_POST['guest2_first_name'] ?? ''),
        'guest2_last_name' => sanitize_text_field($_POST['guest2_last_name'] ?? ''),
        'guest2_nickname' => sanitize_text_field($_POST['guest2_nickname'] ?? ''),
        'guest2_phone' => sanitize_text_field($_POST['guest2_phone'] ?? ''),
        'guest2_email' => sanitize_email($_POST['guest2_email'] ?? ''),
        'guest2_address_line1' => sanitize_text_field($_POST['guest2_address_line1'] ?? ''),
        'guest2_address_line2' => sanitize_text_field($_POST['guest2_address_line2'] ?? ''),
        'guest2_city' => sanitize_text_field($_POST['guest2_city'] ?? ''),
        'guest2_state_province' => sanitize_text_field($_POST['guest2_state_province'] ?? ''),
        'guest2_postal_code' => sanitize_text_field($_POST['guest2_postal_code'] ?? ''),
        'guest2_country' => sanitize_text_field($_POST['guest2_country'] ?? ''),
        'guest2_shirt_size' => sanitize_text_field($_POST['guest2_shirt_size'] ?? ''),
        'guest2_driving_status' => sanitize_text_field($_POST['guest2_driving_status'] ?? ''),
        'guest2_travel_details' => sanitize_textarea_field($_POST['guest2_travel_details'] ?? ''),
        'guest2_dietary_restrictions' => sanitize_textarea_field($_POST['guest2_dietary_restrictions'] ?? ''),
        'guest2_emergency_contact' => sanitize_textarea_field($_POST['guest2_emergency_contact'] ?? ''),
        
        // Other fields
        'participant_sex' => sanitize_text_field($_POST['participant_sex'] ?? ''),
        'sharing_preference' => sanitize_text_field($_POST['sharing_preference'] ?? ''),
        'bed_preference' => sanitize_text_field($_POST['bed_preference'] ?? ''),
        'hotel_nights_before' => intval($_POST['hotel_nights_before'] ?? 0),
        'hotel_nights_after' => intval($_POST['hotel_nights_after'] ?? 0),
        'how_heard' => sanitize_text_field($_POST['how_heard'] ?? ''),
        'how_heard_other' => sanitize_text_field($_POST['how_heard_other'] ?? ''),
        'motor_club' => sanitize_text_field($_POST['motor_club'] ?? ''),
        'source' => sanitize_text_field($_POST['source'] ?? ''),
        'referrer' => sanitize_text_field($_POST['referrer'] ?? ''),
        'additional_charge' => floatval($_POST['additional_charge'] ?? 0),
        'booking_status' => sanitize_text_field($_POST['booking_status'] ?? 'Pending'),
        'booking_method' => sanitize_text_field($_POST['booking_method'] ?? 'Web'),
        'booking_commission_percent' => floatval($_POST['booking_commission_percent'] ?? 0),
        'booking_commission_reason' => sanitize_text_field($_POST['booking_commission_reason'] ?? ''),
        'deposit_commission_invoice' => sanitize_text_field($_POST['deposit_commission_invoice'] ?? ''),
        'balance_commission_invoice' => sanitize_text_field($_POST['balance_commission_invoice'] ?? ''),
        'additional_payment_commission_invoice' => sanitize_text_field($_POST['additional_payment_commission_invoice'] ?? ''),
        'refund_payment_method' => sanitize_text_field($_POST['refund_payment_method'] ?? ''),
        'refund_payment_amount' => floatval($_POST['refund_payment_amount'] ?? 0),
        'refund_payment_date' => sanitize_text_field($_POST['refund_payment_date'] ?? ''),
        'refund_commission_invoice' => sanitize_text_field($_POST['refund_commission_invoice'] ?? ''),
        'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
    );

    if ($id > 0) {
        // Update existing booking using centralized function
        $result = bst_update_tour_booking($id, $data, 'ajax_update');
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Tour booking updated successfully.', 'id' => $id));
        } else {
            wp_send_json_error(array('message' => 'Update failed: ' . $result['error']));
        }
    } else {
        // Create new booking using centralized function
        $result = bst_create_tour_booking($data, 'ajax_create');
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Tour booking created successfully.', 'id' => $result['booking_id']));
        } else {
            wp_send_json_error(array('message' => 'Create failed: ' . $result['error']));
        }
    }
}

function bst_delete_tour_booking() {
    check_ajax_referer('bst_tour_bookings_nonce', 'nonce'); // Verify the nonce

    $id = intval($_POST['id']);

    // Use centralized delete function
    $result = bst_delete_tour_booking_by_id($id, 'ajax_delete');
    
    if ($result['success']) {
        wp_send_json_success(array('message' => 'Tour booking deleted successfully.'));
    } else {
        wp_send_json_error(array('message' => 'Delete failed: ' . $result['error']));
    }
}

/**
 * AJAX handler for updating individual tiles in a booking
 */
function bst_update_tile() {
    check_ajax_referer('bst_tour_bookings_nonce', 'nonce');
    
    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    $booking_id = intval($_POST['booking_id'] ?? 0);
    $tile_type = sanitize_text_field($_POST['tile_type'] ?? '');
    

    
    if (!$booking_id || !$tile_type) {
        wp_send_json_error(array('message' => 'Missing booking ID or tile type.'));
        return;
    }

    // Collect and sanitize data based on tile type
    $data = array();
    
    switch ($tile_type) {
        case 'guest1':
            $data = array(
                'guest1_first_name' => sanitize_text_field($_POST['guest1_first_name'] ?? ''),
                'guest1_last_name' => sanitize_text_field($_POST['guest1_last_name'] ?? ''),
                'guest1_nickname' => sanitize_text_field($_POST['guest1_nickname'] ?? ''),
                'guest1_email' => sanitize_email($_POST['guest1_email'] ?? ''),
                'guest1_phone' => sanitize_text_field($_POST['guest1_phone'] ?? ''),
                'guest1_address_line1' => sanitize_text_field($_POST['guest1_address_line1'] ?? ''),
                'guest1_address_line2' => sanitize_text_field($_POST['guest1_address_line2'] ?? ''),
                'guest1_city' => sanitize_text_field($_POST['guest1_city'] ?? ''),
                'guest1_state_province' => sanitize_text_field($_POST['guest1_state_province'] ?? ''),
                'guest1_postal_code' => sanitize_text_field($_POST['guest1_postal_code'] ?? ''),
                'guest1_country' => sanitize_text_field($_POST['guest1_country'] ?? ''),
                'guest1_shirt_size' => sanitize_text_field($_POST['guest1_shirt_size'] ?? ''),
                'guest1_driving_status' => sanitize_text_field($_POST['guest1_driving_status'] ?? ''),
                'guest1_dietary_restrictions' => sanitize_textarea_field($_POST['guest1_dietary_restrictions'] ?? ''),
                'guest1_medical_insurance' => sanitize_textarea_field($_POST['guest1_medical_insurance'] ?? ''),
                'guest1_emergency_contact_name' => sanitize_text_field($_POST['guest1_emergency_contact_name'] ?? ''),
                'guest1_emergency_contact_phone' => sanitize_text_field($_POST['guest1_emergency_contact_phone'] ?? ''),
                'guest1_emergency_contact_email' => sanitize_email($_POST['guest1_emergency_contact_email'] ?? ''),
                'guest1_travel_details' => sanitize_textarea_field($_POST['guest1_travel_details'] ?? '')
            );
            break;

        case 'guest2':
            $data = array(
                'guest2_first_name' => sanitize_text_field($_POST['guest2_first_name'] ?? ''),
                'guest2_last_name' => sanitize_text_field($_POST['guest2_last_name'] ?? ''),
                'guest2_nickname' => sanitize_text_field($_POST['guest2_nickname'] ?? ''),
                'guest2_email' => sanitize_email($_POST['guest2_email'] ?? ''),
                'guest2_phone' => sanitize_text_field($_POST['guest2_phone'] ?? ''),
                'guest2_address_line1' => sanitize_text_field($_POST['guest2_address_line1'] ?? ''),
                'guest2_address_line2' => sanitize_text_field($_POST['guest2_address_line2'] ?? ''),
                'guest2_city' => sanitize_text_field($_POST['guest2_city'] ?? ''),
                'guest2_state_province' => sanitize_text_field($_POST['guest2_state_province'] ?? ''),
                'guest2_postal_code' => sanitize_text_field($_POST['guest2_postal_code'] ?? ''),
                'guest2_country' => sanitize_text_field($_POST['guest2_country'] ?? ''),
                'guest2_shirt_size' => sanitize_text_field($_POST['guest2_shirt_size'] ?? ''),
                'guest2_driving_status' => sanitize_text_field($_POST['guest2_driving_status'] ?? ''),
                'guest2_dietary_restrictions' => sanitize_textarea_field($_POST['guest2_dietary_restrictions'] ?? ''),
                'guest2_medical_insurance' => sanitize_textarea_field($_POST['guest2_medical_insurance'] ?? ''),
                'guest2_emergency_contact_name' => sanitize_text_field($_POST['guest2_emergency_contact_name'] ?? ''),
                'guest2_emergency_contact_phone' => sanitize_text_field($_POST['guest2_emergency_contact_phone'] ?? ''),
                'guest2_emergency_contact_email' => sanitize_email($_POST['guest2_emergency_contact_email'] ?? ''),
                'guest2_travel_details' => sanitize_textarea_field($_POST['guest2_travel_details'] ?? '')
            );
            break;

        case 'notes':
            $data = array(
                'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
            );
            break;

        case 'customer':
            $data = array(
                'customer_id' => intval($_POST['customer_id'] ?? 0)
            );
            break;

        case 'marketing':
            $data = array(
                'how_heard' => sanitize_text_field($_POST['how_heard'] ?? ''),
                'how_heard_other' => sanitize_text_field($_POST['how_heard_other'] ?? ''),
                'source' => sanitize_text_field($_POST['source'] ?? ''),
                'referrer' => sanitize_text_field($_POST['referrer'] ?? ''),
                'motor_club' => sanitize_text_field($_POST['motor_club'] ?? '')
            );
            break;

        case 'administrative':
            $data = array(
                'booking_status' => sanitize_text_field($_POST['booking_status'] ?? ''),
                'booking_method' => sanitize_text_field($_POST['booking_method'] ?? ''),
                'booking_commission_percent' => floatval($_POST['booking_commission_percent'] ?? 0),
                'booking_commission_reason' => sanitize_text_field($_POST['booking_commission_reason'] ?? '')
            );
            break;

        case 'gravity_forms':
            $data = array(
                'booking_entry_id' => sanitize_text_field($_POST['booking_form_id'] ?? ''),
                'finalization_entry_id' => sanitize_text_field($_POST['finalization_form_id'] ?? ''),
                'additional_payment_entry_id' => sanitize_text_field($_POST['add_pmt_form_id'] ?? '')
            );
            break;

        case 'system':
            $data = array(
                'data_source' => sanitize_text_field($_POST['data_source'] ?? '')
            );
            break;

        case 'financials':
            $data = array(
                // Currency and pricing matrix fields
                'tour_currency' => sanitize_text_field($_POST['tour_currency'] ?? 'EUR'),
                'tour_price' => floatval($_POST['tour_price'] ?? 0),
                'coupon_amount' => floatval($_POST['coupon_amount'] ?? 0),
                'coupon_code' => sanitize_text_field($_POST['coupon_code'] ?? ''),
                'additional_charge' => floatval($_POST['additional_charge'] ?? 0),
                
                // Payment matrix fields
                'deposit_payment_method' => sanitize_text_field($_POST['deposit_payment_method'] ?? ''),
                'deposit_payment_amount' => floatval($_POST['deposit_payment_amount'] ?? 0),
                'deposit_payment_date' => sanitize_text_field($_POST['deposit_payment_date'] ?? ''),
                'deposit_commission_invoice' => sanitize_text_field($_POST['deposit_commission_invoice'] ?? ''),
                'deposit_payment_discount' => floatval($_POST['deposit_payment_discount'] ?? 0),
                'deposit_payment_status' => function_exists( 'bst_sanitize_payment_status' ) ? bst_sanitize_payment_status( wp_unslash( $_POST['deposit_payment_status'] ?? '' ) ) : null,
                
                'balance_payment_method' => sanitize_text_field($_POST['balance_payment_method'] ?? ''),
                'balance_payment_amount' => floatval($_POST['balance_payment_amount'] ?? 0),
                'balance_payment_date' => sanitize_text_field($_POST['balance_payment_date'] ?? ''),
                'balance_commission_invoice' => sanitize_text_field($_POST['balance_commission_invoice'] ?? ''),
                'balance_payment_discount' => floatval($_POST['balance_payment_discount'] ?? 0),
                'balance_payment_status' => function_exists( 'bst_sanitize_payment_status' ) ? bst_sanitize_payment_status( wp_unslash( $_POST['balance_payment_status'] ?? '' ) ) : null,
                
                'additional_payment_method' => sanitize_text_field($_POST['additional_payment_method'] ?? ''),
                'additional_payment_amount' => floatval($_POST['additional_payment_amount'] ?? 0),
                'additional_payment_date' => sanitize_text_field($_POST['additional_payment_date'] ?? ''),
                'additional_payment_commission_invoice' => sanitize_text_field($_POST['additional_payment_commission_invoice'] ?? ''),
                'additional_payment_discount' => floatval($_POST['additional_payment_discount'] ?? 0),
                'additional_payment_status' => function_exists( 'bst_sanitize_payment_status' ) ? bst_sanitize_payment_status( wp_unslash( $_POST['additional_payment_status'] ?? '' ) ) : null,
                
                'refund_payment_method' => sanitize_text_field($_POST['refund_payment_method'] ?? ''),
                'refund_payment_amount' => floatval($_POST['refund_payment_amount'] ?? 0),
                'refund_payment_date' => sanitize_text_field($_POST['refund_payment_date'] ?? ''),
                'refund_commission_invoice' => sanitize_text_field($_POST['refund_commission_invoice'] ?? ''),
                'refund_payment_status' => function_exists( 'bst_sanitize_payment_status' ) ? bst_sanitize_payment_status( wp_unslash( $_POST['refund_payment_status'] ?? '' ) ) : null,
            );
            
            // Calculate derived values
            $tour_price = $data['tour_price'];
            $coupon_amount = $data['coupon_amount'];
            $additional_charge = $data['additional_charge'];
            
            $net_tour_price = $tour_price - $coupon_amount;
            $total_due = $net_tour_price + $additional_charge;
            
            // Calculate total paid (deposits + balance + additional - refunds)
            $total_paid = $data['deposit_payment_amount'] + $data['balance_payment_amount'] + $data['additional_payment_amount'] - $data['refund_payment_amount'];
            
            // Calculate payment discount amount
            $payment_discount_amount = $data['deposit_payment_discount'] + $data['balance_payment_discount'] + $data['additional_payment_discount'];
            $data['payment_discount_amount'] = $payment_discount_amount;
            
            $balance_due = $total_due - $total_paid - $payment_discount_amount;
            
            // Add calculated fields to data (only fields that exist in database)
            $data['net_tour_price'] = $net_tour_price;
            // total_due is not a database column, only calculated for display
            $data['total_paid'] = $total_paid;
            $data['balance_due'] = $balance_due;
            
            // Auto-update status based on payment information and method
            // Get current booking to check current status
            global $wpdb;
            $booking_table = $wpdb->prefix . 'bst_tour_booking';
            $current_booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $booking_table WHERE id = %d", $booking_id));
            
            if ($current_booking) {
                $current_status = $current_booking->booking_status;

                // Resolve line statuses from POST with DB fallback (same row as financials save).
                $dep_status_for_rules = $data['deposit_payment_status'] ?? $current_booking->deposit_payment_status ?? null;
                $bal_status_for_rules = $data['balance_payment_status'] ?? $current_booking->balance_payment_status ?? null;
                $dep_amt_for_rules      = floatval( $data['deposit_payment_amount'] ?? 0 );
                $bal_amt_for_rules      = floatval( $data['balance_payment_amount'] ?? 0 );

                $dep_line_received = function_exists( 'bst_payment_line_received_for_display' )
                    ? bst_payment_line_received_for_display( $dep_status_for_rules, $dep_amt_for_rules )
                    : false;
                $bal_line_received = function_exists( 'bst_payment_line_received_for_display' )
                    ? bst_payment_line_received_for_display( $bal_status_for_rules, $bal_amt_for_rules )
                    : false;

                $dep_st_trim = ( null !== $dep_status_for_rules && '' !== $dep_status_for_rules ) ? trim( (string) $dep_status_for_rules ) : '';
                $bal_st_trim = ( null !== $bal_status_for_rules && '' !== $bal_status_for_rules ) ? trim( (string) $bal_status_for_rules ) : '';
                // Legacy date+amount path only when the line is not already marked Paid/Processing (status is source of truth).
                $dep_already_paid_label = in_array( $dep_st_trim, array( 'Paid', 'Processing' ), true );
                $bal_already_paid_label = in_array( $bal_st_trim, array( 'Paid', 'Processing' ), true );

                // Rule 1: Pending → Booked when deposit bank wire is received (per line status), or legacy date+amount if line not already Paid/Processing.
                if ( $current_status === 'Pending'
                    && $data['deposit_payment_method'] === 'Bank Wire'
                    && $dep_amt_for_rules > 0 ) {
                    if ( $dep_line_received ) {
                        $data['booking_status'] = 'Booked';
                    } elseif ( ! empty( $data['deposit_payment_date'] ) && ! $dep_already_paid_label ) {
                        $data['booking_status'] = 'Booked';
                    }
                }

                // Rule 2: Booked → Finalized when balance bank wire is received (per line status), or legacy date+amount if line not already Paid/Processing.
                if ( $current_status === 'Booked'
                    && $data['balance_payment_method'] === 'Bank Wire'
                    && $bal_amt_for_rules > 0 ) {
                    if ( $bal_line_received ) {
                        $data['booking_status'] = 'Finalized';
                    } elseif ( ! empty( $data['balance_payment_date'] ) && ! $bal_already_paid_label ) {
                        $data['booking_status'] = 'Finalized';
                    }
                }

                if ( function_exists( 'bst_merge_booking_status_with_payment_line_statuses' ) ) {
                    $base_status = isset( $data['booking_status'] ) ? $data['booking_status'] : $current_status;
                    $data['booking_status'] = bst_merge_booking_status_with_payment_line_statuses(
                        $base_status,
                        $data['deposit_payment_status'] ?? $current_booking->deposit_payment_status ?? null,
                        $data['balance_payment_status'] ?? $current_booking->balance_payment_status ?? null,
                        $data['additional_payment_status'] ?? $current_booking->additional_payment_status ?? null,
                        $data['refund_payment_status'] ?? $current_booking->refund_payment_status ?? null
                    );
                }
            }
            break;

        case 'tour_package':
            $data = array(
                'tour_id' => intval($_POST['tour_id'] ?? 0),
                'tour_date_id' => intval($_POST['tour_date_id'] ?? 0),
                'tour_package_id' => intval($_POST['tour_package_id'] ?? 0),
                'tour_package_text' => sanitize_text_field($_POST['tour_package_text'] ?? ''),
                'tour_text' => sanitize_text_field($_POST['tour_text'] ?? ''),
                'tour_date_text' => sanitize_text_field($_POST['tour_date_text'] ?? ''),
                'vehicle1' => sanitize_text_field($_POST['vehicle1'] ?? ''),
                'vehicle2' => sanitize_text_field($_POST['vehicle2'] ?? ''),
                'tour_extension_added' => ($_POST['tour_extension_added'] ?? '') === '1' ? 1 : 0,
                'tour_extension_text' => sanitize_text_field($_POST['tour_extension_text'] ?? ''),
                'tour_extension_date_text' => sanitize_text_field($_POST['tour_extension_date_text'] ?? ''),
                'participant_sex' => sanitize_text_field($_POST['participant_sex'] ?? ''),
                'sharing_preference' => sanitize_text_field($_POST['sharing_preference'] ?? ''),
                'bed_preference' => sanitize_text_field($_POST['bed_preference'] ?? ''),
                'hotel_nights_before' => intval($_POST['hotel_nights_before'] ?? 0),
                'hotel_nights_after' => intval($_POST['hotel_nights_after'] ?? 0),
                'package_people' => intval($_POST['package_people'] ?? 0),
                'package_rooms' => floatval($_POST['package_rooms'] ?? 0),
                'package_vehicles' => intval($_POST['package_vehicles'] ?? 0),
                'vehicle_choices' => intval($_POST['vehicle_choices'] ?? 0)
            );
            break;

        case 'invoicing':
            $data = array(
                'booking_invoice_number' => sanitize_text_field($_POST['booking_invoice_number'] ?? ''),
                'booking_invoice_date' => sanitize_text_field($_POST['booking_invoice_date'] ?? ''),
                'booking_eu_percent' => floatval($_POST['booking_eu_percent'] ?? 0),
                'booking_vat_rate' => floatval($_POST['booking_vat_rate'] ?? 0),
                'booking_tour_package_amount' => floatval($_POST['booking_tour_package_amount'] ?? 0),
                'booking_vehicle_1_use_amount' => floatval($_POST['booking_vehicle_1_use_amount'] ?? 0),
                'booking_vehicle_2_use_amount' => floatval($_POST['booking_vehicle_2_use_amount'] ?? 0)
            );
            break;

        default:
            wp_send_json_error(array('message' => 'Unsupported tile type: ' . $tile_type));
            return;
    }

    // Convert empty strings to null values for consistent database storage
    $data = bst_convert_empty_to_null($data);

    // Update the booking using the centralized function
    $result = bst_update_tour_booking($booking_id, $data, 'tile_update');
    
    if ($result['success']) {
        // Get the updated booking data for rendering
        global $wpdb;
        $booking_table = $wpdb->prefix . 'bst_tour_booking';
        $updated_booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $booking_table WHERE id = %d", $booking_id));
        
        if (!$updated_booking) {
            wp_send_json_error(array('message' => 'Failed to retrieve updated booking data.'));
            return;
        }
        
        // Generate the updated HTML content for this tile
        $updated_html = bst_render_tile_content($tile_type, $updated_booking);
        

        
        // Prepare response data
        $response_data = array(
            'message' => ucfirst($tile_type) . ' information updated successfully.',
            'tile_type' => $tile_type,
            'form_data' => $data,
            'html' => $updated_html,
            'updated_booking' => $updated_booking // Include actual database values for client-side booking object
        );
        
        // If a status change occurred, include it in the response
        if (isset($data['booking_status'])) {
            $response_data['status_changed'] = true;
            $response_data['new_status'] = $data['booking_status'];
        }
        
        // If tour_package updated and package_people changed, include guest2 visibility info
        if ($tile_type === 'tour_package' && isset($data['package_people'])) {
            $response_data['package_people'] = intval($data['package_people']);
            $response_data['show_guest2'] = intval($data['package_people']) === 2;
            
            // If guest2 should be shown, generate its HTML content
            if ($response_data['show_guest2']) {
                $response_data['guest2_html'] = bst_render_tile_content('guest2', $updated_booking);
            }
        }
        
        wp_send_json_success($response_data);
    } else {
        wp_send_json_error(array('message' => 'Update failed: ' . $result['error']));
    }
}

/**
 * AJAX handler for updating tour price and recalculating financials
 */
function bst_update_tour_price() {
    try {
        // Temporarily bypass nonce check to see if that's the issue
        // check_ajax_referer('bst_tour_bookings_nonce', 'nonce');
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }

    $booking_id = intval($_POST['booking_id'] ?? 0);
    $tour_price = floatval($_POST['tour_price'] ?? 0);
    

    
    if (!$booking_id || $tour_price <= 0) {

        wp_send_json_error(array('message' => 'Missing booking ID or invalid tour price.'));
        return;
    }

    // Get current booking data to calculate new totals

    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $booking_table WHERE id = %d", $booking_id));
    if (!$booking) {

        wp_send_json_error(array('message' => 'Booking not found.'));
        return;
    }
    


    // Calculate new financial totals
    $coupon_amount = floatval($booking->coupon_amount ?? 0);
    $additional_charge = floatval($booking->additional_charge ?? 0);
    $net_tour_price = $tour_price - $coupon_amount;
    $total_due = $net_tour_price + $additional_charge;
    
    // Calculate total paid (don't change payments, just recalculate balance)
    $deposit_amount = floatval($booking->deposit_payment_amount ?? 0);
    $balance_amount = floatval($booking->balance_payment_amount ?? 0);
    $additional_amount = floatval($booking->additional_payment_amount ?? 0);
    $refund_amount = floatval($booking->refund_payment_amount ?? 0);
    $total_paid = $deposit_amount + $balance_amount + $additional_amount - $refund_amount;
    
    // Include payment discounts in balance_due calculation
    $payment_discount_amount = floatval($booking->payment_discount_amount ?? 0);
    $balance_due = $total_due - $total_paid - $payment_discount_amount;
    
    // Prepare data for update
    $data = array(
        'tour_price' => number_format($tour_price, 2, '.', ''),
        'net_tour_price' => number_format($net_tour_price, 2, '.', ''),
        'balance_due' => number_format($balance_due, 2, '.', '')
    );
    


    // Update the booking

    $result = bst_update_tour_booking($booking_id, $data, 'tour_price_update');
    

    
    if ($result['success']) {

        
        // Get the updated booking data for rendering
        $updated_booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $booking_table WHERE id = %d", $booking_id));
        
        if (!$updated_booking) {
            wp_send_json_error(array('message' => 'Failed to retrieve updated booking data.'));
            return;
        }
        
        // Generate the updated HTML content for both affected tiles
        $tour_package_html = bst_render_tile_content('tour_package', $updated_booking);
        $financials_html = bst_render_tile_content('financials', $updated_booking);
        
        wp_send_json_success(array(
            'message' => 'Tour price updated successfully.',
            'new_price' => $tour_price,
            'net_tour_price' => $net_tour_price,
            'total_due' => $total_due,
            'balance_due' => $balance_due,
            'tour_package_html' => $tour_package_html,
            'financials_html' => $financials_html
        ));
    } else {

        wp_send_json_error(array('message' => 'Tour price update failed: ' . $result['error']));
    }
}

/**
 * AJAX handler for customer lookup
 */
function bst_lookup_customer() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    
    // Check user permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }
    
    $customer_id = intval($_POST['customer_id'] ?? 0);
    
    if (!$customer_id) {
        wp_send_json_error(array('message' => 'Invalid customer ID.'));
        return;
    }
    
    global $wpdb;
    
    // Lookup customer in the database
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id, first_name, last_name, email FROM {$wpdb->prefix}bst_customers WHERE id = %d",
        $customer_id
    ));
    
    if (!$customer) {
        // Customer not found, but return success with empty data
        wp_send_json_success(array(
            'id' => null,
            'first_name' => '',
            'last_name' => '',
            'email' => ''
        ));
        return;
    }
    
    // Customer found, return data
    wp_send_json_success(array(
        'id' => $customer->id,
        'first_name' => $customer->first_name,
        'last_name' => $customer->last_name,
        'email' => $customer->email
    ));
}

/**
 * AJAX handler for creating a new booking
 */
function bst_create_booking() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['create_booking_nonce'], 'bst_create_booking')) {
        wp_send_json_error('Security check failed');
        return;
    }

    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    // Validate required fields
    $required_fields = ['guest1_first_name', 'guest1_last_name', 'guest1_email', 'tour_id', 'tour_date_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error("Missing required field: $field");
            return;
        }
    }

    // Sanitize and prepare data
    $booking_data = array();
    
    // Essential guest information
    $booking_data['guest1_first_name'] = sanitize_text_field($_POST['guest1_first_name']);
    $booking_data['guest1_last_name'] = sanitize_text_field($_POST['guest1_last_name']);
    $booking_data['guest1_email'] = sanitize_email($_POST['guest1_email']);
    $booking_data['guest1_phone'] = sanitize_text_field($_POST['guest1_phone'] ?? '');
    
    // Tour information
    $booking_data['tour_id'] = intval($_POST['tour_id']);
    $booking_data['tour_date_id'] = intval($_POST['tour_date_id']);
    $booking_data['tour_package_id'] = intval($_POST['tour_package_id'] ?? 0);
    $booking_data['tour_currency'] = sanitize_text_field($_POST['tour_currency'] ?? 'EUR');
    $booking_data['tour_price'] = floatval($_POST['tour_price'] ?? 0);
    
    // Financial information
    $booking_data['deposit_payment_amount'] = floatval($_POST['deposit_payment_amount'] ?? 0);
    $booking_data['deposit_payment_method'] = sanitize_text_field($_POST['deposit_payment_method'] ?? '');
    $booking_data['deposit_payment_date'] = sanitize_text_field($_POST['deposit_payment_date'] ?? '');
    
    // Derived financial fields (calculated in JavaScript)
    $booking_data['net_tour_price'] = floatval($_POST['net_tour_price'] ?? 0);
    $booking_data['total_paid'] = floatval($_POST['total_paid'] ?? 0);
    $booking_data['additional_charge'] = floatval($_POST['additional_charge'] ?? 0);
    $booking_data['balance_due'] = floatval($_POST['balance_due'] ?? 0);
    
    // Guest 2 information (if provided)
    $booking_data['guest2_first_name'] = sanitize_text_field($_POST['guest2_first_name'] ?? '');
    $booking_data['guest2_last_name'] = sanitize_text_field($_POST['guest2_last_name'] ?? '');
    
    // Package-related fields
    $booking_data['package_people'] = intval($_POST['package_people'] ?? 0);
    $booking_data['package_rooms'] = floatval($_POST['package_rooms'] ?? 0);
    $booking_data['package_vehicles'] = intval($_POST['package_vehicles'] ?? 0);
    $booking_data['vehicle_choices'] = sanitize_text_field($_POST['vehicle_choices'] ?? '');
    $booking_data['tour_package_text'] = sanitize_text_field($_POST['tour_package_text'] ?? '');
    
    // Notes
    $booking_data['notes'] = sanitize_textarea_field($_POST['notes'] ?? '');
    
    // Administrative defaults based on booking type
    $booking_type = sanitize_text_field($_POST['booking_type'] ?? 'paper');
    $booking_data['booking_method'] = sanitize_text_field($_POST['booking_method'] ?? '');
    $booking_data['booking_status'] = sanitize_text_field($_POST['booking_status'] ?? '');
    $booking_data['booking_commission_percent'] = floatval($_POST['booking_commission_percent'] ?? 0);
    $booking_data['booking_commission_reason'] = sanitize_text_field($_POST['booking_commission_reason'] ?? '');
    
    // Set data source based on booking status
    $data_source = sanitize_text_field($_POST['data_source'] ?? '');
    if (empty($data_source)) {
        // Auto-set data source based on booking status
        if ($booking_data['booking_status'] === 'waitlist') {
            $data_source = 'Waiting List';
        } elseif ($booking_data['booking_status'] === 'reservation') {
            $data_source = 'Reservation';
        } else {
            $data_source = 'Bill Booking';
        }
    }
    $booking_data['data_source'] = $data_source;
    
    // Get tour and date text for display (only if not already provided)
    if (empty($booking_data['tour_text'])) {
        $tour_post = get_post($booking_data['tour_id']);
        $booking_data['tour_text'] = $tour_post ? $tour_post->post_title : '';
    }
    
    if (empty($booking_data['tour_date_text'])) {
        $tour_date_post = get_post($booking_data['tour_date_id']);
        if ($tour_date_post) {
            // Use the standardized tour date title directly - it's already in format "Tour Name (Date Range)"
            // Extract just the date range part from within the parentheses
            if (preg_match('/\((.*)\)$/', $tour_date_post->post_title, $matches)) {
                $booking_data['tour_date_text'] = $matches[1];
            } else {
                // Fallback to full title if format doesn't match expected pattern
                $booking_data['tour_date_text'] = $tour_date_post->post_title;
            }
        }
    }
    
    // Get package text if package selected and not already provided
    if ($booking_data['tour_package_id'] > 0 && empty($booking_data['tour_package_text'])) {
        $packages = get_field('packages', $booking_data['tour_id']);
        if ($packages && is_array($packages)) {
            foreach ($packages as $index => $pkg) {
                $pkg_id = isset($pkg['package_id']) ? $pkg['package_id'] : ($index + 1);
                if ($pkg_id == $booking_data['tour_package_id']) {
                    $booking_data['tour_package_text'] = $pkg['package_name'] ?? '';
                    break;
                }
            }
        }
    }
    
    // Calculate customer ID and commission based on email address (unless already provided)
    $customer_id_provided = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    
    if ($customer_id_provided) {
        // Customer already selected via email populate - use provided commission data
        $booking_data['customer_id'] = $customer_id_provided;
        
        // Use commission data from customer populate if available
        if (!empty($_POST['customer_commission_percent'])) {
            $booking_data['booking_commission_percent'] = floatval($_POST['customer_commission_percent']) / 100; // Convert percentage to decimal
        }
        if (!empty($_POST['customer_commission_reason'])) {
            $booking_data['booking_commission_reason'] = sanitize_text_field($_POST['customer_commission_reason']);
        }
        
        error_log('BST Plugin: Using existing customer ' . $customer_id_provided . ' for booking creation - skipping commission calculation');
        
    } else {
        // No customer provided - use original commission calculation logic
        list($booking_commission_percent, $booking_commission_reason, $customer_id) = bst_calculate_commission_percent(
            $booking_data['guest1_first_name'],
            $booking_data['guest1_last_name'],
            $booking_data['guest1_email'],
            $booking_data['guest1_phone'],
            $booking_data['guest2_first_name'],
            $booking_data['guest2_last_name'],
            null, // how_heard not available in manual form
            null, // source not available in manual form
            'Manual Admin Entry' // data_source for manual entries
        );
        
        // Only override commission if not already set
        if (empty($booking_data['booking_commission_percent'])) {
            $booking_data['booking_commission_percent'] = $booking_commission_percent;
        }
        if (empty($booking_data['booking_commission_reason'])) {
            $booking_data['booking_commission_reason'] = $booking_commission_reason;
        }
        if ($customer_id) {
            $booking_data['customer_id'] = $customer_id;
        }
        
        error_log('BST Plugin: Calculated commission for new booking: ' . ($booking_commission_percent * 100) . '% (' . $booking_commission_reason . ') - Customer ID: ' . ($customer_id ?? 'none'));
    }
    
    // Set financial defaults for reservation and waitlist bookings
    if (in_array($booking_data['booking_status'], ['reservation', 'waitlist'])) {
        // Force payment amounts to 0 for reservations and waitlists
        $booking_data['deposit_payment_amount'] = 0;
        $booking_data['balance_payment_amount'] = 0;
        $booking_data['additional_payment_amount'] = 0;
        $booking_data['refund_payment_amount'] = 0;
        
        // Calculate total paid and balance due (Total Paid = 0, Balance Due = Tour Price)
        $total_paid = 0;
        $balance_due = floatval($booking_data['tour_price'] ?? 0);
        
        $booking_data['total_paid'] = $total_paid;
        $booking_data['balance_due'] = $balance_due;
    }
    
    // Convert empty strings to null for consistent database storage
    $booking_data = bst_convert_empty_to_null($booking_data);
    
    // For paper bookings with deposit payment date, use that as the create date
    if ($booking_type === 'paper' && !empty($booking_data['deposit_payment_date'])) {
        // Override the standard audit field creation to use deposit date
        $user_identifier = bst_get_current_user_identifier();
        $deposit_date = $booking_data['deposit_payment_date'];
        
        // Convert date format from Y-m-d to Y-m-d H:i:s (use current time for time part)
        $create_date = $deposit_date . ' ' . date('H:i:s');
        
        $booking_data['created_by'] = $user_identifier;
        $booking_data['created_date'] = $create_date;
        $booking_data['updated_by'] = $user_identifier;
        $booking_data['updated_date'] = current_time('mysql');
        
        // Create the booking without adding standard audit fields
        $result = bst_create_tour_booking_with_custom_date($booking_data, 'manual_creation');
    } else {
        // Create the booking using the standard function
        $result = bst_create_tour_booking($booking_data, 'manual_creation');
    }
    
    if ($result['success']) {
        wp_send_json_success(array(
            'booking_id' => $result['booking_id'],
            'message' => 'Booking created successfully'
        ));
    } else {
        wp_send_json_error('Failed to create booking: ' . $result['error']);
    }
}

/**
 * AJAX handler for deleting a booking
 */
function bst_delete_booking() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }

    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    // Validate booking ID
    $booking_id = intval($_POST['booking_id'] ?? 0);
    if (!$booking_id) {
        wp_send_json_error('Invalid booking ID');
        return;
    }

    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';

    // Check if booking exists
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT id, guest1_first_name, guest1_last_name, tour_date_id FROM $booking_table WHERE id = %d",
        $booking_id
    ));

    if (!$booking) {
        wp_send_json_error('Booking not found');
        return;
    }

    // Delete the booking
    $result = $wpdb->delete($booking_table, ['id' => $booking_id], ['%d']);

    if ($result === false) {
        wp_send_json_error('Database error: Failed to delete booking');
        return;
    }

    if ($result === 0) {
        wp_send_json_error('Booking not found or already deleted');
        return;
    }

    // Log the deletion (optional)
    error_log("BST: Booking #{$booking_id} ({$booking->guest1_first_name} {$booking->guest1_last_name}) deleted by user " . wp_get_current_user()->user_login);

    // Trigger availability recalculation
    if (!empty($booking->tour_date_id)) {
        error_log("BST: Triggering availability recalculation for tour date ID: {$booking->tour_date_id}");
        do_action('bst_booking_deleted', $booking->tour_date_id, 'ajax_delete');
    } else {
        error_log("BST: Warning - No tour_date_id found for deleted booking #{$booking_id}");
    }

    wp_send_json_success(['message' => 'Booking deleted successfully']);
}

/**
 * AJAX handler for creating a waiting list booking from customer-facing form
 */
function bst_create_waiting_list_booking() {
    // Add debug logging
    error_log('BST: Waiting list AJAX called with data: ' . print_r($_POST, true));
    
    // Basic honeypot check to prevent spam (optional)
    if (!empty($_POST['website'])) {
        error_log('BST: Honeypot triggered in waiting list request');
        wp_send_json_error('Invalid request');
        return;
    }

    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'tour_id', 'tour_date_id', 'tour_package_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error("Missing required field: $field");
            return;
        }
    }

    // Sanitize and prepare data
    $booking_data = array();
    
    // Essential guest information
    $booking_data['guest1_first_name'] = sanitize_text_field($_POST['first_name']);
    $booking_data['guest1_last_name'] = sanitize_text_field($_POST['last_name']);
    $booking_data['guest1_email'] = sanitize_email($_POST['email']);
    $booking_data['guest1_phone'] = sanitize_text_field($_POST['phone']);
    
    // Tour information
    $booking_data['tour_id'] = intval($_POST['tour_id']);
    $booking_data['tour_date_id'] = intval($_POST['tour_date_id']);
    $booking_data['tour_package_id'] = intval($_POST['tour_package_id']);
    $booking_data['tour_currency'] = sanitize_text_field($_POST['tour_currency'] ?? 'EUR');
    $booking_data['tour_price'] = floatval($_POST['tour_price'] ?? 0);
    
    // Check if this email is already booked for this tour date
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    $existing_booking = $wpdb->get_row($wpdb->prepare(
        "SELECT id, booking_status FROM $booking_table 
         WHERE guest1_email = %s 
         AND tour_date_id = %d 
         ORDER BY id ASC 
         LIMIT 1",
        $booking_data['guest1_email'],
        $booking_data['tour_date_id']
    ));
    
    if ($existing_booking) {
        if ($existing_booking->booking_status === 'Waiting List') {
            // Calculate their current queue position
            $queue_position = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $booking_table 
                 WHERE tour_date_id = %d 
                 AND booking_status = 'Waiting List' 
                 AND id < %d",
                $booking_data['tour_date_id'],
                $existing_booking->id
            ));
            
            $position_message = ($queue_position === 0) 
                ? "You are first in line for this tour date!" 
                : "You are #" . ($queue_position + 1) . " in line for this tour date (" . $queue_position . " guest" . ($queue_position === 1 ? '' : 's') . " ahead of you in the queue).";
            
            wp_send_json_error("You are already on the waiting list for this tour date. " . $position_message);
            return;
        } else {
            // They have a regular booking
            wp_send_json_error("You already have a booking for this tour date with status: " . $existing_booking->booking_status);
            return;
        }
    }
    
    // Vehicle choices
    $booking_data['vehicle_choices'] = intval($_POST['vehicle_choices'] ?? 0);
    
    // Vehicle selections (actual vehicle IDs)
    if (!empty($_POST['vehicle1'])) {
        $booking_data['vehicle1'] = sanitize_text_field($_POST['vehicle1']);
    }
    if (!empty($_POST['vehicle2'])) {
        $booking_data['vehicle2'] = sanitize_text_field($_POST['vehicle2']);
    }
    
    // Extension information
    if (!empty($_POST['tour_extension_added']) && $_POST['tour_extension_added'] === '1') {
        $booking_data['tour_extension_added'] = '1';
    }
    if (!empty($_POST['tour_extension_text'])) {
        $booking_data['tour_extension_text'] = sanitize_text_field($_POST['tour_extension_text']);
    }
    if (!empty($_POST['tour_extension_date_text'])) {
        $booking_data['tour_extension_date_text'] = sanitize_text_field($_POST['tour_extension_date_text']);
    }
    
    // Package text (from JavaScript)
    $booking_data['tour_package_text'] = sanitize_text_field($_POST['tour_package_text'] ?? '');
    
    // Notes
    $booking_data['notes'] = sanitize_textarea_field($_POST['notes'] ?? '');
    
    // Set waiting list defaults
    $booking_data['booking_method'] = 'Web';
    $booking_data['booking_status'] = 'Waiting List';
    $booking_data['data_source'] = 'Waiting List';
    
    // Set financial defaults for waiting list
    $booking_data['net_tour_price'] = $booking_data['tour_price'];
    $booking_data['total_paid'] = 0;
    $booking_data['additional_charge'] = 0;
    $booking_data['balance_due'] = $booking_data['tour_price'];
    $booking_data['deposit_payment_amount'] = 0;
    
    // Get tour text
    $tour_post = get_post($booking_data['tour_id']);
    $booking_data['tour_text'] = $tour_post ? $tour_post->post_title : '';
    
    // Get tour date text
    $tour_date_post = get_post($booking_data['tour_date_id']);
    if ($tour_date_post) {
        // Use the standardized tour date title directly - it's already in format "Tour Name (Date Range)"
        // Extract just the date range part from within the parentheses
        if (preg_match('/\((.*)\)$/', $tour_date_post->post_title, $matches)) {
            $booking_data['tour_date_text'] = $matches[1];
        } else {
            // Fallback to full title if format doesn't match expected pattern
            $booking_data['tour_date_text'] = $tour_date_post->post_title;
        }
    }
    
    // Get package details from ACF for people/rooms/vehicles only
    if ($booking_data['tour_package_id'] > 0) {
        $packages = get_field('packages', $booking_data['tour_id']);
        if ($packages && is_array($packages)) {
            foreach ($packages as $index => $pkg) {
                $pkg_id = isset($pkg['package_id']) ? $pkg['package_id'] : ($index + 1);
                if ($pkg_id == $booking_data['tour_package_id']) {
                    $booking_data['package_people'] = intval($pkg['people'] ?? 0);
                    $booking_data['package_rooms'] = floatval($pkg['rooms'] ?? 0);
                    $booking_data['package_vehicles'] = intval($pkg['vehicles'] ?? 0);
                    break;
                }
            }
        }
    }
    
    // Get source and referrer for commission calculation
    $source = sanitize_text_field($_POST['source'] ?? '');
    $referrer = sanitize_text_field($_POST['referrer'] ?? '');
    
    // Auto-populate how_heard based on source code or referrer URL (same logic as GF9)
    $how_heard = null;
    if (empty($how_heard)) {
        // Check source code first
        if (!empty($source) && strtolower($source) === 'bstmc') {
            $how_heard = 'Blue Strada Tours Email List';
        }
        // Then check referrer if how_heard still empty
        elseif (!empty($referrer)) {
            $referrer_lower = strtolower($referrer);
            if (strpos($referrer_lower, 'google.') !== false || 
                strpos($referrer_lower, 'bing.') !== false || 
                strpos($referrer_lower, 'yahoo.') !== false || 
                strpos($referrer_lower, 'duckduckgo.') !== false ||
                strpos($referrer_lower, 'ecosia.') !== false ||
                strpos($referrer_lower, 'baidu.') !== false ||
                strpos($referrer_lower, 'yandex.') !== false ||
                strpos($referrer_lower, 'search.brave.') !== false ||
                strpos($referrer_lower, 'qwant.') !== false ||
                strpos($referrer_lower, 'startpage.') !== false ||
                strpos($referrer_lower, 'ask.') !== false ||
                strpos($referrer_lower, 'aol.') !== false ||
                strpos($referrer_lower, 'search.') !== false) {
                $how_heard = 'Search engine';
            } elseif (strpos($referrer_lower, 'facebook.') !== false || 
                      strpos($referrer_lower, 'fb.') !== false) {
                $how_heard = 'Facebook';
            } elseif (strpos($referrer_lower, 'instagram.') !== false) {
                $how_heard = 'Instagram';
            } elseif (strpos($referrer_lower, 'linkedin.') !== false ||
                      strpos($referrer_lower, 'lnkd.in') !== false) {
                $how_heard = 'LinkedIn';
            } elseif (strpos($referrer_lower, 'twitter.') !== false ||
                      strpos($referrer_lower, 'x.com') !== false ||
                      strpos($referrer_lower, 't.co') !== false) {
                $how_heard = 'Twitter/X';
            } elseif (strpos($referrer_lower, 'tripadvisor.') !== false) {
                $how_heard = 'TripAdvisor';
            } elseif (strpos($referrer_lower, 'youtube.') !== false ||
                      strpos($referrer_lower, 'youtu.be') !== false) {
                $how_heard = 'YouTube';
            } elseif (strpos($referrer_lower, 'whatsapp.') !== false ||
                      strpos($referrer_lower, 'wa.me') !== false) {
                $how_heard = 'WhatsApp';
            } elseif (strpos($referrer_lower, 'm.me') !== false ||
                      strpos($referrer_lower, 'messenger.') !== false) {
                $how_heard = 'Messenger';
            }
        }
    }
    
    // Calculate customer ID and commission
    list($booking_commission_percent, $booking_commission_reason, $customer_id) = bst_calculate_commission_percent(
        $booking_data['guest1_first_name'],
        $booking_data['guest1_last_name'],
        $booking_data['guest1_email'],
        $booking_data['guest1_phone'],
        '', // guest2_first_name
        '', // guest2_last_name
        $how_heard, // Auto-populated from source/referrer or null
        $source, // source
        'Waiting List' // data_source
    );
    
    $booking_data['booking_commission_percent'] = $booking_commission_percent;
    $booking_data['booking_commission_reason'] = $booking_commission_reason;
    if ($customer_id) {
        $booking_data['customer_id'] = $customer_id;
    }
    
    // Set how_heard if determined from source/referrer logic
    if (!empty($how_heard)) {
        $booking_data['how_heard'] = $how_heard;
    }
    
    // Set referrer if provided (already sanitized above)
    if (!empty($referrer)) {
        $booking_data['referrer'] = $referrer;
    }
    
    // Create the booking
    $result = bst_create_tour_booking($booking_data, 'waiting_list');
    
    if ($result['success']) {
        // Calculate queue position: count waiting list bookings for this tour date with ID < current booking ID
        global $wpdb;
        $booking_table = $wpdb->prefix . 'bst_tour_booking';
        
        $queue_position = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $booking_table 
             WHERE tour_date_id = %d 
             AND booking_status = 'Waiting List' 
             AND id < %d",
            $booking_data['tour_date_id'],
            $result['booking_id']
        ));
        
        // Log the waiting list creation
        error_log("BST: Waiting list booking #{$result['booking_id']} created for {$booking_data['guest1_first_name']} {$booking_data['guest1_last_name']} ({$booking_data['guest1_email']}) - Queue position: $queue_position");
        
        wp_send_json_success(array(
            'message' => 'Successfully added to waiting list',
            'booking_id' => $result['booking_id'],
            'queue_position' => intval($queue_position)
        ));
    } else {
        error_log("BST: Failed to create waiting list booking - " . $result['error']);
        wp_send_json_error($result['error']);
    }
}

/**
 * Create a waiting list booking with notification
 * 
 * @param array $booking_data Basic booking data for waiting list
 * @return array Success status and booking ID or error message
 */
function bst_create_waiting_list_booking_with_notification($booking_data) {
    // Set waiting list specific data
    $booking_data['booking_status'] = 'Waiting List';
    $booking_data['booking_method'] = 'Web';
    $booking_data['data_source'] = 'Waiting List Form';
    
    $result = bst_create_tour_booking($booking_data, 'waiting_list');
    
    if ($result['success']) {
        // Send notification for waiting list addition
        $booking_id = $result['booking_id'];
        
        // Format guest name like the tour bookings list
        $guest1_first = $booking_data['guest1_first_name'] ?? '';
        $guest1_last = $booking_data['guest1_last_name'] ?? '';
        $guest2_first = $booking_data['guest2_first_name'] ?? '';
        $guest2_last = $booking_data['guest2_last_name'] ?? '';
        
        if (empty($guest2_first)) {
            $guest_name = $guest1_first . ' ' . $guest1_last;
        } else {
            if (empty($guest2_last) || $guest1_last === $guest2_last) {
                $guest_name = $guest1_first . ' & ' . $guest2_first . ' ' . $guest1_last;
            } else {
                $guest_name = $guest1_first . ' ' . $guest1_last . ' & ' . $guest2_first . ' ' . $guest2_last;
            }
        }
        
        // Format tour info like the tour bookings list
        $tour_info = ($booking_data['tour_text'] ?? 'Tour') . ' (' . ($booking_data['tour_date_text'] ?? 'Date') . ') - ' . ($booking_data['tour_package_text'] ?? 'Package');
        $booking_link = admin_url('admin.php?page=view_booking&booking_id=' . $booking_id);
        
        $message = sprintf(
            '%s - %s was added to the waiting list for %s - <a href="%s">View Booking #%d</a>',
            date('M j, Y'),
            esc_html($guest_name),
            esc_html($tour_info),
            esc_url($booking_link),
            $booking_id
        );
        
        BST_Plugin::add_notification(
            'waiting_list_booking_' . $booking_id,
            $message,
            'warning',
            true,
            array('manage_options'),
            7 // Expires in 7 days
        );
    }
    
    return $result;
}

/**
 * AJAX handler to recalculate invoice fields based on GF10 logic
 */
function bst_recalculate_invoice_data() {
    check_ajax_referer('bst_tour_bookings_nonce', 'nonce');
    
    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    global $wpdb;
    $booking_id = intval($_POST['booking_id'] ?? 0);
    
    if (!$booking_id) {
        wp_send_json_error(array('message' => 'Missing booking ID.'));
        return;
    }

    // Get booking record
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $booking_table WHERE id = %d",
        $booking_id
    ));

    if (!$booking) {
        wp_send_json_error(array('message' => 'Booking not found.'));
        return;
    }

    // Get VAT rate from settings
    $vat_rate = get_option('bst_vat_rate', 22.00);
    
    // Calculate invoice fields using shared helper function
    $invoice_fields = bst_calculate_invoice_fields($booking);
    $booking_eu_percent = $invoice_fields['eu_percent'];
    $booking_vehicle_1_use_amount = $invoice_fields['vehicle_1_amount'];
    $booking_vehicle_2_use_amount = $invoice_fields['vehicle_2_amount'];
    
    // Calculate payment discounts
    $deposit_discount = floatval($booking->deposit_payment_discount ?? 0);
    $balance_payment_discount = floatval($booking->balance_payment_discount ?? 0);
    $additional_discount = floatval($booking->additional_payment_discount ?? 0);
    $payment_discount_amount = $deposit_discount + $balance_payment_discount + $additional_discount;
    
    // Calculate booking_tour_package_amount
    $net_tour_price = floatval($booking->net_tour_price ?? 0);
    $booking_tour_package_amount = $net_tour_price - $booking_vehicle_1_use_amount - $booking_vehicle_2_use_amount - $payment_discount_amount;
    
    // Set invoice date to current date if not already set
    $invoice_date = !empty($booking->booking_invoice_date) ? $booking->booking_invoice_date : current_time('mysql');
    
    // Generate invoice number if blank
    $invoice_number = $booking->booking_invoice_number;
    if (empty($invoice_number)) {
        $invoice_number = bst_generate_invoice_number();
    }
    
    // Update the booking record
    $result = $wpdb->update(
        $booking_table,
        array(
            'booking_invoice_number' => $invoice_number,
            'booking_invoice_date' => $invoice_date,
            'booking_vat_rate' => $vat_rate,
            'booking_eu_percent' => $booking_eu_percent,
            'booking_vehicle_1_use_amount' => $booking_vehicle_1_use_amount,
            'booking_vehicle_2_use_amount' => $booking_vehicle_2_use_amount,
            'booking_tour_package_amount' => $booking_tour_package_amount,
        ),
        array('id' => $booking_id),
        array('%s', '%s', '%f', '%f', '%f', '%f', '%f'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error(array('message' => 'Database update failed.'));
        return;
    }
    
    wp_send_json_success(array(
        'message' => 'Invoice data recalculated successfully.',
        'data' => array(
            'booking_invoice_number'       => $invoice_number,
            'booking_invoice_date'         => $invoice_date,
            'booking_vat_rate'             => $vat_rate,
            'booking_eu_percent'           => $booking_eu_percent,
            'booking_vehicle_1_use_amount' => $booking_vehicle_1_use_amount,
            'booking_vehicle_2_use_amount' => $booking_vehicle_2_use_amount,
            'booking_tour_package_amount'  => $booking_tour_package_amount,
        ),
    ));
}

