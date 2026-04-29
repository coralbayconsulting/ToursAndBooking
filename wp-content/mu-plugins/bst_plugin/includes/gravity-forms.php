<?php
/**
 * Payment status constants + helpers (deposit/balance line statuses for bookings).
 */
require_once __DIR__ . '/booking-payment-status.php';

// --- Dynamic field population for encoded booking IDs ---
// Populate field 261 (booking ID field in form 10) with decoded booking ID from bid parameter
// Field 261 has parameter name "booking_update_id"
add_filter('gform_field_value_booking_update_id', 'bst_populate_booking_update_id_from_code');
function bst_populate_booking_update_id_from_code($value) {
    if (isset($_GET['bid']) && !empty($_GET['bid'])) {
        $encoded_bid = sanitize_text_field($_GET['bid']);
        $decoded_id = bst_decode_booking_id($encoded_bid);
        return $decoded_id;
    }
    return $value;
}

// Populate hidden field with bank wire discount rate for use in calculations
// Add a hidden field to your form and set its parameter name to "bank_wire_discount_perc"
add_filter('gform_field_value_bank_wire_discount_perc', 'bst_populate_bank_wire_discount_perc');
function bst_populate_bank_wire_discount_perc($value) {
    return get_option('bst_bank_wire_discount', 2.5);
}

// --- Enqueue Gravity Forms styles and scripts ---
add_action('gform_enqueue_scripts', 'enqueue_custom_gravity_forms_styles');
function enqueue_custom_gravity_forms_styles() {
    if (class_exists('GFCommon')) {
        wp_enqueue_style('gravity-forms-custom-styles', get_stylesheet_directory_uri() . '/gravity-forms.css', array(), '1.0.0');
    }
}

add_action('wp_enqueue_scripts', 'enqueue_custom_gravity_forms_scripts');
function enqueue_custom_gravity_forms_scripts() {
    if (class_exists('GFCommon')) {
        wp_enqueue_script('gravity-forms-custom-scripts', get_stylesheet_directory_uri() . '/gravity-forms.js', array('jquery'), '1.0.0', true);
        wp_localize_script('gravity-forms-custom-scripts', 'ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));

        // Get global settings for packages
        $package_settings = get_package_settings();

        // Localize the script with package settings
        wp_localize_script('gravity-forms-custom-scripts', 'packageSettings', $package_settings);
    
        // Localize the script with the user's locale
        wp_localize_script('gravity-forms-custom-scripts', 'localeData', array(
            'locale' => get_locale()
        ));
    }
}

// --- Gravity Forms Currency Formatting ---
add_filter('gform_currencies', 'custom_currency_formatting');
function custom_currency_formatting($currencies) {
    // Set the currency formatting for both EUR and USD
    $currencies['EUR'] = array(
        'name'               => __('Euro', 'gravityforms'),
        'symbol_left'        => '€',
        'symbol_right'       => '',
        'symbol_padding'     => '',
        'thousand_separator' => ',',
        'decimal_separator'  => '.',
        'decimals'           => 2,
        'code'               => 'EUR'
    );
    
    $currencies['USD'] = array(
        'name'               => __('U.S. Dollar', 'gravityforms'),
        'symbol_left'        => '$',
        'symbol_right'       => '',
        'symbol_padding'     => '',
        'thousand_separator' => ',',
        'decimal_separator'  => '.',
        'decimals'           => 2,
        'code'               => 'USD'
    );

    return $currencies;
}

// --- Shared Currency Helper Functions ---
function bst_get_form_currency_from_sources($form_id, $form = null) {
    $tour_currency = null;
    
    $valid_currencies = array('USD', 'CAD', 'AUD', 'EUR', 'GBP', 'Other');
    
    // Simple approach: Check the most common sources in order of priority
    
    // 1. Form submission data (page navigation) - check form-specific hidden currency fields
    // Both forms now use field 223 for tour_currency
    if (($form_id == 9 || $form_id == 10) && !empty($_POST['input_223'])) {
        $tour_currency = sanitize_text_field($_POST['input_223']);
    }
    // 2. Initial form load from single-tour.js
    elseif (isset($_POST['tour_currency']) && !empty($_POST['tour_currency'])) {
        $tour_currency = sanitize_text_field($_POST['tour_currency']);
    }
    // 3. Reservation/booking context - handle encoded bid parameter
    elseif (!empty($_GET['bid'])) {
        $encoded_bid = sanitize_text_field($_GET['bid']);
        $booking_id = bst_decode_booking_id($encoded_bid);
        if ($booking_id) {
            global $wpdb;
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT tour_currency FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
                $booking_id
            ));
            if ($booking && !empty($booking->tour_currency)) {
                $tour_currency = $booking->tour_currency;
            }
        }
    }
    // 4. Single tour page context
    elseif (is_singular('tour')) {
        global $post;
        if ($post && function_exists('get_field')) {
            $currency_field = get_field('currency', $post->ID);
            if (!empty($currency_field)) {
                $tour_currency = is_string($currency_field) ? $currency_field : $currency_field['currency'];
            }
        }
    }
    
    // Validate currency before returning
    if ($tour_currency && !in_array($tour_currency, $valid_currencies)) {
        static $error_count = 0;
        if ($error_count < 3) {
            error_log('BST Currency Error - Invalid currency from source: ' . json_encode(array(
                'invalid_currency' => $tour_currency,
                'form_id' => $form_id,
                'is_admin' => is_admin(),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            )));
            $error_count++;
        }
        return null; // Return null for invalid currency
    }
    
    return $tour_currency;
}

function bst_set_form_currency($form, $form_id) {
    $tour_currency = bst_get_form_currency_from_sources($form_id, $form);
    
    if ($tour_currency) {
        // Set the form currency (this affects all currency fields in the form)
        $form['currency'] = $tour_currency;
        
        // Manually populate the form-specific hidden currency fields when not coming from POST data 
        // (i.e., for reservations where currency comes from database, not web bookings)
        if (empty($_POST['tour_currency'])) {
            foreach ($form['fields'] as &$field) {
                // Both forms now use field 223 for tour_currency
                if ($field->id == 223) {
                    $field->defaultValue = $tour_currency;
                    break;
                }
            }
        }
    }
    
    return $form;
}

function bst_add_shared_currency_javascript($form, $form_id = null) {
    $form_id = $form_id ?: $form['id'];
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Currency configurations (matches PHP currency formatting)
        var currencyConfigs = {
            'USD': {
                "name": "U.S. Dollar",
                "symbol_left": "$",
                "symbol_right": "",
                "symbol_padding": "",
                "thousand_separator": ",",
                "decimal_separator": ".",
                "decimals": 2
            },
            'EUR': {
                "name": "Euro",
                "symbol_left": "€",
                "symbol_right": "",
                "symbol_padding": "",
                "thousand_separator": ",",
                "decimal_separator": ".",
                "decimals": 2
            }
        };
        
        // Function to update currency globally
        window.bstUpdateCurrency = function(currency) {
            console.log('BST: Updating currency to ' + currency);
            
            // Only apply currency updates for non-EUR currencies (EUR is default and works without updates)
            if (currency !== 'EUR' && currencyConfigs[currency] && typeof window.gf_global !== 'undefined') {
                // Store original if not already stored
                if (!window.gf_global.gf_currency_config_original) {
                    window.gf_global.gf_currency_config_original = JSON.parse(JSON.stringify(window.gf_global.gf_currency_config));
                }
                
                // Update the global currency configuration
                window.gf_global.gf_currency_config = currencyConfigs[currency];
                
                <?php if ($form_id == 9): ?>
                // Form 9: Let Gravity Forms handle currency formatting based on form currency setting
                // Removed field-specific formatting to prevent override of form currency
                <?php elseif ($form_id == 10): ?>
                // Form 10: Let Gravity Forms handle currency formatting based on form currency setting  
                // Removed field-specific formatting to prevent override of form currency
                <?php endif; ?>
                
                // Trigger a global event to notify other scripts
                $(document).trigger('bst_currency_updated', [currency]);
            } else if (currency === 'EUR') {
                console.log('BST: EUR currency detected, using default configuration');
                // For EUR, trigger event but don't modify global config since EUR is default
                $(document).trigger('bst_currency_updated', [currency]);
            }
        };
    });
    </script>
    <?php
    
    return $form;
}

// Shared currency override function
function bst_override_form_currency($currency, $form_id) {
    // Validate currency - force to EUR if invalid (prevents crashes from data corruption)
    $valid_currencies = array('USD', 'CAD', 'AUD', 'EUR', 'GBP', 'Other');
    if (!in_array($currency, $valid_currencies)) {
        return 'EUR';
    }
    
    // Don't override in admin or on non-relevant pages - just return validated currency
    if (is_admin() || (!is_singular('tour') && empty($_POST) && empty($_GET['bid']))) {
        return $currency;
    }
    
    // Get currency from sources (tour page, form submission, or booking)
    $tour_currency = bst_get_form_currency_from_sources($form_id);
    
    // Validate tour currency
    if ($tour_currency && !in_array($tour_currency, $valid_currencies)) {
        $tour_currency = null;
    }
    
    // Only override if we have a valid non-EUR currency that differs
    if ($tour_currency && $tour_currency !== 'EUR' && $tour_currency !== $currency) {
        return $tour_currency;
    }
    
    return $currency;
}

// #endregion

// --- Helper function to calculate payment details from GF entry ---
function bst_calculate_gf_payment_details($entry, $existing_total_paid = 0, $context = 'create') {
    $payment_method = rgar($entry, '118');
    $wire_received_date = rgar($entry, '209');
    $wire_received_amount = rgar($entry, '210');
    
    $payment_amount = 0;
    $payment_date = null;
    $booking_status = null;
    
    $deposit_payment_status = null;

    if ($payment_method == 'Bank Wire' && (empty($wire_received_date) || empty($wire_received_amount))) {
        $booking_status         = 'Pending';
        // Option B: include expected wire amount (field 230, tour currency) in totals.
        $payment_amount         = floatval( rgar( $entry, '230' ) );
        $deposit_payment_status = 'Pending';
    } else {
        // Check entry payment status for async payment methods (SEPA, etc.)
        $entry_payment_status = isset($entry['payment_status']) ? $entry['payment_status'] : '';
        
        if ($entry_payment_status === 'Processing') {
            $booking_status = 'Processing';  // SEPA Direct Debit, etc. awaiting confirmation
            $deposit_payment_status = 'Processing';
        } elseif ($entry_payment_status === 'Failed') {
            $booking_status = 'Payment Failed';  // Payment was declined/failed
            $deposit_payment_status = 'Failed';
        } else {
            // Determine initial status based on context
            $booking_status = ($context === 'update') ? 'Booked' : 'Booked';
        }
        
        // Calculate payment amount based on method
        if ($payment_method == 'Bank Wire') {
            $payment_amount = floatval(preg_replace('/[^0-9.]/', '', $wire_received_amount));
            $payment_date = $wire_received_date;
            if ( $payment_amount > 0 ) {
                $deposit_payment_status = 'Paid';
            }
        } else {
            // Try different field sources for non-wire payments
            $payment_amount = floatval(preg_replace('/[^0-9.]/', '', rgar($entry, '177')));
            if (empty($payment_amount)) {
                $payment_amount = floatval(rgar($entry, 'payment_amount'));
            }
            $payment_date = rgar($entry, 'payment_date') ? rgar($entry, 'payment_date') : $entry['date_created'];
            if ( $payment_amount > 0 && null === $deposit_payment_status ) {
                $deposit_payment_status = 'Paid';
            }
        }
    }
    
    // Calculate totals
    $total_paid = $existing_total_paid + $payment_amount;

    if ( null === $deposit_payment_status && $payment_amount > 0 && null !== $payment_method && '' !== $payment_method ) {
        $deposit_payment_status = 'Paid';
    }

    $booking_status = function_exists( 'bst_merge_booking_status_with_payment_line_statuses' )
        ? bst_merge_booking_status_with_payment_line_statuses( $booking_status, $deposit_payment_status, null, null, null )
        : $booking_status;
    
    return array(
        'payment_method' => $payment_method,
        'payment_amount' => $payment_amount,
        'payment_date' => $payment_date,
        'total_paid' => $total_paid,
        'booking_status' => $booking_status,
        'deposit_payment_status' => $deposit_payment_status,
    );
}

// --- Helper function for admin payment updates with additional fallbacks ---
function bst_calculate_gf_admin_payment_details($entry, $existing_total_paid = 0, $existing_status = 'Booked', $net_tour_price = 0) {
    $payment_method = rgar($entry, '118');
    $wire_received_date = rgar($entry, '209');
    $wire_received_amount = floatval(rgar($entry, '210'));
    
    $payment_amount = 0;
    $payment_date = null;
    $booking_status = $existing_status; // Keep existing status by default
    
    if ($payment_method == 'Bank Wire') {
        // For bank wire, only process payment if both date and amount are provided
        if (!empty($wire_received_date) && !empty($wire_received_amount)) {
            $payment_amount = floatval($wire_received_amount);
            $payment_date = $wire_received_date;
            // For admin updates on GF9, only change from Pending to Booked
            if ($existing_status === 'Pending') {
                $booking_status = 'Booked';
            }
        }
    } else {
        // For non-bank wire payments, check multiple possible field sources
        $payment_amount = floatval(rgar($entry, 'payment_amount'));
        if (empty($payment_amount)) {
            // Try field 177 which is used in create function  
            $payment_amount = floatval(rgar($entry, '177'));
        }
        if (empty($payment_amount)) {
            // Try getting from payment_status meta or other payment fields
            $payment_amount = floatval(rgar($entry, 'transaction_amount'));
        }
        $payment_date = rgar($entry, 'payment_date');
        if (empty($payment_date)) {
            $payment_date = rgar($entry, 'date_created');
        }
        
        // For non-bank wire payments with payment info, only change from Pending to Booked
        if (!empty($payment_amount) && $existing_status === 'Pending') {
            $booking_status = 'Booked';
        }
    }
    
    // Calculate new totals (only if we have a payment amount)
    $total_paid = $existing_total_paid;
    if (!empty($payment_amount)) {
        $total_paid += $payment_amount;
    }
    
    return array(
        'payment_method' => $payment_method,
        'payment_amount' => $payment_amount,
        'payment_date' => $payment_date,
        'total_paid' => $total_paid,
        'booking_status' => $booking_status
    );
}

// --- Consolidated helper function for balance_due calculation ---
function bst_calculate_balance_due($net_tour_price, $total_paid, $payment_discount_amount, $additional_charge = 0) {
    return floatval($net_tour_price) + floatval($additional_charge) - floatval($total_paid) - floatval($payment_discount_amount);
}

/**
 * GF10: balance due from entry + DB booking (same inputs as finalization).
 * Used when payment method is Credit Card: gform_after_submission normally defers to
 * gform_post_payment_completed, which does not run when there is no charge ($0 balance).
 *
 * @param array $entry Gravity Forms entry.
 * @return float|null Balance due, or null if booking cannot be resolved.
 */
function bst_gf10_estimate_balance_due_from_entry( $entry ) {
    $tour_booking_id = rgar( $entry, '261' );
    if ( ! $tour_booking_id || ! is_numeric( $tour_booking_id ) ) {
        return null;
    }
    global $wpdb;
    $tour_booking = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
            intval( $tour_booking_id )
        )
    );
    if ( ! $tour_booking ) {
        return null;
    }
    $payment_method         = rgar( $entry, '118' );
    $existing_total_paid    = floatval( rgar( $entry, '193' ) );
    if ( 0.0 === $existing_total_paid ) {
        $existing_total_paid = floatval( $tour_booking->total_paid ?? 0 );
    }
    $net_tour_price = floatval( rgar( $entry, '192' ) );
    if ( 0.0 === $net_tour_price ) {
        $net_tour_price = floatval( $tour_booking->net_tour_price ?? 0 );
    }
    $additional_charge = floatval( rgar( $entry, '285' ) );
    if ( 0.0 === $additional_charge ) {
        $additional_charge = floatval( $tour_booking->additional_charge ?? 0 );
    }
    $balance_payment_discount = ( 'Bank Wire' === $payment_method ) ? floatval( rgar( $entry, '278' ) ) : 0;
    $deposit_discount         = floatval( $tour_booking->deposit_payment_discount ?? 0 );
    $additional_discount      = floatval( $tour_booking->additional_payment_discount ?? 0 );
    $payment_discount_amount  = $deposit_discount + $balance_payment_discount + $additional_discount;

    return bst_calculate_balance_due( $net_tour_price, $existing_total_paid, $payment_discount_amount, $additional_charge );
}

