<?php
/**
 * BST Plugin Data Export Handlers
 * 
 * Handles various data export operations for the BST Plugin
 * including Excel exports and other data export formats.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Apply tour booking list status filter for exports (matches admin list: supports all_active).
 *
 * @param string       $filter_status   GET/POST filter_status value.
 * @param string[]     $where_conditions SQL fragments (with booking_status unqualified).
 * @param array        $where_params     Bound parameters.
 */
function bst_export_apply_booking_status_filter( $filter_status, array &$where_conditions, array &$where_params ) {
    if ( empty( $filter_status ) ) {
        return;
    }
    if ( 'all_active' === $filter_status ) {
        $where_conditions[] = 'booking_status NOT IN (%s, %s)';
        $where_params[]     = 'Waiting List';
        $where_params[]     = 'Cancelled';
    } else {
        $where_conditions[] = 'booking_status = %s';
        $where_params[]     = $filter_status;
    }
}

/**
 * ORDER BY for exports — matches BST_Plugin::bst_tour_bookings_page() list ordering.
 *
 * @param string $sort_by    sort_by value (id, name, date, tour, or whitelisted column).
 * @param string $sort_order asc / desc.
 * @return string SQL fragment beginning with " ORDER BY ...".
 */
function bst_export_booking_list_order_clause( $sort_by, $sort_order ) {
    $sort_by  = is_string( $sort_by ) ? $sort_by : 'id';
    $sort_ord = ( is_string( $sort_order ) && strtolower( $sort_order ) === 'desc' ) ? 'DESC' : 'ASC';

    if ( 'name' === $sort_by ) {
        return " ORDER BY guest1_last_name {$sort_ord}, guest1_first_name {$sort_ord}";
    }
    if ( 'date' === $sort_by ) {
        return " ORDER BY created_date {$sort_ord}";
    }
    if ( 'tour' === $sort_by ) {
        global $wpdb;
        return " ORDER BY (SELECT post_title FROM {$wpdb->posts} p WHERE p.ID = {$wpdb->prefix}bst_tour_booking.tour_id LIMIT 1) {$sort_ord}";
    }
    $allowed = array( 'id', 'guest1_first_name', 'guest1_last_name', 'booking_status', 'created_date' );
    if ( in_array( $sort_by, $allowed, true ) ) {
        return " ORDER BY {$sort_by} {$sort_ord}";
    }
    // Same fallback as list when column is unknown: avoid unbounded results order.
    return " ORDER BY id {$sort_ord}";
}

function bst_export_live_tour_title( $tour_id ) {
    $tour_id = (int) $tour_id;
    if ( $tour_id <= 0 ) {
        return '';
    }
    $p = get_post( $tour_id );
    return ( $p && 'tour' === $p->post_type ) ? (string) $p->post_title : '';
}

function bst_export_live_tour_date_text( $tour_date_id ) {
    $tour_date_id = (int) $tour_date_id;
    if ( $tour_date_id <= 0 ) {
        return '';
    }
    $p = get_post( $tour_date_id );
    if ( ! $p || 'tour-date' !== $p->post_type ) {
        return '';
    }
    $start_date = get_post_meta( $tour_date_id, 'start_date', true );
    $end_date   = get_post_meta( $tour_date_id, 'end_date', true );
    if ( $start_date && $end_date ) {
        return ( date( 'M', strtotime( $start_date ) ) === date( 'M', strtotime( $end_date ) ) )
            ? date( 'j', strtotime( $start_date ) ) . '-' . date( 'j M Y', strtotime( $end_date ) )
            : date( 'j M', strtotime( $start_date ) ) . ' - ' . date( 'j M Y', strtotime( $end_date ) );
    }
    if ( $start_date ) {
        return date( 'j M Y', strtotime( $start_date ) );
    }
    return (string) $p->post_title;
}

function bst_export_live_package_name( $package_id ) {
    $package_id = (int) $package_id;
    if ( $package_id <= 0 ) {
        return '';
    }
    return (string) get_option( 'bst_package_' . $package_id . '_name', '' );
}

// #region Tour Bookings Excel Export

/**
 * Admin handler to export tour bookings to Excel
 */
add_action('admin_post_bst_export_bookings_excel', 'bst_export_bookings_excel_handler');

function bst_export_bookings_excel_handler() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['export_nonce'], 'bst_export_bookings')) {
        error_log('BST Export: Nonce verification failed');
        wp_die('Security check failed', 'Error', array('response' => 403));
    }
    
    error_log('BST Export: Security checks passed');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    
    // Get filter parameters
    $filter_tour_id = isset($_POST['filter_tour_id']) ? intval($_POST['filter_tour_id']) : 0;
    $filter_tour_date_id = isset($_POST['filter_tour_date_id']) ? intval($_POST['filter_tour_date_id']) : 0;
    $filter_status = isset($_POST['filter_status']) ? trim($_POST['filter_status']) : '';
    $sort_by = isset($_POST['sort_by']) ? $_POST['sort_by'] : 'id';
    $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 'asc';
    
    // Build the query with filters
    $where_conditions = array('1=1');
    $query_params = array();
    
    if ($filter_tour_id > 0) {
        $where_conditions[] = 'tour_id = %d';
        $query_params[] = $filter_tour_id;
    }
    
    if ($filter_tour_date_id > 0) {
        $where_conditions[] = 'tour_date_id LIKE %s';
        $query_params[] = $filter_tour_date_id . '%';
    }
    
    bst_export_apply_booking_status_filter( $filter_status, $where_conditions, $query_params );
    
    $order_clause = bst_export_booking_list_order_clause( $sort_by, $sort_order );
    
    // Execute query
    $where_clause = implode(' AND ', $where_conditions);
    $query = "SELECT * FROM {$table_name} WHERE {$where_clause}{$order_clause}";
    
    if (!empty($query_params)) {
        $bookings = $wpdb->get_results($wpdb->prepare($query, $query_params));
    } else {
        $bookings = $wpdb->get_results($query);
    }
    
    if (!$bookings) {
        error_log('BST Export: No bookings found');
        wp_redirect(admin_url('admin.php?page=bst-tour-bookings&export=error&message=no_data'));
        exit;
    }

    // Clean any previous output FIRST, before any logging
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log('BST Export: Found ' . count($bookings) . ' bookings to export');

    // Generate filename with timestamp and filters
    $filename = 'tour_bookings_' . date('Y-m-d_H-i-s');
    if ($filter_tour_id > 0) {
        $tour_name = get_the_title($filter_tour_id);
        $filename .= '_' . sanitize_file_name($tour_name);
    }
    if ($filter_tour_date_id > 0) {
        $filename .= '_date' . $filter_tour_date_id;
    }
    if (!empty($filter_status)) {
        $filename .= '_' . sanitize_file_name( 'all_active' === $filter_status ? 'All_Active_No_WL_Cancelled' : $filter_status );
    }
    $filename .= '.csv';
    
    error_log('BST Export: Generated filename: ' . $filename);
    error_log('BST Export: Headers set, starting output');
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Define all column headers (all fields from the database)
    $headers = array(
        'ID',
        'Booking Entry ID',
        'Finalization Entry ID',
        'Additional Payment Entry ID',
        'Customer ID',
        'Guest 1 First Name',
        'Guest 1 Last Name',
        'Guest 1 Nickname',
        'Guest 1 Phone',
        'Guest 1 Email',
        'Guest 1 Address Line 1',
        'Guest 1 Address Line 2',
        'Guest 1 City',
        'Guest 1 State/Province',
        'Guest 1 Postal Code',
        'Guest 1 Country',
        'Guest 1 Shirt Size',
        'Guest 1 Driving Status',
        'Guest 1 Travel Details',
        'Guest 1 Dietary Restrictions',
        'Guest 1 Medical Insurance',
        'Guest 1 Emergency Contact Name',
        'Guest 1 Emergency Contact Phone',
        'Guest 1 Emergency Contact Email',
        'Guest 2 First Name',
        'Guest 2 Last Name',
        'Guest 2 Nickname',
        'Guest 2 Phone',
        'Guest 2 Email',
        'Guest 2 Address Line 1',
        'Guest 2 Address Line 2',
        'Guest 2 City',
        'Guest 2 State/Province',
        'Guest 2 Postal Code',
        'Guest 2 Country',
        'Guest 2 Shirt Size',
        'Guest 2 Driving Status',
        'Guest 2 Travel Details',
        'Guest 2 Dietary Restrictions',
        'Guest 2 Medical Insurance',
        'Guest 2 Emergency Contact Name',
        'Guest 2 Emergency Contact Phone',
        'Guest 2 Emergency Contact Email',
        'Tour ID',
        'Tour Text',
        'Tour Date ID',
        'Tour Date Text',
        'Tour Package ID',
        'Tour Package Text',
        'Vehicle 1',
        'Vehicle 2',
        'Participant Sex',
        'Sharing Preference',
        'Bed Preference',
        'Hotel Nights Before',
        'Hotel Nights After',
        'Package People',
        'Package Rooms',
        'Package Vehicles',
        'Vehicle Choices',
        'Tour Price',
        'Tour Currency',
        'Net Tour Price',
        'Coupon Code',
        'Coupon Amount',
        'Total Paid',
        'Additional Charge',
        'Balance Due',
        'Deposit Payment Method',
        'Deposit Payment Amount',
        'Deposit Payment Date',
        'Deposit Payment Status',
        'Balance Payment Method',
        'Balance Payment Amount',
        'Balance Payment Date',
        'Balance Payment Status',
        'Additional Payment Method',
        'Additional Payment Amount',
        'Additional Payment Date',
        'Additional Payment Status',
        'Refund Payment Method',
        'Refund Payment Amount',
        'Refund Payment Date',
        'Refund Payment Status',
        'How Heard',
        'How Heard Other',
        'Motor Club',
        'Source',
        'Referrer',
        'Booking Status',
        'Booking Method',
        'Booking Commission Percent',
        'Booking Commission Reason',
        'Deposit Commission Invoice',
        'Balance Commission Invoice',
        'Additional Payment Commission Invoice',
        'Refund Commission Invoice',
        'Notes',
        'Data Source',
        'Created By',
        'Created Date',
        'Updated By',
        'Updated Date'
    );
    
    // Write headers (CSV)
    fputcsv($output, $headers);
    
    // Write booking data
    foreach ($bookings as $booking) {
        $live_tour_text = bst_export_live_tour_title( $booking->tour_id );
        $live_tour_date_text = bst_export_live_tour_date_text( $booking->tour_date_id );
        $live_package_text = bst_export_live_package_name( $booking->tour_package_id );
        $row = array(
            $booking->id,
            $booking->booking_entry_id,
            $booking->finalization_entry_id,
            $booking->additional_payment_entry_id ?? '',
            $booking->customer_id,
            $booking->guest1_first_name,
            $booking->guest1_last_name,
            $booking->guest1_nickname ?? '',
            // Quote phone numbers to preserve formatting in Excel
            !empty($booking->guest1_phone) ? '="' . $booking->guest1_phone . '"' : '',
            $booking->guest1_email,
            $booking->guest1_address_line1,
            $booking->guest1_address_line2 ?? '',
            $booking->guest1_city,
            $booking->guest1_state_province,
            // Quote postal codes to preserve leading zeros in Excel
            !empty($booking->guest1_postal_code) ? '="' . $booking->guest1_postal_code . '"' : '',
            $booking->guest1_country,
            $booking->guest1_shirt_size,
            $booking->guest1_driving_status ?? '',
            $booking->guest1_travel_details ?? '',
            $booking->guest1_dietary_restrictions ?? '',
            $booking->guest1_medical_insurance ?? '',
            $booking->guest1_emergency_contact_name,
            // Quote emergency phone numbers to preserve formatting in Excel
            !empty($booking->guest1_emergency_contact_phone) ? '="' . $booking->guest1_emergency_contact_phone . '"' : '',
            $booking->guest1_emergency_contact_email,
            $booking->guest2_first_name,
            $booking->guest2_last_name,
            $booking->guest2_nickname ?? '',
            // Quote phone numbers to preserve formatting in Excel
            !empty($booking->guest2_phone) ? '="' . $booking->guest2_phone . '"' : '',
            $booking->guest2_email,
            $booking->guest2_address_line1 ?? '',
            $booking->guest2_address_line2 ?? '',
            $booking->guest2_city ?? '',
            $booking->guest2_state_province ?? '',
            // Quote postal codes to preserve leading zeros in Excel
            !empty($booking->guest2_postal_code) ? '="' . $booking->guest2_postal_code . '"' : '',
            $booking->guest2_country ?? '',
            $booking->guest2_shirt_size,
            $booking->guest2_driving_status ?? '',
            $booking->guest2_travel_details ?? '',
            $booking->guest2_dietary_restrictions ?? '',
            $booking->guest2_medical_insurance ?? '',
            $booking->guest2_emergency_contact_name,
            // Quote emergency phone numbers to preserve formatting in Excel
            !empty($booking->guest2_emergency_contact_phone) ? '="' . $booking->guest2_emergency_contact_phone . '"' : '',
            $booking->guest2_emergency_contact_email,
            $booking->tour_id,
            $live_tour_text,
            $booking->tour_date_id,
            $live_tour_date_text,
            $booking->tour_package_id,
            $live_package_text,
            function_exists( 'bst_booking_vehicle_display_text' ) ? bst_booking_vehicle_display_text( $booking, 1 ) : '',
            function_exists( 'bst_booking_vehicle_display_text' ) ? bst_booking_vehicle_display_text( $booking, 2 ) : '',
            $booking->participant_sex ?? '',
            $booking->sharing_preference ?? '',
            $booking->bed_preference ?? '',
            $booking->hotel_nights_before ?? '',
            $booking->hotel_nights_after ?? '',
            $booking->package_people,
            $booking->package_rooms,
            $booking->package_vehicles,
            $booking->vehicle_choices,
            $booking->tour_price,
            $booking->tour_currency,
            $booking->net_tour_price,
            $booking->coupon_code,
            $booking->coupon_amount,
            $booking->total_paid,
            $booking->additional_charge,
            $booking->balance_due,
            $booking->deposit_payment_method,
            $booking->deposit_payment_amount,
            $booking->deposit_payment_date,
            $booking->deposit_payment_status ?? '',
            $booking->balance_payment_method,
            $booking->balance_payment_amount,
            $booking->balance_payment_date ?? '',
            $booking->balance_payment_status ?? '',
            $booking->additional_payment_method,
            $booking->additional_payment_amount,
            $booking->additional_payment_date ?? '',
            $booking->additional_payment_status ?? '',
            $booking->refund_payment_method ?? '',
            $booking->refund_payment_amount ?? '',
            $booking->refund_payment_date ?? '',
            $booking->refund_payment_status ?? '',
            $booking->how_heard,
            $booking->how_heard_other,
            $booking->motor_club,
            $booking->source ?? '',
            $booking->referrer ?? '',
            $booking->booking_status,
            $booking->booking_method,
            $booking->booking_commission_percent,
            $booking->booking_commission_reason,
            $booking->deposit_commission_invoice ?? '',
            $booking->balance_commission_invoice ?? '',
            $booking->additional_payment_commission_invoice ?? '',
            $booking->refund_commission_invoice ?? '',
            $booking->notes ?? '',
            $booking->data_source,
            $booking->created_by,
            $booking->created_date,
            $booking->updated_by,
            $booking->updated_date
        );
        
        // Write CSV row (fputcsv handles proper quoting automatically)
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit; // Clean exit without any additional output
    
    // If we reach here, something went wrong with the download
    // Redirect back with an error
    wp_redirect(admin_url('admin.php?page=bst-tour-bookings&export=error&message=download_failed'));
    exit;
}

