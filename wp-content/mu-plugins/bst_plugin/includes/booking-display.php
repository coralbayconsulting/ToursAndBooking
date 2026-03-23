<?php
/**
 * Booking Display Shortcodes
 * 
 * Provides customer-facing shortcodes for displaying booking confirmation
 * and booking status information.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if ( defined( 'BST_PLUGIN_DIR' ) && ! function_exists( 'bst_payment_line_received_for_display' ) ) {
    require_once BST_PLUGIN_DIR . 'includes/booking-payment-status.php';
}

// Note: All styles are inline in the HTML generation functions below
// for email compatibility and consistent rendering. No separate CSS file needed.

// --- ID Obfuscation Functions ---
// Encode booking/entry IDs for URLs to prevent enumeration
function bst_encode_booking_id($id) {
    if (empty($id) || !is_numeric($id)) {
        return '';
    }
    
    // Secret key for XOR encryption (change this to a unique value)
    $secret_key = 0x2BA7;
    
    // XOR the ID with the secret key
    $encoded_num = intval($id) ^ $secret_key;
    
    // Convert to base62 (0-9, a-z, A-Z)
    $base62_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $base62 = '';
    
    while ($encoded_num > 0) {
        $base62 = $base62_chars[$encoded_num % 62] . $base62;
        $encoded_num = floor($encoded_num / 62);
    }
    
    // Handle zero case
    if (empty($base62)) {
        $base62 = '0';
    }
    
    return $base62;
}

// Decode obfuscated booking/entry IDs from URLs
function bst_decode_booking_id($encoded) {
    if (empty($encoded) || !is_string($encoded)) {
        return 0;
    }
    
    // Base62 character set
    $base62_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    // Convert from base62 to integer
    $num = 0;
    $len = strlen($encoded);
    for ($i = 0; $i < $len; $i++) {
        $char = $encoded[$i];
        $pos = strpos($base62_chars, $char);
        if ($pos === false) {
            // Invalid character, return 0
            return 0;
        }
        $num = $num * 62 + $pos;
    }
    
    // XOR with the same secret key to decode
    $secret_key = 0x2BA7;
    $decoded_id = $num ^ $secret_key;
    
    return intval($decoded_id);
}

/**
 * Generate finalization URL with encoded booking ID
 * 
 * @param int $booking_id The booking ID to encode
 * @return string The finalization URL with encoded bid parameter
 */
function bst_get_finalization_url($booking_id) {
    $encoded_bid = bst_encode_booking_id($booking_id);
    return site_url('/booking-finalization/') . '?bid=' . $encoded_bid;
}

/**
 * Generate reservation URL with encoded booking ID
 * 
 * @param int $booking_id The booking ID to encode
 * @return string The reservation URL with encoded bid parameter
 */
function bst_get_reservation_url($booking_id) {
    $encoded_bid = bst_encode_booking_id($booking_id);
    return site_url('/tour-booking/') . '?bid=' . $encoded_bid;
}

/**
 * Format guest name(s) consistently across all displays
 * 
 * @param string $guest1_first First guest's first name
 * @param string $guest1_last First guest's last name
 * @param string $guest2_first Second guest's first name (optional)
 * @param string $guest2_last Second guest's last name (optional)
 * @return string Formatted guest name(s)
 */
function bst_format_guest_name($guest1_first, $guest1_last, $guest2_first = '', $guest2_last = '') {
    if (empty($guest2_first)) {
        // Single guest
        return $guest1_first . ' ' . $guest1_last;
    } else {
        // Couple - check if they share the same last name
        if (empty($guest2_last) || $guest1_last === $guest2_last) {
            return $guest1_first . ' & ' . $guest2_first . ' ' . $guest1_last;
        } else {
            return $guest1_first . ' ' . $guest1_last . ' & ' . $guest2_first . ' ' . $guest2_last;
        }
    }
}

/**
 * Get currency symbol for a given currency code
 * 
 * @param string $currency_code The currency code (EUR, USD, GBP)
 * @return string The currency symbol (€, $, £)
 */
function bst_get_currency_symbol($currency_code) {
    switch ($currency_code) {
        case 'EUR':
            return '€';
        case 'USD':
            return '$';
        case 'GBP':
            return '£';
        default:
            return '€'; // Default to Euro
    }
}

/**
 * Parse balance payment deadline from registration terms text
 * 
 * @param string $terms_text The full registration terms text
 * @return array Array with 'value' => number, 'unit' => 'days' or 'months', 'clause' => matching text
 */
function bst_parse_balance_deadline($terms_text) {
    $result = array(
        'value' => null,
        'unit' => '',
        'clause' => ''
    );
    
    if (empty($terms_text)) {
        return $result;
    }
    
    // Pattern to match various formats:
    // "due sixty days before", "due 60 days before", "due three months before", "due 3 months before"
    $patterns = array(
        // Match: "balance payment, due [number/word] days/months before"
        '/balance payment[^.]*?due\s+([a-z]+|\d+)\s+(day|month)s?\s+before/i',
        // Match: "due [number/word] days/months before" (more general)
        '/\bdue\s+([a-z]+|\d+)\s+(day|month)s?\s+before\s+(?:the\s+)?(?:tour|event)/i'
    );
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $terms_text, $matches)) {
            $number_str = strtolower(trim($matches[1]));
            $unit = strtolower($matches[2]);
            
            // Convert word numbers to digits
            $word_to_number = array(
                'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
                'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
                'fifteen' => 15, 'thirty' => 30, 'forty' => 40, 'forty-five' => 45,
                'fifty' => 50, 'sixty' => 60, 'seventy' => 70, 'eighty' => 80,
                'ninety' => 90, 'hundred' => 100, 'one hundred twenty' => 120
            );
            
            if (isset($word_to_number[$number_str])) {
                $number = $word_to_number[$number_str];
            } elseif (is_numeric($number_str)) {
                $number = intval($number_str);
            } else {
                continue; // Try next pattern
            }
            
            // Extract the full clause for display
            $clause_pattern = '/[^.]*balance payment[^.]*\./i';
            if (preg_match($clause_pattern, $terms_text, $clause_matches)) {
                $result['clause'] = trim($clause_matches[0]);
            } else {
                $result['clause'] = $matches[0];
            }
            
            $result['value'] = $number;
            $result['unit'] = $unit . 's';
            break; // Found a match, stop looking
        }
    }
    
    return $result;
}

/**
 * Calculate balance due date for a booking
 * 
 * @param object $booking The booking object
 * @return string The formatted balance due date (Y-m-d) or empty string if cannot be calculated
 */
function bst_calculate_balance_due_date($booking) {
    if (empty($booking)) {
        return '';
    }
    
    // Get the entry to access registration terms
    $entry_id = !empty($booking->booking_entry_id) ? $booking->booking_entry_id : $booking->finalization_entry_id;
    if (empty($entry_id)) {
        return '';
    }
    
    $entry = GFAPI::get_entry($entry_id);
    if (!$entry || is_wp_error($entry)) {
        return '';
    }
    
    $form_id = intval($entry['form_id']);
    
    // Default for form 4 (legacy) - always use 60 days regardless of field 36 content
    $payment_deadline = array('value' => null, 'unit' => null);
    if ($form_id === 4) {
        $payment_deadline = array('value' => 60, 'unit' => 'days');
    } else {
        // For other forms, try to parse from registration terms field
        $reg_terms_field = GFAPI::get_field($form_id, '36');
        $reg_terms_consented = rgar($entry, '36.1');
        $reg_terms_text = '';
        
        if ($reg_terms_field && $reg_terms_consented) {
            $reg_terms_text = $reg_terms_field->get_value_export($entry, '36.3');
        }
        
        if (!empty($reg_terms_text)) {
            $payment_deadline = bst_parse_balance_deadline($reg_terms_text);
        }
    }
    
    // Get tour start date from booking's tour_date_id
    if (!empty($booking->tour_date_id) && $payment_deadline['value']) {
        $tour_date_id = intval(explode('|', $booking->tour_date_id)[0]);
        $start_date_str = get_field('start_date', $tour_date_id);
        
        if (!empty($start_date_str)) {
            $tour_start_date = strtotime($start_date_str);
            
            if ($tour_start_date) {
                // Calculate balance due date using calendar months or days
                if ($payment_deadline['unit'] === 'months') {
                    $balance_due_timestamp = strtotime('-' . $payment_deadline['value'] . ' months', $tour_start_date);
                } else {
                    $balance_due_timestamp = strtotime('-' . $payment_deadline['value'] . ' days', $tour_start_date);
                }
                
                if ($balance_due_timestamp) {
                    return date('Y-m-d', $balance_due_timestamp);
                }
            }
        }
    }
    
    return '';
}

/**
 * Get card type for a given entry
 * Checks Stripe field first, then falls back to wp_bst_paypal_payments table for old PayPal data
 * 
 * @param array $entry The Gravity Forms entry
 * @param int $form_id The form ID (9 or 10) to determine correct Stripe field
 * @return string The card type (e.g., 'Visa', 'Mastercard') or empty string
 */
function bst_get_card_type($entry, $form_id = null) {
    if (empty($entry) || empty($entry['id'])) {
        return '';
    }
    
    // Determine Stripe field based on form ID
    $stripe_field = '228.4'; // Default for form 9 (payment method type)
    $stripe_brand_field = '228.5'; // Card brand subfield
    if ($form_id == 10) {
        $stripe_field = '281.4';
        $stripe_brand_field = '281.5';
    } elseif (empty($form_id) && !empty($entry['form_id'])) {
        // Use entry's form_id if not provided
        if ($entry['form_id'] == 10) {
            $stripe_field = '281.4';
            $stripe_brand_field = '281.5';
        }
    }
    
    // Try Stripe field first (payment method type)
    $card_type = rgar($entry, $stripe_field);
    
    // If it's "Link", also check for the actual card brand
    if ($card_type === 'Link') {
        $card_brand = rgar($entry, $stripe_brand_field);
        if (!empty($card_brand)) {
            $card_type = $card_brand;
        }
    }
    
    // If Stripe card type is empty, check wp_bst_paypal_payments table for old PayPal data
    if (empty($card_type)) {
        global $wpdb;
        $paypal_data = $wpdb->get_row($wpdb->prepare(
            "SELECT card_type FROM {$wpdb->prefix}bst_paypal_payments WHERE entry_id = %d",
            $entry['id']
        ));
        
        if ($paypal_data && !empty($paypal_data->card_type)) {
            $card_type = $paypal_data->card_type;
        }
    }
    
    return $card_type;
}

// Shared function to generate booking details table (used by both confirmation page and email merge tags)
function bst_get_booking_details_data($booking, $entry) {
    // Build the guest name using helper
    $guest_name = bst_format_guest_name(
        $booking->guest1_first_name,
        $booking->guest1_last_name,
        $booking->guest2_first_name ?? '',
        $booking->guest2_last_name ?? ''
    );
    
    // Format currency symbol using helper
    $currency_symbol = bst_get_currency_symbol($booking->tour_currency);
    
    // Get payment method
    $payment_method = !empty($booking->deposit_payment_method) ? $booking->deposit_payment_method : 'N/A';
    
    // Add card type for Credit Card payments
    if ($payment_method === 'Credit Card' && $entry) {
        $card_type = bst_get_card_type($entry, rgar($entry, 'form_id'));
        
        if (!empty($card_type)) {
            $payment_method .= ' (' . ucwords($card_type) . ')';
        }
    }
    
    // Check if we have coupon info
    $has_coupon = !empty($booking->coupon_code) && !empty($booking->coupon_amount) && floatval($booking->coupon_amount) > 0;
    
    // Check if extension was added
    $has_extension = !empty($booking->tour_extension_added) && $booking->tour_extension_added == 1;
    
    // Get deposit amount for Bank Wire payments
    $deposit_eur = 0;
    if ($payment_method === 'Bank Wire' && $entry) {
        $deposit_eur = floatval(rgar($entry, '177'));
    }
    
    // Return all the data needed for display
    return array(
        'guest_name' => $guest_name,
        'currency_symbol' => $currency_symbol,
        'payment_method' => $payment_method,
        'has_coupon' => $has_coupon,
        'has_extension' => $has_extension,
        'deposit_eur' => $deposit_eur,
        'booking' => $booking,
        'entry' => $entry
    );
}