// --- Helper function for GF10 payment calculations with finalization logic ---
function bst_calculate_gf10_payment_details($entry, $existing_total_paid = 0, $net_tour_price = 0, $additional_charge = 0, $context = 'submission', $bank_wire_discount = 0, $existing_payment_discount = 0) {
    // Check if balance is already paid in full (or overpaid)
    // Note: existing_payment_discount already includes all previous discounts, and bank_wire_discount is the current payment discount
    // So we add them together to get total payment discount
    $total_payment_discount = $existing_payment_discount + $bank_wire_discount;
    $balance_due = bst_calculate_balance_due($net_tour_price, $existing_total_paid, $total_payment_discount, $additional_charge);
    
    // If balance is already paid (<=0), skip payment processing entirely
    if ($balance_due <= 0) {
        return array(
            'payment_method' => null,  // No payment method needed
            'payment_amount' => 0,
            'payment_date' => null,
            'total_paid' => $existing_total_paid,
            'balance_due' => $balance_due,
            'booking_status' => 'Finalized',  // Already paid, set to Finalized
            'has_payment_info' => false,
            'balance_payment_status' => null,
        );
    }
    
    $payment_method = rgar($entry, '118');
    $wire_received_date = rgar($entry, '213');  // GF10 wire fields
    $wire_received_amount = rgar($entry, '214');
    
    $payment_amount = 0;
    $payment_date = null;
    $has_payment_info = false;
    $booking_status = null;
    $balance_payment_status = null;
    
    if ($payment_method == 'Bank Wire') {
        if (!empty($wire_received_date) && !empty($wire_received_amount)) {
            $has_payment_info = true;
            $payment_amount = floatval($wire_received_amount);
            $payment_date = $wire_received_date;
            $balance_payment_status = 'Paid';
        } else {
            // Bank Wire selected but no payment received yet - set to Pending; Option B: expected amount field 279
            $booking_status = 'Pending';
            $balance_payment_status = 'Pending';
            $payment_amount = floatval( rgar( $entry, '279' ) );
        }
    } else {
        // Check entry payment status for async payment methods (SEPA, etc.)
        $entry_payment_status = isset($entry['payment_status']) ? $entry['payment_status'] : '';
        
        // For non-bank wire payments (Credit Card, etc.), get payment info from specific fields
        $payment_amount = floatval(rgar($entry, '208')); // Credit card payment amount (number field)
        
        // If field 208 is empty, try field 191 (Total field)
        if (empty($payment_amount)) {
            $payment_amount = floatval(rgar($entry, '191')); // Total field
        }
        
        // For payment date, try standard field first, then fallback to entry creation date
        $payment_date = rgar($entry, 'payment_date');
        if (empty($payment_date)) {
            $payment_date = $entry['date_created'];
        }
        
        if (!empty($payment_amount)) {
            $has_payment_info = true;
        }
        
        // Set status based on entry payment status for async payments
        if ($entry_payment_status === 'Processing') {
            $booking_status = 'Processing';  // SEPA Direct Debit, etc. awaiting confirmation
            $balance_payment_status = 'Processing';
        } elseif ($entry_payment_status === 'Failed') {
            $booking_status = 'Payment Failed';  // Payment was declined/failed
            $balance_payment_status = 'Failed';
        } elseif ( ! empty( $payment_amount ) ) {
            $balance_payment_status = 'Paid';
        }
    }
    
    // Calculate totals
    $total_paid = $existing_total_paid;
    if ($has_payment_info) {
        $total_paid += $payment_amount;
    } elseif ( 'Bank Wire' === $payment_method && 'Pending' === $booking_status && $payment_amount > 0 ) {
        $total_paid += $payment_amount;
    }
    
    $total_payment_discount = $existing_payment_discount + $bank_wire_discount;
    $balance_due = bst_calculate_balance_due($net_tour_price, $total_paid, $total_payment_discount, $additional_charge);
    
    // Determine final status - GF10 pays balance, so always Finalized when payment completes
    // If status already set by payment status check (Processing/Failed/Pending), keep it
    // Otherwise if we have payment info, it's Finalized (balance payment completed)
    if (!$booking_status && $has_payment_info) {
        $booking_status = 'Finalized';
    }

    if ( null === $balance_payment_status && $payment_amount > 0 && $payment_method ) {
        $balance_payment_status = 'Paid';
    }
    
    return array(
        'payment_method' => $payment_method,
        'payment_amount' => $payment_amount,
        'payment_date' => $payment_date,
        'total_paid' => $total_paid,
        'balance_due' => $balance_due,
        'booking_status' => $booking_status,
        'has_payment_info' => $has_payment_info,
        'balance_payment_status' => $balance_payment_status,
    );
}

// #region Gravity Forms ID 9 code

// not sure all of these filters needed
add_filter('gform_pre_render_9', 'bst_gf9_prepopulate_booking_form', 5);
// combined means it is handling the reservation as well

// Currency configuration is now handled by custom_currency_formatting function above

// Force currency setting for form 9 specifically using shared helper (run after prepopulation)
add_filter('gform_form_post_get_meta_9', 'bst_force_form_currency_9');
add_filter('gform_pre_render_9', 'bst_force_form_currency_9', 15);
// Removed gform_pre_validation_9 and gform_pre_submission_filter_9 - could impact PayPal

function bst_force_form_currency_9($form) {
    return bst_set_form_currency($form, 9);
}

// Currency override for form 9 using shared helper
// Global currency override for Forms 9 and 10 (both use field 223 for tour_currency)
add_filter('gform_currency', 'bst_override_forms_currency', 10, 1);
function bst_override_forms_currency($currency) {
    // Check field 223 (both forms use this for currency)
    if (!empty($_POST['input_223'])) {
        $value = sanitize_text_field($_POST['input_223']);
        if (in_array($value, array('EUR', 'USD', 'GBP'))) {
            return $value;
        }
    }
    
    // Fallback: Check tour_currency parameter from JavaScript (initial form load)
    if (!empty($_POST['tour_currency'])) {
        $value = sanitize_text_field($_POST['tour_currency']);
        if (in_array($value, array('EUR', 'USD', 'GBP'))) {
            return $value;
        }
    }
    
    return $currency;
}

// Add JavaScript to handle dynamic currency changes using shared function (run after currency detection)
add_filter('gform_pre_render_9', 'bst_add_shared_currency_javascript', 20);

// Create booking early (before confirmation) so we have the booking ID for redirect
function bst_gf9_create_booking_early($form) {
    // Only create booking for new submissions, not updates
    $is_update = false;
    if (isset($_POST['input_219']) && is_numeric($_POST['input_219'])) {
        $is_update = true; // This is a reservation update
    }
    
    // Only proceed for new bookings (not updates)
    if (!$is_update) {
        // Create a temporary entry object from POST data
        $entry = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'input_') === 0) {
                $field_id = str_replace('input_', '', $key);
                $entry[$field_id] = $value;
            }
        }
        
        // Note: The actual entry ID won't be available yet, so we'll store it as 0
        // and update it in gform_after_submission
        $entry['id'] = 0; // Will be updated after submission
        $entry['form_id'] = $form['id'];
        $entry['date_created'] = current_time('mysql');
        
        // Create the booking (it will be updated with the real entry ID later)
        bst_gf9_create_booking($entry, $form);
    }
}

// Shortcode functions have been moved to booking-display.php

// Register only the correct actions for booking creation and update
// Use gform_after_submission for Bank Wire (no payment processing needed)
add_action('gform_after_submission_9', 'bst_gf9_handle_submission', 10, 2);
// Use gform_post_payment_completed for Credit Card (fires after Stripe processes payment)
add_action('gform_post_payment_completed', 'bst_gf9_handle_payment_completed', 10, 2);
add_action('gform_after_update_entry_9', 'bst_gf9_handle_admin_update', 10, 2);

function bst_live_tour_title_by_id( $tour_id ) {
    $tour_id = (int) $tour_id;
    if ( $tour_id <= 0 ) {
        return '';
    }
    $p = get_post( $tour_id );
    return ( $p && 'tour' === $p->post_type ) ? (string) $p->post_title : '';
}

function bst_live_tour_date_text_by_id( $tour_date_id ) {
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

function bst_live_package_name_by_id( $package_id ) {
    $package_id = (int) $package_id;
    if ( $package_id <= 0 ) {
        return '';
    }
    return (string) get_option( 'bst_package_' . $package_id . '_name', '' );
}

function bst_live_booking_tour_parts( $tour_id, $tour_date_id, $tour_package_id ) {
    return array(
        'tour_text'         => bst_live_tour_title_by_id( $tour_id ),
        'tour_date_text'    => bst_live_tour_date_text_by_id( $tour_date_id ),
        'tour_package_text' => bst_live_package_name_by_id( $tour_package_id ),
    );
}

/**
 * Canonical GF field IDs for tour entities on form 9 (see {@see bst_gf9_create_booking()}):
 * tour_id 149, tour_date_id 150 (may contain pipe suffix), tour_package_id 151.
 *
 * @param array $entry GF entry row.
 * @return array{tour_id:int,tour_date_id_raw:string,tour_date_id_int:int,tour_package_id:int}
 */
function bst_gf9_entry_normalized_tour_ids( $entry ) {
    $tour_id          = (int) rgar( $entry, '149' );
    $td_raw           = (string) rgar( $entry, '150' );
    $td_parts         = explode( '|', $td_raw );
    $tour_date_id_int = isset( $td_parts[0] ) ? (int) trim( $td_parts[0] ) : 0;
    $tour_package_id  = (int) rgar( $entry, '151' );
    return array(
        'tour_id'          => $tour_id,
        'tour_date_id_raw' => $td_raw,
        'tour_date_id_int' => $tour_date_id_int,
        'tour_package_id'  => $tour_package_id,
    );
}

/**
 * Live CPT / option labels derived only from GF9 ID fields — not from removed snapshot text columns.
 *
 * @param array $entry GF9 entry.
 * @return array{tour_text:string,tour_date_text:string,tour_package_text:string}
 */
function bst_gf9_entry_live_tour_parts( $entry ) {
    $ids = bst_gf9_entry_normalized_tour_ids( $entry );
    return bst_live_booking_tour_parts( $ids['tour_id'], $ids['tour_date_id_int'], $ids['tour_package_id'] );
}

function bst_gf9_prepopulate_booking_form($form) {
    // Remove all error fields at the start
    bst_gf_remove_all_errors($form);
    $tour_booking_id = null;
    $bookingtype = 'Regular';

    // if it is a reservation booking, set the booking type and ID
    if (isset($_POST['bid']) && !empty($_POST['bid'])) {
        $encoded_bid = sanitize_text_field($_POST['bid']);
        $tour_booking_id = bst_decode_booking_id($encoded_bid);
        $bookingtype = 'Reservation';
    } elseif (isset($_GET['bid']) && !empty($_GET['bid'])) {
        $encoded_bid = sanitize_text_field($_GET['bid']);
        $tour_booking_id = bst_decode_booking_id($encoded_bid);
        $bookingtype = 'Reservation';
    }

    if ($tour_booking_id) {
        global $wpdb;
        $tour_booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
            $tour_booking_id
        ));
        
        // Check if booking exists and has the correct status for the booking type
        $valid_booking = false;
        if ($tour_booking) {
            if ($bookingtype === 'Reservation' && ($tour_booking->booking_status === 'Reserved' || $tour_booking->booking_status === 'Waiting List')) {
                $valid_booking = true;
            }
        }
        
        if ($valid_booking) {
            // If already booked (booking_entry_id present), show error and do not prepopulate
            if (!empty($tour_booking->booking_entry_id)) {
                bst_gf_inject_error($form, 'This ' . strtolower($bookingtype) . ' has already been booked.');
                return $form;
            }
            
            // Set the form currency based on the booking's tour currency
            if (!empty($tour_booking->tour_currency)) {
                $detected_currency = $tour_booking->tour_currency;
            } else {
                // Fallback: get currency from the tour's ACF field
                $tour_currency_field = get_field('currency', $tour_booking->tour_id);
                $valid_currencies = array('USD', 'CAD', 'AUD', 'EUR', 'GBP', 'Other');
                $detected_currency = (!empty($tour_currency_field) && in_array($tour_currency_field, $valid_currencies)) ? $tour_currency_field : 'EUR';
            }
            
            // Store the detected currency in global variable for the filter
            $GLOBALS['bst_detected_currency_9'] = $detected_currency;
            
            // --- Prepopulate fields for Reserved/Waitlist booking ---
            $net_tour_price = floatval($tour_booking->net_tour_price);
            $deposit_amount = bst_calculate_deposit($tour_booking->tour_id, $net_tour_price, $tour_booking->package_people);
            $deposit = number_format($deposit_amount, 2, '.', '');
            $balance = number_format($net_tour_price - $deposit_amount, 2, '.', '');
            $live_parts = bst_live_booking_tour_parts( $tour_booking->tour_id, $tour_booking->tour_date_id, $tour_booking->tour_package_id );
            $product_name = $live_parts['tour_text'] . ' Tour, ' . $live_parts['tour_date_text'] . ': ' . $live_parts['tour_package_text'] . ' Package';
            $res_v1_id = (int) ( $tour_booking->vehicle1_id ?? 0 );
            $res_v2_id = (int) ( $tour_booking->vehicle2_id ?? 0 );
            $v1_label  = bst_vehicle_label_for_gf_from_id( $res_v1_id );
            if ( '' !== $v1_label ) {
                $product_name .= ' (' . $v1_label;
                $v2_label = bst_vehicle_label_for_gf_from_id( $res_v2_id );
                if ( '' !== $v2_label ) {
                    $product_name .= '/' . $v2_label;
                }
                $product_name .= ')';
            }
            // Coupon code/amount from entry if available
            $coupon_code = $tour_booking->coupon_code;
            $coupon_amount = $tour_booking->coupon_amount;
            $bank_wire_discount = floatval(rgar($entry, '229'));
            // --- Use package values from the booking record for reservations/finalization ---
            $package_people = $tour_booking->package_people;
            $package_rooms = $tour_booking->package_rooms;
            $package_vehicles = $tour_booking->package_vehicles;
            $vehicle_choices = ( $res_v2_id > 0 ) ? '2' : ( ( $res_v1_id > 0 ) ? '1' : '0' );
            
            // Map input names to booking fields (similar to GF10 approach)
            $field_mapping = array(
                'first_name'     => 'guest1_first_name',
                'last_name'      => 'guest1_last_name',
                'email'          => 'guest1_email',
                'phone'          => 'guest1_phone',
                'first_name2'    => 'guest2_first_name',
                'last_name2'     => 'guest2_last_name',
                'tour_id'        => 'tour_id',
                'tourtext'       => 'tour_text',
                'tourdate_id'    => 'tour_date_id',
                'tourdatestext'  => 'tour_date_text',
                'package_id'     => 'tour_package_id',
                'packagetext'    => 'tour_package_text',
                'vehicle1text'   => 'vehicle1',
                'vehicle2text'   => 'vehicle2',
                'vehicle1_id'    => 'vehicle1_id',
                'vehicle2_id'    => 'vehicle2_id',
                'package_people' => 'package_people',
                'package_rooms'  => 'package_rooms',
                'package_vehicles'=> 'package_vehicles',
                'vehicle_choices'=> 'vehicle_choices',
                'tourprice'      => 'tour_price',
                'deposit'        => 'deposit_calculated',
                'balance'        => 'balance_calculated',
                'coupon_code'    => 'coupon_code',
                'coupon_amount'  => 'coupon_amount',
                'commission_percent' => 'booking_commission_percent',
                'source'         => 'booking_source'
            );
            
            // Build source data array from the booking object (similar to GF10)
            $source_data = array();
            foreach ($field_mapping as $input_name => $db_field) {
                if (isset($tour_booking->$db_field)) {
                    $source_data[$db_field] = $tour_booking->$db_field;
                }
            }
            // Hidden GF fields 236/237 (vehicle CPT ids); always set so prepopulate works when DB values are null.
            $source_data['vehicle1_id'] = (string) (int) ( $tour_booking->vehicle1_id ?? 0 );
            $source_data['vehicle2_id'] = (string) (int) ( $tour_booking->vehicle2_id ?? 0 );

            $source_data['vehicle1'] = $v1_label;
            $source_data['vehicle2'] = bst_vehicle_label_for_gf_from_id( $res_v2_id );
            
            // Add calculated fields
            $source_data['deposit_calculated'] = $deposit;
            $source_data['balance_calculated'] = $balance;
            $source_data['package_people'] = $package_people;
            $source_data['package_rooms'] = $package_rooms;
            $source_data['package_vehicles'] = $package_vehicles;
            $source_data['vehicle_choices'] = $vehicle_choices;
            $source_data['booking_source'] = $bookingtype;
            
            // Add hidden field for booking ID so it's available during form submission
            $source_data['booking_update_id'] = $tour_booking_id;
            $field_mapping['booking_update_id'] = 'booking_update_id';
            $source_data['tour_text'] = $live_parts['tour_text'];
            $source_data['tour_date_text'] = $live_parts['tour_date_text'];
            $source_data['tour_package_text'] = $live_parts['tour_package_text'];
            
            // Set product field label separately - only on initial load
            if (!isset($_POST['gform_submit']) && !isset($_POST['gform_target_page_number'])) {
                foreach ($form['fields'] as &$field) {
                    if ($field->type == 'product' && $field->id == 176) {
                        $field->label = $product_name;
                    }
                }
            }
            
            // Use the utility function to set defaults (same as GF10)
            bst_set_gf_field_defaults($form, $field_mapping, $source_data);
        } else {
            // Show specific error based on booking type and status
            if (!$tour_booking) {
                bst_gf_inject_error($form, 'Booking record not found.');
            } elseif ($bookingtype === 'Reservation' && $tour_booking->booking_status !== 'Reserved' && $tour_booking->booking_status !== 'Waiting List') {
                bst_gf_inject_error($form, 'This booking is not in Reserved or Waiting List status. Current status: ' . $tour_booking->booking_status);
            } else {
                bst_gf_inject_error($form, 'This is not a valid ' . strtolower($bookingtype) . ' booking.');
            }
        }
        return $form;
    } else {
        // No id: then this means we are creating a new booking and will use the form generated by single-tour.js
        $tour_currency = null;
        
        if ($_POST && isset($_POST['tour_id'])) {
            // Get the tour's currency and set it for the entire form
            $tour_id = intval($_POST['tour_id']);
            $tour_currency = 'EUR'; // Default
            if (isset($_POST['tour_currency'])) {
                $tour_currency = sanitize_text_field($_POST['tour_currency']);
            } else {
                // Fallback to database lookup
                $tour_currency_field = get_field('currency', $tour_id);
                $valid_currencies = array('USD', 'CAD', 'AUD', 'EUR', 'GBP', 'Other');
                if (!empty($tour_currency_field) && in_array($tour_currency_field, $valid_currencies)) {
                    $tour_currency = $tour_currency_field;
                } else {
                    $tour_currency = 'EUR'; // Default to EUR if invalid or empty
                }
            }
            
            // Set the form's currency - this controls how ALL currency fields are displayed
            $form['currency'] = $tour_currency;
            
            // Store currency in global for JavaScript override to use
            if (!isset($GLOBALS['bst_form_currency'])) {
                $GLOBALS['bst_form_currency'] = array();
            }
            $GLOBALS['bst_form_currency'][9] = $tour_currency;
            
            // Also set the detected currency global to match (this was causing the override conflict)
            $GLOBALS['bst_detected_currency_9'] = $tour_currency;
            
            // Handle field population - use the generic approach that was working
            foreach ($form['fields'] as &$field) {
                // Only modify product field on initial load, not during form submissions/PayPal processing
                if ($field->type == 'product' && $field->id == 176) {
                    if (!isset($_POST['gform_submit']) && !isset($_POST['gform_target_page_number'])) {
                        // Set the label for the product field
                        if (isset($_POST['product_name'])) {
                            $field->label = sanitize_text_field($_POST['product_name']);
                        }
                        
                        // Set the product price if provided
                        if (isset($_POST['tourprice'])) {
                            $price = floatval($_POST['tourprice']);
                            $field->basePrice = number_format($price, 2, '.', '');
                        }
                    }
                } elseif ($field->inputName == 'tour_currency' && $tour_currency) {
                    // Always populate tour_currency field with detected currency
                    $field->defaultValue = $tour_currency;
                } elseif ($field->id == 223 && $tour_currency) {
                    // Always populate field 223 (hidden currency field) with detected currency
                    $field->defaultValue = $tour_currency;
                } elseif (isset($_POST[$field->inputName]) && !in_array($field->inputName, array('booking_update_id'))) {
                    if ( 'vehicle1text' === $field->inputName ) {
                        $vid = isset( $_POST['vehicle1_id'] ) ? absint( wp_unslash( $_POST['vehicle1_id'] ) ) : 0;
                        $field->defaultValue = bst_vehicle_label_for_gf_from_id( $vid );
                    } elseif ( 'vehicle2text' === $field->inputName ) {
                        $vid = isset( $_POST['vehicle2_id'] ) ? absint( wp_unslash( $_POST['vehicle2_id'] ) ) : 0;
                        $field->defaultValue = bst_vehicle_label_for_gf_from_id( $vid );
                    } else {
                        $field->defaultValue = sanitize_text_field( wp_unslash( $_POST[ $field->inputName ] ) );
                    }
                }
            }
        } else {
            // No POST data - check if we have currency from global variables for page navigation
            if (isset($GLOBALS['bst_form_currency'][9])) {
                $tour_currency = $GLOBALS['bst_form_currency'][9];
                $form['currency'] = $tour_currency;
            }
        }
        
        // Field population for tour_currency is now handled by bst_set_form_currency()
    }
    return $form;
}


