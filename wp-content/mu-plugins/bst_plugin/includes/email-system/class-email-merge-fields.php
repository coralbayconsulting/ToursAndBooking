<?php

/**
 * BST Email Merge Fields Class
 * 
 * Handles merge field processing for email templates
 */
class BST_Email_Merge_Fields {
    
    /**
     * Get all available merge fields organized by category
     */
    public function get_available_fields() {
        return [
            'Custom Links & Actions' => [
                'ConfirmationLink' => 'Booking confirmation page link',
                'ConfirmationQueryString' => 'Query string for confirmation URL',
                'CustBookingDetailsLink' => 'Customer booking details link',
                'BstBookingDetailsLink' => 'BST admin booking details link',
                'AccountantInvoiceLink' => 'Accountant invoice link',
                'BstBookingSubject' => 'BST admin email subject line',
                'BstBookingSummary' => 'BST admin booking summary',
                'CustBookingSubject' => 'Customer email subject line',
                'CustBookingSummary' => 'Customer booking summary',
                'BankWireDiscount' => 'Bank wire discount percentage',
                'BankWireInstructions' => 'Bank wire payment instructions',
                'BstEmail' => 'Blue Strada Tours email address',
                'reservation_link' => 'Link to complete reservation',
                'finalization_link' => 'Link to finalize booking',
                'BstEmailSignature' => 'Email signature with logo and contact info'
            ],
            'Booking Information' => [
                'booking_id' => 'Unique booking ID number',
                'booking_status' => 'Current status of the booking',
                'created_date' => 'Date booking was created',
                'notes' => 'Booking notes and comments'
            ],
            'Guest 1 Information' => [
                'guest1_first_name' => 'First guest first name',
                'guest1_last_name' => 'First guest last name',
                'guest1_full_name' => 'First guest full name',
                'guest1_nickname' => 'First guest nickname',
                'guest1_email' => 'First guest email address',
                'guest1_phone' => 'First guest phone number',
                'guest1_address_line1' => 'First guest address line 1',
                'guest1_address_line2' => 'First guest address line 2',
                'guest1_city' => 'First guest city',
                'guest1_state_province' => 'First guest state/province',
                'guest1_postal_code' => 'First guest postal code',
                'guest1_country' => 'First guest country',
                'guest1_shirt_size' => 'First guest shirt size',
                'guest1_driving_status' => 'First guest driving preference',
                'guest1_travel_details' => 'First guest travel arrangements',
                'guest1_dietary_restrictions' => 'First guest dietary needs',
                'guest1_medical_insurance' => 'First guest medical insurance',
                'guest1_emergency_contact_name' => 'First guest emergency contact name',
                'guest1_emergency_contact_phone' => 'First guest emergency contact phone',
                'guest1_emergency_contact_email' => 'First guest emergency contact email'
            ],
            'Guest 2 Information' => [
                'guest2_first_name' => 'Second guest first name',
                'guest2_last_name' => 'Second guest last name',
                'guest2_full_name' => 'Second guest full name',
                'guest2_nickname' => 'Second guest nickname',
                'guest2_email' => 'Second guest email address',
                'guest2_phone' => 'Second guest phone number',
                'guest2_address_line1' => 'Second guest address line 1',
                'guest2_address_line2' => 'Second guest address line 2',
                'guest2_city' => 'Second guest city',
                'guest2_state_province' => 'Second guest state/province',
                'guest2_postal_code' => 'Second guest postal code',
                'guest2_country' => 'Second guest country',
                'guest2_shirt_size' => 'Second guest shirt size',
                'guest2_driving_status' => 'Second guest driving preference',
                'guest2_travel_details' => 'Second guest travel arrangements',
                'guest2_dietary_restrictions' => 'Second guest dietary needs',
                'guest2_medical_insurance' => 'Second guest medical insurance',
                'guest2_emergency_contact_name' => 'Second guest emergency contact name',
                'guest2_emergency_contact_phone' => 'Second guest emergency contact phone',
                'guest2_emergency_contact_email' => 'Second guest emergency contact email',
                'guest_count' => 'Total number of guests'
            ],
            'Tour Details' => [
                'tour_id' => 'Tour ID number',
                'tour_text' => 'Tour name/description',
                'tour_date_id' => 'Tour date ID',
                'tour_date_text' => 'Formatted tour date',
                'tour_date_start' => 'Tour start date',
                'tour_date_end' => 'Tour end date',
                'tour_package_id' => 'Package ID',
                'tour_package_text' => 'Package name',
                'tour_extension_added' => 'Whether tour extension was added',
                'tour_extension_text' => 'Tour extension title (from Tour ACF when extension offered)',
                'tour_extension_date_text' => 'Tour extension date range (from tour + tour-date, not booking snapshot)',
                'vehicle1' => 'Primary vehicle assignment',
                'vehicle2' => 'Secondary vehicle assignment',
                'participant_sex' => 'Participant gender',
                'sharing_preference' => 'Room sharing preference',
                'bed_preference' => 'Bed type preference',
                'package_people' => 'Number of people in package',
                'package_rooms' => 'Number of rooms',
                'package_vehicles' => 'Number of vehicles',
                'vehicle_choices' => 'Vehicle choice options'
            ],
            'Financial Information' => [
                'tour_price' => 'Base tour price',
                'tour_currency' => 'Tour currency code',
                'net_tour_price' => 'Net tour price',
                'coupon_code' => 'Applied coupon code',
                'coupon_amount' => 'Coupon discount amount',
                'total_paid' => 'Total amount paid',
                'additional_charge' => 'Additional charges',
                'balance_due' => 'Outstanding balance',
                'finalization_due_date' => 'Balance payment due date (YYYY-MM-DD)',
                'payment_discount_amount' => 'Total payment discount',
                'deposit_payment_method' => 'Deposit payment method',
                'deposit_payment_amount' => 'Deposit amount',
                'deposit_payment_date' => 'Deposit payment date',
                'deposit_payment_discount' => 'Deposit payment discount',
                'balance_payment_method' => 'Balance payment method',
                'balance_payment_amount' => 'Balance amount',
                'balance_payment_date' => 'Balance payment date',
                'balance_payment_discount' => 'Balance payment discount',
                'additional_payment_method' => 'Additional payment method',
                'additional_payment_amount' => 'Additional payment amount',
                'additional_payment_date' => 'Additional payment date',
                'additional_payment_discount' => 'Additional payment discount',
                'refund_payment_method' => 'Refund payment method',
                'refund_payment_amount' => 'Refund amount',
                'refund_payment_date' => 'Refund payment date'
            ],
            'Marketing Information' => [
                'how_heard' => 'How customer heard about tour',
                'how_heard_other' => 'Other method specified',
                'motor_club' => 'Motor club membership',
                'source' => 'Booking source',
                'referrer' => 'Referring party'
            ],
            'Site Information' => [
                'site_name' => 'Website name',
                'site_url' => 'Website URL',
                'contact_email' => 'Site contact email',
                'contact_phone' => 'Site contact phone',
                'current_date' => 'Current date',
                'current_year' => 'Current year'
            ]
        ];
    }
    