// #endregion

// #region Additional Export Handlers

/**
 * Admin handler to export commission bookings to Excel
 */


/**
 * Helper function to get original commission basis in booking currency (no conversion)
 */
function bst_get_original_commission_basis($booking) {
    if (function_exists('bst_commission_booking_net_basis_original_currency')) {
        return bst_commission_booking_net_basis_original_currency($booking);
    }
    return 0;
}

/**
 * Helper function to calculate commission basis for a booking
 */
function bst_calculate_commission_basis($booking, $usd_rate) {
    if (!function_exists('bst_commission_booking_net_basis_original_currency')) {
        return 0;
    }
    $basis = bst_commission_booking_net_basis_original_currency($booking);
    $currency = strtoupper(trim($booking->tour_currency ?? 'EUR'));
    if ($currency === 'USD') {
        return $basis * floatval($usd_rate);
    }
    return $basis;
}

/**
 * Helper function to write a booking row to CSV output
 */
function bst_write_commission_booking_row($output, $booking, $usd_rate) {
    // Build composite Name field (same logic as listing)
    $guest1_first = $booking->guest1_first_name;
    $guest1_last = $booking->guest1_last_name;
    $guest2_first = $booking->guest2_first_name ?? '';
    $guest2_last = $booking->guest2_last_name ?? '';
    
    if (empty($guest2_first)) {
        $name = $guest1_first . ' ' . $guest1_last;
    } else {
        if (empty($guest2_last) || $guest1_last === $guest2_last) {
                $name = $guest1_first . ' & ' . $guest2_first . ' ' . $guest1_last;
            } else {
                $name = $guest1_first . ' ' . $guest1_last . ' & ' . $guest2_first . ' ' . $guest2_last;
            }
        }
        
        // Build composite Tour field (same logic as listing)
        $tour_label = bst_export_live_tour_title( $booking->tour_id );
        $tour_date_id = $booking->tour_date_id;
        $tour_date_text = bst_export_live_tour_date_text( $booking->tour_date_id );
        $paren = '';
        if (!empty($tour_date_id)) {
            $parts = explode('|', $tour_date_id);
            $tour_date_id_val = trim($parts[0]);
            $tour_date_post = get_post($tour_date_id_val);
            if ($tour_date_post && $tour_date_post->post_type === 'tour-date') {
                // Use standardized tour date title - extract date range from parentheses
                if (preg_match('/\((.*)\)$/', $tour_date_post->post_title, $matches)) {
                    $tour_date_text = $matches[1];
                } else {
                    // Fallback to full title if format doesn't match expected pattern
                    $tour_date_text = $tour_date_post->post_title;
                }
            }
        }
        $date_label = $tour_date_text !== '' ? $tour_date_text : $tour_date_id;
        if ($date_label) {
            $paren = $date_label;
        }
        $package_label = bst_export_live_package_name( $booking->tour_package_id );
        $tour = $tour_label . ($paren ? ' (' . $paren . ')' : '') . ' - ' . $package_label;
        
        // Build Vehicles field from Vehicle CPT ids only.
        $v1e = function_exists( 'bst_booking_vehicle_display_text' ) ? trim( (string) bst_booking_vehicle_display_text( $booking, 1 ) ) : '';
        $v2e = function_exists( 'bst_booking_vehicle_display_text' ) ? trim( (string) bst_booking_vehicle_display_text( $booking, 2 ) ) : '';
        $vehicles = $v1e;
        if ( $v2e !== '' ) {
            $vehicles .= ( $vehicles !== '' ? ' + ' : '' ) . $v2e;
        }
        
        // Build How Heard field with : delimiter when other is present
        $how_heard = trim($booking->how_heard ?? '');
        if (!empty($booking->how_heard_other)) {
            $how_heard .= ($how_heard ? ': ' : '') . trim($booking->how_heard_other);
        }
        
        // Commission export reason / "Paid on": payment line status + commission invoice fields + uninvoiced refund netting.
        $paid_on_payments = array();
        $missing_labels     = array();
        if (function_exists('bst_commission_uninvoiced_inflow_amounts')) {
            $nets = bst_commission_uninvoiced_inflow_amounts($booking);
            if (floatval($nets['deposit'] ?? 0) > 0) {
                $paid_on_payments[] = 'Deposit';
                $missing_labels[]    = 'deposit';
            }
            if (floatval($nets['balance'] ?? 0) > 0) {
                $paid_on_payments[] = 'Balance';
                $missing_labels[]    = 'balance';
            }
            if (floatval($nets['additional'] ?? 0) > 0) {
                $paid_on_payments[] = 'Additional';
                $missing_labels[]    = 'additional payment';
            }
        }
        if (function_exists('bst_commission_refund_reduces_basis') && bst_commission_refund_reduces_basis($booking)) {
            $paid_on_payments[] = 'Refund (reverse)';
        }

        $paid_on = implode(', ', $paid_on_payments);
        $export_reason = '';
        if (!empty($missing_labels)) {
            $export_reason = count($missing_labels) === 1
                ? 'No ' . $missing_labels[0] . ' commission invoice'
                : 'No ' . implode(' or ', $missing_labels) . ' commission invoice';
        } elseif (function_exists('bst_commission_refund_reduces_basis') && bst_commission_refund_reduces_basis($booking)) {
            $export_reason = 'Refund: commission reversal not invoiced';
        }

        $converted_basis = bst_calculate_commission_basis($booking, $usd_rate);
        $commission_percent = floatval($booking->booking_commission_percent ?? 0);
        $commission_to_invoice = round($converted_basis * $commission_percent, 2);
        
    // Format commission percentage as display format (2%, 5%, etc.)
    $commission_percent_display = round(floatval($booking->booking_commission_percent ?? 0) * 100) . '%';
    
    // Format currency amounts - convert USD to EUR for consistency
    $currency = strtoupper(trim($booking->tour_currency ?? 'EUR'));
    
    // Calculate "Ttl Tour Cost = tour price - coupon + add'l charge"
    $tour_price = floatval($booking->tour_price ?? 0);
    $coupon_amount = floatval($booking->coupon_amount ?? 0);
    $additional_charge = floatval($booking->additional_charge ?? 0);
    $total_tour_cost = $tour_price - $coupon_amount + $additional_charge;
    
    // Convert USD amounts to EUR for display consistency in commission export
    if ($currency === 'USD') {
        $total_tour_cost_eur = $total_tour_cost / $usd_rate;
        $total_paid_eur = floatval($booking->total_paid ?? 0) / $usd_rate;
        $balance_due_eur = floatval($booking->balance_due ?? 0) / $usd_rate;
        $deposit_pmt_eur = floatval($booking->deposit_payment_amount ?? 0) / $usd_rate;
        $balance_pmt_eur = floatval($booking->balance_payment_amount ?? 0) / $usd_rate;
        
        // Format with EUR symbol for converted amounts
        $total_tour_cost_formatted = '€ ' . number_format($total_tour_cost_eur, 2) . ' ($ ' . number_format($total_tour_cost, 2) . ')';
        $total_paid_formatted = '€ ' . number_format($total_paid_eur, 2) . ' ($ ' . number_format($booking->total_paid, 2) . ')';
        $balance_due_formatted = '€ ' . number_format($balance_due_eur, 2) . ' ($ ' . number_format($booking->balance_due, 2) . ')';
        $deposit_pmt_formatted = ($booking->deposit_payment_amount ?? 0) > 0 ? '€ ' . number_format($deposit_pmt_eur, 2) . ' ($ ' . number_format($booking->deposit_payment_amount, 2) . ')' : '';
        $balance_pmt_formatted = ($booking->balance_payment_amount ?? 0) > 0 ? '€ ' . number_format($balance_pmt_eur, 2) . ' ($ ' . number_format($booking->balance_payment_amount, 2) . ')' : '';
    } else {
        // EUR amounts - format normally
        $total_tour_cost_formatted = '€ ' . number_format($total_tour_cost, 2);
        $total_paid_formatted = '€ ' . number_format($booking->total_paid, 2);
        $balance_due_formatted = '€ ' . number_format($booking->balance_due, 2);
        $deposit_pmt_formatted = ($booking->deposit_payment_amount ?? 0) > 0 ? '€ ' . number_format($booking->deposit_payment_amount, 2) : '';
        $balance_pmt_formatted = ($booking->balance_payment_amount ?? 0) > 0 ? '€ ' . number_format($booking->balance_payment_amount, 2) : '';
    }
    
    // Format commission basis and commission (always in EUR after conversion)
    $basis_formatted = '€ ' . number_format($converted_basis, 2);
    $commission_formatted = '€ ' . number_format($commission_to_invoice, 2);

    $row = array(
        $booking->id,
        $name,
        $tour,
        $vehicles,
        $total_tour_cost_formatted,
        $total_paid_formatted,
        $balance_due_formatted,
        $deposit_pmt_formatted,
        $balance_pmt_formatted,
        $commission_percent_display,
        $booking->booking_commission_reason ?? '',
        $booking->motor_club ?? '',
        $paid_on,
        $basis_formatted,
        '', // Euros column left blank for manual conversion
        $commission_formatted
    );
    
    fputcsv($output, $row);}

// #endregion

// #region Export with DB Field Names (Tab-Delimited)

/**
 * Admin handler to export tour bookings to Excel with database field names as headers (tab-delimited)
 */
add_action('admin_post_bst_export_bookings_excel_db_fields', 'bst_export_bookings_excel_db_fields_handler');

