<?php
/**
 * XLSX Export Handler for BST Commission Reports
 * Creates Excel files with proper formatting and currency formatting
 */

// Include SimpleXLSXGen library
require_once BST_PLUGIN_DIR . 'includes/vendor/SimpleXLSXGen.php';

function bst_xlsx_live_tour_title( $tour_id ) {
    $tour_id = (int) $tour_id;
    if ( $tour_id <= 0 ) {
        return '';
    }
    $p = get_post( $tour_id );
    return ( $p && 'tour' === $p->post_type ) ? (string) $p->post_title : '';
}

/**
 * Enhanced commission export with proper formatting
 */
function bst_export_commission_bookings_xlsx_handler() {
    // Security checks
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    if (!wp_verify_nonce($_POST['export_nonce'], 'bst_export_commission_xlsx')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    
    // Candidate rows: uninvoiced commission fields on any payment line (filter by payment status in PHP via bst_calculate_commission_basis).
    $query = "SELECT * FROM {$table_name} WHERE
        (booking_commission_percent IS NOT NULL AND booking_commission_percent != 0)
        AND booking_status NOT IN ('Reserved', 'Waiting List')
        AND (
            (deposit_payment_amount > 0 AND (deposit_commission_invoice IS NULL OR deposit_commission_invoice = ''))
            OR (balance_payment_amount > 0 AND (balance_commission_invoice IS NULL OR balance_commission_invoice = ''))
            OR (additional_payment_amount > 0 AND (additional_payment_commission_invoice IS NULL OR additional_payment_commission_invoice = ''))
            OR (refund_payment_amount > 0 AND (refund_commission_invoice IS NULL OR refund_commission_invoice = ''))
        )";
    
    $bookings = $wpdb->get_results($query);
    
    if (!$bookings) {
        wp_redirect(admin_url('admin.php?page=bst-tour-bookings&export=error&message=no_data'));
        exit;
    }

    // Get USD to EUR rate directly from saved rate
    $usd_to_eur_rate = bst_get_exchange_rate('USD', 'EUR');
    
    // If not found, try the legacy approach or fallback
    if ($usd_to_eur_rate === false || $usd_to_eur_rate <= 0) {
        error_log('Warning: USD to EUR exchange rate not found in direct lookup, using fallback rate 0.8521');
        $usd_to_eur_rate = 0.8521; // Clean fallback rate
    }
    
    // Clean any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Prepare data for XLSX
    $xlsx_data = array();
    
    // Headers with bold formatting and font size
    $headers = array(
        '<style font-size="10"><b>ID</b></style>', 
        '<style font-size="10"><b>Name</b></style>', 
        '<style font-size="10"><b>Tour</b></style>', 
        '<style font-size="10"><b>Vehicle</b></style>', 
        '<style font-size="10"><b>Ttl Tour Cost</b></style>', 
        '<style font-size="10"><b>Total Paid</b></style>',
        '<style font-size="10"><b>Balance Due</b></style>', 
        '<style font-size="10"><b>Deposit Pmt</b></style>', 
        '<style font-size="10"><b>Balance Pmt</b></style>', 
        '<style font-size="10"><b>Add\'l Pmt</b></style>', 
        '<style font-size="10"><b>Commission Reason*</b></style>', 
        '<style font-size="10"><b>Motor Club</b></style>', 
        '<style font-size="10"><b>#</b></style>', 
        '<style font-size="10"><b>Basis</b></style>', 
        '<style font-size="10"><b>Euros</b></style>', 
        '<style font-size="10"><b>Commission</b></style>',
        '<style font-size="10"><b>deposit_commission_invoice</b></style>',
        '<style font-size="10"><b>balance_commission_invoice</b></style>',
        '<style font-size="10"><b>additional_payment_commission_invoice</b></style>',
        '<style font-size="10"><b>refund_commission_invoice</b></style>'
    );
    
    // Add exchange rate and date information at the top
    $exchange_rate = $usd_to_eur_rate;
    $xlsx_data[] = array(
        '<style font-size="10"><b>Rate</b></style>', 
        '<style font-size="10" nf="€#,##0.0000">' . $exchange_rate . '</style>', 
        '<style font-size="10"><b>USD to EUR Exchange Rate</b></style>', 
        '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''
    );
    $xlsx_data[] = array(
        '<style font-size="10"><b>Date</b></style>', 
        '<style font-size="10">' . date('Y-m-d') . '</style>', 
        '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''
    );
    $xlsx_data[] = array_fill(0, 20, ''); // Blank row
    
    // Add headers
    $xlsx_data[] = $headers;
    
    // Group bookings same as CSV export
    $grouped_bookings = array();
    
    foreach ($bookings as $booking) {
        $commission_basis = bst_calculate_commission_basis($booking, $usd_to_eur_rate);
        
        $is_refund_case = function_exists('bst_commission_refund_reduces_basis') && bst_commission_refund_reduces_basis($booking);

        // Skip bookings with no commission basis UNLESS it's a refund case (negative basis is OK for refunds)
        if ($commission_basis == 0 || ($commission_basis < 0 && !$is_refund_case)) {
            continue;
        }

        $commission_percent         = floatval($booking->booking_commission_percent ?? 0);
        $commission_percent_display = (int) round($commission_percent * 100);

        $nets_row = function_exists('bst_commission_uninvoiced_inflow_amounts')
            ? bst_commission_uninvoiced_inflow_amounts($booking)
            : array('deposit' => 0, 'balance' => 0, 'additional' => 0);
        $needs_dep = floatval($nets_row['deposit'] ?? 0) > 0;
        $needs_bal = floatval($nets_row['balance'] ?? 0) > 0;
        $needs_add = floatval($nets_row['additional'] ?? 0) > 0;

        $payments_on_same_invoice = $needs_dep && $needs_bal;

        $is_refund = $is_refund_case;

        // Group logic — payment status + invoice fields (no paper/offline split)
        if ($is_refund) {
            if ($commission_percent_display === 2) {
                $group_key  = 'H_2_Refunds';
                $group_name = '2% Bookings (Refund)';
            } elseif ($commission_percent_display === 5) {
                $group_key  = 'I_5_Refunds';
                $group_name = '5% Bookings (Refund)';
            } else {
                $group_key  = 'Z_Other_Refunds_' . $commission_percent_display;
                $group_name = $commission_percent_display . '% Bookings (Refund)';
            }
        } elseif ($commission_percent_display === 2) {
            if ($payments_on_same_invoice) {
                $group_key  = 'B_2_Total';
                $group_name = '2% Bookings (Total)';
            } elseif ($needs_dep && ! $needs_bal && ! $needs_add) {
                $group_key  = 'C_2_Deposits';
                $group_name = '2% Bookings (Deposits)';
            } elseif ($needs_dep && $needs_add && ! $needs_bal) {
                $group_key  = 'C2_2_Deposits_Additional';
                $group_name = '2% Bookings (Deposits + Additional)';
            } elseif ($needs_add && ! $needs_bal && ! $needs_dep) {
                $group_key  = 'D2_2_Additional';
                $group_name = '2% Bookings (Additional Payments)';
            } elseif ($needs_bal && $needs_add) {
                $group_key  = 'D3_2_Balance_Additional';
                $group_name = '2% Bookings (Balance + Additional)';
            } else {
                $group_key  = 'D_2_Balance';
                $group_name = '2% Bookings (Balance)';
            }
        } elseif ($commission_percent_display === 5) {
            if ($payments_on_same_invoice) {
                $group_key  = 'E_5_Total';
                $group_name = '5% Bookings (Total)';
            } elseif ($needs_dep && ! $needs_bal && ! $needs_add) {
                $group_key  = 'F_5_Deposits';
                $group_name = '5% Bookings (Deposits)';
            } elseif ($needs_dep && $needs_add && ! $needs_bal) {
                $group_key  = 'F2_5_Deposits_Additional';
                $group_name = '5% Bookings (Deposits + Additional)';
            } elseif ($needs_add && ! $needs_bal && ! $needs_dep) {
                $group_key  = 'G2_5_Additional';
                $group_name = '5% Bookings (Additional Payments)';
            } elseif ($needs_bal && $needs_add) {
                $group_key  = 'G3_5_Balance_Additional';
                $group_name = '5% Bookings (Balance + Additional)';
            } else {
                $group_key  = 'G_5_Balance';
                $group_name = '5% Bookings (Balance)';
            }
        } else {
            $group_key  = 'Z_Other_' . $commission_percent_display;
            $group_name = $commission_percent_display . '% Bookings';
        }
        
        if (!isset($grouped_bookings[$group_key])) {
            $grouped_bookings[$group_key] = array(
                'name' => $group_name,
                'bookings' => array()
            );
        }
        
        $grouped_bookings[$group_key]['bookings'][] = $booking;
    }
    
    ksort($grouped_bookings);
    
    // Track subtotal rows for grand total formulas
    $subtotal_rows = array();
    
    // Process each group
    foreach ($grouped_bookings as $group_key => $group_data) {
        // Sort bookings within group
        usort($group_data['bookings'], function($a, $b) {
            $tour_compare = strcasecmp(bst_xlsx_live_tour_title($a->tour_id ?? 0), bst_xlsx_live_tour_title($b->tour_id ?? 0));
            if ($tour_compare !== 0) return $tour_compare;
            
            $name_compare = strcasecmp($a->guest1_last_name, $b->guest1_last_name);
            if ($name_compare !== 0) return $name_compare;
            
            return strcasecmp($a->guest1_first_name, $b->guest1_first_name);
        });
        
        // Track row numbers for formulas - calculate correctly
        $group_start_row = count($xlsx_data) + 1; // +1 because Excel is 1-based (headers already in array, so next row is count+1)
        
        // Add bookings and track the actual range
        $bookings_added = 0;
        foreach ($group_data['bookings'] as $booking) {
            $row = bst_get_commission_booking_row_data_xlsx($booking, $usd_to_eur_rate);
            $xlsx_data[] = $row;
            $bookings_added++;
        }
        
        $group_end_row = $group_start_row + $bookings_added - 1; // Last data row (not subtotal row)
        
        // Add group subtotal row with formulas
        $group_name_clean = $group_data['name'];
        $group_subtotal_row = array_fill(0, 19, '');
        $group_subtotal_row[11] = '<style font-size="10"><b><right>' . $group_name_clean . '</right></b></style>'; // Group name in column L, bold and right-aligned
        
        // Add formulas for this group with currency formatting (use proper range)
        // Note: Using EUR format for subtotals since they mix currencies - will need manual formatting for mixed currency groups
        $group_subtotal_row[12] = '<style font-size="10"><b><f>=COUNTA(N' . $group_start_row . ':N' . $group_end_row . ')</f></b></style>'; // Count of bookings (same range as basis)
        $group_subtotal_row[13] = '<style font-size="10"><b></b></style>'; // Basis - no sum since mixed currencies
        $group_subtotal_row[14] = '<style font-size="10" nf="€#,##0.00"><b><f>=SUM(O' . $group_start_row . ':O' . $group_end_row . ')</f></b></style>'; // Euros  
        $group_subtotal_row[15] = '<style font-size="10" nf="€#,##0.00"><b><f>=SUM(P' . $group_start_row . ':P' . $group_end_row . ')</f></b></style>'; // Commission
        
        $xlsx_data[] = $group_subtotal_row;
        
        // Track this subtotal row for grand total calculations
        $subtotal_rows[] = count($xlsx_data); // This row was just added, so count($xlsx_data) is the correct Excel row number
        
        // Add blank row
        $xlsx_data[] = array_fill(0, 19, '');
    }
    
    // Calculate Invoice Total row with formulas
    $current_row = count($xlsx_data) + 2; // +2 for Excel indexing
    $grand_total_row = array_fill(0, 19, '');
    $grand_total_row[10] = '<style font-size="10"><b><right>Invoice Total</right></b></style>'; // Column K, bold and right-aligned
    
    // Create SUM formulas for Basis, Euros, and Commission columns
    // We need to sum all data rows, excluding group headers and blank rows
    $data_rows = array();
    $row_num = 2; // Start after headers
    foreach ($grouped_bookings as $group_data) {
        foreach ($group_data['bookings'] as $booking) {
            $data_rows[] = $row_num;
            $row_num++;
        }
        $row_num += 2; // Skip group header and blank row
    }
    
    // Create range strings for formulas
    if (!empty($data_rows)) {
        $ranges = array();
        $start = $data_rows[0];
        $end = $data_rows[0];
        
        for ($i = 1; $i < count($data_rows); $i++) {
            if ($data_rows[$i] == $end + 1) {
                $end = $data_rows[$i];
            } else {
                $ranges[] = "M{$start}:M{$end}";
                $start = $end = $data_rows[$i];
            }
        }
        $ranges[] = "N{$start}:N{$end}";
        
        $basis_formula = "=SUM(" . implode(",", $ranges) . ")";
        
        // Create formulas to sum the subtotal rows (same approach as count)
        $euros_refs = array_map(function($row) { return 'O' . $row; }, $subtotal_rows);
        $euros_formula = '=SUM(' . implode(',', $euros_refs) . ')';
        
        $commission_refs = array_map(function($row) { return 'P' . $row; }, $subtotal_rows);
        $commission_formula = '=SUM(' . implode(',', $commission_refs) . ')';
        
        // Create count formula - sum all the count cells from subtotal rows
        $count_formula = '';
        if (count($subtotal_rows) > 0) {
            $count_refs = array_map(function($row) { return 'M' . $row; }, $subtotal_rows);
            $count_formula = '=SUM(' . implode(',', $count_refs) . ')';
        }
        
        // Add grand total formulas 
        $grand_total_row[12] = $count_formula ? '<style font-size="10"><b><f>' . $count_formula . '</f></b></style>' : '<style font-size="10"></style>'; // Sum of counts
        $grand_total_row[13] = '<style font-size="10"></style>'; // Leave basis column blank (mixed currencies)
        $grand_total_row[14] = '<style font-size="10" nf="€#,##0.00"><b><f>' . $euros_formula . '</f></b></style>'; 
        $grand_total_row[15] = '<style font-size="10" nf="€#,##0.00"><b><f>' . $commission_formula . '</f></b></style>';
    }
    
    // Add legend header to the grand total row itself (* in column A, header in column B)
    $grand_total_row[0] = '<style font-size="10"><center>*</center></style>';
    $grand_total_row[1] = '<style font-size="10"><b>Legend for Commission Reason</b></style>';
    
    $xlsx_data[] = $grand_total_row;
    
    // Add legend entries
    $xlsx_data[] = array('', '<style font-size="10">Bill Paper Booking: those bookings sent to Bill on paper that I enter manually</style>', '', '', '', '', '', '', '', '', '', '', '', '', '');
    $xlsx_data[] = array('', '<style font-size="10">Bill Customer: those existing customers from Claudio\'s "All Customers" list that I imported, or booked a previous tour credited to Bill (Paper, How Heard, Source)</style>', '', '', '', '', '', '', '', '', '', '', '', '', '');
    $xlsx_data[] = array('', '<style font-size="10">Claudio Customer: existing customer that booked a previous tour credited to Claudio (How Heard, Source)</style>', '', '', '', '', '', '', '', '', '', '', '', '', '');
    $xlsx_data[] = array('', '<style font-size="10">Wayne Customer: existing customer that booked a previous tour credited to Wayne (How Heard, Source)</style>', '', '', '', '', '', '', '', '', '', '', '', '', '');
    $xlsx_data[] = array('', '<style font-size="10">How Heard: New Web Customer credited to Bill, Claudio or Wayne based on the How Heard Field on the booking form</style>', '', '', '', '', '', '', '', '', '', '', '', '', '');
    $xlsx_data[] = array('', '<style font-size="10">Source: New Web Customer credited to Bill, Claudio or Wayne based on the source in links that bring people to site</style>', '', '', '', '', '', '', '', '', '', '', '', '', '');
    $xlsx_data[] = array('', '<style font-size="10">Existing Web Customer: Customer was already marked as a Web Customer when they booked a previous tour</style>', '', '', '', '', '', '', '', '', '', '', '', '', '');
    $xlsx_data[] = array('', '<style font-size="10">New Web Customer: None of the above, so 5%</style>', '', '', '', '', '', '', '', '', '', '', '', '', '');
    
    // Create XLSX
    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($xlsx_data);
    
    // Set headers for download
    $filename = 'commission_bookings_' . date('Y-m-d_H-i-s') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $xlsx;
    exit;
}

/**
 * Helper function to get booking row data as array for XLSX
 */
function bst_get_commission_booking_row_data_xlsx($booking, $exchange_rate) {
    // Validate exchange rate - throw error if invalid
    if (!$exchange_rate || $exchange_rate <= 0) {
        wp_die('Error: Invalid exchange rate passed to booking calculation function: ' . $exchange_rate);
    }
    // Extract data similar to CSV export but return raw numbers for Excel formatting
    $guest_name = trim(($booking->guest1_first_name ?? '') . ' ' . ($booking->guest1_last_name ?? ''));
    
    $v1x = function_exists( 'bst_booking_vehicle_display_text' ) ? trim( (string) bst_booking_vehicle_display_text( $booking, 1 ) ) : '';
    $v2x = function_exists( 'bst_booking_vehicle_display_text' ) ? trim( (string) bst_booking_vehicle_display_text( $booking, 2 ) ) : '';
    $vehicle_text = $v1x;
    if ( $v2x !== '' && $v2x !== $v1x ) {
        $vehicle_text .= ( $vehicle_text !== '' ? ', ' : '' ) . $v2x;
    }
    
    // Use actual booking currency (not always EUR)
    $currency = strtoupper(trim($booking->tour_currency ?? 'EUR'));
    
    $original_basis = bst_get_original_commission_basis($booking);
    $eur_basis      = bst_calculate_commission_basis($booking, $exchange_rate);
    
    $commission_percent = floatval($booking->booking_commission_percent ?? 0);
    $commission_amount = round($eur_basis * $commission_percent, 2);
    
    // Calculate "Ttl Tour Cost = tour price - coupon + add'l charge" (same as CSV export)
    $tour_price = floatval($booking->tour_price ?? 0);
    $coupon_amount = floatval($booking->coupon_amount ?? 0);
    $additional_charge = floatval($booking->additional_charge ?? 0);
    $tour_cost = $tour_price - $coupon_amount + $additional_charge;
    
    if ($currency === 'USD') {
        // Keep USD amounts in USD for display
        $tour_cost_display = $tour_cost;
        $total_paid_display = floatval($booking->total_paid ?? 0);
        $balance_due_display = floatval($booking->balance_due ?? 0);
        $deposit_pmt_display = floatval($booking->deposit_payment_amount ?? 0);
        $balance_pmt_display = floatval($booking->balance_payment_amount ?? 0);
        $commission_basis_display = $original_basis;    // USD basis
        $commission_euros_display = $eur_basis;         // EUR converted amount
        $commission_amount_display = $commission_amount; // EUR commission
        
        // Use USD formatting for basis, EUR for euros and commission
        $basis_format = '$#,##0.00';     // USD format for Basis column
        $euros_format = '€#,##0.00';     // EUR format for Euros column
        $commission_format = '€#,##0.00'; // EUR format for Commission column
        $currency_format = '$#,##0.00';  // USD format for other columns
    } else {
        // EUR amounts
        $tour_cost_display = $tour_cost;
        $total_paid_display = floatval($booking->total_paid ?? 0);
        $balance_due_display = floatval($booking->balance_due ?? 0);
        $deposit_pmt_display = floatval($booking->deposit_payment_amount ?? 0);
        $balance_pmt_display = floatval($booking->balance_payment_amount ?? 0);
        $commission_basis_display = $original_basis;    // EUR basis
        $commission_euros_display = $eur_basis;         // Same as basis for EUR bookings
        $commission_amount_display = $commission_amount;
        
        // Use EUR formatting for all columns
        $basis_format = '€#,##0.00';
        $euros_format = '€#,##0.00';
        $commission_format = '€#,##0.00';
        $currency_format = '€#,##0.00';
    }
    
    $tour_title = bst_xlsx_live_tour_title($booking->tour_id ?? 0);

    return array(
        '<style font-size="10">' . $booking->id . '</style>',
        '<style font-size="10">' . $guest_name . '</style>',
        '<style font-size="10">' . $tour_title . '</style>',
        '<style font-size="10">' . $vehicle_text . '</style>',
        '<style font-size="10" nf="' . $currency_format . '">' . $tour_cost_display . '</style>',
        '<style font-size="10" nf="' . $currency_format . '">' . $total_paid_display . '</style>',
        '<style font-size="10" nf="' . $currency_format . '">' . $balance_due_display . '</style>',
        $deposit_pmt_display > 0 ? '<style font-size="10" nf="' . $currency_format . '">' . $deposit_pmt_display . '</style>' : '<style font-size="10"></style>',
        $balance_pmt_display > 0 ? '<style font-size="10" nf="' . $currency_format . '">' . $balance_pmt_display . '</style>' : '<style font-size="10"></style>',
        floatval($booking->additional_payment_amount ?? 0) > 0 ? '<style font-size="10" nf="' . $currency_format . '">' . floatval($booking->additional_payment_amount ?? 0) . '</style>' : '<style font-size="10"></style>',
        '<style font-size="10">' . ($booking->booking_commission_reason ?? '') . '</style>',
        '<style font-size="10">' . ($booking->motor_club ?? '') . '</style>',
        '<style font-size="10"></style>', // Empty count column for individual rows
        '<style font-size="10" nf="' . $basis_format . '">' . $commission_basis_display . '</style>',
        '<style font-size="10" nf="' . $euros_format . '">' . $commission_euros_display . '</style>', // Euros column with proper conversion
        '<style font-size="10" nf="' . $commission_format . '">' . $commission_amount_display . '</style>',
        '<style font-size="10">' . ($booking->deposit_commission_invoice ?? '') . '</style>',
        '<style font-size="10">' . ($booking->balance_commission_invoice ?? '') . '</style>',
        '<style font-size="10">' . ($booking->additional_payment_commission_invoice ?? '') . '</style>',
        '<style font-size="10">' . ($booking->refund_commission_invoice ?? '') . '</style>'
    );
}

// Add the XLSX export action
add_action('admin_post_bst_export_commission_bookings_xlsx', 'bst_export_commission_bookings_xlsx_handler');