// Shared function to generate booking summary HTML from entry data
// This is called by confirmation shortcode, customer merge tag, and admin merge tag
// Pass in the entry_id and it handles all queries and HTML generation
// Returns array with 'html' and 'booking' keys for confirmation shortcode to access booking data
// $link_type: 'none' (default for shortcode), 'customer' (for email with detail link), 'admin' (for email with admin links)
function bst_generate_booking_summary_html($entry_id, $return_booking = false, $link_type = 'none', $encoded_id = '', $include_payment_history = false) {
    global $wpdb;
    
    // Get the entry
    $entry = GFAPI::get_entry($entry_id);
    if (!$entry || !is_array($entry)) {
        $html = '<p>Booking details not available.</p>';
        return $return_booking ? array('html' => $html, 'booking' => null, 'entry' => null) : $html;
    }
    
    // Try to find a booking record with this entry_id (search across all entry ID fields)
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bst_tour_booking 
        WHERE booking_entry_id = %d OR finalization_entry_id = %d OR additional_payment_entry_id = %d
        LIMIT 1",
        $entry_id, $entry_id, $entry_id
    ));
    
    // For GF10, if booking not found by entry IDs, try to get it from field 261 (booking update ID)
    // Field 261 already contains the decoded booking ID (integer), not encoded
    if (!$booking && rgar($entry, 'form_id') == 10) {
        $booking_id = intval(rgar($entry, '261'));
        if ($booking_id > 0) {
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
                $booking_id
            ));
        }
    }
    
    // 1) Initialize all variables needed for booking details
    $guest1_first = '';
    $guest1_last = '';
    $guest1_email = '';
    $guest1_phone = '';
    $guest2_first = '';
    $guest2_last = '';
    $tour_text = '';
    $tour_date_text = '';
    $tour_package_text = '';
    $extension_added = '';
    $extension_text = '';
    $extension_date_text = '';
    $vehicle1 = '';
    $vehicle2 = '';
    $payment_method = '';
    $tour_currency = '';
    $tour_price = 0;
    $coupon_code = '';
    $coupon_amount = 0;
    $net_tour_price = 0;
    $additional_charge = 0;
    $deposit_amount = 0;
    $balance_due = 0;
    
    // Get card type using helper function
    $card_type = bst_get_card_type($entry, rgar($entry, 'form_id'));
    
    // 2) Populate variables from booking table if it exists, otherwise from entry
    if ($booking) {
        // Booking exists - use booking data (may have been updated after initial booking)
        $tour_price = floatval($booking->tour_price ?? 0);
        $coupon_code = $booking->coupon_code ?? '';
        $coupon_amount = floatval($booking->coupon_amount ?? 0);
        $net_tour_price = floatval($booking->net_tour_price ?? $booking->tour_price);
        $additional_charge = floatval($booking->additional_charge ?? 0);
        $deposit_amount = floatval($booking->deposit_payment_amount ?? 0);
        $balance_due = floatval($booking->balance_due ?? 0);
        
        // Get payment method based on form ID
        $form_id = intval(rgar($entry, 'form_id'));
        if ($form_id == 10) {
            // GF10 - Get balance payment method
            $payment_method = $booking->balance_payment_method ?? rgar($entry, '118');
        } else {
            // GF9 or other - Get deposit payment method
            $payment_method = $booking->deposit_payment_method ?? rgar($entry, '118');
        }
        
        // For GF10 entries, if this entry is not yet saved as finalization_entry_id,
        // recalculate balance_due from entry fields (email sent during submission, before booking updated)
        if ($form_id == 10 && $booking->finalization_entry_id != $entry_id) {
            // Also get payment method from current entry since booking not yet updated
            $payment_method = rgar($entry, '118');
            
            // Recalculate balance_due using the same logic as bst_calculate_gf10_payment_details
            $existing_total_paid = floatval(rgar($entry, '193')); // Total paid before this payment
            $net_tour_price_entry = floatval(rgar($entry, '192')); // Net tour price from entry
            $additional_charge_entry = floatval(rgar($entry, '285')); // Additional charge from entry
            
            // Get balance payment discount from field 278
            $balance_payment_discount = floatval(rgar($entry, '278'));
            
            // Get deposit discount from booking (already applied to existing_total_paid)
            $deposit_discount = floatval($booking->deposit_payment_discount ?? 0);
            
            // Calculate total payment discount (deposit + balance discounts)
            $total_payment_discount = $deposit_discount + $balance_payment_discount;
            
            // Recalculate balance_due: net price + additional - paid - discounts
            $balance_due = bst_calculate_balance_due($net_tour_price_entry, $existing_total_paid, $total_payment_discount, $additional_charge_entry);
        }
    } else {
        // No booking record - use entry data
        $tour_price = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '143')));
        $coupon_code = rgar($entry, '161'); // Coupon code field
        $net_tour_price = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '160')));
        
        // Calculate coupon amount from price difference
        $coupon_amount = $tour_price - $net_tour_price;
        if ($coupon_amount < 0) {
            $coupon_amount = 0;
        }
        
        $additional_charge = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '285'))); // May not exist in entry
        $deposit_amount = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '177')));
        $balance_due = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '190')));
        $payment_method = rgar($entry, '118');
    }
    
    // Store original payment method before modification
    $payment_method_raw = $payment_method;
    
    // Add card type to payment method for Credit Card payments
    if ($payment_method === 'Credit Card' && !empty($card_type)) {
        $card_type = str_replace('Sepa', 'SEPA', ucwords($card_type));
        $payment_method .= ' (' . $card_type . ')';
    }
    
    // These fields come from entry, but fall back to booking if empty (GF10 uses different field IDs)
    $guest1_first = rgar($entry, '31.3');
    $guest1_last = rgar($entry, '31.6');
    $guest1_email = rgar($entry, '33');
    $guest1_phone = rgar($entry, '34');
    $guest2_first = rgar($entry, '215.3');
    $guest2_last = rgar($entry, '215.6');
    $tour_text = rgar($entry, '137');
    $tour_date_text = rgar($entry, '141');
    $tour_package_text = rgar($entry, '138');
    $extension_added = rgar($entry, '224');
    $extension_text = rgar($entry, '225');
    $extension_date_text = rgar($entry, '226');
    $vehicle1 = rgar($entry, '140');
    $vehicle2 = rgar($entry, '142');
    $tour_currency = rgar($entry, '223');
    
    // If tour fields are empty (GF10 uses different field numbers), get from booking
    if (empty($tour_text) && $booking) {
        $tour_text = $booking->tour_text;
        $tour_date_text = $booking->tour_date_text;
        $tour_package_text = $booking->tour_package_text;
        $extension_added = !empty($booking->tour_extension_added) ? $booking->tour_extension_added : '';
        $extension_text = !empty($booking->tour_extension_text) ? $booking->tour_extension_text : '';
        $extension_date_text = !empty($booking->tour_extension_date_text) ? $booking->tour_extension_date_text : '';
        $vehicle1 = !empty($booking->vehicle1) ? $booking->vehicle1 : '';
        $vehicle2 = !empty($booking->vehicle2) ? $booking->vehicle2 : '';
        $tour_currency = $booking->tour_currency;
    }
    
    // Build guest name using helper
    $guest_name = bst_format_guest_name($guest1_first, $guest1_last, $guest2_first, $guest2_last);
    
    // Get currency symbol using helper
    $currency_symbol = bst_get_currency_symbol($tour_currency);
    
    // Build HTML output - header and table with embedded CSS
    $html = '<style>
        .bsum-header { margin: 0; padding: 10px 15px; background: #0073aa; color: white; font-size: 18px; text-align: center; }
        .bsum-table { width: 100%; max-width: 600px; border-collapse: collapse; margin-bottom: 20px; }
        .bsum-table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .bsum-table td:first-child { font-weight: bold; width: 40%; text-align: right; }
        .bsum-payment-header { margin-top: 30px; margin-bottom: 0; padding: 10px 15px; background: #0073aa; color: white; font-size: 18px; text-align: center; }
        .bsum-payment-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .bsum-payment-table thead tr { border-bottom: 2px solid #0073aa; }
        .bsum-payment-table th { padding: 8px; font-weight: bold; }
        .bsum-payment-table th:first-child { text-align: left !important; }
        .bsum-payment-table th:not(:first-child) { text-align: center !important; }
        .bsum-payment-table td { padding: 8px; }
        .bsum-payment-table td:first-child { text-align: left !important; }
        .bsum-payment-table td:not(:first-child) { text-align: center !important; }
        .bsum-footer-cust { text-align: center; margin: 20px 0; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa; }
        .bsum-footer-cust p { margin: 0; }
        .bsum-footer-cust p:first-child { margin-bottom: 12px; color: #666666; font-size: 14px; }
        .bsum-footer-cust a { color: #0073aa; font-weight: 600; text-decoration: none; }
        .bsum-footer-admin { margin: 20px 0; border: 1px solid #dddddd; padding: 15px; background-color: #fff3cd; }
        .bsum-footer-admin h3 { color: #856404; margin-top: 0; font-size: 18px; }
        .bsum-footer-admin table { width: 100%; border-collapse: collapse; }
        .bsum-footer-admin td { padding: 8px 0; }
        .bsum-footer-admin td:first-child { color: #666666; font-weight: bold; }
        .bsum-footer-admin a { color: #0066cc; text-decoration: none; }
    </style>';
    
    $html .= '<h3 class="bsum-header">Booking Summary</h3>';
    $html .= '<table class="bsum-table">';
    
    // Guest
    $html .= '<tr><td>' . (!empty($guest2_first) ? 'Guests:' : 'Guest:') . '</td>';
    $html .= '<td>' . esc_html($guest_name) . '</td></tr>';
    
    // Email
    $html .= '<tr><td>Email:</td>';
    $html .= '<td>' . esc_html($guest1_email) . '</td></tr>';
    
    // Phone
    $html .= '<tr><td>Phone:</td>';
    $html .= '<td>' . esc_html(bst_format_phone_international($guest1_phone)) . '</td></tr>';
    
    // Tour (combined with dates)
    $tour_display = $tour_text;
    if (!empty($tour_date_text)) {
        $tour_display .= ' (' . $tour_date_text . ')';
    }
    $html .= '<tr><td>Tour:</td>';
    $html .= '<td>' . esc_html($tour_display) . '</td></tr>';
    
    // Extension (combined with dates)
    if (!empty($extension_added) && !empty($extension_text)) {
        // Strip price from extension text (remove anything in parentheses with € or $)
        $extension_display = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $extension_text);
        if (!empty($extension_date_text)) {
            $extension_display .= ' (' . $extension_date_text . ')';
        }
        $html .= '<tr><td>Tour Extension:</td>';
        $html .= '<td>' . esc_html($extension_display) . '</td></tr>';
    }
    
    // Package
    $html .= '<tr><td>Package:</td>';
    $html .= '<td>' . esc_html($tour_package_text) . '</td></tr>';
    
    // Vehicles - combine if both exist
    if (!empty($vehicle1) || !empty($vehicle2)) {
        // Strip prices from vehicle text (remove anything in parentheses with € or $)
        $vehicle1_clean = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $vehicle1);
        $vehicle2_clean = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $vehicle2);
        
        // Combine vehicles if both exist
        if (!empty($vehicle2_clean)) {
            $vehicle_display = $vehicle1_clean . ' / ' . $vehicle2_clean;
            $vehicle_label = 'Vehicles:';
        } else {
            $vehicle_display = $vehicle1_clean;
            $vehicle_label = 'Vehicle:';
        }
        
        $html .= '<tr><td>' . $vehicle_label . '</td>';
        $html .= '<td>' . esc_html($vehicle_display) . '</td></tr>';
    }
    
    // Participant Sex
    if ($booking && !empty($booking->participant_sex)) {
        $html .= '<tr><td>Participant Sex:</td>';
        $html .= '<td>' . esc_html($booking->participant_sex) . '</td></tr>';
    }
    
    // Room Sharing
    if ($booking && !empty($booking->sharing_preference)) {
        $html .= '<tr><td>Room Sharing:</td>';
        $html .= '<td>' . esc_html($booking->sharing_preference) . '</td></tr>';
    }
    
    // Bed Preference
    if ($booking && !empty($booking->bed_preference)) {
        $html .= '<tr><td>Bed Preference:</td>';
        $html .= '<td>' . esc_html($booking->bed_preference) . '</td></tr>';
    }
    
    // Pricing section - show Tour Price and Coupon if there's a coupon
    // Store payment discount for later display (after Total Paid)
    $payment_discount = 0;
    if ($booking && !empty($booking->payment_discount_amount)) {
        $payment_discount = floatval($booking->payment_discount_amount);
    }
    
    // Calculate pending amounts if booking exists
    // Payment information - use entry-based logic for booking confirmation
    if ($booking && $entry) {
        $form_id = intval(rgar($entry, 'form_id'));
        $is_finalization = ($form_id == 10);
        
        if ($is_finalization) {
            // GF10 - Balance Payment - Show tour pricing and deposit payment history first
            
            // Show Tour Price info from booking if available
            if ($booking) {
                $tour_price_full = floatval($booking->tour_price ?? 0);
                $coupon_code_val = $booking->coupon_code ?? '';
                $coupon_amount_val = floatval($booking->coupon_amount ?? 0);
                $net_tour_price_val = floatval($booking->net_tour_price ?? 0);
                
                $has_coupon = (!empty($coupon_code_val) || $coupon_amount_val > 0);
                
                if ($has_coupon) {
                    $html .= '<tr><td>Tour Price:</td>';
                    $html .= '<td>' . esc_html($currency_symbol . number_format($tour_price_full, 2)) . '</td></tr>';
                    
                    $html .= '<tr><td>Coupon:</td>';
                    $html .= '<td>-' . esc_html($currency_symbol . number_format($coupon_amount_val, 2));
                    if (!empty($coupon_code_val)) {
                        $html .= ' (' . esc_html($coupon_code_val) . ')';
                    }
                    $html .= '</td></tr>';
                    
                    $html .= '<tr><td>Net Tour Price:</td>';
                    $html .= '<td><strong>' . esc_html($currency_symbol . number_format($net_tour_price_val, 2)) . '</strong></td></tr>';
                } else {
                    $html .= '<tr><td>Tour Price:</td>';
                    $html .= '<td><strong>' . esc_html($currency_symbol . number_format($tour_price_full, 2)) . '</strong></td></tr>';
                }
            }
            
            // Show Deposit Payment
            $deposit_paid = floatval(rgar($entry, '193'));
            $deposit_discount = floatval(rgar($entry, '282'));
            
            if ($deposit_paid > 0) {
                $html .= '<tr><td>Deposit Payment:</td>';
                $html .= '<td>' . esc_html($currency_symbol . number_format($deposit_paid, 2)) . '</td></tr>';
                
                // Show Deposit Bank Transfer Discount if > 0
                if ($deposit_discount > 0) {
                    $html .= '<tr><td>Deposit Bank Transfer Discount:</td>';
                    $html .= '<td>-' . esc_html($currency_symbol . number_format($deposit_discount, 2)) . '</td></tr>';
                }
            }
            
            // Now handle current balance payment
            $balance_after_discount = floatval(rgar($entry, '279')); // Read calculated amount directly
            $discount_amount = floatval(rgar($entry, '278'));
            $payment_discount = 0;
            
            // Check if this is Bank Wire or Credit Card
            $is_bank_wire = ($payment_method === 'Bank Wire');
            
            if ($is_bank_wire) {
                // Received vs pending: use stored line payment status (not amount+date alone)
                $received_amount   = floatval($booking->balance_payment_amount ?? 0);
                $received_discount = floatval($booking->balance_payment_discount ?? 0);
                $line_received     = function_exists( 'bst_payment_line_received_for_display' )
                    && bst_payment_line_received_for_display( $booking->balance_payment_status ?? '', $received_amount );

                if ( $line_received ) {
                    $display_amount   = $received_amount;
                    $payment_discount = $received_discount;
                    $pending_note     = '';
                    $booking_status   = 'Booked - Received Bank Wire';
                } else {
                    // Payment pending - use calculated amounts from GF fields
                    $display_amount   = $balance_after_discount;
                    $payment_discount = $discount_amount;
                    $pending_note     = ' (Pending Bank Transfer)';
                    $booking_status   = 'Pending';
                }
                $amount_label = 'Balance Payment:';
            } else {
                // Credit Card - use after-discount amount (no discount for credit card)
                $display_amount = $balance_after_discount;
                $amount_label = 'Balance Payment:';
                $pending_note = '';
                $booking_status = 'Booked';
            }
        } else {
            // GF9 - Deposit Payment - Show tour pricing first
            
            // Show Tour Price info from booking if available
            if ($booking) {
                $tour_price_full = floatval($booking->tour_price ?? 0);
                $coupon_code_val = $booking->coupon_code ?? '';
                $coupon_amount_val = floatval($booking->coupon_amount ?? 0);
                $net_tour_price_val = floatval($booking->net_tour_price ?? 0);
                
                $has_coupon = (!empty($coupon_code_val) || $coupon_amount_val > 0);
                
                if ($has_coupon) {
                    $html .= '<tr><td>Tour Price:</td>';
                    $html .= '<td>' . esc_html($currency_symbol . number_format($tour_price_full, 2)) . '</td></tr>';
                    
                    $html .= '<tr><td>Coupon:</td>';
                    $html .= '<td>-' . esc_html($currency_symbol . number_format($coupon_amount_val, 2));
                    if (!empty($coupon_code_val)) {
                        $html .= ' (' . esc_html($coupon_code_val) . ')';
                    }
                    $html .= '</td></tr>';
                    
                    $html .= '<tr><td>Net Tour Price:</td>';
                    $html .= '<td><strong>' . esc_html($currency_symbol . number_format($net_tour_price_val, 2)) . '</strong></td></tr>';
                } else {
                    $html .= '<tr><td>Tour Price:</td>';
                    $html .= '<td><strong>' . esc_html($currency_symbol . number_format($tour_price_full, 2)) . '</strong></td></tr>';
                }
            }
            
            $deposit_undiscounted = floatval(rgar($entry, '177'));
            $discount_percent = floatval(rgar($entry, '231'));
            $entry_balance = floatval(rgar($entry, '190'));
            $payment_discount = 0;
            
            // Check if this is Bank Wire or Credit Card
            $is_bank_wire = ($payment_method === 'Bank Wire');
            
            if ($is_bank_wire) {
                $received_amount   = floatval($booking->deposit_payment_amount ?? 0);
                $received_discount = floatval($booking->deposit_payment_discount ?? 0);
                $line_received     = function_exists( 'bst_payment_line_received_for_display' )
                    && bst_payment_line_received_for_display( $booking->deposit_payment_status ?? '', $received_amount );

                if ( $line_received ) {
                    $display_amount   = $received_amount;
                    $payment_discount = $received_discount;
                    $pending_note     = '';
                    $booking_status   = 'Booked - Received Bank Wire';
                } else {
                    // Payment pending - calculate from entry
                    if ($deposit_undiscounted > 0 && $discount_percent > 0) {
                        $payment_discount = $deposit_undiscounted * ($discount_percent / 100);
                        $display_amount = $deposit_undiscounted - $payment_discount;
                    } else {
                        $display_amount = $deposit_undiscounted;
                    }
                    $pending_note = ' (Pending Bank Transfer)';
                    $booking_status = 'Pending';
                }
                $amount_label = 'Deposit:';
            } else {
                // Credit Card - use undiscounted amount from entry, no discount
                $display_amount = $deposit_undiscounted;
                $amount_label = 'Amount Paid:';
                $pending_note = '';
                $booking_status = 'Booked';
            }
        }
        
        // Display Payment Method
        $payment_method_display = $payment_method;
        if ($payment_method_display === 'Credit Card' && $entry) {
            $card_type = bst_get_card_type($entry, rgar($entry, 'form_id'));
            if (!empty($card_type)) {
                $payment_method_display .= ' (' . ucwords($card_type) . ')';
            }
        }
        // Convert Bank Wire to Bank Transfer for display
        if ($payment_method_display === 'Bank Wire') {
            $payment_method_display = 'Bank Transfer';
        }
        $html .= '<tr><td>Payment Method:</td>';
        $html .= '<td>' . esc_html($payment_method_display) . '</td></tr>';
        
        // Display Amount Paid/Deposit/Balance Payment
        $html .= '<tr><td>' . $amount_label . '</td>';
        $html .= '<td>' . esc_html($currency_symbol . number_format($display_amount, 2)) . $pending_note . '</td></tr>';
        
        // Display Payment Discount (if applicable)
        if ($payment_discount > 0) {
            $discount_label = $is_finalization ? 'Balance Payment Discount:' : 'Payment Discount:';
            $html .= '<tr><td>' . $discount_label . '</td>';
            $html .= '<td>-' . esc_html($currency_symbol . number_format($payment_discount, 2)) . '</td></tr>';
        }
        
        // Display Balance Due - show for both GF9 and GF10
        if ($is_finalization) {
            // GF10 - Show balance due and adjust for pending payment
            // Note: $balance_due was already set earlier in the function, either from DB or recalculated for merge tags
            $projected_balance = $balance_due;
            
            // If payment is pending, subtract the after-discount payment amount
            // $display_amount is already the after-discount amount for pending payments
            // balance_due already correctly includes all discounts, so subtracting display_amount gives correct projection
            if (!empty($pending_note) && $display_amount > 0) {
                $projected_balance = $balance_due - $display_amount;
            }
            
            $html .= '<tr><td>Remaining Balance:</td>';
            $html .= '<td><strong>' . esc_html($currency_symbol . number_format(max(0, $projected_balance), 2)) . '</strong>' . $pending_note . '</td></tr>';
        } else {
            // GF9 - Show remaining balance
            $balance_display = $entry_balance;
            if ($balance_display < 0) {
                $balance_display = 0;
            }
            $html .= '<tr><td>Remaining Balance:</td>';
            $html .= '<td><strong>' . esc_html($currency_symbol . number_format($balance_display, 2)) . '</strong>' . $pending_note . '</td></tr>';
        }
    } else {
        // No booking - show simple payment info for entry-only display
        $form_id = intval(rgar($entry, 'form_id'));
        $is_finalization = ($form_id == 10);
        
        // Show Tour Price/Coupon/Net Tour Price from entry fields (booking not saved yet for emails)
        $tour_price_entry = floatval(rgar($entry, '143'));
        $net_tour_price_entry = floatval(rgar($entry, '160'));
        $coupon_code_entry = rgar($entry, '161');
        $coupon_amount_entry = $tour_price_entry - $net_tour_price_entry;
        
        $has_coupon_entry = (!empty($coupon_code_entry) || $coupon_amount_entry > 0);
        
        if ($has_coupon_entry) {
            $html .= '<tr><td>Tour Price:</td>';
            $html .= '<td>' . esc_html($currency_symbol . number_format($tour_price_entry, 2)) . '</td></tr>';
            
            $html .= '<tr><td>Coupon:</td>';
            $html .= '<td>-' . esc_html($currency_symbol . number_format($coupon_amount_entry, 2));
            if (!empty($coupon_code_entry)) {
                $html .= ' (' . esc_html($coupon_code_entry) . ')';
            }
            $html .= '</td></tr>';
            
            $html .= '<tr><td>Net Tour Price:</td>';
            $html .= '<td><strong>' . esc_html($currency_symbol . number_format($net_tour_price_entry, 2)) . '</strong></td></tr>';
        } else {
            $html .= '<tr><td>Tour Price:</td>';
            $html .= '<td><strong>' . esc_html($currency_symbol . number_format($tour_price_entry, 2)) . '</strong></td></tr>';
        }
        
        $payment_method_display = $payment_method;
        // Add card type for Credit Card payments
        if ($payment_method_display === 'Credit Card' && $entry) {
            $card_type = bst_get_card_type($entry, rgar($entry, 'form_id'));
            if (!empty($card_type)) {
                $payment_method_display .= ' (' . ucwords($card_type) . ')';
            }
        }
        // Convert Bank Wire to Bank Transfer for display
        if ($payment_method_display === 'Bank Wire') {
            $payment_method_display = 'Bank Transfer';
        }
        $html .= '<tr><td>Payment Method:</td>';
        $html .= '<td>' . esc_html($payment_method_display) . '</td></tr>';
        
        if ($is_finalization) {
            // GF10 - Balance Payment - Show tour price/coupon first, then deposit payment history
            // Get tour price from entry fields passed from GF9
            $tour_price_gf10 = floatval(rgar($entry, '188'));  // Net Tour Price passed in
            $deposit_paid_gf10 = floatval(rgar($entry, '190')); // Deposit paid passed in
            $deposit_discount_gf10 = floatval(rgar($entry, '283')); // Deposit discount passed in
            
            // For GF10, try to get tour price and coupon from passed-in fields or calculate
            // Field 188 is the net price passed in, we need to check if there was a coupon
            // If deposit + balance != net price, there may have been a coupon
            // For now, just show what we have
            if ($tour_price_gf10 > 0) {
                $html .= '<tr><td>Tour Price:</td>';
                $html .= '<td>' . esc_html($currency_symbol . number_format($tour_price_gf10, 2)) . '</td></tr>';
            }
            
            // Show Deposit Payment
            $deposit_paid = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '193')));
            $deposit_discount = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '282')));
            
            // Show Deposit Payment
            if ($deposit_paid > 0) {
                $html .= '<tr><td>Deposit Payment:</td>';
                $html .= '<td>' . esc_html($currency_symbol . number_format($deposit_paid, 2)) . '</td></tr>';
                
                // Show Deposit Bank Transfer Discount if > 0
                if ($deposit_discount > 0) {
                    $html .= '<tr><td>Deposit Bank Transfer Discount:</td>';
                    $html .= '<td>-' . esc_html($currency_symbol . number_format($deposit_discount, 2)) . '</td></tr>';
                }
            }
            
            // Now handle current balance payment
            $balance_after_discount = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '279'))); // Read calculated amount directly
            $discount_amount = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '278')));
            $payment_discount = 0;
            
            if ($payment_method_raw === 'Bank Wire') {
                // Use calculated amounts from GF fields
                $display_amount = $balance_after_discount;
                $payment_discount = $discount_amount;
                $amount_label = 'Balance Payment:';
                $pending_note = ' (Pending Bank Transfer)';
            } else {
                // Credit Card - use after-discount amount (no discount for credit card)
                $display_amount = $balance_after_discount;
                $amount_label = 'Balance Payment:';
                $pending_note = '';
            }
        } else {
            // GF9 - Deposit Payment
            $deposit_undiscounted = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '177')));
            $discount_percent = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '231')));
            $payment_discount = 0;
            
            if ($payment_method_raw === 'Bank Wire') {
                // Calculate discounted deposit
                if ($deposit_undiscounted > 0 && $discount_percent > 0) {
                    $payment_discount = $deposit_undiscounted * ($discount_percent / 100);
                    $display_amount = $deposit_undiscounted - $payment_discount;
                } else {
                    $display_amount = $deposit_undiscounted;
                }
                $amount_label = 'Deposit:';
                $pending_note = ' (Pending Bank Transfer)';
            } else {
                // Credit Card - use undiscounted amount, no discount
                $display_amount = $deposit_undiscounted;
                $amount_label = 'Amount Paid:';
                $pending_note = '';
            }
        }
        
        // Display amount
        $html .= '<tr><td>' . $amount_label . '</td>';
        $html .= '<td>' . esc_html($currency_symbol . number_format($display_amount, 2)) . $pending_note . '</td></tr>';
        
        // Display payment discount (Bank Wire only)
        if ($payment_method_raw === 'Bank Wire' && $payment_discount > 0) {
            $discount_label = $is_finalization ? 'Balance Payment Discount:' : 'Payment Discount:';
            $html .= '<tr><td>' . $discount_label . '</td>';
            $html .= '<td>-' . esc_html($currency_symbol . number_format($payment_discount, 2)) . '</td></tr>';
        }
        
        // Display balance due - show for both GF9 and GF10
        if ($is_finalization) {
            // GF10 - Check if Bank Wire payment is pending (no booking row: entry-only; no line status yet)
            if ( $payment_method_raw === 'Bank Wire' && ! empty( $pending_note ) ) {
                // Payment pending - after this payment is received, balance will be 0
                $html .= '<tr><td>Remaining Balance:</td>';
                $html .= '<td><strong>' . esc_html($currency_symbol . '0.00') . '</strong>' . $pending_note . '</td></tr>';
            } else {
                // Payment received or Credit Card - balance is 0
                $html .= '<tr><td>Remaining Balance:</td>';
                $html .= '<td><strong>' . esc_html($currency_symbol . '0.00') . '</strong></td></tr>';
            }
        } else {
            // GF9 - Show balance due from entry
            $entry_balance = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '190')));
            $balance_display = $entry_balance;
            
            $html .= '<tr><td>Remaining Balance:</td>';
            $html .= '<td>' . esc_html($currency_symbol . number_format($balance_display, 2)) . $pending_note . '</td></tr>';
        }
    }
    
    // Booking Date
    $html .= '<tr><td>Booking Date:</td>';
    $html .= '<td>' . esc_html(date('Y-m-d', strtotime($entry['date_created']))) . '</td></tr>';
    
    // Status - use actual booking status if booking exists, otherwise determine from payment method
    if ($booking && !empty($booking->booking_status)) {
        $booking_status = $booking->booking_status;
    } else {
        $booking_status = ($payment_method_raw !== 'Bank Wire') ? 'Booked' : 'Pending';
    }
    $html .= '<tr><td>Status:</td>';
    $html .= '<td><strong>' . esc_html($booking_status) . '</strong></td></tr>';
    
    $html .= '</table>';
    
    // Add Payment History section if booking exists and has payments (only when explicitly requested)
    if ($include_payment_history && $booking && (
        (floatval($booking->deposit_payment_amount ?? 0) > 0) ||
        (floatval($booking->balance_payment_amount ?? 0) > 0) ||
        (floatval($booking->additional_payment_amount ?? 0) > 0)
    )) {
        $html .= '<h3 class="bsum-payment-header">Payment History</h3>';
        $html .= '<table class="bsum-payment-table">';
        $html .= '<thead><tr>';
        $html .= '<th>Payment</th>';
        $html .= '<th>Date</th>';
        $html .= '<th>Amount</th>';
        $html .= '<th>Method</th>';
        $html .= '<th>Type</th>';
        $html .= '</tr></thead><tbody>';
        
        // Deposit payment
        if (!empty($booking->deposit_payment_amount) && floatval($booking->deposit_payment_amount) > 0) {
            $deposit_method = $booking->deposit_payment_method ?? '';
            $card_type = '';
            if ($deposit_method === 'Credit Card' && !empty($booking->booking_entry_id)) {
                $deposit_entry = GFAPI::get_entry($booking->booking_entry_id);
                if ($deposit_entry && !is_wp_error($deposit_entry)) {
                    $card_type = bst_get_card_type($deposit_entry, rgar($deposit_entry, 'form_id'));
                    $card_type = $card_type ? str_replace('Sepa', 'SEPA', ucwords($card_type)) : '';
                }
            } elseif ($deposit_method === 'Bank Wire' && !empty($booking->booking_entry_id)) {
                $deposit_entry = GFAPI::get_entry($booking->booking_entry_id);
                if ($deposit_entry && !is_wp_error($deposit_entry)) {
                    $region_code = rgar($deposit_entry, '232', 'Other');
                    $card_type = ($region_code === 'Other') ? 'EUR' : $region_code;
                }
            }
            // Convert Bank Wire to Bank Transfer for display
            $deposit_method_display = ($deposit_method === 'Bank Wire') ? 'Bank Transfer' : $deposit_method;
            $html .= '<tr>';
            $html .= '<td>Deposit Payment</td>';
            $html .= '<td>' . (!empty($booking->deposit_payment_date) ? esc_html(date('Y-m-d', strtotime($booking->deposit_payment_date))) : '') . '</td>';
            $html .= '<td>' . esc_html($currency_symbol . number_format($booking->deposit_payment_amount, 2)) . '</td>';
            $html .= '<td>' . esc_html($deposit_method_display) . '</td>';
            $html .= '<td>' . esc_html($card_type) . '</td>';
            $html .= '</tr>';
        }
        
        // Balance payment
        if (!empty($booking->balance_payment_amount) && floatval($booking->balance_payment_amount) > 0) {
            $balance_method = $booking->balance_payment_method ?? '';
            $card_type = '';
            if ($balance_method === 'Credit Card') {
                // Balance payment is from form 10 - field 281.4
                // If we have the finalization entry (viewing GF10 confirmation), use it
                if ($entry && rgar($entry, 'form_id') == 10) {
                    $card_type = bst_get_card_type($entry, 10);
                    $card_type = $card_type ? str_replace('Sepa', 'SEPA', ucwords($card_type)) : '';
                } elseif (!empty($booking->finalization_entry_id)) {
                    // Otherwise fetch it
                    $balance_entry = GFAPI::get_entry($booking->finalization_entry_id);
                    if ($balance_entry && !is_wp_error($balance_entry)) {
                        $card_type = bst_get_card_type($balance_entry, 10);
                        $card_type = $card_type ? str_replace('Sepa', 'SEPA', ucwords($card_type)) : '';
                    }
                }
            } elseif ($balance_method === 'Bank Wire') {
                // Get region code from form 10
                if ($entry && rgar($entry, 'form_id') == 10) {
                    $region_code = rgar($entry, '280', 'Other');
                    $card_type = ($region_code === 'Other') ? 'EUR' : $region_code;
                } elseif (!empty($booking->finalization_entry_id)) {
                    $balance_entry = GFAPI::get_entry($booking->finalization_entry_id);
                    if ($balance_entry && !is_wp_error($balance_entry)) {
                        $region_code = rgar($balance_entry, '280', 'Other');
                        $card_type = ($region_code === 'Other') ? 'EUR' : $region_code;
                    }
                }
            }
            // Convert Bank Wire to Bank Transfer for display
            $balance_method_display = ($balance_method === 'Bank Wire') ? 'Bank Transfer' : $balance_method;
            $html .= '<tr>';
            $html .= '<td>Balance Payment</td>';
            $html .= '<td>' . (!empty($booking->balance_payment_date) ? esc_html(date('Y-m-d', strtotime($booking->balance_payment_date))) : '') . '</td>';
            $html .= '<td>' . esc_html($currency_symbol . number_format($booking->balance_payment_amount, 2)) . '</td>';
            $html .= '<td>' . esc_html($balance_method_display) . '</td>';
            $html .= '<td>' . esc_html($card_type) . '</td>';
            $html .= '</tr>';
        }
        
        // Additional payment
        if (!empty($booking->additional_payment_amount) && floatval($booking->additional_payment_amount) > 0) {
            $additional_method = $booking->additional_payment_method ?? '';
            // No type lookup — no GF entry exists for additional payments yet
            $additional_method_display = ($additional_method === 'Bank Wire') ? 'Bank Transfer' : $additional_method;
            $html .= '<tr>';
            $html .= '<td>Additional Payment</td>';
            $html .= '<td>' . (!empty($booking->additional_payment_date) ? esc_html(date('Y-m-d', strtotime($booking->additional_payment_date))) : '') . '</td>';
            $html .= '<td>' . esc_html($currency_symbol . number_format($booking->additional_payment_amount, 2)) . '</td>';
            $html .= '<td>' . esc_html($additional_method_display) . '</td>';
            $html .= '<td></td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    // Add expandable bank wire instructions section after payments (if any bank wire payments exist)
    // Only show for customer-facing views (not for admin/bst) AND only if payment is pending
    if ($link_type === 'customer' || $link_type === 'none') {
        // Check if bank wire payment is pending
        $show_instructions = false;
        $form_id = intval(rgar($entry, 'form_id'));
        
        if ($payment_method_raw === 'Bank Wire') {
            if ($form_id == 10) {
                // GF10 - Balance payment: use line payment status when booking exists
                if ($booking) {
                    $amt = floatval( $booking->balance_payment_amount ?? 0 );
                    $received          = function_exists( 'bst_payment_line_received_for_display' )
                        && bst_payment_line_received_for_display( $booking->balance_payment_status ?? '', $amt );
                    $show_instructions = ! $received;
                } else {
                    $show_instructions = true;
                }
            } else {
                // GF9 - Deposit payment
                if ($booking) {
                    $amt = floatval( $booking->deposit_payment_amount ?? 0 );
                    $received          = function_exists( 'bst_payment_line_received_for_display' )
                        && bst_payment_line_received_for_display( $booking->deposit_payment_status ?? '', $amt );
                    $show_instructions = ! $received;
                } else {
                    $show_instructions = true;
                }
            }
        }
        
        if ($show_instructions) {
            // If encoded_id is empty, this is from merge tag (email) - always open (no expandable)
            // If encoded_id has value, this could be from merge tag OR web page - check link_type
            // Web pages (confirmation) will have link_type='customer' with empty encoded_id from the call
            $always_open = (!empty($encoded_id)); // Has encoded_id = from merge tag (email)
            $html .= bst_generate_bank_wire_section($booking, $entry, $always_open, false, 'confirmation');
        }
    }
    
    // Add footer links based on link_type
    if ($link_type === 'customer' && !empty($encoded_id)) {
        // Customer version - link to booking details page
        $details_url = site_url('/bookingdetails/') . '?eid=' . $encoded_id;
        $confirmation_url = site_url('/bookingconfirmation/') . '?eid=' . $encoded_id;
        
        $html .= '<div class="bsum-footer-cust">';
        // Show invoice text if booking has invoice OR if this is a GF10 entry (invoice always created on finalization)
        $is_finalized = (rgar($entry, 'form_id') == 10);
        $invoice_text = (($booking && !empty($booking->booking_invoice_number) && $booking->booking_invoice_number !== 'Not generated') || $is_finalized) ? ', including the pro forma invoice' : '';
        $html .= '<p>The above is a summary of your booking. Click the button below to view full details' . $invoice_text . '.</p>';
        $html .= '<p>📋 <a href="' . esc_url($details_url) . '">View Full Booking Details</a></p>';
        $html .= '</div>';
    } elseif ($link_type === 'admin' && !empty($encoded_id)) {
        // Admin version - links to admin panel and customer details page
        $html .= '<div class="bsum-footer-admin">';
        $html .= '<h3>Links (Internal Use Only)</h3>';
        $html .= '<table>';
        
        // Admin booking page link (use booking ID if available, otherwise entry ID with booking_entry_id parameter)
        if ($booking) {
            $admin_link = admin_url('admin.php?page=view_booking&id=' . $booking->id);
        } else {
            $admin_link = admin_url('admin.php?page=view_booking&booking_entry_id=' . $entry_id);
        }
        $html .= '<tr>';
        $html .= '<td>Admin Booking Page:</td>';
        $html .= '<td><a href="' . esc_url($admin_link) . '">View in Admin</a></td>';
        $html .= '</tr>';
        
        // Full booking details link
        $html .= '<tr>';
        $html .= '<td>Full Booking Details:</td>';
        $html .= '<td><a href="' . esc_url(site_url('/bookingdetails/') . '?eid=' . $encoded_id . '&type=BST') . '">Full Booking Details</a></td>';
        $html .= '</tr>';
        
        $html .= '</table>';
        $html .= '</div>';
    }
    
    // Return HTML string, or array with HTML and booking object if requested  
    if ($return_booking) {
        return array(
            'html' => $html,
            'booking' => $booking,
            'entry' => $entry
        );
    }
    
    return $html;
}

// Shortcode to display booking confirmation details on confirmation page
add_shortcode('booking_confirmation', 'bst_booking_confirmation_shortcode');
function bst_booking_confirmation_shortcode($atts) {
    // Get encoded eid (entry ID) from URL and decode it
    $decoded_id = 0;
    if (isset($_GET['eid']) && is_string($_GET['eid'])) {
        $decoded_id = bst_decode_booking_id($_GET['eid']);
    }
    
    if (!$decoded_id) {
        return '<div class="booking-confirmation-error">No booking information found.</div>';
    }
    
    // Get entry to extract guest name and payment method for the confirmation message
    $entry = GFAPI::get_entry($decoded_id);
    if (!$entry || is_wp_error($entry)) {
        return '<div class="booking-confirmation-error">Booking information not found.</div>';
    }
    
    // Extract guest name for confirmation message using helper
    $guest1_first = rgar($entry, '31.3');
    $guest1_last = rgar($entry, '31.6');
    $guest2_first = rgar($entry, '215.3');
    $guest2_last = rgar($entry, '215.6');
    $guest_name = bst_format_guest_name($guest1_first, $guest1_last, $guest2_first, $guest2_last);
    
    $payment_method = rgar($entry, '118');
    $payment_method_raw = $payment_method; // Store raw payment method for conditional checks
    $form_id = $entry['form_id'];
    
    // Get booking summary HTML and booking object using shared function
    // Pass 'customer' link_type so bank wire section knows this is a web page (expandable)
    // Do NOT include payment history - confirmation is a snapshot, details page has full history
    $result = bst_generate_booking_summary_html($decoded_id, true, 'customer', '', false);
    $booking_html = $result['html'];
    $booking = $result['booking'];
    $booking_entry = $result['entry'];
    
    // Build output HTML with inline styles
    ob_start();
    ?>
    <style>
        .booking-details-container { max-width: 900px; margin: 0 auto; padding: 20px; font-size: 0.9em; background: #f5f5f5; }
        .booking-details-container h2 { color: #0073aa; font-size: 1.8em; font-weight: bold; margin-bottom: 0; }
        .confirmation-message { font-size: 1.1em; margin-bottom: 25px; }
        .booking-details-section { background: white; padding: 15px; border-radius: 4px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .booking-details-section h3 { font-size: 1.2em; margin: 0 0 15px 0; padding: 10px 15px; background: #0073aa; color: white; border-radius: 4px 4px 0 0; margin: -15px -15px 15px -15px; }
        .booking-details-section table { width: 100%; max-width: 100%; border-collapse: collapse; }
        .booking-details-section table td { padding: 8px 5px; border-bottom: 1px solid #e8e8e8; vertical-align: top; font-size: 0.95em; }
        .booking-details-section table tr:last-child td { border-bottom: none; }
        .booking-details-section table td:first-child { width: 40%; text-align: right; padding-right: 15px; font-weight: bold; color: #333; }
        .booking-details-section table td:last-child { text-align: left; padding-left: 15px; }
        .confirmation-next-steps { background: #e8f5e9; padding: 20px; border-radius: 8px; border-left: 4px solid #2c5f2d; font-size: 0.9em; }
        .confirmation-next-steps h3 { margin-top: 0; font-size: 1.2em; font-weight: 700; }
        .bank-wire-instructions { background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #856404; font-size: 0.9em; margin-top: 20px; }
        .bank-wire-instructions h3 { margin-top: 0; font-size: 1.2em; font-weight: 700; color: #856404; }
        .bank-wire-instructions table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .bank-wire-instructions table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .bank-wire-instructions table td:first-child { font-weight: bold; width: 30%; }
    </style>
    <div class="booking-details-container">
        <?php 
        // Determine header based on form ID
        $form_id = $entry['form_id'];
        $page_header = ($form_id == 10) ? 'Booking Finalized!' : 'Booking Confirmed!';
        ?>
        <h2><?php echo $page_header; ?></h2>
        <p class="confirmation-message">Thank you for your booking, <?php echo esc_html($guest_name); ?>!</p>
        
        <div class="booking-details-section">
            <?php echo $booking_html; ?>
        </div>
        
        <?php 
        // Check if finalization link should be shown
        $show_finalization_link = false;
        $finalization_url = '';
        $finalization_due_date = '';
        $tour_start_date = null;
        
        if ($booking && strtolower($booking->booking_status) === 'booked') {
            // Calculate balance due date using centralized helper function
            $finalization_due_date = bst_calculate_balance_due_date($booking);
            
            // Get tour start date for finalization window calculation
            if (!empty($booking->tour_date_id)) {
                $tour_date_id = intval(explode('|', $booking->tour_date_id)[0]);
                $start_date_str = get_field('start_date', $tour_date_id);
                
                if (!empty($start_date_str)) {
                    $tour_start_date = strtotime($start_date_str);
                    
                    if ($tour_start_date) {
                        $days_until_tour = floor(($tour_start_date - time()) / 86400);
                        
                        // Show finalization link if within sent window
                        $finalization_sent_days = get_option('bst_finalization_sent_days', 120);
                        if ($days_until_tour > 0 && $days_until_tour <= $finalization_sent_days) {
                            $show_finalization_link = true;
                            $finalization_url = bst_get_finalization_url($booking->id);
                        }
                    }
                }
            }
        }
        
        // Determine if there's a pending bank wire deposit
        $has_pending_deposit = false;
        if ($booking && !empty($booking->deposit_payment_method) && 
            $booking->deposit_payment_method === 'Bank Wire' && 
            floatval($booking->deposit_payment_amount) == 0) {
            $has_pending_deposit = true;
        } elseif (!$booking && $payment_method_raw === 'Bank Wire') {
            // Check entry fields for wire received date/amount
            $wire_received_date = rgar($booking_entry, '209');
            $wire_received_amount = rgar($booking_entry, '210');
            // Only pending if no received date/amount in entry
            if (empty($wire_received_date) || empty($wire_received_amount)) {
                $has_pending_deposit = true;
            }
        }
        
        // Get currency info for finalization message
        $tour_currency = rgar($booking_entry, '223');
        $currency_symbol = '€';
        if ($tour_currency === 'USD') {
            $currency_symbol = '$';
        } elseif ($tour_currency === 'GBP') {
            $currency_symbol = '£';
        }
        
        // Get balance due for finalization message (already accounts for payment discount)
        $balance_due = 0;
        if ($booking && isset($booking->balance_due)) {
            $balance_due = floatval($booking->balance_due);
        } else {
            $balance_due = floatval(rgar($booking_entry, '190'));
        }
        ?>
        
        <?php if ($payment_method_raw === 'Bank Wire' && $has_pending_deposit): 
            // Get deposit amounts from entry (both undiscounted and after discount)
            $deposit_undiscounted = floatval(rgar($booking_entry, '177'));
            $deposit_discount = floatval(rgar($booking_entry, '229'));
            $deposit_eur = $deposit_undiscounted - $deposit_discount;
            
            // Get booking details for "What's Next" section
            $guest1_email = rgar($booking_entry, '33');
            
            // Use the same eid from the URL
            $encoded_eid = isset($_GET['eid']) ? sanitize_text_field($_GET['eid']) : '';
        ?>
        <?php if ($encoded_eid): ?>
        <div style="margin: 20px 0; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa;">
            <p style="margin: 0 0 12px 0; color: #666666; font-size: 14px;">The above is a summary of your booking. Click the button below to view full details<?php if ($booking && !empty($booking->booking_invoice_number) && $booking->booking_invoice_number !== 'Not generated'): ?>, including the pro forma invoice<?php endif; ?>.</p>
            <p style="margin: 0;">📋 <a href="<?php echo esc_url(home_url('/bookingdetails/?eid=' . $encoded_eid)); ?>" style="color: #0073aa; font-weight: 600; text-decoration: none;">View Full Booking Details</a></p>
        </div>
        <?php endif; ?>
        <div class="confirmation-next-steps">
            <h3>What's Next?</h3>
            <?php if (!$booking): ?>
            <p>A confirmation email has been sent to <strong><?php echo esc_html($guest1_email); ?></strong></p>
            <?php endif; ?>
            
            <?php if ($show_finalization_link): ?>
            <p>To complete your booking, you must provide additional tour information and pay the remaining balance of <strong><?php echo esc_html($currency_symbol . number_format($balance_due, 2)); ?></strong> by <strong><?php echo esc_html($finalization_due_date); ?></strong>.</p>
            <?php if (!empty($finalization_url)): ?>
            <p><a href="<?php echo esc_url($finalization_url); ?>" class="finalization-button" style="display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">Finalize Your Booking</a></p>
            <?php endif; ?>
            <?php elseif (!empty($finalization_due_date) && $balance_due > 0): ?>
                <p>The remaining balance of <strong><?php echo esc_html($currency_symbol . number_format($balance_due, 2)); ?></strong> is due by <strong><?php echo esc_html($finalization_due_date); ?></strong>. You can return to this page closer to your tour date to finalize your booking and provide additional tour information.</p>
            <?php else: ?>
                <?php 
                $remaining_balance = $balance_due - $deposit_eur;
                if ($remaining_balance > 0): 
                ?>
                <p>The remaining balance of <strong><?php echo esc_html($currency_symbol . number_format($remaining_balance, 2)); ?></strong> is due 90 days before the tour starts.</p>
                <?php endif; ?>
            <?php endif; ?>
            
            <p>As your tour start date approaches, you will receive planning emails with important tour information. Please read these carefully as they contain essential details for your tour.</p>
            <p>If you have any questions, please contact us at <strong><a href="mailto:info@bluestradatours.com">info@bluestradatours.com</a></strong></p>
        </div>
        <?php else: 
            // Use the same eid from the URL
            $encoded_eid = isset($_GET['eid']) ? sanitize_text_field($_GET['eid']) : '';
        ?>
        <!-- Non-Bank Wire or Bank Wire already received -->
        <?php if ($encoded_eid): ?>
        <div style="margin: 20px 0; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa;">
            <p style="margin: 0 0 12px 0; color: #666666; font-size: 14px;">The above is a summary of your booking. Click the button below to view full details<?php if ($booking && !empty($booking->booking_invoice_number) && $booking->booking_invoice_number !== 'Not generated'): ?>, including the pro forma invoice<?php endif; ?>.</p>
            <p style="margin: 0;">📋 <a href="<?php echo esc_url(home_url('/bookingdetails/?eid=' . $encoded_eid)); ?>" style="color: #0073aa; font-weight: 600; text-decoration: none;">View Full Booking Details</a></p>
        </div>
        <?php endif; ?>
        <div class="confirmation-next-steps">
            <h3>What's Next?</h3>
            <?php if (!$booking): ?>
            <p>A confirmation email has been sent to <strong><?php echo esc_html(rgar($booking_entry, '33')); ?></strong></p>
            <?php endif; ?>
            
            <?php 
            // Check if booking is in Processing status (SEPA, etc.)
            if ($booking && $booking->booking_status === 'Processing'): 
            ?>
            <p style="color: #f0ad4e; font-weight: 600;">⏳ Your payment is currently being processed. This typically takes 2-14 days for <?php echo esc_html($payment_method); ?> payments. You will receive an email confirmation once your payment clears.</p>
            <?php endif; ?>
            
            <?php 
            // Check if booking is in Payment Failed status
            if ($booking && $booking->booking_status === 'Payment Failed'): 
            ?>
            <p style="color: #dc3232; font-weight: 600;">❌ We're sorry, but your payment could not be processed. Please check your payment method or contact us at <strong><a href="mailto:info@bluestradatours.com">info@bluestradatours.com</a></strong> to complete your booking.</p>
            <?php endif; ?>
            
            <?php if ($show_finalization_link): ?>
            <p>To complete your booking, you must provide additional tour information and pay the remaining balance of <strong><?php echo esc_html($currency_symbol . number_format($balance_due, 2)); ?></strong> by <strong><?php echo esc_html($finalization_due_date); ?></strong>.</p>
            <?php if (!empty($finalization_url)): ?>
            <p><a href="<?php echo esc_url($finalization_url); ?>" class="finalization-button" style="display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">Finalize Your Booking</a></p>
            <?php endif; ?>
            <?php elseif (!empty($finalization_due_date) && $balance_due > 0): ?>
            <p>The remaining balance of <strong><?php echo esc_html($currency_symbol . number_format($balance_due, 2)); ?></strong> is due by <strong><?php echo esc_html($finalization_due_date); ?></strong>. You can return to this page closer to your tour date to finalize your booking and provide additional tour information.</p>
            <?php endif; ?>
            
            <p>As your tour start date approaches, you will receive planning emails with important tour information. Please read these carefully as they contain essential details for your tour.</p>
            <p>If you have any questions, please contact us at <strong><a href="mailto:info@bluestradatours.com">info@bluestradatours.com</a></strong></p>
        </div>
        <?php endif; ?>
    </div>
    <style>
        /* Bank Transfer Payment Instructions arrow rotation */
        .bank-wire-details[open] .bank-wire-arrow {
            transform: rotate(90deg);
        }
    </style>
    <?php
    return ob_get_clean();
}


// Shortcode to display booking status for customers to check their booking
add_shortcode('booking_details', 'bst_booking_details_shortcode');
function bst_booking_details_shortcode($atts) {
    global $wpdb;
    
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'type' => 'customer',  // 'customer' or 'bst'
        'id' => ''
    ), $atts);
    
    // Check URL parameter for type (from merge tag links)
    if (isset($_GET['type']) && $_GET['type'] === 'bst') {
        $display_type = 'bst';
    } else {
        $display_type = strtolower($atts['type']);
    }
    
    // Get encoded eid (entry ID) or bid (booking ID) from URL and decode it
    $booking_id = 0;
    $entry_id = 0;
    
    if (isset($_GET['eid']) && is_string($_GET['eid'])) {
        // eid = entry_id (booking, finalization, or additional payment entry)
        $entry_id = bst_decode_booking_id($_GET['eid']);
    }
    
    // Check for bid parameter (booking_id) - used when no entry_id exists
    if (isset($_GET['bid']) && is_string($_GET['bid'])) {
        $booking_id = bst_decode_booking_id($_GET['bid']);
    }
    
    if (!$booking_id && !$entry_id) {
        return '<div class="booking-status-error">No booking or entry ID provided.</div>';
    }
    
    // Get the booking record
    $booking = null;
    
    if ($booking_id) {
        // Direct lookup by booking ID
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
            $booking_id
        ));
    } elseif ($entry_id) {
        // Search by entry_id across all entry ID fields
        // Try booking_entry_id first
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE booking_entry_id = %d",
            $entry_id
        ));
        
        // If not found, try finalization_entry_id
        if (!$booking) {
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE finalization_entry_id = %d",
                $entry_id
            ));
        }
        
        // If still not found, try additional_payment_entry_id (additional payment entry)
        if (!$booking) {
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE additional_payment_entry_id = %d",
                $entry_id
            ));
        }
    }
    
    if (!$booking) {
        return '<div class="booking-status-error">Booking not found.</div>';
    }
    
    // Get the entry if we need it
    $entry = null;
    if ($booking->booking_entry_id) {
        $entry = GFAPI::get_entry($booking->booking_entry_id);
    }
    
    // Get booking details data using shared function
    $data = bst_get_booking_details_data($booking, $entry);
    $guest_name = $data['guest_name'];
    $currency_symbol = $data['currency_symbol'];
    $has_coupon = $data['has_coupon'];
    $has_extension = $data['has_extension'];
    
    // Check for pending Bank Wire payments (line status, not amount==0 alone)
    $pending_deposit_eur = 0;
    $pending_balance_eur = 0;

    $dep_amt = floatval( $booking->deposit_payment_amount ?? 0 );
    $bal_amt = floatval( $booking->balance_payment_amount ?? 0 );
    $dep_pending = function_exists( 'bst_payment_line_received_for_display' )
        ? ! bst_payment_line_received_for_display( $booking->deposit_payment_status ?? '', $dep_amt )
        : ( $dep_amt == 0 );
    $bal_pending = function_exists( 'bst_payment_line_received_for_display' )
        ? ! bst_payment_line_received_for_display( $booking->balance_payment_status ?? '', $bal_amt )
        : ( $bal_amt == 0 );

    if ( ! empty( $booking->deposit_payment_method ) &&
        $booking->deposit_payment_method === 'Bank Wire' &&
        $dep_pending &&
        $entry ) {
        $deposit_undiscounted = floatval( rgar( $entry, '177' ) );
        $deposit_discount     = floatval( rgar( $entry, '229' ) );
        $pending_deposit_eur  = $deposit_undiscounted - $deposit_discount;
    }

    if ( ! empty( $booking->balance_payment_method ) &&
        $booking->balance_payment_method === 'Bank Wire' &&
        $bal_pending ) {
        $pending_balance_eur = floatval( $booking->balance_due ?? 0 ) - $pending_deposit_eur;
    }

    // Same pending note as admin financials tile (Pending/Processing inflow lines)
    $pending_payment_note_html = function_exists( 'bst_booking_pending_payment_note_html' ) ? bst_booking_pending_payment_note_html( $booking ) : '';
    
    // Calculate balance due date using centralized helper function
    $balance_due_date_formatted = bst_calculate_balance_due_date($booking);
    $balance_due_timestamp = !empty($balance_due_date_formatted) ? strtotime($balance_due_date_formatted) : null;
    $tour_start_date = null;
    
    // Get tour start date for finalization window calculation
    if (!empty($booking->tour_date_id)) {
        $tour_date_id = intval(explode('|', $booking->tour_date_id)[0]);
        $start_date_str = get_field('start_date', $tour_date_id);
        
        if (!empty($start_date_str)) {
            $tour_start_date = strtotime($start_date_str);
        }
    }
    
    // Check if finalization link should be shown
    $show_finalization_link = false;
    $finalization_url = '';
    $finalization_due_date = '';
    $is_overdue = false;
    if (strtolower($booking->booking_status) === 'booked') {
        // Use the calculated balance due date from terms
        $finalization_due_date = $balance_due_date_formatted;
        
        // Check if tour is within finalization window
        if ($tour_start_date) {
            $days_until_tour = floor(($tour_start_date - time()) / 86400);
            $finalization_sent_days = get_option('bst_finalization_sent_days', 120);
            
            // Show finalization notice only if within sent window
            if ($days_until_tour > 0 && $days_until_tour <= $finalization_sent_days && !empty($finalization_due_date)) {
                $show_finalization_link = true;
                // Build finalization URL using encoded booking ID
                $finalization_url = bst_get_finalization_url($booking->id);
                
                // Check if overdue based on grace period setting
                if ($balance_due_timestamp) {
                    $overdue_grace_days = get_option('bst_finalization_overdue_grace_days', 7);
                    $overdue_threshold = strtotime('+' . $overdue_grace_days . ' days', $balance_due_timestamp);
                    $today = time();
                    $is_overdue = $today > $overdue_threshold;
                }
            }
        }
    }
    
    // Build output HTML with inline styles
    ob_start();
    ?>
    <style>
        .booking-details-container { max-width: 900px; margin: 0 auto; padding: 20px; font-size: 0.9em; background: #f5f5f5; }
        .booking-details-container h2 { color: #0073aa; font-size: 1.8em; font-weight: bold; margin-bottom: 0; text-align: center; }
        .booking-details-section { background: white; padding: 15px; border-radius: 4px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .booking-details-section h3 { font-size: 1.2em; margin: 0 0 15px 0; padding: 10px 15px; background: #0073aa; color: white; border-radius: 4px 4px 0 0; margin: -15px -15px 15px -15px; text-align: center; }
        .booking-details-table { width: 100%; border-collapse: collapse; }
        .booking-details-table td { padding: 8px 5px; border-bottom: 1px solid #e8e8e8; vertical-align: top; font-size: 0.95em; }
        .booking-details-table tr:last-child td { border-bottom: none; }
        .booking-details-table td.label-col { width: 40%; text-align: right; padding-right: 15px; font-weight: bold; color: #333; }
        .booking-details-table td.value-col { text-align: left; padding-left: 15px; }
        .booking-status-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: bold; }
        .status-booked { background: #28a745; color: white; }
        .status-pending { background: #ffc107; color: #333; }
        .status-finalized { background: #007bff; color: white; }
        .status-reserved { background: #6c757d; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
        .finalization-notice { background: #d1ecf1; padding: 20px; border-radius: 8px; border-left: 4px solid #0c5460; margin-bottom: 30px; }
        .finalization-notice h3 { color: #0c5460; margin-top: 0; }
        .finalization-button { display: inline-block; background: #2c5f2d; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 10px; }
        .finalization-button:hover { background: #1f4320; }
    </style>
    <div class="booking-details-container">
        <h2>Booking Details</h2>
        
        <?php if ($show_finalization_link && !empty($finalization_due_date) && !(isset($_GET['type']) && $_GET['type'] === 'bst')): ?>
        <div style="background: <?php echo $is_overdue ? '#fff3cd' : '#d1ecf1'; ?>; padding: 15px; border-radius: 8px; border-left: 4px solid <?php echo $is_overdue ? '#ffc107' : '#17a2b8'; ?>; margin-bottom: 20px; text-align: center;">
            <p style="margin: 0; font-weight: 600; color: <?php echo $is_overdue ? '#856404' : '#0c5460'; ?>; font-size: 1.05em;">
                <?php if ($is_overdue): ?>
                    📋 Please complete your booking finalization. The due date was <strong><?php echo esc_html($finalization_due_date); ?></strong>.
                <?php else: ?>
                    ⏰ Your booking finalization is due by <strong><?php echo esc_html($finalization_due_date); ?></strong>.
                <?php endif; ?>
                <a href="#finalization-section" style="color: <?php echo $is_overdue ? '#856404' : '#0c5460'; ?>; text-decoration: underline; font-weight: bold;">Complete finalization below</a>
            </p>
        </div>
        <?php endif; ?>
        
        <br>
        <div class="booking-details-section">
            <h3>Booking Information</h3>
            <table class="booking-details-table">
                <tr>
                    <td class="label-col">Booking ID:</td>
                    <td class="value-col"><?php echo esc_html($booking->id); ?></td>
                </tr>
                <tr>
                    <td class="label-col">Booking Date:</td>
                    <td class="value-col"><?php 
                        // Use booking created_at, fallback to entry date_created if needed
                        $booking_date = !empty($booking->created_at) ? $booking->created_at : '';
                        if (empty($booking_date) && $entry) {
                            $booking_date = $entry['date_created'];
                        }
                        echo esc_html(date('Y-m-d', strtotime($booking_date))); 
                    ?></td>
                </tr>
                <tr>
                    <td class="label-col">Status:</td>
                    <td class="value-col"><span class="booking-status-badge status-<?php echo esc_attr(strtolower($booking->booking_status)); ?>"><?php echo esc_html($booking->booking_status); ?></span></td>
                </tr>
                <?php 
                // Show finalization due date if finalization not completed and status is Booked or Pending
                $status_lower = strtolower($booking->booking_status);
                if (empty($booking->finalization_entry_id) && ($status_lower === 'booked' || $status_lower === 'pending') && !empty($balance_due_date_formatted)): 
                ?>
                <tr>
                    <td class="label-col">Finalization Due Date:</td>
                    <td class="value-col"><strong><?php echo esc_html(date('Y-m-d', $balance_due_timestamp)); ?></strong></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="label-col"><?php echo !empty($data['guest2_first']) ? 'Guests:' : 'Guest:'; ?></td>
                    <td class="value-col"><?php echo esc_html($guest_name); ?></td>
                </tr>
            </table>
        </div>
        
        <?php if (!empty($booking->guest2_first_name)): ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div class="booking-details-section">
                <h3><?php echo esc_html($booking->guest1_first_name . ' ' . $booking->guest1_last_name); ?></h3>
                <table class="booking-details-table">
                    <tr>
                        <td class="label-col">Email:</td>
                        <td class="value-col"><?php echo esc_html($booking->guest1_email); ?></td>
                    </tr>
                    <?php if (!empty($booking->guest1_phone)): ?>
                    <tr>
                        <td class="label-col">Phone:</td>
                        <td class="value-col"><?php echo esc_html(bst_format_phone_international($booking->guest1_phone)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest1_address_line1) || !empty($booking->guest1_address_line2) || !empty($booking->guest1_city) || !empty($booking->guest1_state_province) || !empty($booking->guest1_postal_code) || !empty($booking->guest1_country)): ?>
                    <tr>
                        <td class="label-col">Address:</td>
                        <td class="value-col">
                            <?php 
                            $address_lines = array();
                            if (!empty($booking->guest1_address_line1)) $address_lines[] = esc_html($booking->guest1_address_line1);
                            if (!empty($booking->guest1_address_line2)) $address_lines[] = esc_html($booking->guest1_address_line2);
                            $location_parts = array_filter(array($booking->guest1_city, $booking->guest1_state_province, $booking->guest1_postal_code));
                            if (!empty($location_parts)) $address_lines[] = esc_html(implode(', ', $location_parts));
                            if (!empty($booking->guest1_country)) $address_lines[] = esc_html($booking->guest1_country);
                            echo implode('<br>', $address_lines);
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest1_nickname)): ?>
                    <tr>
                        <td class="label-col">Preferred Name/Nickname:</td>
                        <td class="value-col"><?php echo esc_html($booking->guest1_nickname); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest1_shirt_size)): ?>
                    <tr>
                        <td class="label-col">Shirt Size:</td>
                        <td class="value-col"><?php echo esc_html(bst_get_shirt_size_display($booking->guest1_shirt_size)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest1_driving_status)): ?>
                    <tr>
                        <td class="label-col">Driving Status:</td>
                        <td class="value-col"><?php echo esc_html($booking->guest1_driving_status); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest1_dietary_restrictions)): ?>
                    <tr>
                        <td class="label-col">Food Intolerances:</td>
                        <td class="value-col"><?php echo nl2br(esc_html($booking->guest1_dietary_restrictions)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest1_medical_insurance)): ?>
                    <tr>
                        <td class="label-col">Medical Insurance:</td>
                        <td class="value-col"><?php echo esc_html($booking->guest1_medical_insurance); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php 
                    $guest1_emergency_display = bst_format_emergency_contact_from_fields(
                        $booking->guest1_emergency_contact_name ?? '',
                        $booking->guest1_emergency_contact_phone ?? '',
                        $booking->guest1_emergency_contact_email ?? ''
                    );
                    if (!empty($guest1_emergency_display)): 
                    ?>
                    <tr>
                        <td class="label-col">Emergency Contact:</td>
                        <td class="value-col"><?php echo esc_html($guest1_emergency_display); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest1_travel_details)): ?>
                    <tr>
                        <td class="label-col">Travel Details:</td>
                        <td class="value-col"><?php echo nl2br(esc_html($booking->guest1_travel_details)); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <div class="booking-details-section">
                <h3><?php echo esc_html($booking->guest2_first_name . ' ' . $booking->guest2_last_name); ?></h3>
                <table class="booking-details-table">
                    <?php if (!empty($booking->guest2_email)): ?>
                    <tr>
                        <td class="label-col">Email:</td>
                        <td class="value-col"><?php echo esc_html($booking->guest2_email); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest2_phone)): ?>
                    <tr>
                        <td class="label-col">Phone:</td>
                        <td class="value-col"><?php echo esc_html(bst_format_phone_international($booking->guest2_phone)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest2_address_line1) || !empty($booking->guest2_address_line2) || !empty($booking->guest2_city) || !empty($booking->guest2_state_province) || !empty($booking->guest2_postal_code) || !empty($booking->guest2_country)): ?>
                    <tr>
                        <td class="label-col">Address:</td>
                        <td class="value-col">
                            <?php 
                            $address_lines = array();
                            if (!empty($booking->guest2_address_line1)) $address_lines[] = esc_html($booking->guest2_address_line1);
                            if (!empty($booking->guest2_address_line2)) $address_lines[] = esc_html($booking->guest2_address_line2);
                            $location_parts = array_filter(array($booking->guest2_city, $booking->guest2_state_province, $booking->guest2_postal_code));
                            if (!empty($location_parts)) $address_lines[] = esc_html(implode(', ', $location_parts));
                            if (!empty($booking->guest2_country)) $address_lines[] = esc_html($booking->guest2_country);
                            echo implode('<br>', $address_lines);
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest2_nickname)): ?>
                    <tr>
                        <td class="label-col">Preferred Name/Nickname:</td>
                        <td class="value-col"><?php echo esc_html($booking->guest2_nickname); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest2_shirt_size)): ?>
                    <tr>
                        <td class="label-col">Shirt Size:</td>
                        <td class="value-col"><?php echo esc_html(bst_get_shirt_size_display($booking->guest2_shirt_size)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest2_driving_status)): ?>
                    <tr>
                        <td class="label-col">Driving Status:</td>
                        <td class="value-col"><?php echo esc_html($booking->guest2_driving_status); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest2_dietary_restrictions)): ?>
                    <tr>
                        <td class="label-col">Food Intolerances:</td>
                        <td class="value-col"><?php echo nl2br(esc_html($booking->guest2_dietary_restrictions)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest2_medical_insurance)): ?>
                    <tr>
                        <td class="label-col">Medical Insurance:</td>
                        <td class="value-col"><?php echo esc_html($booking->guest2_medical_insurance); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php 
                    $guest2_emergency_display = bst_format_emergency_contact_from_fields(
                        $booking->guest2_emergency_contact_name ?? '',
                        $booking->guest2_emergency_contact_phone ?? '',
                        $booking->guest2_emergency_contact_email ?? ''
                    );
                    if (!empty($guest2_emergency_display)): 
                    ?>
                    <tr>
                        <td class="label-col">Emergency Contact:</td>
                        <td class="value-col"><?php echo esc_html($guest2_emergency_display); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->guest2_travel_details)): ?>
                    <tr>
                        <td class="label-col">Travel Details:</td>
                        <td class="value-col"><?php echo nl2br(esc_html($booking->guest2_travel_details)); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="booking-details-section">
            <h3>Guest Details</h3>
            <table class="booking-details-table">
                <tr>
                    <td class="label-col">Email:</td>
                    <td class="value-col"><?php echo esc_html($booking->guest1_email); ?></td>
                </tr>
                <?php if (!empty($booking->guest1_phone)): ?>
                <tr>
                    <td class="label-col">Phone:</td>
                    <td class="value-col"><?php echo esc_html(bst_format_phone_international($booking->guest1_phone)); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->guest1_address_line1) || !empty($booking->guest1_address_line2) || !empty($booking->guest1_city) || !empty($booking->guest1_state_province) || !empty($booking->guest1_postal_code) || !empty($booking->guest1_country)): ?>
                <tr>
                    <td class="label-col">Address:</td>
                    <td class="value-col">
                        <?php 
                        $address_lines = array();
                        if (!empty($booking->guest1_address_line1)) $address_lines[] = esc_html($booking->guest1_address_line1);
                        if (!empty($booking->guest1_address_line2)) $address_lines[] = esc_html($booking->guest1_address_line2);
                        $location_parts = array_filter(array($booking->guest1_city, $booking->guest1_state_province, $booking->guest1_postal_code));
                        if (!empty($location_parts)) $address_lines[] = esc_html(implode(', ', $location_parts));
                        if (!empty($booking->guest1_country)) $address_lines[] = esc_html($booking->guest1_country);
                        echo implode('<br>', $address_lines);
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->guest1_shirt_size)): ?>
                <tr>
                    <td class="label-col">Shirt Size:</td>
                    <td class="value-col"><?php echo esc_html(bst_get_shirt_size_display($booking->guest1_shirt_size)); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->guest1_driving_status)): ?>
                <tr>
                    <td class="label-col">Driving Status:</td>
                    <td class="value-col"><?php echo esc_html($booking->guest1_driving_status); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->guest1_dietary_restrictions)): ?>
                <tr>
                    <td class="label-col">Food Intolerances:</td>
                    <td class="value-col"><?php echo nl2br(esc_html($booking->guest1_dietary_restrictions)); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->guest1_medical_insurance)): ?>
                <tr>
                    <td class="label-col">Medical Insurance:</td>
                    <td class="value-col"><?php echo esc_html($booking->guest1_medical_insurance); ?></td>
                </tr>
                <?php endif; ?>
                <?php 
                $guest1_emergency_display = bst_format_emergency_contact_from_fields(
                    $booking->guest1_emergency_contact_name ?? '',
                    $booking->guest1_emergency_contact_phone ?? '',
                    $booking->guest1_emergency_contact_email ?? ''
                );
                if (!empty($guest1_emergency_display)): 
                ?>
                <tr>
                    <td class="label-col">Emergency Contact:</td>
                    <td class="value-col"><?php echo esc_html($guest1_emergency_display); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->guest1_travel_details)): ?>
                <tr>
                    <td class="label-col">Travel Details:</td>
                    <td class="value-col"><?php echo nl2br(esc_html($booking->guest1_travel_details)); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <?php 
        $emergency_contact_display = bst_format_emergency_contact_from_fields(
            $booking->emergency_contact_name ?? '',
            $booking->emergency_contact_phone ?? '',
            $booking->emergency_contact_email ?? ''
        );
        if (!empty($emergency_contact_display) || !empty($booking->emergency_contact_relationship)): 
        ?>
        <div class="booking-details-section">
            <h3>Emergency Contact</h3>
            <table class="booking-details-table">
                <?php if (!empty($emergency_contact_display)): ?>
                <tr>
                    <td class="label-col">Contact:</td>
                    <td class="value-col"><?php echo esc_html($emergency_contact_display); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->emergency_contact_relationship)): ?>
                <tr>
                    <td class="label-col">Relationship:</td>
                    <td class="value-col"><?php echo esc_html($booking->emergency_contact_relationship); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($booking->arrival_date) || !empty($booking->departure_date) || !empty($booking->pre_tour_hotel) || !empty($booking->post_tour_hotel)): ?>
        <div class="booking-details-section">
            <h3>Travel Details</h3>
            <table class="booking-details-table">
                <?php if (!empty($booking->arrival_date)): ?>
                <tr>
                    <td class="label-col">Arrival Date:</td>
                    <td class="value-col"><?php echo esc_html($booking->arrival_date); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->arrival_time)): ?>
                <tr>
                    <td class="label-col">Arrival Time:</td>
                    <td class="value-col"><?php echo esc_html($booking->arrival_time); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->arrival_flight)): ?>
                <tr>
                    <td class="label-col">Arrival Flight:</td>
                    <td class="value-col"><?php echo esc_html($booking->arrival_flight); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->departure_date)): ?>
                <tr>
                    <td class="label-col">Departure Date:</td>
                    <td class="value-col"><?php echo esc_html($booking->departure_date); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->departure_time)): ?>
                <tr>
                    <td class="label-col">Departure Time:</td>
                    <td class="value-col"><?php echo esc_html($booking->departure_time); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->departure_flight)): ?>
                <tr>
                    <td class="label-col">Departure Flight:</td>
                    <td class="value-col"><?php echo esc_html($booking->departure_flight); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->pre_tour_hotel)): ?>
                <tr>
                    <td class="label-col">Pre-Tour Hotel:</td>
                    <td class="value-col"><?php echo esc_html($booking->pre_tour_hotel); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->post_tour_hotel)): ?>
                <tr>
                    <td class="label-col">Post-Tour Hotel:</td>
                    <td class="value-col"><?php echo esc_html($booking->post_tour_hotel); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($booking->passport_name) || !empty($booking->passport_number) || !empty($booking->tour_insurance)): ?>
        <div class="booking-details-section">
            <h3>Passport & Insurance</h3>
            <table class="booking-details-table">
                <?php if (!empty($booking->passport_name)): ?>
                <tr>
                    <td class="label-col">Passport Name:</td>
                    <td class="value-col"><?php echo esc_html($booking->passport_name); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->passport_number)): ?>
                <tr>
                    <td class="label-col">Passport Number:</td>
                    <td class="value-col"><?php echo esc_html($booking->passport_number); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->passport_expiration)): ?>
                <tr>
                    <td class="label-col">Passport Expiration:</td>
                    <td class="value-col"><?php echo esc_html($booking->passport_expiration); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->passport_country)): ?>
                <tr>
                    <td class="label-col">Passport Country:</td>
                    <td class="value-col"><?php echo esc_html($booking->passport_country); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->tour_insurance)): ?>
                <tr>
                    <td class="label-col">Tour Insurance:</td>
                    <td class="value-col"><?php echo esc_html($booking->tour_insurance); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($booking->dietary_restrictions) || !empty($booking->medical_conditions)): ?>
        <div class="booking-details-section">
            <h3>Health Information</h3>
            <table class="booking-details-table">
                <?php if (!empty($booking->dietary_restrictions)): ?>
                <tr>
                    <td class="label-col">Dietary Restrictions:</td>
                    <td class="value-col"><?php echo esc_html($booking->dietary_restrictions); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->medical_conditions)): ?>
                <tr>
                    <td class="label-col">Medical Conditions:</td>
                    <td class="value-col"><?php echo esc_html($booking->medical_conditions); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="booking-details-section">
            <h3>Tour Information</h3>
            <table class="booking-details-table">
                <tr>
                    <td class="label-col">Tour:</td>
                    <td class="value-col"><?php 
                        $tour_display = $booking->tour_text;
                        if (!empty($booking->tour_date_text)) {
                            $tour_display .= ' (' . $booking->tour_date_text . ')';
                        }
                        echo esc_html($tour_display); 
                    ?></td>
                </tr>
                <?php if ($has_extension && !empty($booking->tour_extension_text)): ?>
                <tr>
                    <td class="label-col">Tour Extension:</td>
                    <td class="value-col"><?php 
                        $extension_display = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $booking->tour_extension_text);
                        if (!empty($booking->tour_extension_date_text)) {
                            $extension_display .= ' (' . $booking->tour_extension_date_text . ')';
                        }
                        echo esc_html($extension_display); 
                    ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="label-col">Package:</td>
                    <td class="value-col"><?php echo esc_html($booking->tour_package_text); ?></td>
                </tr>
                <?php if (!empty($booking->participant_sex)): ?>
                <tr>
                    <td class="label-col">Participant Sex:</td>
                    <td class="value-col"><?php echo esc_html($booking->participant_sex); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->sharing_preference)): ?>
                <tr>
                    <td class="label-col">Room Sharing:</td>
                    <td class="value-col"><?php echo esc_html($booking->sharing_preference); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->bed_preference)): ?>
                <tr>
                    <td class="label-col">Bed Preference:</td>
                    <td class="value-col"><?php echo esc_html($booking->bed_preference); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->vehicle1)): ?>
                <tr>
                    <td class="label-col"><?php echo !empty($booking->vehicle2) ? 'Vehicles:' : 'Vehicle:'; ?></td>
                    <td class="value-col"><?php 
                        $vehicle_display = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $booking->vehicle1);
                        if (!empty($booking->vehicle2)) {
                            $vehicle_display .= ' / ' . preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $booking->vehicle2);
                        }
                        echo esc_html($vehicle_display);
                    ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->motor_club)): ?>
                <tr>
                    <td class="label-col">Motor Club:</td>
                    <td class="value-col"><?php echo esc_html($booking->motor_club); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->how_heard)): ?>
                <tr>
                    <td class="label-col">How Heard:</td>
                    <td class="value-col"><?php echo esc_html($booking->how_heard); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($has_coupon): ?>
                <tr>
                    <td class="label-col">Tour Price:</td>
                    <td class="value-col"><?php echo esc_html($currency_symbol . number_format($booking->tour_price, 2)); ?></td>
                </tr>
                <tr>
                    <td class="label-col">Coupon:</td>
                    <td class="value-col">-<?php echo esc_html($currency_symbol . number_format($booking->coupon_amount, 2)); ?><?php if (!empty($booking->coupon_code)): ?> <span style="font-weight: normal;">(<?php echo esc_html($booking->coupon_code); ?>)</span><?php endif; ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="label-col"><?php echo $has_coupon ? 'Net Tour Price:' : 'Tour Price:'; ?></td>
                    <td class="value-col"><strong><?php echo esc_html($currency_symbol . number_format($booking->net_tour_price, 2)); ?></strong></td>
                </tr>
                <?php if (!empty($booking->additional_charge) && floatval($booking->additional_charge) != 0): ?>
                <tr>
                    <td class="label-col">Additional Charge:</td>
                    <td class="value-col"><?php echo esc_html($currency_symbol . number_format($booking->additional_charge, 2)); ?></td>
                </tr>
                <tr>
                    <td class="label-col">Total Due:</td>
                    <td class="value-col"><strong><?php echo esc_html($currency_symbol . number_format($booking->net_tour_price + $booking->additional_charge, 2)); ?></strong></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="label-col">Total Paid:</td>
                    <td class="value-col">
                        <?php echo esc_html($currency_symbol . number_format($booking->total_paid, 2)); ?>
                        <?php if ($pending_deposit_eur > 0): ?>
                            (Awaiting <?php echo esc_html($currency_symbol . number_format($pending_deposit_eur, 2)); ?> Deposit Payment via Bank Transfer)
                        <?php elseif ($pending_balance_eur > 0): ?>
                            (Awaiting <?php echo esc_html($currency_symbol . number_format($pending_balance_eur, 2)); ?> Balance Payment via Bank Transfer)
                        <?php endif; ?>
                        <?php echo $pending_payment_note_html; ?>
                    </td>
                </tr>
                <?php if (!empty($booking->payment_discount_amount) && floatval($booking->payment_discount_amount) > 0): ?>
                <tr>
                    <td class="label-col">Payment Discount:</td>
                    <td class="value-col">-<?php echo esc_html($currency_symbol . number_format($booking->payment_discount_amount, 2)); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="label-col">Remaining Balance:</td>
                    <td class="value-col">
                        <strong><?php echo esc_html($currency_symbol . number_format($booking->balance_due, 2)); ?></strong>
                        <?php if ($pending_deposit_eur > 0): ?>
                            (<?php echo esc_html($currency_symbol . number_format($booking->balance_due - $pending_deposit_eur, 2)); ?> after Deposit Payment received)
                        <?php elseif ($pending_balance_eur > 0): ?>
                            (<?php echo esc_html($currency_symbol . number_format($booking->balance_due - $pending_balance_eur, 2)); ?> after Balance Payment received)
                        <?php endif; ?>
                        <?php echo $pending_payment_note_html; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="booking-details-section">
            <h3>Payment History</h3>
            <table class="booking-details-table" style="text-align: left; width: 100%;">
                <thead>
                    <tr style="border-bottom: 2px solid #0073aa;">
                        <th style="padding: 8px; text-align: left;">Payment</th>
                        <th style="padding: 8px; text-align: center;">Date</th>
                        <th style="padding: 8px; text-align: center;">Amount</th>
                        <th style="padding: 8px; text-align: center;">Method</th>
                        <th style="padding: 8px; text-align: center;">Type</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Display deposit payment
                if (!empty($booking->deposit_payment_amount) && floatval($booking->deposit_payment_amount) > 0):
                    $deposit_method = $booking->deposit_payment_method ?? '';
                    $deposit_card_type = '';
                    if ($deposit_method === 'Credit Card' && $entry) {
                        $deposit_card_type = bst_get_card_type($entry, rgar($entry, 'form_id'));
                        $deposit_card_type = $deposit_card_type ? str_replace('Sepa', 'SEPA', ucwords($deposit_card_type)) : '';
                    } elseif ($deposit_method === 'Bank Wire' && $entry) {
                        $region_code = rgar($entry, '232', 'Other');
                        $deposit_card_type = bst_region_to_currency_code($region_code);
                    }
                ?>
                <tr>
                    <td style="padding: 8px; text-align: left;">Deposit Pmt</td>
                    <td style="padding: 8px; text-align: center;"><?php echo !empty($booking->deposit_payment_date) ? esc_html(date('Y-m-d', strtotime($booking->deposit_payment_date))) : ''; ?></td>
                    <td style="padding: 8px; text-align: center;"><?php echo esc_html($currency_symbol . number_format($booking->deposit_payment_amount, 2)); ?></td>
                    <td style="padding: 8px; text-align: center;"><?php echo esc_html($deposit_method === 'Bank Wire' ? 'Bank Transfer' : $deposit_method); ?></td>
                    <td style="padding: 8px; text-align: center;"><?php echo esc_html($deposit_card_type); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php
                // Display balance payment
                if (!empty($booking->balance_payment_amount) && floatval($booking->balance_payment_amount) > 0):
                    $balance_method = $booking->balance_payment_method ?? '';
                    $balance_card_type = '';
                    if ($balance_method === 'Credit Card') {
                        // Balance payment is from finalization entry (form 10) - field 275
                        // First check if current entry is the finalization entry
                        if ($entry && $entry['form_id'] == 10) {
                            $balance_card_type = bst_get_card_type($entry, 10);
                            $balance_card_type = $balance_card_type ? str_replace('Sepa', 'SEPA', ucwords($balance_card_type)) : '';
                        } elseif (!empty($booking->finalization_entry_id)) {
                            $finalization_entry = GFAPI::get_entry($booking->finalization_entry_id);
                            if ($finalization_entry && is_array($finalization_entry)) {
                                $balance_card_type = bst_get_card_type($finalization_entry, 10);
                                $balance_card_type = $balance_card_type ? str_replace('Sepa', 'SEPA', ucwords($balance_card_type)) : '';
                            }
                        }
                    } elseif ($balance_method === 'Bank Wire') {
                        // Get region code from form 10
                        if ($entry && $entry['form_id'] == 10) {
                            $region_code = rgar($entry, '280', 'Other');
                            $balance_card_type = bst_region_to_currency_code($region_code);
                        } elseif (!empty($booking->finalization_entry_id)) {
                            $finalization_entry = GFAPI::get_entry($booking->finalization_entry_id);
                            if ($finalization_entry && is_array($finalization_entry)) {
                                $region_code = rgar($finalization_entry, '280', 'Other');
                                $balance_card_type = bst_region_to_currency_code($region_code);
                            }
                        }
                    }
                ?>
                <tr>
                    <td style="padding: 8px; text-align: left;">Balance Pmt</td>
                    <td style="padding: 8px; text-align: center;"><?php echo !empty($booking->balance_payment_date) ? esc_html(date('Y-m-d', strtotime($booking->balance_payment_date))) : ''; ?></td>
                    <td style="padding: 8px; text-align: center;"><?php echo esc_html($currency_symbol . number_format($booking->balance_payment_amount, 2)); ?></td>
                    <td style="padding: 8px; text-align: center;"><?php echo esc_html($balance_method === 'Bank Wire' ? 'Bank Transfer' : $balance_method); ?></td>
                    <td style="padding: 8px; text-align: center;"><?php echo esc_html($balance_card_type); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php
                // Display additional payment
                if (!empty($booking->additional_payment_amount) && floatval($booking->additional_payment_amount) > 0):
                    $additional_method = $booking->additional_payment_method ?? '';
                    // No type lookup — no GF entry exists for additional payments yet.
                    // Do NOT use $entry here; it belongs to the deposit/balance form and would show the wrong card type.
                    $additional_card_type = '';
                ?>
                <tr>
                    <td style="padding: 8px; text-align: left;">Additional Payment</td>
                    <td style="padding: 8px; text-align: center;"><?php echo !empty($booking->additional_payment_date) ? esc_html(date('Y-m-d', strtotime($booking->additional_payment_date))) : ''; ?></td>
                    <td style="padding: 8px; text-align: center;"><?php echo esc_html($currency_symbol . number_format($booking->additional_payment_amount, 2)); ?></td>
                    <td style="padding: 8px; text-align: center;"><?php echo esc_html($additional_method); ?></td>
                    <td style="padding: 8px; text-align: center;"><?php echo esc_html($additional_card_type); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php
                // Display refund if any
                if (!empty($booking->refund_payment_amount) && floatval($booking->refund_payment_amount) > 0):
                ?>
                <tr>
                    <td style="padding: 8px; text-align: left;">Refund</td>
                    <td style="padding: 8px; text-align: center;"><?php echo !empty($booking->refund_payment_date) ? esc_html(date('Y-m-d', strtotime($booking->refund_payment_date))) : ''; ?></td>
                    <td style="padding: 8px; text-align: center;">-<?php echo esc_html($currency_symbol . number_format($booking->refund_payment_amount, 2)); ?></td>
                    <td style="padding: 8px; text-align: center;" colspan="2"></td>
                </tr>
                <?php endif; ?>
                
                <?php
                // If no payments at all, show a message
                $has_any_payment = (!empty($booking->deposit_payment_amount) && floatval($booking->deposit_payment_amount) > 0) ||
                               (!empty($booking->balance_payment_amount) && floatval($booking->balance_payment_amount) > 0) ||
                               (!empty($booking->additional_payment_amount) && floatval($booking->additional_payment_amount) > 0);
                if (!$has_any_payment):
                    // Check if there's a pending bank wire payment
                    $has_pending_wire = ($pending_deposit_eur > 0 || $pending_balance_eur > 0);
                    $no_payment_text = $has_pending_wire ? 'No payments received yet (bank wire payment pending).' : 'No payments recorded yet.';
                ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 8px;"><?php echo esc_html($no_payment_text); ?></td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php
        // Add expandable bank wire instructions section after payments (if any bank wire payments exist)
        if ($display_type === 'customer') {
            // Customer view: expandable, auto-opens if pending
            echo bst_generate_bank_wire_section($booking, $entry, false, false, 'details');
        } else {
            // BST view: expandable but always closed (never auto-opens)
            echo bst_generate_bank_wire_section($booking, $entry, false, true, 'details');
        }
        ?>
        
        <?php
        // Add Pro Forma Invoice section if invoice exists
        if (!empty($booking->booking_invoice_number) && $booking->booking_invoice_number !== 'Not generated' && !empty($booking->finalization_entry_id)):
            $encoded_entry_id = bst_encode_booking_id($booking->finalization_entry_id);
            $invoice_url = site_url('/bookinginvoice/') . '?eid=' . $encoded_entry_id . '&lang=en';
            
            // Calculate totals for summary - use pre-calculated tour package amount from booking
            $vehicle_total = floatval($booking->booking_vehicle_1_use_amount ?? 0) + floatval($booking->booking_vehicle_2_use_amount ?? 0);
            $tour_cost = floatval($booking->booking_tour_package_amount ?? 0);
            
            // Get VAT info
            $eu_percent = !empty($booking->booking_eu_percent) ? floatval($booking->booking_eu_percent) : 100.00;
            $vat_rate = !empty($booking->booking_vat_rate) ? floatval($booking->booking_vat_rate) : 22.00;
            
            // Calculate VAT
            $tour_vat = ($tour_cost * ($eu_percent / 100)) * ($vat_rate / 100);
            $vehicle_vat = ($vehicle_total * ($eu_percent / 100)) * ($vat_rate / 100);
            $total_with_vat = $tour_cost + $vehicle_total + $tour_vat + $vehicle_vat;
        ?>
        <div class="booking-details-section">
            <h3>Pro Forma Invoice</h3>
            <div style="padding: 15px; background: #f0f8ff; border-left: 4px solid #0066cc; margin-bottom: 15px;">
                <p style="margin: 0 0 10px 0; font-size: 0.95em; line-height: 1.6;">
                    <strong>Note:</strong> This pro forma invoice is a detailed receipt provided for EU regulatory compliance. 
                    It is for your records only and <strong>does not require payment</strong> — all amounts shown have already been paid. 
                    VAT is included in the prices and is handled by Blue Strada Tours.
                </p>
            </div>
            
            <table class="booking-details-table">
                <tr>
                    <td class="label-col">Invoice Number:</td>
                    <td class="value-col"><strong><?php echo esc_html($booking->booking_invoice_number); ?></strong></td>
                </tr>
                <tr>
                    <td class="label-col">Invoice Date:</td>
                    <td class="value-col"><?php echo !empty($booking->booking_invoice_date) ? esc_html(date('Y-m-d', strtotime($booking->booking_invoice_date))) : esc_html(date('Y-m-d')); ?></td>
                </tr>
                <tr>
                    <td class="label-col">Tour Packages:</td>
                    <td class="value-col"><?php echo esc_html($currency_symbol . number_format($tour_cost, 2)); ?></td>
                </tr>
                <?php if ($vehicle_total > 0): ?>
                <tr>
                    <td class="label-col">Other Services:</td>
                    <td class="value-col"><?php echo esc_html($currency_symbol . number_format($vehicle_total, 2)); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="label-col">View Full Invoice:</td>
                    <td class="value-col"><a href="<?php echo esc_url($invoice_url); ?>" target="_blank" style="color: #0066cc; text-decoration: none; font-weight: 600;">Open Pro Forma Invoice</a></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>
        
        <?php 
        // Terms and Conditions Sections
        if ($booking->booking_entry_id): 
            $booking_entry = GFAPI::get_entry($booking->booking_entry_id);
            if ($booking_entry && !is_wp_error($booking_entry)):
        ?>
        <!-- Booking Terms and Conditions -->
        <div class="booking-details-section terms-section">
            <h3>Booking Terms and Conditions</h3>
            <div class="terms-container">
                <?php
                $booking_terms = array(
                    '36' => 'Acceptance of the Registration Terms',
                    '37' => 'Acceptance of Specific Clauses',
                    '38' => 'Privacy and Cookies Consent'
                );
                
                foreach ($booking_terms as $field_id => $label):
                    $consented = rgar($booking_entry, $field_id . '.1');
                    $consent_date = !empty($booking_entry['date_created']) ? date('F j, Y', strtotime($booking_entry['date_created'])) : '';
                    
                    // Get the consent text using GF's method which handles revision lookups
                    $field = GFAPI::get_field(9, $field_id);
                    $consent_text = '';
                    
                    if ($field && $consented) {
                        // Use the field's get_value_export method which retrieves historical consent text from revisions
                        $consent_text = $field->get_value_export($booking_entry, $field_id . '.3');
                    }
                ?>
                <div class="term-item">
                    <details>
                        <summary>
                            <span class="arrow">▶</span>
                            <?php if ($consented): ?>
                                <span class="consent-check">✓</span>
                            <?php else: ?>
                                <span class="consent-check unchecked">○</span>
                            <?php endif; ?>
                            <?php echo esc_html($label); ?>
                            <?php if ($consented && $consent_date): ?>
                                <span class="consent-date-inline"> - Agreed on <?php echo esc_html($consent_date); ?></span>
                            <?php endif; ?>
                        </summary>
                        <?php if (!empty($consent_text)): ?>
                        <div class="consent-text">
                            <?php echo wp_kses_post($consent_text); ?>
                        </div>
                        <?php endif; ?>
                    </details>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        
        <?php 
            endif;
        endif;
        
        // Finalization Terms and Conditions (only show if finalized)
        if (!empty($booking->finalization_entry_id)): 
            $finalization_entry = GFAPI::get_entry($booking->finalization_entry_id);
            if ($finalization_entry && !is_wp_error($finalization_entry)):
        ?>
        <!-- Finalization Terms and Conditions -->
        <div class="booking-details-section terms-section">
            <h3>Finalization Terms and Conditions</h3>
            <div class="terms-container">
                <?php
                $finalization_terms = array(
                    '36' => 'Acceptance of the Participation Terms',
                    '37' => 'Acceptance of Specific Clauses',
                    '38' => 'Privacy and Cookies Consent'
                );
                
                foreach ($finalization_terms as $field_id => $label):
                    $consented = rgar($finalization_entry, $field_id . '.1');
                    $consent_date = !empty($finalization_entry['date_created']) ? date('F j, Y', strtotime($finalization_entry['date_created'])) : '';
                    
                    // Get the consent text using GF's method which handles revision lookups
                    $field = GFAPI::get_field(10, $field_id);
                    $consent_text = '';
                    
                    if ($field && $consented) {
                        // Use the field's get_value_export method which retrieves historical consent text from revisions
                        $consent_text = $field->get_value_export($finalization_entry, $field_id . '.3');
                    }
                ?>
                <div class="term-item">
                    <details>
                        <summary>
                            <span class="arrow">▶</span>
                            <?php if ($consented): ?>
                                <span class="consent-check">✓</span>
                            <?php else: ?>
                                <span class="consent-check unchecked">○</span>
                            <?php endif; ?>
                            <?php echo esc_html($label); ?>
                            <?php if ($consented && $consent_date): ?>
                                <span class="consent-date-inline"> - Agreed on <?php echo esc_html($consent_date); ?></span>
                            <?php endif; ?>
                        </summary>
                        <?php if (!empty($consent_text)): ?>
                        <div class="consent-text">
                            <?php echo wp_kses_post($consent_text); ?>
                        </div>
                        <?php endif; ?>
                    </details>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php 
            endif;
        endif;
        ?>
        
        <?php if ($display_type === 'customer' && $show_finalization_link): ?>
        <div class="finalization-notice" id="finalization-section">
            <h3>Finalize Your Booking</h3>
            <p>To complete your booking, please provide the remaining traveler information and pay your outstanding balance<?php if (!empty($finalization_due_date)): ?> by <strong><?php echo esc_html($finalization_due_date); ?></strong><?php endif; ?>. This ensures we have everything needed to prepare for your tour.<?php if (!empty($finalization_url)): ?> Click the button below to get started.<?php else: ?> Please contact us to complete your booking.<?php endif; ?></p>
            <?php if (!empty($finalization_url)): ?>
            <p><a href="<?php echo esc_url($finalization_url); ?>" class="finalization-button">Finalize Your Booking</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($display_type === 'bst'): ?>
        <!-- BST Admin Links -->
        <div class="booking-details-section" style="background: #fff3cd; border-left: 4px solid #856404;">
            <h3 style="background: #856404;">Admin Links</h3>
            <table class="booking-details-table">
                <tr>
                    <td class="label-col">WordPress Admin:</td>
                    <td class="value-col"><a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" style="color: #0073aa; text-decoration: none; font-weight: 600;">Edit Booking in WP Admin</a></td>
                </tr>
                <?php 
                $encoded_id = bst_encode_booking_id($entry_id ? $entry_id : $booking->booking_entry_id);
                if ($encoded_id): 
                ?>
                <tr>
                    <td class="label-col">Customer View:</td>
                    <td class="value-col"><a href="<?php echo esc_url(site_url('/bookingdetails/') . '?eid=' . $encoded_id); ?>" style="color: #0073aa; text-decoration: none; font-weight: 600;">View Customer Details Page</a></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($display_type === 'customer'): ?>
        <div class="booking-contact" style="text-align: center;">
            <h3 style="font-size: 1.3em; color: #0073aa;">Questions/Concerns?</h3>
            <p style="font-size: 0.85em;">If you have any questions about your booking or you need to make changes, please contact us at <a href="mailto:info@bluestradatours.com" style="color: #0073aa; text-decoration: none; font-weight: bold;">info@bluestradatours.com</a></p>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
    /* Remove bottom margin from Payment History when followed by terms */
    .booking-details-section:has(+ .terms-section) {
        margin-bottom: 0 !important;
    }
    
    /* Terms and Conditions Sections */
    .booking-summary.terms-section {
        margin-top: 10px;
        margin-bottom: 10px;
    }
    .terms-section h3 {
        background-color: #0073aa;
        color: white;
        padding: 15px 20px;
        margin: -15px -15px 0 -15px;
        font-size: 18px;
        font-weight: 600;
        border-radius: 4px 4px 0 0;
    }
    .terms-container {
        background-color: #ffffff;
        border: 1px solid #dddddd;
        border-radius: 0 0 4px 4px;
        padding: 8px 5px;
    }
    .term-item {
        margin-bottom: 8px;
        background-color: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 3px;
        overflow: hidden;
    }
    .term-item:last-child {
        margin-bottom: 0;
    }
    .term-item details {
        margin: 0;
    }
    .term-item summary {
        padding: 12px 15px;
        background-color: #f5f5f5;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        list-style: none;
        user-select: none;
        border-bottom: 1px solid #e0e0e0;
        text-align: left;
    }
    .term-item summary::-webkit-details-marker {
        display: none;
    }
    .term-item summary:hover {
        background-color: #eeeeee;
    }
    .term-item summary .arrow {
        display: inline-block;
        margin-right: 8px;
        transition: transform 0.2s;
        color: #0073aa;
    }
    .term-item details[open] summary {
        border-bottom: 1px solid #e0e0e0;
    }
    .term-item details[open] summary .arrow {
        transform: rotate(90deg);
    }
    
    /* Bank Transfer Payment Instructions arrow rotation */
    .bank-wire-details[open] .bank-wire-arrow {
        transform: rotate(90deg);
    }
    
    .consent-check {
        color: #28a745;
        margin-right: 6px;
        font-weight: bold;
    }
    .consent-check.unchecked {
        color: #dc3545;
    }
    .consent-date-inline {
        color: #666666;
        font-size: 13px;
        font-weight: normal;
        margin-left: 8px;
    }
    .consent-text {
        padding: 20px;
        background-color: #ffffff;
        text-align: left;
        line-height: 2;
        font-size: 14px;
        color: #333333;
    }
    
    /* Mobile responsive adjustments */
    @media screen and (max-width: 768px) {
        /* Slightly larger font for booking summary details on mobile */
        .booking-details-section table td {
            font-size: 13px !important;
        }
        
        /* Hide Card Type column on mobile for Payment History */
        .booking-details-table th:nth-child(5),
        .booking-details-table td:nth-child(5) {
            display: none;
        }
        
        /* Reduce payment history table font size on mobile */
        .booking-details-table th,
        .booking-details-table td {
            font-size: 12px !important;
            padding: 6px 4px !important;
        }
        
        /* Reduce term summary font size on mobile */
        .term-item summary {
            font-size: 12px !important;
            font-weight: 600 !important;
            padding: 10px 12px !important;
        }
        
        .consent-date-inline {
            font-size: 10px !important;
        }
    }
    
    .consent-text b,
    .consent-text strong {
        display: block;
        color: #000000;
        font-weight: 600;
        margin-top: 15px;
        margin-bottom: 8px;
    }
    .consent-text b:first-child,
    .consent-text strong:first-child {
        margin-top: 0;
    }
    .consent-text a {
        color: #0073aa;
        text-decoration: underline;
    }
    .consent-text a:hover {
        color: #005177;
    }
    
    /* Mobile responsive styles */
    @media screen and (max-width: 600px) {
        .terms-section h3 {
            font-size: 16px;
            padding: 12px 15px;
        }
        .terms-container {
            padding: 8px 3px;
        }
        .term-item {
            margin-bottom: 8px;
        }
        .term-item summary {
            font-size: 14px;
            padding: 10px 12px;
        }
        .consent-text {
            font-size: 13px;
            padding: 15px;
            line-height: 1.8;
        }
        .consent-date-inline {
            font-size: 9px !important;
        }
    }
    </style>
    <?php
}