function bst_export_bookings_excel_db_fields_handler() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['export_nonce'], 'bst_export_bookings')) {
        wp_die('Security check failed', 'Error', array('response' => 403));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    
    // Get filter parameters (same logic as main plugin class)
    $filter_tour_id = isset($_POST['filter_tour_id']) ? intval($_POST['filter_tour_id']) : 0;
    $filter_tour_date_id = isset($_POST['filter_tour_date_id']) ? intval($_POST['filter_tour_date_id']) : 0;
    $filter_status = isset($_POST['filter_status']) ? trim($_POST['filter_status']) : '';
    $sort_by = isset($_POST['sort_by']) ? $_POST['sort_by'] : 'id';
    $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 'asc';
    
    // Build the same SQL query as in the main plugin class
    $where_conditions = array();
    $where_params = array();

    if ($filter_tour_id > 0) {
        $where_conditions[] = "tour_id = %d";
        $where_params[] = $filter_tour_id;
    }

    if ($filter_tour_date_id > 0) {
        $where_conditions[] = "tour_date_id = %d";
        $where_params[] = $filter_tour_date_id;
    }

    bst_export_apply_booking_status_filter( $filter_status, $where_conditions, $where_params );

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
    }

    $order_clause = bst_export_booking_list_order_clause( $sort_by, $sort_order );

    $sql = "SELECT * FROM $table_name" . $where_clause . $order_clause;
    
    if (!empty($where_params)) {
        $bookings = $wpdb->get_results($wpdb->prepare($sql, $where_params), ARRAY_A);
    } else {
        $bookings = $wpdb->get_results($sql, ARRAY_A);
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tour_bookings_db_fields_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper Excel encoding
    fwrite($output, "\xEF\xBB\xBF");
    
    if (!empty($bookings)) {
        // Use database field names as headers
        $headers = array_keys($bookings[0]);
        
        // Write headers (CSV)
        fputcsv($output, $headers);
        
        // Write data rows (CSV)
        foreach ($bookings as $booking) {
            $row = array();
            foreach ($headers as $field) {
                $value = $booking[$field] ?? '';
                
                // Quote phone numbers and postal codes to preserve formatting in Excel
                if (in_array($field, ['guest1_phone', 'guest2_phone', 'guest1_emergency_contact_phone', 'guest2_emergency_contact_phone', 'guest1_postal_code', 'guest2_postal_code']) && !empty($value)) {
                    $value = '="' . $value . '"';
                }
                
                $row[] = $value;
            }
            // fputcsv handles proper quoting for phone numbers, zip codes, etc.
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    
    exit;
}

// #endregion

// #region Tour Excel Export

/**
 * Admin handler to export tours to Excel
 */
add_action('admin_post_bst_export_tours_excel', 'bst_export_tours_excel_handler');

function bst_export_tours_excel_handler() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['export_nonce'], 'bst_export_tours')) {
        wp_die('Security check failed', 'Error', array('response' => 403));
    }
    
    // Get filter parameters
    $tour_type_filter = isset($_POST['tour_type_filter']) ? sanitize_text_field($_POST['tour_type_filter']) : '';
    $post_status = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : '';
    $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'title';
    $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC';
    
    // Build query args
    $args = array(
        'post_type' => 'tour',
        'posts_per_page' => -1,
        'post_status' => $post_status ?: array('publish', 'draft', 'pending', 'private'),
        'orderby' => $orderby,
        'order' => $order
    );
    
    // Add search if present
    if (!empty($search)) {
        $args['s'] = $search;
    }
    
    // Add tour type filter if present (using taxonomy)
    if (!empty($tour_type_filter)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'tour-type-code',
                'field' => 'slug',
                'terms' => $tour_type_filter
            )
        );
    }
    
    $tours = get_posts($args);
    
    if (!$tours) {
        wp_redirect(admin_url('edit.php?post_type=tour&export=error&message=no_data'));
        exit;
    }
    
    // Clean any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Generate filename with timestamp and filters
    $filename = 'tours_' . date('Y-m-d_H-i-s');
    if ($meta_tour_type > 0) {
        $tour_type_name = get_the_title($meta_tour_type);
        $filename .= '_' . sanitize_file_name($tour_type_name);
    }
    if (!empty($post_status) && $post_status !== 'all') {
        $filename .= '_' . sanitize_file_name($post_status);
    }
    $filename .= '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Define column headers
    $headers = array(
        'ID',
        'Title',
        'Type Code',
        'Listing Description',
        'Sort Order',
        'Short Description',
        'New',
        'Status',
        'Date Created',
        'Last Modified'
    );
    
    fputcsv($output, $headers);
    
    // Output data
    foreach ($tours as $tour) {
        $type_code = get_field('type_code', $tour->ID);
        $type_code_slug = ($type_code && isset($type_code->slug)) ? $type_code->slug : '';
        
        $row = array(
            $tour->ID,
            $tour->post_title,
            $type_code_slug,
            get_field('listing_description', $tour->ID) ?: '',
            get_field('listing_sort_order', $tour->ID) ?: '',
            get_field('short_description', $tour->ID) ?: '',
            get_field('new', $tour->ID) ? 'Yes' : 'No',
            $tour->post_status,
            $tour->post_date,
            $tour->post_modified
        );
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// #endregion

// #region Tour Dates Excel Export

/**
 * Admin handler to export tour dates to Excel
 */
add_action('admin_post_bst_export_tour_dates_excel', 'bst_export_tour_dates_excel_handler');

function bst_export_tour_dates_excel_handler() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['export_nonce'], 'bst_export_tour_dates')) {
        wp_die('Security check failed', 'Error', array('response' => 403));
    }
    
    // Get filter parameters
    $meta_tour_filter = isset($_POST['meta_tour_filter']) ? intval($_POST['meta_tour_filter']) : 0;
    $post_status = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : '';
    $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'meta_value';
    $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC';
    
    // Build query args
    $args = array(
        'post_type' => 'tour-date',
        'posts_per_page' => -1,
        'post_status' => $post_status ?: array('publish', 'draft', 'pending', 'private'),
        'meta_key' => 'start_date',
        'orderby' => $orderby,
        'order' => $order
    );
    
    // Add search if present
    if (!empty($search)) {
        $args['s'] = $search;
    }
    
    // Add tour filter if present
    if ($meta_tour_filter > 0) {
        $args['meta_query'] = array(
            array(
                'key' => 'tour',
                'value' => $meta_tour_filter,
                'compare' => '='
            )
        );
    }
    
    $tour_dates = get_posts($args);
    
    if (!$tour_dates) {
        wp_redirect(admin_url('edit.php?post_type=tour-date&export=error&message=no_data'));
        exit;
    }
    
    // Clean any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Generate filename with timestamp and filters
    $filename = 'tour_dates_' . date('Y-m-d_H-i-s');
    if ($meta_tour_filter > 0) {
        $tour_name = get_the_title($meta_tour_filter);
        $filename .= '_' . sanitize_file_name($tour_name);
    }
    if (!empty($post_status) && $post_status !== 'all') {
        $filename .= '_' . sanitize_file_name($post_status);
    }
    $filename .= '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Define column headers
    $headers = array(
        'ID',
        'Tour',
        'Start Date',
        'End Date',
        'Available',
        'New',
        'Status',
        'Date Created',
        'Last Modified'
    );
    
    fputcsv($output, $headers);
    
    // Output data
    foreach ($tour_dates as $tour_date) {
        $tour_id = get_field('tour', $tour_date->ID);
        $tour_title = $tour_id ? get_the_title($tour_id) : 'Unknown';
        
        $availability = get_field('available_slots', $tour_date->ID);
        $availability = ($availability !== '' && $availability !== null) ? $availability : 0;
        
        $row = array(
            $tour_date->ID,
            $tour_title,
            get_field('start_date', $tour_date->ID) ?: '',
            get_field('end_date', $tour_date->ID) ?: '',
            $availability,
            get_field('new', $tour_date->ID) ? 'Yes' : 'No',
            $tour_date->post_status,
            $tour_date->post_date,
            $tour_date->post_modified
        );
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// #endregion

// #region Invoiced Amounts Report

add_action('admin_post_bst_export_invoiced_amounts', 'bst_export_invoiced_amounts_handler');

function bst_export_invoiced_amounts_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', ['response' => 403]);
    }
    if (!wp_verify_nonce($_POST['invoiced_amounts_nonce'], 'bst_export_invoiced_amounts')) {
        wp_die('Security check failed', 'Error', ['response' => 403]);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'bst_tour_booking';

    $bookings = $wpdb->get_results("
        SELECT
            b.id,
            b.booking_commission_percent,
            b.tour_currency,
            b.deposit_payment_amount,
            b.deposit_payment_date,
            b.deposit_commission_invoice,
            b.balance_payment_amount,
            b.balance_payment_date,
            b.balance_commission_invoice,
            b.additional_payment_amount,
            b.additional_payment_date,
            b.additional_payment_commission_invoice,
            b.refund_payment_amount,
            b.refund_payment_date,
            b.refund_commission_invoice
        FROM {$table} b
        WHERE (b.deposit_commission_invoice IS NOT NULL AND b.deposit_commission_invoice != '')
           OR (b.balance_commission_invoice IS NOT NULL AND b.balance_commission_invoice != '')
           OR (b.additional_payment_commission_invoice IS NOT NULL AND b.additional_payment_commission_invoice != '')
           OR (b.refund_commission_invoice IS NOT NULL AND b.refund_commission_invoice != '')
    ");

    if (!$bookings) {
        wp_die('No invoiced bookings found. DB error: ' . $wpdb->last_error);
    }

    // Build invoice map: invoice_num => [ lines grouped by currency ]
    // No currency conversion — keep each currency separate
    $invoices = [];

    foreach ($bookings as $b) {
        $pct      = floatval($b->booking_commission_percent ?? 0);
        $currency = strtoupper(trim($b->tour_currency ?? 'EUR'));

        $payments = [
            ['Deposit',    $b->deposit_payment_amount,    $b->deposit_payment_date,    $b->deposit_commission_invoice],
            ['Balance',    $b->balance_payment_amount,    $b->balance_payment_date,    $b->balance_commission_invoice],
            ['Additional', $b->additional_payment_amount, $b->additional_payment_date, $b->additional_payment_commission_invoice],
            ['Refund',     $b->refund_payment_amount,     $b->refund_payment_date,     $b->refund_commission_invoice],
        ];

        foreach ($payments as [$type, $raw_amount, $date, $inv_num]) {
            if (empty($inv_num) || floatval($raw_amount ?? 0) == 0) continue;

            $amount = floatval($raw_amount);
            if ($type === 'Refund' && function_exists('bst_commission_refund_reversal_amount')) {
                $amount = bst_commission_refund_reversal_amount($b);
                if ($amount <= 0) {
                    continue;
                }
            }
            $sign   = ($type === 'Refund') ? -1 : 1;
            $basis  = $sign * $amount;
            $commission = round($basis * $pct, 2);

            $invoices[$inv_num][] = [
                'booking'    => $b->id,
                'type'       => $type,
                'date'       => $date ?: '',
                'currency'   => $currency,
                'basis'      => $basis,
                'pct'        => $pct,
                'commission' => $commission,
            ];
        }
    }

    if (empty($invoices)) {
        wp_die('No invoice numbers found in the data.');
    }

    ksort($invoices, SORT_NATURAL);

    // Stream CSV download
    $filename = 'invoiced-amounts-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

    fputcsv($output, ['Invoice #', 'Booking ID', 'Type', 'Date', 'Currency', 'Basis Amount', 'Commission %', 'Commission Amount']);

    // Track per-invoice, per-currency totals for summary
    $summary = []; // [ inv_num => [ currency => total ] ]

    foreach ($invoices as $inv_num => $lines) {
        // Group lines by currency within this invoice
        $by_currency = [];
        foreach ($lines as $line) {
            $by_currency[$line['currency']][] = $line;
        }
        ksort($by_currency); // EUR before USD alphabetically

        foreach ($by_currency as $curr => $curr_lines) {
            foreach ($curr_lines as $line) {
                fputcsv($output, [
                    $inv_num,
                    $line['booking'],
                    $line['type'],
                    $line['date'],
                    $curr,
                    number_format($line['basis'], 2, '.', ''),
                    number_format($line['pct'] * 100, 1) . '%',
                    number_format($line['commission'], 2, '.', ''),
                ]);
            }
            $curr_total = array_sum(array_column($curr_lines, 'commission'));
            $summary[$inv_num][$curr] = ($summary[$inv_num][$curr] ?? 0) + $curr_total;
            // Currency subtotal row
            fputcsv($output, ['', '', '', '', $curr . ' Subtotal', '', '', number_format($curr_total, 2, '.', '')]);
        }

        fputcsv($output, []); // blank row between invoices
    }

    // Summary section
    fputcsv($output, []);
    fputcsv($output, ['--- SUMMARY ---']);
    fputcsv($output, ['Invoice #', 'Currency', 'Total Commission']);

    $grand = [];
    foreach ($summary as $inv_num => $currencies) {
        foreach ($currencies as $curr => $total) {
            fputcsv($output, [$inv_num, $curr, number_format($total, 2, '.', '')]);
            $grand[$curr] = ($grand[$curr] ?? 0) + $total;
        }
    }

    fputcsv($output, []);
    ksort($grand);
    foreach ($grand as $curr => $total) {
        fputcsv($output, ['Grand Total', $curr, number_format($total, 2, '.', '')]);
    }

    fclose($output);
    exit;
}

// #endregion

// #region Annual Commission Summary Export

/**
 * Admin handler to export annual commission summary by month and group
 */
add_action('admin_post_bst_export_annual_commission', 'bst_export_annual_commission_handler');

function bst_export_annual_commission_handler() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['annual_export_nonce'], 'bst_export_annual_commission')) {
        wp_die('Security check failed', 'Error', array('response' => 403));
    }
    
    $year = intval($_POST['commission_year'] ?? date('Y'));
    
    // Set up CSV headers and download
    $filename = "annual-commission-summary-{$year}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Get all bookings for the selected year
    $bookings = bst_get_annual_commission_data($year);
    
    // Process bookings and calculate monthly commissions
    $commission_data = bst_calculate_monthly_commissions($bookings, $year);

    $projection         = null;
    $projection_message = '';
    if ( ! empty( $_POST['annual_include_projection'] ) ) {
        $usd_rate = bst_get_exchange_rate( 'USD', 'EUR' );
        if ( ! $usd_rate || $usd_rate <= 0 ) {
            $usd_rate = floatval( get_option( 'bst_usd_exchange_rate', 0.86 ) );
        }
        $projection = bst_build_annual_commission_capacity_projection( (int) $year, floatval( $usd_rate ) );
        if ( null === $projection ) {
            $projection_message = 'Projection not included — no active bookings (excluding Cancelled / Waiting List / Reserved) for ' . (int) $year . ' departures, so no average commission % could be derived.';
        }
    }

    // Generate CSV output
    bst_write_annual_commission_csv( $output, $commission_data, $year, $projection, $projection_message );
    
    fclose($output);
    exit;
}

/**
 * Calculate monthly commissions by group and month
 */
function bst_calculate_monthly_commissions($bookings, $year) {
    // Initialize commission data structure
    $commission_data = array();
    
    // Define the 4 groups
    $groups = array(
        'Deposit'    => 'Deposit',
        'Balance'    => 'Balance',
        'Additional' => 'Additional',
        'Refund'     => 'Refund'
    );
    
    // Initialize data structure
    foreach ($groups as $group_key => $group_name) {
        $commission_data[$group_key] = array(
            'name' => $group_name,
            'months' => array_fill(1, 12, array('invoiced' => 0, 'ready' => 0, 'uninvoiced' => 0))
        );
    }
    
    // Get USD→EUR exchange rate (same direction as commission export uses)
    $usd_rate = bst_get_exchange_rate('USD', 'EUR');
    if (!$usd_rate || $usd_rate <= 0) {
        $usd_rate = floatval(get_option('bst_usd_exchange_rate', 0.86)); // legacy fallback
    }

    foreach ($bookings as $booking) {
        
        // Determine group classification
        $commission_percent = floatval($booking->booking_commission_percent ?? 0);
        
        // Calculate total tour cost
        $tour_price = floatval($booking->tour_price ?? 0);
        $coupon_amount = floatval($booking->coupon_amount ?? 0);
        $additional_charge = floatval($booking->additional_charge ?? 0);
        $total_tour_cost = $tour_price - $coupon_amount + $additional_charge;
        
        // Convert to EUR if USD
        $currency = strtoupper(trim($booking->tour_currency ?? 'EUR'));
        if ($currency === 'USD') {
            $total_tour_cost = $total_tour_cost * $usd_rate;
        }

        bst_allocate_commission_by_month($booking, $commission_data, $year, $total_tour_cost, $commission_percent, $usd_rate);
    }

    bst_apply_finalization_due_remaining( $commission_data, $year, $usd_rate );

    return $commission_data;
}

/**
 * Booking statuses used for the **€/slot** average (broader sample so the projection always has a rate to apply).
 *
 * Finalized + Completed: `Completed` is only set by an explicit bulk admin action; many realized trips remain `Finalized`.
 *
 * @return string[]
 */
function bst_commission_projection_benchmark_statuses() {
    return array( 'Finalized', 'Completed' );
}

/**
 * Booking statuses used for the **average percent sold** (fill rate per tour-type / blended).
 *
 * Filters on `booking_status = 'Completed'` (status of the **booking**, not the tour-date or tour CPT).
 *
 * @return string[]
 */
function bst_commission_projection_completed_statuses() {
    return array( 'Completed' );
}

/**
 * Bookings feeding projection averages — no JOIN to tour-date meta (still works when dates/tours are non-published).
 *
 * @global wpdb $wpdb
 * @return object[] Full booking rows from {@see wpdb}.
 */
function bst_get_bookings_for_commission_projection_benchmark() {
    global $wpdb;

    $table    = $wpdb->prefix . 'bst_tour_booking';
    $statuses = bst_commission_projection_benchmark_statuses();
    $in_ph    = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

    // tour_date_id may be numeric or Gravity Forms pipe form (123|Label); coerce to numeric id for sanity only.
    $sql = "
        SELECT b.*
        FROM {$table} b
        WHERE b.booking_status IN ({$in_ph})
        AND b.tour_date_id IS NOT NULL
        AND TRIM( CAST( b.tour_date_id AS CHAR ) ) <> ''
        AND CAST( NULLIF( TRIM( SUBSTRING_INDEX( CAST( b.tour_date_id AS CHAR ), '|', 1 ) ), '' ) AS UNSIGNED ) > 0
        ";

    return $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder -- unpacking matches IN (...) placeholders.
        $wpdb->prepare( $sql, ...array_values( $statuses ) )
    );
}

/**
 * Active bookings whose tour departure falls in the calendar year (for commission-% mix only — not benchmark €/slot).
 *
 * Excludes `Cancelled`, `Waiting List`, and `Reserved` (Reserved has its own pipeline and shouldn’t feed projection).
 * `Pending` is treated as an active booking (it’s a confirmed booking awaiting payment).
 *
 * @param int $departure_year Report / export calendar year (tour-date `start_date`).
 * @return object[] Booking rows.
 */
function bst_get_active_bookings_for_projection_departure_year( $departure_year ) {
    global $wpdb;

    $departure_year = (int) $departure_year;
    if ( $departure_year < 1970 || $departure_year > 2100 ) {
        return array();
    }

    $table    = $wpdb->prefix . 'bst_tour_booking';
    $start_lo = sprintf( '%04d-01-01', $departure_year );
    $start_hi = sprintf( '%04d-12-31', $departure_year );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- export batch query.
    $sql = "
        SELECT b.*
        FROM {$table} b
        INNER JOIN {$wpdb->posts} td ON td.ID = CAST(
            NULLIF( TRIM( SUBSTRING_INDEX( CAST( b.tour_date_id AS CHAR ), '|', 1 ) ), '' )
        AS UNSIGNED)
            AND td.post_type = 'tour-date'
        INNER JOIN {$wpdb->postmeta} td_sd ON td_sd.post_id = td.ID AND td_sd.meta_key = 'start_date'
        WHERE b.booking_status NOT IN ( 'Cancelled', 'Waiting List', 'Reserved' )
        AND b.tour_date_id IS NOT NULL
        AND TRIM( CAST( b.tour_date_id AS CHAR ) ) <> ''
        AND td_sd.meta_value != ''
        AND td_sd.meta_value BETWEEN %s AND %s
        ";

    return $wpdb->get_results(
        $wpdb->prepare(
            $sql,
            $start_lo,
            $start_hi
        )
    );
}

/**
 * Vehicle-slot–weighted average of stored `booking_commission_percent` (0 / 2% / 5% as decimals).
 *
 * @param object[] $bookings Rows with package_vehicles, booking_commission_percent.
 * @return array{slots: float, weighted_pct: float|null}
 */
function bst_commission_projection_slot_weighted_stored_commission_pct( array $bookings ) {
    $sum_pct_slots = 0.0;
    $sum_slots     = 0.0;

    foreach ( $bookings as $booking ) {
        $veh = (int) ( $booking->package_vehicles ?? 0 );
        if ( $veh < 1 ) {
            $veh = 1;
        }
        $pct = floatval( $booking->booking_commission_percent ?? 0 );
        $sum_pct_slots += $pct * $veh;
        $sum_slots     += $veh;
    }

    if ( $sum_slots <= 0 ) {
        return array(
            'slots'        => 0.0,
            'weighted_pct' => null,
        );
    }

    return array(
        'slots'        => $sum_slots,
        'weighted_pct' => $sum_pct_slots / $sum_slots,
    );
}