// --- Wrapper for Bank Wire payments (gform_after_submission) ---
function bst_gf9_handle_submission($entry, $form) {
    $payment_method = rgar($entry, '118');
    
    // For Credit Card payments, skip here - will be handled by post_payment hook
    if ($payment_method === 'Credit Card') {
        error_log('BST Booking: Credit Card payment detected, will be handled by post_payment hook');
        return;
    }
    
    // Call the common processing logic for Bank Wire and other methods
    bst_gf9_process_booking_logic($entry, $form);
}

// --- Common booking processing logic (called by both Bank Wire and Credit Card handlers) ---
function bst_gf9_process_booking_logic($entry, $form) {
    global $wpdb;

    $payment_method = rgar($entry, '118'); // Field 118 is payment method
    error_log('BST Booking: Processing ' . $payment_method . ' payment');

    // Get booking ID from different sources
    $tour_booking_id = null;
    
    // First, check if this is an update from a reservation/waitlist booking (new approach)
    $booking_update_id = rgar($entry, '219');  // Use field ID 219 instead of inputName
    if (!empty($booking_update_id) && is_numeric($booking_update_id)) {
        $tour_booking_id = intval($booking_update_id);
    }
    
    // Decode bid parameter if present
    if (!$tour_booking_id) {
        if (isset($_POST['bid']) && !empty($_POST['bid'])) {
            $encoded_bid = sanitize_text_field($_POST['bid']);
            $tour_booking_id = bst_decode_booking_id($encoded_bid);
        } elseif (isset($_GET['bid']) && !empty($_GET['bid'])) {
            $encoded_bid = sanitize_text_field($_GET['bid']);
            $tour_booking_id = bst_decode_booking_id($encoded_bid);
        }
    }

    // if there is an ID, we will update (in case of a reservation or waitlist booking)
    if ($tour_booking_id) {
        $tour_booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
            $tour_booking_id
        ));
        if ($tour_booking && ($tour_booking->booking_status === 'Reserved' || $tour_booking->booking_status === 'Waiting List')) {
            // Update ALL fields from the new entry
            $booking_entry_id = $entry['id'];
            $guest1_first_name = rgar($entry, '31.3');
            $guest1_last_name = rgar($entry, '31.6');
            $guest1_phone = rgar($entry, '34');
            $guest1_email = rgar($entry, '33');
            $guest2_first_name = rgar($entry, '215.3');
            $guest2_last_name = rgar($entry, '215.6');
            $tour_ids = bst_gf9_entry_normalized_tour_ids( $entry );
            $tour_id         = (string) $tour_ids['tour_id'];
            $tour_date_id    = $tour_ids['tour_date_id_raw'];
            $tour_package_id = (string) $tour_ids['tour_package_id'];
            list( $vehicle1_id, $vehicle2_id ) = bst_gf9_entry_vehicle_ids_from_fields( $entry );
            bst_gf9_resolve_legacy_vehicle_ids( $entry, (int) $entry['form_id'], (int) $tour_ids['tour_id'], $vehicle1_id, $vehicle2_id );
            $package_people = rgar($entry, '200');
            $package_rooms = rgar($entry, '201');
            $package_vehicles = rgar($entry, '212');
            $vehicle_choices = rgar($entry, '214');
            $tour_price = rgar($entry, '143');
            $net_tour_price = rgar($entry, '160');
            $payment_method = rgar($entry, '118');
            $wire_received_date = rgar($entry, '209');
            $wire_received_amount = rgar($entry, '210');
            // Calculate commission percent and reason
            $how_heard = rgar($entry, '101');
            $source = rgar($entry, '109');
            $referrer = rgar($entry, '110');
            // Ignore gravityapi.com referrers (form preview/testing)
            if (!empty($referrer) && stripos($referrer, 'gravityapi.com') !== false) {
                $referrer = null;
            }
            $guest1_email = rgar($entry, '33');

            // Auto-populate how_heard based on source code or referrer URL
            // Note: the form should hide how_heard when source is present to preserve source-based attribution.
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

            list($booking_commission_percent, $booking_commission_reason, $customer_id) = bst_calculate_commission_percent($guest1_first_name, $guest1_last_name, $guest1_email, $guest1_phone, $guest2_first_name, $guest2_last_name,$how_heard, $source, 'Gravity Forms ID 9 - Update');

            // Calculate payment details using helper function
            $payment_details = bst_calculate_gf_payment_details($entry, 0, 'update');
            $payment_method = $payment_details['payment_method'];
            $payment_amount = $payment_details['payment_amount'];
            $payment_date = $payment_details['payment_date'];
            $total_paid = $payment_details['total_paid'];
            $status = $payment_details['booking_status'];
            
            // Coupon code/amount logic (match create function)
            $coupon_code = rgar($entry, '161');
            $coupon_code = !empty($coupon_code) ? $coupon_code : null;
            $coupon_amount = floatval($tour_price) - floatval($net_tour_price);
            if (empty($coupon_amount) || $coupon_amount < 0) {
                $coupon_amount = 0;
            }
            
            // Get currency from tour's ACF field or default to EUR
            $tour_currency = 'EUR'; // Default
            if ($tour_id) {
                $tour_currency_field = get_field('currency', $tour_id);
                static $acf_error_count = 0;
                if (!empty($tour_currency_field)) {
                    $valid_currencies = array('USD', 'CAD', 'AUD', 'EUR', 'GBP', 'Other');
                    if (in_array($tour_currency_field, $valid_currencies)) {
                        $tour_currency = $tour_currency_field;
                    } else {
                        // Invalid currency from ACF - log once and use default
                        if ($acf_error_count < 3) {
                            error_log('BST Currency Error - GF9 Update Invalid ACF currency: ' . json_encode(array(
                                'tour_id' => $tour_id,
                                'invalid_value' => $tour_currency_field,
                                'defaulting_to' => 'EUR'
                            )));
                            $acf_error_count++;
                        }
                        $tour_currency = 'EUR';
                    }
                }
            }
            
            // Get deposit payment discount from field 229
            $deposit_payment_discount = floatval(rgar($entry, '229'));
            
            // Calculate total payment discount (deposit only, balance/additional come from DB if they exist)
            $existing_balance_discount = floatval($tour_booking->balance_payment_discount ?? 0);
            $existing_additional_discount = floatval($tour_booking->additional_payment_discount ?? 0);
            $payment_discount_amount = $deposit_payment_discount + $existing_balance_discount + $existing_additional_discount;
            
            $balance_due = bst_calculate_balance_due($net_tour_price, $total_paid, $payment_discount_amount);
            
            // Sanitize fields before database update
            $update_data = bst_sanitize_booking_fields(array(
                'guest1_first_name' => $guest1_first_name,
                'guest1_last_name' => $guest1_last_name,
                'guest1_phone' => $guest1_phone,
                'guest1_email' => $guest1_email,
                'guest2_first_name' => $guest2_first_name,
                'guest2_last_name' => $guest2_last_name,
                'coupon_code' => $coupon_code
            ));
            
            // Use centralized update function
            $update_data_full = array(
                'booking_entry_id' => $booking_entry_id,
                'customer_id' => $tour_booking->customer_id, 
                'guest1_first_name' => $update_data['guest1_first_name'],
                'guest1_last_name' => $update_data['guest1_last_name'],
                'guest1_phone' => $update_data['guest1_phone'],
                'guest1_email' => $update_data['guest1_email'],
                'guest2_first_name' => $update_data['guest2_first_name'],
                'guest2_last_name' => $update_data['guest2_last_name'],
                'tour_id' => $tour_id,
                'tour_date_id' => $tour_date_id,
                'tour_package_id' => $tour_package_id,
                'vehicle1_id' => $vehicle1_id,
                'vehicle2_id' => $vehicle2_id,
                'package_people' => $package_people,
                'package_rooms' => $package_rooms,
                'package_vehicles' => $package_vehicles,
                'vehicle_choices' => $vehicle_choices,
                'tour_price' => $tour_price,
                'net_tour_price' => $net_tour_price,
                'coupon_code' => $update_data['coupon_code'],
                'coupon_amount' => $coupon_amount,
                'payment_discount_amount' => $payment_discount_amount,
                'tour_currency' => $tour_currency,
                'balance_due' => $balance_due,
                'deposit_payment_discount' => $deposit_payment_discount,
                'deposit_payment_method' => $payment_method,
                'deposit_payment_amount' => $payment_amount,
                'deposit_payment_date' => $payment_date,
                'deposit_payment_status' => $payment_details['deposit_payment_status'] ?? null,
                'total_paid' => $total_paid,
                'source' => $source,
                'referrer' => $referrer,
                'booking_status' => $status,
                'booking_commission_percent' => $booking_commission_percent,
                'booking_commission_reason' => $booking_commission_reason
            );
            
            $update_result = bst_update_tour_booking($tour_booking_id, $update_data_full);
            if (!$update_result['success']) {
                error_log('BST GF Wire Update Failed: ' . $update_result['error']);
            } else {
                // Process any pending GF9 email that was captured before booking was updated
                bst_process_pending_gf9_email($tour_booking_id, $entry['id']);
                
                // Send notification for booking finalization
                $guest1_first = $update_data_full['guest1_first_name'];
                $guest1_last = $update_data_full['guest1_last_name'];
                $guest2_first = $update_data_full['guest2_first_name'];
                $guest2_last = $update_data_full['guest2_last_name'];
                
                // Format guest name like the tour bookings list
                if (empty($guest2_first)) {
                    $guest_name = $guest1_first . ' ' . $guest1_last;
                } else {
                    if (empty($guest2_last) || $guest1_last === $guest2_last) {
                        $guest_name = $guest1_first . ' & ' . $guest2_first . ' ' . $guest1_last;
                    } else {
                        $guest_name = $guest1_first . ' ' . $guest1_last . ' & ' . $guest2_first . ' ' . $guest2_last;
                    }
                }
                
                // Format tour info from live IDs (no denormalized text fields).
                $live_parts = bst_live_booking_tour_parts( $update_data_full['tour_id'], $update_data_full['tour_date_id'], $update_data_full['tour_package_id'] );
                $tour_info = $live_parts['tour_text'] . ' (' . $live_parts['tour_date_text'] . ') - ' . $live_parts['tour_package_text'];
                $booking_link = admin_url('admin.php?page=view_booking&booking_id=' . $tour_booking_id);
                
                $message = sprintf(
                    '%s - %s finalized %s - <a href="%s">View Booking #%d</a>',
                    date('M j, Y'),
                    esc_html($guest_name),
                    esc_html($tour_info),
                    esc_url($booking_link),
                    $tour_booking_id
                );
                
                BST_Plugin::add_notification(
                    'gf9_booking_finalized_' . $tour_booking_id,
                    $message,
                    'success',
                    true,
                    array('manage_options'),
                    7 // Expires in 7 days
                );
            }
        }
    } else {
        // Otherwise, fallback to normal create logic
        bst_gf9_create_booking($entry, $form);
    }
}

// --- Handle Credit Card payments after Stripe processes them ---
function bst_gf9_handle_payment_completed($entry, $action) {
    global $wpdb;
    
    // Only process form 9
    if ($entry['form_id'] != 9) {
        return;
    }
    
    $payment_status = isset($entry['payment_status']) ? $entry['payment_status'] : '';
    $transaction_id = isset($entry['transaction_id']) ? $entry['transaction_id'] : '';
    $payment_method = rgar($entry, '118');
    $entry_id = $entry['id'];
    
    error_log('BST Booking (post_payment): Entry ID = ' . $entry_id . ', Payment Status = ' . $payment_status);
    error_log('BST Booking (post_payment): Transaction ID = ' . $transaction_id);
    error_log('BST Booking (post_payment): Payment Method = ' . $payment_method);
    
    // Check if booking already exists for this entry
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    $existing_booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $booking_table 
        WHERE booking_entry_id = %d OR finalization_entry_id = %d OR additional_payment_entry_id = %d
        LIMIT 1",
        $entry_id, $entry_id, $entry_id
    ));
    
    // If booking exists and was Processing, update it based on new payment status
    if ($existing_booking && $existing_booking->booking_status === 'Processing') {
        if ($payment_status === 'Paid') {
            // SEPA payment cleared - update to Booked
            error_log('BST Booking (post_payment): Processing → Booked for booking ID ' . $existing_booking->id);
            $wpdb->update(
                $booking_table,
                array('booking_status' => 'Booked'),
                array('id' => $existing_booking->id),
                array('%s'),
                array('%d')
            );
            // Reprocess booking to update payment amounts
            $form = GFAPI::get_form($entry['form_id']);
            bst_gf9_process_booking_logic($entry, $form);
            return;
        } elseif ($payment_status === 'Failed') {
            // SEPA payment failed
            error_log('BST Booking (post_payment): Processing → Payment Failed for booking ID ' . $existing_booking->id);
            $wpdb->update(
                $booking_table,
                array('booking_status' => 'Payment Failed'),
                array('id' => $existing_booking->id),
                array('%s'),
                array('%d')
            );
            return;
        }
    }
    
    // Always create/update booking regardless of payment status
    // The booking will have appropriate status (Pending/Processing/Booked) based on payment_status
    // This ensures we don't lose bookings if payment fails or is processing
    if ($payment_status !== 'Paid') {
        error_log('BST Booking (post_payment): Payment status is not Paid (' . $payment_status . ') - creating/updating booking for manual follow-up');
    }
    
    // Get the form for the second parameter
    $form = GFAPI::get_form($entry['form_id']);
    
    // Call the common processing logic (bypass the Bank Wire wrapper)
    bst_gf9_process_booking_logic($entry, $form);
}

/**
 * Gravity Forms field id for a field whose inputName matches (e.g. vehicle1text on form 9).
 *
 * @param int    $form_id    Form ID.
 * @param string $input_name Field inputName (parameter name).
 * @return string Field id as string, or empty if not found.
 */
