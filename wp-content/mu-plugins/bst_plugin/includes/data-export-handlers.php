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
        return " ORDER BY tour_text {$sort_ord}";
    }
    $allowed = array( 'id', 'guest1_first_name', 'guest1_last_name', 'tour_text', 'tour_date_text', 'booking_status', 'created_date' );
    if ( in_array( $sort_by, $allowed, true ) ) {
        return " ORDER BY {$sort_by} {$sort_ord}";
    }
    // Same fallback as list when column is unknown: avoid unbounded results order.
    return " ORDER BY id {$sort_ord}";
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
            $booking->tour_text,
            $booking->tour_date_id,
            $booking->tour_date_text,
            $booking->tour_package_id,
            $booking->tour_package_text,
            $booking->vehicle1,
            $booking->vehicle2,
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
        $tour_label = $booking->tour_text;
        $tour_date_id = $booking->tour_date_id;
        $tour_date_text = $booking->tour_date_text;
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
        $tour = $tour_label . ($paren ? ' (' . $paren . ')' : '') . ' - ' . $booking->tour_package_text;
        
        // Build Vehicles field with + delimiter
        $vehicles = trim($booking->vehicle1 ?? '');
        if (!empty($booking->vehicle2)) {
            $vehicles .= ($vehicles ? ' + ' : '') . trim($booking->vehicle2);
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
    
    // Generate CSV output
    bst_write_annual_commission_csv($output, $commission_data, $year);
    
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
    
    return $commission_data;
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
                $m = 1;
            }
            if ( 'ready' === $bucket && ! empty( $date ) && date( 'Y-m-d', strtotime( $date ) ) > date( 'Y-m-d' ) ) {
                $bucket = 'uninvoiced';
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

    $ref_amt = floatval( $booking->refund_payment_amount ?? 0 ) * $fx;
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
            b.tour_text,
            b.tour_date_id,
            b.tour_date_text,
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
 */
function bst_write_annual_commission_csv($output, $commission_data, $year) {
    // Create headers
    $headers = array('Group');
    $month_names = array('', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
    
    // Add month columns (one column per month)
    for ($month = 1; $month <= 12; $month++) {
        $headers[] = $month_names[$month];
    }
    $headers[] = 'Total';
    
    fputcsv($output, array("Annual Commission Summary - {$year}"));
    fputcsv($output, array());
    fputcsv($output, $headers);
    
    // For the current year: uninvoiced amounts in past months get rolled forward into the
    // current month, because invoices go out on the 1st covering the prior month's activity.
    // Any uninvoiced item from a past month will be picked up on the next invoice (1st of next month).
    $current_year  = intval(date('Y'));
    $current_month = intval(date('n'));
    
    if (intval($year) === $current_year) {
        foreach ($commission_data as $group_key => &$group_data) {
            // Roll ALL ready amounts from every month (past and future) into the current month.
            // "Ready to invoice" means it goes on the next invoice regardless of payment date.
            // Uninvoiced (projected) amounts stay in their projected month.
            for ($month = 1; $month <= 12; $month++) {
                if ($month === $current_month) continue;
                $ready = $group_data['months'][$month]['ready'];
                if ($ready != 0) {
                    $group_data['months'][$current_month]['ready'] += $ready;
                    $group_data['months'][$month]['ready'] = 0;
                }
                // Past-due uninvoiced amounts (due date already passed, still unpaid) roll forward too.
                if ($month < $current_month) {
                    $uninvoiced = $group_data['months'][$month]['uninvoiced'];
                    if ($uninvoiced != 0) {
                        $group_data['months'][$current_month]['uninvoiced'] += $uninvoiced;
                        $group_data['months'][$month]['uninvoiced'] = 0;
                    }
                }
            }
        }
        unset($group_data);
    }
    
    $grand_totals    = array_fill(1, 12, 0);
    $grand_invoiced  = array_fill(1, 12, 0);
    $grand_ready     = array_fill(1, 12, 0);
    $grand_remaining = array_fill(1, 12, 0);
    $grand_total           = 0;
    $grand_invoiced_total  = 0;
    $grand_ready_total     = 0;
    $grand_remaining_total = 0;
    
    foreach ($commission_data as $group_key => $group_data) {
        $row = array($group_data['name']);
        $group_total = 0;
        
        // Add monthly data (combine invoiced + ready + uninvoiced)
        for ($month = 1; $month <= 12; $month++) {
            $month_total = $group_data['months'][$month]['invoiced']
                         + $group_data['months'][$month]['ready']
                         + $group_data['months'][$month]['uninvoiced'];
            
            $row[] = $month_total > 0 ? '€ ' . number_format($month_total, 2) : '';
            
            $group_total += $month_total;
            $grand_totals[$month]    += $month_total;
            $grand_invoiced[$month]  += $group_data['months'][$month]['invoiced'];
            $grand_ready[$month]     += $group_data['months'][$month]['ready'];
            $grand_remaining[$month] += $group_data['months'][$month]['uninvoiced'];
        }
        
        $row[] = $group_total > 0 ? '€ ' . number_format($group_total, 2) : '';
        $grand_total += $group_total;
        
        fputcsv($output, $row);
    }
    
    // Add blank row before totals
    fputcsv($output, array());
    
    // TOTALS row
    $totals_row = array('TOTALS');
    for ($month = 1; $month <= 12; $month++) {
        $totals_row[] = $grand_totals[$month] > 0 ? '€ ' . number_format($grand_totals[$month], 2) : '';
        $grand_invoiced_total  += $grand_invoiced[$month];
        $grand_ready_total     += $grand_ready[$month];
        $grand_remaining_total += $grand_remaining[$month];
    }
    $totals_row[] = $grand_total > 0 ? '€ ' . number_format($grand_total, 2) : '';
    fputcsv($output, $totals_row);
    
    // Invoiced row
    $invoiced_row = array('Invoiced');
    for ($month = 1; $month <= 12; $month++) {
        $invoiced_row[] = $grand_invoiced[$month] > 0 ? '€ ' . number_format($grand_invoiced[$month], 2) : '';
    }
    $invoiced_row[] = $grand_invoiced_total > 0 ? '€ ' . number_format($grand_invoiced_total, 2) : '';
    fputcsv($output, $invoiced_row);
    
    // Ready to Invoice row — only meaningful for the current month of the current year
    // (all past ready amounts are already rolled forward into current month by the roll-forward logic above)
    $ready_row = array('Ready to Invoice');
    for ($month = 1; $month <= 12; $month++) {
        $show = (intval($year) === $current_year && $month === $current_month);
        $ready_row[] = ($show && $grand_ready[$month] > 0) ? '€ ' . number_format($grand_ready[$month], 2) : '';
    }
    $ready_row[] = $grand_ready_total > 0 ? '€ ' . number_format($grand_ready_total, 2) : '';
    fputcsv($output, $ready_row);
    
    // Remaining row (projected amounts by due date — not yet received)
    $remaining_row = array('Remaining');
    for ($month = 1; $month <= 12; $month++) {
        $remaining_row[] = $grand_remaining[$month] > 0 ? '€ ' . number_format($grand_remaining[$month], 2) : '';
    }
    $remaining_row[] = $grand_remaining_total > 0 ? '€ ' . number_format($grand_remaining_total, 2) : '';
    fputcsv($output, $remaining_row);
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
        $row[] = !empty($booking->tour_package_text) ? $booking->tour_package_text : '';
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
        $row[] = !empty($booking->tour_package_text) ? $booking->tour_package_text : '';
        $row[] = !empty($booking->package_vehicles) ? $booking->package_vehicles : '';
        
        // Strip pricing from vehicle names (remove last set of parentheses)
        $vehicle1 = !empty($booking->vehicle1) ? preg_replace('/\s*\([^)]*\)\s*$/', '', $booking->vehicle1) : '';
        $vehicle2 = !empty($booking->vehicle2) ? preg_replace('/\s*\([^)]*\)\s*$/', '', $booking->vehicle2) : '';
        $vehicle1 = trim($vehicle1);
        $vehicle2 = trim($vehicle2);
        
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