/**
 * Per-tour total price (in EUR) for the **assumed default package** based on tour-type slug.
 *
 * Couple package for Miata/van/jeep; Independence for MC/motorcycle. Match is a case-insensitive substring on
 * `bst_package_<n>_name` global options; `package_pricing` ACF on the tour holds the per-tour price for that slot.
 *
 * @param int    $tour_id        Tour CPT ID.
 * @param string $assumed_keyword `couple` or `independence` (returns null on empty).
 * @param float  $usd_rate       USD→EUR.
 * @return float|null Null when tour, ACF, package, or price is missing.
 */
function bst_commission_projection_assumed_package_price_eur( $tour_id, $assumed_keyword, $usd_rate ) {
    $tour_id         = (int) $tour_id;
    $assumed_keyword = strtolower( trim( (string) $assumed_keyword ) );
    if ( $tour_id <= 0 || '' === $assumed_keyword || ! function_exists( 'get_field' ) ) {
        return null;
    }
    $package_pricing = get_field( 'package_pricing', $tour_id );
    if ( ! is_array( $package_pricing ) ) {
        return null;
    }
    $matched_id = 0;
    for ( $i = 1; $i <= 5; $i++ ) {
        $name = strtolower( (string) get_option( 'bst_package_' . $i . '_name', '' ) );
        if ( '' !== $name && false !== strpos( $name, $assumed_keyword ) ) {
            $matched_id = $i;
            break;
        }
    }
    if ( $matched_id <= 0 ) {
        return null;
    }
    $price = floatval( $package_pricing[ 'package_' . $matched_id ] ?? 0 );
    if ( $price <= 0 ) {
        return null;
    }
    $currency = strtoupper( trim( (string) get_field( 'currency', $tour_id ) ) );
    $usd_rate = floatval( $usd_rate );
    if ( 'USD' === $currency && $usd_rate > 0 ) {
        $price = $price * $usd_rate;
    }
    return $price;
}

/**
 * Tour-date max vehicle capacity (ACF / meta max_slots).
 *
 * @param int $tour_date_post_id Tour-date post ID.
 */
function bst_commission_projection_max_slots_for_tour_date( $tour_date_post_id ) {
    $tour_date_post_id = (int) $tour_date_post_id;
    if ( $tour_date_post_id <= 0 ) {
        return 0;
    }
    if ( function_exists( 'get_field' ) ) {
        $m = get_field( 'max_slots', $tour_date_post_id );
        if ( $m !== null && $m !== '' ) {
            return max( 0, (int) $m );
        }
    }
    $raw = get_post_meta( $tour_date_post_id, 'max_slots', true );
    return ( $raw !== null && $raw !== '' ) ? max( 0, (int) $raw ) : 0;
}

/**
 * Historic sell-through from **bookings whose `booking_status` is `Completed`** (status set by bookings, not tour-dates
 * or tours). Bookings are grouped by their `tour_date_id`, and each unique tour-date contributes one percent-sold
 * data point: sum(package_vehicles on `Completed` bookings) ÷ that tour-date’s max_slots, capped at 100%.
 *
 * Final value per tour-type slug is the unweighted mean of those per-date percentages (each date counts once).
 * A per-parent-tour map is also returned (same math, keyed by tour ID) so the projection can prefer the specific
 * tour’s own history before falling back to the slug average.
 *
 * The benchmark is restricted to tour-dates whose `start_date` falls within the lookback window (default 2 years).
 *
 * @param int $lookback_years How many years back to include (0 = all history).
 * @return array{
 *   by_slug: array<string,float>,
 *   by_slug_counts: array<string,int>,
 *   by_tour: array<int,float>,
 *   by_tour_counts: array<int,int>,
 *   blended: ?float,
 *   tour_dates_used: int,
 *   lookback_years: int,
 *   window_start: string,
 *   window_end: string
 * }
 */
function bst_commission_projection_fill_rates_from_completed_bookings( $lookback_years = 2 ) {
    global $wpdb;

    $lookback_years = max( 0, (int) $lookback_years );
    $table          = $wpdb->prefix . 'bst_tour_booking';
    $statuses       = bst_commission_projection_completed_statuses();
    $in_ph          = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
    $today          = wp_date( 'Y-m-d' );
    $window_start   = $lookback_years > 0
        ? wp_date( 'Y-m-d', strtotime( '-' . $lookback_years . ' years', strtotime( $today ) ) )
        : '1970-01-01';

    // Tour-date `start_date` window narrows the benchmark to recent demand only — older years can drift.
    $sql = "
        SELECT
            CAST(
                NULLIF( TRIM( SUBSTRING_INDEX( CAST( b.tour_date_id AS CHAR ), '|', 1 ) ), '' ) AS UNSIGNED
            ) AS tdid,
            SUM( COALESCE( b.package_vehicles, 0 ) ) AS sold_veh
        FROM {$table} b
        INNER JOIN {$wpdb->posts} td
            ON td.ID = CAST(
                NULLIF( TRIM( SUBSTRING_INDEX( CAST( b.tour_date_id AS CHAR ), '|', 1 ) ), '' ) AS UNSIGNED
            )
            AND td.post_type = 'tour-date'
        INNER JOIN {$wpdb->postmeta} td_sd
            ON td_sd.post_id = td.ID
            AND td_sd.meta_key = 'start_date'
        WHERE b.booking_status IN ({$in_ph})
        AND b.tour_date_id IS NOT NULL
        AND TRIM( CAST( b.tour_date_id AS CHAR ) ) <> ''
        AND td_sd.meta_value != ''
        AND td_sd.meta_value BETWEEN %s AND %s
        GROUP BY tdid
        HAVING tdid > 0 AND sold_veh > 0
        ";

    $args = array_merge( array_values( $statuses ), array( $window_start, $today ) );

    $rows = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
        $wpdb->prepare( $sql, ...$args )
    );

    $pcts_by_slug = array();
    $pcts_by_tour = array();
    $blend_pcts   = array();
    $dates_used   = 0;

    foreach ( (array) $rows as $row ) {
        $tdid = (int) ( $row->tdid ?? 0 );
        if ( $tdid <= 0 ) {
            continue;
        }
        $max_sl = bst_commission_projection_max_slots_for_tour_date( $tdid );
        if ( $max_sl <= 0 ) {
            continue;
        }
        $sold_raw = (int) ( $row->sold_veh ?? 0 );
        $sold_cap = min( $sold_raw, $max_sl );
        $pct_sold = min( 1.0, $sold_cap / $max_sl );

        $tour_id = bst_commission_projection_tour_id_from_tour_date_post( $tdid );
        $slug    = bst_commission_projection_tour_type_slug_for_tour( $tour_id );
        $slug    = ( '' !== trim( (string) $slug ) ) ? strtolower( trim( (string) $slug ) ) : '_other';

        if ( ! isset( $pcts_by_slug[ $slug ] ) ) {
            $pcts_by_slug[ $slug ] = array();
        }
        $pcts_by_slug[ $slug ][] = $pct_sold;

        if ( $tour_id > 0 ) {
            if ( ! isset( $pcts_by_tour[ $tour_id ] ) ) {
                $pcts_by_tour[ $tour_id ] = array();
            }
            $pcts_by_tour[ $tour_id ][] = $pct_sold;
        }

        $blend_pcts[] = $pct_sold;
        ++$dates_used;
    }

    $by_slug        = array();
    $by_slug_counts = array();
    foreach ( $pcts_by_slug as $slug => $list ) {
        if ( ! empty( $list ) ) {
            $by_slug[ $slug ]        = min( 1.0, array_sum( $list ) / count( $list ) );
            $by_slug_counts[ $slug ] = count( $list );
        }
    }

    $by_tour        = array();
    $by_tour_counts = array();
    foreach ( $pcts_by_tour as $tid => $list ) {
        if ( ! empty( $list ) ) {
            $by_tour[ $tid ]        = min( 1.0, array_sum( $list ) / count( $list ) );
            $by_tour_counts[ $tid ] = count( $list );
        }
    }

    $blended = ! empty( $blend_pcts ) ? min( 1.0, array_sum( $blend_pcts ) / count( $blend_pcts ) ) : null;

    return array(
        'by_slug'         => $by_slug,
        'by_slug_counts'  => $by_slug_counts,
        'by_tour'         => $by_tour,
        'by_tour_counts'  => $by_tour_counts,
        'blended'         => $blended,
        'tour_dates_used' => $dates_used,
        'lookback_years'  => $lookback_years,
        'window_start'    => $window_start,
        'window_end'      => $today,
    );
}

/**
 * Per-tour-type cancellation rate from past tour-dates inside the lookback window.
 *
 * Looks at bookings on tour-dates whose `start_date` is between (today − $lookback_years) and today (outcome known).
 * Statuses considered: Pending, Booked, Finalized, Completed, Cancelled (excludes Reserved & Waiting List).
 *
 * Each booking is weighted by `package_vehicles` so a 4-slot booking counts more than a 1-slot booking. The rate is
 * applied to projected new bookings as a (1 − cancel_rate) drag, since some projected bookings will cancel before
 * the tour runs and won’t actually pay commission.
 *
 * @param int $lookback_years How many years back to include (0 = all history).
 * @return array{
 *   by_slug: array<string,float>,
 *   by_slug_samples: array<string,int>,
 *   blended: ?float,
 *   slots_used: int,
 *   lookback_years: int,
 *   window_start: string,
 *   window_end: string
 * }
 */
function bst_commission_projection_cancellation_rates_from_past_bookings( $lookback_years = 2 ) {
    global $wpdb;

    $lookback_years = max( 0, (int) $lookback_years );
    $table          = $wpdb->prefix . 'bst_tour_booking';
    $today          = wp_date( 'Y-m-d' );
    $window_start   = $lookback_years > 0
        ? wp_date( 'Y-m-d', strtotime( '-' . $lookback_years . ' years', strtotime( $today ) ) )
        : '1970-01-01';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- benchmark batch query.
    $sql = "
        SELECT
            CAST(
                NULLIF( TRIM( SUBSTRING_INDEX( CAST( b.tour_date_id AS CHAR ), '|', 1 ) ), '' ) AS UNSIGNED
            ) AS tdid,
            b.booking_status AS status,
            COALESCE( b.package_vehicles, 0 ) AS slots
        FROM {$table} b
        INNER JOIN {$wpdb->posts} td
            ON td.ID = CAST(
                NULLIF( TRIM( SUBSTRING_INDEX( CAST( b.tour_date_id AS CHAR ), '|', 1 ) ), '' ) AS UNSIGNED
            )
            AND td.post_type = 'tour-date'
        INNER JOIN {$wpdb->postmeta} td_sd
            ON td_sd.post_id = td.ID
            AND td_sd.meta_key = 'start_date'
        WHERE b.booking_status IN ( 'Pending', 'Booked', 'Finalized', 'Completed', 'Cancelled' )
        AND b.tour_date_id IS NOT NULL
        AND TRIM( CAST( b.tour_date_id AS CHAR ) ) <> ''
        AND td_sd.meta_value != ''
        AND td_sd.meta_value BETWEEN %s AND %s
        ";

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $window_start, $today ) );

    $tdid_to_slug = array();
    $by_slug      = array(); // slug => [ 'cancelled' => float, 'total' => float ]
    $blend_cancel = 0.0;
    $blend_total  = 0.0;

    foreach ( (array) $rows as $row ) {
        $tdid = (int) ( $row->tdid ?? 0 );
        if ( $tdid <= 0 ) {
            continue;
        }
        if ( ! array_key_exists( $tdid, $tdid_to_slug ) ) {
            $tour_id = bst_commission_projection_tour_id_from_tour_date_post( $tdid );
            $sl      = bst_commission_projection_tour_type_slug_for_tour( $tour_id );
            $sl      = ( '' !== trim( (string) $sl ) ) ? strtolower( trim( (string) $sl ) ) : '_other';
            $tdid_to_slug[ $tdid ] = $sl;
        }
        $sl     = $tdid_to_slug[ $tdid ];
        $slots  = max( 1, (int) ( $row->slots ?? 0 ) );
        $status = (string) ( $row->status ?? '' );

        if ( ! isset( $by_slug[ $sl ] ) ) {
            $by_slug[ $sl ] = array(
                'cancelled' => 0.0,
                'total'     => 0.0,
            );
        }
        $by_slug[ $sl ]['total'] += $slots;
        $blend_total             += $slots;
        if ( 'Cancelled' === $status ) {
            $by_slug[ $sl ]['cancelled'] += $slots;
            $blend_cancel                += $slots;
        }
    }

    $rates_by_slug   = array();
    $samples_by_slug = array();
    foreach ( $by_slug as $sl => $counts ) {
        $tot = floatval( $counts['total'] );
        if ( $tot > 0 ) {
            $rates_by_slug[ $sl ]   = floatval( $counts['cancelled'] ) / $tot;
            $samples_by_slug[ $sl ] = (int) $tot;
        }
    }

    $blended = $blend_total > 0 ? $blend_cancel / $blend_total : null;

    return array(
        'by_slug'         => $rates_by_slug,
        'by_slug_samples' => $samples_by_slug,
        'blended'         => $blended,
        'slots_used'      => (int) $blend_total,
        'lookback_years'  => $lookback_years,
        'window_start'    => $window_start,
        'window_end'      => $today,
    );
}

/**
 * Parent tour ID from a tour-date post (ACF field tour).
 *
 * @param int $tour_date_post_id Tour-date post ID.
 */
function bst_commission_projection_tour_id_from_tour_date_post( $tour_date_post_id ) {
    $tour_date_post_id = (int) $tour_date_post_id;
    if ( $tour_date_post_id <= 0 ) {
        return 0;
    }
    $tour = function_exists( 'get_field' ) ? get_field( 'tour', $tour_date_post_id ) : null;
    if ( is_array( $tour ) && isset( $tour['ID'] ) ) {
        return (int) $tour['ID'];
    }
    if ( is_object( $tour ) && isset( $tour->ID ) ) {
        return (int) $tour->ID;
    }
    if ( is_numeric( $tour ) ) {
        return (int) $tour;
    }
    return 0;
}

/**
 * Primary tour-type-code taxonomy slug for a tour.
 */
function bst_commission_projection_tour_type_slug_for_tour( $tour_id ) {
    $tour_id = (int) $tour_id;
    if ( $tour_id <= 0 ) {
        return '';
    }
    $terms = wp_get_post_terms(
        $tour_id,
        'tour-type-code',
        array(
            'orderby' => 'term_id',
            'order'   => 'ASC',
        )
    );
    if ( is_wp_error( $terms ) || empty( $terms[0] ) ) {
        return '';
    }
    return isset( $terms[0]->slug ) ? (string) $terms[0]->slug : '';
}

/**
 * Which package assumption to use when modeling unknown fills (projection only — not booking UI).
 *
 * Driving (Miata) / van / jeep → couple; MC / motorcycle → independence.
 *
 * @return string couple|independence empty if no rule matched.
 */
function bst_commission_projection_assumed_package_keyword_for_slug( $slug ) {
    $slug = strtolower( trim( (string) $slug ) );
    if ( '' === $slug ) {
        return '';
    }
    if (
        false !== strpos( $slug, 'motorcycle' )
        || false !== strpos( $slug, 'motorbike' )
        || preg_match( '/(^|-)mc($|-)/', $slug )
    ) {
        return 'independence';
    }
    if (
        false !== strpos( $slug, 'driving' )
        || false !== strpos( $slug, 'miata' )
        || false !== strpos( $slug, 'van' )
        || false !== strpos( $slug, 'jeep' )
    ) {
        return 'couple';
    }
    return '';
}