    /**
     * Process merge fields in text content
     */
    public function process_merge_fields($content, $booking, $extra_fields = []) {
        // Get all field values
        $field_values = $this->get_field_values($booking, $extra_fields);
        
        // Process simple merge fields first {field_name}
        $content = $this->process_simple_merge_fields($content, $field_values);
        
        // Process conditional merge fields {#field_name}...{/field_name}
        $content = $this->process_conditional_merge_fields($content, $field_values);
        
        return $content;
    }
    
    /**
     * Get field values for a booking
     * Public method to allow other classes to access field values
     */
    public function get_field_values($booking, $extra_fields = []) {
        $live_tour_text = $this->get_live_tour_title($booking->tour_id ?? 0);
        $live_tour_date_text = $this->get_live_tour_date_text($booking->tour_date_id ?? 0);
        $live_package_text = $this->get_live_package_name($booking->tour_package_id ?? 0);
        $fields = [
            // Booking Information
            'booking_id' => $booking->id ?? '',
            'booking_status' => $booking->booking_status ?? '',
            'created_date' => $this->format_date($booking->created_date ?? ''),
            'notes' => $booking->notes ?? '',
            
            // Guest 1 Information
            'guest1_first_name' => $booking->guest1_first_name ?? '',
            'guest1_last_name' => $booking->guest1_last_name ?? '',
            'guest1_full_name' => trim(($booking->guest1_first_name ?? '') . ' ' . ($booking->guest1_last_name ?? '')),
            'guest1_nickname' => $booking->guest1_nickname ?? '',
            'guest1_email' => $booking->guest1_email ?? '',
            'guest1_phone' => $booking->guest1_phone ?? '',
            'guest1_address_line1' => $booking->guest1_address_line1 ?? '',
            'guest1_address_line2' => $booking->guest1_address_line2 ?? '',
            'guest1_city' => $booking->guest1_city ?? '',
            'guest1_state_province' => $booking->guest1_state_province ?? '',
            'guest1_postal_code' => $booking->guest1_postal_code ?? '',
            'guest1_country' => $booking->guest1_country ?? '',
            'guest1_shirt_size' => $booking->guest1_shirt_size ?? '',
            'guest1_driving_status' => $booking->guest1_driving_status ?? '',
            'guest1_travel_details' => $booking->guest1_travel_details ?? '',
            'guest1_dietary_restrictions' => $booking->guest1_dietary_restrictions ?? '',
            'guest1_medical_insurance' => $booking->guest1_medical_insurance ?? '',
            'guest1_emergency_contact_name' => $booking->guest1_emergency_contact_name ?? '',
            'guest1_emergency_contact_phone' => $booking->guest1_emergency_contact_phone ?? '',
            'guest1_emergency_contact_email' => $booking->guest1_emergency_contact_email ?? '',
            
            // Guest 2 Information
            'guest2_first_name' => $booking->guest2_first_name ?? '',
            'guest2_last_name' => $booking->guest2_last_name ?? '',
            'guest2_full_name' => trim(($booking->guest2_first_name ?? '') . ' ' . ($booking->guest2_last_name ?? '')),
            'guest2_nickname' => $booking->guest2_nickname ?? '',
            'guest2_email' => $booking->guest2_email ?? '',
            'guest2_phone' => $booking->guest2_phone ?? '',
            'guest2_address_line1' => $booking->guest2_address_line1 ?? '',
            'guest2_address_line2' => $booking->guest2_address_line2 ?? '',
            'guest2_city' => $booking->guest2_city ?? '',
            'guest2_state_province' => $booking->guest2_state_province ?? '',
            'guest2_postal_code' => $booking->guest2_postal_code ?? '',
            'guest2_country' => $booking->guest2_country ?? '',
            'guest2_shirt_size' => $booking->guest2_shirt_size ?? '',
            'guest2_driving_status' => $booking->guest2_driving_status ?? '',
            'guest2_travel_details' => $booking->guest2_travel_details ?? '',
            'guest2_dietary_restrictions' => $booking->guest2_dietary_restrictions ?? '',
            'guest2_medical_insurance' => $booking->guest2_medical_insurance ?? '',
            'guest2_emergency_contact_name' => $booking->guest2_emergency_contact_name ?? '',
            'guest2_emergency_contact_phone' => $booking->guest2_emergency_contact_phone ?? '',
            'guest2_emergency_contact_email' => $booking->guest2_emergency_contact_email ?? '',
            'guest_count' => $this->calculate_guest_count($booking),
            
            // Tour Details
            'tour_id' => $booking->tour_id ?? '',
            'tour_text' => $live_tour_text,
            'tour_date_id' => $booking->tour_date_id ?? '',
            'tour_date_text' => $live_tour_date_text,
            'tour_date_start' => $this->get_tour_date_start($booking),
            'tour_date_end' => $this->get_tour_date_end($booking),
            'tour_package_id' => $booking->tour_package_id ?? '',
            'tour_package_text' => $live_package_text,
            'tour_extension_added' => $this->format_boolean($booking->tour_extension_added ?? false),
            'tour_extension_text' => function_exists( 'bst_live_extension_title_for_tour' ) ? bst_live_extension_title_for_tour( (int) ( $booking->tour_id ?? 0 ) ) : '',
            'tour_extension_date_text' => function_exists( 'bst_live_extension_date_range_for_booking' ) ? bst_live_extension_date_range_for_booking( $booking ) : '',
            'vehicle1' => function_exists( 'bst_booking_vehicle_display_text' ) ? bst_booking_vehicle_display_text( $booking, 1 ) : '',
            'vehicle2' => function_exists( 'bst_booking_vehicle_display_text' ) ? bst_booking_vehicle_display_text( $booking, 2 ) : '',
            'participant_sex' => $booking->participant_sex ?? '',
            'sharing_preference' => $booking->sharing_preference ?? '',
            'bed_preference' => $booking->bed_preference ?? '',
            'package_people' => $booking->package_people ?? '',
            'package_rooms' => $booking->package_rooms ?? '',
            'package_vehicles' => $booking->package_vehicles ?? '',
            'vehicle_choices' => $booking->vehicle_choices ?? '',
            
            // Financial Information
            'tour_price' => $this->format_currency($booking->tour_price ?? 0, $booking->tour_currency ?? 'EUR'),
            'tour_currency' => $booking->tour_currency ?? 'EUR',
            'net_tour_price' => $this->format_currency($booking->net_tour_price ?? 0, $booking->tour_currency ?? 'EUR'),
            'coupon_code' => $booking->coupon_code ?? '',
            'coupon_amount' => $this->format_currency($booking->coupon_amount ?? 0, $booking->tour_currency ?? 'EUR'),
            'total_paid' => $this->format_currency($booking->total_paid ?? 0, $booking->tour_currency ?? 'EUR'),
            'additional_charge' => $this->format_currency($booking->additional_charge ?? 0, $booking->tour_currency ?? 'EUR'),
            'balance_due' => $this->format_currency($booking->balance_due ?? 0, $booking->tour_currency ?? 'EUR'),
            'finalization_due_date' => function_exists('bst_calculate_balance_due_date') ? bst_calculate_balance_due_date($booking) : '',
            'payment_discount_amount' => $this->format_currency($booking->payment_discount_amount ?? 0, $booking->tour_currency ?? 'EUR'),
            'deposit_payment_method' => $booking->deposit_payment_method ?? '',
            'deposit_payment_amount' => $this->format_currency($booking->deposit_payment_amount ?? 0, $booking->tour_currency ?? 'EUR'),
            'deposit_payment_date' => $this->format_date($booking->deposit_payment_date ?? ''),
            'deposit_payment_discount' => $this->format_currency($booking->deposit_payment_discount ?? 0, $booking->tour_currency ?? 'EUR'),
            'balance_payment_method' => $booking->balance_payment_method ?? '',
            'balance_payment_amount' => $this->format_currency($booking->balance_payment_amount ?? 0, $booking->tour_currency ?? 'EUR'),
            'balance_payment_date' => $this->format_date($booking->balance_payment_date ?? ''),
            'balance_payment_discount' => $this->format_currency($booking->balance_payment_discount ?? 0, $booking->tour_currency ?? 'EUR'),
            'additional_payment_method' => $booking->additional_payment_method ?? '',
            'additional_payment_amount' => $this->format_currency($booking->additional_payment_amount ?? 0, $booking->tour_currency ?? 'EUR'),
            'additional_payment_date' => $this->format_date($booking->additional_payment_date ?? ''),
            'additional_payment_discount' => $this->format_currency($booking->additional_payment_discount ?? 0, $booking->tour_currency ?? 'EUR'),
            'refund_payment_method' => $booking->refund_payment_method ?? '',
            'refund_payment_amount' => $this->format_currency($booking->refund_payment_amount ?? 0, $booking->tour_currency ?? 'EUR'),
            'refund_payment_date' => $this->format_date($booking->refund_payment_date ?? ''),
            
            // Marketing Information
            'how_heard' => $booking->how_heard ?? '',
            'how_heard_other' => $booking->how_heard_other ?? '',
            'motor_club' => $booking->motor_club ?? '',
            'source' => $booking->source ?? '',
            'referrer' => $booking->referrer ?? '',
            
            // Legacy/Calculated Fields (for backward compatibility)
            'tour_name' => $live_tour_text,
            'tour_date' => $live_tour_date_text,
            'package_name' => $live_package_text,
            'currency' => $booking->tour_currency ?? 'EUR',
            'currency_symbol' => $this->get_currency_symbol($booking->tour_currency ?? 'EUR'),
            'booking_source' => $booking->source ?? '',
            
            // Links and Actions (Custom Links & Actions)
            
            // Determine which entry_id to use (prioritize booking_entry_id, fallback to finalization_entry_id)
            'ConfirmationLink' => $this->generate_confirmation_link($booking),
            'ConfirmationQueryString' => $this->generate_confirmation_query_string($booking),
            'CustBookingDetailsLink' => $this->generate_customer_details_link($booking),
            'BstBookingDetailsLink' => $this->generate_bst_details_link($booking),
            'AccountantInvoiceLink' => $this->generate_accountant_invoice_link($booking),
            'BstBookingSubject' => $this->generate_bst_subject($booking),
            'BstBookingSummary' => $this->generate_bst_summary($booking),
            'CustBookingSubject' => $this->generate_customer_subject($booking),
            'CustBookingSummary' => $this->generate_customer_summary($booking),
            'BankWireDiscount' => $this->get_bank_wire_discount(),
            'BankWireInstructions' => $this->generate_bank_wire_instructions($booking),
            'BstEmail' => get_option('bst_from_email_address', 'info@bluestradatours.com'),
            'BstEmailSignature' => get_option('bst_email_signature', ''),
            
            // Reservation Link: only show if status is Reserved and no booking_entry_id
            'reservation_link' => ($booking->booking_status === 'Reserved' && empty($booking->booking_entry_id) && function_exists('bst_get_reservation_url')) 
                ? '<a href="' . esc_url(bst_get_reservation_url($booking->id ?? '')) . '" style="color: #0066cc; text-decoration: none;">Complete Your Reservation</a>'
                : '',
            // Finalization Link: only show if status is Booked and no finalization_entry_id
            'finalization_link' => ($booking->booking_status === 'Booked' && empty($booking->finalization_entry_id) && function_exists('bst_get_finalization_url')) 
                ? '<a href="' . esc_url(bst_get_finalization_url($booking->id ?? '')) . '" style="color: #0066cc; text-decoration: none;">Finalize Your Booking</a>'
                : '',
            
            // Site Information
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'contact_email' => get_option('admin_email'),
            'contact_phone' => get_option('bst_contact_phone', ''),
            'current_date' => date_i18n(get_option('date_format')),
            'current_year' => date('Y')
        ];
        
        // Add extra fields
        $fields = array_merge($fields, $extra_fields);
        
        return $fields;
    }
    