// Shortcode to display booking invoice
add_shortcode('booking_invoice', 'bst_booking_invoice_shortcode');
function bst_booking_invoice_shortcode($atts) {
    global $wpdb;
    
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'lang' => 'it'  // Default to Italian
    ), $atts);
    
    // Get language from URL parameter or shortcode attribute
    $lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : $atts['lang'];
    $lang = in_array($lang, array('it', 'en')) ? $lang : 'it';
    
    // Get encoded eid from URL and decode it
    $decoded_id = 0;
    if (isset($_GET['eid']) && is_string($_GET['eid'])) {
        $decoded_id = bst_decode_booking_id($_GET['eid']);
    }
    
    if (!$decoded_id) {
        return '<div class="invoice-error">No invoice information found.</div>';
    }
    
    // Get GF10 entry
    $entry = GFAPI::get_entry($decoded_id);
    if (!$entry || is_wp_error($entry) || $entry['form_id'] != 10) {
        return '<div class="invoice-error">Invoice information not found. This invoice is only available for finalized bookings.</div>';
    }
    
    // Get booking from database using finalization entry ID
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $booking_table WHERE finalization_entry_id = %d LIMIT 1",
        $decoded_id
    ));
    
    if (!$booking) {
        return '<div class="invoice-error">Booking not found for this invoice.</div>';
    }
    
    // Get currency symbol
    $currency = $booking->tour_currency ?? 'EUR';
    $currency_symbol = $currency === 'USD' ? '$' : '€';
    
    // Get invoice details
    $invoice_number = $booking->booking_invoice_number ?? 'Not generated';
    $invoice_date = !empty($booking->booking_invoice_date) ? date('Y-m-d', strtotime($booking->booking_invoice_date)) : date('Y-m-d');
    $eu_percent = !empty($booking->booking_eu_percent) ? floatval($booking->booking_eu_percent) : 100.00;
    $vat_rate = !empty($booking->booking_vat_rate) ? floatval($booking->booking_vat_rate) : 22.00;
    
    // Get recipient information
    $recipient_choice = rgar($entry, '143', '');
    
    // Default to guest1 if no choice specified
    if (empty($recipient_choice)) {
        $recipient_choice = 'guest1';
    }
    
    $recipient_name = '';
    $recipient_company = '';
    $recipient_address = '';
    $tax_code = rgar($entry, '149', '');
    $vat_number = rgar($entry, '174', '');
    $sdi_code = rgar($entry, '267', '');
    
    if ($recipient_choice === 'guest1') {
        $recipient_name = trim(($booking->guest1_first_name ?? '') . ' ' . ($booking->guest1_last_name ?? ''));
        $recipient_address = bst_format_address(
            $booking->guest1_address_line1 ?? '',
            $booking->guest1_address_line2 ?? '',
            $booking->guest1_city ?? '',
            $booking->guest1_state_province ?? '',
            $booking->guest1_postal_code ?? '',
            $booking->guest1_country ?? ''
        );
    } elseif ($recipient_choice === 'guest2') {
        $recipient_name = trim(($booking->guest2_first_name ?? '') . ' ' . ($booking->guest2_last_name ?? ''));
        $recipient_address = bst_format_address(
            $booking->guest2_address_line1 ?? '',
            $booking->guest2_address_line2 ?? '',
            $booking->guest2_city ?? '',
            $booking->guest2_state_province ?? '',
            $booking->guest2_postal_code ?? '',
            $booking->guest2_country ?? ''
        );
    } elseif ($recipient_choice === 'third_party') {
        // Field 147 is a Name field with subfields
        $first_name = rgar($entry, '147.3', '');
        $last_name = rgar($entry, '147.6', '');
        $recipient_name = trim($first_name . ' ' . $last_name);
        
        // Field 134 is an address field with subfields
        $recipient_address = bst_format_address(
            rgar($entry, '134.1', ''),  // street
            rgar($entry, '134.2', ''),  // street2
            rgar($entry, '134.3', ''),  // city
            rgar($entry, '134.4', ''),  // state
            rgar($entry, '134.5', ''),  // zip
            rgar($entry, '134.6', '')   // country
        );
    } elseif ($recipient_choice === 'company') {
        // For company, no person name needed - just company name and address
        $recipient_company = rgar($entry, '145', '');
        
        // Field 134 is an address field with subfields
        $recipient_address = bst_format_address(
            rgar($entry, '134.1', ''),  // street
            rgar($entry, '134.2', ''),  // street2
            rgar($entry, '134.3', ''),  // city
            rgar($entry, '134.4', ''),  // state
            rgar($entry, '134.5', ''),  // zip
            rgar($entry, '134.6', '')   // country
        );
    }
    
    // Get company information
    $company_name = get_option('bst_company_name', 'Blue Strada Tours Srl Unipersonale');
    $company_address = get_option('bst_company_address', "Loc. Teppe, 24\n11020 Quart AO\nItalia");
    $company_tax_vat = get_option('bst_company_tax_vat', '01290220076');
    
    // Get tour information - format like admin booking list
    $tour_name_short = $booking->tour_text ?? '';
    $tour_year = $booking->tour_year ?? '';
    $tour_dates = $booking->tour_date_text ?? '';
    $tour_package_type = $booking->tour_package_type ?? '';
    $tour_name_display = $tour_name_short;
    if (!empty($tour_year)) {
        $tour_name_display .= ' (' . $tour_year . ')';
    }
    if (!empty($tour_dates)) {
        $tour_name_display .= ' (' . $tour_dates . ')';
    }
    if (!empty($tour_package_type)) {
        $tour_name_display .= ' - ' . $tour_package_type;
    }
    
    // Get vehicle information
    $tour_id = $booking->tour_id ?? 0;
    $using_bst_vehicles = false;
    if ($tour_id) {
        $using_bst_vehicles = get_field('using_bst_owned_vehicles', $tour_id) ?? false;
    }
    
    $vehicle1_amount = floatval($booking->booking_vehicle_1_use_amount ?? 0);
    $vehicle2_amount = floatval($booking->booking_vehicle_2_use_amount ?? 0);
    
    // Get vehicle names from booking record and strip prices
    $vehicle1_name = !empty($booking->vehicle1) ? preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $booking->vehicle1) : '';
    $vehicle2_name = !empty($booking->vehicle2) ? preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $booking->vehicle2) : '';
    
    // If vehicle has amount but no name, use default
    if ($vehicle1_amount > 0 && empty($vehicle1_name)) {
        $vehicle1_name = 'Mazda MX-5 manual';
    }
    if ($vehicle2_amount > 0 && empty($vehicle2_name)) {
        $vehicle2_name = 'Mazda MX-5 manual';
    }
    
    // Use the pre-calculated tour package amount from booking (already accounts for payment discount)
    $tour_cost = floatval($booking->booking_tour_package_amount ?? 0);
    
    // Get payment method
    $payment_method = '';
    if (!empty($booking->balance_payment_method)) {
        $payment_method = $booking->balance_payment_method;
    } elseif (!empty($booking->deposit_payment_method)) {
        $payment_method = $booking->deposit_payment_method;
    }
    
    // Calculate totals
    $subtotal = $tour_cost;
    if ($using_bst_vehicles) {
        $subtotal += $vehicle1_amount + $vehicle2_amount;
    }
    
    // Calculate VAT breakdown for tour services
    $tour_eu_taxable = $tour_cost * ($eu_percent / 100);
    $tour_eu_tax = 0; // Tour packages have 0 tax (special VAT treatment)
    $tour_non_eu = $tour_cost * (1 - ($eu_percent / 100));
    
    // Calculate VAT breakdown for vehicle services
    $vehicle_total = $vehicle1_amount + $vehicle2_amount;
    $vehicle_eu_taxable = $vehicle_total * ($eu_percent / 100);
    $vehicle_eu_tax = $vehicle_eu_taxable * ($vat_rate / 100);
    
    $total = $subtotal + $tour_eu_tax + $vehicle_eu_tax;
    $total = $subtotal + $tour_eu_tax + $vehicle_eu_tax;
    
    // Get payment information from booking with card type/bank wire code
    $payments = array();
    if (!empty($booking->deposit_payment_amount) && floatval($booking->deposit_payment_amount) > 0) {
        $payment_method = $booking->deposit_payment_method ?? 'Stripe';
        $card_type = '';
        if ($payment_method === 'Credit Card' || $payment_method === 'Stripe') {
            $original_entry = GFAPI::get_entry($booking->booking_entry_id);
            if ($original_entry && is_array($original_entry)) {
                $card_type = bst_get_card_type($original_entry, 9);
                $card_type = !empty($card_type) ? 'Stripe/' . str_replace('Sepa', 'SEPA', ucwords($card_type)) : '';
            }
        } elseif ($payment_method === 'Bank Wire') {
            $original_entry = GFAPI::get_entry($booking->booking_entry_id);
            if ($original_entry && is_array($original_entry)) {
                $region_code = rgar($original_entry, '232', 'Other');
                $card_type = ($region_code === 'Other') ? 'EUR' : $region_code;
            }
        }
        $payments[] = array(
            'label'     => 'deposit',
            'amount'    => floatval($booking->deposit_payment_amount),
            'date'      => $booking->deposit_payment_date ?? '',
            'method'    => $payment_method,
            'card_type' => $card_type
        );
    }

    // Check for balance payment - include even if amount is 0 (pending bank wire)
    if (!empty($booking->balance_payment_method)) {
        $balance_amount = floatval($booking->balance_payment_amount);
        $payment_method = $booking->balance_payment_method;
        if ($payment_method === 'Bank Wire' && $balance_amount == 0) {
            $balance_amount = floatval($booking->balance_due ?? 0);
        }
        if ($balance_amount > 0) {
            $card_type = '';
            if ($payment_method === 'Credit Card') {
                $card_type = bst_get_card_type($entry, 10);
                $card_type = !empty($card_type) ? 'Stripe/' . str_replace('Sepa', 'SEPA', ucwords($card_type)) : '';
            } elseif ($payment_method === 'Bank Wire') {
                $region_code = rgar($entry, '280', 'Other');
                $card_type = ($region_code === 'Other') ? 'EUR' : $region_code;
            }
            $bal_stored = floatval( $booking->balance_payment_amount ?? 0 );
            $is_bal_pending = ( $payment_method === 'Bank Wire' && function_exists( 'bst_payment_line_received_for_display' ) )
                ? ! bst_payment_line_received_for_display( $booking->balance_payment_status ?? '', $bal_stored )
                : ( $payment_method === 'Bank Wire' && $bal_stored == 0 );
            $payments[] = array(
                'label'      => 'balance',
                'amount'     => $balance_amount,
                'date'       => $booking->balance_payment_date ?? '',
                'method'     => $payment_method,
                'card_type'  => $card_type,
                'is_pending' => $is_bal_pending,
            );
        }
    }

    // Additional payment
    if (!empty($booking->additional_payment_amount) && floatval($booking->additional_payment_amount) > 0) {
        $payment_method = $booking->additional_payment_method ?? '';
        $payments[] = array(
            'label'     => 'additional',
            'amount'    => floatval($booking->additional_payment_amount),
            'date'      => $booking->additional_payment_date ?? '',
            'method'    => $payment_method,
            'card_type' => ($payment_method === 'Credit Card') ? 'Airwallex' : ''
        );
    }
    
    // Define translations
    $translations = array(
        'it' => array(
            'invoice' => 'Fattura pro forma (non valida ai fini fiscali)',
            'invoice_of' => 'del',
            'customer' => 'Cliente',
            'name' => 'Cognome',
            'company' => 'Azienda',
            'address' => 'Indirizzo',
            'tax_code' => 'Codice Fiscale',
            'vat_code' => 'P.IVA',
            'sdi_code' => 'Codice Univoco SDI',
            'not_applicable' => 'Non applicabile',
            'tour_services' => 'Pacchetti turistici',
            'vehicle_services' => 'Altri servizi',
            'services_provided' => 'Servizi offerti',
            'description' => 'Descrizione',
            'amount' => 'Importo',
            'total' => 'Totale',
            'totals' => 'Totali',
            'vat_summary' => 'Riepilogo IVA',
            'vat_rate' => 'Aliquota',
            'taxable' => 'Imponibile',
            'tax' => 'Imposta',
            'payments' => 'Pagamenti',
            'payment' => 'Pagamento',
            'date' => 'Data',
            'method' => 'Metodo',
            'type' => 'Tipo',
            'deposit'    => 'Acconto',
            'balance'    => 'Saldo',
            'additional' => 'Pagamento aggiuntivo',
            'vat_non_taxable' => 'Non imponibile IVA artt. 74-ter co. 7 e 21 co. 6 lett. e) DPR 633/72',
            'vat_out_of_scope' => 'Fuori dal campo IVA ex art. 7 ter DPR 633/72',
            'vat_ordinary' => 'ordinaria'
        ),
        'en' => array(
            'invoice' => 'Pro forma invoice (not valid for tax purposes)',
            'invoice_of' => 'of',
            'customer' => 'Customer',
            'name' => 'Name',
            'company' => 'Company',
            'address' => 'Address',
            'tax_code' => 'Tax code',
            'vat_code' => 'VAT code',
            'sdi_code' => 'SDI Unique Code',
            'not_applicable' => 'Not applicable',
            'tour_services' => 'Tour packages',
            'vehicle_services' => 'Other services',
            'services_provided' => 'Services provided',
            'description' => 'Description',
            'amount' => 'Amount',
            'total' => 'Total',
            'totals' => 'Totals',
            'vat_summary' => 'VAT summary',
            'vat_rate' => 'VAT rate',
            'taxable' => 'Taxable',
            'tax' => 'Tax',
            'payments' => 'Payments',
            'payment' => 'Payment',
            'date' => 'Date',
            'method' => 'Method',
            'type' => 'Type',
            'deposit'    => 'Deposit',
            'balance'    => 'Balance',
            'additional' => 'Additional Payment',
            'vat_non_taxable' => 'Non-taxable VAT art. 74-ter co. 7 and 21 co. 6 lett. e) DPR 633/72',
            'vat_out_of_scope' => 'Out of scope VAT ex art. 7 ter DPR 633/72',
            'vat_ordinary' => 'ordinary'
        )
    );
    
    $t = $translations[$lang];
    
    // Build the current page URL for language switching
    $current_url = add_query_arg(array(), $_SERVER['REQUEST_URI']);
    $switch_lang = $lang === 'it' ? 'en' : 'it';
    $switch_url = add_query_arg('lang', $switch_lang, $current_url);
    
    // Build output HTML
    ob_start();
    ?>
    <style>
        .invoice-container { max-width: 900px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif; font-size: 14px; background: white; }
        .invoice-header { border-bottom: 3px solid #0073aa; padding-bottom: 15px; margin-bottom: 20px; position: relative; }
        .invoice-header h1 { margin: 0; font-size: 20px; color: #0073aa; font-weight: bold; }
        .invoice-header .invoice-meta { font-size: 13px; color: #666; margin-top: 5px; }
        .lang-toggle { position: absolute; top: 0; right: 0; background: transparent; border: none; padding: 4px; cursor: pointer; font-size: 32px; text-decoration: none; display: inline-block; line-height: 1; }
        .lang-toggle:hover { opacity: 0.7; }
        .invoice-section { margin-bottom: 25px; }
        .invoice-section h3 { font-size: 15px; background: #0073aa; color: white; padding: 10px 15px; margin: 0 0 15px 0; }
        .invoice-section table { width: 100%; }
        .invoice-section table td { padding: 8px 5px; border-bottom: 1px solid #e8e8e8; vertical-align: top; font-size: 0.95em; }
        .invoice-section table tr:last-child td { border-bottom: none; }
        .invoice-section table td:first-child { width: 40%; text-align: right; padding-right: 15px; font-weight: bold; color: #333; }
        .invoice-section table td:last-child { text-align: left; padding-left: 15px; }
        .invoice-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .invoice-table thead tr { border-bottom: 2px solid #0073aa; }
        .invoice-table th { text-align: left; padding: 8px; font-size: 13px; font-weight: bold; }
        .invoice-table td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 13px; }
        .invoice-table tr:last-child td { border-bottom: none; }
        .invoice-table .amount-col { text-align: right; }
        .address-block { white-space: pre-line; line-height: 1.6; }
        .invoice-error { max-width: 900px; margin: 40px auto; padding: 20px; background: #ffebe9; border-left: 4px solid #dc3545; color: #721c24; }
        .invoice-footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; text-align: center; color: #666; font-size: 13px; font-weight: bold; }
    </style>
    
    <div class="invoice-container">
        <div class="invoice-header">
            <a href="<?php echo esc_url($switch_url); ?>" class="lang-toggle" title="<?php echo $lang === 'it' ? 'Switch to English' : 'Passa all\'italiano'; ?>"><?php echo $lang === 'it' ? '🇬🇧' : '🇮🇹'; ?></a>
            <h1><?php echo esc_html($company_name); ?></h1>
            <div class="invoice-meta">
                <?php echo esc_html($t['invoice']); ?> n. <?php echo esc_html($invoice_number); ?> <?php echo esc_html($t['invoice_of']); ?> <?php echo esc_html(date('j-n-Y', strtotime($invoice_date))); ?>
            </div>
        </div>
        
        <div class="invoice-section">
            <h3><?php echo esc_html($t['customer']); ?></h3>
            <table>
                <?php if (!empty($recipient_name)): ?>
                <tr>
                    <td><?php echo esc_html($t['name']); ?>:</td>
                    <td><?php echo esc_html($recipient_name); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($recipient_company)): ?>
                <tr>
                    <td><?php echo esc_html($t['company']); ?>:</td>
                    <td><?php echo esc_html($recipient_company); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><?php echo esc_html($t['address']); ?>:</td>
                    <td><?php echo !empty($recipient_address) ? '<span class="address-block">' . $recipient_address . '</span>' : esc_html($t['not_applicable']); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html($t['tax_code']); ?>:</td>
                    <td><?php echo !empty($tax_code) ? esc_html($tax_code) : esc_html($t['not_applicable']); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html($t['vat_code']); ?>:</td>
                    <td><?php echo !empty($vat_number) ? esc_html($vat_number) : esc_html($t['not_applicable']); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html($t['sdi_code']); ?>:</td>
                    <td><?php echo !empty($sdi_code) ? esc_html($sdi_code) : esc_html($t['not_applicable']); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Tour Services Section -->
        <div class="invoice-section">
            <h3 style="background: #0073aa; color: white;"><?php echo esc_html($t['tour_services']); ?></h3>
            
            <h4 style="font-size: 14px; margin: 20px 0 10px 0;"><?php echo esc_html($t['services_provided']); ?></h4>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="padding: 8px; text-align: left; width: 75%;"><?php echo esc_html($t['description']); ?></th>
                        <th style="padding: 8px; text-align: right; width: 25%;"><?php echo esc_html($t['amount']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><?php echo esc_html($tour_name_display); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($tour_cost, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><strong><?php echo esc_html($t['total']); ?></strong></td>
                        <td style="padding: 8px; text-align: right;"><strong><?php echo esc_html($currency_symbol) . number_format($tour_cost, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            
            <h4 style="font-size: 14px; margin: 20px 0 10px 0;"><?php echo esc_html($t['vat_summary']); ?></h4>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="padding: 8px; text-align: left; width: 50%;"><?php echo esc_html($t['vat_rate']); ?></th>
                        <th style="padding: 8px; text-align: right; width: 25%;"><?php echo esc_html($t['taxable']); ?></th>
                        <th style="padding: 8px; text-align: right; width: 25%;"><?php echo esc_html($t['tax']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><?php echo esc_html($t['vat_non_taxable']); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($tour_eu_taxable, 2); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($tour_eu_tax, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><?php echo esc_html($t['vat_out_of_scope']); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($tour_non_eu, 2); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . '0.00'; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <?php if ($using_bst_vehicles && ($vehicle1_amount > 0 || $vehicle2_amount > 0)): ?>
        <!-- Vehicle Services Section -->
        <div class="invoice-section">
            <h3 style="background: #0073aa; color: white;"><?php echo esc_html($t['vehicle_services']); ?></h3>
            
            <h4 style="font-size: 14px; margin: 20px 0 10px 0;"><?php echo esc_html($t['services_provided']); ?></h4>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="padding: 8px; text-align: left; width: 75%;"><?php echo esc_html($t['description']); ?></th>
                        <th style="padding: 8px; text-align: right; width: 25%;"><?php echo esc_html($t['amount']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($vehicle1_amount > 0): ?>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><?php echo esc_html($vehicle1_name); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($vehicle1_amount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($vehicle2_amount > 0): ?>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><?php echo esc_html($vehicle2_name); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($vehicle2_amount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><strong><?php echo esc_html($t['total']); ?></strong></td>
                        <td style="padding: 8px; text-align: right;"><strong><?php echo esc_html($currency_symbol) . number_format($vehicle1_amount + $vehicle2_amount, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            
            <h4 style="font-size: 14px; margin: 20px 0 10px 0;"><?php echo esc_html($t['vat_summary']); ?></h4>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="padding: 8px; text-align: left; width: 50%;"><?php echo esc_html($t['vat_rate']); ?></th>
                        <th style="padding: 8px; text-align: right; width: 25%;"><?php echo esc_html($t['taxable']); ?></th>
                        <th style="padding: 8px; text-align: right; width: 25%;"><?php echo esc_html($t['tax']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><?php echo number_format($vat_rate, 0); ?>% <?php echo esc_html($t['vat_ordinary']); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($vehicle_eu_taxable, 2); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($vehicle_eu_tax, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($using_bst_vehicles && ($vehicle1_amount > 0 || $vehicle2_amount > 0)): ?>
        <!-- Totals Section (Tour + Vehicle) -->
        <div class="invoice-section">
            <h3 style="background: #0073aa; color: white;"><?php echo esc_html($t['totals']); ?></h3>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="padding: 8px; text-align: left; width: 40%;"><?php echo esc_html($t['description']); ?></th>
                        <th style="padding: 8px; text-align: right; width: 20%;"><?php echo esc_html($t['amount']); ?></th>
                        <th style="padding: 8px; text-align: right; width: 20%;"><?php echo esc_html($t['taxable']); ?></th>
                        <th style="padding: 8px; text-align: right; width: 20%;"><?php echo esc_html($t['tax']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><?php echo esc_html($t['tour_services']); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($tour_cost, 2); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($tour_eu_taxable + $tour_non_eu, 2); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($tour_eu_tax, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><?php echo esc_html($t['vehicle_services']); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($vehicle1_amount + $vehicle2_amount, 2); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($vehicle_eu_taxable, 2); ?></td>
                        <td style="padding: 8px; text-align: right;"><?php echo esc_html($currency_symbol) . number_format($vehicle_eu_tax, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><strong><?php echo esc_html($t['total']); ?></strong></td>
                        <td style="padding: 8px; text-align: right;"><strong><?php echo esc_html($currency_symbol) . number_format($tour_cost + $vehicle1_amount + $vehicle2_amount, 2); ?></strong></td>
                        <td style="padding: 8px; text-align: right;"><strong><?php echo esc_html($currency_symbol) . number_format($tour_eu_taxable + $tour_non_eu + $vehicle_eu_taxable, 2); ?></strong></td>
                        <td style="padding: 8px; text-align: right;"><strong><?php echo esc_html($currency_symbol) . number_format($tour_eu_tax + $vehicle_eu_tax, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($payments)): ?>
        <div class="invoice-section">
            <h3><?php echo esc_html($t['payments']); ?></h3>
            <table class="invoice-table" style="text-align: left; width: 100%;">
                <thead>
                    <tr style="border-bottom: 2px solid #0073aa;">
                        <th style="padding: 8px; text-align: left;"><?php echo esc_html($t['payment']); ?></th>
                        <th style="padding: 8px; text-align: center;"><?php echo esc_html($t['date']); ?></th>
                        <th style="padding: 8px; text-align: center;"><?php echo esc_html($t['amount']); ?></th>
                        <th style="padding: 8px; text-align: center;"><?php echo esc_html($t['method']); ?></th>
                        <th style="padding: 8px; text-align: center;"><?php echo esc_html($t['type']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $idx => $payment): ?>
                    <?php
                    $payment_label = $t[$payment['label']] ?? ucwords($payment['label']);
                    if (!empty($payment['is_pending'])) {
                        $payment_label .= ' (Pending)';
                    }
                    ?>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><?php echo esc_html($payment_label); ?></td>
                        <td style="padding: 8px; text-align: center;"><?php echo !empty($payment['date']) ? esc_html(date('Y-m-d', strtotime($payment['date']))) : '-'; ?></td>
                        <td style="padding: 8px; text-align: center;"><?php echo esc_html($currency_symbol . number_format($payment['amount'], 2)); ?></td>
                        <td style="padding: 8px; text-align: center;"><?php 
                            $display_method = $payment['method'] === 'Bank Wire' ? 'Bank Transfer' : $payment['method'];
                            echo esc_html($display_method); 
                        ?></td>
                        <td style="padding: 8px; text-align: center;"><?php 
                            // card_type is already formatted in the builder (Stripe/Mastercard, Airwallex, EUR, etc.)
                            // Bank wire codes uppercased, everything else output as-is
                            if ($payment['method'] === 'Bank Wire') {
                                echo esc_html(strtoupper($payment['card_type']));
                            } else {
                                echo esc_html($payment['card_type']);
                            }
                        ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td style="padding: 8px; text-align: left;"><strong><?php echo esc_html($t['total']); ?></strong></td>
                        <td style="padding: 8px; text-align: center;"></td>
                        <td style="padding: 8px; text-align: center;"><strong><?php echo esc_html($currency_symbol . number_format(array_sum(array_column($payments, 'amount')), 2)); ?></strong></td>
                        <td style="padding: 8px; text-align: center;"></td>
                        <td style="padding: 8px; text-align: center;"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="invoice-footer">
            <?php echo esc_html($company_name . ' - ' . str_replace("\n", ' - ', $company_address) . ' - C.F. e P. IVA ' . $company_tax_vat); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Generate expandable bank wire instructions section
 * Checks if any payments use Bank Wire and if they are pending (no received date)
 * Auto-expands based on page context and pending status
 * 
 * @param object $booking The booking object
 * @param object $entry The Gravity Forms entry (optional, for confirmation page context)
 * @param bool $always_open If true, renders without expandable wrapper (for emails)
 * @param bool $force_closed If true, never auto-expands even if pending (for BST views)
 * @param string $page_context 'confirmation' or 'details' - controls default open state
 * @return string HTML for the bank wire instructions section, or empty string if no bank wire payments
 */
function bst_generate_bank_wire_section($booking, $entry = null, $always_open = false, $force_closed = false, $page_context = 'details') {
    // If no booking, try to determine from entry alone (for emails sent during form submission)
    if (!$booking && $entry && is_array($entry)) {
        // Check if this is a bank wire payment from the entry
        $payment_method = rgar($entry, '118');
        if ($payment_method !== 'Bank Wire') {
            return ''; // Not a bank wire payment
        }
        
        // Create a minimal pseudo-booking object for bank wire instructions
        $form_id = intval(rgar($entry, 'form_id'));
        $booking = new stdClass();
        
        if ($form_id == 9) {
            // Deposit payment on form 9
            $booking->deposit_payment_method = 'Bank Wire';
            $booking->deposit_payment_date = null; // Pending
            $booking->booking_entry_id = rgar($entry, 'id');
            $booking->balance_payment_method = null;
        } elseif ($form_id == 10) {
            // Balance payment on form 10
            $booking->balance_payment_method = 'Bank Wire';
            $booking->balance_payment_date = null; // Pending
            $booking->finalization_entry_id = rgar($entry, 'id');
            $booking->deposit_payment_method = null;
        }
    } elseif (!$booking) {
        return '';
    }
    
    // Check for bank wire payments
    $has_bank_wire = false;
    $has_pending_wire = false;
    $bank_wire_payments = array();
    
    // Check deposit payment
    if (!empty($booking->deposit_payment_method) && $booking->deposit_payment_method === 'Bank Wire') {
        $has_bank_wire = true;
        $is_pending = empty($booking->deposit_payment_date);
        if ($is_pending) {
            $has_pending_wire = true;
        }
        $bank_wire_payments[] = array(
            'type' => 'Deposit',
            'pending' => $is_pending,
            'entry_id' => $booking->booking_entry_id,
            'form_id' => 9
        );
    }
    
    // Check balance payment
    if (!empty($booking->balance_payment_method) && $booking->balance_payment_method === 'Bank Wire') {
        $has_bank_wire = true;
        $is_pending = empty($booking->balance_payment_date);
        if ($is_pending) {
            $has_pending_wire = true;
        }
        $bank_wire_payments[] = array(
            'type' => 'Balance Due',
            'pending' => $is_pending,
            'entry_id' => $booking->finalization_entry_id,
            'form_id' => 10
        );
    }
    
    // If no bank wire payments OR no pending wire payments, return empty
    if (!$has_bank_wire || !$has_pending_wire) {
        return '';
    }
    
    // Build the section - either always open or expandable
    if ($always_open) {
        // Always open version (for emails) - no expandable wrapper
        $html = '<div style="margin-bottom: 30px; border: 1px solid #dddddd; overflow: hidden;">';
        $html .= '<div style="padding: 15px 20px; background-color: #ffc107; color: #856404; font-size: 18px; font-weight: 600; margin: 0; text-align: center;">';
        $html .= 'Bank Transfer Payment Instructions';
        if ($has_pending_wire) {
            $html .= ' <span style="color: #d9534f; font-size: 14px; font-weight: bold;">(Payment Pending)</span>';
        }
        $html .= '</div>';
        $html .= '<div style="padding: 20px; background-color: #f5f5f5;">';
    } else {
        // Expandable version (for web pages)
        // Open if: pending wire AND confirmation page AND not force_closed
        // Closed by default on details page
        $should_open = ($has_pending_wire && $page_context === 'confirmation' && !$force_closed);
        $html = '<div class="booking-details-section terms-section">';
        $html .= '<details class="bank-wire-details"' . ($should_open ? ' open' : '') . ' style="border: 1px solid #dddddd; overflow: hidden;">';
        $html .= '<summary style="padding: 15px 20px; background-color: #ffc107; color: #856404; cursor: pointer; font-size: 18px; font-weight: 600; list-style: none; margin: 0;">';
        $html .= '<span class="bank-wire-arrow" style="display: inline-block; margin-right: 8px; transition: transform 0.2s;">▶</span>';
        $html .= 'Bank Transfer Payment Instructions';
        if ($has_pending_wire) {
            $html .= ' <span style="color: #d9534f; font-size: 14px; font-weight: bold;">(Payment Pending)</span>';
        }
        $html .= '</summary>';
        $html .= '<div style="padding: 20px; background-color: #f5f5f5;">';
    }
    
    // Generate instructions ONLY for pending bank wire payments
    foreach ($bank_wire_payments as $payment) {
        // Skip non-pending payments - only show instructions for payments that haven't been received
        if (!$payment['pending']) {
            continue;
        }
        
        // Get the entry for this payment
        $payment_entry = null;
        if (!empty($payment['entry_id'])) {
            $payment_entry = GFAPI::get_entry($payment['entry_id']);
        }
        
        // If we don't have an entry, use the current entry if it matches
        if (!$payment_entry && $entry && rgar($entry, 'form_id') == $payment['form_id']) {
            $payment_entry = $entry;
        }
        
        if ($payment_entry && !is_wp_error($payment_entry)) {
            // Get currency code from bank wire field (already populated by detection hooks)
            // Field 232 for form 9, field 280 for form 10
            if ($payment['form_id'] == 10) {
                $region_code = rgar($payment_entry, '280', 'Other');
            } else {
                $region_code = rgar($payment_entry, '232', 'Other');
            }
            
            // Get amount and discount based on payment type (prefer stored booking amounts when set)
            $amount = 0;
            $discount = 0;
            if ($payment['form_id'] == 9) {
                $amount = floatval( $booking->deposit_payment_amount ?? 0 );
                if ( $amount <= 0 ) {
                    $amount = floatval( rgar( $payment_entry, '230', 0 ) );
                }
                $discount = floatval(rgar($payment_entry, '229', 0));
            } elseif ($payment['form_id'] == 10) {
                $amount = floatval( $booking->balance_payment_amount ?? 0 );
                if ( $amount <= 0 ) {
                    $amount = floatval( rgar( $payment_entry, '279', 0 ) );
                }
                $discount = floatval(rgar($payment_entry, '278', 0));
            }
            
            // Get currency
            $currency = rgar($payment_entry, 'currency', 'EUR');
            
            // Count only pending payments for heading logic
            $pending_payments = array_filter($bank_wire_payments, function($p) { return $p['pending']; });
            
            // Add a heading if there are multiple pending payments
            if (count($pending_payments) > 1) {
                $html .= '<h4 style="color: #0073aa; margin-top: ' . ($payment !== $bank_wire_payments[0] ? '30px' : '0') . '; margin-bottom: 15px;">' . esc_html($payment['type']) . ' Payment Instructions</h4>';
            }
            
            // Generate the instructions
            $html .= bst_generate_bank_wire_instructions($region_code, $payment['type'], $amount, $currency, $payment_entry, $discount);
        }
    }
    
    $html .= '</div>'; // content padding div
    if (!$always_open) {
        $html .= '</details>';
        $html .= '</div>'; // booking-details-section
    } else {
        $html .= '</div>'; // outer wrapper
    }
    
    return $html;
}

/**
 * Detect region from phone number
 */
function bst_detect_region_from_phone($phone) {
    // Remove all non-numeric characters except the leading +
    $cleaned = preg_replace('/[^0-9+]/', '', $phone);
    
    // Normalize: add + if not present and looks like international format
    if (strpos($cleaned, '+') !== 0) {
        // If it starts with a digit, check if it's a full international number
        if (strlen($cleaned) >= 10) {
            // If starts with 1 and has 11 digits, it's likely North America
            if (substr($cleaned, 0, 1) == '1' && strlen($cleaned) == 11) {
                $cleaned = '+' . $cleaned;
            }
            // If it's 10 digits, assume North America and add +1
            elseif (strlen($cleaned) == 10) {
                $cleaned = '+1' . $cleaned;
            }
            // Other international formats - add +
            elseif (strlen($cleaned) >= 11) {
                $cleaned = '+' . $cleaned;
            }
        }
    }
    
    // Check 3-digit country codes first
    if (preg_match('/^\+(420|421|351|352|353|356|357|358|359|370|371|372|385|386)/', $cleaned, $matches)) {
        $code = $matches[1];
        $eu_codes_3digit = array('420', '421', '351', '352', '353', '356', '357', '358', '359', '370', '371', '372', '385', '386');
        if (in_array($code, $eu_codes_3digit)) {
            return 'EUR';
        }
    }
    
    // Check 2-digit country codes
    if (preg_match('/^\+(30|31|32|33|34|36|39|40|43|44|45|46|48|49|61|64)/', $cleaned, $matches)) {
        $code = $matches[1];
        if ($code == '44') return 'GBP';
        if ($code == '61') return 'AUD';
        if ($code == '64') return 'AUD'; // New Zealand
        
        $eu_codes_2digit = array('30', '31', '32', '33', '34', '36', '39', '40', '43', '45', '46', '48', '49');
        if (in_array($code, $eu_codes_2digit)) {
            return 'EUR';
        }
    }
    
    // Check 1-digit country codes
    if (preg_match('/^\+([1-9])/', $cleaned, $matches)) {
        $code = $matches[1];
        if ($code == '1') {
            // Check for Canadian area codes
            if (preg_match('/^\+1(204|226|236|249|250|289|306|343|365|403|416|418|431|437|438|450|506|514|519|548|579|581|587|604|613|639|647|705|709|778|780|782|807|819|825|867|902|905)/', $cleaned)) {
                return 'CAD';
            }
            return 'USD';
        }
    }
    
    return 'Other';
}

/**
 * Map country name/code to region
 */
function bst_map_country_to_region($country) {
    $country = strtoupper(trim($country));
    
    // US variations
    if (in_array($country, array('US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA'))) {
        return 'USD';
    }

    // US-affiliated territories (Gravity Forms country labels) — USD bank wire, not EUR/Other
    $us_territories_usd = array(
        'US MINOR OUTLYING ISLANDS',
        'VIRGIN ISLANDS, U.S.',
        'VIRGIN ISLANDS, U.S', // without trailing period
        'VIRGIN ISLANDS, US',
    );
    if (in_array($country, $us_territories_usd, true)) {
        return 'USD';
    }
    
    // Canada
    if (in_array($country, array('CA', 'CAN', 'CANADA'))) {
        return 'CAD';
    }
    
    // Australia and New Zealand
    if (in_array($country, array('AU', 'AUS', 'AUSTRALIA', 'NZ', 'NZL', 'NEW ZEALAND'))) {
        return 'AUD';
    }
    
    // UK
    if (in_array($country, array('GB', 'GBR', 'UK', 'UNITED KINGDOM', 'GREAT BRITAIN', 'ENGLAND', 'SCOTLAND', 'WALES', 'NORTHERN IRELAND'))) {
        return 'GBP';
    }
    
    // EU countries
    $eu_countries = array(
        'AT', 'AUSTRIA', 'BE', 'BELGIUM', 'BG', 'BULGARIA', 'HR', 'CROATIA',
        'CY', 'CYPRUS', 'CZ', 'CZECH REPUBLIC', 'CZECHIA', 'DK', 'DENMARK',
        'EE', 'ESTONIA', 'FI', 'FINLAND', 'FR', 'FRANCE', 'DE', 'GERMANY',
        'GR', 'GREECE', 'HU', 'HUNGARY', 'IE', 'IRELAND', 'IT', 'ITALY',
        'LV', 'LATVIA', 'LT', 'LITHUANIA', 'LU', 'LUXEMBOURG', 'MT', 'MALTA',
        'NL', 'NETHERLANDS', 'PL', 'POLAND', 'PT', 'PORTUGAL', 'RO', 'ROMANIA',
        'SK', 'SLOVAKIA', 'SI', 'SLOVENIA', 'ES', 'SPAIN', 'SE', 'SWEDEN'
    );
    
    if (in_array($country, $eu_countries)) {
        return 'EUR';
    }
    
    return 'Other';
}

/**
 * Convert region code to currency code
 * Form fields store currency codes (USD, CAD, GBP, AUD, EUR, Other)
 * Only "Other" needs conversion to EUR
 * 
 * @param string $region_code The currency code (USD, CAD, GBP, AUD, EUR, Other)
 * @return string The currency code (USD, CAD, GBP, AUD, EUR)
 */
function bst_region_to_currency_code($region_code) {
    // Only convert "Other" to EUR, pass everything else unchanged
    return ($region_code === 'Other') ? 'EUR' : $region_code;
}

/**
 * Generate static bank wire instructions HTML based on region code
 * 
 * @param string $region_code The region code (USD, CAN, EUR, AUD, GBR, Other)
 * @param string $payment_type The payment type (Deposit or Balance)
 * @param float $amount The payment amount in EUR
 * @param string $base_currency The base currency (usually EUR)
 * @param array $entry The Gravity Forms entry (optional, for guest info)
 * @param float $discount The discount amount in EUR (optional)
 * @return string The HTML for bank wire instructions
 */
function bst_generate_bank_wire_instructions($region_code, $payment_type, $amount, $base_currency = 'EUR', $entry = null, $discount = 0) {
    // Get exchange rates from individual options (stored as bst_exchange_rate_eur_usd, etc.)
    $usd_rate = floatval(get_option('bst_exchange_rate_eur_usd', 1.10));
    $cad_rate = floatval(get_option('bst_exchange_rate_eur_cad', 1.50));
    $aud_rate = floatval(get_option('bst_exchange_rate_eur_aud', 1.65));
    $gbp_rate = floatval(get_option('bst_exchange_rate_eur_gbp', 0.85));
    
    // Calculate amount after discount (for "Converted from" display)
    // Note: $amount is already the after-discount amount for shortcodes
    $amount_after_discount = $amount;
    
    // Calculate converted amounts (after discount)
    $amount_usd = $amount_after_discount * $usd_rate;
    $amount_cad = $amount_after_discount * $cad_rate;
    $amount_aud = $amount_after_discount * $aud_rate;
    $amount_gbp = $amount_after_discount * $gbp_rate;
    
    // Calculate converted discount amounts
    $discount_usd = $discount * $usd_rate;
    $discount_cad = $discount * $cad_rate;
    $discount_aud = $discount * $aud_rate;
    $discount_gbp = $discount * $gbp_rate;
    
    // Get company information from settings
    $company_name = get_option('bst_company_name');
    // remove Unipersonale from use here
    $company_name = str_replace(' Unipersonale', '', $company_name);
    $company_address = get_option('bst_company_address');
    $company_address_formatted = str_replace("\r\n", ', ', $company_address);

    // Get guest information for transfer description (if entry provided)
    $guest_name = 'Your Name';
    $tour_text = 'Tour Name';
    $tour_date_text = 'Tour Dates';
    
    if ($entry && is_array($entry)) {
        // Build guest name from entry using helper
        $guest1_first = rgar($entry, '31.3');
        $guest1_last = rgar($entry, '31.6');
        $guest2_first = rgar($entry, '215.3');
        $guest2_last = rgar($entry, '215.6');
        
        $guest_name = bst_format_guest_name($guest1_first, $guest1_last, $guest2_first, $guest2_last);
        
        // Get tour info - check form ID for correct fields
        $form_id = intval(rgar($entry, 'form_id'));
        if ($form_id == 10) {
            // GF10 (finalization form)
            $tour_text = rgar($entry, '200');
            $tour_date_text = rgar($entry, '201');
        } else {
            // GF9 (booking form)
            $tour_text = rgar($entry, '137');
            $tour_date_text = rgar($entry, '141');
        }
    }
    
    // Build HTML with embedded CSS and structured content
    $html = '<style>
        .bwi-intro { margin: 10px 0; line-height: 1.6; text-align: left; }
        .bwi-amount-box { padding: 15px; background-color: #e8f4f8; border-left: 4px solid #0073aa; margin: 15px 0; text-align: center; }
        .bwi-amount-box span { color: #666666; }
        .bwi-amount-box strong { color: #0073aa; font-size: 1.2em; }
        .bwi-amount-box em { display: block; margin-top: 5px; color: #666666; font-size: 0.9em; }
        .bwi-amount-box .note { display: block; margin-top: 8px; font-size: 0.85em; font-style: italic; color: #555555; }
        .bwi-heading { color: #333333; border-bottom: 2px solid #0073aa; padding-bottom: 8px; margin: 20px 0 15px 0; text-align: center; }
        .bwi-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .bwi-table td { padding: 10px; border: 1px solid #dddddd; }
        .bwi-table td:first-child { background-color: #f5f5f5; font-weight: bold; width: 35%; text-align: right; }
        .bwi-notes { background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; }
        .bwi-notes-heading { color: #856404; font-weight: bold; margin-bottom: 10px; font-size: 1.1em; text-align: left; }
        .bwi-notes ul { margin: 10px 0 0 0; padding-left: 20px; text-align: left; }
        .bwi-notes li { margin-bottom: 10px; line-height: 1.6; text-align: left; }
        .bwi-notes li:last-child { margin-bottom: 0; }
        .bwi-note-text { margin: 15px 0; }
    </style>';
    
    // Use different intro text based on payment type
    if ($payment_type === 'Balance Due') {
        $html .= '<p class="bwi-intro">Please note that the tour price is set in euros, and exchange rates fluctuate daily. If the transfer is initiated after the day you place your booking, the final amount in your local currency may differ.</p>';
    } else {
        $html .= '<p class="bwi-intro">We will hold your booking for three business days while we await your bank transfer. Please note that the tour price is set in euros, and exchange rates fluctuate daily. If the transfer is initiated after the day you place your booking, the final amount in your local currency may differ.</p>';
    }
    
    // Region-specific content
    switch ($region_code) {
        case 'USD':
            $html .= '<h4 class="bwi-heading">' . esc_html($company_name) . ' - US Bank Details</h4>';
            $html .= '<table class="bwi-table">';
            $html .= '<tr><td>Amount to Send:</td><td><strong style="color: #28a745;">&#36;' . number_format($amount_usd, 2) . '</strong>';
            if ($discount > 0) {
                $html .= ' (You saved &#36;' . number_format($discount_usd, 2) . ' with this bank transfer)';
            }
            $html .= '<br><em>Converted from <strong>&euro;' . number_format($amount_after_discount, 2) . '</strong> at current rate</em></td></tr>';
            $html .= '<tr><td>Recipient:</td><td>' . esc_html($company_name) . ' c/o CFSB<br>89-16 Jamaica Ave, Woodhaven, NY 11421 - USA</td></tr>';
            $html .= '<tr><td>Account Name:</td><td>' . esc_html($company_name) . '</td></tr>';
            $html .= '<tr><td>Account Number:</td><td>8480420487</td></tr>';
            $html .= '<tr><td>ACH Routing Number:</td><td>026073150</td></tr>';
            $html .= '<tr><td>Fedwire Routing Number:</td><td>026073008</td></tr>';
            $html .= '<tr><td>Bank:</td><td>Community Federal Savings Bank<br>89-16 Jamaica Ave, Woodhaven, NY 11421 - USA<br><em style="font-size: 0.9em; color: #666;">(Serviced via Airwallex)</em></td></tr>';
            $html .= '</table>';
            
            $html .= '<div class="bwi-notes">';
            $html .= '<div class="bwi-notes-heading"><strong>US Bank Transfer Notes</strong></div>';
            $html .= '<ul>';
            $html .= '<li><strong>ACH (Recommended):</strong> If your bank allows ACH transfers, use the <strong>ACH Routing Number</strong> + <strong>Account Number</strong> (simpler and often free or low cost).<br><br><strong>ACH Limits:</strong> Many banks impose daily ACH transfer limits that vary by institution and account type. For large transfers, check with your bank about your specific daily limits to avoid delays.</li>';
            $html .= '<li><strong>Domestic Wire Transfer:</strong> If your bank requires domestic wire transfers, use the <strong>Fedwire Routing Number</strong> + <strong>Account Number</strong> (typically has a fee).</li>';
            $html .= '<li style="margin-bottom: 0;"><strong>Important:</strong> If your bank doesn\'t allow you to complete this transfer online, you may be able to complete it in person at your bank branch. If you\'re unable to initiate a domestic transfer, please refer to the Alternate International Bank Wire option below.</li>';
            $html .= '</ul></div>';
            
            // Add international fallback
            $html .= '<h4 class="bwi-heading">Alternative: International Bank Wire</h4>';
            $html .= '<table class="bwi-table">';
            $html .= '<tr><td>Amount to Send:</td><td><strong style="color: #28a745;">&euro;' . number_format($amount, 2) . '</strong>';
            if ($discount > 0) {
                $html .= ' (You saved &euro;' . number_format($discount, 2) . ' with this bank transfer)';
            }
            $html .= '</td></tr>';
            $html .= '<tr><td>Recipient:</td><td>' . esc_html($company_name) . '<br>' . esc_html($company_address_formatted) . '</td></tr>';
            $html .= '<tr><td>Account Name:</td><td>' . esc_html($company_name) . '</td></tr>';
            $html .= '<tr><td>SWIFT/BIC code:</td><td>BCITITMMXXX</td></tr>';
            $html .= '<tr><td>IBAN:</td><td>IT71K0306903202100000075058</td></tr>';
            $html .= '<tr><td>Bank:</td><td>Intesa Sanpaolo<br>Via dei Prati Fiscali, 187, 00141 Rome - Italy</td></tr>';
            $html .= '</table>';
            
            $html .= '<div class="bwi-notes">';
            $html .= '<div class="bwi-notes-heading"><strong>International Bank Wire Notes</strong></div>';
            $html .= '<ul>';
            $html .= '<li>Use <strong>IBAN</strong> + <strong>SWIFT/BIC code</strong> + <strong>Bank name and address</strong>.</li>';
            $html .= '<li style="margin-bottom: 0;"><strong>Wire fees:</strong> International bank wires typically have higher fees and take 3-5 business days. Check with your bank for exact costs.</li>';
            $html .= '</ul></div>';
            break;
            
        case 'CAD':
            $html .= '<h4 class="bwi-heading">' . esc_html($company_name) . ' - Canadian Bank Details</h4>';
            $html .= '<table class="bwi-table">';
            $html .= '<tr><td>Amount to Send:</td><td><strong style="color: #28a745;">C&#36;' . number_format($amount_cad, 2) . '</strong>';
            if ($discount > 0) {
                $html .= ' (You saved C&#36;' . number_format($discount_cad, 2) . ' with this bank transfer)';
            }
            $html .= '<br><em>Converted from <strong>&euro;' . number_format($amount_after_discount, 2) . '</strong> at current rate</em></td></tr>';
            $html .= '<tr><td>Recipient:</td><td>' . esc_html($company_name) . ' c/o DCB<br>736 Meridian Road NE, T2A 2N7 Calgary, Alberta – Canada</td></tr>';
            $html .= '<tr><td>Account Name:</td><td>' . esc_html($company_name) . '</td></tr>';
            $html .= '<tr><td>Interac e-Transfer:</td><td>claudio@bluestradatours.com</td></tr>';
            $html .= '<tr><td>Bank account number:</td><td>992269720</td></tr>';
            $html .= '<tr><td>Transit number:</td><td>10009</td></tr>';
            $html .= '<tr><td>Financial institution:</td><td>352</td></tr>';
            $html .= '<tr><td>Bank:</td><td>Digital Commerce Bank<br>736 Meridian Road NE, T2A 2N7 Calgary, Alberta – Canada<br><em style="font-size: 0.9em; color: #666;">(Serviced via Airwallex)</em></td></tr>';
            $html .= '</table>';
            
            $html .= '<div class="bwi-notes">';
            $html .= '<div class="bwi-notes-heading"><strong>Canadian Bank Transfer Notes</strong></div>';
            $html .= '<ul>';
            $html .= '<li><strong>Interac e-Transfer (Recommended):</strong> Easiest method - just use the <strong>Interac e-Transfer email</strong> address shown above. Free or low cost and arrives instantly.<br><br><strong>Payment Limits:</strong> Most Canadian banks have daily Interac e-Transfer limits (typically C&#36;3,000-&#36;10,000 per day). Amounts over C&#36;3,000 may require special approval from your bank. If your payment exceeds your daily limit, you may need to split the transfer across multiple days. Contact your bank to confirm your specific limits and approval requirements.</li>';
            $html .= '<li><strong>EFT Transfer:</strong> Use <strong>Account Number</strong> + <strong>Transit Number</strong> + <strong>Financial Institution Number</strong> if your bank doesn\'t support Interac or if your transfer exceeds Interac e-Transfer limits (limits vary by bank).</li>';
            $html .= '<li style="margin-bottom: 0;"><strong>Important:</strong> If your bank doesn\'t allow you to complete this transfer online, you may be able to complete it in person at your bank branch. If you\'re unable to initiate a domestic transfer, please refer to the Alternate International Bank Wire option below.</li>';
            $html .= '</ul></div>';
            
            // Add international fallback
            $html .= '<h4 class="bwi-heading">Alternative: International Bank Wire</h4>';
            $html .= '<table class="bwi-table">';
            $html .= '<tr><td>Amount to Send:</td><td><strong style="color: #28a745;">&euro;' . number_format($amount, 2) . '</strong>';
            if ($discount > 0) {
                $html .= ' (You saved &euro;' . number_format($discount, 2) . ' with this bank transfer)';
            }
            $html .= '</td></tr>';
            $html .= '<tr><td>Recipient:</td><td>' . esc_html($company_name) . '<br>' . esc_html($company_address_formatted) . '</td></tr>';
            $html .= '<tr><td>Account Name:</td><td>' . esc_html($company_name) . '</td></tr>';
            $html .= '<tr><td>SWIFT/BIC code:</td><td>BCITITMMXXX</td></tr>';
            $html .= '<tr><td>IBAN:</td><td>IT71K0306903202100000075058</td></tr>';
            $html .= '<tr><td>Bank:</td><td>Intesa Sanpaolo<br>Via dei Prati Fiscali, 187, 00141 Rome - Italy</td></tr>';
            $html .= '</table>';
            
            $html .= '<div class="bwi-notes">';
            $html .= '<div class="bwi-notes-heading"><strong>International Bank Wire Notes</strong></div>';
            $html .= '<ul>';
            $html .= '<li>Use <strong>IBAN</strong> + <strong>SWIFT/BIC code</strong> + <strong>Bank name and address</strong>.</li>';
            $html .= '<li style="margin-bottom: 0;"><strong>Wire fees:</strong> International bank wires typically have higher fees and take 3-5 business days. Check with your bank for exact costs.</li>';
            $html .= '</ul></div>';
            
            break;
            
        case 'AUD':
            $html .= '<p class="bwi-note-text"><strong>Note for New Zealand customers:</strong> New Zealand banks can send funds to Australian bank accounts in AUD.</p>';
            
            $html .= '<h4 class="bwi-heading">' . esc_html($company_name) . ' - Australian Bank Details</h4>';
            $html .= '<table class="bwi-table">';
            $html .= '<tr><td>Amount to Send:</td><td><strong style="color: #28a745;">&#36;' . number_format($amount_aud, 2) . ' AUD</strong>';
            if ($discount > 0) {
                $html .= ' (You saved &#36;' . number_format($discount_aud, 2) . ' AUD with this bank transfer)';
            }
            $html .= '<br><em>Converted from <strong>&euro;' . number_format($amount_after_discount, 2) . '</strong> at current rate</em></td></tr>';
            $html .= '<tr><td>Recipient:</td><td>' . esc_html($company_name) . ' c/o ANZ<br>833 Collins Street, 3000 Melbourne, Victoria, Australia</td></tr>';
            $html .= '<tr><td>Account Name:</td><td>' . esc_html($company_name) . '</td></tr>';
            $html .= '<tr><td>Bank account number:</td><td>613852444</td></tr>';
            $html .= '<tr><td>BSB code:</td><td>013943</td></tr>';
            $html .= '<tr><td>Bank:</td><td>Australia and New Zealand Banking Group Limited<br>833 Collins Street, 3000 Melbourne, Victoria, Australia<br><em style="font-size: 0.9em; color: #666;">(Serviced via Airwallex)</em></td></tr>';
            $html .= '</table>';
            
            $html .= '<div class="bwi-notes">';
            $html .= '<div class="bwi-notes-heading"><strong>Australian Bank Transfer Notes</strong></div>';
            $html .= '<ul>';
            $html .= '<li><strong>Domestic Transfer (Recommended):</strong> Use <strong>BSB Code</strong> + <strong>Account Number</strong> for instant or same-day transfer (usually free). PayID/Osko supported by most banks.</li>';
            $html .= '<li><strong>New Zealand customers:</strong> Most NZ banks can send AUD to Australian accounts using the same details. Check with your bank about conversion rates and fees.</li>';
            $html .= '<li style="margin-bottom: 0;"><strong>Important:</strong> If your bank doesn\'t allow you to complete this transfer online, you may be able to complete it in person at your bank branch. If you\'re unable to initiate a domestic transfer, please refer to the Alternate International Bank Wire option below.</li>';
            $html .= '</ul></div>';
            
            // Add international fallback
            $html .= '<h4 class="bwi-heading">Alternative: International Bank Wire</h4>';
            $html .= '<table class="bwi-table">';
            $html .= '<tr><td>Amount to Send:</td><td><strong style="color: #28a745;">&euro;' . number_format($amount, 2) . '</strong>';
            if ($discount > 0) {
                $html .= ' (You saved &euro;' . number_format($discount, 2) . ' with this bank transfer)';
            }
            $html .= '</td></tr>';
            $html .= '<tr><td>Recipient:</td><td>' . esc_html($company_name) . '<br>' . esc_html($company_address_formatted) . '</td></tr>';
            $html .= '<tr><td>Account Name:</td><td>' . esc_html($company_name) . '</td></tr>';
            $html .= '<tr><td>SWIFT/BIC code:</td><td>BCITITMMXXX</td></tr>';
            $html .= '<tr><td>IBAN:</td><td>IT71K0306903202100000075058</td></tr>';
            $html .= '<tr><td>Bank:</td><td>Intesa Sanpaolo<br>Via dei Prati Fiscali, 187, 00141 Rome - Italy</td></tr>';
            $html .= '</table>';
            
            $html .= '<div class="bwi-notes">';
            $html .= '<div class="bwi-notes-heading"><strong>International Bank Wire Notes</strong></div>';
            $html .= '<ul>';
            $html .= '<li>Use <strong>IBAN</strong> + <strong>SWIFT/BIC code</strong> + <strong>Bank name and address</strong>.</li>';
            $html .= '<li style="margin-bottom: 0;"><strong>Wire fees:</strong> International bank wires typically have higher fees and take 3-5 business days. Check with your bank for exact costs.</li>';
            $html .= '</ul></div>';
            
            break;
            
        case 'EUR':
            $html .= '<h4 class="bwi-heading">' . esc_html($company_name) . ' - EU Bank Details</h4>';
            $html .= '<table class="bwi-table">';
            $html .= '<tr><td>Amount to Send:</td><td><strong style="color: #28a745;">&euro;' . number_format($amount, 2) . '</strong>';
            if ($discount > 0) {
                $html .= ' (You saved &euro;' . number_format($discount, 2) . ' with this bank transfer)';
            }
            $html .= '</td></tr>';
            $html .= '<tr><td>Recipient:</td><td>' . esc_html($company_name) . '<br>' . esc_html($company_address_formatted) . '</td></tr>';
            $html .= '<tr><td>Account Name:</td><td>' . esc_html($company_name) . '</td></tr>';
            $html .= '<tr><td>SWIFT/BIC code:</td><td>BCITITMMXXX</td></tr>';
            $html .= '<tr><td>IBAN:</td><td>IT71K0306903202100000075058</td></tr>';
            $html .= '<tr><td>Bank:</td><td>Intesa Sanpaolo<br>Via dei Prati Fiscali, 187, 00141 Rome - Italy</td></tr>';
            $html .= '</table>';
            
            $html .= '<div class="bwi-notes">';
            $html .= '<div class="bwi-notes-heading"><strong>EU Bank Transfer Notes</strong></div>';
            $html .= '<ul>';
            $html .= '<li style="margin-bottom: 0;"><strong>SEPA Transfer (Recommended):</strong> Use the <strong>IBAN</strong> shown above for transfers within the EU. Typically free or very low cost, arrives within 1-2 business days. Many banks also offer instant SEPA for a small fee.</li>';
            $html .= '</ul></div>';
            
            break;
            
        case 'GBP':
            $html .= '<h4 class="bwi-heading">' . esc_html($company_name) . ' - UK Bank Details</h4>';
            $html .= '<table class="bwi-table">';
            $html .= '<tr><td>Amount to Send:</td><td><strong style="color: #28a745;">&pound;' . number_format($amount_gbp, 2) . '</strong>';
            if ($discount > 0) {
                $html .= ' (You saved &pound;' . number_format($discount_gbp, 2) . ' with this bank transfer)';
            }
            $html .= '<br><em>Converted from <strong>&euro;' . number_format($amount_after_discount, 2) . '</strong> at current rate</em></td></tr>';
            $html .= '<tr><td>Recipient:</td><td>' . esc_html($company_name) . ' c/o Airwallex UK<br>Labs House 15-19 Bloomsbury Way, London WC1A 2TH – United Kingdom</td></tr>';
            $html .= '<tr><td>Account Name:</td><td>' . esc_html($company_name) . '</td></tr>';
            $html .= '<tr><td>Bank account number:</td><td>08136480</td></tr>';
            $html .= '<tr><td>Sort code:</td><td>041907</td></tr>';
            $html .= '<tr><td>SWIFT/BIC code:</td><td>AIRWGB22XXX</td></tr>';
            $html .= '<tr><td>IBAN:</td><td>GB96AIRW04190708136480</td></tr>';
            $html .= '<tr><td>Bank:</td><td>Airwallex (UK) Limited<br>Labs House 15-19 Bloomsbury Way, London WC1A 2TH – United Kingdom</td></tr>';
            $html .= '</table>';
            
            $html .= '<div class="bwi-notes">';
            $html .= '<div class="bwi-notes-heading"><strong>UK Bank Transfer Notes</strong></div>';
            $html .= '<ul>';
            $html .= '<li><strong>Faster Payments (Recommended):</strong> Use <strong>Sort Code</strong> + <strong>Account Number</strong> for instant United Kingdom domestic transfers (usually free, arrives within minutes).</li>';
            $html .= '<li><strong>CHAPS:</strong> For guaranteed same-day transfer, use the same details but select CHAPS (has a fee).</li>';
            $html .= '<li style="margin-bottom: 0;"><strong>Important:</strong> If your bank doesn\'t allow you to complete this transfer online, you may be able to complete it in person at your bank branch. If you\'re unable to initiate a domestic transfer, please refer to the Alternate International Bank Wire option below.</li>';
            $html .= '</ul></div>';
            
            // Add international fallback
            $html .= '<h4 class="bwi-heading">Alternative: International Bank Wire</h4>';
            $html .= '<table class="bwi-table">';
            $html .= '<tr><td>Amount to Send:</td><td><strong style="color: #28a745;">&euro;' . number_format($amount, 2) . '</strong>';
            if ($discount > 0) {
                $html .= ' (You saved &euro;' . number_format($discount, 2) . ' with this bank transfer)';
            }
            $html .= '</td></tr>';
            $html .= '<tr><td>Recipient:</td><td>' . esc_html($company_name) . '<br>' . esc_html($company_address_formatted) . '</td></tr>';
            $html .= '<tr><td>Account Name:</td><td>' . esc_html($company_name) . '</td></tr>';
            $html .= '<tr><td>SWIFT/BIC code:</td><td>BCITITMMXXX</td></tr>';
            $html .= '<tr><td>IBAN:</td><td>IT71K0306903202100000075058</td></tr>';
            $html .= '<tr><td>Bank:</td><td>Intesa Sanpaolo<br>Via dei Prati Fiscali, 187, 00141 Rome - Italy</td></tr>';
            $html .= '</table>';
            
            $html .= '<div class="bwi-notes">';
            $html .= '<div class="bwi-notes-heading"><strong>International Bank Wire Notes</strong></div>';
            $html .= '<ul>';
            $html .= '<li>Use <strong>IBAN</strong> + <strong>SWIFT/BIC code</strong> + <strong>Bank name and address</strong>.</li>';
            $html .= '<li style="margin-bottom: 0;"><strong>Wire fees:</strong> International bank wires typically have higher fees and take 3-5 business days. Check with your bank for exact costs.</li>';
            $html .= '</ul></div>';
            
            break;
            
        case 'Other':
        default:
            $html .= '<h4 class="bwi-heading">' . esc_html($company_name) . ' - EU Bank Details (International Bank Wire)</h4>';
            $html .= '<table class="bwi-table">';
            $html .= '<tr><td>Amount to Send:</td><td><strong style="color: #28a745;">&euro;' . number_format($amount, 2) . '</strong>';
            if ($discount > 0) {
                $html .= ' (You saved &euro;' . number_format($discount, 2) . ' with this bank transfer)';
            }
            $html .= '</td></tr>';
            $html .= '<tr><td>Recipient:</td><td>' . esc_html($company_name) . '<br>' . esc_html($company_address_formatted) . '</td></tr>';
            $html .= '<tr><td>Account Name:</td><td>' . esc_html($company_name) . '</td></tr>';
            $html .= '<tr><td>SWIFT/BIC code:</td><td>BCITITMMXXX</td></tr>';
            $html .= '<tr><td>IBAN:</td><td>IT71K0306903202100000075058</td></tr>';
            $html .= '<tr><td>Bank:</td><td>Intesa Sanpaolo<br>Via dei Prati Fiscali, 187, 00141 Rome - Italy</td></tr>';
            $html .= '</table>';
            
            $html .= '<div class="bwi-notes">';
            $html .= '<div class="bwi-notes-heading"><strong>International Bank Wire Notes</strong></div>';
            $html .= '<ul>';
            $html .= '<li>Use <strong>IBAN</strong> + <strong>SWIFT/BIC code</strong> + <strong>Bank name and address</strong>.</li>';
            $html .= '<li style="margin-bottom: 0;"><strong>Wire fees:</strong> International bank wires typically have higher fees and take 3-5 business days. Check with your bank for exact costs.</li>';
            $html .= '</ul></div>';
            
            break;
    }
    
    // Add common notes section for all regions
    $html .= '<div style="background-color: #e8f4f8; border: 1px solid #b8dce8; padding: 15px; margin: 20px 0;">';
    $html .= '<div style="color: #0c5460; font-weight: bold; margin-bottom: 10px; font-size: 1.1em; text-align: left;"><strong>General Transfer Information</strong></div>';
    $html .= '<ul style="margin: 10px 0 0 0; padding-left: 20px; text-align: left;">';
    $html .= '<li style="margin-bottom: 10px; line-height: 1.6; text-align: left;"><strong>First-time transfers:</strong> Many banks allow transfers online, but some may require you to go in person for first-time transfers or higher amounts.</li>';
    $html .= '<li style="margin-bottom: 10px; line-height: 1.6; text-align: left;"><strong>Transfer description:</strong> Include in the transfer description: <strong>' . esc_html($guest_name) . ' – ' . esc_html($tour_text) . ' – ' . esc_html($tour_date_text) . '</strong></li>';
    $html .= '<li style="margin-bottom: 0; line-height: 1.6; text-align: left;"><strong>Proof of payment:</strong> Please initiate this transfer within 3 business days of submitting this ' . ($payment_type === 'Balance Due' ? 'finalization' : 'booking') . ' and send proof of payment to <a href="mailto:info@bluestradatours.com">info@bluestradatours.com</a></li>';
    $html .= '</ul></div>';
    
    return $html;
}

// Custom Gravity Forms merge tags for booking links
add_filter('gform_custom_merge_tags', 'bst_add_booking_merge_tags', 10, 4);
function bst_add_booking_merge_tags($merge_tags, $form_id, $fields, $element_id) {
    // Add global merge tags for all forms
    $merge_tags[] = array(
        'label' => 'Blue Strada Email',
        'tag' => '{BstEmail}'
    );
    
    $merge_tags[] = array(
        'label' => 'Email Signature',
        'tag' => '{BstEmailSignature}'
    );
    
    // Only add booking-specific tags for forms 9 and 10
    if ($form_id == 9 || $form_id == 10) {
        $merge_tags[] = array(
            'label' => 'Booking Confirmation Link',
            'tag' => '{ConfirmationLink}'
        );
        $merge_tags[] = array(
            'label' => 'Booking Confirmation Query String',
            'tag' => '{ConfirmationQueryString}'
        );
        $merge_tags[] = array(
            'label' => 'Customer Booking Details Link',
            'tag' => '{CustBookingDetailsLink}'
        );
        $merge_tags[] = array(
            'label' => 'BST Booking Details Link',
            'tag' => '{BstBookingDetailsLink}'
        );
        $merge_tags[] = array(
            'label' => 'BST Booking Subject',
            'tag' => '{BstBookingSubject}'
        );
        $merge_tags[] = array(
            'label' => 'BST Booking Summary',
            'tag' => '{BstBookingSummary}'
        );
        $merge_tags[] = array(
            'label' => 'Customer Booking Subject',
            'tag' => '{CustBookingSubject}'
        );
        $merge_tags[] = array(
            'label' => 'Customer Booking Summary',
            'tag' => '{CustBookingSummary}'
        );
        $merge_tags[] = array(
            'label' => 'Bank Transfer Discount',
            'tag' => '{BankWireDiscount}'
        );
        $merge_tags[] = array(
            'label' => 'Bank Transfer Instructions',
            'tag' => '{BankWireInstructions:code_field=123:type=Deposit:amount_field=177:currency=EUR}'
        );
        $merge_tags[] = array(
            'label' => 'Reservation Link',
            'tag' => '{reservation_link}'
        );
        $merge_tags[] = array(
            'label' => 'Finalization Link',
            'tag' => '{finalization_link}'
        );
        
        // Add form 10 specific merge tags
        if ($form_id == 10) {
            $merge_tags[] = array(
                'label' => 'Accountant Invoice Link',
                'tag' => '{AccountantInvoiceLink}'
            );
        }
    }
    
    return $merge_tags;
}

// Enable merge tag processing in HTML fields for forms 9 and 10
add_filter('gform_pre_render_9', 'bst_enable_html_merge_tags');
add_filter('gform_pre_render_10', 'bst_enable_html_merge_tags');
function bst_enable_html_merge_tags($form) {
    if (!class_exists('GFCommon')) {
        return $form;
    }
    
    // Get current entry if available
    $entry = GFFormsModel::get_current_lead();
    
    // Process HTML fields to replace merge tags
    foreach ($form['fields'] as &$field) {
        if ($field->type === 'html' && !empty($field->content)) {
            // Replace merge tags in HTML field content
            $field->content = GFCommon::replace_variables($field->content, $form, $entry, false, false, false, 'html');
        }
    }
    
    return $form;
}

// Auto-populate region code field on Form 9 page navigation (for multi-page forms)
add_filter('gform_validation_9', 'bst_populate_region_code_form9_validation');
function bst_populate_region_code_form9_validation($validation_result) {
    // Always recalculate region code on every validation to handle phone number changes
    // Get phone number from field 34 - check multiple possible formats
    $phone_number = rgpost('input_34');
    
    // Some phone field plugins store the full international number in a hidden field
    if (empty($phone_number)) {
        $phone_number = rgpost('input_34_full'); // Full international format
    }
    if (empty($phone_number)) {
        $phone_number = rgpost('input_34_intl'); // International format
    }
    
    // Detect region from phone number
    if (!empty($phone_number)) {
        $region_code = bst_detect_region_from_phone($phone_number);
    } else {
        $region_code = 'Other';
    }
    
    // Always update field 232 with the detected region code
    $_POST['input_232'] = $region_code;
    
    // Also update the field in the form so it persists correctly
    $form = $validation_result['form'];
    foreach ($form['fields'] as &$field) {
        if ($field->id == 232) {
            $field->defaultValue = $region_code;
            break;
        }
    }
    $validation_result['form'] = $form;
    
    return $validation_result;
}

// Auto-populate region code field on Form 9 submission (fallback)
add_action('gform_pre_submission_9', 'bst_populate_region_code_form9');
function bst_populate_region_code_form9($form) {
    // Get phone number from field 34 - check multiple possible formats
    $phone_number = rgpost('input_34');
    
    // Some phone field plugins store the full international number in a hidden field
    // Check for variations
    if (empty($phone_number)) {
        $phone_number = rgpost('input_34_full'); // Full international format
    }
    if (empty($phone_number)) {
        $phone_number = rgpost('input_34_intl'); // International format
    }
    
    // Detect region from phone number
    if (!empty($phone_number)) {
        $region_code = bst_detect_region_from_phone($phone_number);
    } else {
        $region_code = 'Other';
    }
    
    // Set field 232 with the detected region code (only if not already set)
    if (empty($_POST['input_232'])) {
        $_POST['input_232'] = $region_code;
    }
}

// Auto-populate region code field on Form 10 page navigation (for multi-page forms)
add_filter('gform_validation_10', 'bst_populate_region_code_form10_validation');
function bst_populate_region_code_form10_validation($validation_result) {
    // Always recalculate region code on every validation to handle phone/address changes
    // First try address country from field 139
    $address_country = rgpost('input_139_6');
    
    if (!empty($address_country)) {
        $region_code = bst_map_country_to_region($address_country);
    } else {
        // Fall back to phone number from field 34
        $phone_number = rgpost('input_34');
        
        if (empty($phone_number)) {
            $phone_number = rgpost('input_34_full');
        }
        if (empty($phone_number)) {
            $phone_number = rgpost('input_34_intl');
        }
        
        if (!empty($phone_number)) {
            $region_code = bst_detect_region_from_phone($phone_number);
        } else {
            $region_code = 'Other';
        }
    }
    
    // Always update field 280 with the detected region code
    $_POST['input_280'] = $region_code;
    
    // Also update the field in the form so it persists correctly
    $form = $validation_result['form'];
    foreach ($form['fields'] as &$field) {
        if ($field->id == 280) {
            $field->defaultValue = $region_code;
            break;
        }
    }
    $validation_result['form'] = $form;
    
    return $validation_result;
}

// Auto-populate region code field on Form 10 submission (fallback)
add_action('gform_pre_submission_10', 'bst_populate_region_code_form10');
function bst_populate_region_code_form10($form) {
    // First try address country from field 139
    $address_country = rgpost('input_139_6');
    
    if (!empty($address_country)) {
        $region_code = bst_map_country_to_region($address_country);
    } else {
        // Fall back to phone number from field 34 - check multiple possible formats
        $phone_number = rgpost('input_34');
        
        // Some phone field plugins store the full international number in a hidden field
        if (empty($phone_number)) {
            $phone_number = rgpost('input_34_full'); // Full international format
        }
        if (empty($phone_number)) {
            $phone_number = rgpost('input_34_intl'); // International format
        }
        
        if (!empty($phone_number)) {
            $region_code = bst_detect_region_from_phone($phone_number);
        } else {
            $region_code = 'Other';
        }
    }
    
    // Set field 280 with the detected region code (only if not already set)
    if (empty($_POST['input_280'])) {
        $_POST['input_280'] = $region_code;
    }
}

add_filter('gform_replace_merge_tags', 'bst_replace_booking_merge_tags', 10, 7);
function bst_replace_booking_merge_tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format) {
    // Replace Blue Strada Email address
    if (strpos($text, '{BstEmail}') !== false) {
        $from_email = get_option('bst_from_email_address', 'info@bluestradatours.com');
        $text = str_replace('{BstEmail}', $from_email, $text);
    }
    
    // Replace Email Signature
    if (strpos($text, '{BstEmailSignature}') !== false) {
        $signature = get_option('bst_email_signature', '');
        $text = str_replace('{BstEmailSignature}', $signature, $text);
    }
    
    // Replace Bank Wire Discount percentage first (doesn't require any form/entry validation)
    if (strpos($text, '{BankWireDiscount}') !== false) {
        $discount_rate = get_option('bst_bank_wire_discount', 2.5);
        // Return raw number for calculations - don't format it
        $text = str_replace('{BankWireDiscount}', $discount_rate, $text);
    }
    
    // Replace Bank Wire Instructions
    // Supports both parameterized and auto-detect modes:
    // Parameterized: {BankWireInstructions:code_field=123:type=Deposit:amount_field=177:currency=EUR}
    // Auto-detect: {BankWireInstructions} - uses fields 232 (code) and 230 (amount)
    if (strpos($text, '{BankWireInstructions') !== false) {
        // Check if it has parameters or is auto-detect mode
        if (preg_match('/{BankWireInstructions:([^}]+)}/', $text, $matches)) {
            // Parameterized mode
            $params_string = $matches[1];
            $params = array();
            
            // Parse parameters
            $param_pairs = explode(':', $params_string);
            foreach ($param_pairs as $pair) {
                if (strpos($pair, '=') !== false) {
                    list($key, $value) = explode('=', $pair, 2);
                    $params[trim($key)] = trim($value);
                }
            }
            
            // Get code value
            $code_value = 'EUR';
            if (isset($params['code_field']) && $entry && is_array($entry)) {
                $code_value = rgar($entry, $params['code_field'], 'EUR');
            } elseif (isset($params['code'])) {
                $code_value = $params['code'];
            }
            
            // Get amount value
            $amount_value = 0;
            if (isset($params['amount_field']) && $entry && is_array($entry)) {
                $amount_value = floatval(rgar($entry, $params['amount_field'], 0));
            } elseif (isset($params['amount'])) {
                $amount_value = floatval($params['amount']);
            }
            
            // Get type
            $type_value = isset($params['type']) ? $params['type'] : 'Deposit';
            
            // Get currency
            $currency_value = isset($params['currency']) ? $params['currency'] : 'EUR';
            
            $pattern = '/{BankWireInstructions:[^}]+}/';
        } elseif (strpos($text, '{BankWireInstructions}') !== false) {
            // Auto-detect mode - use saved region code from field 232 (Form 9) or 280 (Form 10)
            
            // Determine which field has the region code based on form
            $region_field_id = '232'; // Default to Form 9
            if ($form && is_array($form) && !empty($form['id']) && $form['id'] == 10) {
                $region_field_id = '280';
            }
            
            // Check if we have a saved region code from entry
            $code_value = $entry && is_array($entry) ? rgar($entry, $region_field_id, '') : '';
            
            // If no entry, check POST data (multi-page form - field 232 from page 1)
            if (empty($code_value)) {
                $code_value = rgpost('input_' . $region_field_id);
            }
            
            // If still no value, try to read from field default value
            if (empty($code_value) && $form && is_array($form) && isset($form['fields'])) {
                foreach ($form['fields'] as $field) {
                    if ($field->id == $region_field_id) {
                        $code_value = !empty($field->defaultValue) ? $field->defaultValue : '';
                        break;
                    }
                }
            }
            
            // If no saved code, detect from phone number
            if (empty($code_value)) {
                // Try to get phone from entry first, then from POST (multi-page forms)
                $phone_number = '';
                if ($entry && is_array($entry)) {
                    $phone_number = rgar($entry, '34', '');
                }
                
                // If no entry, check POST data (multi-page form in progress)
                if (empty($phone_number)) {
                    $phone_number = rgpost('input_34');
                    if (empty($phone_number)) {
                        $phone_number = rgpost('input_34_full');
                    }
                    if (empty($phone_number)) {
                        $phone_number = rgpost('input_34_intl');
                    }
                }
                
                if (!empty($phone_number)) {
                    $code_value = bst_detect_region_from_phone($phone_number);
                } else {
                    $code_value = 'Other';
                }
                
                // For form 10, check address field 139 and override if present
                if ($form && is_array($form) && !empty($form['id']) && $form['id'] == 10) {
                    $address_country = '';
                    if ($entry && is_array($entry)) {
                        $address_country = rgar($entry, '139.6', '');
                    }
                    // Check POST for multi-page form
                    if (empty($address_country)) {
                        $address_country = rgpost('input_139_6');
                    }
                    
                    if (!empty($address_country)) {
                        $code_value = bst_map_country_to_region($address_country);
                    }
                }
            }
            
            // Validate bank wire code
            $valid_codes = array('USD', 'CAD', 'AUD', 'EUR', 'GBP', 'Other');
            if (empty($code_value) || !in_array($code_value, $valid_codes, true)) {
                // Log invalid code to debug log
                if (function_exists('error_log')) {
                    error_log('BST Bank Wire: Invalid bank wire code detected: "' . ($code_value ?? 'empty') . '". Defaulting to "Other".');
                }
                $code_value = 'Other';
            }
            
            // Get amount, discount, and type based on form
            if ($form && is_array($form) && !empty($form['id']) && $form['id'] == 10) {
                // Form 10: Read directly from fields 279 (amount after discount) and 278 (discount amount)
                $amount_value = 0;
                $discount_value = 0;
                $type_value = 'Balance Due';
                
                if ($entry && is_array($entry)) {
                    // Get amount after discount and discount amount from entry
                    $amount_value = floatval(rgar($entry, '279', 0));
                    $discount_value = floatval(rgar($entry, '278', 0));
                } else {
                    // Displaying on form - read from form field values
                    if (isset($form['fields']) && is_array($form['fields'])) {
                        foreach ($form['fields'] as $field) {
                            if ($field->id == 279) {
                                $amount_value = !empty($field->defaultValue) ? floatval($field->defaultValue) : 0;
                            }
                            if ($field->id == 278) {
                                $discount_value = !empty($field->defaultValue) ? floatval($field->defaultValue) : 0;
                            }
                        }
                    }
                }
            } else {
                // Form 9: ALWAYS calculate from fields 177 and 231
                $amount_value = 0;
                $discount_value = 0;
                $type_value = 'Deposit';
                
                if ($entry && is_array($entry)) {
                    // Get undiscounted deposit and discount percent from entry
                    $deposit_undiscounted = floatval(rgar($entry, '177', 0));
                    $discount_percent = floatval(rgar($entry, '231', 0));
                    
                    if ($deposit_undiscounted > 0 && $discount_percent > 0) {
                        $discount_value = $deposit_undiscounted * ($discount_percent / 100);
                        $amount_value = $deposit_undiscounted - $discount_value;
                    } else if ($deposit_undiscounted > 0) {
                        // No discount, use undiscounted amount
                        $amount_value = $deposit_undiscounted;
                    }
                } else {
                    // Displaying on form - read from form field values
                    $deposit_undiscounted = 0;
                    $discount_percent = 0;
                    
                    if (isset($form['fields']) && is_array($form['fields'])) {
                        foreach ($form['fields'] as $field) {
                            if ($field->id == 177) {
                                $deposit_undiscounted = !empty($field->calculatedValue) ? floatval($field->calculatedValue) : floatval($field->defaultValue ?? 0);
                            }
                            if ($field->id == 231) {
                                $discount_percent = !empty($field->defaultValue) ? floatval($field->defaultValue) : 0;
                            }
                        }
                    }
                    
                    // If form context has no valid data, skip generating instructions
                    if ($deposit_undiscounted == 0) {
                        return $text; // Return original text without replacement
                    }
                    
                    if ($deposit_undiscounted > 0 && $discount_percent > 0) {
                        $discount_value = $deposit_undiscounted * ($discount_percent / 100);
                        $amount_value = $deposit_undiscounted - $discount_value;
                    } else if ($deposit_undiscounted > 0) {
                        $amount_value = $deposit_undiscounted;
                    }
                }
            }
            
            // Auto-detect currency from form settings
            $currency_value = 'EUR'; // default
            if ($form && is_array($form) && !empty($form['currency'])) {
                $currency_value = $form['currency'];
            }
            
            $pattern = '/{BankWireInstructions}/';
        } else {
            // No match, skip
            return $text;
        }
        
        // Generate the bank wire instructions HTML with wrapper
        $instructions_content = bst_generate_bank_wire_instructions(
            $code_value,
            $type_value,
            $amount_value,
            $currency_value,
            $entry,
            $discount_value
        );
        
        // Wrap with container and header for Gravity Forms display
        $instructions_html = '<div style="margin-bottom: 30px; border: 1px solid #dddddd; overflow: hidden;">';
        $instructions_html .= '<div style="padding: 15px 20px; background-color: #ffc107; color: #856404; font-size: 18px; font-weight: 600; margin: 0; text-align: center;">';
        $instructions_title = ($type_value === 'Balance Due') ? 'Balance Payment Instructions' : 'Deposit Payment Instructions';
        $instructions_html .= $instructions_title;
        $instructions_html .= '</div>';
        $instructions_html .= '<div style="padding: 20px; background-color: #f5f5f5;">';
        
        $instructions_html .= $instructions_content;
        $instructions_html .= '</div></div>';
        
        $text = preg_replace($pattern, $instructions_html, $text, 1);
    }
    
    // Check if form and entry are valid
    if (!$form || !is_array($form) || empty($form['id'])) {
        return $text;
    }
    
    // Only process for booking forms (forms 9 and 10)
    if ($form['id'] != 9 && $form['id'] != 10) {
        return $text;
    }
    
    // Check if entry is valid
    if (!$entry || !is_array($entry) || empty($entry['id'])) {
        return $text;
    }
    
    // Get the entry ID
    $entry_id = $entry['id'];
    
    // Get the booking ID (needed for status links)
    global $wpdb;
    
    // For form 10 (finalization), get booking by ID from field 261 (which contains direct booking ID)
    // For form 9 (initial booking), get booking by entry ID
    if ($form['id'] == 10) {
        $booking_id = rgar($entry, '261');
        if (!empty($booking_id) && is_numeric($booking_id)) {
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
                intval($booking_id)
            ));
        } else {
            $booking = null;
        }
    } else {
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE booking_entry_id = %d",
            $entry_id
        ));
    }
    
    // Encode entry ID for confirmation (created immediately)
    $encoded_entry_id = bst_encode_booking_id($entry_id);
    
    // Encode booking ID for status (if booking exists)
    $encoded_booking_id = '';
    if ($booking) {
        $encoded_booking_id = bst_encode_booking_id($booking->id);
    }
    
    // Generate booking summary (customer version - with customer link)
    if (strpos($text, '{CustBookingSummary}') !== false) {
        $summary_html = bst_generate_booking_summary_html($entry_id, false, 'customer', $encoded_entry_id, false);
        // Wrap with email-appropriate wrapper (header already included in summary)
        $booking_summary = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
        $booking_summary .= $summary_html;
        $booking_summary .= '</div>';
        $text = str_replace('{CustBookingSummary}', $booking_summary, $text);
    }
    
    // Generate BST booking summary (admin version - with admin links)
    if (strpos($text, '{BstBookingSummary}') !== false) {
        $summary_html = bst_generate_booking_summary_html($entry_id, false, 'admin', $encoded_entry_id, false);
        // Wrap with email-appropriate wrapper (header already included in summary)
        $bst_booking_summary = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
        $bst_booking_summary .= $summary_html;
        $bst_booking_summary .= '</div>';
        $text = str_replace('{BstBookingSummary}', $bst_booking_summary, $text);
    }
    
    // Generate BST booking subject (use entry data directly)
    if (strpos($text, '{BstBookingSubject}') !== false) {
        // Build guest name from entry using helper
        $guest1_first = rgar($entry, '31.3');
        $guest1_last = rgar($entry, '31.6');
        $guest2_first = rgar($entry, '215.3');
        $guest2_last = rgar($entry, '215.6');
        
        $guest_name = bst_format_guest_name($guest1_first, $guest1_last, $guest2_first, $guest2_last);
        
        // Get tour info - for form 10, get from original form 9 entry; for form 9, from current entry
        if ($form['id'] == 10 && $booking) {
            // Get the original form 9 entry
            $original_entry = GFAPI::get_entry($booking->booking_entry_id);
            if ($original_entry && is_array($original_entry)) {
                $tour_text = rgar($original_entry, '137');
                $tour_date_text = rgar($original_entry, '141');
                $extension_added = rgar($original_entry, '224');
            } else {
                $tour_text = $booking->tour_text;
                $tour_date_text = $booking->tour_date_text;
                $extension_added = '';
            }
        } else {
            $tour_text = rgar($entry, '137');
            $tour_date_text = rgar($entry, '141');
            $extension_added = rgar($entry, '224');
        }
        
        // Determine if this is a booking (form 9) or finalization (form 10)
        $action_word = (intval($form['id']) == 10) ? 'finalized' : 'booked';
        
        $subject = $guest_name . ' ' . $action_word . ' the ' . $tour_text . ' tour';
        if (!empty($extension_added) && $extension_added == 1) {
            $subject .= ' with extension';
        }
        $subject .= '!';
        $text = str_replace('{BstBookingSubject}', $subject, $text);
    }
    
    // Generate confirmation link (uses entry ID)
    if (strpos($text, '{ConfirmationLink}') !== false) {
        $confirmation_url = site_url('/bookingconfirmation/') . '?eid=' . $encoded_entry_id;
        // Use different link text based on form ID
        $link_text = ($form['id'] == 10) ? 'View Finalization Confirmation' : 'View Booking Confirmation';
        $confirmation_link = '<a href="' . esc_url($confirmation_url) . '" style="color: #0066cc; text-decoration: none;">' . $link_text . '</a>';
        $text = str_replace('{ConfirmationLink}', $confirmation_link, $text);
    }
    
    // Generate the confirmation query string (uses entry ID for redirects)
    if (strpos($text, '{ConfirmationQueryString}') !== false) {
        $query_string = 'eid=' . $encoded_entry_id;
        $text = str_replace('{ConfirmationQueryString}', $query_string, $text);
    }
    
    // Generate customer booking subject (use entry data directly)
    if (strpos($text, '{CustBookingSubject}') !== false) {
        // For form 10, get from original form 9 entry
        if ($form['id'] == 10 && $booking) {
            $original_entry = GFAPI::get_entry($booking->booking_entry_id);
            if ($original_entry && is_array($original_entry)) {
                $tour_text = rgar($original_entry, '137');
                $tour_date_text = rgar($original_entry, '141');
                $extension_added = rgar($original_entry, '224');
            } else {
                $tour_text = $booking->tour_text;
                $tour_date_text = $booking->tour_date_text;
                $extension_added = '';
            }
        } else {
            $tour_text = rgar($entry, '137');  // Tour name
            $tour_date_text = rgar($entry, '141');  // Tour dates
            $extension_added = rgar($entry, '224');  // Extension field
        }
        
        // Determine if this is a booking (form 9) or finalization (form 10)
        $action_word = (intval($form['id']) == 10) ? 'finalized' : 'booked';
        
        $subject = 'You ' . $action_word . ' the ' . $tour_text . ' tour';
        if (!empty($extension_added) && $extension_added == 1) {
            $subject .= ' with extension';
        }
        $subject .= '!';
        $text = str_replace('{CustBookingSubject}', $subject, $text);
    }
    
    // Generate customer booking details link (uses entry ID)
    if (strpos($text, '{CustBookingDetailsLink}') !== false && $encoded_entry_id) {
        $details_url = site_url('/bookingdetails/') . '?eid=' . $encoded_entry_id;
        $details_link = '<a href="' . esc_url($details_url) . '" style="color: #0066cc; text-decoration: none;">View Booking Details</a>';
        $text = str_replace('{CustBookingDetailsLink}', $details_link, $text);
    }
    
    // Generate BST booking details link (uses entry ID, includes type=bst parameter)
    if (strpos($text, '{BstBookingDetailsLink}') !== false && $encoded_entry_id) {
        $details_url = site_url('/bookingdetails/') . '?eid=' . $encoded_entry_id . '&type=bst';
        $details_link = '<a href="' . esc_url($details_url) . '" style="color: #0066cc; text-decoration: none;">View Booking Details</a>';
        $text = str_replace('{BstBookingDetailsLink}', $details_link, $text);
    }
    
    // Generate Accountant Invoice link (uses finalization entry ID for form 10 only)
    if (strpos($text, '{AccountantInvoiceLink}') !== false && $encoded_entry_id) {
        // Only generate link for form 10 (finalization form)
        if ($form && is_array($form) && $form['id'] == 10) {
            $invoice_url = site_url('/bookinginvoice/') . '?eid=' . $encoded_entry_id;
            $invoice_link = '<a href="' . esc_url($invoice_url) . '" style="color: #0066cc; text-decoration: none;">Visualizza fattura pro forma</a>';
            $text = str_replace('{AccountantInvoiceLink}', $invoice_link, $text);
        } else {
            // For non-form-10, replace with empty string or message
            $text = str_replace('{AccountantInvoiceLink}', '', $text);
        }
    }
    
    // Generate reservation link (uses booking ID with bid parameter)
    if (strpos($text, '{reservation_link}') !== false) {
        if ($booking && !empty($booking->id)) {
            $reservation_url = bst_get_reservation_url($booking->id);
            $reservation_link = '<a href="' . esc_url($reservation_url) . '" style="color: #0066cc; text-decoration: none;">Complete Your Reservation</a>';
            $text = str_replace('{reservation_link}', $reservation_link, $text);
        } else {
            $text = str_replace('{reservation_link}', '', $text);
        }
    }
    
    // Generate finalization link (uses booking ID with bid parameter)
    if (strpos($text, '{finalization_link}') !== false) {
        if ($booking && !empty($booking->id)) {
            $finalization_url = bst_get_finalization_url($booking->id);
            $finalization_link = '<a href="' . esc_url($finalization_url) . '" style="color: #0066cc; text-decoration: none;">Finalize Your Booking</a>';
            $text = str_replace('{finalization_link}', $finalization_link, $text);
        } else {
            $text = str_replace('{finalization_link}', '', $text);
        }
    }
    
    return $text;
}

// Generate booking status HTML for email notifications
function bst_generate_booking_status_html($booking, $encoded_id = '', $include_admin_links = false) {
    // Get the entry if we need it
    $entry = null;
    if ($booking->booking_entry_id) {
        $entry = GFAPI::get_entry($booking->booking_entry_id);
    }
    
    // Get booking details data using shared function
    $data = bst_get_booking_details_data($booking, $entry);
    $guest_name = $data['guest_name'];
    $currency_symbol = $data['currency_symbol'];
    $has_coupon = $data['has_coupon'];
    $has_extension = $data['has_extension'];
    
    // Check for pending Bank Wire payments (use line payment status, not amount==0 alone)
    $pending_deposit_eur = 0;
    $pending_balance_eur = 0;

    $dep_amt = floatval( $booking->deposit_payment_amount ?? 0 );
    $bal_amt = floatval( $booking->balance_payment_amount ?? 0 );
    $dep_pending = function_exists( 'bst_payment_line_received_for_display' )
        ? ! bst_payment_line_received_for_display( $booking->deposit_payment_status ?? '', $dep_amt )
        : ( $dep_amt == 0 );
    $bal_pending = function_exists( 'bst_payment_line_received_for_display' )
        ? ! bst_payment_line_received_for_display( $booking->balance_payment_status ?? '', $bal_amt )
        : ( $bal_amt == 0 );

    if ( ! empty( $booking->deposit_payment_method ) &&
        $booking->deposit_payment_method === 'Bank Wire' &&
        $dep_pending &&
        $entry ) {
        $pending_deposit_eur = floatval( rgar( $entry, '177' ) );
    }

    if ( ! empty( $booking->balance_payment_method ) &&
        $booking->balance_payment_method === 'Bank Wire' &&
        $bal_pending ) {
        $pending_balance_eur = floatval( $booking->balance_due ?? 0 ) - $pending_deposit_eur;
    }

    $pending_payment_note_html = function_exists( 'bst_booking_pending_payment_note_html' ) ? bst_booking_pending_payment_note_html( $booking ) : '';
    
    // Get booking date
    $booking_date = !empty($booking->created_at) ? $booking->created_at : '';
    if (empty($booking_date) && $entry) {
        $booking_date = $entry['date_created'];
    }
    
    // Build HTML with embedded CSS
    ob_start();
    ?>
    <style>
        .bs-container { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #ffffff; }
        .bs-container h2 { color: #333333; border-bottom: 2px solid #0066cc; padding-bottom: 10px; margin-top: 0; }
        .bs-section { margin-bottom: 30px; border: 1px solid #dddddd; padding: 15px; background-color: #f9f9f9; }
        .bs-section h3 { color: #0066cc; margin-top: 0; font-size: 18px; }
        .bs-section.payment-header { padding: 0; }
        .bs-section.payment-header h3 { margin: 0; padding: 10px 15px; background: #0073aa; color: white; }
        .bs-section.payment-header .content { padding: 15px; }
        .bs-section.links-section { background-color: #fff3cd; }
        .bs-section.links-section h3 { color: #856404; }
        .bs-table { width: 100%; border-collapse: collapse; }
        .bs-table td { padding: 8px 0; }
        .bs-table td:first-child { width: 40%; color: #666666; font-weight: bold; }
        .bs-table td:last-child { color: #333333; }
        .bs-status-badge { display: inline-block; padding: 4px 12px; background-color: #28a745; color: #ffffff; font-weight: bold; }
        .bs-pending-text { color: #ff6600; }
        .bs-no-payment { padding: 8px 0; text-align: center; color: #999999; }
        .bs-terms { margin-bottom: 30px; border: 1px solid #dddddd; overflow: hidden; }
        .bs-terms details { margin: 0; }
        .bs-terms summary { padding: 15px; background-color: #f5f5f5; cursor: pointer; font-size: 18px; font-weight: bold; list-style: none; }
        .bs-terms summary .arrow { display: inline-block; margin-right: 8px; }
        .bs-terms .content { padding: 20px; background-color: #ffffff; }
        .bs-terms .term-item { margin-bottom: 25px; border-bottom: 1px solid #eeeeee; padding-bottom: 15px; }
        .bs-terms .term-item .title { margin-bottom: 10px; }
        .bs-terms .term-item .title strong { color: #333333; font-size: 16px; }
        .bs-terms .term-item .title .check { margin-right: 8px; }
        .bs-terms .term-item .title .check-yes { color: #28a745; }
        .bs-terms .term-item .title .check-no { color: #999999; }
        .bs-terms .term-item .consent-date { color: #666666; font-size: 14px; margin-bottom: 10px; margin-left: 24px; }
        .bs-terms .term-item .consent-text { margin-left: 24px; padding: 15px; background-color: #f9f9f9; border-left: 3px solid #0073aa; max-height: 200px; overflow-y: auto; font-size: 14px; line-height: 1.6; }
        .bs-footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dddddd; color: #666666; font-size: 14px; }
        .bs-footer p { margin: 0; }
        .bs-cta { text-align: center; margin: 25px 0; padding: 20px 15px; background-color: #f9f9f9; border: 1px solid #e0e0e0; }
        .bs-cta p { margin: 0; }
        .bs-cta p:first-child { margin-bottom: 12px; color: #666666; font-size: 14px; }
        .bs-cta a { display: inline-block; background-color: #0073aa; color: #ffffff; padding: 12px 24px; text-decoration: none; font-weight: 600; font-size: 15px; }
    </style>
    <div class="bs-container">
        <h2>Booking Status</h2>
        
        <!-- Booking Information -->
        <div class="bs-section">
            <h3>Booking Information</h3>
            <table class="bs-table">
                <tr>
                    <td>Booking ID:</td>
                    <td><?php echo esc_html($booking->id); ?></td>
                </tr>
                <tr>
                    <td>Booking Date:</td>
                    <td><?php echo esc_html(date('Y-m-d', strtotime($booking_date))); ?></td>
                </tr>
                <tr>
                    <td>Status:</td>
                    <td>
                        <span class="bs-status-badge"><?php echo esc_html($booking->booking_status); ?></span>
                    </td>
                </tr>
                <tr>
                    <td>' . (!empty($guest2_first) ? 'Guests:' : 'Guest:') . '</td>
                    <td><?php echo esc_html($guest_name); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Tour Information -->
        <div class="bs-section">
            <h3>Tour Information</h3>
            <table class="bs-table">
                <tr>
                    <td>Tour:</td>
                    <td><?php 
                        $tour_display = $booking->tour_text;
                        if (!empty($booking->tour_date_text)) {
                            $tour_display .= ' (' . $booking->tour_date_text . ')';
                        }
                        echo esc_html($tour_display); 
                    ?></td>
                </tr>
                <?php if ($has_extension && !empty($booking->tour_extension_text)): ?>
                <tr>
                    <td>Tour Extension:</td>
                    <td><?php 
                        $extension_display = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $booking->tour_extension_text);
                        if (!empty($booking->tour_extension_date_text)) {
                            $extension_display .= ' (' . $booking->tour_extension_date_text . ')';
                        }
                        echo esc_html($extension_display); 
                    ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Package:</td>
                    <td><?php echo esc_html($booking->tour_package_text); ?></td>
                </tr>
                <?php if (!empty($booking->vehicle1)): ?>
                <tr>
                    <td><?php echo !empty($booking->vehicle2) ? 'Vehicles:' : 'Vehicle:'; ?></td>
                    <td><?php 
                        $vehicle_display = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $booking->vehicle1);
                        if (!empty($booking->vehicle2)) {
                            $vehicle_display .= ' / ' . preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $booking->vehicle2);
                        }
                        echo esc_html($vehicle_display);
                    ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($has_coupon): ?>
                <tr>
                    <td>Tour Price:</td>
                    <td><?php echo esc_html($currency_symbol . number_format($booking->tour_price, 2)); ?></td>
                </tr>
                <tr>
                    <td>Coupon<?php if (!empty($booking->coupon_code)): ?> (<?php echo esc_html($booking->coupon_code); ?>)<?php endif; ?>:</td>
                    <td>-<?php echo esc_html($currency_symbol . number_format($booking->coupon_amount, 2)); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><?php echo $has_coupon ? 'Net Tour Price:' : 'Tour Price:'; ?></td>
                    <td><strong><?php echo esc_html($currency_symbol . number_format($booking->net_tour_price, 2)); ?></strong></td>
                </tr>
                <?php if (!empty($booking->additional_charge) && floatval($booking->additional_charge) != 0): ?>
                <tr>
                    <td>Additional Charge:</td>
                    <td><?php echo esc_html($currency_symbol . number_format($booking->additional_charge, 2)); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Total Paid:</td>
                    <td>
                        <?php echo esc_html($currency_symbol . number_format($booking->total_paid, 2)); ?>
                        <?php if ($pending_deposit_eur > 0): ?>
                            (<span class="bs-pending-text">Awaiting <?php echo esc_html($currency_symbol . number_format($pending_deposit_eur, 2)); ?> Deposit Payment via Bank Transfer</span>)
                        <?php elseif ($pending_balance_eur > 0): ?>
                            (<span class="bs-pending-text">Awaiting <?php echo esc_html($currency_symbol . number_format($pending_balance_eur, 2)); ?> Balance Payment via Bank Transfer</span>)
                        <?php endif; ?>
                        <?php echo $pending_payment_note_html; ?>
                    </td>
                </tr>
                <tr>
                    <td>Remaining Balance:</td>
                    <td>
                        <strong><?php echo esc_html($currency_symbol . number_format($booking->balance_due, 2)); ?></strong>
                        <?php if ($pending_deposit_eur > 0): ?>
                            (<span class="bs-pending-text"><?php echo esc_html($currency_symbol . number_format($booking->balance_due - $pending_deposit_eur, 2)); ?> after Deposit Payment received</span>)
                        <?php elseif ($pending_balance_eur > 0): ?>
                            (<span class="bs-pending-text"><?php echo esc_html($currency_symbol . number_format($booking->balance_due - $pending_balance_eur, 2)); ?> after Balance Payment received</span>)
                        <?php endif; ?>
                        <?php echo $pending_payment_note_html; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Payment History -->
        <div class="bs-section payment-header">
            <h3>Payment History</h3>
            <div class="content">
            <table class="bs-table">
                <?php if (!empty($booking->deposit_payment_amount) && floatval($booking->deposit_payment_amount) > 0): ?>
                <tr>
                    <td>Deposit Payment:</td>
                    <td>
                        <?php echo esc_html($currency_symbol . number_format($booking->deposit_payment_amount, 2)); ?>
                        via <?php echo esc_html($booking->deposit_payment_method); ?>
                        <?php if (!empty($booking->deposit_payment_date)): ?>
                            on <?php echo esc_html(date('Y-m-d', strtotime($booking->deposit_payment_date))); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($booking->balance_payment_amount) && floatval($booking->balance_payment_amount) > 0): ?>
                <tr>
                    <td>Balance Payment:</td>
                    <td>
                        <?php echo esc_html($currency_symbol . number_format($booking->balance_payment_amount, 2)); ?>
                        via <?php echo esc_html($booking->balance_payment_method); ?>
                        <?php if (!empty($booking->balance_payment_date)): ?>
                            on <?php echo esc_html(date('Y-m-d', strtotime($booking->balance_payment_date))); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php 
                // Show "No payments" message only if BOTH deposit and balance are empty
                $has_deposit = !empty($booking->deposit_payment_amount) && floatval($booking->deposit_payment_amount) > 0;
                $has_balance = !empty($booking->balance_payment_amount) && floatval($booking->balance_payment_amount) > 0;
                if (!$has_deposit && !$has_balance): 
                    // Check if there's a pending bank wire payment (use the same variables as the Total Paid line)
                    $has_pending_wire = ($pending_deposit_eur > 0 || $pending_balance_eur > 0);
                    $no_payment_text = $has_pending_wire ? 'No payments received yet (bank wire payment pending).' : 'No payments recorded yet.';
                ?>
                <tr>
                    <td colspan="2" class="bs-no-payment"><?php echo esc_html($no_payment_text); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            </div>
        </div>
        
        <?php
        // Add expandable bank wire instructions section after payments (if any bank wire payments exist)
        echo bst_generate_bank_wire_section($booking, $entry, false, false, 'details');
        ?>
        
        <?php if ($include_admin_links && !empty($encoded_id)): ?>
        <!-- Links Section (BST Internal Use Only) -->
        <div class="bs-section links-section">
            <h3>Links (Internal Use Only)</h3>
            <table class="bs-table">
                <tr>
                    <td>Customer Status Page:</td>
                    <td>
                        <a href="<?php echo esc_url(site_url('/bookingstatus/') . '?eid=' . $encoded_id); ?>" style="color: #0066cc; text-decoration: none;">View Customer Status Page</a>
                    </td>
                </tr>
                <tr>
                    <td>Admin Booking Page:</td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" style="color: #0066cc; text-decoration: none;">View in Admin (Login Required)</a>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>
        
        <?php 
        // Terms and Conditions Sections
        if ($booking->booking_entry_id): 
            $booking_entry = GFAPI::get_entry($booking->booking_entry_id);
            if ($booking_entry && !is_wp_error($booking_entry)):
        ?>
        <!-- Booking Terms and Conditions -->
        <div class="bs-terms">
            <details>
                <summary>
                    <span class="arrow">▶</span>Booking Terms and Conditions
                </summary>
                <div class="content">
                    <?php
                    $booking_form = GFAPI::get_form(9);
                    $booking_terms = array(
                        '36' => 'Acceptance of the Registration Terms',
                        '37' => 'Acceptance of Specific Clauses',
                        '38' => 'Privacy and Cookies Consent'
                    );
                    
                    foreach ($booking_terms as $field_id => $label):
                        $consented = rgar($booking_entry, $field_id);
                        $consent_date = !empty($booking_entry['date_created']) ? date('F j, Y', strtotime($booking_entry['date_created'])) : '';
                        
                        // Get the consent text using GF's method which handles revision lookups
                        $field = GFAPI::get_field($booking_form, $field_id);
                        $consent_text = '';
                        
                        if ($field && $consented) {
                            // Use the field's get_value_export method which retrieves historical consent text from revisions
                            $consent_text = $field->get_value_export($booking_entry, $field_id . '.3');
                        }
                    ?>
                    <div class="term-item">
                        <div class="title">
                            <strong>
                                <?php if ($consented): ?>
                                    <span class="check check-yes">✓</span>
                                <?php else: ?>
                                    <span class="check check-no">○</span>
                                <?php endif; ?>
                                <?php echo esc_html($label); ?>
                            </strong>
                        </div>
                        <?php if ($consented && $consent_date): ?>
                        <div class="consent-date">
                            Agreed on <?php echo esc_html($consent_date); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($consent_text)): ?>
                        <div class="consent-text">
                            <?php echo wp_kses_post($consent_text); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
        

        
        <?php 
            endif;
        endif;
        
        // Finalization Terms and Conditions (only show if finalized)
        if (!empty($booking->finalization_entry_id)): 
            $finalization_entry = GFAPI::get_entry($booking->finalization_entry_id);
            if ($finalization_entry && !is_wp_error($finalization_entry)):
        ?>
        <!-- Finalization Terms and Conditions -->
        <div class="bs-terms">
            <details>
                <summary>
                    <span class="arrow">▶</span>Finalization Terms and Conditions
                </summary>
                <div class="content">
                    <?php
                    $finalization_form = GFAPI::get_form(10);
                    $finalization_terms = array(
                        '36' => 'Acceptance of the Participation Terms',
                        '37' => 'Acceptance of Specific Clauses',
                        '38' => 'Privacy and Cookies Consent'
                    );
                    
                    foreach ($finalization_terms as $field_id => $label):
                        $consented = rgar($finalization_entry, $field_id);
                        $consent_date = !empty($finalization_entry['date_created']) ? date('F j, Y', strtotime($finalization_entry['date_created'])) : '';
                        
                        // Get the consent text using GF's method which handles revision lookups
                        $field = GFAPI::get_field($finalization_form, $field_id);
                        $consent_text = '';
                        
                        if ($field && $consented) {
                            // Use the field's get_value_export method which retrieves historical consent text from revisions
                            $consent_text = $field->get_value_export($finalization_entry, $field_id . '.3');
                        }
                    ?>
                    <div class="term-item">
                        <div class="title">
                            <strong>
                                <?php if ($consented): ?>
                                    <span class="check check-yes">✓</span>
                                <?php else: ?>
                                    <span class="check check-no">○</span>
                                <?php endif; ?>
                                <?php echo esc_html($label); ?>
                            </strong>
                        </div>
                        <?php if ($consented && $consent_date): ?>
                        <div class="consent-date">
                            Agreed on <?php echo esc_html($consent_date); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($consent_text)): ?>
                        <div class="consent-text">
                            <?php echo wp_kses_post($consent_text); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
        <?php 
            endif;
        endif;
        ?>
        
        <div class="bs-footer">
            <p>If you have any questions about your booking, please contact us at <strong>info@bluestradatours.com</strong></p>
        </div>
        
        <?php if (!empty($encoded_id)): ?>
        <div class="bs-cta">
            <p>The above is a summary of your booking. Click the button below to view full details.</p>
            <p><a href="<?php echo esc_url(site_url('/bookingdetails/') . '?eid=' . $encoded_id); ?>">View Full Booking Details</a></p>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
    /* Enhance details/summary for better UX */
    .bs-terms details[open] > summary .arrow {
        display: inline-block;
        transform: rotate(90deg);
        transition: transform 0.2s;
    }
    .bs-terms details > summary::-webkit-details-marker {
        display: none;
    }
    .bs-terms details > summary {
        outline: none;
    }
    .bs-terms details > summary:hover {
        background-color: #eeeeee;
    }
    
    /* Mobile responsive styles */
    @media screen and (max-width: 600px) {
        .bs-terms summary {
            font-size: 16px !important;
            padding: 12px !important;
        }
        .bs-terms .content {
            padding: 15px !important;
        }
        .bs-terms .title strong {
            font-size: 14px !important;
        }
        .bs-terms .consent-text {
            font-size: 13px !important;
            padding: 12px !important;
        }
    }
    </style>
    <?php
    return ob_get_clean();
}

// Generate entry summary table (lightweight version for confirmations)
function bst_generate_entry_summary_table($entry_id, $include_admin_links = false, $encoded_id = '') {
    global $wpdb;
    
    $entry = GFAPI::get_entry($entry_id);
    if (!$entry || !is_array($entry)) {
        return '<p>Summary not available.</p>';
    }
    
    $form_id = $entry['form_id'];
    
    // Get names, emails, phones from entry (always from entry, can be changed)
    $guest1_first = rgar($entry, '31.3');
    $guest1_last = rgar($entry, '31.6');
    $guest1_email = rgar($entry, '33');
    $guest1_phone = rgar($entry, '34');
    $guest2_first = rgar($entry, '215.3');
    $guest2_last = rgar($entry, '215.6');
    
    // Build guest name using helper
    $guest_name = bst_format_guest_name($guest1_first, $guest1_last, $guest2_first, $guest2_last);
    
    // For GF9: Get tour/pricing from entry
    // For GF10: Get tour/pricing from booking record
    if ($form_id == 9) {
        $tour_text = rgar($entry, '137');
        $tour_date_text = rgar($entry, '141');
        $tour_package_text = rgar($entry, '138');
        $tour_currency = rgar($entry, '223');
        $payment_amount = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '177'))); // Deposit amount
        $payment_label = 'Deposit Paid:';
    } else {
        // GF10: Get from booking
        $booking_id = rgar($entry, '261');
        $booking = null;
        if ($booking_id) {
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
                intval($booking_id)
            ));
        }
        
        if ($booking) {
            $tour_text = $booking->tour_text;
            $tour_date_text = $booking->tour_date_text;
            $tour_package_text = $booking->tour_package_text;
            $tour_currency = $booking->tour_currency;
        } else {
            $tour_text = '';
            $tour_date_text = '';
            $tour_package_text = '';
            $tour_currency = 'EUR';
        }
        
        // Get balance payment from entry
        $payment_amount = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '191'))); // Balance amount
        $payment_label = 'Balance Paid:';
    }
    
    // Get currency symbol
    $currency_symbol = '€';
    if ($tour_currency === 'USD') {
        $currency_symbol = '$';
    } elseif ($tour_currency === 'GBP') {
        $currency_symbol = '£';
    }
    
    // Build HTML with CSS
    $html = '<style>
        .entry-summary-table { width: 100%; max-width: 600px; border-collapse: collapse; margin-bottom: 20px; }
        .entry-summary-table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .entry-summary-table td:first-child { font-weight: bold; width: 40%; }
        .bank-wire-details[open] .bank-wire-arrow { transform: rotate(90deg); }
    </style>';
    
    $html .= '<table class="entry-summary-table">';
    
    // Guest
    $html .= '<tr><td>' . (!empty($guest2_first) ? 'Guests:' : 'Guest:') . '</td>';
    $html .= '<td>' . esc_html($guest_name) . '</td></tr>';
    
    // Email
    $html .= '<tr><td>Email:</td>';
    $html .= '<td>' . esc_html($guest1_email) . '</td></tr>';
    
    // Phone
    $html .= '<tr><td>Phone:</td>';
    $html .= '<td>' . esc_html(bst_format_phone_international($guest1_phone)) . '</td></tr>';
    
    // Tour (only if available)
    if (!empty($tour_text)) {
        $tour_display = $tour_text;
        if (!empty($tour_date_text)) {
            $tour_display .= ' (' . $tour_date_text . ')';
        }
        $html .= '<tr><td>Tour:</td>';
        $html .= '<td>' . esc_html($tour_display) . '</td></tr>';
    }
    
    // Get extension and vehicle information
    // For form 10, get from original form 9 entry
    if ($form_id == 10 && $booking) {
        $original_entry = GFAPI::get_entry($booking->booking_entry_id);
        if ($original_entry && is_array($original_entry)) {
            $extension_added = rgar($original_entry, '224');
            $extension_text = rgar($original_entry, '225');
            $extension_date_text = rgar($original_entry, '226');
            $vehicle1 = rgar($original_entry, '140');
            $vehicle2 = rgar($original_entry, '142');
        } else {
            $extension_added = '';
            $extension_text = '';
            $extension_date_text = '';
            $vehicle1 = '';
            $vehicle2 = '';
        }
    } else {
        $extension_added = rgar($entry, '224');
        $extension_text = rgar($entry, '225');
        $extension_date_text = rgar($entry, '226');
        $vehicle1 = rgar($entry, '140');
        $vehicle2 = rgar($entry, '142');
    }
    
    // Tour Extension (if added) - show right after tour
    if (!empty($extension_added) && !empty($extension_text)) {
        // Strip prices from extension text but keep dates
        $extension_display = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $extension_text);
        if (!empty($extension_date_text)) {
            $extension_display .= ' (' . $extension_date_text . ')';
        }
        $html .= '<tr><td>Tour Extension:</td>';
        $html .= '<td>' . esc_html($extension_display) . '</td></tr>';
    }
    
    // Package (only if available)
    if (!empty($tour_package_text)) {
        $html .= '<tr><td>Package:</td>';
        $html .= '<td>' . esc_html($tour_package_text) . '</td></tr>';
    }
    
    // Vehicle (if selected) - show after package
    if (!empty($vehicle1) || !empty($vehicle2)) {
        // Strip prices from vehicle text
        $vehicle1_clean = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $vehicle1);
        $vehicle2_clean = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $vehicle2);
        
        $vehicle_display = '';
        if (!empty($vehicle1_clean) && !empty($vehicle2_clean)) {
            $vehicle_display = $vehicle1_clean . ' & ' . $vehicle2_clean;
        } else {
            $vehicle_display = !empty($vehicle1_clean) ? $vehicle1_clean : $vehicle2_clean;
        }
        
        $html .= '<tr><td>Vehicle:</td>';
        $html .= '<td>' . esc_html($vehicle_display) . '</td></tr>';
    }
    
    // Payment amount
    $html .= '<tr><td>' . $payment_label . '</td>';
    $html .= '<td>' . $currency_symbol . number_format($payment_amount, 2) . '</td></tr>';
    
    // Booking Date
    $html .= '<tr><td>Booking Date:</td>';
    $html .= '<td>' . esc_html(date('Y-m-d', strtotime($entry['date_created']))) . '</td></tr>';
    
    $html .= '</table>';
    
    return $html;
}