/**
 * Build monthly EUR projection per **tour-type slug** for the report year.
 *
 * For each future tour-date row in the export year (start_date − 3 months falls in $export_year):
 *   slug          = primary `tour-type-code` taxonomy slug for the tour
 *   assumed_pkg   = couple (driving/van/jeep — driving is the Miata slug) or independence (MC/motorcycle)
 *   tour_price    = `package_pricing[package_<n>]` ACF on the tour, where <n> matches the global package option name
 *                  containing the keyword `couple`/`independence` (USD→EUR using the same fx as the rest of the export)
 *   fill_rate     = avg % sold on Completed-status bookings, preferring the parent tour’s own history
 *                   (>= tour_min_samples), falling back to the slug average (>= slug_min_samples), falling back
 *                   to the configured `lowsample_default_fill` (50%) when neither has enough samples
 *   max_slots     = ACF/meta `max_slots` on the tour-date
 *   committed     = max_slots − available_slots
 *   typical_sold  = fill_rate × max_slots
 *   incr_bookings = min( available, max(0, typical_sold − committed) )
 *   cancel_rate   = slot-weighted Cancelled / total ratio per slug from past bookings in the lookback window
 *                   (falls back to blended; capped at 95% so projection never collapses to zero)
 *   avg_pct       = slot-weighted stored `booking_commission_percent` across **active** bookings (not Cancelled,
 *                   Waiting List, or Reserved) whose tour-date `start_date` is in $export_year
 *   row_eur       = incr_bookings × tour_price × avg_pct × (1 − cancel_rate)
 *   bucket month  = `bst_commission_remaining_forecast_month()` on (start_date − 3 months)
 *
 * Knobs (all `apply_filters`-able so they can be tuned without code edits):
 *   - `bst_commission_projection_lookback_years`           default 2  — how far back fill-rate / cancel benchmarks reach
 *   - `bst_commission_projection_slug_min_samples`         default 4  — min completed dates per slug to use slug avg
 *   - `bst_commission_projection_tour_min_samples`         default 2  — min completed dates per tour to use tour avg
 *   - `bst_commission_projection_lowsample_default_fill`   default 0.5 — fallback fill rate when neither qualifies
 *
 * @param int   $export_year
 * @param float $usd_rate
 * @return array|null Null if avg_pct can’t be derived from active same-year bookings.
 */
function bst_build_annual_commission_capacity_projection( $export_year, $usd_rate ) {
    $usd_rate    = floatval( $usd_rate );
    $export_year = (int) $export_year;

    $lookback_years    = max( 0, (int) apply_filters( 'bst_commission_projection_lookback_years', 2 ) );
    $slug_min_samples  = max( 1, (int) apply_filters( 'bst_commission_projection_slug_min_samples', 4 ) );
    $tour_min_samples  = max( 1, (int) apply_filters( 'bst_commission_projection_tour_min_samples', 2 ) );
    $lowsample_default = floatval( apply_filters( 'bst_commission_projection_lowsample_default_fill', 0.5 ) );
    $lowsample_default = max( 0.0, min( 1.0, $lowsample_default ) );

    $fill_pack       = bst_commission_projection_fill_rates_from_completed_bookings( $lookback_years );
    $fill_by_slug    = isset( $fill_pack['by_slug'] ) && is_array( $fill_pack['by_slug'] ) ? $fill_pack['by_slug'] : array();
    $fill_by_slug_n  = isset( $fill_pack['by_slug_counts'] ) && is_array( $fill_pack['by_slug_counts'] ) ? $fill_pack['by_slug_counts'] : array();
    $fill_by_tour    = isset( $fill_pack['by_tour'] ) && is_array( $fill_pack['by_tour'] ) ? $fill_pack['by_tour'] : array();
    $fill_by_tour_n  = isset( $fill_pack['by_tour_counts'] ) && is_array( $fill_pack['by_tour_counts'] ) ? $fill_pack['by_tour_counts'] : array();
    $fill_blended    = isset( $fill_pack['blended'] ) ? $fill_pack['blended'] : null;
    $fill_dates_used = isset( $fill_pack['tour_dates_used'] ) ? (int) $fill_pack['tour_dates_used'] : 0;
    $fill_window_lo  = isset( $fill_pack['window_start'] ) ? (string) $fill_pack['window_start'] : '';
    $fill_window_hi  = isset( $fill_pack['window_end'] ) ? (string) $fill_pack['window_end'] : '';

    $cancel_pack       = bst_commission_projection_cancellation_rates_from_past_bookings( $lookback_years );
    $cancel_by_slug    = isset( $cancel_pack['by_slug'] ) && is_array( $cancel_pack['by_slug'] ) ? $cancel_pack['by_slug'] : array();
    $cancel_blended    = isset( $cancel_pack['blended'] ) ? $cancel_pack['blended'] : null;
    $cancel_slots_used = isset( $cancel_pack['slots_used'] ) ? (int) $cancel_pack['slots_used'] : 0;

    $active_year    = bst_get_active_bookings_for_projection_departure_year( $export_year );
    $active_year    = is_array( $active_year ) ? $active_year : array();
    $pct_year_pack  = bst_commission_projection_slot_weighted_stored_commission_pct( $active_year );
    $avg_commission = isset( $pct_year_pack['weighted_pct'] ) ? floatval( $pct_year_pack['weighted_pct'] ) : 0.0;

    if ( $avg_commission <= 0 ) {
        return null;
    }

    global $wpdb;
    // Tour-date start_date window covering departures whose finalization-due (start − 3 months) lands in $export_year:
    // earliest start = April $export_year (due Jan); latest start = March $export_year+1 (due Dec).
    $start_lo            = sprintf( '%04d-04-01', $export_year );
    $start_hi            = sprintf( '%04d-03-31', $export_year + 1 );
    $today               = wp_date( 'Y-m-d' );
    // Tours starting within 4 months of "now" are unlikely to attract more bookings — exclude them from the projection.
    $today_ts            = strtotime( $today );
    $new_booking_cutoff  = $today_ts ? wp_date( 'Y-m-d', strtotime( '+4 months', $today_ts ) ) : $today;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- export batch query.
    // Both tour-date and parent tour must be published — projections shouldn’t fire on private/draft trips.
    $tour_dates = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT p.ID AS tour_date_post_id,
                   CAST(COALESCE(NULLIF(pm_av.meta_value, ''), '0') AS UNSIGNED) AS available_slots,
                   CAST(COALESCE(NULLIF(pm_mx.meta_value, ''), '0') AS UNSIGNED) AS max_slots,
                   pm_sd.meta_value AS start_date_raw
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_sd ON pm_sd.post_id = p.ID AND pm_sd.meta_key = 'start_date'
            LEFT JOIN {$wpdb->postmeta} pm_av ON pm_av.post_id = p.ID AND pm_av.meta_key = 'available_slots'
            LEFT JOIN {$wpdb->postmeta} pm_mx ON pm_mx.post_id = p.ID AND pm_mx.meta_key = 'max_slots'
            INNER JOIN {$wpdb->postmeta} pm_tour ON pm_tour.post_id = p.ID AND pm_tour.meta_key = 'tour'
            INNER JOIN {$wpdb->posts} t
                ON t.ID = CAST( NULLIF( TRIM( pm_tour.meta_value ), '' ) AS UNSIGNED )
                AND t.post_type = 'tour'
                AND t.post_status = 'publish'
            WHERE p.post_type = 'tour-date'
            AND p.post_status = 'publish'
            AND pm_sd.meta_value != ''
            AND pm_sd.meta_value BETWEEN %s AND %s
            ",
            $start_lo,
            $start_hi
        )
    );

    $months         = array_fill( 1, 12, 0.0 );
    $months_by_slug = array();
    $slug_summary   = array();
    $skip_reasons   = array(); // [ slug_key => [ 'no_keyword' => N, 'no_price' => N, 'no_fill' => N, 'at_or_above' => N, 'too_soon' => N ] ]

    $bump_skip = function ( $sk, $reason ) use ( &$skip_reasons ) {
        if ( ! isset( $skip_reasons[ $sk ] ) ) {
            $skip_reasons[ $sk ] = array(
                'no_keyword'   => 0,
                'no_price'     => 0,
                'no_fill'      => 0,
                'at_or_above'  => 0,
                'too_soon'     => 0,
            );
        }
        $skip_reasons[ $sk ][ $reason ] = ( $skip_reasons[ $sk ][ $reason ] ?? 0 ) + 1;
    };

    foreach ( (array) $tour_dates as $td ) {
        $avail          = intval( $td->available_slots ?? 0 );
        $max_sl_q       = intval( $td->max_slots ?? 0 );
        $start_meta     = isset( $td->start_date_raw ) ? trim( (string) $td->start_date_raw ) : '';
        $tour_date_post = isset( $td->tour_date_post_id ) ? (int) $td->tour_date_post_id : 0;

        if ( $avail <= 0 || '' === $start_meta || $tour_date_post <= 0 ) {
            continue;
        }
        if ( $start_meta < $today ) {
            continue;
        }

        // Skip tour-dates that depart within 4 months — assume no new bookings will land in time.
        if ( $start_meta < $new_booking_cutoff ) {
            $tour_id_for_skip   = bst_commission_projection_tour_id_from_tour_date_post( $tour_date_post );
            $slug_for_skip      = bst_commission_projection_tour_type_slug_for_tour( $tour_id_for_skip );
            $slug_key_for_skip  = ( '' !== trim( (string) $slug_for_skip ) ) ? strtolower( trim( (string) $slug_for_skip ) ) : '_other';
            $bump_skip( $slug_key_for_skip, 'too_soon' );
            continue;
        }

        $start_ts = strtotime( $start_meta );
        if ( ! $start_ts ) {
            continue;
        }
        $due_ts = strtotime( '-3 months', $start_ts );
        if ( ! $due_ts || (int) wp_date( 'Y', $due_ts ) !== $export_year ) {
            continue;
        }
        $month = bst_commission_remaining_forecast_month( $due_ts, $export_year );
        if ( $month < 1 || $month > 12 ) {
            continue;
        }

        $tour_id    = bst_commission_projection_tour_id_from_tour_date_post( $tour_date_post );
        $slug       = bst_commission_projection_tour_type_slug_for_tour( $tour_id );
        $slug_key   = ( '' !== trim( (string) $slug ) ) ? strtolower( trim( (string) $slug ) ) : '_other';
        $assumed_kw = bst_commission_projection_assumed_package_keyword_for_slug( $slug );

        if ( '' === $assumed_kw ) {
            $bump_skip( $slug_key, 'no_keyword' );
            continue;
        }

        $tour_price_eur = bst_commission_projection_assumed_package_price_eur( $tour_id, $assumed_kw, $usd_rate );
        if ( null === $tour_price_eur || $tour_price_eur <= 0 ) {
            $bump_skip( $slug_key, 'no_price' );
            continue;
        }

        // Fill-rate fallback chain: per-tour first (>= tour_min_samples), per-slug next (>= slug_min_samples),
        // then the configured low-sample default (50% by default). This way Jeep / brand-new slugs aren't denied
        // a projection just because they have no historical Completed bookings yet.
        $tour_n = isset( $fill_by_tour_n[ $tour_id ] ) ? (int) $fill_by_tour_n[ $tour_id ] : 0;
        $slug_n = isset( $fill_by_slug_n[ $slug_key ] ) ? (int) $fill_by_slug_n[ $slug_key ] : 0;
        if ( $tour_n >= $tour_min_samples && isset( $fill_by_tour[ $tour_id ] ) ) {
            $fill_rate   = floatval( $fill_by_tour[ $tour_id ] );
            $fill_source = 'tour';
        } elseif ( $slug_n >= $slug_min_samples && isset( $fill_by_slug[ $slug_key ] ) ) {
            $fill_rate   = floatval( $fill_by_slug[ $slug_key ] );
            $fill_source = 'slug';
        } else {
            $fill_rate   = $lowsample_default;
            $fill_source = 'fallback';
        }
        $fill_rate = max( 0.0, min( 1.0, $fill_rate ) );

        $max_sl_use = $max_sl_q > 0 ? $max_sl_q : bst_commission_projection_max_slots_for_tour_date( $tour_date_post );
        if ( $max_sl_use <= 0 ) {
            continue;
        }
        $committed     = max( 0.0, (float) $max_sl_use - (float) $avail );
        $target        = $fill_rate * (float) $max_sl_use;
        $incr_bookings = min( (float) $avail, max( 0.0, $target - $committed ) );
        if ( $incr_bookings <= 0 ) {
            $bump_skip( $slug_key, 'at_or_above' );
            continue;
        }

        // Per-slug cancellation drag — fraction of (Pending+Booked+Finalized+Completed+Cancelled) past slots that
        // ended Cancelled. Per-slug rate first, then blended, then 0. Capped at 95% so a freak sample can't zero
        // out projections entirely.
        if ( isset( $cancel_by_slug[ $slug_key ] ) ) {
            $cancel_rate = floatval( $cancel_by_slug[ $slug_key ] );
        } elseif ( null !== $cancel_blended ) {
            $cancel_rate = floatval( $cancel_blended );
        } else {
            $cancel_rate = 0.0;
        }
        $cancel_rate = max( 0.0, min( 0.95, $cancel_rate ) );
        $retention   = 1.0 - $cancel_rate;

        $row_eur = round( $incr_bookings * $tour_price_eur * $avg_commission * $retention, 2 );

        $months[ $month ] += $row_eur;
        if ( ! isset( $months_by_slug[ $slug_key ] ) ) {
            $months_by_slug[ $slug_key ] = array_fill( 1, 12, 0.0 );
        }
        $months_by_slug[ $slug_key ][ $month ] += $row_eur;

        if ( ! isset( $slug_summary[ $slug_key ] ) ) {
            $slug_summary[ $slug_key ] = array(
                'eur'          => 0.0,
                'slots'        => 0.0,
                'rows'         => 0,
                'fill_sources' => array(
                    'tour'     => 0,
                    'slug'     => 0,
                    'fallback' => 0,
                ),
                'cancel_rate'  => $cancel_rate,
            );
        }
        $slug_summary[ $slug_key ]['eur']   += $row_eur;
        $slug_summary[ $slug_key ]['slots'] += $incr_bookings;
        $slug_summary[ $slug_key ]['rows']  += 1;
        $slug_summary[ $slug_key ]['fill_sources'][ $fill_source ] = ( $slug_summary[ $slug_key ]['fill_sources'][ $fill_source ] ?? 0 ) + 1;
        $slug_summary[ $slug_key ]['cancel_rate'] = $cancel_rate;
    }

    $basis_bits = array(
        sprintf(
            'avg commission %s%% (%d active %d-departure rows, slot-weighted)',
            number_format( 100.0 * $avg_commission, 2, '.', '' ),
            count( $active_year ),
            $export_year
        ),
    );

    if ( $lookback_years > 0 ) {
        $basis_bits[] = sprintf(
            'lookback %d year(s): %s to %s',
            $lookback_years,
            $fill_window_lo,
            $fill_window_hi
        );
    } else {
        $basis_bits[] = 'lookback: all history';
    }

    if ( $fill_dates_used > 0 && null !== $fill_blended && $fill_blended > 0 ) {
        $basis_bits[] = sprintf(
            'avg %% sold (Completed bookings, grouped by tour-date) %s%%; %d tour-dates in sample',
            number_format( 100.0 * $fill_blended, 1, '.', '' ),
            $fill_dates_used
        );
    } else {
        $basis_bits[] = sprintf(
            'no Completed-status bookings in lookback — using %s%% fallback for all slugs',
            number_format( 100.0 * $lowsample_default, 0, '.', '' )
        );
    }

    $fill_samples = array();
    foreach ( $fill_by_slug as $sk => $fv ) {
        if ( '_other' === $sk || $fv <= 0 ) {
            continue;
        }
        $n_for_slug     = isset( $fill_by_slug_n[ $sk ] ) ? (int) $fill_by_slug_n[ $sk ] : 0;
        $qualifies      = $n_for_slug >= $slug_min_samples ? '' : ' (low sample → 50% used)';
        $fill_samples[] = sprintf(
            '%s %s%% (n=%d)%s',
            $sk,
            number_format( 100.0 * $fv, 0, '.', '' ),
            $n_for_slug,
            $qualifies
        );
        if ( count( $fill_samples ) >= 8 ) {
            break;
        }
    }
    if ( ! empty( $fill_samples ) ) {
        $basis_bits[] = 'by-type avg % sold: ' . implode( ', ', $fill_samples );
    }

    $cancel_samples = array();
    foreach ( $cancel_by_slug as $sk => $cv ) {
        if ( '_other' === $sk ) {
            continue;
        }
        $cancel_samples[] = sprintf( '%s %s%%', $sk, number_format( 100.0 * $cv, 1, '.', '' ) );
        if ( count( $cancel_samples ) >= 8 ) {
            break;
        }
    }
    if ( ! empty( $cancel_samples ) ) {
        $blend_label   = ( null !== $cancel_blended ) ? sprintf( ' (blended %s%%)', number_format( 100.0 * $cancel_blended, 1, '.', '' ) ) : '';
        $basis_bits[]  = sprintf(
            'cancellation drag (slot-weighted, %d slots in lookback)%s — by type: %s',
            $cancel_slots_used,
            $blend_label,
            implode( ', ', $cancel_samples )
        );
    } elseif ( null !== $cancel_blended ) {
        $basis_bits[] = sprintf(
            'cancellation drag (blended) %s%% applied (%d slots in lookback)',
            number_format( 100.0 * $cancel_blended, 1, '.', '' ),
            $cancel_slots_used
        );
    } else {
        $basis_bits[] = 'no cancellation history in lookback — projection not discounted';
    }

    $basis_bits[] = 'projection = incremental bookings × per-tour package price (couple/independence) × avg commission % × (1 − cancel rate)';

    if ( ! empty( $skip_reasons ) ) {
        $skip_strs = array();
        foreach ( $skip_reasons as $sk => $counts ) {
            $parts = array();
            if ( ! empty( $counts['no_keyword'] ) ) {
                $parts[] = $counts['no_keyword'] . ' no slug→pkg map';
            }
            if ( ! empty( $counts['no_price'] ) ) {
                $parts[] = $counts['no_price'] . ' no per-tour pkg price';
            }
            if ( ! empty( $counts['at_or_above'] ) ) {
                $parts[] = $counts['at_or_above'] . ' already at/above fill';
            }
            if ( ! empty( $counts['too_soon'] ) ) {
                $parts[] = $counts['too_soon'] . ' starts within 4 months';
            }
            if ( ! empty( $parts ) ) {
                $skip_strs[] = $sk . ': ' . implode( ', ', $parts );
            }
        }
        if ( ! empty( $skip_strs ) ) {
            $basis_bits[] = 'skipped tour-dates by slug — ' . implode( ' | ', $skip_strs );
        }
    }

    return array(
        'months'             => $months,
        'months_by_slug'     => $months_by_slug,
        'slug_summaries'     => $slug_summary,
        'avg_commission_pct' => $avg_commission,
        'active_count'       => count( $active_year ),
        'fill_blended'       => $fill_blended,
        'fill_dates_used'    => $fill_dates_used,
        'fill_by_slug'       => $fill_by_slug,
        'fill_by_slug_n'     => $fill_by_slug_n,
        'cancel_by_slug'     => $cancel_by_slug,
        'cancel_blended'     => $cancel_blended,
        'lookback_years'     => $lookback_years,
        'basis_description'  => implode( '; ', $basis_bits ) . '; bucketed by start − 3 months',
    );
}

