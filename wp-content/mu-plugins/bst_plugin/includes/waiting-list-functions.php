<?php
/**
 * Waiting List Availability Functions
 * 
 * Handles checking and notifying when waiting list bookings can be converted to reserved status
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Check if a waiting list booking can be converted to reserved
 */
function check_waiting_list_availability($booking) {
    if (!$booking->tour_date_id || !$booking->package_vehicles) {
        return false;
    }
    
    // Get availability directly from the stored field
    $available_slots = intval(get_field('available_slots', $booking->tour_date_id));
    
    // Check if there's enough space for this booking's vehicle requirement
    return $available_slots >= intval($booking->package_vehicles);
}

/**
 * Check and create waiting list availability notifications
 * Can be called for all waiting list bookings or specific tour date
 * 
 * @param int|null $tour_date_id Optional tour date ID to check only that tour
 */
function check_and_create_waiting_list_notifications($tour_date_id = null) {
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    // If specific tour date provided, only check that one, otherwise check all
    $where_clause = $tour_date_id ? "AND b.tour_date_id = " . intval($tour_date_id) : "";
    
    // Get waiting list bookings (optionally filtered by tour date)
    $waiting_list_bookings = $wpdb->get_results("
        SELECT b.id, b.guest1_first_name, b.guest1_last_name, b.guest2_first_name, b.guest2_last_name, 
               b.created_date,
               b.tour_id, b.tour_date_id, b.package_vehicles, b.tour_package_id
        FROM $booking_table b
        WHERE b.booking_status = 'Waiting List' 
        $where_clause
        ORDER BY b.id ASC
    ");
    
    if (empty($waiting_list_bookings)) {
        return;
    }
    
    $convertible_details = [];
    
    // Group bookings by tour date to handle queue properly
    $bookings_by_tour_date = [];
    foreach ($waiting_list_bookings as $booking) {
        $bookings_by_tour_date[$booking->tour_date_id][] = $booking;
    }
    
    // Process each tour date separately to respect queue order
    foreach ($bookings_by_tour_date as $current_tour_date_id => $tour_bookings) {
        // Get current availability for this tour date
        // Get availability directly from the stored field
        $available_slots = intval(get_field('available_slots', $current_tour_date_id));
        
        // If no slots available, skip this tour date
        if ($available_slots <= 0) {
            continue;
        }
        
        // Sort this tour's bookings by ID (queue order)
        usort($tour_bookings, function($a, $b) {
            return $a->id - $b->id;
        });
        
        // Only notify the FIRST booking in queue (next in line)
        if (!empty($tour_bookings)) {
            $next_booking = $tour_bookings[0]; // First booking in queue (lowest ID)
            $required_vehicles = intval($next_booking->package_vehicles);
            
            // Only notify if this booking can actually fit
            if ($available_slots >= $required_vehicles) {
                // Get tour details for notification
                $tour_id = get_post_meta($next_booking->tour_date_id, 'tour', true);
                $tour_title = get_the_title($tour_id);
                
                // Use the tour date post title (which should now be auto-generated)
                $tour_date_post = get_post($next_booking->tour_date_id);
                $tour_date_title = $tour_date_post ? $tour_date_post->post_title : '';
                
                // Extract just the date part if title is in the format "Tour Name (Date)"
                $tour_date_text = '';
                if ($tour_date_title && preg_match('/\((.*)\)$/', $tour_date_title, $matches)) {
                    $tour_date_text = $matches[1];
                } else {
                    // Use full title as fallback
                    $tour_date_text = $tour_date_title ?: 'Unknown Date';
                }
                
                $convertible_details[] = [
                    'booking' => $next_booking,
                    'tour_title' => $tour_title,
                    'tour_date' => $tour_date_text,
                    'available_slots' => $available_slots,
                    'queue_position' => 1
                ];
            } else {
            }
        }
    }
    
    // Create notifications for convertible bookings
    if (!empty($convertible_details)) {
        $existing_notices = get_option('bst_admin_notices', []);
        
        foreach ($convertible_details as $detail) {
            $booking = $detail['booking'];
            $notification_id = 'waiting_list_convertible_' . $booking->id . '_' . $booking->tour_date_id . '_' . time();
            
            // Check if a similar notification already exists (same booking and tour date, regardless of timestamp)
            $notice_exists = false;
            $base_id_pattern = 'waiting_list_convertible_' . $booking->id . '_' . $booking->tour_date_id;
            foreach ($existing_notices as $existing_notice) {
                if (isset($existing_notice['id']) && strpos($existing_notice['id'], $base_id_pattern) === 0) {
                    $notice_exists = true;
                    break;
                }
            }
            
            // Only create notification if it doesn't exist
            if (!$notice_exists) {
                $formatted_name = bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '');
                
                // Create improved message format
                $slots_text = $detail['available_slots'] == 1 ? "slot" : "slots";
                $tour_date_formatted = date('M j, Y', strtotime($detail['tour_date']));
                $notification_message = "There is now availability on {$detail['tour_title']} ({$tour_date_formatted}) - {$detail['available_slots']} {$slots_text}. Waiting List <a href='/wp-admin/admin.php?page=bst-bookings&action=edit&booking_id={$booking->id}'>Booking {$booking->id}</a> ({$formatted_name}) can now be converted to a Reservation!";
                
                $existing_notices[] = [
                    'id' => $notification_id,
                    'message' => $notification_message,
                    'type' => 'success',
                    'dismissible' => true,
                    'important' => true,
                    'timestamp' => time()
                ];
            }
        }
        
        update_option('bst_admin_notices', $existing_notices);
    }
}