function bst_gf_field_id_by_input_name( $form_id, $input_name ) {
    static $cache = array();
    $form_id = (int) $form_id;
    $key     = $form_id . '|' . $input_name;
    if ( isset( $cache[ $key ] ) ) {
        return $cache[ $key ];
    }
    $cache[ $key ] = '';
    if ( ! class_exists( 'GFAPI' ) || $input_name === '' ) {
        return '';
    }
    $form = GFAPI::get_form( $form_id );
    if ( ! $form || empty( $form['fields'] ) ) {
        return '';
    }
    foreach ( $form['fields'] as $field ) {
        if ( ! is_object( $field ) ) {
            continue;
        }
        $iname = isset( $field->inputName ) ? (string) $field->inputName : '';
        if ( $iname === $input_name ) {
            $cache[ $key ] = (string) $field->id;
            return $cache[ $key ];
        }
    }
    return '';
}

/**
 * Read vehicle CPT ids from a GF9 entry.
 * Priority: fields 236/237 (parameter names vehicle1_id / vehicle2_id), then legacy vehicle1id/vehicle2id, then 140/142.
 *
 * @param array $entry GF entry.
 * @return int[] Two ints: vehicle1_id, vehicle2_id.
 */
function bst_gf9_entry_vehicle_ids_from_fields( $entry ) {
    $v1 = absint( rgar( $entry, '236' ) );
    $v2 = absint( rgar( $entry, '237' ) );
    if ( $v1 <= 0 ) {
        $v1 = absint( rgar( $entry, 'vehicle1id' ) );
    }
    if ( $v1 <= 0 ) {
        $v1 = absint( rgar( $entry, '140' ) );
    }
    if ( $v2 <= 0 ) {
        $v2 = absint( rgar( $entry, 'vehicle2id' ) );
    }
    if ( $v2 <= 0 ) {
        $v2 = absint( rgar( $entry, '142' ) );
    }
    return array( $v1, $v2 );
}

/**
 * When GF vehicle id fields are still empty after {@see bst_gf9_entry_vehicle_ids_from_fields()}, try to infer Vehicle CPT ids
 * from legacy free-text columns on very old entries only. Does not use text for display or DB snapshot labels.
 *
 * @param array $entry       GF entry.
 * @param int   $form_id     Form ID.
 * @param int   $tour_id     Tour post ID.
 * @param int   $vehicle1_id In/out.
 * @param int   $vehicle2_id In/out.
 */
function bst_gf9_resolve_legacy_vehicle_ids( $entry, $form_id, $tour_id, &$vehicle1_id, &$vehicle2_id ) {
    $vehicle1_id = absint( $vehicle1_id );
    $vehicle2_id = absint( $vehicle2_id );
    $tour_id     = (int) $tour_id;
    $form_id     = (int) $form_id;

    $legacy1 = '';
    $legacy2 = '';
    $fid1    = bst_gf_field_id_by_input_name( $form_id, 'vehicle1text' );
    $fid2    = bst_gf_field_id_by_input_name( $form_id, 'vehicle2text' );
    if ( $fid1 !== '' ) {
        $legacy1 = trim( (string) rgar( $entry, $fid1 ) );
    }
    if ( $fid2 !== '' ) {
        $legacy2 = trim( (string) rgar( $entry, $fid2 ) );
    }

    if ( ! function_exists( 'bst_vehicle_label_resolution_maps' ) || ! function_exists( 'bst_vehicle_resolve_base_name_to_vehicle_id' ) ) {
        return;
    }
    $maps              = bst_vehicle_label_resolution_maps();
    $tour_linked_cache = array();

    if ( $vehicle1_id <= 0 && $legacy1 !== '' ) {
        $vid = bst_vehicle_resolve_base_name_to_vehicle_id( $legacy1, $maps['norm_to_id'], $maps['vehicles_by_id'] );
        if ( $vid > 0 && function_exists( 'bst_vehicle_id_allowed_for_booking_tour' ) && bst_vehicle_id_allowed_for_booking_tour( $vid, $tour_id, $tour_linked_cache ) ) {
            $vehicle1_id = $vid;
        }
    }
    if ( $vehicle2_id <= 0 && $legacy2 !== '' ) {
        $vid = bst_vehicle_resolve_base_name_to_vehicle_id( $legacy2, $maps['norm_to_id'], $maps['vehicles_by_id'] );
        if ( $vid > 0 && function_exists( 'bst_vehicle_id_allowed_for_booking_tour' ) && bst_vehicle_id_allowed_for_booking_tour( $vid, $tour_id, $tour_linked_cache ) ) {
            $vehicle2_id = $vid;
        }
    }
}

// --- Booking Creation Logic ---
function bst_gf9_create_booking($entry, $form) {
    global $wpdb;
    
    $booking_entry_id = $entry['id'];
    // Only create a new booking if one does not already exist for this entry
    $existing_booking_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}bst_tour_booking WHERE booking_entry_id = %d",
        $booking_entry_id
    ));
    
    if ($existing_booking_id) {
        return;
    }

    // Booking Contact
    $guest1_first_name = rgar($entry, '31.3');
    $guest1_last_name = rgar($entry, '31.6');
    $guest1_phone = rgar($entry, '34');
    $guest1_email = rgar($entry, '33');

    $guest2_first_name = rgar($entry, '215.3');
    $guest2_last_name = rgar($entry, '215.6');
    // Tour selection — canonical ID fields only (137/138/141/225/226 snapshots removed).
    $tour_ids       = bst_gf9_entry_normalized_tour_ids( $entry );
    $tour_id        = (string) $tour_ids['tour_id'];
    $tour_date_id   = $tour_ids['tour_date_id_raw'];
    $tour_package_id = (string) $tour_ids['tour_package_id'];
    list( $vehicle1_id, $vehicle2_id ) = bst_gf9_entry_vehicle_ids_from_fields( $entry );
    bst_gf9_resolve_legacy_vehicle_ids( $entry, (int) $entry['form_id'], (int) $tour_ids['tour_id'], $vehicle1_id, $vehicle2_id );

    // Extension fields — checkbox only; text comes from CPT/tour-date via helpers at display time.
    $tour_extension_added = rgar($entry, '224');
    if (!empty($tour_extension_added)) {
        $tour_extension_added = 1;
    } else {
        $tour_extension_added = 0;
    }
    
    // Strip delimiter and number from these fields
    $participant_sex = preg_replace('/\|.*/', '', rgar($entry, '72'));
    $sharing_preference = preg_replace('/\|.*/', '', rgar($entry, '74'));
    $bed_preference = preg_replace('/\|.*/', '', rgar($entry, '68'));
    
    // --- Use form entry values directly for package settings ---
    $package_people = rgar($entry, '200');
    $package_rooms = rgar($entry, '201');
    $package_vehicles = rgar($entry, '212');
    $vehicle_choices = rgar($entry, '214');
 
    // Tour pricing
    $tour_price = rgar($entry, '143'); // also id 176, but this is easier to get
    $net_tour_price = rgar($entry, '160');
    $coupon_code = rgar($entry, '161');
    
    // Convert prices to floats, removing any currency symbols or commas
    $tour_price_float = floatval(preg_replace('/[^0-9.]/', '', $tour_price));
    $net_tour_price_float = floatval(preg_replace('/[^0-9.]/', '', $net_tour_price));
    $coupon_amount = $tour_price_float - $net_tour_price_float;
    
    // Get currency from tour's ACF field or default to EUR
    $tour_currency = 'EUR'; // Default
    if ($tour_id) {
        $tour_currency_field = get_field('currency', $tour_id);
        static $acf_create_error_count = 0;
        if (!empty($tour_currency_field)) {
            $valid_currencies = array('USD', 'CAD', 'AUD', 'EUR', 'GBP', 'Other');
            if (in_array($tour_currency_field, $valid_currencies)) {
                $tour_currency = $tour_currency_field;
            } else {
                // Invalid currency from ACF - log once and use default
                if ($acf_create_error_count < 3) {
                    error_log('BST Currency Error - GF9 Create Invalid ACF currency: ' . json_encode(array(
                        'tour_id' => $tour_id,
                        'invalid_value' => $tour_currency_field,
                        'defaulting_to' => 'EUR'
                    )));
                    $acf_create_error_count++;
                }
                $tour_currency = 'EUR';
            }
        }
    }

    // Calculate payment details using helper function
    $payment_details = bst_calculate_gf_payment_details($entry, 0, 'create');
    $payment_method = $payment_details['payment_method'];
    $payment_amount = $payment_details['payment_amount'];
    $payment_date = $payment_details['payment_date'];
    $total_paid = $payment_details['total_paid'];
    $booking_status = $payment_details['booking_status'];
    
    // Get deposit payment discount from field 229 - only if Bank Wire
    $deposit_payment_discount = ($payment_method === 'Bank Wire') ? floatval(rgar($entry, '229')) : 0;
    
    // Calculate total payment discount (deposit + balance + additional)
    $payment_discount_amount = $deposit_payment_discount;

    $balance_due = bst_calculate_balance_due($net_tour_price_float, $total_paid, $payment_discount_amount);
    
    // Build bank wire notes if payment method is Bank Wire
    $bank_wire_notes = '';
    if ($payment_method === 'Bank Wire' && $deposit_payment_discount > 0) {
        $date_chosen = date('Y-m-d');
        // Get payment currency from field 232 (Bank Wire Code)
        $payment_currency = rgar($entry, '232'); // Bank Wire Code (USD, CAD, etc.)
        // Convert region code to proper currency code (e.g., "Other" -> "EUR", pass through USD/CAD/GBP/AUD)
        $payment_currency = bst_region_to_currency_code($payment_currency);
        // Get deposit after discount from field 230 (in tour currency) and convert to payment currency
        $deposit_after_discount = floatval(rgar($entry, '230')); // Deposit After Discount in tour currency
        
        // Convert from tour currency to payment currency using exchange rate
        $exchange_rate = bst_get_exchange_rate($tour_currency, $payment_currency);
        if ($exchange_rate && $exchange_rate > 0) {
            $converted_amount = $deposit_after_discount * $exchange_rate;
        } else {
            // Fallback if exchange rate not available
            $converted_amount = $deposit_after_discount;
            error_log("BST: Bank wire notes - exchange rate not found for {$tour_currency} to {$payment_currency}");
        }
        
        $bank_wire_notes = "Customer chose Bank Wire for their deposit payment on {$date_chosen} and was instructed to send " . number_format($converted_amount, 2) . " in {$payment_currency} (which is converted from " . number_format($deposit_after_discount, 2) . " {$tour_currency} at today's exchange rates)";
    }

    // source, etc
    $how_heard = rgar($entry, '101');
    $how_heard_other = rgar($entry, '102') ?: rgar($entry, '104'); // guest, other (excluding motor club) - only one will be filled
    $motor_club = rgar($entry, '103'); // Motor Club
    $source = rgar($entry, '109');
    $referrer = rgar($entry, '110');
    // Ignore gravityapi.com referrers (form preview/testing)
    if (!empty($referrer) && stripos($referrer, 'gravityapi.com') !== false) {
        $referrer = null;
    }
    $comments = rgar($entry, '211');

    // Auto-populate how_heard based on source code or referrer URL
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

    $booking_commission_percent = .02;
    $booking_commission_reason = null;
    $customer_id = null;
    
    // Determine data source - check if this is an imported entry
    $is_imported = rgar($entry, '_bst_imported') == '1';
    $data_source = $is_imported ? 'Gravity Forms ID 9 - Import' : 'Gravity Forms ID 9';
    
    // Use utility function for commission percent and reason
    list($booking_commission_percent, $booking_commission_reason, $customer_id) = bst_calculate_commission_percent($guest1_first_name, $guest1_last_name, $guest1_email, $guest1_phone, $guest2_first_name, $guest2_last_name, $how_heard, $source, $data_source);
    
    // Prepare data for centralized create function
    $booking_data = array(
        'booking_entry_id' => $entry['id'],
        'customer_id' => $customer_id,
        'guest1_first_name' => $guest1_first_name,
        'guest1_last_name' => $guest1_last_name,
        'guest1_phone' => $guest1_phone,
        'guest1_email' => $guest1_email,
        'guest2_first_name' => $guest2_first_name,
        'guest2_last_name' => $guest2_last_name,
        'tour_id' => $tour_id,
        'tour_date_id' => $tour_date_id,
        'tour_package_id' => $tour_package_id,
        'package_people' => $package_people,
        'package_rooms' => $package_rooms,
        'package_vehicles' => $package_vehicles,
        'vehicle_choices' => $vehicle_choices,
        'vehicle1_id' => $vehicle1_id,
        'vehicle2_id' => $vehicle2_id,
        'tour_extension_added' => $tour_extension_added,
        'participant_sex' => $participant_sex,
        'sharing_preference' => $sharing_preference,
        'bed_preference' => $bed_preference,
        'tour_price' => $tour_price_float,
        'tour_currency' => $tour_currency,
        'net_tour_price' => $net_tour_price_float,
        'coupon_code' => $coupon_code,
        'coupon_amount' => $coupon_amount,
        'payment_discount_amount' => $payment_discount_amount,
        'deposit_payment_method' => $payment_method,
        'deposit_payment_amount' => $payment_amount,
        'deposit_payment_discount' => $deposit_payment_discount,
        'deposit_payment_date' => $payment_date,
        'deposit_payment_status' => $payment_details['deposit_payment_status'] ?? null,
        'total_paid' => $total_paid,
        'balance_due' => $balance_due,
        'how_heard' => $how_heard,
        'how_heard_other' => $how_heard_other,
        'motor_club' => $motor_club,
        'source' => $source,
        'referrer' => $referrer,
        'booking_status' => $booking_status,
        'booking_method' => 'Web',
        'booking_commission_percent' => $booking_commission_percent,
        'booking_commission_reason' => $booking_commission_reason,
        'data_source' => $data_source,
        'notes' => $bank_wire_notes
    );
    
    // Use centralized create function
    $result = bst_create_tour_booking($booking_data, 'gf9_submission');
    
    if (!$result['success']) {
        $error_message = 'GF9 submission: Create booking failed for entry ' . $entry['id'] . ' - ' . $result['error'];
        error_log($error_message);
        
        // Get stack trace for debugging
        $stack_trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $formatted_trace = '';
        foreach ($stack_trace as $index => $trace) {
            $file = isset($trace['file']) ? basename($trace['file']) : 'unknown';
            $line = isset($trace['line']) ? $trace['line'] : 'unknown';
            $function = isset($trace['function']) ? $trace['function'] : 'unknown';
            $formatted_trace .= "#{$index} {$file}:{$line} {$function}()\n";
        }
        
        // Add notification with error details and stack trace
        BST_Plugin::add_notification(
            'tour_booking_creation_failed_' . $entry['id'],
            sprintf(
                '<strong>Tour Booking Creation Failed</strong><br>Entry ID: %d<br>Error: %s<br><details><summary>Stack Trace</summary><pre>%s</pre></details>',
                $entry['id'],
                esc_html($result['error']),
                esc_html($formatted_trace)
            ),
            'error',
            true,
            array('manage_options'),
            30 // Expires in 30 days
        );
    } else {
        // Booking created successfully - notification is already handled by bst_create_tour_booking
        $booking_id = $result['booking_id'];
        $success_message = 'GF9 submission: Booking created successfully with ID ' . $booking_id;
        error_log($success_message);
        
        // Process any pending GF9 email that was captured before booking was created
        bst_process_pending_gf9_email($booking_id, $entry['id']);
    }
}

// --- Admin update for form 9 ---
function bst_gf9_handle_admin_update($form, $entry_id) {
    if ($form['id'] != 9) return;
    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) return;
    
    global $wpdb;
    $booking_entry_id = $entry['id'];
    
    // Get existing booking record to calculate correct totals
    $tour_booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE booking_entry_id = %d",
        $booking_entry_id
    ));
    if (!$tour_booking) return;
    
    // Use existing payment method from database - cannot be changed in admin update
    $payment_method = $tour_booking->deposit_payment_method;
    $wire_received_date = rgar($entry, '209');
    $wire_received_amount = floatval(rgar($entry, '210'));
    
    // Only update if payment method is Bank Wire and both wire fields are provided
    if ($payment_method !== 'Bank Wire' || empty($wire_received_date) || empty($wire_received_amount)) {
        return; // Exit early if not a valid bank wire update
    }
    
    // Update deposit payment fields from entry
    $deposit_payment_amount = $wire_received_amount;
    $deposit_payment_date = $wire_received_date;
    $deposit_payment_discount = floatval(rgar($entry, '229')); // Get discount from entry
    
    // Update payment_discount_amount - recalculate from all payment discounts
    $balance_discount = floatval($tour_booking->balance_payment_discount ?? 0);
    $additional_discount = floatval($tour_booking->additional_payment_discount ?? 0);
    $payment_discount_amount = $deposit_payment_discount + $balance_discount + $additional_discount;
    
    // Recalculate total_paid from ALL individual payment amounts in the booking record
    // Use updated deposit value, existing balance/additional/refund values
    $balance_amount = floatval($tour_booking->balance_payment_amount ?? 0);
    $additional_amount = floatval($tour_booking->additional_payment_amount ?? 0);
    $refund_amount = floatval($tour_booking->refund_payment_amount ?? 0);
    $total_paid = $deposit_payment_amount + $balance_amount + $additional_amount - $refund_amount;
    
    // Calculate balance due
    $net_tour_price = floatval(rgar($entry, '160'));
    $additional_charge = floatval($tour_booking->additional_charge ?? 0);
    $balance_due = bst_calculate_balance_due($net_tour_price, $total_paid, $payment_discount_amount, $additional_charge);
    
    // Determine booking status
    $booking_status = ($balance_due <= 0) ? 'Finalized' : 'Booked';
    
    $updated_by = is_user_logged_in() ? wp_get_current_user()->user_login : 'Web';
    $updated_date = current_time('mysql');
    
    $deposit_status_paid = bst_sanitize_payment_status( BST_PAYMENT_STATUS_PAID ) ?: BST_PAYMENT_STATUS_PAID;

    // Prepare update data - update deposit fields, payment discount, and calculated totals
    $update_data = array(
        'booking_status' => $booking_status,
        'deposit_payment_amount' => $deposit_payment_amount,
        'deposit_payment_date' => $deposit_payment_date,
        'deposit_payment_discount' => $deposit_payment_discount,
        'deposit_payment_status' => $deposit_status_paid,
        'payment_discount_amount' => $payment_discount_amount,
        'total_paid' => $total_paid,
        'balance_due' => $balance_due,
        'updated_by' => $updated_by,
        'updated_date' => $updated_date
    );

    // Update tour booking fields using centralized function
    $update_result = bst_update_tour_booking($tour_booking->id, $update_data);
    if (!$update_result['success']) {
        error_log('BST GF9 Admin Update Failed: ' . $update_result['error']);
    }
}