/**
 * Calendar month column (1–12) for Remaining forecast when the due date falls in $export_year.
 *
 * For exports of the **current** calendar year, if the due date is **before today** and the due
 * date’s month is **before** the current month, the amount is bucketed in the **current month**
 * (same as the CSV roll-forward: overdue prior-month forecast shows with this month’s pile).
 *
 * @param int|false $due_ts      Unix timestamp of due date (site-local date via strtotime).
 * @param int       $export_year Year selected for the report (e.g. 2026).
 * @return int Month 1–12.
 */
function bst_commission_remaining_forecast_month( $due_ts, $export_year ) {
    if ( ! $due_ts ) {
        return 1;
    }
    $export_year = (int) $export_year;
    $due_year    = (int) date( 'Y', $due_ts );
    $m           = (int) date( 'n', $due_ts );
    if ( $due_year !== $export_year ) {
        return $m;
    }
    if ( $export_year !== (int) current_time( 'Y' ) ) {
        return $m;
    }
    $due_ymd = date( 'Y-m-d', $due_ts );
    $today   = current_time( 'Y-m-d' );
    if ( $due_ymd >= $today ) {
        return $m;
    }
    $cur_m = (int) current_time( 'n' );
    if ( $m < $cur_m ) {
        return $cur_m;
    }
    return $m;
}

/**
 * Project balance commission into Remaining (uninvoiced) by finalization due month.
 *
 * Unpaid balance lines are often Pending, so bst_commission_uninvoiced_inflow_amounts() yields no
 * balance net and bst_allocate_commission_by_month never allocates them. The dashboard/booking UI
 * uses bst_calculate_balance_due_date() (60 days or N months before tour per registration terms).
 *
 * Skips bookings that already have balance commission in the main allocation (paid/processing nets).
 *
 * @param array $commission_data Commission buckets (mutated).
 * @param int   $year            Selected export year.
 * @param float $usd_rate        USD→EUR rate (same as main commission loop).
 */
function bst_apply_finalization_due_remaining( &$commission_data, $year, $usd_rate ) {
    if ( ! function_exists( 'bst_calculate_balance_due_date' ) || ! function_exists( 'bst_commission_uninvoiced_inflow_amounts' ) ) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'bst_tour_booking';

    $rows = $wpdb->get_results(
        "
        SELECT *
        FROM {$table}
        WHERE booking_status IN ('Booked', 'Pending')
        AND (finalization_entry_id IS NULL OR finalization_entry_id = 0 OR finalization_entry_id = '')
        AND balance_due > 0
        AND booking_entry_id IS NOT NULL AND booking_entry_id != 0
        "
    );

    if ( empty( $rows ) ) {
        return;
    }

    $usd_rate = floatval( $usd_rate );
    if ( $usd_rate <= 0 ) {
        $usd_rate = floatval( get_option( 'bst_usd_exchange_rate', 0.86 ) );
    }

    foreach ( $rows as $booking ) {
        if ( ! empty( $booking->balance_commission_invoice ) ) {
            continue;
        }

        $nets = bst_commission_uninvoiced_inflow_amounts( $booking );
        if ( floatval( $nets['balance'] ?? 0 ) > 0 ) {
            continue;
        }

        $due = bst_calculate_balance_due_date( $booking );
        if ( empty( $due ) ) {
            continue;
        }

        $due_ts = strtotime( $due );
        if ( ! $due_ts ) {
            continue;
        }

        if ( (int) date( 'Y', $due_ts ) !== (int) $year ) {
            continue;
        }

        $month = bst_commission_remaining_forecast_month( $due_ts, (int) $year );
        if ( $month < 1 || $month > 12 ) {
            continue;
        }

        $commission_percent = floatval( $booking->booking_commission_percent ?? 0 );
        $fx                 = ( strtoupper( trim( $booking->tour_currency ?? 'EUR' ) ) === 'USD' ) ? $usd_rate : 1.0;
        $basis              = floatval( $booking->balance_due ?? 0 );
        if ( $basis <= 0 || $commission_percent <= 0 ) {
            continue;
        }

        $commission = round( $basis * $fx * $commission_percent, 2 );
        if ( $commission <= 0 ) {
            continue;
        }

        if ( isset( $commission_data['Balance']['months'][ $month ]['uninvoiced'] ) ) {
            $commission_data['Balance']['months'][ $month ]['uninvoiced'] += $commission;
        }
    }
}

/**
 * Allocate commission amounts to specific months.
 *
 * Uses bst_commission_uninvoiced_inflow_amounts + invoice fields (same basis as bst_calculate_commission_basis).
 * No paper/offline special case; no booking_status-based branching for deposit/balance/additional.
 *
 * Payment dates are used only to determine which month to place the amount in.
 */
function bst_allocate_commission_by_month($booking, &$commission_data, $year, $total_tour_cost, $commission_percent, $usd_rate) {
    $fx = (strtoupper(trim($booking->tour_currency ?? 'EUR')) === 'USD') ? $usd_rate : 1.0;

    $place = function ( $group, $date, $amount, $invoice_field, $bucket_if_uninvoiced ) use ( &$commission_data, $year ) {
        if ( $amount <= 0 ) {
            return;
        }
        $bucket = ! empty( $invoice_field ) ? 'invoiced' : $bucket_if_uninvoiced;
        if ( ! empty( $date ) ) {
            $m  = intval( date( 'n', strtotime( $date ) ) );
            $yr = intval( date( 'Y', strtotime( $date ) ) );
        } else {
            $m  = 1;
            $yr = $year;
        }
        if ( 'invoiced' === $bucket ) {
            if ( $yr !== (int) $year ) {
                return;
            }
        } else {
            if ( $yr > $year ) {
                return;
            }
            if ( $yr < $year ) {
                // Unbilled invoicable items roll forward into the next invoicing cycle. Within the current-year
                // report that's the current month (handled by the roll-forward block in the CSV writer). They
                // should NOT appear in a future-year report — those events belong to the prior year. The one
                // exception: when the report is generated in December, the next invoicing cycle has already
                // crossed into January of next year, so we let prior-year unbilled flow into Jan of the
                // next-year report.
                $today_year  = (int) current_time( 'Y' );
                $today_month = (int) current_time( 'n' );
                $december_to_next_year = ( 12 === $today_month && (int) $year === $today_year + 1 );
                if ( (int) $year > $today_year && ! $december_to_next_year ) {
                    return;
                }
                $m = 1;
            }
            if ( 'ready' === $bucket && ! empty( $date ) && date( 'Y-m-d', strtotime( $date ) ) > current_time( 'Y-m-d' ) ) {
                $bucket = 'uninvoiced';
            }
        }
        if ( 'uninvoiced' === $bucket && function_exists( 'bst_commission_remaining_forecast_month' ) && ! empty( $date ) ) {
            $ts = strtotime( $date );
            if ( $ts && (int) date( 'Y', $ts ) === (int) $year ) {
                $m = bst_commission_remaining_forecast_month( $ts, (int) $year );
            }
        }
        $commission_data[ $group ]['months'][ $m ][ $bucket ] += $amount;
    };

    $dep_amt = floatval( $booking->deposit_payment_amount ?? 0 ) * $fx;
    $bal_amt = floatval( $booking->balance_payment_amount ?? 0 ) * $fx;
    $add_amt = floatval( $booking->additional_payment_amount ?? 0 ) * $fx;

    $nets = array( 'deposit' => 0.0, 'balance' => 0.0, 'additional' => 0.0 );
    if ( function_exists( 'bst_commission_uninvoiced_inflow_amounts' ) ) {
        $nets = bst_commission_uninvoiced_inflow_amounts( $booking );
    }

    $line_defs = array(
        array(
            'group'   => 'Deposit',
            'gross_fx' => $dep_amt,
            'net_key' => 'deposit',
            'date'    => 'deposit_payment_date',
            'inv'     => 'deposit_commission_invoice',
        ),
        array(
            'group'   => 'Balance',
            'gross_fx' => $bal_amt,
            'net_key' => 'balance',
            'date'    => 'balance_payment_date',
            'inv'     => 'balance_commission_invoice',
        ),
        array(
            'group'   => 'Additional',
            'gross_fx' => $add_amt,
            'net_key' => 'additional',
            'date'    => 'additional_payment_date',
            'inv'     => 'additional_payment_commission_invoice',
        ),
    );

    foreach ( $line_defs as $L ) {
        $gross = $L['gross_fx'];
        if ( $gross <= 0 || ! function_exists( 'bst_commission_line_needs_invoice' ) ) {
            continue;
        }
        $inv   = $booking->{ $L['inv'] } ?? '';
        $pdate = $booking->{ $L['date'] } ?? null;
        $nk    = $L['net_key'];
        if ( ! empty( $inv ) ) {
            $place( $L['group'], $pdate, round( $gross * $commission_percent, 2 ), $inv, 'ready' );
        } elseif ( floatval( $nets[ $nk ] ?? 0 ) > 0 ) {
            $place( $L['group'], $pdate, round( floatval( $nets[ $nk ] ) * $fx * $commission_percent, 2 ), '', 'ready' );
        }
    }

    $ref_amt = 0.0;
    if ( function_exists( 'bst_commission_refund_reversal_amount' ) ) {
        $ref_amt = bst_commission_refund_reversal_amount( $booking ) * $fx;
    } elseif ( floatval( $booking->refund_payment_amount ?? 0 ) > 0
        && function_exists( 'bst_commission_refund_reduces_basis' )
        && bst_commission_refund_reduces_basis( $booking ) ) {
        $ref_amt = floatval( $booking->refund_payment_amount ?? 0 ) * $fx;
    }
    if ( $ref_amt > 0 ) {
        $ref_comm    = round( $ref_amt * $commission_percent, 2 );
        $refund_date = ! empty( $booking->refund_payment_date ) ? $booking->refund_payment_date : date( 'Y-m-d', strtotime( '+7 days' ) );
        if ( ! empty( $booking->refund_commission_invoice ) ) {
            $place( 'Refund', $refund_date, $ref_comm, $booking->refund_commission_invoice, 'ready' );
        } elseif ( function_exists( 'bst_commission_refund_reduces_basis' ) && bst_commission_refund_reduces_basis( $booking ) ) {
            $place( 'Refund', $refund_date, $ref_comm, '', 'ready' );
        }
    }
}

/**
 * Get all booking data for annual commission calculations
 */
function bst_get_annual_commission_data($year) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    
    // Query to get all bookings for the year with necessary date fields
    // Join with tour date posts to get the actual end dates
    $query = $wpdb->prepare("
        SELECT 
            b.id,
            b.tour_id,
            b.tour_date_id,
            b.created_date as booking_date,
            b.deposit_payment_date,
            b.balance_payment_date,
            b.booking_status,
            b.booking_method,
            b.booking_commission_percent,
            b.booking_commission_reason,
            b.tour_price,
            b.coupon_amount,
            b.additional_charge,
            b.deposit_payment_amount,
            b.deposit_payment_status,
            b.balance_payment_amount,
            b.balance_payment_status,
            b.additional_payment_amount,
            b.additional_payment_date,
            b.additional_payment_status,
            b.refund_payment_amount,
            b.refund_payment_date,
            b.refund_payment_status,
            b.deposit_commission_invoice,
            b.balance_commission_invoice,
            b.additional_payment_commission_invoice,
            b.refund_commission_invoice,
            b.tour_currency,
            b.total_paid,
            b.balance_due,
            b.package_people,
            b.booking_entry_id,
            b.finalization_entry_id,
            td_meta.meta_value as tour_end_date,
            td_start.meta_value as tour_start_date
        FROM {$table_name} b
        LEFT JOIN {$wpdb->posts} td ON b.tour_date_id = td.ID
        LEFT JOIN {$wpdb->postmeta} td_meta ON td.ID = td_meta.post_id AND td_meta.meta_key = 'end_date'
        LEFT JOIN {$wpdb->postmeta} td_start ON td.ID = td_start.post_id AND td_start.meta_key = 'start_date'
        WHERE (
            YEAR(td_meta.meta_value) = %d OR
            YEAR(td_start.meta_value) = %d OR
            YEAR(b.created_date) = %d OR
            YEAR(b.deposit_payment_date) = %d OR
            YEAR(b.balance_payment_date) = %d OR
            YEAR(b.additional_payment_date) = %d
        )
        AND b.booking_status NOT IN ('Cancelled', 'Waiting List')
        ORDER BY td_meta.meta_value, b.created_date
    ", $year, $year, $year, $year, $year, $year);
    
    $results = $wpdb->get_results($query);
    
    return $results;
}

