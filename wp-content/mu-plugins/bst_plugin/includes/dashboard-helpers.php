<?php
/**
 * Shared dashboard helpers for BST plugin.
 *
 * Centralizes key booking metrics used by both the main dashboard template
 * and the WordPress dashboard widget so business rules stay in sync.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return core booking metrics for dashboard tiles.
 *
 * @global wpdb $wpdb
 * @return array {
 *   @type array $overbooked_dates List of overbooked tour-date info arrays.
 *   @type int   $waiting_list_count
 *   @type int   $processing_count
 *   @type int   $payment_failed_count
 *   @type int   $bank_wire_count
 *   @type int   $reservations_count
 *   @type int   $finalization_count
 *   @type int   $refunds_due_count
 *   @type int   $last_24h_count
 *   @type object|null $last_web_booking
 * }
 */
function bst_get_dashboard_metrics() {
    global $wpdb;

    $booking_table = $wpdb->prefix . 'bst_tour_booking';

    // 1. Overbooked tour dates (reuse existing method logic via helper)
    if (class_exists('BST_Plugin') && method_exists('BST_Plugin', 'get_overbooked_tour_dates')) {
        $overbooked_dates = BST_Plugin::get_instance()->get_overbooked_tour_dates();
    } else {
        $overbooked_dates = array();
    }

    // 2. Waiting list bookings
    $waiting_list_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $booking_table WHERE booking_status = 'Waiting List'"
    );

    // 3. Processing payments
    $processing_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $booking_table WHERE booking_status = 'Processing'"
    );

    // 4. Payment failed
    $payment_failed_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $booking_table WHERE booking_status = 'Payment Failed'"
    );

    // 5. Bank wire pending (same criteria as existing widget)
    $bank_wire_count = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM $booking_table 
        WHERE (
            booking_status = 'Pending' OR
            (deposit_payment_method = 'Bank Wire' AND (deposit_payment_amount IS NULL OR deposit_payment_amount = 0)) OR
            (balance_payment_method = 'Bank Wire' AND (balance_payment_amount IS NULL OR balance_payment_amount = 0)) OR
            (additional_payment_method = 'Bank Wire' AND (additional_payment_amount IS NULL OR additional_payment_amount = 0))
        )
    ");

    // 6. Reservations not booked (older than 30 minutes)
    $reservations_count = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM $booking_table 
        WHERE booking_status = 'Reserved' 
        AND created_date < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");

    // 7. Finalization needed (match existing criteria)
    $one_twenty_days_from_now = date('Y-m-d', strtotime('+120 days'));
    $finalization_count = (int) $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $booking_table b
        LEFT JOIN {$wpdb->prefix}posts td ON b.tour_date_id = td.ID AND td.post_type = 'tour-date'
        LEFT JOIN {$wpdb->prefix}postmeta td_meta ON td.ID = td_meta.post_id AND td_meta.meta_key = 'start_date'
        WHERE b.booking_method = 'Web'
        AND b.booking_status = 'Booked'
        AND td_meta.meta_value IS NOT NULL
        AND (
            (LENGTH(td_meta.meta_value) = 8 AND STR_TO_DATE(td_meta.meta_value, '%%Y%%m%%d') <= %s AND STR_TO_DATE(td_meta.meta_value, '%%Y%%m%%d') >= CURDATE()) OR
            (LENGTH(td_meta.meta_value) = 10 AND td_meta.meta_value <= %s AND td_meta.meta_value >= CURDATE())
        )
        AND (b.finalization_entry_id IS NULL OR b.finalization_entry_id = 0)
    ", $one_twenty_days_from_now, $one_twenty_days_from_now));

    // 8. Refunds due (negative balance OR pending refund payment)
    $refunds_due_count = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM $booking_table 
        WHERE balance_due < 0
           OR (refund_payment_status = 'Pending' AND COALESCE(refund_payment_amount, 0) > 0)
    ");

    // 9. Web bookings in last 24 hours (exclude reservations + waiting list)
    $last_24h_count = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM $booking_table 
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND booking_status IN ('Pending', 'Booked', 'Finalized')
        AND booking_entry_id IS NOT NULL 
        AND booking_entry_id != 0
    ");

    // 10. Last web booking (for widget note)
    $last_web_booking = $wpdb->get_row("
        SELECT guest1_first_name, guest1_last_name, guest2_first_name, guest2_last_name, created_date 
        FROM $booking_table 
        WHERE booking_entry_id IS NOT NULL 
        AND booking_entry_id != 0 
        AND booking_status IN ('Pending', 'Booked', 'Finalized')
        ORDER BY created_date DESC 
        LIMIT 1
    ");

    return array(
        'overbooked_dates'      => $overbooked_dates,
        'waiting_list_count'    => $waiting_list_count,
        'processing_count'      => $processing_count,
        'payment_failed_count'  => $payment_failed_count,
        'bank_wire_count'       => $bank_wire_count,
        'reservations_count'    => $reservations_count,
        'finalization_count'    => $finalization_count,
        'refunds_due_count'     => $refunds_due_count,
        'last_24h_count'        => $last_24h_count,
        'last_web_booking'      => $last_web_booking,
    );
}