// #endregion

// #region Gravity Forms ID 10 code

// Force currency setting for form 10 specifically using shared helper (run after prepopulation)
add_filter('gform_form_post_get_meta_10', 'bst_force_form_currency_10');
add_filter('gform_pre_render_10', 'bst_force_form_currency_10', 15);
// Removed gform_pre_validation_10 and gform_pre_submission_filter_10 - could impact PayPal

// Hook prepopulation to form rendering (matches Form 9 pattern)
add_filter('gform_pre_render_10', 'bst_gf10_prepopulate_finalization_form', 5);

function bst_gf10_prepopulate_finalization_form($form) {
    $tour_booking_id = null;
    $nonce = null;

    // Check for POST data, and verify nonce
    if (isset($_POST['bid']) && !empty($_POST['bid'])) {
        $encoded_bid = sanitize_text_field($_POST['bid']);
        $tour_booking_id = bst_decode_booking_id($encoded_bid);
        if (isset($_POST['booking_finalization_nonce'])) {
            $nonce = $_POST['booking_finalization_nonce'];
            if ($nonce && !wp_verify_nonce($nonce, 'booking_finalization_nonce')) {
                return $form; // Invalid nonce, return the form as-is
            }
        }
    } elseif (isset($_GET['bid']) && !empty($_GET['bid'])) {
        $encoded_bid = sanitize_text_field($_GET['bid']);
        $tour_booking_id = bst_decode_booking_id($encoded_bid);
    }

    if ($tour_booking_id) {
        global $wpdb;

        // Get the existing tour booking record by id (not booking_entry_id)
        $tour_booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
            $tour_booking_id
        ));

        if ($tour_booking) {
            // Map input names to the database fields
            $field_mapping = array(
                'first_name'     => 'guest1_first_name',
                'last_name'      => 'guest1_last_name', 
                'phone'          => 'guest1_phone',
                'email'          => 'guest1_email',
                'first_name2'     => 'guest2_first_name',
                'last_name2'     => 'guest2_last_name',
                'email2'         => 'guest2_email',
                'guest1_shirtsize' => 'guest1_shirt_size',
                'guest2_shirtsize' => 'guest2_shirt_size',
                'booking_id'     => 'booking_entry_id',  
                'tour'           => 'tour_text',
                'tour_dates'     => 'tour_date_text',
                'package'        => 'tour_package_text',
                'package_people' => 'package_people',
                'vehicle_choices'=> 'vehicle_choices',
                'package_vehicles'=> 'package_vehicles',
                'net_price'      => 'net_tour_price',
                'deposit_paid'   => 'total_paid',
                'balance_due'    => 'balance_due',
                'additonal_charge' => 'additional_charge',
                'booking_update_id' => 'booking_update_id',
                'tour_currency'  => 'tour_currency',
                'bank_wire_discount_perc' => 'bank_wire_discount_perc',
                'deposit_bank_wire_discount' => 'deposit_bank_wire_discount',
                'balance_payment_discount' => 'balance_payment_discount',
                'balance_after_discount' => 'balance_after_discount',
                'bank_wire_code' => 'bank_wire_code',
                'extensionadded' => 'tour_extension_added',
                'extensiontext'  => 'tour_extension_text',
                'extensiondatestext' => 'tour_extension_date_text',
                'require_departure_info' => 'require_departure_info',
                'require_arrival_info' => 'require_arrival_info',
            );
            // Build source data array from the booking object
            $source_data = array();
            foreach ($field_mapping as $input_name => $db_field) {
                $source_data[$db_field] = isset($tour_booking->$db_field) ? $tour_booking->$db_field : '';
            }
            $source_data['vehicle1'] = bst_vehicle_label_for_gf_from_id( (int) ( $tour_booking->vehicle1_id ?? 0 ) );
            $source_data['vehicle2'] = bst_vehicle_label_for_gf_from_id( (int) ( $tour_booking->vehicle2_id ?? 0 ) );
            $live_parts = bst_live_booking_tour_parts( $tour_booking->tour_id, $tour_booking->tour_date_id, $tour_booking->tour_package_id );
            $source_data['tour_text'] = $live_parts['tour_text'];
            $source_data['tour_date_text'] = $live_parts['tour_date_text'];
            $source_data['tour_package_text'] = $live_parts['tour_package_text'];
            
            // Calculate vehicle_choices from stored vehicle CPT ids (not legacy text columns).
            $gf10_v1 = (int) ( $tour_booking->vehicle1_id ?? 0 );
            $gf10_v2 = (int) ( $tour_booking->vehicle2_id ?? 0 );
            $vehicle_choices = ( $gf10_v2 > 0 ) ? '2' : ( ( $gf10_v1 > 0 ) ? '1' : '0' );
            $source_data['vehicle_choices'] = $vehicle_choices;
            
            // Add the booking ID as booking_update_id for form submission
            $source_data['booking_update_id'] = $tour_booking->id;
            
            // Add bank wire discount from settings
            $source_data['bank_wire_discount_perc'] = get_option('bst_bank_wire_discount', 2.5);
            
            // Add deposit bank wire discount
            $source_data['deposit_bank_wire_discount'] = floatval($tour_booking->deposit_payment_discount ?? 0);
            
            // Get require_departure_info from tour ACF field if not in booking
            if (empty($source_data['require_departure_info']) && !empty($tour_booking->tour_id)) {
                $require_departure_info = get_field('require_departure_info', $tour_booking->tour_id);
                $source_data['require_departure_info'] = $require_departure_info ? $require_departure_info : '';
            }
            
            // Get require_arrival_info from tour ACF field if not in booking
            if (empty($source_data['require_arrival_info']) && !empty($tour_booking->tour_id)) {
                $require_arrival_info = get_field('require_arrival_info', $tour_booking->tour_id);
                $source_data['require_arrival_info'] = $require_arrival_info ? $require_arrival_info : '';
            }

            // Live extension labels (same as emails / booking display) — DB snapshot columns may be empty.
            if (!empty($tour_booking->tour_extension_added) && (int) $tour_booking->tour_extension_added === 1) {
                if (function_exists('bst_live_extension_title_for_tour')) {
                    $live_ext_title = bst_live_extension_title_for_tour((int) $tour_booking->tour_id);
                    if ($live_ext_title !== '') {
                        $source_data['tour_extension_text'] = $live_ext_title;
                    }
                }
                if (function_exists('bst_live_extension_date_range_for_booking')) {
                    $live_ext_dates = bst_live_extension_date_range_for_booking($tour_booking);
                    if ($live_ext_dates !== '') {
                        $source_data['tour_extension_date_text'] = $live_ext_dates;
                    }
                }
                if (
                    empty($source_data['tour_extension_text']) && empty($source_data['tour_extension_date_text'])
                    && function_exists('bst_live_booking_extension_display_label')
                ) {
                    $combined = bst_live_booking_extension_display_label($tour_booking);
                    if ($combined !== '') {
                        $source_data['tour_extension_text'] = $combined;
                    }
                }
            }
            
            // Use the utility function to set defaults
            bst_set_gf_field_defaults($form, $field_mapping, $source_data);
        }
    }

    return $form;
}

function bst_force_form_currency_10($form) {
    return bst_set_form_currency($form, 10);
}

// Currency override for form 10 using shared helper
// Currency override hook already registered above (bst_override_forms_currency)

// Add JavaScript to handle dynamic currency changes using shared function (run after currency detection)
add_filter('gform_pre_render_10', 'bst_add_shared_currency_javascript', 20);

// --- Helper function to format travel details as readable text ---
function bst_format_travel_details($travel_method_to, $travel_method_from, $arrival_location, $arrival_flight, $arrival_date, $arrival_time, $departure_location, $departure_flight, $departure_date, $departure_time, $travel_other_to, $travel_other_from) {
    $details = array();
    
    // Handle travel TO tour
    if (!empty($travel_method_to)) {
        // Check for Other/Unknown values - be more flexible with the matching
        if (stripos($travel_method_to, 'other') !== false || stripos($travel_method_to, 'unknown') !== false) {
            $to_detail = !empty($travel_other_to) ? "TO TOUR: " . trim($travel_other_to) : "TO TOUR: " . $travel_method_to;
        } elseif (in_array(strtolower($travel_method_to), array('plane', 'train'))) {
            $transport_type = (strtolower($travel_method_to) === 'plane') ? 'flight' : 'train';
            $to_detail = "TO TOUR";
            
            // Add arrival info if available
            if (!empty($arrival_location) || !empty($arrival_date) || !empty($arrival_flight)) {
                $arrival_text = ' - Arrival';
                if (!empty($arrival_location)) {
                    $arrival_text .= ' at ' . $arrival_location;
                }
                if (!empty($arrival_date)) {
                    $arrival_text .= ': ' . $arrival_date;
                }
                if (!empty($arrival_time)) {
                    $arrival_text .= ' at ' . $arrival_time;
                }
                if (!empty($arrival_flight)) {
                    $arrival_text .= ' on ' . $transport_type . ' ' . $arrival_flight;
                }
                $to_detail .= $arrival_text;
            } else {
                $to_detail .= ' - ' . ucfirst($travel_method_to);
            }
        } else {
            $to_detail = "TO TOUR: " . $travel_method_to;
        }
        $details[] = $to_detail;
    }
    
    // Handle travel FROM tour
    if (!empty($travel_method_from)) {
        // Check for Other/Unknown values - be more flexible with the matching
        if (stripos($travel_method_from, 'other') !== false || stripos($travel_method_from, 'unknown') !== false) {
            $from_detail = !empty($travel_other_from) ? "FROM TOUR: " . trim($travel_other_from) : "FROM TOUR: " . $travel_method_from;
        } elseif (in_array(strtolower($travel_method_from), array('plane', 'train'))) {
            $transport_type = (strtolower($travel_method_from) === 'plane') ? 'flight' : 'train';
            $from_detail = "FROM TOUR";
            
            // Add departure info if available
            if (!empty($departure_location) || !empty($departure_date) || !empty($departure_flight)) {
                $departure_text = ' - Departure';
                if (!empty($departure_location)) {
                    $departure_text .= ' from ' . $departure_location;
                }
                if (!empty($departure_date)) {
                    $departure_text .= ': ' . $departure_date;
                }
                if (!empty($departure_time)) {
                    $departure_text .= ' at ' . $departure_time;
                }
                if (!empty($departure_flight)) {
                    $departure_text .= ' on ' . $transport_type . ' ' . $departure_flight;
                }
                $from_detail .= $departure_text;
            } else {
                $from_detail .= ' - ' . ucfirst($travel_method_from);
            }
        } else {
            $from_detail = "FROM TOUR: " . $travel_method_from;
        }
        $details[] = $from_detail;
    }
    
    return implode("\n", $details);
}

// --- New logic for Gravity Forms ID 10 booking finalization ---
// Use gform_after_submission for Bank Wire (no payment processing needed)
add_action('gform_after_submission_10', 'bst_gf10_handle_submission', 10, 2);
// Use gform_post_payment_completed for Credit Card (fires after Stripe processes payment)
add_action('gform_post_payment_completed', 'bst_gf10_handle_payment_completed', 10, 2);
add_action('gform_after_update_entry_10', 'bst_gf10_handle_admin_update', 10, 2);

// --- Wrapper for Bank Wire payments (gform_after_submission) ---
function bst_gf10_handle_submission($entry, $form) {
    $payment_method = rgar($entry, '118');
    
    // Credit Card: finalization normally runs in gform_post_payment_completed after Stripe.
    // With $0 balance there is often no charge, so that hook may never run; some forms still post
    // "Credit Card" from a hidden/default field — finalize here so the booking still updates.
    // (Empty payment method always falls through below — typical for $0 balance, no card section.)
    if ($payment_method === 'Credit Card') {
        $balance_due = bst_gf10_estimate_balance_due_from_entry( $entry );
        if ( null !== $balance_due && $balance_due <= 0 ) {
            bst_gf10_process_finalization_logic( $entry, $form );
        }
        return;
    }
    
    // Bank Wire and other non-card paths (or empty payment method when no card section shown)
    bst_gf10_process_finalization_logic($entry, $form);
}

// --- Handle Credit Card payments after Stripe processes them ---
function bst_gf10_handle_payment_completed($entry, $action) {
    global $wpdb;
    
    // Only process form 10
    if ($entry['form_id'] != 10) {
        return;
    }
    
    $payment_status = isset($entry['payment_status']) ? $entry['payment_status'] : '';
    $transaction_id = isset($entry['transaction_id']) ? $entry['transaction_id'] : '';
    $payment_method = rgar($entry, '118');
    $entry_id = $entry['id'];
    
    // Check if booking was already updated for this entry
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    $existing_booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $booking_table 
        WHERE finalization_entry_id = %d
        LIMIT 1",
        $entry_id
    ));
    
    // If booking exists and was Processing, update it based on new payment status
    if ($existing_booking && $existing_booking->booking_status === 'Processing') {
        if ($payment_status === 'Paid') {
            // SEPA balance payment cleared - always go to Finalized (GF10 = balance payment)
            $new_status = 'Finalized';
            $wpdb->update(
                $booking_table,
                array('booking_status' => $new_status),
                array('id' => $existing_booking->id),
                array('%s'),
                array('%d')
            );
            // Reprocess finalization to update payment amounts
            $form = GFAPI::get_form($entry['form_id']);
            bst_gf10_process_finalization_logic($entry, $form);
            return;
        } elseif ($payment_status === 'Failed') {
            // SEPA payment failed
            $wpdb->update(
                $booking_table,
                array('booking_status' => 'Payment Failed'),
                array('id' => $existing_booking->id),
                array('%s'),
                array('%d')
            );
            return;
        }
    }
    
    // Always process finalization regardless of payment status
    // The finalization will have appropriate status (Processing/Booked/Finalized) based on payment_status
    
    // Get the form for the second parameter
    $form = GFAPI::get_form($entry['form_id']);
    
    // Call the common processing logic (bypass the Bank Wire wrapper)
    bst_gf10_process_finalization_logic($entry, $form);
}