/**
 * Clean up old waiting list notifications for a specific tour date
 * Only call this when capacity actually changes
 */
function cleanup_waiting_list_notifications_for_tour_date($tour_date_id) {
    $existing_notices = get_option('bst_admin_notices', []);
    
    $existing_notices = array_filter($existing_notices, function($notice) use ($tour_date_id) {
        // Keep notifications that are NOT waiting list notifications for bookings on this tour date
        if (strpos($notice['id'], 'waiting_list_convertible_') === 0) {
            // This is a waiting list notification, check if it's for a booking on this tour date
            global $wpdb;
            $booking_table = $wpdb->prefix . 'bst_tour_booking';
            
            // Extract booking ID from notification ID
            if (preg_match('/waiting_list_convertible_(\d+)_/', $notice['id'], $matches)) {
                $booking_id = $matches[1];
                $booking_tour_date = $wpdb->get_var($wpdb->prepare(
                    "SELECT tour_date_id FROM $booking_table WHERE id = %d",
                    $booking_id
                ));
                
                // Remove this notification if it's for the same tour date
                return $booking_tour_date != $tour_date_id;
            }
        }
        return true; // Keep all other notifications
    });
    
    update_option('bst_admin_notices', $existing_notices);
}

/**
 * WordPress hook handlers for waiting list availability checks
 */

// Check availability when tour date meta is updated (max_slots, sold_slots, etc.)
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
    // Only check for tour date posts and relevant meta keys
    if (get_post_type($post_id) === 'tour-date' && 
        in_array($meta_key, ['max_slots', 'sold_slots', 'offline_sold_slots', 'reserved_slots'])) {
        
        // Clean up old notifications for this tour date first
        cleanup_waiting_list_notifications_for_tour_date($post_id);
        
        // Check availability for this specific tour date
        check_and_create_waiting_list_notifications($post_id);
    }
}, 10, 4);

// Check availability when tour date is saved/updated
add_action('save_post_tour-date', function($post_id) {
    // Clean up old notifications for this tour date first
    cleanup_waiting_list_notifications_for_tour_date($post_id);
    
    // Check availability for this specific tour date
    check_and_create_waiting_list_notifications($post_id);
});

// Check availability when booking status changes (e.g., confirmed booking is cancelled, freeing up slots)
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    // Check if this is a booking status change
    if ($meta_key === 'booking_status') {
        // Get the booking to find its tour_date_id
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT tour_date_id FROM $booking_table WHERE id = %d",
            $post_id
        ));
        
        if ($booking && $booking->tour_date_id) {
            // Check availability for this tour date since a booking status changed
            check_and_create_waiting_list_notifications($booking->tour_date_id);
        }
    }
}, 10, 4);

// Check availability when bookings are deleted
add_action('before_delete_post', function($post_id) {
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    // Get the booking to find its tour_date_id before it's deleted
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT tour_date_id FROM $booking_table WHERE id = %d",
        $post_id
    ));
    
    if ($booking && $booking->tour_date_id) {
        // Schedule the availability check to run after deletion
        wp_schedule_single_event(time() + 5, 'bst_check_waiting_list_availability', [$booking->tour_date_id]);
    }
});

// Custom action for scheduled availability checks
add_action('bst_check_waiting_list_availability', 'check_and_create_waiting_list_notifications');

// Hook into the existing BST booking system for better integration
add_action('bst_booking_saved', 'bst_waiting_list_on_booking_change', 15, 2);
function bst_waiting_list_on_booking_change($tour_date_id, $context) {
    cleanup_waiting_list_notifications_for_tour_date($tour_date_id);
    clear_dismissed_waiting_list_notifications($tour_date_id);
    check_and_create_waiting_list_notifications($tour_date_id);
}

add_action('bst_booking_deleted', 'bst_waiting_list_on_booking_deleted', 15, 2);
function bst_waiting_list_on_booking_deleted($tour_date_id, $context) {
    cleanup_waiting_list_notifications_for_tour_date($tour_date_id);
    clear_dismissed_waiting_list_notifications($tour_date_id);
    check_and_create_waiting_list_notifications($tour_date_id);
}

// Hook into ACF field updates for manual capacity changes
add_action('acf/save_post', 'bst_waiting_list_on_acf_save', 25);
function bst_waiting_list_on_acf_save($post_id) {
    if (get_post_type($post_id) === 'tour-date') {
        cleanup_waiting_list_notifications_for_tour_date($post_id);
        check_and_create_waiting_list_notifications($post_id);
    }
}

/**
 * Clear dismissed status for waiting list notifications for a specific tour date
 * This allows notifications to reappear if slots become available again
 */
function clear_dismissed_waiting_list_notifications($tour_date_id = null) {
    $current_user_id = get_current_user_id();
    if (!$current_user_id) return;
    
    $dismissed_notices = get_user_meta($current_user_id, 'bst_dismissed_notices', true);
    if (!is_array($dismissed_notices)) {
        return;
    }
    
    // Remove dismissed status for waiting list notifications
    $updated_dismissed = array_filter($dismissed_notices, function($notice_id) use ($tour_date_id) {
        // Remove any waiting list notifications
        if (strpos($notice_id, 'waiting_list_convertible_') === 0) {
            // If specific tour date provided, only clear for that tour date
            if ($tour_date_id !== null) {
                return strpos($notice_id, "_$tour_date_id") === false;
            }
            // Otherwise clear all waiting list notifications
            return false;
        }
        // Keep all other dismissed notices
        return true;
    });
    
    update_user_meta($current_user_id, 'bst_dismissed_notices', $updated_dismissed);
}