    /**
     * Process simple merge fields {field_name}
     */
    private function process_simple_merge_fields($content, $field_values) {
        foreach ($field_values as $field => $value) {
            $content = str_replace('{' . $field . '}', $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Process conditional merge fields
     * Supports:
     * - {#field_name}content{/field_name} - Show if field has value
     * - {#field_name=value}content{/field_name} - Show if field equals value
     * - {#field_name!=value}content{/field_name} - Show if field not equals value
     * - {#field_name>value}content{/field_name} - Show if field greater than value
     * - {#field_name<value}content{/field_name} - Show if field less than value
     * - {#field_name>=value}content{/field_name} - Show if field greater than or equal value
     * - {#field_name<=value}content{/field_name} - Show if field less than or equal value
     */
    private function process_conditional_merge_fields($content, $field_values) {
        // Pattern to match conditional blocks with optional comparison operators
        // {#field_name} or {#field_name=value} or {#field_name!=value} etc.
        $pattern = '/\{#(\w+)((<=|>=|!=|=|<|>)([^}]+))?\}(.*?)\{\/\1\}/s';
        
        return preg_replace_callback($pattern, function($matches) use ($field_values) {
            $field_name = $matches[1];
            $operator = isset($matches[3]) ? $matches[3] : null;
            $compare_value = isset($matches[4]) ? trim($matches[4]) : null;
            $conditional_content = $matches[5];
            
            // Get field value
            $field_value = $field_values[$field_name] ?? '';
            
            // Determine if condition is met
            $condition_met = false;
            
            if ($operator === null) {
                // No operator: just check if field has a value
                $condition_met = !empty($field_value);
            } else {
                // Has operator: perform comparison
                $condition_met = $this->evaluate_condition($field_value, $operator, $compare_value);
            }
            
            if ($condition_met) {
                // Process merge fields within the conditional content
                return $this->process_simple_merge_fields($conditional_content, $field_values);
            }
            
            return ''; // Remove the entire conditional block if condition not met
        }, $content);
    }
    
    /**
     * Evaluate a conditional expression
     */
    private function evaluate_condition($field_value, $operator, $compare_value) {
        // Strip quotes from compare value if present
        $compare_value = trim($compare_value, '"\' ');
        
        // Normalize field value (strip formatting for comparisons)
        $field_value_normalized = strip_tags($field_value);
        $field_value_normalized = html_entity_decode($field_value_normalized);
        $field_value_normalized = trim($field_value_normalized);
        
        switch ($operator) {
            case '=':
                // Case-insensitive string comparison
                return strcasecmp($field_value_normalized, $compare_value) === 0;
                
            case '!=':
                // Case-insensitive string comparison (not equal)
                return strcasecmp($field_value_normalized, $compare_value) !== 0;
                
            case '>':
                // Numeric comparison if both are numeric, otherwise string
                if (is_numeric($field_value_normalized) && is_numeric($compare_value)) {
                    return floatval($field_value_normalized) > floatval($compare_value);
                }
                return strcmp($field_value_normalized, $compare_value) > 0;
                
            case '<':
                // Numeric comparison if both are numeric, otherwise string
                if (is_numeric($field_value_normalized) && is_numeric($compare_value)) {
                    return floatval($field_value_normalized) < floatval($compare_value);
                }
                return strcmp($field_value_normalized, $compare_value) < 0;
                
            case '>=':
                // Numeric comparison if both are numeric, otherwise string
                if (is_numeric($field_value_normalized) && is_numeric($compare_value)) {
                    return floatval($field_value_normalized) >= floatval($compare_value);
                }
                return strcmp($field_value_normalized, $compare_value) >= 0;
                
            case '<=':
                // Numeric comparison if both are numeric, otherwise string
                if (is_numeric($field_value_normalized) && is_numeric($compare_value)) {
                    return floatval($field_value_normalized) <= floatval($compare_value);
                }
                return strcmp($field_value_normalized, $compare_value) <= 0;
                
            default:
                return false;
        }
    }
    
    /**
     * Calculate guest count
     */
    private function calculate_guest_count($booking) {
        $count = 1; // At least guest1
        
        if (!empty($booking->guest2_first_name) || !empty($booking->guest2_last_name)) {
            $count = 2;
        }
        
        return $count;
    }
    
    /**
     * Extract tour time from booking data
     */
    private function extract_tour_time($booking) {
        // Try to get time from tour_date_text or other fields
        $time = '';
        $tour_date_text = $this->get_live_tour_date_text($booking->tour_date_id ?? 0);

        if (!empty($tour_date_text)) {
            // Look for time patterns in the date text
            if (preg_match('/(\d{1,2}:\d{2}(?:\s*(?:AM|PM|am|pm))?)/', $tour_date_text, $matches)) {
                $time = $matches[1];
            }
        }
        
        return $time;
    }
    
    /**
     * Get tour duration from tour post
     */
    private function get_tour_duration($booking) {
        if (empty($booking->tour_id)) {
            return '';
        }
        
        return get_post_meta($booking->tour_id, 'duration', true) ?: '';
    }
    
    /**
     * Extract package name from package text
     */
    private function extract_package_name($package_text) {
        if (empty($package_text)) {
            return '';
        }
        
        // Remove price information from package text
        $name = preg_replace('/\s*-\s*[€$£]\d+[\d,.\s]*/', '', $package_text);
        $name = preg_replace('/\s*\(\s*[€$£]\d+[\d,.\s]*\)/', '', $name);
        
        return trim($name);
    }
    
    /**
     * Get package description
     */
    private function get_package_description($booking) {
        if (empty($booking->tour_package_id)) {
            return '';
        }
        
        // Extract package ID from tour_package_id field (format might be "id|other_data")
        $package_id = explode('|', $booking->tour_package_id)[0];
        
        return get_post_meta($package_id, 'description', true) ?: '';
    }
    
    /**
     * Get meeting location
     */
    private function get_meeting_location($booking) {
        if (empty($booking->tour_id)) {
            return '';
        }
        
        return get_post_meta($booking->tour_id, 'meeting_location', true) ?: '';
    }
    
    /**
     * Format boolean value
     */
    private function format_boolean($value) {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        if (is_numeric($value)) {
            return intval($value) === 1 ? 'Yes' : 'No';
        }
        
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            return in_array($lower, ['1', 'true', 'yes', 'on']) ? 'Yes' : 'No';
        }
        
        return 'No';
    }
    
    /**
     * Format currency amount
     */
    private function format_currency($amount, $currency) {
        if (function_exists('bst_format_currency')) {
            return bst_format_currency($amount, $currency);
        }
        
        // Fallback formatting
        $symbol = $this->get_currency_symbol($currency);
        return $symbol . number_format(floatval($amount), 2);
    }
    
    /**
     * Get currency symbol
     */
    private function get_currency_symbol($currency) {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'JPY' => '¥'
        ];
        
        return $symbols[$currency] ?? $currency;
    }
    
    /**
     * Format date
     */
    private function format_date($date_string) {
        if (empty($date_string) || $date_string === '0000-00-00' || $date_string === '0000-00-00 00:00:00') {
            return '';
        }
        
        $timestamp = strtotime($date_string);
        
        if ($timestamp === false) {
            return $date_string; // Return as-is if can't parse
        }
        
        return date_i18n(get_option('date_format'), $timestamp);
    }
    
    /**
     * Generate booking link (reservation/finalization link)
     */
    private function generate_booking_link($booking_id) {
        if (empty($booking_id)) {
            return '';
        }
        
        return admin_url('admin.php?page=edit_booking&id=' . intval($booking_id));
    }
    
    /**
     * Format booking name exactly like booking list
     * Uses the same logic from tour-bookings-list.php lines 920-938
     */
    private function format_booking_name($booking) {
        $guest1_first = trim($booking->guest1_first_name ?? '');
        $guest1_last = trim($booking->guest1_last_name ?? '');
        $guest2_first = trim($booking->guest2_first_name ?? '');
        $guest2_last = trim($booking->guest2_last_name ?? '');
        
        // If no guest2 name, display guest1 only
        if (empty($guest2_first)) {
            return $guest1_first . ' ' . $guest1_last;
        } else {
            // Guest2 exists - check if last names are same/blank
            if (empty($guest2_last) || $guest1_last === $guest2_last) {
                // Same or blank last name: "First1 & First2 Last1"
                return $guest1_first . ' & ' . $guest2_first . ' ' . $guest1_last;
            } else {
                // Different last names: "First1 Last1 & First2 Last2"
                return $guest1_first . ' ' . $guest1_last . ' & ' . $guest2_first . ' ' . $guest2_last;
            }
        }
    }
    
    /**
     * Format individual guest display name
     */
    private function format_guest_display_name($booking, $guest_number) {
        if ($guest_number == 1) {
            $first = trim($booking->guest1_first_name ?? '');
            $last = trim($booking->guest1_last_name ?? '');
        } else {
            $first = trim($booking->guest2_first_name ?? '');
            $last = trim($booking->guest2_last_name ?? '');
        }
        
        return trim($first . ' ' . $last);
    }

    private function get_live_tour_title($tour_id) {
        $tour_id = intval($tour_id);
        if ($tour_id <= 0) {
            return '';
        }
        $p = get_post($tour_id);
        return ($p && $p->post_type === 'tour') ? (string) $p->post_title : '';
    }

    private function get_live_tour_date_text($tour_date_id) {
        $tour_date_id = intval($tour_date_id);
        if ($tour_date_id <= 0) {
            return '';
        }
        $p = get_post($tour_date_id);
        if (!$p || $p->post_type !== 'tour-date') {
            return '';
        }
        $start_date = get_post_meta($tour_date_id, 'start_date', true);
        $end_date = get_post_meta($tour_date_id, 'end_date', true);
        if ($start_date && $end_date) {
            return (date('M', strtotime($start_date)) == date('M', strtotime($end_date)))
                ? date('j', strtotime($start_date)) . '-' . date('j M Y', strtotime($end_date))
                : date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
        }
        if ($start_date) {
            return date('j M Y', strtotime($start_date));
        }
        return (string) $p->post_title;
    }

    private function get_live_package_name($package_id) {
        $package_id = intval($package_id);
        if ($package_id <= 0) {
            return '';
        }
        return (string) get_option('bst_package_' . $package_id . '_name', '');
    }
    
    /**
     * Format tour full display exactly like booking list
     * Uses the same logic from tour-bookings-list.php lines 945-969
     */
    private function format_tour_full_display($booking) {
        $tour_label = $this->get_live_tour_title($booking->tour_id ?? 0);
        $tour_date_text = $this->get_live_tour_date_text($booking->tour_date_id ?? 0);
        $package_text = $this->get_live_package_name($booking->tour_package_id ?? 0);
        $tour_date_id = $booking->tour_date_id ?? '';
        $paren = '';
        $date_label = $tour_date_text !== '' ? $tour_date_text : $tour_date_id;
        if ($date_label) {
            $paren = $date_label;
        }
        
        return $tour_label . ($paren ? ' (' . $paren . ')' : '') . ($package_text ? ' - ' . $package_text : '');
    }

    /**
     * Calculate deposit required (same logic as fast processing method)
     */
    private function calculate_deposit_required($booking) {
        // If there's a deposit payment amount, use that
        if (!empty($booking->deposit_payment_amount) && $booking->deposit_payment_amount > 0) {
            return number_format($booking->deposit_payment_amount, 2);
        }
        
        // Otherwise calculate 15% of net tour price
        $net_tour_price = $booking->net_tour_price ?? 0;
        $deposit = $net_tour_price * 0.15;
        
        return number_format($deposit, 2);
    }

    /**
     * Calculate balance after deposit (same logic as fast processing method)
     */
    private function calculate_balance_after_deposit($booking) {
        // If there's a balance payment amount, use that
        if (!empty($booking->balance_payment_amount) && $booking->balance_payment_amount > 0) {
            return number_format($booking->balance_payment_amount, 2);
        }
        
        // Otherwise calculate net tour price minus deposit required
        $net_tour_price = $booking->net_tour_price ?? 0;
        $deposit_required = $this->get_deposit_amount($booking); // Get actual number, not formatted
        $balance = $net_tour_price - $deposit_required;
        
        return number_format($balance, 2);
    }

    /**
     * Helper method to get deposit amount as number for calculations
     */
    private function get_deposit_amount($booking) {
        if (!empty($booking->deposit_payment_amount) && $booking->deposit_payment_amount > 0) {
            return $booking->deposit_payment_amount;
        }
        
        $net_tour_price = $booking->net_tour_price ?? 0;
        return $net_tour_price * 0.15;
    }
    
    /**
     * Generate confirmation link
     */
    private function generate_confirmation_link($booking) {
        $entry_id = $booking->booking_entry_id ?? $booking->finalization_entry_id ?? null;
        if (empty($entry_id) || !function_exists('bst_encode_booking_id')) {
            return '';
        }
        
        $encoded_eid = bst_encode_booking_id($entry_id);
        $url = site_url('/bookingconfirmation/') . '?eid=' . $encoded_eid;
        return '<a href="' . esc_url($url) . '" style="color: #0066cc; text-decoration: none;">View Booking Confirmation</a>';
    }
    
    /**
     * Generate confirmation query string
     */
    private function generate_confirmation_query_string($booking) {
        $entry_id = $booking->booking_entry_id ?? $booking->finalization_entry_id ?? null;
        if (empty($entry_id) || !function_exists('bst_encode_booking_id')) {
            return '';
        }
        
        $encoded_eid = bst_encode_booking_id($entry_id);
        return 'eid=' . $encoded_eid;
    }
    
    /**
     * Generate customer booking details link
     */
    private function generate_customer_details_link($booking) {
        $entry_id = $booking->booking_entry_id ?? $booking->finalization_entry_id ?? null;
        if (empty($entry_id) || !function_exists('bst_encode_booking_id')) {
            return '';
        }
        
        $encoded_eid = bst_encode_booking_id($entry_id);
        $url = site_url('/bookingdetails/') . '?eid=' . $encoded_eid;
        return '<a href="' . esc_url($url) . '" style="color: #0066cc; text-decoration: none;">View Booking Details</a>';
    }
    
    /**
     * Generate BST admin booking details link
     */
    private function generate_bst_details_link($booking) {
        $entry_id = $booking->booking_entry_id ?? $booking->finalization_entry_id ?? null;
        if (empty($entry_id) || !function_exists('bst_encode_booking_id')) {
            return '';
        }
        
        $encoded_eid = bst_encode_booking_id($entry_id);
        $url = site_url('/bookingdetails/') . '?eid=' . $encoded_eid . '&type=bst';
        return '<a href="' . esc_url($url) . '" style="color: #0066cc; text-decoration: none;">View Booking Details</a>';
    }
    
    /**
     * Generate accountant invoice link (only for finalized bookings)
     */
    private function generate_accountant_invoice_link($booking) {
        // Only generate if booking has finalization_entry_id
        if (empty($booking->finalization_entry_id) || !function_exists('bst_encode_booking_id')) {
            return '';
        }
        
        $encoded_eid = bst_encode_booking_id($booking->finalization_entry_id);
        $url = site_url('/bookinginvoice/') . '?eid=' . $encoded_eid;
        return '<a href="' . esc_url($url) . '" style="color: #0066cc; text-decoration: none;">Visualizza fattura pro forma</a>';
    }
    
    /**
     * Generate BST admin email subject
     */
    private function generate_bst_subject($booking) {
        $guest_name = $this->format_booking_name($booking);
        $tour_text = $this->get_live_tour_title($booking->tour_id ?? 0);
        $extension_added = $booking->tour_extension_added ?? false;
        
        // Determine if booked or finalized based on finalization_entry_id
        $action_word = !empty($booking->finalization_entry_id) ? 'finalized' : 'booked';
        
        $subject = $guest_name . ' ' . $action_word . ' the ' . $tour_text . ' tour';
        if ($extension_added == 1 || $extension_added === true) {
            $subject .= ' with extension';
        }
        $subject .= '!';
        
        return $subject;
    }
    
    /**
     * Generate BST admin booking summary (HTML)
     */
    private function generate_bst_summary($booking) {
        try {
            if (!function_exists('bst_generate_booking_summary_html') || !function_exists('bst_encode_booking_id')) {
                return '';
            }
            
            $entry_id = $booking->booking_entry_id ?? $booking->finalization_entry_id ?? null;
            if (empty($entry_id)) {
                return '';
            }
            
            $encoded_eid = bst_encode_booking_id($entry_id);
            $summary_html = bst_generate_booking_summary_html($entry_id, false, 'admin', $encoded_eid, false);
            
            return '<div style="font-family: Arial, sans-serif; max-width: 600px;">' . $summary_html . '</div>';
        } catch (Exception $e) {
            error_log('Error generating BST summary: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Generate customer email subject
     */
    private function generate_customer_subject($booking) {
        $tour_text = $this->get_live_tour_title($booking->tour_id ?? 0);
        $extension_added = $booking->tour_extension_added ?? false;
        
        // Determine if booked or finalized based on finalization_entry_id
        $action_word = !empty($booking->finalization_entry_id) ? 'finalized' : 'booked';
        
        $subject = 'You ' . $action_word . ' the ' . $tour_text . ' tour';
        if ($extension_added == 1 || $extension_added === true) {
            $subject .= ' with extension';
        }
        $subject .= '!';
        
        return $subject;
    }
    
    /**
     * Generate customer booking summary (HTML)
     */
    private function generate_customer_summary($booking) {
        try {
            if (!function_exists('bst_generate_booking_summary_html') || !function_exists('bst_encode_booking_id')) {
                return '';
            }
            
            $entry_id = $booking->booking_entry_id ?? $booking->finalization_entry_id ?? null;
            if (empty($entry_id)) {
                return '';
            }
            
            $encoded_eid = bst_encode_booking_id($entry_id);
            $summary_html = bst_generate_booking_summary_html($entry_id, false, 'customer', $encoded_eid, false);
            
            return '<div style="font-family: Arial, sans-serif; max-width: 600px;">' . $summary_html . '</div>';
        } catch (Exception $e) {
            error_log('Error generating customer summary: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Get bank wire discount percentage
     */
    private function get_bank_wire_discount() {
        $discount = get_option('bst_bank_wire_discount', '');
        if (empty($discount)) {
            return '';
        }
        return rtrim(rtrim(number_format($discount, 2, '.', ''), '0'), '.') . '%';
    }
    
    /**
     * Generate bank wire instructions
     */
    private function generate_bank_wire_instructions($booking) {
        // This would need to be customized based on your bank wire instructions
        // For now, return a placeholder
        return 'Bank wire payment instructions will be provided upon request.';
    }
    
    /**
     * Get tour start date from tour date post
     */
    private function get_tour_date_start($booking) {
        // Check if booking already has tour_date_start (for test data)
        if (!empty($booking->tour_date_start)) {
            return $this->format_date($booking->tour_date_start);
        }
        
        $tour_date_id = $booking->tour_date_id ?? '';
        
        if (empty($tour_date_id)) {
            return '';
        }
        
        // Handle pipe-separated values
        $parts = explode('|', $tour_date_id);
        $tour_date_id_val = trim($parts[0]);
        
        // Get start date from tour-date post
        $start_date = get_post_meta($tour_date_id_val, 'start_date', true);
        
        if (empty($start_date)) {
            return '';
        }
        
        return $this->format_date($start_date);
    }
    
    /**
     * Get tour end date from tour date post
     */
    private function get_tour_date_end($booking) {
        // Check if booking already has tour_date_end (for test data)
        if (!empty($booking->tour_date_end)) {
            return $this->format_date($booking->tour_date_end);
        }
        
        $tour_date_id = $booking->tour_date_id ?? '';
        
        if (empty($tour_date_id)) {
            return '';
        }
        
        // Handle pipe-separated values
        $parts = explode('|', $tour_date_id);
        $tour_date_id_val = trim($parts[0]);
        
        // Get end date from tour-date post
        $end_date = get_post_meta($tour_date_id_val, 'end_date', true);
        
        if (empty($end_date)) {
            return '';
        }
        
        return $this->format_date($end_date);
    }
}