// --- Common finalization processing logic (called by both Bank Wire and Credit Card handlers) ---
function bst_gf10_process_finalization_logic($entry, $form) {
    global $wpdb;
    
    $payment_method = rgar($entry, '118'); // Field 118 is payment method
    
    // Get booking ID from booking_update_id field (ID 261)
    $tour_booking_id = rgar($entry, '261');
    if (!$tour_booking_id || !is_numeric($tour_booking_id)) {
        return;
    }
    
    // Get the booking record using the direct booking ID
    $tour_booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
        intval($tour_booking_id)
    ));
    
    if (!$tour_booking) {
        return;
    }

    // Extract guest 1 details
    $guest1_first_name = rgar($entry, '31.3'); // Guest 1 Name (Nome)
    $guest1_last_name = rgar($entry, '31.6'); // Guest 1 Name (Cognome)
    $guest1_nickname = rgar($entry, '163'); // Guest 1 Nickname
    $guest1_phone = rgar($entry, '34'); // Guest 1 Phone
    $guest1_email = rgar($entry, '33'); // Guest 1 Email
    $guest1_address_line1 = rgar($entry, '139.1'); // Guest 1 Address Line 1
    $guest1_address_line2 = rgar($entry, '139.2'); // Guest 1 Address Line 2
    $guest1_city = rgar($entry, '139.3'); // Guest 1 City
    $guest1_state_province = rgar($entry, '139.4'); // Guest 1 State/Province
    $guest1_postal_code = rgar($entry, '139.5'); // Guest 1 ZIP/Postal Code
    $guest1_country = rgar($entry, '139.6'); // Guest 1 Country
    $guest1_driving_status = rgar($entry, '258'); // Guest 1 Drive/Passenger Status
    $guest1_shirt_size = rgar($entry, '150'); // Guest 1 Shirt Size
    
    // Build guest 1 travel details
    $guest1_travel_method_to = rgar($entry, '237'); // Travel method TO tour
    $guest1_travel_method_from = rgar($entry, '262'); // Travel method FROM tour
    $guest1_arrival_location = rgar($entry, '231');
    $guest1_arrival_flight = rgar($entry, '232');
    $guest1_arrival_date = rgar($entry, '233');
    $guest1_arrival_time = rgar($entry, '235');
    $guest1_departure_location = rgar($entry, '245');
    $guest1_departure_flight = rgar($entry, '246');
    $guest1_departure_date = rgar($entry, '247');
    $guest1_departure_time = rgar($entry, '248');
    $guest1_travel_other_to = rgar($entry, '250'); // Travel TO tour - Other/Unknown
    $guest1_travel_other_from = rgar($entry, '263'); // Travel FROM tour - Other/Unknown
    
    $guest1_travel_details = bst_format_travel_details(
        $guest1_travel_method_to,
        $guest1_travel_method_from,
        $guest1_arrival_location,
        $guest1_arrival_flight,
        $guest1_arrival_date,
        $guest1_arrival_time,
        $guest1_departure_location,
        $guest1_departure_flight,
        $guest1_departure_date,
        $guest1_departure_time,
        $guest1_travel_other_to,
        $guest1_travel_other_from
    );
    
    $guest1_dietary_restrictions = rgar($entry, '211'); // Guest 1 Food preferences
    $guest1_medical_insurance = rgar($entry, '226'); // Guest 1 Medical/Insurance Information
    
    // Build guest 1 emergency contact - separate fields
    $guest1_emergency_contact_name = rgar($entry, '218');
    $guest1_emergency_contact_phone = rgar($entry, '219');
    $guest1_emergency_contact_email = rgar($entry, '221');
    
    // Extract guest 2 details (if package has 2 people)
    $package_people = rgar($entry, '196');
    $guest2_first_name = null;
    $guest2_last_name = null;
    $guest2_nickname = null;
    $guest2_phone = null;
    $guest2_email = null;
    $guest2_address_line1 = null;
    $guest2_address_line2 = null;
    $guest2_city = null;
    $guest2_state_province = null;
    $guest2_postal_code = null;
    $guest2_country = null;
    $guest2_driving_status = null;
    $guest2_shirt_size = null;
    $guest2_travel_details = null;
    $guest2_dietary_restrictions = null;
    $guest2_medical_insurance = null;
    $guest2_emergency_contact_name = null;
    $guest2_emergency_contact_phone = null;
    $guest2_emergency_contact_email = null;
    
    if ($package_people == 2) {
        $guest2_first_name = rgar($entry, '135.3'); // Guest 2 Name (Nome)
        $guest2_last_name = rgar($entry, '135.6'); // Guest 2 Name (Cognome)
        
        $guest2_nickname = rgar($entry, '165'); // Guest 2 Nickname
        $guest2_phone = rgar($entry, '136'); // Guest 2 Phone
        $guest2_email = rgar($entry, '178'); // Guest 2 Email
        $guest2_address_line1 = rgar($entry, '180.1'); // Guest 2 Address Line 1
        $guest2_address_line2 = rgar($entry, '180.2'); // Guest 2 Address Line 2
        $guest2_city = rgar($entry, '180.3'); // Guest 2 City
        $guest2_state_province = rgar($entry, '180.4'); // Guest 2 State/Province
        $guest2_postal_code = rgar($entry, '180.5'); // Guest 2 ZIP/Postal Code
        $guest2_country = rgar($entry, '180.6'); // Guest 2 Country
        $guest2_driving_status = rgar($entry, '159'); // Guest 2 Drive/Passenger Status
        $guest2_shirt_size = rgar($entry, '151'); // Guest 2 Shirt Size
        
        // Check if guest 2 travel details are same as guest 1
        $travel_details_same = rgar($entry, '249.1'); // "same as mine" checkbox
        if ($travel_details_same == '1') {
            $guest2_travel_details = "Same Travel Details as Guest 1"; // Use descriptive text instead of copying
        } else {
            // Build guest 2 travel details
            $guest2_travel_method_to = rgar($entry, '240'); // Travel method TO tour
            $guest2_travel_method_from = rgar($entry, '264'); // Travel method FROM tour
            $guest2_arrival_location = rgar($entry, '241');
            $guest2_arrival_flight = rgar($entry, '242');
            $guest2_arrival_date = rgar($entry, '243');
            $guest2_arrival_time = rgar($entry, '244');
            $guest2_departure_location = rgar($entry, '251');
            $guest2_departure_flight = rgar($entry, '252');
            $guest2_departure_date = rgar($entry, '253');
            $guest2_departure_time = rgar($entry, '254');
            $guest2_travel_other_to = rgar($entry, '256'); // Travel TO tour - Other/Unknown
            $guest2_travel_other_from = rgar($entry, '265'); // Travel FROM tour - Other/Unknown
            
            $guest2_travel_details = bst_format_travel_details(
                $guest2_travel_method_to,
                $guest2_travel_method_from,
                $guest2_arrival_location,
                $guest2_arrival_flight,
                $guest2_arrival_date,
                $guest2_arrival_time,
                $guest2_departure_location,
                $guest2_departure_flight,
                $guest2_departure_date,
                $guest2_departure_time,
                $guest2_travel_other_to,
                $guest2_travel_other_from
            );
        }
        
        $guest2_dietary_restrictions = rgar($entry, '212'); // Guest 2 Food preferences
        $guest2_medical_insurance = rgar($entry, '227'); // Guest 2 Medical/Insurance Information
        
        // Check if guest 2 emergency contact is same as guest 1
        $emergency_contact_same = rgar($entry, '257.1'); // "same as mine" checkbox
        if ($emergency_contact_same) {
            // Copy guest 1 emergency contact
            $guest2_emergency_contact_name = $guest1_emergency_contact_name;
            $guest2_emergency_contact_phone = $guest1_emergency_contact_phone;
            $guest2_emergency_contact_email = $guest1_emergency_contact_email;
        } else {
            // Build guest 2 emergency contact - separate fields
            // Field 266 is now guest2 emergency contact name (was 223)
            $guest2_emergency_contact_name = rgar($entry, '266');
            $guest2_emergency_contact_phone = rgar($entry, '224');
            $guest2_emergency_contact_email = rgar($entry, '225');
        }
    }

    // Check payment information using helper function
    // Try to read from entry fields first, fallback to booking data if empty
    $existing_total_paid = floatval(rgar($entry, '193')); // existing total paid
    if ($existing_total_paid == 0) {
        $existing_total_paid = floatval($tour_booking->total_paid ?? 0);
    }
    
    $net_tour_price = floatval(rgar($entry, '192'));
    if ($net_tour_price == 0) {
        $net_tour_price = floatval($tour_booking->net_tour_price ?? 0);
    }
    
    $additional_charge = floatval(rgar($entry, '285')); // additional charge from read-only field
    if ($additional_charge == 0) {
        $additional_charge = floatval($tour_booking->additional_charge ?? 0);
    }
    
    // Get balance payment discount from field 278 - only if Bank Wire
    $balance_payment_discount = ($payment_method === 'Bank Wire') ? floatval(rgar($entry, '278')) : 0;
    
    // Calculate total payment discount from all three payment discount fields
    $deposit_discount = floatval($tour_booking->deposit_payment_discount ?? 0);
    $additional_discount = floatval($tour_booking->additional_payment_discount ?? 0);
    $payment_discount_amount = $deposit_discount + $balance_payment_discount + $additional_discount;
    
    // === Calculate Invoice Fields ===
    // Set booking_invoice_date to GF10 finalization entry date
    $booking_invoice_date = $entry['date_created'];
    
    // Get VAT rate from settings
    $vat_rate = get_option('bst_vat_rate'); // No default to verify setting retrieval
    
    // Calculate invoice fields using shared helper function
    $invoice_fields = bst_calculate_invoice_fields($tour_booking);
    $booking_eu_percent = $invoice_fields['eu_percent'];
    $booking_vehicle_1_use_amount = $invoice_fields['vehicle_1_amount'];
    $booking_vehicle_2_use_amount = $invoice_fields['vehicle_2_amount'];
    
    // Calculate booking_tour_package_amount
    $booking_tour_package_amount = $net_tour_price - $booking_vehicle_1_use_amount - $booking_vehicle_2_use_amount - $payment_discount_amount;
    
    // For balance_due calculation: existing_total_paid already has deposit after discount applied
    // Pass only the NEW balance payment discount (not deposit discount which is already in existing_total_paid)
    // existing_payment_discount = deposit discount that's already factored into existing_total_paid
    $existing_payment_discount = $deposit_discount;
    
    // Calculate payment details using helper function
    // Pass only the balance_payment_discount as the bank_wire_discount (not the total payment_discount_amount)
    $payment_details = bst_calculate_gf10_payment_details(
        $entry, 
        $existing_total_paid, 
        $net_tour_price, 
        $additional_charge, 
        'submission',
        $balance_payment_discount,  // Only the NEW balance discount
        $existing_payment_discount  // Deposit discount (already in existing_total_paid)
    );
    
    $payment_method = $payment_details['payment_method'];
    $payment_amount = $payment_details['payment_amount'];
    $payment_date = $payment_details['payment_date'];
    $total_paid = $payment_details['total_paid'];
    $balance_due = $payment_details['balance_due'];
    $has_payment_info = $payment_details['has_payment_info'];
    $balance_payment_status = $payment_details['balance_payment_status'] ?? null;

    if ( function_exists( 'bst_merge_booking_status_with_payment_line_statuses' ) ) {
        $booking_status = bst_merge_booking_status_with_payment_line_statuses(
            $payment_details['booking_status'] ? $payment_details['booking_status'] : $tour_booking->booking_status,
            $tour_booking->deposit_payment_status ?? null,
            $balance_payment_status,
            $tour_booking->additional_payment_status ?? null,
            $tour_booking->refund_payment_status ?? null
        );
    } else {
        $booking_status = $payment_details['booking_status'] ? $payment_details['booking_status'] : $tour_booking->booking_status;
    }
    
    // Build bank wire notes if payment method is Bank Wire and append to existing notes
    $bank_wire_notes = '';
    if ($payment_method === 'Bank Wire' && $balance_payment_discount > 0) {
        $date_chosen = date('Y-m-d');
        // Get tour currency from booking
        $tour_currency = $tour_booking->tour_currency;
        // Get payment currency from field 280 (Bank Wire Code for gf10)
        $payment_currency = rgar($entry, '280');
        // Convert region code to proper currency code (e.g., "Other" -> "EUR", pass through USD/CAD/GBP/AUD)
        $payment_currency = bst_region_to_currency_code($payment_currency);
        // Get balance after discount from field 279 (in tour currency) and convert to payment currency
        $balance_after_discount = floatval(rgar($entry, '279'));
        
        // Convert from tour currency to payment currency using exchange rate
        $exchange_rate = bst_get_exchange_rate($tour_currency, $payment_currency);
        if ($exchange_rate && $exchange_rate > 0) {
            $converted_amount = $balance_after_discount * $exchange_rate;
        } else {
            // Fallback if exchange rate not available
            $converted_amount = $balance_after_discount;
            error_log("BST: Bank wire notes (gf10) - exchange rate not found for {$tour_currency} to {$payment_currency}");
        }
        
        $bank_wire_notes = "Customer chose Bank Wire for their balance payment on {$date_chosen} and was instructed to send " . number_format($converted_amount, 2) . " in {$payment_currency} (which is converted from " . number_format($balance_after_discount, 2) . " {$tour_currency} at today's exchange rates)";
    }
    
    $updated_by = is_user_logged_in() ? wp_get_current_user()->user_login : 'Web';
    $updated_date = current_time('mysql');
    
    // Sanitize guest fields before database update
    $sanitized_fields = bst_sanitize_booking_fields(array(
        'guest1_first_name' => $guest1_first_name,
        'guest1_last_name' => $guest1_last_name,
        'guest1_nickname' => $guest1_nickname,
        'guest1_phone' => $guest1_phone,
        'guest1_email' => $guest1_email,
        'guest1_address_line1' => $guest1_address_line1,
        'guest1_address_line2' => $guest1_address_line2,
        'guest1_city' => $guest1_city,
        'guest1_state_province' => $guest1_state_province,
        'guest1_postal_code' => $guest1_postal_code,
        'guest1_country' => $guest1_country,
        'guest2_first_name' => $guest2_first_name,
        'guest2_last_name' => $guest2_last_name,
        'guest2_nickname' => $guest2_nickname,
        'guest2_phone' => $guest2_phone,
        'guest2_email' => $guest2_email,
        'guest2_address_line1' => $guest2_address_line1,
        'guest2_address_line2' => $guest2_address_line2,
        'guest2_city' => $guest2_city,
        'guest2_state_province' => $guest2_state_province,
        'guest2_postal_code' => $guest2_postal_code,
        'guest2_country' => $guest2_country,
        'updated_by' => $updated_by
    ));
    
    // Prepare update data with all guest fields
    $update_data = array(
        'finalization_entry_id' => $entry['id'],
        'booking_invoice_date' => $booking_invoice_date,
        'booking_vat_rate' => $vat_rate,
        'booking_eu_percent' => $booking_eu_percent,
        'booking_vehicle_1_use_amount' => $booking_vehicle_1_use_amount,
        'booking_vehicle_2_use_amount' => $booking_vehicle_2_use_amount,
        'booking_tour_package_amount' => $booking_tour_package_amount,
        'guest1_first_name' => $sanitized_fields['guest1_first_name'],
        'guest1_last_name' => $sanitized_fields['guest1_last_name'],
        'guest1_nickname' => $sanitized_fields['guest1_nickname'],
        'guest1_phone' => $sanitized_fields['guest1_phone'],
        'guest1_email' => $sanitized_fields['guest1_email'],
        'guest1_address_line1' => $sanitized_fields['guest1_address_line1'],
        'guest1_address_line2' => $sanitized_fields['guest1_address_line2'],
        'guest1_city' => $sanitized_fields['guest1_city'],
        'guest1_state_province' => $sanitized_fields['guest1_state_province'],
        'guest1_postal_code' => $sanitized_fields['guest1_postal_code'],
        'guest1_country' => $sanitized_fields['guest1_country'],
        'guest1_driving_status' => $guest1_driving_status,
        'guest1_shirt_size' => $guest1_shirt_size,
        'guest1_travel_details' => $guest1_travel_details,
        'guest1_dietary_restrictions' => $guest1_dietary_restrictions,
        'guest1_medical_insurance' => $guest1_medical_insurance,
        'guest1_emergency_contact_name' => $guest1_emergency_contact_name,
        'guest1_emergency_contact_phone' => $guest1_emergency_contact_phone,
        'guest1_emergency_contact_email' => $guest1_emergency_contact_email,
        'guest2_first_name' => $sanitized_fields['guest2_first_name'],
        'guest2_last_name' => $sanitized_fields['guest2_last_name'],
        'guest2_nickname' => $sanitized_fields['guest2_nickname'],
        'guest2_phone' => $sanitized_fields['guest2_phone'],
        'guest2_email' => $sanitized_fields['guest2_email'],
        'guest2_address_line1' => $sanitized_fields['guest2_address_line1'],
        'guest2_address_line2' => $sanitized_fields['guest2_address_line2'],
        'guest2_city' => $sanitized_fields['guest2_city'],
        'guest2_state_province' => $sanitized_fields['guest2_state_province'],
        'guest2_postal_code' => $sanitized_fields['guest2_postal_code'],
        'guest2_country' => $sanitized_fields['guest2_country'],
        'guest2_driving_status' => $guest2_driving_status,
        'guest2_shirt_size' => $guest2_shirt_size,
        'guest2_travel_details' => $guest2_travel_details,
        'guest2_dietary_restrictions' => $guest2_dietary_restrictions,
        'guest2_medical_insurance' => $guest2_medical_insurance,
        'guest2_emergency_contact_name' => $guest2_emergency_contact_name,
        'guest2_emergency_contact_phone' => $guest2_emergency_contact_phone,
        'guest2_emergency_contact_email' => $guest2_emergency_contact_email,
        'booking_status' => $booking_status,
        'payment_discount_amount' => $payment_discount_amount,
        'balance_payment_discount' => $balance_payment_discount,
        'balance_payment_status' => $balance_payment_status,
    );
    
    // If we have bank wire notes, append them to existing notes
    if (!empty($bank_wire_notes)) {
        $existing_notes = $tour_booking->notes;
        if (!empty($existing_notes)) {
            $update_data['notes'] = $existing_notes . "\n" . $bank_wire_notes;
        } else {
            $update_data['notes'] = $bank_wire_notes;
        }
    }
    
    // Balance line: only persist when there is a real payment method to record (Bank Wire, card, etc.).
    // Zero-balance finalization has no method and must clear any stale balance_* values from older edits.
    if (!empty($payment_method)) {
        $update_data['balance_payment_method'] = $payment_method;
        $update_data['balance_payment_amount'] = $payment_amount;
        if ($has_payment_info) {
            $update_data['balance_payment_date'] = $payment_date;
        }
        $update_data['total_paid'] = $total_paid;
        $update_data['balance_due'] = $balance_due;
    } else {
        $update_data['balance_payment_method']   = '';
        $update_data['balance_payment_amount']   = 0;
        $update_data['balance_payment_date']     = null;
        $update_data['balance_payment_status']   = null;
        $update_data['balance_payment_discount'] = 0;
        $update_data['total_paid']                 = $total_paid;
        $update_data['balance_due']                = $balance_due;
    }
    
    // Use centralized update function
    $result = bst_update_tour_booking($tour_booking->id, $update_data, 'gf10_finalization');
    
    // Process any pending email notifications that were stored in transient
    bst_process_pending_gf10_email($tour_booking->id, $entry['id']);
}