/**
 * Write annual commission data to CSV
 *
 * @param resource|false $output Stream from fopen('php://output','w').
 * @param array          $commission_data Deposit/Balance/Additional/Refund only (actual pipeline).
 * @param int|string     $year            Report year.
 * @param array|null     $projection      From {@see bst_build_annual_commission_capacity_projection()}, or null if disabled / unavailable.
 * @param string         $projection_message Optional notice row when projection requested but not computed.
 */
function bst_write_annual_commission_csv( $output, $commission_data, $year, $projection = null, $projection_message = '' ) {
    $year                = intval( $year );
    $actual_group_order = array( 'Deposit', 'Balance', 'Additional', 'Refund' );

    $headers       = array( 'Group' );
    $month_names   = array( '', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' );
    $month_headers = array();
    for ( $month = 1; $month <= 12; $month++ ) {
        $month_headers[] = $month_names[ $month ];
    }
    $headers = array_merge( $headers, $month_headers, array( 'Total' ) );

    $row_width_pad = count( $headers );

    $pad_row_first = static function ( $first_cell ) use ( $row_width_pad ) {
        $row = array( $first_cell );
        while ( count( $row ) < $row_width_pad ) {
            $row[] = '';
        }
        return $row;
    };

    fputcsv( $output, array( "Annual Commission Summary - {$year}" ) );

    if ( '' !== $projection_message ) {
        fputcsv( $output, $pad_row_first( $projection_message ) );
    }

    fputcsv( $output, array() );
    fputcsv( $output, $headers );

    // For the current year: roll-forward applies only to actual groups (Deposit…Refund).
    $current_year  = intval( current_time( 'Y' ) );
    $current_month = intval( current_time( 'n' ) );

    if ( intval( $year ) === $current_year ) {
        foreach ( $actual_group_order as $group_key ) {
            if ( ! isset( $commission_data[ $group_key ] ) ) {
                continue;
            }
            $group_data = &$commission_data[ $group_key ];
            for ( $month = 1; $month <= 12; $month++ ) {
                if ( $month === $current_month ) {
                    continue;
                }
                $ready = $group_data['months'][ $month ]['ready'];
                if ( 0 !== $ready ) {
                    $group_data['months'][ $current_month ]['ready'] += $ready;
                    $group_data['months'][ $month ]['ready']         = 0;
                }
                if ( $month < $current_month ) {
                    $uninvoiced = $group_data['months'][ $month ]['uninvoiced'];
                    if ( 0 !== $uninvoiced ) {
                        $group_data['months'][ $current_month ]['uninvoiced'] += $uninvoiced;
                        $group_data['months'][ $month ]['uninvoiced']         = 0;
                    }
                }
            }
            unset( $group_data );
        }
    }

    $grand_totals          = array_fill( 1, 12, 0 );
    $grand_invoiced        = array_fill( 1, 12, 0 );
    $grand_ready           = array_fill( 1, 12, 0 );
    $grand_remaining       = array_fill( 1, 12, 0 );
    $grand_total           = 0;
    $grand_invoiced_total  = 0;
    $grand_ready_total     = 0;
    $grand_remaining_total = 0;

    fputcsv( $output, $pad_row_first( 'Actual — from bookings & pipeline (commission groups)' ) );

    foreach ( $actual_group_order as $group_key ) {
        if ( ! isset( $commission_data[ $group_key ] ) ) {
            continue;
        }
        $group_data  = $commission_data[ $group_key ];
        $row         = array( $group_data['name'] );
        $group_total = 0;

        for ( $month = 1; $month <= 12; $month++ ) {
            $month_total = $group_data['months'][ $month ]['invoiced']
                + $group_data['months'][ $month ]['ready']
                + $group_data['months'][ $month ]['uninvoiced'];

            $row[] = $month_total > 0 ? '€ ' . number_format( $month_total, 2 ) : '';

            $group_total += $month_total;
            $grand_totals[ $month ]          += $month_total;
            $grand_invoiced[ $month ]        += $group_data['months'][ $month ]['invoiced'];
            $grand_ready[ $month ]           += $group_data['months'][ $month ]['ready'];
            $grand_remaining[ $month ]       += $group_data['months'][ $month ]['uninvoiced'];
        }

        $row[] = $group_total > 0 ? '€ ' . number_format( $group_total, 2 ) : '';
        $grand_total += $group_total;

        fputcsv( $output, $row );
    }

    $proj_monthlies = array_fill( 1, 12, 0.0 );
    $proj_sum       = 0.0;

    if ( is_array( $projection ) && isset( $projection['months'] ) ) {
        for ( $m = 1; $m <= 12; $m++ ) {
            $proj_monthlies[ $m ] = isset( $projection['months'][ $m ] )
                ? round( floatval( $projection['months'][ $m ] ), 2 )
                : 0.0;
            $proj_sum += $proj_monthlies[ $m ];
        }
    }

    if ( is_array( $projection ) && isset( $projection['months'] ) ) {
        fputcsv( $output, array() );

        $desc        = isset( $projection['basis_description'] ) ? (string) $projection['basis_description'] : '';
        $by_slug     = isset( $projection['months_by_slug'] ) && is_array( $projection['months_by_slug'] ) ? $projection['months_by_slug'] : array();
        $summaries   = isset( $projection['slug_summaries'] ) && is_array( $projection['slug_summaries'] ) ? $projection['slug_summaries'] : array();
        $fill_by_sl  = isset( $projection['fill_by_slug'] ) && is_array( $projection['fill_by_slug'] ) ? $projection['fill_by_slug'] : array();
        $avg_pct     = isset( $projection['avg_commission_pct'] ) ? floatval( $projection['avg_commission_pct'] ) : 0.0;

        $heading = 'Estimated from historical averages — not invoiced bookings';
        if ( '' !== $desc ) {
            $heading .= ' — ' . $desc;
        }
        fputcsv( $output, $pad_row_first( $heading ) );

        // Sort slugs by total EUR descending so heaviest contributor is on top.
        uksort(
            $by_slug,
            function ( $a, $b ) use ( $summaries ) {
                $ea = isset( $summaries[ $a ]['eur'] ) ? floatval( $summaries[ $a ]['eur'] ) : 0.0;
                $eb = isset( $summaries[ $b ]['eur'] ) ? floatval( $summaries[ $b ]['eur'] ) : 0.0;
                return ( $eb <=> $ea );
            }
        );

        foreach ( $by_slug as $slug => $monthly_arr ) {
            $sum     = isset( $summaries[ $slug ] ) ? $summaries[ $slug ] : array(
                'eur'          => 0.0,
                'slots'        => 0.0,
                'rows'         => 0,
                'fill_sources' => array(),
                'cancel_rate'  => 0.0,
            );
            $fill    = isset( $fill_by_sl[ $slug ] ) ? floatval( $fill_by_sl[ $slug ] ) : 0.0;
            $sources = isset( $sum['fill_sources'] ) && is_array( $sum['fill_sources'] ) ? $sum['fill_sources'] : array();
            $src_bits = array();
            foreach ( array( 'tour' => 'tour', 'slug' => 'slug', 'fallback' => '50%' ) as $sk => $sl_label ) {
                $cnt = isset( $sources[ $sk ] ) ? (int) $sources[ $sk ] : 0;
                if ( $cnt > 0 ) {
                    $src_bits[] = $cnt . ' ' . $sl_label;
                }
            }
            $src_str  = ! empty( $src_bits ) ? ' [fill src: ' . implode( ', ', $src_bits ) . ']' : '';
            $cr       = isset( $sum['cancel_rate'] ) ? floatval( $sum['cancel_rate'] ) : 0.0;
            $cr_str   = $cr > 0 ? sprintf( ', cancel drag %s%%', number_format( 100.0 * $cr, 1, '.', '' ) ) : '';
            $label   = sprintf(
                'Projected (%s) — %d tour-dates, %s incremental bookings, fill %s%% × commission %s%%%s%s',
                $slug,
                isset( $sum['rows'] ) ? (int) $sum['rows'] : 0,
                number_format( isset( $sum['slots'] ) ? floatval( $sum['slots'] ) : 0.0, 1, '.', '' ),
                $fill > 0 ? number_format( 100.0 * $fill, 1, '.', '' ) : '?',
                number_format( 100.0 * $avg_pct, 2, '.', '' ),
                $cr_str,
                $src_str
            );
            $row     = array( $label );
            $row_tot = 0.0;
            for ( $month = 1; $month <= 12; $month++ ) {
                $val      = isset( $monthly_arr[ $month ] ) ? round( floatval( $monthly_arr[ $month ] ), 2 ) : 0.0;
                $row_tot += $val;
                $row[]    = $val > 0 ? '€ ' . number_format( $val, 2 ) : '';
            }
            $row[] = $row_tot > 0 ? '€ ' . number_format( $row_tot, 2 ) : '';
            fputcsv( $output, $row );
        }

        // Total row across all slugs (matches $proj_monthlies above).
        $p_row   = array( 'Projected (total)' );
        $p_total = 0.0;
        for ( $month = 1; $month <= 12; $month++ ) {
            $val      = isset( $proj_monthlies[ $month ] ) ? round( floatval( $proj_monthlies[ $month ] ), 2 ) : 0.0;
            $p_total += $val;
            $p_row[]  = $val > 0 ? '€ ' . number_format( $val, 2 ) : '';
        }
        $p_row[] = $p_total > 0 ? '€ ' . number_format( $p_total, 2 ) : '';
        fputcsv( $output, $p_row );
    }

    // Blank row before totals (actual totals only reference booked pipeline).
    fputcsv( $output, array() );

    // TOTALS (actual bookings & pipeline — excludes modeled capacity fill).
    $totals_row = array( 'TOTALS — actual above' );
    for ( $month = 1; $month <= 12; $month++ ) {
        $totals_row[] = $grand_totals[ $month ] > 0 ? '€ ' . number_format( $grand_totals[ $month ], 2 ) : '';
        $grand_invoiced_total  += $grand_invoiced[ $month ];
        $grand_ready_total     += $grand_ready[ $month ];
        $grand_remaining_total += $grand_remaining[ $month ];
    }
    $totals_row[] = $grand_total > 0 ? '€ ' . number_format( $grand_total, 2 ) : '';
    fputcsv( $output, $totals_row );

    if ( $proj_sum > 0 ) {
        $comb_row = array( 'COMBINED — actual TOTALS row + projected capacity row' );
        for ( $month = 1; $month <= 12; $month++ ) {
            $am = isset( $grand_totals[ $month ] ) ? floatval( $grand_totals[ $month ] ) : 0.0;
            $pm = isset( $proj_monthlies[ $month ] ) ? floatval( $proj_monthlies[ $month ] ) : 0.0;
            $t  = round( $am + $pm, 2 );
            $comb_row[] = $t > 0 ? '€ ' . number_format( $t, 2 ) : '';
        }
        $combined_total = round( $grand_total + $proj_sum, 2 );
        $comb_row[]     = $combined_total > 0 ? '€ ' . number_format( $combined_total, 2 ) : '';
        fputcsv( $output, $comb_row );
    }

    // Actual pipeline breakdown rows (omit projection).
    $invoiced_row = array( 'Invoiced (actual)' );
    for ( $month = 1; $month <= 12; $month++ ) {
        $invoiced_row[] = $grand_invoiced[ $month ] > 0 ? '€ ' . number_format( $grand_invoiced[ $month ], 2 ) : '';
    }
    $invoiced_row[] = $grand_invoiced_total > 0 ? '€ ' . number_format( $grand_invoiced_total, 2 ) : '';
    fputcsv( $output, $invoiced_row );

    $ready_row = array( 'Ready to Invoice (actual)' );
    for ( $month = 1; $month <= 12; $month++ ) {
        $val = $grand_ready[ $month ];
        if ( intval( $year ) === $current_year ) {
            $show       = ( $month === $current_month );
            $ready_row[] = ( $show && $val > 0 ) ? '€ ' . number_format( $val, 2 ) : '';
        } else {
            $ready_row[] = $val > 0 ? '€ ' . number_format( $val, 2 ) : '';
        }
    }
    $ready_row[] = $grand_ready_total > 0 ? '€ ' . number_format( $grand_ready_total, 2 ) : '';
    fputcsv( $output, $ready_row );

    // Remaining: booking pipeline uninvoiced (not capacity model).
    $remaining_row = array( 'Remaining (actual unpaid / forecast by due)' );
    for ( $month = 1; $month <= 12; $month++ ) {
        $remaining_row[] = $grand_remaining[ $month ] > 0 ? '€ ' . number_format( $grand_remaining[ $month ], 2 ) : '';
    }
    $remaining_row[] = $grand_remaining_total > 0 ? '€ ' . number_format( $grand_remaining_total, 2 ) : '';
    fputcsv( $output, $remaining_row );
}

// #endregion

// #region Custom List Export (Rooming / Vehicle / Shirt Size)

/**
 * Admin handler to export custom lists (rooming, vehicle, or shirt size)
 */
add_action('admin_post_bst_export_custom_list', 'bst_export_custom_list_handler');

function bst_export_custom_list_handler() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['export_nonce'], 'bst_export_custom_list')) {
        wp_die('Security check failed', 'Error', array('response' => 403));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    
    // Get parameters
    $list_type = isset($_POST['list_type']) ? trim($_POST['list_type']) : '';
    $filter_tour_id = isset($_POST['filter_tour_id']) ? intval($_POST['filter_tour_id']) : 0;
    $filter_tour_date_id = isset($_POST['filter_tour_date_id']) ? intval($_POST['filter_tour_date_id']) : 0;
    $filter_status = isset($_POST['filter_status']) ? trim($_POST['filter_status']) : '';
    $sort_by       = isset($_POST['sort_by']) ? $_POST['sort_by'] : 'id';
    $sort_order    = isset($_POST['sort_order']) ? $_POST['sort_order'] : 'desc';
    
    // Validate list type
    if (!in_array($list_type, array('rooming', 'vehicle', 'shirt', 'travel_details'), true)) {
        wp_die('Invalid list type', 'Error', array('response' => 400));
    }
    
    // Build the query with filters
    $where_conditions = array('1=1');
    $query_params = array();
    
    if ($filter_tour_id > 0) {
        $where_conditions[] = 'tour_id = %d';
        $query_params[] = $filter_tour_id;
    }
    
    // Always filter by tour_date_id (already validated above)
    $where_conditions[] = 'tour_date_id = %d';
    $query_params[] = $filter_tour_date_id;
    
    bst_export_apply_booking_status_filter( $filter_status, $where_conditions, $query_params );
    
    $where_clause  = implode(' AND ', $where_conditions );
    $order_clause  = bst_export_booking_list_order_clause( $sort_by, $sort_order );
    $query         = "SELECT * FROM $table_name WHERE $where_clause{$order_clause}";
    
    if (!empty($query_params)) {
        $bookings = $wpdb->get_results($wpdb->prepare($query, $query_params));
    } else {
        $bookings = $wpdb->get_results($query);
    }
    
    // Set up CSV output
    header('Content-Type: text/csv; charset=utf-8');
    $filename = $list_type . '_list_' . date('Y-m-d_His') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Generate CSV based on list type
    if ($list_type === 'rooming') {
        bst_export_rooming_list_csv($output, $bookings, $filter_tour_date_id);
    } elseif ($list_type === 'vehicle') {
        bst_export_vehicle_list_csv($output, $bookings, $filter_tour_date_id);
    } elseif ($list_type === 'travel_details') {
        bst_export_travel_details_list_csv($output, $bookings, $filter_tour_date_id);
    } else {
        bst_export_shirt_size_list_csv($output, $bookings, $filter_tour_date_id);
    }
    
    fclose($output);
    exit;
}

