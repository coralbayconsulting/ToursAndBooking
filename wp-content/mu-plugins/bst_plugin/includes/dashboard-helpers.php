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
 * Booking statuses treated as new web bookings on the dashboard (filtered by created_date).
 *
 * @return string[] Status labels as stored in booking_status.
 */
function bst_dashboard_new_web_booking_statuses() {
    return array( 'Pending', 'Booked', 'Finalized', 'Completed' );
}

/**
 * SQL IN (...) fragment for bst_dashboard_new_web_booking_statuses().
 *
 * @global wpdb $wpdb
 * @return string Quoted, comma-separated status values for SQL IN clauses.
 */
function bst_dashboard_new_web_booking_status_sql_in() {
    global $wpdb;

    return implode(
        ', ',
        array_map(
            static function ( $status ) use ( $wpdb ) {
                return $wpdb->prepare( '%s', $status );
            },
            bst_dashboard_new_web_booking_statuses()
        )
    );
}

/**
 * Bookings that need finalization and whose tour departs within the sent window.
 *
 * Includes Web and Offline (paper/admin) bookings — finalization does not require a GF9 entry.
 * Uses normalized tour-date parsing (Y-m-d, Ymd, m/d/Y) so departures are not dropped when
 * ACF stores American-style dates.
 *
 * @return object[] Rows shaped for the dashboard finalization tile.
 */
function bst_get_finalization_needed_bookings() {
    global $wpdb;

    $booking_table           = $wpdb->prefix . 'bst_tour_booking';
    $finalization_sent_days  = (int) get_option( 'bst_finalization_sent_days', 120 );
    $finalization_sent_date  = date( 'Y-m-d', strtotime( '+' . $finalization_sent_days . ' days' ) );
    $today                   = current_time( 'Y-m-d' );

    $candidates = $wpdb->get_results(
        "
        SELECT b.id, b.guest1_first_name, b.guest1_last_name, b.guest2_first_name, b.guest2_last_name,
               b.guest1_email, b.guest2_email,
               CONCAT(b.guest1_first_name, ' ', b.guest1_last_name) AS guest1_name,
               CONCAT(b.guest2_first_name, ' ', b.guest2_last_name) AS guest2_name,
               b.finalization_email_sent, b.created_date, b.tour_date_id,
               b.tour_id, b.tour_package_id,
               b.balance_due, b.tour_currency, b.finalization_entry_id, b.booking_entry_id,
               el.last_finalization_sent
        FROM {$booking_table} b
        LEFT JOIN (
            SELECT booking_id, MAX(sent_date) AS last_finalization_sent
            FROM {$wpdb->prefix}bst_email_log
            WHERE email_type = 'finalization' AND sent_successfully = 1
            GROUP BY booking_id
        ) el ON b.id = el.booking_id
        WHERE b.booking_status = 'Booked'
        AND (b.finalization_entry_id IS NULL OR b.finalization_entry_id = 0 OR b.finalization_entry_id = '')
        "
    );

    if ( empty( $candidates ) ) {
        return array();
    }

    $finalization_needed = array();

    foreach ( $candidates as $booking ) {
        $tour_date_post_id = function_exists( 'bst_booking_tour_date_post_id' )
            ? bst_booking_tour_date_post_id( $booking->tour_date_id )
            : (int) trim( explode( '|', (string) $booking->tour_date_id )[0] );

        if ( $tour_date_post_id <= 0 ) {
            continue;
        }

        $start_raw = get_post_meta( $tour_date_post_id, 'start_date', true );
        if ( ( '' === $start_raw || null === $start_raw ) && function_exists( 'get_field' ) ) {
            $acf_start = get_field( 'start_date', $tour_date_post_id );
            $start_raw = ( is_scalar( $acf_start ) && '' !== $acf_start ) ? (string) $acf_start : '';
        }

        $start_ymd = function_exists( 'bst_tour_date_acf_date_meta_to_ymd' )
            ? bst_tour_date_acf_date_meta_to_ymd( $start_raw )
            : '';

        if ( '' === $start_ymd || $start_ymd < $today || $start_ymd > $finalization_sent_date ) {
            continue;
        }

        $booking->start_date = $start_ymd;
        $finalization_needed[] = $booking;
    }

    usort(
        $finalization_needed,
        static function ( $a, $b ) {
            return strcmp( $a->start_date, $b->start_date );
        }
    );

    return $finalization_needed;
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

    // 7. Finalization needed (same rules as dashboard tile — robust date parsing)
    $finalization_count = count( bst_get_finalization_needed_bookings() );

    // 8. Refunds due (negative balance OR pending refund payment)
    $refunds_due_count = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM $booking_table 
        WHERE balance_due < 0
           OR (refund_payment_status = 'Pending' AND COALESCE(refund_payment_amount, 0) > 0)
    ");

    $new_web_booking_statuses = bst_dashboard_new_web_booking_status_sql_in();

    // 9. Web bookings in last 24 hours (exclude reservations + waiting list)
    $last_24h_count = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM $booking_table 
        WHERE created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND booking_status IN ($new_web_booking_statuses)
        AND booking_entry_id IS NOT NULL 
        AND booking_entry_id != 0
    ");

    // 10. Last web booking (for widget note)
    $last_web_booking = $wpdb->get_row("
        SELECT guest1_first_name, guest1_last_name, guest2_first_name, guest2_last_name, created_date 
        FROM $booking_table 
        WHERE booking_entry_id IS NOT NULL 
        AND booking_entry_id != 0 
        AND booking_status IN ($new_web_booking_statuses)
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