// --- Admin handler to import GF9 tour bookings ---
add_action('admin_post_bst_import_gf9_tour_bookings', 'bst_import_gf9_tour_bookings_handler');
function bst_import_gf9_tour_bookings_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    global $wpdb;
    $imported = 0;
    $skipped = 0;
    
    // Get all GF9 entries
    $entries = GFAPI::get_entries(9);
    
    if (is_wp_error($entries)) {
        wp_redirect(admin_url('admin.php?page=bst-tour-bookings&gf9_import=error'));
        exit;
    }
    
    // Get the form object for the create function
    $form = GFAPI::get_form(9);
    
    foreach ($entries as $entry) {
        // Check if booking already exists for this entry
        $existing_booking_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bst_tour_booking WHERE booking_entry_id = %d",
            $entry['id']
        ));
        
        if ($existing_booking_id) {
            $skipped++;
            continue;
        }
        
        // Create booking using existing logic (includes wire payment processing)
        bst_gf9_create_booking($entry, $form);
        $imported++;
    }
    
    wp_redirect(admin_url('admin.php?page=bst-tour-bookings&gf9_import=done&imported=' . $imported . '&skipped=' . $skipped));
    exit;
}

// --- Admin handler to import GF10 tour bookings ---
add_action('admin_post_bst_import_gf10_tour_bookings', 'bst_import_gf10_tour_bookings_handler');
function bst_import_gf10_tour_bookings_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    global $wpdb;
    $imported = 0;
    $skipped = 0;
    
    // Get all GF10 entries
    $entries = GFAPI::get_entries(10);
    
    if (is_wp_error($entries)) {
        wp_redirect(admin_url('admin.php?page=bst-tour-bookings&gf10_import=error'));
        exit;
    }
    
    // Get the form object for the update function
    $form = GFAPI::get_form(10);
    
    foreach ($entries as $entry) {
        // Try to locate the booking via field 261 (direct booking ID, preferred) or
        // fall back to field 195 (GF9 booking entry ID, legacy).
        $booking_update_id = rgar($entry, '261'); // direct booking ID (current)
        $booking_entry_id  = rgar($entry, '195'); // GF9 entry ID (legacy)
        
        $existing_booking = null;
        
        if ($booking_update_id && is_numeric($booking_update_id)) {
            $existing_booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
                intval($booking_update_id)
            ));
        }
        
        if (!$existing_booking && $booking_entry_id) {
            $existing_booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE booking_entry_id = %d",
                $booking_entry_id
            ));
        }
        
        if (!$existing_booking) {
            $skipped++;
            continue;
        }
        
        // Check if finalization already processed for this entry
        if ($existing_booking->finalization_entry_id == $entry['id']) {
            $skipped++;
            continue;
        }
        
        // Process the finalization using existing logic
        bst_gf10_handle_submission($entry, $form);
        $imported++;
    }
    
    wp_redirect(admin_url("admin.php?page=bst-tour-bookings&gf10_import=done&imported={$imported}&skipped={$skipped}"));
    exit;
}

// --- Admin update for form 10 ---
function bst_gf10_handle_admin_update($form, $entry_id) {
    if ($form['id'] != 10) return;
    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) return;
    
    global $wpdb;
    $booking_entry_id = rgar($entry, '195');
    $tour_booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE booking_entry_id = %d",
        $booking_entry_id
    ));
    if (!$tour_booking) return;
    
    // Get payment method from entry or database
    $payment_method = rgar($entry, '118') ?: ($tour_booking->balance_payment_method ?: $tour_booking->deposit_payment_method);
    
    // Skip Credit Card payments - those are managed by Stripe
    if ($payment_method === 'Credit Card') {
        error_log('BST GF10 Admin Update: Skipping Credit Card payment - managed by Stripe');
        return;
    }
    
    $wire_received_date = rgar($entry, '213');
    $wire_received_amount = floatval(rgar($entry, '214'));
    $net_tour_price = floatval(rgar($entry, '192'));
    $additional_charge = floatval(rgar($entry, '285')); // additional charge from read-only field

    // Only proceed if payment method is Bank Wire and both wire fields are provided
    if ($payment_method !== 'Bank Wire' || empty($wire_received_date) || empty($wire_received_amount)) {
        return; // Exit early if not a valid bank wire update
    }

    // Calculate payment info for Bank Wire only
    $payment_amount = floatval($wire_received_amount);
    $payment_date = $wire_received_date;
    $balance_payment_discount = floatval(rgar($entry, '278')); // Get discount from entry
    
    // Recalculate total_paid from ALL individual payment amounts in the booking record
    // Use updated balance value, existing deposit/additional/refund values
    $deposit_amount = floatval($tour_booking->deposit_payment_amount ?? 0);
    $additional_amount = floatval($tour_booking->additional_payment_amount ?? 0);
    $refund_amount = floatval($tour_booking->refund_payment_amount ?? 0);
    $total_paid = $deposit_amount + $payment_amount + $additional_amount - $refund_amount;
    
    // Update payment_discount_amount - recalculate from all payment discounts
    $deposit_discount = floatval($tour_booking->deposit_payment_discount ?? 0);
    $additional_discount = floatval($tour_booking->additional_payment_discount ?? 0);
    $payment_discount_amount = $deposit_discount + $balance_payment_discount + $additional_discount;
    
    $balance_due = bst_calculate_balance_due($net_tour_price, $total_paid, $payment_discount_amount, $additional_charge);
    // GF10 is balance payment, so receiving payment means Finalized
    $booking_status = 'Finalized';
    
    $updated_by = is_user_logged_in() ? wp_get_current_user()->user_login : 'Web';
    $updated_date = current_time('mysql');

    $balance_status_paid = bst_sanitize_payment_status( BST_PAYMENT_STATUS_PAID ) ?: BST_PAYMENT_STATUS_PAID;

    // Update only payment and status fields for admin updates (bank wire received in GF entry)
    $update_data = array(
        'balance_payment_amount' => $payment_amount,
        'balance_payment_date' => $payment_date,
        'balance_payment_discount' => $balance_payment_discount,
        'balance_payment_status' => $balance_status_paid,
        'payment_discount_amount' => $payment_discount_amount,
        'booking_status' => $booking_status,
        'total_paid' => $total_paid,
        'balance_due' => $balance_due,
        'updated_by' => $updated_by,
        'updated_date' => $updated_date
    );
    
    // Update tour booking using centralized function
    $update_result = bst_update_tour_booking($tour_booking->id, $update_data);
    if (!$update_result['success']) {
    }
}

// Send an email to the user to complete the balance payment
function send_balance_payment_email($user_email, $tour_booking_id) {
    // Generate a nonce
    $nonce = wp_create_nonce('booking_finalization_nonce');

    // Get the hostname of the current WordPress site
    $site_url = wp_parse_url(get_site_url());
    $hostname = $site_url['host'];
    
    // Generate the form HTML
    $form_html = '
        <p>Dear User,</p>
        <p>Please complete your balance payment by clicking the button below:</p>
        <form action="https://' . esc_attr($hostname) . '/booking-finalization-form/" method="post">
            <input type="hidden" name="id" value="' . esc_attr($tour_booking_id) . '">
            <input type="hidden" name="booking_finalization_nonce" value="' . esc_attr($nonce) . '">
            <input type="submit" value="Complete Balance Payment">
        </form>
        <p>Thank you!</p>
    ';

    // Send the email
    wp_mail($user_email, 'Finalize Your Booking', $form_html, array('Content-Type: text/html; charset=UTF-8'));
}

// --- Utility Functions ---

/**
 * Truncate a string field to specified length if it's a string
 * @param mixed $value The value to truncate
 * @param int $length Maximum length
 * @return mixed The truncated value or original if not a string
 */
function bst_truncate_field($value, $length) {
    return is_string($value) ? mb_substr($value, 0, $length) : $value;
}

/**
 * Truncate multiple fields according to database column limits
 * @param array $data Associative array of field data
 * @return array The data with truncated string fields
 */
function bst_sanitize_booking_fields($data) {
    $field_limits = array(
        'guest1_first_name' => 100,
        'guest1_last_name' => 100,
        'guest1_nickname' => 100,
        'guest1_phone' => 20,
        'guest1_email' => 100,
        'guest1_address_line1' => 100,
        'guest1_address_line2' => 100,
        'guest1_city' => 50,
        'guest1_state_province' => 50,
        'guest1_postal_code' => 20,
        'guest1_country' => 50,
        'guest2_first_name' => 100,
        'guest2_last_name' => 100,
        'guest2_nickname' => 100,
        'guest2_phone' => 20,
        'guest2_email' => 100,
        'guest2_address_line1' => 100,
        'guest2_address_line2' => 100,
        'guest2_city' => 50,
        'guest2_state_province' => 50,
        'guest2_postal_code' => 20,
        'guest2_country' => 50,
        'tour_text' => 100,
        'tour_date_text' => 40,
        'tour_package_text' => 20,
        'vehicle1' => 100,
        'vehicle2' => 100,
        'participant_sex' => 10,
        'sharing_preference' => 20,
        'bed_preference' => 20,
        'coupon_code' => 50,
        'deposit_payment_method' => 20,
        'how_heard' => 100,
        'how_heard_other' => 100,
        'motor_club' => 100,
        'source' => 100,
        'referrer' => 100,
        'created_by' => 50,
        'updated_by' => 50
    );
    
    foreach ($field_limits as $field_name => $max_length) {
        if (isset($data[$field_name])) {
            $data[$field_name] = bst_truncate_field($data[$field_name], $max_length);
        }
    }
    
    return $data;
}

function bst_set_gf_field_defaults(&$form, $mapping, $source_data) {
    foreach ($form['fields'] as &$field) {
        if (!empty($field->inputName) && isset($mapping[$field->inputName])) {
            $value = $mapping[$field->inputName];
            $field->defaultValue = isset($source_data[$value]) ? esc_html($source_data[$value]) : '';
        }
        if ($field->type === 'email') {
            if (isset($field->inputs) && is_array($field->inputs)) {
                // Multi-input email field (like email confirmation)
                // Determine which email to use based on inputName
                $email_value = '';
                if (!empty($field->inputName)) {
                    if ($field->inputName === 'email2' && isset($source_data['guest2_email'])) {
                        $email_value = $source_data['guest2_email'];
                    } elseif ($field->inputName === 'email' && isset($source_data['guest1_email'])) {
                        $email_value = $source_data['guest1_email'];
                    } elseif (isset($source_data['booking_email'])) {
                        $email_value = $source_data['booking_email'];
                    }
                } else {
                    // No inputName, default to guest1_email
                    if (isset($source_data['guest1_email'])) {
                        $email_value = $source_data['guest1_email'];
                    } elseif (isset($source_data['booking_email'])) {
                        $email_value = $source_data['booking_email'];
                    }
                }
                
                if (!empty($email_value)) {
                    foreach ($field->inputs as &$input) {
                        $input['defaultValue'] = esc_html($email_value);
                    }
                }
            } else {
                // Simple email field - already handled by the inputName logic at the top
                // No additional processing needed
            }
        }
        if ($field->type === 'name' && isset($field->inputs) && is_array($field->inputs)) {
            foreach ($field->inputs as &$input) {
                // Handle guest 1 name fields
                if ($input['name'] === 'first_name' && isset($source_data['guest1_first_name'])) {
                    $input['defaultValue'] = esc_html($source_data['guest1_first_name']);
                } elseif ($input['name'] === 'last_name' && isset($source_data['guest1_last_name'])) {
                    $input['defaultValue'] = esc_html($source_data['guest1_last_name']);
                }
                
                // Handle guest 2 name fields (inputs with name 'first_name2' and 'last_name2')
                if ($input['name'] === 'first_name2' && isset($source_data['guest2_first_name'])) {
                    $input['defaultValue'] = esc_html($source_data['guest2_first_name']);
                } elseif ($input['name'] === 'last_name2' && isset($source_data['guest2_last_name'])) {
                    $input['defaultValue'] = esc_html($source_data['guest2_last_name']);
                }
            }
        }
    }
}

function bst_gf_inject_error(&$form, $message) {
    $message = $message . '. Please contact Blue Strada Tours for information. <a href="mailto:info@bluestradatours.com">info@bluestradatours.com</a>';
    if (class_exists('GF_Field_HTML')) {
        $error_field = new GF_Field_HTML(array(
            // Use wp_kses_post to allow the <a> tag, or do not escape at all if you trust the message
            'content' => '<h3 class="gform-error-header" style="padding: 1em 1.5em;">' . wp_kses_post($message) . '</h3>',
            'cssClass' => 'gform_validation_errors'
        ));
        array_unshift($form['fields'], $error_field);
    }
}

function bst_gf_remove_all_errors(&$form) {
    $form['fields'] = array_filter($form['fields'], function($f) {
        if ($f instanceof GF_Field_HTML && isset($f->cssClass) && strpos($f->cssClass, 'gform_validation_errors') !== false) {
            return false;
        }
        return true;
    });
}

function bst_extract_booking_data($entry) {
    $form_id = isset( $entry['form_id'] ) ? (int) $entry['form_id'] : 9;
    $tour_ids = bst_gf9_entry_normalized_tour_ids( $entry );
    $tour_id = (int) $tour_ids['tour_id'];
    list( $vehicle1_id, $vehicle2_id ) = bst_gf9_entry_vehicle_ids_from_fields( $entry );
    bst_gf9_resolve_legacy_vehicle_ids( $entry, $form_id, $tour_id, $vehicle1_id, $vehicle2_id );
    $live = bst_gf9_entry_live_tour_parts( $entry );

    return [
        'guest1_first_name' => rgar($entry, '31.3'),
        'guest1_last_name' => rgar($entry, '31.6'),
        'guest1_phone' => rgar($entry, '34'),
        'guest1_email' => rgar($entry, '33'),
        'tour_id' => (string) $tour_ids['tour_id'],
        'tour_text' => $live['tour_text'],
        'tour_date_id' => $tour_ids['tour_date_id_raw'],
        'tour_date_text' => $live['tour_date_text'],
        'tour_package_id' => (string) $tour_ids['tour_package_id'],
        'tour_package_text' => $live['tour_package_text'],
        'vehicle1_id' => $vehicle1_id,
        'vehicle2_id' => $vehicle2_id,
        'package_people' => rgar($entry, '200'),
        'package_rooms' => rgar($entry, '201'),
        'package_vehicles' => rgar($entry, '212'),
        'vehicle_choices' => rgar($entry, '214'),
        'tour_price' => rgar($entry, '143'),
        'net_tour_price' => rgar($entry, '160'),
        'coupon_code' => rgar($entry, '161'),
        'how_heard' => rgar($entry, '101'),
        'how_heard_other' => rgar($entry, '102') ?: rgar($entry, '104'), // guest, other (excluding motor club) - only one will be filled
        'motor_club' => rgar($entry, '103'), // Motor Club - separate field
        'source' => rgar($entry, '109'),
        'referrer' => (function() use ($entry) {
            $ref = rgar($entry, '110');
            return (!empty($ref) && stripos($ref, 'gravityapi.com') !== false) ? null : $ref;
        })(),
        'comments' => rgar($entry, '211'),
        'participant_sex' => rgar($entry, '75'),
        'sharing_preference' => rgar($entry, '74'),
        'bed_preference' => rgar($entry, '68'),
    ];
}