/**
 * Generate rooming list CSV
 */
function bst_export_rooming_list_csv($output, $bookings, $tour_date_id) {
    // Get tour info from tour_date_id
    $tour_name = '';
    $tour_dates = '';
    $tour_id = 0;
    
    if ($tour_date_id > 0) {
        $tour_date_post = get_post($tour_date_id);
        
        if ($tour_date_post) {
            // Get tour_id from ACF field 'tour' on the tour-date post
            $tour_id = get_field('tour', $tour_date_id);
            
            if ($tour_id) {
                $tour_post = get_post($tour_id);
                $tour_name = $tour_post ? $tour_post->post_title : '';
            }
            
            $start_date = get_field('start_date', $tour_date_id);
            $end_date = get_field('end_date', $tour_date_id);
            
            if ($start_date && $end_date) {
                $tour_dates = date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
            }
        }
    }
    
    // Check if tour offers extension
    $extension_offered = false;
    $extension_name = '';
    $extension_dates = '';
    
    if ($tour_id > 0) {
        $extension_offered = get_field('extension_offered', $tour_id);
        if ($extension_offered) {
            $extension_name = get_field('extension_title', $tour_id) ?: 'Extension';
        }
    }
    
    if ($tour_date_id > 0 && $extension_offered) {
        $extension_days = get_field('extension_driving_days', $tour_id);
        $tour_end_date = get_field('end_date', $tour_date_id);
        if ($extension_days && $tour_end_date) {
            // Extension starts on tour end date, not the day after
            $ext_start = date('j M Y', strtotime($tour_end_date));
            $ext_end = date('j M Y', strtotime($tour_end_date . ' +' . $extension_days . ' days'));
            $extension_dates = $ext_start . ' - ' . $ext_end;
        }
    }
    
    // Header section with tour info
    fputcsv($output, array('Tour:', $tour_name));
    fputcsv($output, array('Tour Dates:', $tour_dates));
    if ($extension_offered) {
        fputcsv($output, array('Extension:', $extension_name));
        fputcsv($output, array('Extension Dates:', $extension_dates));
    }
    fputcsv($output, array()); // Blank row
    
    // Column headers
    $headers = array(
        'Guest 1',
        'Guest 2',
        'Package',
        'Rooms',
        'Bed Preference',
        'Sex/Sharing Selection'
    );
    if ($extension_offered) {
        $headers[] = 'Extension';
    }
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($bookings as $booking) {
        // Note: Rooming list requires separate guest names in individual columns
        $guest1_name = trim($booking->guest1_first_name . ' ' . $booking->guest1_last_name);
        $guest2_name = trim($booking->guest2_first_name . ' ' . $booking->guest2_last_name);
        
        $row = array();
        $row[] = $guest1_name ?: '';
        $row[] = $guest2_name ?: '';
        $row[] = bst_export_live_package_name( $booking->tour_package_id );
        $row[] = !empty($booking->package_rooms) ? $booking->package_rooms : '';
        $row[] = !empty($booking->bed_preference) ? $booking->bed_preference : '';
        
        // Combine participant_sex and sharing_preference into proper English
        $sex_sharing = '';
        if (!empty($booking->participant_sex)) {
            $sex_sharing = 'A ' . $booking->participant_sex;
            if (!empty($booking->sharing_preference)) {
                $sharing = $booking->sharing_preference;
                // If sharing preference doesn't start with "with", add "willing to share with"
                if (stripos($sharing, 'with') !== 0) {
                    $sex_sharing .= ', willing to share with ' . strtolower($sharing);
                } else {
                    $sex_sharing .= ', willing to share ' . strtolower($sharing);
                }
            }
        } elseif (!empty($booking->sharing_preference)) {
            $sex_sharing = 'Willing to share ' . strtolower($booking->sharing_preference);
        }
        $row[] = $sex_sharing;
        
        // Extension added (only if extension is offered)
        if ($extension_offered) {
            $extension_added = (!empty($booking->tour_extension_added) && $booking->tour_extension_added == 1) ? 'X' : '';
            $row[] = $extension_added;
        }
        
        fputcsv($output, $row);
    }
}

/**
 * Generate vehicle list CSV
 */
function bst_export_vehicle_list_csv($output, $bookings, $tour_date_id) {
    // Get tour info from tour_date_id
    $tour_name = '';
    $tour_dates = '';
    $tour_id = 0;
    
    if ($tour_date_id > 0) {
        $tour_date_post = get_post($tour_date_id);
        
        if ($tour_date_post) {
            // Get tour_id from ACF field 'tour' on the tour-date post
            $tour_id = get_field('tour', $tour_date_id);
            
            if ($tour_id) {
                $tour_post = get_post($tour_id);
                $tour_name = $tour_post ? $tour_post->post_title : '';
            }
            
            $start_date = get_field('start_date', $tour_date_id);
            $end_date = get_field('end_date', $tour_date_id);
            
            if ($start_date && $end_date) {
                $tour_dates = date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
            }
        }
    }
    
    // Check if tour offers extension
    $extension_offered = false;
    $extension_name = '';
    $extension_dates = '';
    
    if ($tour_id > 0) {
        $extension_offered = get_field('extension_offered', $tour_id);
        if ($extension_offered) {
            $extension_name = get_field('extension_title', $tour_id) ?: 'Extension';
        }
    }
    
    if ($tour_date_id > 0 && $extension_offered) {
        $extension_days = get_field('extension_driving_days', $tour_id);
        $tour_end_date = get_field('end_date', $tour_date_id);
        if ($extension_days && $tour_end_date) {
            // Extension starts on tour end date, not the day after
            $ext_start = date('j M Y', strtotime($tour_end_date));
            $ext_end = date('j M Y', strtotime($tour_end_date . ' +' . $extension_days . ' days'));
            $extension_dates = $ext_start . ' - ' . $ext_end;
        }
    }
    
    // Header section with tour info
    fputcsv($output, array('Tour:', $tour_name));
    fputcsv($output, array('Tour Dates:', $tour_dates));
    if ($extension_offered) {
        fputcsv($output, array('Extension:', $extension_name));
        fputcsv($output, array('Extension Dates:', $extension_dates));
    }
    fputcsv($output, array()); // Blank row
    
    // Column headers
    $headers = array(
        'Guest 1',
        'Guest 2',
        'Package',
        'Vehicles',
        'Vehicle 1',
        'Vehicle 2'
    );
    if ($extension_offered) {
        $headers[] = 'Extension';
    }
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($bookings as $booking) {
        // Note: Vehicle list requires separate guest names in individual columns
        $guest1_name = trim($booking->guest1_first_name . ' ' . $booking->guest1_last_name);
        $guest2_name = trim($booking->guest2_first_name . ' ' . $booking->guest2_last_name);
        
        $row = array();
        $row[] = $guest1_name ?: '';
        $row[] = $guest2_name ?: '';
        $row[] = bst_export_live_package_name( $booking->tour_package_id );
        $row[] = !empty($booking->package_vehicles) ? $booking->package_vehicles : '';
        
        $vehicle1 = function_exists( 'bst_booking_vehicle_display_text' ) ? trim( (string) bst_booking_vehicle_display_text( $booking, 1 ) ) : '';
        $vehicle2 = function_exists( 'bst_booking_vehicle_display_text' ) ? trim( (string) bst_booking_vehicle_display_text( $booking, 2 ) ) : '';
        
        $row[] = $vehicle1;
        $row[] = $vehicle2;
        
        // Extension added (only if extension is offered)
        if ($extension_offered) {
            $extension_added = (!empty($booking->tour_extension_added) && $booking->tour_extension_added == 1) ? 'X' : '';
            $row[] = $extension_added;
        }
        
        fputcsv($output, $row);
    }
}

/**
 * Generate travel details list CSV (guest 1/2 name, phone, travel details; same tour header as rooming/vehicle).
 */
function bst_export_travel_details_list_csv( $output, $bookings, $tour_date_id ) {
    $tour_name   = '';
    $tour_dates  = '';
    $tour_id     = 0;

    if ( $tour_date_id > 0 ) {
        $tour_date_post = get_post( $tour_date_id );

        if ( $tour_date_post ) {
            $tour_id = get_field( 'tour', $tour_date_id );

            if ( $tour_id ) {
                $tour_post = get_post( $tour_id );
                $tour_name = $tour_post ? $tour_post->post_title : '';
            }

            $start_date = get_field( 'start_date', $tour_date_id );
            $end_date   = get_field( 'end_date', $tour_date_id );

            if ( $start_date && $end_date ) {
                $tour_dates = date( 'j M', strtotime( $start_date ) ) . ' - ' . date( 'j M Y', strtotime( $end_date ) );
            }
        }
    }

    $extension_offered = false;
    $extension_name    = '';
    $extension_dates   = '';

    if ( $tour_id > 0 ) {
        $extension_offered = get_field( 'extension_offered', $tour_id );
        if ( $extension_offered ) {
            $extension_name = get_field( 'extension_title', $tour_id ) ?: 'Extension';
        }
    }

    if ( $tour_date_id > 0 && $extension_offered ) {
        $extension_days = get_field( 'extension_driving_days', $tour_id );
        $tour_end_date  = get_field( 'end_date', $tour_date_id );
        if ( $extension_days && $tour_end_date ) {
            $ext_start = date( 'j M Y', strtotime( $tour_end_date ) );
            $ext_end   = date( 'j M Y', strtotime( $tour_end_date . ' +' . $extension_days . ' days' ) );
            $extension_dates = $ext_start . ' - ' . $ext_end;
        }
    }

    fputcsv( $output, array( 'Tour:', $tour_name ) );
    fputcsv( $output, array( 'Tour Dates:', $tour_dates ) );
    if ( $extension_offered ) {
        fputcsv( $output, array( 'Extension:', $extension_name ) );
        fputcsv( $output, array( 'Extension Dates:', $extension_dates ) );
    }
    fputcsv( $output, array() );

    fputcsv(
        $output,
        array(
            'Guest 1 name',
            'Guest 1 phone',
            'Guest 1 travel details',
            'Guest 2 name',
            'Guest 2 phone',
            'Guest 2 travel details',
        )
    );

    foreach ( $bookings as $booking ) {
        $g1 = trim( ( $booking->guest1_first_name ?? '' ) . ' ' . ( $booking->guest1_last_name ?? '' ) );
        $g2 = trim( ( $booking->guest2_first_name ?? '' ) . ' ' . ( $booking->guest2_last_name ?? '' ) );
        $p1_raw = isset( $booking->guest1_phone ) ? trim( (string) $booking->guest1_phone ) : '';
        $p2_raw = isset( $booking->guest2_phone ) ? trim( (string) $booking->guest2_phone ) : '';
        $p1     = ( '' !== $p1_raw && function_exists( 'bst_format_phone_international' ) ) ? bst_format_phone_international( $p1_raw ) : $p1_raw;
        $p2     = ( '' !== $p2_raw && function_exists( 'bst_format_phone_international' ) ) ? bst_format_phone_international( $p2_raw ) : $p2_raw;
        $t1 = isset( $booking->guest1_travel_details ) ? trim( (string) $booking->guest1_travel_details ) : '';
        $t2 = isset( $booking->guest2_travel_details ) ? trim( (string) $booking->guest2_travel_details ) : '';

        fputcsv(
            $output,
            array(
                $g1,
                $p1,
                $t1,
                $g2,
                $p2,
                $t2,
            )
        );
    }
}

/**
 * Generate shirt size list CSV (one row per guest: name + shirt size; same tour header as rooming/vehicle).
 */
function bst_export_shirt_size_list_csv( $output, $bookings, $tour_date_id ) {
    $tour_name   = '';
    $tour_dates  = '';
    $tour_id     = 0;

    if ( $tour_date_id > 0 ) {
        $tour_date_post = get_post( $tour_date_id );

        if ( $tour_date_post ) {
            $tour_id = get_field( 'tour', $tour_date_id );

            if ( $tour_id ) {
                $tour_post = get_post( $tour_id );
                $tour_name = $tour_post ? $tour_post->post_title : '';
            }

            $start_date = get_field( 'start_date', $tour_date_id );
            $end_date   = get_field( 'end_date', $tour_date_id );

            if ( $start_date && $end_date ) {
                $tour_dates = date( 'j M', strtotime( $start_date ) ) . ' - ' . date( 'j M Y', strtotime( $end_date ) );
            }
        }
    }

    $extension_offered = false;
    $extension_name    = '';
    $extension_dates   = '';

    if ( $tour_id > 0 ) {
        $extension_offered = get_field( 'extension_offered', $tour_id );
        if ( $extension_offered ) {
            $extension_name = get_field( 'extension_title', $tour_id ) ?: 'Extension';
        }
    }

    if ( $tour_date_id > 0 && $extension_offered ) {
        $extension_days = get_field( 'extension_driving_days', $tour_id );
        $tour_end_date  = get_field( 'end_date', $tour_date_id );
        if ( $extension_days && $tour_end_date ) {
            $ext_start = date( 'j M Y', strtotime( $tour_end_date ) );
            $ext_end   = date( 'j M Y', strtotime( $tour_end_date . ' +' . $extension_days . ' days' ) );
            $extension_dates = $ext_start . ' - ' . $ext_end;
        }
    }

    fputcsv( $output, array( 'Tour:', $tour_name ) );
    fputcsv( $output, array( 'Tour Dates:', $tour_dates ) );
    if ( $extension_offered ) {
        fputcsv( $output, array( 'Extension:', $extension_name ) );
        fputcsv( $output, array( 'Extension Dates:', $extension_dates ) );
    }
    fputcsv( $output, array() );

    fputcsv( $output, array( 'Guest Name', 'Shirt Size' ) );

    foreach ( $bookings as $booking ) {
        $g1 = trim( ( $booking->guest1_first_name ?? '' ) . ' ' . ( $booking->guest1_last_name ?? '' ) );
        $g2 = trim( ( $booking->guest2_first_name ?? '' ) . ' ' . ( $booking->guest2_last_name ?? '' ) );
        $s1 = isset( $booking->guest1_shirt_size ) ? trim( (string) $booking->guest1_shirt_size ) : '';
        $s2 = isset( $booking->guest2_shirt_size ) ? trim( (string) $booking->guest2_shirt_size ) : '';

        $guest1_row = ( '' !== $g1 || '' !== $s1 );
        $guest2_row = ( '' !== $g2 || '' !== $s2 );

        if ( $guest1_row ) {
            $size_disp = function_exists( 'bst_get_shirt_size_display' ) ? bst_get_shirt_size_display( $s1 ) : $s1;
            fputcsv( $output, array( $g1, $size_disp ) );
        }
        if ( $guest2_row ) {
            $size_disp2 = function_exists( 'bst_get_shirt_size_display' ) ? bst_get_shirt_size_display( $s2 ) : $s2;
            fputcsv( $output, array( $g2, $size_disp2 ) );
        }
    }
}

// #endregion

// Future export handlers can be added here as needed
// Example: Customer export, tour data export, etc.