function bst_calculate_commission_percent($first_name, $last_name, $email, $phone, $first_name2, $last_name2, $how_heard, $source, $data_source = null) {
    global $wpdb;



    $customer = null;
    $commission_percent = 0;
    $commission_reason = null;
    $customer_id = null;
    $credit = null;

    if (!empty($email) && $email !== 'none') {
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_customers WHERE email = %s",
            $email
        ));
        
        if (!$customer) {
            // If no customer found by email, also try by name with null/empty email
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bst_customers WHERE first_name = %s AND last_name = %s AND (email IS NULL OR email = '' OR email = 'none')",
                $first_name,
                $last_name
            ));
        }
    } else {
        // If no email or email is 'none', search by name with any empty-ish email
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_customers WHERE first_name = %s AND last_name = %s AND (email IS NULL OR email = '' OR email = 'none')",
            $first_name,
            $last_name
        ));
    }

    // if a customer is found, we will use the credit field to determine commission
    if ($customer) {
        $customer_id = $customer->id;
        $credit = trim($customer->credit);

            // then set thhe fields based on the DB
            if($credit === 'Bill') {
                $commission_percent = 0.02;
                $commission_reason = 'Bill Customer';
            } elseif($credit === 'Claudio') {
                $commission_percent = 0.02;
                $commission_reason = 'Claudio Customer';
            } elseif ($credit === 'Wayne') {
                $commission_percent = 0.05;
                $commission_reason = 'Wayne Customer';
            } else {
                $commission_percent = 0.05;
                $commission_reason = 'Existing Web Customer';
            }

            // Consolidate all customer updates into a single operation to prevent data loss
            $customer_update_data = array();
            
            // Update phone if saved phone is blank
            if (empty($customer->phone) && !empty($phone)) {    
                $customer_update_data['phone'] = $phone;
                error_log(sprintf('BST GF Customer: Adding phone update for customer %d: %s', $customer_id, $phone));
            }

            // Update partner names if conditions are met
            if (!empty($first_name2) && !empty($last_name2) && 
                ($first_name2 !== $first_name || $last_name2 !== $last_name)) {
                
                // Update partner names if both fields are currently empty, OR if we have a complete new pair
                if ((empty($customer->partner_first) && empty($customer->partner_last)) ||
                    ($customer->partner_first !== $first_name2 || $customer->partner_last !== $last_name2)) {
                    
                    $customer_update_data['partner_first'] = $first_name2;
                    $customer_update_data['partner_last'] = $last_name2;
                    error_log(sprintf('BST GF Customer: Adding partner update for customer %d: %s %s', $customer_id, $first_name2, $last_name2));
                }
            }
            
            // Perform single consolidated update if there's any data to update
            if (!empty($customer_update_data)) {
                error_log('BST GF Customer: Consolidated update data: ' . print_r($customer_update_data, true));
                $update_result = bst_update_customer($customer_id, $customer_update_data);
                if (!$update_result['success']) {
                    error_log('BST GF Customer Consolidated Update Failed: ' . $update_result['error']);
                } else {
                    error_log(sprintf('BST GF Customer: Successfully updated customer %d with consolidated data', $customer_id));
                }
            }

    } else {
        // No existing customer found - determine credit based on how heard first, then source
        
        // Priority 1: High-priority How Heard values that don't check source
        $commission_02_values = [
            'Referred by Bill Kniegge',
            'Went on a previous Blue Strada tour'
        ];
        
        // How Heard values that should check source code for override (social media + YouTube)
        $source_override_values = [
            'Facebook',
            'Instagram',
            'LinkedIn',
            'Twitter/X',
            'YouTube',
            'WhatsApp',
            'Messenger'
        ];
        
        if (trim($how_heard) === 'Referred by Wayne Wilson') {
            $commission_percent = 0.05;
            $commission_reason = 'How Heard (Wayne)';
            $credit = "Wayne";
        } elseif (trim($how_heard) === 'Referred by Claudio Angeletti') {
            $commission_percent = 0.02;
            $commission_reason = 'How Heard (Claudio)';
            $credit = "Claudio";
        } elseif (trim($how_heard) === 'Motor Club') {
            $commission_percent = 0.05;
            $commission_reason = 'How Heard (Motor Club)';
            $credit = "Web";
        } elseif (trim($how_heard) === 'Blue Strada Tours Email List') {
            $commission_percent = 0.05;
            $commission_reason = 'How Heard (BST Email List)';
            $credit = "Web";
        } elseif (trim($how_heard) === 'TripAdvisor') {
            $commission_percent = 0.05;
            $commission_reason = 'How Heard (TripAdvisor)';
            $credit = "Web";
        } elseif (in_array(trim($how_heard), $commission_02_values, true)) {
            $commission_percent = 0.02;
            if (trim($how_heard) === 'Referred by Bill Kniegge') {
                $commission_reason = 'How Heard (Bill)';
            } else { // 'Went on a previous Blue Strada tour'
                $commission_reason = 'How Heard (Previous BST Guest)';
            }
            $credit = "Bill";
        } elseif (in_array(trim($how_heard), $source_override_values, true)) {
            // Priority 2: For social media and YouTube, check source code for override
            if($source) {
                $source_code = sanitize_text_field($source);
                $args = array(
                    'post_type'      => 'source-code',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'meta_query'     => array(
                        array(
                            'key'   => 'code',
                            'value' => $source_code,
                            'compare' => '='
                        )
                    )
                );
                $query = new WP_Query($args);
                if ($query->have_posts()) {
                    $post = $query->posts[0];
                    $credit = get_field('source', $post->ID); // should be Bill, Claudio, Wayne, Web
                }
            } 

            if(!empty($credit)) { 
                // Source code found - use it with How Heard value in reason
                if ($credit === 'Bill') {
                    $commission_percent = 0.02;
                    $commission_reason = "How Heard ({$how_heard}: Bill)";
                } elseif ($credit === 'Claudio') {
                    $commission_percent = 0.02;
                    $commission_reason = "How Heard ({$how_heard}: Claudio)";
                } elseif ($credit === 'Wayne') {
                    $commission_percent = 0.05;
                    $commission_reason = "How Heard ({$how_heard}: Wayne)";
                } elseif ($credit === 'Web') {
                    $commission_percent = 0.05;
                    $commission_reason = "How Heard ({$how_heard})";
                }
            } else {
                // No source match - use How Heard as-is with Web credit
                $commission_percent = 0.05;
                $commission_reason = "How Heard ({$how_heard})";
                $credit = "Web";
            }
        } elseif (empty($how_heard)) {
            // Priority 3: No how heard value (e.g., waiting list) - check source code
            if($source) {
                $source_code = sanitize_text_field($source);
                $args = array(
                    'post_type'      => 'source-code',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'meta_query'     => array(
                        array(
                            'key'   => 'code',
                            'value' => $source_code,
                            'compare' => '='
                        )
                    )
                );
                $query = new WP_Query($args);
                if ($query->have_posts()) {
                    $post = $query->posts[0];
                    $credit = get_field('source', $post->ID); // should be Bill, Claudio, Wayne, Web
                    
                    if ($credit === 'Bill') {
                        $commission_percent = 0.02;
                        $commission_reason = "Source Code: Bill";
                    } elseif ($credit === 'Claudio') {
                        $commission_percent = 0.02;
                        $commission_reason = "Source Code: Claudio";
                    } elseif ($credit === 'Wayne') {
                        $commission_percent = 0.05;
                        $commission_reason = "Source Code: Wayne";
                    } else {
                        $commission_percent = 0.05;
                        $commission_reason = "New Web Customer";
                        $credit = "Web";
                    }
                } else {
                    // No source code match - default to new web customer
                    $commission_percent = 0.05;
                    $commission_reason = "New Web Customer";
                    $credit = "Web";
                }
            } else {
                // No source code provided - default to new web customer
                $commission_percent = 0.05;
                $commission_reason = "New Web Customer";
                $credit = "Web";
            }
        } else {
            // Priority 4: All other How Heard values (including Search engine)
            $commission_percent = 0.05;
            $commission_reason = "How Heard ({$how_heard})";
            $credit = "Web";
        }
        
        // Create a new customer record and use the calculated credit so we can use next time
        $customer_data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'partner_first' => $first_name2,
            'partner_last' => $last_name2,
            'data_source' => $data_source ?? 'Web Booking',
            'credit' => $credit
        );
        
        $create_result = bst_create_customer($customer_data);
        if ($create_result['success']) {
            $customer_id = $create_result['customer_id'];
            error_log(sprintf('BST GF9 Customer created successfully: ID %d', $customer_id));
        } else {
            error_log('BST GF Customer Creation Failed: ' . $create_result['error']);
            $customer_id = null;
        }
    }
    // Ensure commission reason fits the DB schema: booking_commission_reason is VARCHAR(100)
    // GF9 can produce "How Heard ({user input})" strings that exceed this limit.
    if (!empty($commission_reason)) {
        $commission_reason = sanitize_text_field($commission_reason);
        $max_len = 100;
        if (function_exists('mb_substr')) {
            $commission_reason = mb_substr($commission_reason, 0, $max_len);
        } else {
            $commission_reason = substr($commission_reason, 0, $max_len);
        }
        $commission_reason = trim($commission_reason);
    }

    return [$commission_percent, $commission_reason, $customer_id];
}

// Admin handler for importing GF9 CSV entries
add_action('admin_post_bst_import_gf9_csv', 'bst_admin_import_gf9_csv_handler');

function bst_admin_import_gf9_csv_handler() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['gf9_csv_file']) || $_FILES['gf9_csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_redirect(admin_url('admin.php?page=bst-tour-bookings&csv_import=error&message=file_upload_failed'));
        exit;
    }
    
    $file_path = $_FILES['gf9_csv_file']['tmp_name'];
    
    if (!file_exists($file_path)) {
        wp_redirect(admin_url('admin.php?page=bst-tour-bookings&csv_import=error&message=file_not_found'));
        exit;
    }
    
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    $error_details = array();
    
    try {
        // Read CSV file with proper handling for complex data
        $file_handle = fopen($file_path, 'r');
        if (!$file_handle) {
            wp_redirect(admin_url('admin.php?page=bst-tour-bookings&csv_import=error&message=file_read_failed'));
            exit;
        }
        
        // Read header row
        $headers = fgetcsv($file_handle, 0, ',', '"', '"');
        if (!$headers) {
            fclose($file_handle);
            wp_redirect(admin_url('admin.php?page=bst-tour-bookings&csv_import=error&message=no_headers'));
            exit;
        }
        
        $row_index = 0;
        while (($row = fgetcsv($file_handle, 0, ',', '"', '"')) !== FALSE) {
            $row_index++;
            
            if (count($row) !== count($headers)) {
                $error_details[] = "Row " . ($row_index + 1) . ": Column count mismatch. Expected " . count($headers) . ", got " . count($row);
                $errors++;
                continue;
            }
            
            $data = array_combine($headers, $row);
            
            // Get entry ID from CSV
            $entry_id = intval($data['Entry Id'] ?? '');
            if ($entry_id <= 0) {
                $error_details[] = "Row " . ($row_index + 1) . ": Invalid or missing Entry Id: " . ($data['Entry Id'] ?? 'not found');
                $errors++;
                continue;
            }
            
            // Check if entry already exists in form 9
            $existing_entry = GFAPI::get_entry($entry_id);
            if (!is_wp_error($existing_entry) && $existing_entry['form_id'] == 9) {
                $skipped++;
                continue; // Entry already exists
            }
            
            // Map CSV data to GF9 field structure
            $entry_data = bst_map_csv_to_gf9_entry($data);
            if (!$entry_data) {
                $error_details[] = "Row " . ($row_index + 2) . ": Failed to map CSV data to entry structure";
                $errors++;
                continue;
            }
            
            // Add import flag to entry data
            $entry_data['_bst_imported'] = '1';
            
            // Create the entry - GFAPI will assign a new ID first
            $result = GFAPI::add_entry($entry_data);
            if (is_wp_error($result)) {
                $error_details[] = "Row " . ($row_index + 2) . ": GFAPI error: " . $result->get_error_message();
                $errors++;
                continue;
            }
            
            // Now update the entry ID to match the production ID
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'gf_entry',
                array('id' => $entry_id),
                array('id' => $result),
                array('%d'),
                array('%d')
            );
            
            // Update entry meta table IDs
            $wpdb->update(
                $wpdb->prefix . 'gf_entry_meta',
                array('entry_id' => $entry_id),
                array('entry_id' => $result),
                array('%d'),
                array('%d')
            );
            
            $imported++;
        }
        
        fclose($file_handle);
        
        wp_redirect(admin_url("admin.php?page=bst-tour-bookings&csv_import=done&imported=$imported&skipped=$skipped&errors=$errors"));
        
    } catch (Exception $e) {
        wp_redirect(admin_url('admin.php?page=bst-tour-bookings&csv_import=error&message=processing_failed'));
    }
    
    exit;
}

// Map CSV data to GF9 entry structure
function bst_map_csv_to_gf9_entry($data) {
    // Basic entry structure for form 9
    $entry = array(
        'form_id' => 9,
        'date_created' => $data['Entry Date'] ?? current_time('mysql'),
        'date_updated' => $data['Date Updated'] ?? current_time('mysql'),
        'is_starred' => 0,
        'is_read' => 0,
        'ip' => $data['User IP'] ?? '127.0.0.1',
        'source_url' => $data['Source Url'] ?? '',
        'user_agent' => $data['User Agent'] ?? '',
        'payment_status' => $data['Payment Status'] ?? '',
        'payment_amount' => $data['Payment Amount'] ?? '',
        'payment_date' => $data['Payment Date'] ?? '',
        'transaction_id' => $data['Transaction Id'] ?? '',
        'status' => 'active'
    );
    
    // Map CSV columns to GF9 field IDs based on actual CSV headers
    $field_mappings = array(
        // Basic info
        '109' => $data['Source'] ?? '',
        '110' => $data['Referrer'] ?? '',
        
        // Name fields (31 is a Name field with subfields)
        '31.3' => $data['Your Name (Nome)'] ?? '',          // First name
        '31.6' => $data['Your Name (Cognome)'] ?? '',        // Last name
        
        // Contact info - using the actual CSV headers
        '34' => $data['Phone (Raw)'] ?? $data['Phone'] ?? '',
        '33' => $data['Email (Enter Email)'] ?? '',
        
        // Companion info (215 is a Name field with subfields)
        '215.3' => $data['Traveling Companion (First)'] ?? '',  // Companion first name
        '215.6' => $data['Traveling Companion (Last)'] ?? '',   // Companion last name
        
        // Tour selection (IDs only — denormalized Tour/Dates/Package text columns removed from GF)
        '149' => $data['Tour ID'] ?? '',
        '150' => $data['Tour Date ID'] ?? '',
        '151' => $data['Package ID'] ?? '',
        
        // Vehicle info
        '236' => $data['Vehicle 1 ID'] ?? '',
        '237' => $data['Vehicle 2 ID'] ?? '',
        '140' => $data['Vehicle 1'] ?? '',
        '142' => $data['Vehicle 2'] ?? '',
        
        // Preferences and additional fields
        '72' => $data['Sex of the Participant'] ?? '',
        '74' => $data['Sharing Preference'] ?? '',
        '68' => $data['Bed Preference'] ?? '',
        '222' => $data['I would like to request a car with an automatic transmission.'] ?? '',
        
        // Package details
        '200' => $data['Package People'] ?? '',
        '201' => $data['Package Rooms'] ?? '',
        '212' => $data['Package Vehicles'] ?? '',
        '214' => $data['Vehicle Choices'] ?? '',
        '219' => $data['Booking Update ID'] ?? '',
        
        // Pricing - using actual CSV headers
        '143' => $data['Tour Price (Price)'] ?? $data['Tour Price'] ?? '',
        '160' => $data['Net Tour Price'] ?? '',
        '161' => $data['Coupon'] ?? '',
        '177' => $data['Deposit Payment (Price)'] ?? $data['Payment Amount'] ?? '',
        
        // Wire transfer fields
        '209' => $data['Bank Wire Received Date'] ?? '',
        '210' => $data['Bank Wire Received Amount'] ?? '',
        
        // How heard fields
        '101' => $data['How did you hear about us?'] ?? '',
        '102' => $data['Who is the guest that referred you?'] ?? '',
        '104' => $data['Now we are really curious! :) Please, tell us more.'] ?? '',
        '103' => $data['Car or Motorcycle Club (if any)'] ?? '',
        
        // Payment method and credit card type
        '118' => $data['Payment Method'] ?? '',
        '119' => $data['Credit Card (Card Type)'] ?? '',
        
        // Additional fields from CSV
        '211' => $data['Comments'] ?? '', // If this field exists in your form
        
        // Mailing list subscription
        '35' => $data['Subscribe our Mailing List'] ?? '',
        
        // Acceptance checkboxes (these should be set to "Checked" for imports)
        '223' => !empty($data['Acceptance of the Registration Terms (Consenso)']) ? 'Checked' : '',
        '224' => !empty($data['Acceptance of Specific Clauses (Consenso)']) ? 'Checked' : '',
        '225' => !empty($data['Privacy and Cookies Consent (Consenso)']) ? 'Checked' : '',
    );
    
    // Add field mappings to entry
    foreach ($field_mappings as $field_id => $value) {
        if (!empty($value)) {
            $entry[$field_id] = $value;
        }
    }
    
    return $entry;
}

// --- Multi-Currency Support ---

// AJAX handler to reprocess a GF10 finalization entry for a given booking
add_action('wp_ajax_bst_reprocess_gf10', 'bst_ajax_reprocess_gf10');
function bst_ajax_reprocess_gf10() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    if (!check_ajax_referer('bst_tour_bookings_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    $booking_id = intval($_POST['booking_id'] ?? 0);
    if (!$booking_id) {
        wp_send_json_error(array('message' => 'No booking ID provided'));
        return;
    }

    global $wpdb;
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
        $booking_id
    ));

    if (!$booking) {
        wp_send_json_error(array('message' => 'Booking not found'));
        return;
    }

    $finalization_entry_id = intval($booking->finalization_entry_id);
    if (!$finalization_entry_id) {
        wp_send_json_error(array('message' => 'No finalization entry ID set on this booking'));
        return;
    }

    $entry = GFAPI::get_entry($finalization_entry_id);
    if (is_wp_error($entry)) {
        wp_send_json_error(array('message' => 'GF10 entry not found: ' . $entry->get_error_message()));
        return;
    }

    if ($entry['form_id'] != 10) {
        wp_send_json_error(array('message' => 'Entry ' . $finalization_entry_id . ' does not belong to form 10'));
        return;
    }

    $form = GFAPI::get_form(10);
    bst_gf10_process_finalization_logic($entry, $form);

    wp_send_json_success(array('message' => 'GF10 entry ' . $finalization_entry_id . ' reprocessed successfully'));
}

// AJAX endpoint to get tour currency
add_action('wp_ajax_get_tour_currency', 'get_tour_currency');
add_action('wp_ajax_nopriv_get_tour_currency', 'get_tour_currency');
function get_tour_currency() {
    // Verify we have a tour ID
    if (!isset($_POST['tour_id'])) {
        wp_send_json_error('No tour ID provided');
        return;
    }

    $tour_id = intval($_POST['tour_id']);
    
    // Get the currency field from the tour
    $currency = get_field('currency', $tour_id);
    $valid_currencies = array('USD', 'CAD', 'AUD', 'EUR', 'GBP', 'Other');
    
    // Default to EUR if no currency is set or if invalid
    if (empty($currency) || !in_array($currency, $valid_currencies)) {
        $currency = 'EUR';
    }
    
    wp_send_json_success(array('currency' => $currency));
}
