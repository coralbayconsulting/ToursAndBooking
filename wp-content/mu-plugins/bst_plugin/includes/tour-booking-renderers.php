<?php
/**
 * Tour Booking Tile Rendering Functions
 * 
 * This file contains all the rendering functions for tour booking tiles.
 * It's separated from the database actions to keep concerns separated.
 * 
 * @package BST_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if ( ! function_exists( 'bst_booking_pending_payment_note_html' ) && defined( 'BST_PLUGIN_DIR' ) ) {
    require_once BST_PLUGIN_DIR . 'includes/booking-payment-status.php';
}
if ( ! function_exists( 'bst_booking_vehicle_display_text' ) && defined( 'BST_PLUGIN_DIR' ) ) {
    require_once BST_PLUGIN_DIR . 'includes/vehicle-helpers.php';
}

/**
 * Helper functions for tile rendering (shared utilities)
 */

// Helper function to format phone numbers in international style
function bst_format_phone_international($phone) {
    if (empty($phone)) {
        return '';
    }
    
    // Remove all non-numeric characters except the leading +
    $hasPlus = (strpos($phone, '+') === 0);
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    
    // If it already has proper formatting (contains spaces or dashes), return as is
    if ($hasPlus && (strpos($phone, ' ') !== false || strpos($phone, '-') !== false || strpos($phone, '(') !== false)) {
        return $phone;
    }
    
    // Handle different length patterns
    $length = strlen($cleaned);
    
    if ($length == 10) {
        // US/Canada format: +1 (XXX) XXX-XXXX
        return '+1 (' . substr($cleaned, 0, 3) . ') ' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6);
    } elseif ($length == 11 && substr($cleaned, 0, 1) == '1') {
        // US/Canada with leading 1: +1 (XXX) XXX-XXXX
        return '+1 (' . substr($cleaned, 1, 3) . ') ' . substr($cleaned, 4, 3) . '-' . substr($cleaned, 7);
    } elseif ($length == 12 && substr($cleaned, 0, 2) == '44') {
        // UK format: +44 20 XXXX XXXX or +44 1XXX XXX XXX
        $area = substr($cleaned, 2, 2);
        if ($area == '20') {
            // London: +44 20 XXXX XXXX
            return '+44 20 ' . substr($cleaned, 4, 4) . ' ' . substr($cleaned, 8);
        } else {
            // Other UK: +44 1XXX XXX XXX
            return '+44 ' . substr($cleaned, 2, 4) . ' ' . substr($cleaned, 6, 3) . ' ' . substr($cleaned, 9);
        }
    } elseif ($length == 12 && substr($cleaned, 0, 2) == '33') {
        // France format: +33 X XX XX XX XX
        return '+33 ' . substr($cleaned, 2, 1) . ' ' . substr($cleaned, 3, 2) . ' ' . substr($cleaned, 5, 2) . ' ' . substr($cleaned, 7, 2) . ' ' . substr($cleaned, 9);
    } elseif ($length == 12 && substr($cleaned, 0, 2) == '49') {
        // Germany format: +49 XXX XXXXXXX
        return '+49 ' . substr($cleaned, 2, 3) . ' ' . substr($cleaned, 5);
    } elseif ($length >= 10 && $length <= 15) {
        // Generic international format
        if ($length <= 11) {
            $country = substr($cleaned, 0, 1);
            $remaining = substr($cleaned, 1);
        } elseif ($length <= 13) {
            $country = substr($cleaned, 0, 2);
            $remaining = substr($cleaned, 2);
        } else {
            $country = substr($cleaned, 0, 3);
            $remaining = substr($cleaned, 3);
        }
        
        // Format remaining digits in groups of 3-4
        $formatted = '+' . $country . ' ';
        $remaining_length = strlen($remaining);
        
        if ($remaining_length >= 9) {
            // Long numbers: XXX XXX XXX
            $formatted .= substr($remaining, 0, 3) . ' ' . substr($remaining, 3, 3) . ' ' . substr($remaining, 6);
        } elseif ($remaining_length >= 6) {
            // Medium numbers: XXX XXX
            $formatted .= substr($remaining, 0, 3) . ' ' . substr($remaining, 3);
        } else {
            // Short numbers: as-is
            $formatted .= $remaining;
        }
        
        return $formatted;
    }
    
    // If we can't determine format, just add + prefix if it looks like international
    if ($length > 7) {
        return '+' . $cleaned;
    }
    
    // Return original if we can't format it
    return $phone;
}

/**
 * Format name from first name, last name, and optional nickname
 */
function bst_format_name($first_name, $last_name, $nickname = '') {
    $name_parts = array();
    if (!empty($first_name)) $name_parts[] = trim($first_name);
    if (!empty($last_name)) $name_parts[] = trim($last_name);
    $full_name = implode(' ', $name_parts);
    
    if (!empty($nickname)) {
        $full_name .= ' (' . trim($nickname) . ')';
    }
    
    return $full_name;
}

/**
 * Format currency with consistent symbols
 */
function bst_format_currency($amount, $currency = 'EUR') {
    if (!is_numeric($amount) && $amount !== 0 && $amount !== '0') return '';
    
    $symbol = ($currency === 'USD') ? '$' : '€';
    return $symbol . number_format(floatval($amount), 2);
}

/**
 * Format address from separate fields
 */
function bst_format_address($address_line1, $address_line2, $city, $state_province, $postal_code, $country) {
    $address_parts = array();
    
    // Add address lines
    if (!empty($address_line1)) $address_parts[] = esc_html(trim($address_line1));
    if (!empty($address_line2)) $address_parts[] = esc_html(trim($address_line2));
    
    // Add city, state, postal
    $city_line = array();
    if (!empty($city)) $city_line[] = esc_html(trim($city));
    if (!empty($state_province)) $city_line[] = esc_html(trim($state_province));
    if (!empty($postal_code)) $city_line[] = esc_html(trim($postal_code));
    
    if (!empty($city_line)) {
        $address_parts[] = implode(', ', $city_line);
    }
    
    // Add country
    if (!empty($country)) $address_parts[] = esc_html(trim($country));
    
    return implode('<br>', $address_parts);
}

/**
 * Format text info with line breaks
 */
function bst_format_text_info($text) {
    if (empty($text)) return '';
    
    // Clean up the text
    $formatted = trim($text);
    $formatted = nl2br(esc_html($formatted));
    
    return $formatted;
}

/**
 * Convert shirt size values to display text
 */
function bst_get_shirt_size_display($size_value) {
    if (empty($size_value)) return '';
    
    // Map of shirt size values to display text (matching the dropdown options)
    $size_map = array(
        'XS' => 'XS (Men)',
        'S' => 'S (Men)', 
        'M' => 'M (Men)',
        'L' => 'L (Men)',
        'XL' => 'XL (Men)',
        'XXL' => 'XXL (Men)',
        '3XL' => '3XL (Men)',
        'XS-L' => 'XS (Ladies)',
        'S-L' => 'S (Ladies)',
        'M-L' => 'M (Ladies)',
        'L-L' => 'L (Ladies)',
        'XL-L' => 'XL (Ladies)',
        'XXL-L' => 'XXL (Ladies)',
        '3XL-L' => '3XL (Ladies)'
    );
    
    return isset($size_map[$size_value]) ? $size_map[$size_value] : $size_value;
}

/**
 * Format emergency contact from separate fields
 */
function bst_format_emergency_contact_from_fields($name, $phone, $email) {
    $contact_parts = array();
    
    if (!empty($name)) $contact_parts[] = trim($name);
    if (!empty($phone)) $contact_parts[] = bst_format_phone_international(trim($phone));  
    if (!empty($email)) $contact_parts[] = trim($email);
    
    return implode(', ', $contact_parts);
}

/**
 * Get source code title by source code
 */
function bst_get_source_code_title($source_code) {
    if (empty($source_code)) {
        return '';
    }
    
    $args = array(
        'post_type'      => 'source-code',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'   => 'code',
                'value' => sanitize_text_field($source_code),
                'compare' => '='
            )
        )
    );
    
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        $post = $query->posts[0];
        return $post->post_title;
    }
    
    return '';
}

/**
 * Shared utility functions for tile rendering
 */

/**
 * Render a view item with conditional display
 * @param string $label The label text
 * @param string|null $value The value to display
 * @param bool $escape Whether to escape the value (default true)
 * @return string HTML or empty string if value is empty
 */
function bst_render_view_item_conditional($label, $value, $escape = true) {
    if (empty($value)) {
        return '';
    }
    
    $escaped_value = $escape ? esc_html($value) : $value;
    return '<div class="view-item"><strong>' . esc_html($label) . ':</strong> ' . $escaped_value . '</div>';
}

/**
 * Render a view item (always displays, even if value is empty)
 * @param string $label The label text
 * @param string $value The value to display
 * @param bool $escape Whether to escape the value (default true)
 * @return string HTML
 */
function bst_render_view_item($label, $value, $escape = true) {
    $escaped_value = $escape ? esc_html($value) : $value;
    return '<div class="view-item"><strong>' . esc_html($label) . ':</strong> ' . $escaped_value . '</div>';
}

/**
 * Render the tile view content for marketing information
 * @param object $booking The booking object
 * @return string HTML content
 */
function bst_render_marketing_tile_content($booking) {
    $html = '<div class="tile-view-content">';
    
    // How Heard
    $html .= bst_render_view_item_conditional('How Heard', $booking->how_heard ?? '');
    
    // Please Specify
    $html .= bst_render_view_item_conditional('Please Specify', $booking->how_heard_other ?? '');
    
    // Source with special handling for source codes
    if (!empty($booking->source)) {
        $source_title = bst_get_source_code_title($booking->source);
        $source_display = !empty($source_title) 
            ? $source_title . ' (' . $booking->source . ')'
            : $booking->source;
        $html .= bst_render_view_item('Source', $source_display);
    }
    
    // Referrer
    $html .= bst_render_view_item_conditional('Referrer', $booking->referrer ?? '');
    
    // Motor Club
    $html .= bst_render_view_item_conditional('Motor Club', $booking->motor_club ?? '');
    
    $html .= '</div>';
    return $html;
}

/**
 * Render the tile view content for guest information
 * @param object $booking The booking object
 * @param int $guest_number 1 or 2
 * @return string HTML content
 */
function bst_render_guest_tile_content($booking, $guest_number) {
    $prefix = 'guest' . $guest_number . '_';
    $html = '<div class="tile-view-content">';
    
    // Check if we have any guest data
    $has_guest_data = !empty($booking->{$prefix . 'first_name'}) || 
                      !empty($booking->{$prefix . 'last_name'}) || 
                      !empty($booking->{$prefix . 'email'}) || 
                      !empty($booking->{$prefix . 'phone'});
    
    // If no guest data and this is guest2, show appropriate message
    if (!$has_guest_data && $guest_number == 2) {
        return '<div class="tile-view-content"><div class="view-item empty-info">Click the edit button to add second guest information</div></div>';
    }
    
    // Basic information
    $name = bst_format_name(
        $booking->{$prefix . 'first_name'} ?? '',
        $booking->{$prefix . 'last_name'} ?? '',
        $booking->{$prefix . 'nickname'} ?? ''
    );
    $html .= bst_render_view_item('Name', $name);
    $html .= bst_render_view_item_conditional('Email', $booking->{$prefix . 'email'} ?? '');
    $html .= bst_render_view_item_conditional('Phone', bst_format_phone_international($booking->{$prefix . 'phone'} ?? ''));
    
    // Address
    $address = bst_format_address(
        $booking->{$prefix . 'address_line1'} ?? '',
        $booking->{$prefix . 'address_line2'} ?? '',
        $booking->{$prefix . 'city'} ?? '',
        $booking->{$prefix . 'state_province'} ?? '',
        $booking->{$prefix . 'postal_code'} ?? '',
        $booking->{$prefix . 'country'} ?? ''
    );
    if (!empty($address)) {
        $html .= '<div class="view-item"><strong>Address:</strong><span class="address-block">' . $address . '</span></div>';
    }
    
    // Additional Details section - only show header if there are details to display
    $additional_details_html = '';
    
    // Collect all additional details
    $additional_details_html .= bst_render_view_item_conditional('Shirt Size', bst_get_shirt_size_display($booking->{$prefix . 'shirt_size'} ?? ''));
    $additional_details_html .= bst_render_view_item_conditional('Driving Status', $booking->{$prefix . 'driving_status'} ?? '');
    
    // Food Intolerances with line breaks
    if (!empty($booking->{$prefix . 'dietary_restrictions'})) {
        $dietary = nl2br(esc_html($booking->{$prefix . 'dietary_restrictions'}));
        $additional_details_html .= '<div class="view-item"><strong>Food Intolerances:</strong> ' . $dietary . '</div>';
    }
    
    // Medical Insurance
    if (!empty($booking->{$prefix . 'medical_insurance'})) {
        $medical = bst_format_text_info($booking->{$prefix . 'medical_insurance'});
        $additional_details_html .= '<div class="view-item"><strong>Medical Insurance:</strong> ' . $medical . '</div>';
    }
    
    // Emergency Contact
    $emergency_display = bst_format_emergency_contact_from_fields(
        $booking->{$prefix . 'emergency_contact_name'} ?? '',
        $booking->{$prefix . 'emergency_contact_phone'} ?? '',
        $booking->{$prefix . 'emergency_contact_email'} ?? ''
    );
    if (!empty($emergency_display)) {
        $additional_details_html .= '<div class="view-item"><strong>Emergency Contact:</strong> <span class="emergency-contact-simple">' . esc_html($emergency_display) . '</span></div>';
    }
    
    // Travel Details
    if (!empty($booking->{$prefix . 'travel_details'})) {
        $travel = nl2br(esc_html($booking->{$prefix . 'travel_details'}));
        $additional_details_html .= '<div class="view-item"><strong>Travel Details:</strong> <span class="travel-details-text">' . $travel . '</span></div>';
    }
    
    // Only add the header and details if there are any details to show
    if (!empty($additional_details_html)) {
        $html .= '<h4 class="tile-subsection-header">Additional Details</h4>';
        $html .= $additional_details_html;
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Render the tile view content for notes
 * @param object $booking The booking object
 * @return string HTML content
 */
function bst_render_notes_tile_content($booking) {
    $notes = $booking->notes ?? 'No notes available';
    return '<div class="tile-view-content"><div class="view-item"><div class="notes-display">' . esc_html($notes) . '</div></div></div>';
}

/**
 * Render the tile view content for customer information
 * @param object $booking The booking object
 * @return string HTML content
 */
function bst_render_customer_tile_content($booking) {
    global $wpdb;
    
    $html = '<div class="tile-view-content">';
    
    // Customer display with link
    $html .= '<div class="view-item"><strong>Customer:</strong>';
    
    if (!empty($booking->customer_id)) {
        $cust = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bst_customers WHERE id = %d", $booking->customer_id));
        if ($cust) {
            $html .= '<a href="' . esc_url(admin_url('admin.php?page=bst-plugin-customer-form&action=edit&id=' . $cust->id)) . '" target="_blank">';
            $html .= esc_html("#{$cust->id} - {$cust->first_name} {$cust->last_name} ({$cust->email})");
            $html .= '</a>';
        } else {
            $html .= esc_html($booking->customer_id);
        }
    } else {
        $html .= '<em>None</em>';
    }
    
    $html .= '</div>';
    
    // Update customer button
    if (!empty($booking->customer_id)) {
        $html .= '<button type="button" id="update-customer-from-booking" class="btn btn-secondary" 
                style="margin-top: 15px;"
                data-booking-id="' . esc_attr($booking->id) . '"
                data-customer-id="' . esc_attr($booking->customer_id) . '">
            <i class="fas fa-sync-alt"></i> Update Customer from Booking
        </button>';
        $html .= '<div style="margin-top: 5px;"><small class="text-muted">Update customer record from booking data</small></div>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Render the tile view content for gravity forms information
 * @param object $booking The booking object
 * @return string HTML content
 */
function bst_render_gravity_forms_tile_content($booking) {
    $html = '<div class="tile-view-content">';
    
    // Booking ID
    $html .= '<div class="view-item"><strong>Booking ID:</strong> ';
    if (!empty($booking->booking_entry_id)) {
        $html .= '<a href="' . esc_url(admin_url('admin.php?page=gf_entries&view=entry&id=9&lid=' . $booking->booking_entry_id)) . '" target="_blank">';
        $html .= esc_html($booking->booking_entry_id);
        $html .= '</a>';
    } else {
        $html .= '-';
    }
    $html .= '</div>';
    
    // Finalization ID
    $html .= '<div class="view-item"><strong>Finalization ID:</strong> ';
    if (!empty($booking->finalization_entry_id)) {
        $html .= '<a href="' . esc_url(admin_url('admin.php?page=gf_entries&view=entry&id=10&lid=' . $booking->finalization_entry_id)) . '" target="_blank">';
        $html .= esc_html($booking->finalization_entry_id);
        $html .= '</a>';
        $html .= ' &nbsp;<button type="button" class="reprocess-gf10-btn" style="background:#8b5e00;color:white;border:none;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:12px;vertical-align:middle;">Reprocess</button>';
    } else {
        $html .= '-';
    }
    $html .= '</div>';
    
    // Additional Payment ID
    $html .= '<div class="view-item"><strong>Add\'l Pmt ID:</strong> ';
    if (!empty($booking->additional_payment_entry_id)) {
        // No form exists yet, just display the ID as text
        $html .= esc_html($booking->additional_payment_entry_id);
    } else {
        $html .= '-';
    }
    $html .= '</div>';
    
    $html .= '</div>';
    return $html;
}

/**
 * Render the tile view content for administrative information
 * @param object $booking The booking object
 * @return string HTML content
 */
function bst_render_administrative_tile_content($booking) {
    $html = '<div class="tile-view-content">';
    
    $html .= bst_render_view_item('Booking Status', $booking->booking_status ?? '');
    $html .= bst_render_view_item('Booking Method', $booking->booking_method ?? '');
    
    // Commission (convert from decimal to percentage)
    $commission_percent = ($booking->booking_commission_percent ?? 0) * 100;
    $html .= bst_render_view_item('Commission', $commission_percent . '%');
    
    $html .= bst_render_view_item('Commission Reason', $booking->booking_commission_reason ?? '');
    
    $html .= '</div>';
    return $html;
}

/**
 * Helper function to generate link HTML with copy button
 * @param string $label The link label
 * @param string $url The URL
 * @return string HTML for the link item
 */
function bst_render_link_item($label, $url) {
    $html = '<div class="view-item">';
    $html .= '<strong>' . esc_html($label) . ':</strong>';
    $html .= '<a href="' . esc_url($url) . '" target="_blank" class="link-url">' . esc_html($url) . '</a>';
    $html .= '<button class="copy-button" onclick="copyToClipboard(\'' . esc_js($url) . '\', this)">';
    $html .= '<span class="dashicons dashicons-clipboard copy-icon"></span>';
    $html .= '<span class="copy-text">Copy</span>';
    $html .= '</button>';
    $html .= '</div>';
    return $html;
}

/**
 * Render the tile view content for links
 * @param object $booking The booking object
 * @return string HTML content
 */
function bst_render_links_tile_content($booking) {
    $html = '<div class="tile-view-content links-tile">';
    
    // Pre-encode all IDs for efficiency
    $encoded_booking_id = !empty($booking->id) ? bst_encode_booking_id($booking->id) : null;
    $encoded_booking_eid = !empty($booking->booking_entry_id) ? bst_encode_booking_id($booking->booking_entry_id) : null;
    $encoded_finalization_eid = !empty($booking->finalization_entry_id) ? bst_encode_booking_id($booking->finalization_entry_id) : null;
    
    // Determine which entry_id to use for detail links (prioritize booking_entry_id, fallback to finalization_entry_id)
    $primary_entry_id = $booking->booking_entry_id ?? $booking->finalization_entry_id ?? null;
    $encoded_primary_eid = $encoded_booking_eid ?? $encoded_finalization_eid ?? null;
    
    // Reservation Link (only show if status is Reserved and no booking_entry_id)
    if ($booking->booking_status === 'Reserved' && empty($booking->booking_entry_id)) {
        $reservation_url = bst_get_reservation_url($booking->id);
        $html .= bst_render_link_item('Reservation Link', $reservation_url);
    }
    
    // Finalization Link (only show if status is Booked and no finalization_entry_id)
    if ($booking->booking_status === 'Booked' && empty($booking->finalization_entry_id)) {
        $finalization_url = bst_get_finalization_url($booking->id);
        $html .= bst_render_link_item('Finalization Link', $finalization_url);
    }
    
    // Customer Booking Confirmation (only show if booking_entry_id exists)
    if ($encoded_booking_eid) {
        $confirmation_url = site_url('/bookingconfirmation/') . '?eid=' . $encoded_booking_eid;
        $html .= bst_render_link_item('Cust Booking Confirmation', $confirmation_url);
    }
    
    // Customer Finalization Confirmation (only show if finalization_entry_id exists)
    if ($encoded_finalization_eid) {
        $finalization_confirmation_url = site_url('/bookingconfirmation/') . '?eid=' . $encoded_finalization_eid;
        $html .= bst_render_link_item('Cust Finalization Confirmation', $finalization_confirmation_url);
    }
    
    // Customer Booking Details (show if any entry_id exists OR if we have a booking_id)
    if ($encoded_primary_eid) {
        $customer_details_url = site_url('/bookingdetails/') . '?eid=' . $encoded_primary_eid;
        $html .= bst_render_link_item('Cust Booking Details', $customer_details_url);
    } elseif ($encoded_booking_id) {
        // No entry_id but we have booking_id - use bid parameter
        $customer_details_url = site_url('/bookingdetails/') . '?bid=' . $encoded_booking_id;
        $html .= bst_render_link_item('Cust Booking Details', $customer_details_url);
    }
    
    // BST Booking Details (show if any entry_id exists OR if we have a booking_id)
    if ($encoded_primary_eid) {
        $bst_details_url = site_url('/bookingdetails/') . '?eid=' . $encoded_primary_eid . '&type=bst';
        $html .= bst_render_link_item('BST Booking Details', $bst_details_url);
    } elseif ($encoded_booking_id) {
        // No entry_id but we have booking_id - use bid parameter
        $bst_details_url = site_url('/bookingdetails/') . '?bid=' . $encoded_booking_id . '&type=bst';
        $html .= bst_render_link_item('BST Booking Details', $bst_details_url);
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Render the tile view content for system information
 * @param object $booking The booking object
 * @return string HTML content
 */
function bst_render_system_tile_content($booking) {
    $html = '<div class="tile-view-content">';
    
    $html .= bst_render_view_item('Record ID', $booking->id ?? '');
    $html .= bst_render_view_item('Data Source', $booking->data_source ?? '');
    $html .= bst_render_view_item('Created By', $booking->created_by ?? '');
    $html .= bst_render_view_item('Created Date', $booking->created_date ?? '');
    $html .= bst_render_view_item('Updated By', $booking->updated_by ?? '');
    $html .= bst_render_view_item('Updated Date', $booking->updated_date ?? '');
    
    $html .= '</div>';
    return $html;
}

/**
 * Render the tile view content for financials information
 * @param object $booking The booking object
 * @return string HTML content
 */
function bst_render_financials_tile_content($booking) {
    $html = '<div class="tile-view-content">';
    
    // Start pricing matrix container
    $html .= '<div class="pricing-matrix">';
    
    // Currency
    $html .= '<div class="pricing-label">Currency:</div>';
    $html .= '<div class="pricing-value">' . esc_html($booking->tour_currency ?? 'EUR') . '</div>';
    
    // Tour Price
    $html .= '<div class="pricing-label">Tour Price:</div>';
    $html .= '<div class="pricing-value">' . esc_html(bst_format_currency($booking->tour_price ?? 0, $booking->tour_currency ?? 'EUR')) . '</div>';
    
    // Coupon information
    if (!empty($booking->coupon_amount) && $booking->coupon_amount > 0) {
        $html .= '<div class="pricing-label">Coupon Amount:</div>';
        $html .= '<div class="pricing-value">';
        $html .= esc_html(bst_format_currency($booking->coupon_amount, $booking->tour_currency ?? 'EUR'));
        if (!empty($booking->coupon_code)) {
            $html .= '<small style="margin-left: 8px; color: #666;">(' . esc_html($booking->coupon_code) . ')</small>';
        }
        $html .= '</div>';
        
        $html .= '<div class="pricing-label">Net Tour Price:</div>';
        $html .= '<div class="pricing-value">' . esc_html(bst_format_currency($booking->net_tour_price ?? 0, $booking->tour_currency ?? 'EUR')) . '</div>';
    }
    
    // Additional charge
    if (!empty($booking->additional_charge) && $booking->additional_charge > 0) {
        $html .= '<div class="pricing-label">Additional Charge:</div>';
        $html .= '<div class="pricing-value">' . esc_html(bst_format_currency($booking->additional_charge, $booking->tour_currency ?? 'EUR')) . '</div>';
        
        $html .= '<div class="pricing-label">Total Due:</div>';
        $html .= '<div class="pricing-value">' . esc_html(bst_format_currency(bst_calculate_total_due($booking->net_tour_price ?? 0, $booking->additional_charge ?? 0), $booking->tour_currency ?? 'EUR')) . '</div>';
    }
    
    // Total Paid (optional: pending breakdown — same note as Balance Due when wires/cards are unsettled)
    $pending_note = function_exists( 'bst_booking_pending_payment_note_html' ) ? bst_booking_pending_payment_note_html( $booking ) : '';
    $html .= '<div class="pricing-label">Total Paid:</div>';
    $html .= '<div class="pricing-value">' . esc_html( bst_format_currency( $booking->total_paid ?? 0, $booking->tour_currency ?? 'EUR' ) ) . $pending_note . '</div>';
    
    // Payment Discount (only show if has value)
    if (!empty($booking->payment_discount_amount) && $booking->payment_discount_amount > 0) {
        $html .= '<div class="pricing-label">Payment Discount:</div>';
        $html .= '<div class="pricing-value">' . esc_html(bst_format_currency($booking->payment_discount_amount, $booking->tour_currency ?? 'EUR')) . '</div>';
    }
    
    // Balance Due (display stored value, not calculated)
    $html .= '<div class="pricing-label">Balance Due:</div>';
    $balance_due_display = bst_format_currency($booking->balance_due ?? 0, $booking->tour_currency ?? 'EUR');
    
    // Add due date if available and balance is greater than 0
    if (($booking->balance_due ?? 0) > 0 && function_exists('bst_calculate_balance_due_date')) {
        $balance_due_date = bst_calculate_balance_due_date($booking);
        if (!empty($balance_due_date)) {
            $balance_due_display .= ' <span style="color: #666; font-size: 0.9em;">(due ' . esc_html($balance_due_date) . ')</span>';
        }
    }
    
    $html .= '<div class="pricing-value">' . $balance_due_display . $pending_note . '</div>';
    
    // Close pricing matrix container
    $html .= '</div>';
    
    // Payment table (view): fixed layout, no horizontal scroll — see .bst-financials-payment-table--view CSS
    $html .= '<div class="payment-matrix bst-payment-matrix bst-financials-payment-wrap">';
    
    // Helper function to check if a payment row has meaningful data
    $has_payment_data = function($booking, $type) {
        $method = $booking->{$type . '_payment_method'} ?? '';
        $amount = $booking->{$type . '_payment_amount'} ?? '';
        $date = $booking->{$type . '_payment_date'} ?? '';
        $invoice = $booking->{$type . '_commission_invoice'} ?? '';
        
        // For additional payment, use different invoice field
        if ($type === 'additional') {
            $invoice = $booking->additional_payment_commission_invoice ?? '';
        }
        
        // Consider it has data if it has method, amount, date, or invoice
        return (!empty($method) && $method !== '') || 
               (!empty($amount) && $amount != 0) || 
               (!empty($date) && $date !== '0000-00-00' && $date !== '0000-00-00 00:00:00') ||
               (!empty($invoice) && $invoice !== '');
    };
    
    // Check which payment types have data
    $payment_types = ['deposit', 'balance', 'additional', 'refund'];
    $visible_payments = [];
    
    foreach ($payment_types as $type) {
        if ($has_payment_data($booking, $type)) {
            $visible_payments[] = $type;
        }
    }
    
    if (empty($visible_payments)) {
        $html .= '<p style="font-style: italic; color: #666; margin: 20px 0;">There are no payments on this booking</p>';
    } else {
        $html .= '<table class="bst-financials-payment-table bst-financials-payment-table--view">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>Type</th>';
        $html .= '<th>Method</th>';
        $html .= '<th>Amount</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Discount</th>';
        $html .= '<th>Date</th>';
        $html .= '<th>CBC Invoice</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        // Helper function to format payment date
        $format_payment_date = function($date) {
            if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
                return '-';
            }
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                return '-';
            }
            if (strpos($date, ' 00:00:00') !== false) {
                return date('Y-m-d', $timestamp);
            } elseif (strpos($date, ' ') !== false) {
                return date('Y-m-d H:i', $timestamp);
            } else {
                return date('Y-m-d', $timestamp);
            }
        };
        
        // Deposit payment
        if (in_array('deposit', $visible_payments)) {
            $html .= '<tr>';
            $html .= '<td><strong>Deposit</strong></td>';
            $method = $booking->deposit_payment_method ?? '';
            // Add card type for Credit Card payments
            if ($method === 'Credit Card' && !empty($booking->booking_entry_id)) {
                $deposit_entry = GFAPI::get_entry($booking->booking_entry_id);
                if ($deposit_entry && !is_wp_error($deposit_entry)) {
                    $card_type = bst_get_card_type($deposit_entry, rgar($deposit_entry, 'form_id'));
                    if (!empty($card_type)) {
                        $method .= ' (' . ucwords($card_type) . ')';
                    }
                }
            }
            // Convert Bank Wire to Bank Transfer for display
            $method = ($method === 'Bank Wire') ? 'Bank Transfer' : $method;
            $html .= '<td>' . esc_html(($method && $method !== '') ? $method : '-') . '</td>';
            $d_amt = floatval( $booking->deposit_payment_amount ?? 0 );
            $amount = $d_amt > 0 ? bst_format_currency( $d_amt, $booking->tour_currency ?? 'EUR' ) : '-';
            $html .= '<td>' . $amount . '</td>';
            $html .= '<td>' . esc_html( function_exists( 'bst_payment_status_label_for_display' ) ? ( bst_payment_status_label_for_display( $booking->deposit_payment_status ?? '' ) ?: '-' ) : '-' ) . '</td>';
            $discount = !empty($booking->deposit_payment_discount) ? bst_format_currency($booking->deposit_payment_discount, $booking->tour_currency ?? 'EUR') : '-';
            $html .= '<td>' . esc_html($discount) . '</td>';
            $html .= '<td>' . esc_html($format_payment_date($booking->deposit_payment_date ?? '')) . '</td>';
            $invoice = $booking->deposit_commission_invoice ?? '';
            $html .= '<td>' . esc_html(($invoice && $invoice !== '') ? $invoice : '-') . '</td>';
            $html .= '</tr>';
        }
        
        // Balance payment
        if (in_array('balance', $visible_payments)) {
            $html .= '<tr>';
            $html .= '<td><strong>Balance</strong></td>';
            $method = $booking->balance_payment_method ?? '';
            // Add card type for Credit Card payments
            if ($method === 'Credit Card' && !empty($booking->finalization_entry_id)) {
                $balance_entry = GFAPI::get_entry($booking->finalization_entry_id);
                if ($balance_entry && !is_wp_error($balance_entry)) {
                    $card_type = bst_get_card_type($balance_entry, rgar($balance_entry, 'form_id'));
                    if (!empty($card_type)) {
                        $method .= ' (' . ucwords($card_type) . ')';
                    }
                }
            }
            // Convert Bank Wire to Bank Transfer for display
            $method = ($method === 'Bank Wire') ? 'Bank Transfer' : $method;
            $html .= '<td>' . esc_html(($method && $method !== '') ? $method : '-') . '</td>';
            $b_amt = floatval( $booking->balance_payment_amount ?? 0 );
            $amount = $b_amt > 0 ? bst_format_currency( $b_amt, $booking->tour_currency ?? 'EUR' ) : '-';
            $html .= '<td>' . $amount . '</td>';
            $html .= '<td>' . esc_html( function_exists( 'bst_payment_status_label_for_display' ) ? ( bst_payment_status_label_for_display( $booking->balance_payment_status ?? '' ) ?: '-' ) : '-' ) . '</td>';
            $discount = !empty($booking->balance_payment_discount) ? bst_format_currency($booking->balance_payment_discount, $booking->tour_currency ?? 'EUR') : '-';
            $html .= '<td>' . esc_html($discount) . '</td>';
            $html .= '<td>' . esc_html($format_payment_date($booking->balance_payment_date ?? '')) . '</td>';
            $invoice = $booking->balance_commission_invoice ?? '';
            $html .= '<td>' . esc_html(($invoice && $invoice !== '') ? $invoice : '-') . '</td>';
            $html .= '</tr>';
        }
        
        // Additional payment
        if (in_array('additional', $visible_payments)) {
            $html .= '<tr>';
            $html .= '<td><strong>Additional</strong></td>';
            $method = $booking->additional_payment_method ?? '';
            // Add card type for Credit Card payments
            if ($method === 'Credit Card' && !empty($booking->additional_payment_entry_id)) {
                $additional_entry = GFAPI::get_entry($booking->additional_payment_entry_id);
                if ($additional_entry && !is_wp_error($additional_entry)) {
                    $card_type = bst_get_card_type($additional_entry, rgar($additional_entry, 'form_id'));
                    if (!empty($card_type)) {
                        $method .= ' (' . ucwords($card_type) . ')';
                    }
                }
            }
            // Convert Bank Wire to Bank Transfer for display
            $method = ($method === 'Bank Wire') ? 'Bank Transfer' : $method;
            $html .= '<td>' . esc_html(($method && $method !== '') ? $method : '-') . '</td>';
            $a_amt = floatval( $booking->additional_payment_amount ?? 0 );
            $amount = $a_amt > 0 ? bst_format_currency( $a_amt, $booking->tour_currency ?? 'EUR' ) : '-';
            $html .= '<td>' . $amount . '</td>';
            $html .= '<td>' . esc_html( function_exists( 'bst_payment_status_label_for_display' ) ? ( bst_payment_status_label_for_display( $booking->additional_payment_status ?? '' ) ?: '-' ) : '-' ) . '</td>';
            $discount = !empty($booking->additional_payment_discount) ? bst_format_currency($booking->additional_payment_discount, $booking->tour_currency ?? 'EUR') : '-';
            $html .= '<td>' . esc_html($discount) . '</td>';
            $html .= '<td>' . esc_html($format_payment_date($booking->additional_payment_date ?? '')) . '</td>';
            $invoice = $booking->additional_payment_commission_invoice ?? '';
            $html .= '<td>' . esc_html(($invoice && $invoice !== '') ? $invoice : '-') . '</td>';
            $html .= '</tr>';
        }
        
        // Refund payment
        if (in_array('refund', $visible_payments)) {
            $html .= '<tr>';
            $html .= '<td><strong>Refund</strong></td>';
            $method = $booking->refund_payment_method ?? '';
            $html .= '<td>' . esc_html(($method && $method !== '') ? $method : '-') . '</td>';
            $r_amt = floatval( $booking->refund_payment_amount ?? 0 );
            $amount = $r_amt > 0 ? '-' . bst_format_currency($r_amt, $booking->tour_currency ?? 'EUR') : '-';
            $html .= '<td>' . $amount . '</td>';
            $html .= '<td>' . esc_html( function_exists( 'bst_payment_status_label_for_display' ) ? ( bst_payment_status_label_for_display( $booking->refund_payment_status ?? '' ) ?: '-' ) : '-' ) . '</td>';
            $html .= '<td>-</td>';
            $html .= '<td>' . esc_html($format_payment_date($booking->refund_payment_date ?? '')) . '</td>';
            $invoice = $booking->refund_commission_invoice ?? '';
            $html .= '<td>' . esc_html(($invoice && $invoice !== '') ? $invoice : '-') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
    }
    
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render the tile view content for tour package information
 * @param object $booking The booking object
 * @return string HTML content
 */
function bst_render_tour_package_tile_content($booking) {
    $html = '<div class="tile-view-content">';
    
    // Tour with ID link
    $tour_title = '';
    if ( ! empty( $booking->tour_id ) ) {
        $p = get_post( (int) $booking->tour_id );
        if ( $p && 'tour' === $p->post_type ) {
            $tour_title = $p->post_title;
        }
    }
    if ( '' === $tour_title ) {
        $tour_title = (string) ( $booking->tour_text ?? '' );
    }
    $html .= '<div class="view-item"><strong>Tour:</strong> ' . esc_html($tour_title);
    if (!empty($booking->tour_id)) {
        $html .= ' (ID: <a href="' . esc_url(admin_url('post.php?post=' . $booking->tour_id . '&action=edit')) . '" target="_blank" title="Edit tour record">' . esc_html($booking->tour_id) . '</a>)';
    }
    $html .= '</div>';
    
    // Tour Date with ID link
    $tour_date_display = '';
    if (!empty($booking->tour_date_id)) {
        $tour_date_id = explode('|', $booking->tour_date_id)[0];
        $td = get_post( (int) $tour_date_id );
        if ( $td && 'tour-date' === $td->post_type ) {
            $tour_date_display = $td->post_title;
        }
        $html .= ' (ID: <a href="' . esc_url(admin_url('post.php?post=' . $tour_date_id . '&action=edit')) . '" target="_blank" title="Edit tour date record">' . esc_html($tour_date_id) . '</a>)';
    }
    if ( '' === $tour_date_display ) {
        $tour_date_display = (string) ( $booking->tour_date_text ?? '' );
    }
    $html = str_replace('<div class="view-item"><strong>Tour Date:</strong> ', '<div class="view-item"><strong>Tour Date:</strong> ' . esc_html($tour_date_display), $html);
    $html .= '</div>';
    
    // Package with price removal
    $html .= '<div class="view-item"><strong>Package:</strong> ';
    $package_text = $booking->tour_package_text ?? '';
    $package_display = preg_replace('/\s*-\s*[€$]\s?[0-9,]+\.?\d*/', '', $package_text);
    $html .= esc_html($package_display);
    if (!empty($booking->tour_package_id)) {
        $html .= ' (ID: ' . esc_html($booking->tour_package_id) . ')';
    }
    $html .= '</div>';
    
    // Vehicles (prefer normalized IDs; keep legacy text as fallback)
    $v1 = function_exists('bst_booking_vehicle_display_text') ? bst_booking_vehicle_display_text($booking, 1) : ($booking->vehicle1 ?? '');
    $v2 = function_exists('bst_booking_vehicle_display_text') ? bst_booking_vehicle_display_text($booking, 2) : ($booking->vehicle2 ?? '');
    if ( ! empty( $v1 ) || ! empty( $v2 ) ) {
        if ( ! empty( $v1 ) ) {
            $html .= '<div class="view-item"><strong>Vehicle 1:</strong> ' . esc_html($v1) . '</div>';
        }
        if ( ! empty( $v2 ) ) {
            $html .= '<div class="view-item"><strong>Vehicle 2:</strong> ' . esc_html($v2) . '</div>';
        }
    }
    
    // Extension information
    if (!empty($booking->tour_extension_added) && $booking->tour_extension_added == 1) {
        // tour_extension_text contains "Title (+€price)" e.g. "Dolomites Trip (+€3567)"
        // tour_extension_date_text contains "9-13 Dec 2025"
        // Display format should be: "Title (dates - price)" e.g. "Dolomites Trip (9-13 Dec 2025 - €3567)"
        
        $extensionText = trim($booking->tour_extension_text ?? '');
        $extensionDates = trim($booking->tour_extension_date_text ?? '');
        
        // Simple replacement: insert dates after opening parenthesis with " - " separator
        // "Dolomites Trip (+€3567)" becomes "Dolomites Trip (9-13 Dec 2025 - €3567)"
        $displayText = str_replace('(+', '(' . $extensionDates . ' - ', $extensionText);
        
        $html .= '<div class="view-item"><strong>Extension:</strong> ' . esc_html($displayText) . '</div>';
    }
    
    // Additional preferences
    if (!empty($booking->participant_sex)) {
        $html .= '<div class="view-item"><strong>Participant Sex:</strong> ' . esc_html($booking->participant_sex) . '</div>';
    }
    if (!empty($booking->sharing_preference)) {
        $display_preference = $booking->sharing_preference === 'same' ? 'With a person of the same sex' : 
                             ($booking->sharing_preference === 'any' ? 'With a person of any sex' : $booking->sharing_preference);
        $html .= '<div class="view-item"><strong>Sharing Preference:</strong> ' . esc_html($display_preference) . '</div>';
    }
    if (!empty($booking->bed_preference)) {
        $html .= '<div class="view-item"><strong>Bed Preference:</strong> ' . esc_html($booking->bed_preference) . '</div>';
    }
    if (!empty($booking->hotel_nights_before)) {
        $html .= '<div class="view-item"><strong>Hotel Nights Before:</strong> ' . esc_html($booking->hotel_nights_before) . '</div>';
    }
    if (!empty($booking->hotel_nights_after)) {
        $html .= '<div class="view-item"><strong>Hotel Nights After:</strong> ' . esc_html($booking->hotel_nights_after) . '</div>';
    }
    
    // Hidden fields for logic purposes
    $html .= '<div class="view-item" style="display: none;"><strong>Package People:</strong> ' . esc_html($booking->package_people ?? '') . '</div>';
    
    $html .= '<div class="view-item" style="display: none;"><strong>Package Rooms:</strong> ';
    $rooms = $booking->package_rooms ?? '';
    if (!empty($rooms)) {
        if (floor($rooms) == $rooms) {
            $html .= intval($rooms);
        } else {
            $formatted = rtrim(sprintf('%.1f', $rooms), '0');
            $html .= ltrim($formatted, '0');
        }
    }
    $html .= '</div>';
    
    $html .= '<div class="view-item" style="display: none;"><strong>Package Vehicles:</strong> ' . esc_html($booking->package_vehicles ?? '') . '</div>';
    $html .= '<div class="view-item" style="display: none;"><strong>Vehicle Choices:</strong> ' . esc_html($booking->vehicle_choices ?? '0') . '</div>';
    
    $html .= '</div>';
    return $html;
}

/**
 * Main function to render tile content based on tile type
 * @param string $tile_type The type of tile to render
 * @param object $booking The booking object
 * @return string HTML content
 */
function bst_render_tile_content($tile_type, $booking) {
    switch ($tile_type) {
        case 'marketing':
            return bst_render_marketing_tile_content($booking);
        case 'guest1':
            return bst_render_guest_tile_content($booking, 1);
        case 'guest2':
            return bst_render_guest_tile_content($booking, 2);
        case 'notes':
            return bst_render_notes_tile_content($booking);
        case 'customer':
            return bst_render_customer_tile_content($booking);
        case 'gravity_forms':
            return bst_render_gravity_forms_tile_content($booking);
        case 'administrative':
            return bst_render_administrative_tile_content($booking);
        case 'system':
            return bst_render_system_tile_content($booking);
        case 'financials':
            return bst_render_financials_tile_content($booking);
        case 'tour_package':
            return bst_render_tour_package_tile_content($booking);
        case 'invoicing':
            return bst_render_invoicing_tile_content($booking);
        default:
            return '<div class="view-item">Tile type not supported yet: ' . esc_html($tile_type) . '</div>';
    }
}

/**
 * Calculate total due amount
 */
function bst_calculate_total_due($net_price, $additional_charge) {
    $net = floatval($net_price ?? 0);
    $additional = floatval($additional_charge ?? 0);
    return $net + $additional;
}

/**
 * Calculate net balance due (accounting for refunds)
 */
function bst_calculate_net_balance_due($net_price, $additional_charge, $total_paid, $refund_amount = 0) {
    $total_due = bst_calculate_total_due($net_price, $additional_charge);
    $net_paid = floatval($total_paid ?? 0) - floatval($refund_amount ?? 0);
    return $total_due - $net_paid;
}

/**
 * Legacy helper to extract and format emergency contact into comma-separated string (for backwards compatibility)
 */
function bst_format_emergency_contact($emergency_contact) {
    if (empty($emergency_contact)) return '';
    
    // Try to decode as JSON first
    $contact_data = json_decode($emergency_contact, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($contact_data)) {
        // JSON format - extract structured data
        $contact_parts = [];
        
        if (!empty($contact_data['name'])) {
            $contact_parts[] = $contact_data['name'];
        }
        
        if (!empty($contact_data['phone'])) {
            $contact_parts[] = $contact_data['phone'];
        }
        
        if (!empty($contact_data['email'])) {
            $contact_parts[] = $contact_data['email'];
        }
        
        return implode(', ', $contact_parts);
    } else {
        // Fallback to text parsing for non-JSON data
        $lines = explode("\n", trim($emergency_contact));
        $cleaned_lines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $cleaned_lines[] = $line;
            }
        }
        
        return implode(', ', $cleaned_lines);
    }
}

/**
 * Helper to extract and format travel information into arrival/departure grid
 */
function bst_format_travel_grid($travel_details) {
    if (empty($travel_details)) return [];
    
    // Try to decode as JSON first
    $travel_data = json_decode($travel_details, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($travel_data)) {
        // JSON format - extract structured data
        $arrival = [];
        $departure = [];
        
        // Map the JSON fields to display fields
        if (!empty($travel_data['travel_method'])) {
            $arrival['Method'] = $travel_data['travel_method'];
            $departure['Method'] = $travel_data['travel_method'];
        }
        
        if (!empty($travel_data['arrival_location'])) {
            $arrival['Location'] = $travel_data['arrival_location'];
        }
        if (!empty($travel_data['departure_location'])) {
            $departure['Location'] = $travel_data['departure_location'];
        }
        
        if (!empty($travel_data['arrival_flight'])) {
            $arrival['Flight'] = $travel_data['arrival_flight'];
        }
        if (!empty($travel_data['departure_flight'])) {
            $departure['Flight'] = $travel_data['departure_flight'];
        }
        
        if (!empty($travel_data['arrival_date'])) {
            $arrival['Date'] = $travel_data['arrival_date'];
        }
        if (!empty($travel_data['departure_date'])) {
            $departure['Date'] = $travel_data['departure_date'];
        }
        
        if (!empty($travel_data['arrival_time'])) {
            $arrival['Time'] = $travel_data['arrival_time'];
        }
        if (!empty($travel_data['departure_time'])) {
            $departure['Time'] = $travel_data['departure_time'];
        }
        
        if (!empty($travel_data['travel_other'])) {
            $arrival['Other'] = $travel_data['travel_other'];
        }
        
        // Get all unique field names
        $all_fields = array_unique(array_merge(array_keys($arrival), array_keys($departure)));
        
        return [
            'fields' => $all_fields,
            'arrival' => $arrival,
            'departure' => $departure
        ];
    } else {
        // Fallback to text parsing for non-JSON data
        $arrival = [];
        $departure = [];
        $lines = explode("\n", trim($travel_details));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Check if line contains arrival information
            if (preg_match('/arrival|arrive|inbound|incoming/i', $line)) {
                // Extract field and value
                if (preg_match('/^([^:=]+)[:=\s]*(.+)/', $line, $matches)) {
                    $field = trim(preg_replace('/arrival|arrive|inbound|incoming/i', '', $matches[1]));
                    $field = trim($field, ':= ');
                    if (empty($field)) $field = 'Details';
                    $arrival[ucfirst($field)] = trim($matches[2]);
                } else {
                    $arrival['Details'] = $line;
                }
            }
            // Check if line contains departure information
            elseif (preg_match('/departure|depart|outbound|outgoing/i', $line)) {
                // Extract field and value
                if (preg_match('/^([^:=]+)[:=\s]*(.+)/', $line, $matches)) {
                    $field = trim(preg_replace('/departure|depart|outbound|outgoing/i', '', $matches[1]));
                    $field = trim($field, ':= ');
                    if (empty($field)) $field = 'Details';
                    $departure[ucfirst($field)] = trim($matches[2]);
                } else {
                    $departure['Details'] = $line;
                }
            }
            // Try to categorize by common travel fields
            elseif (preg_match('/^(flight|airline|time|date|terminal|gate)[:=\s]*(.+)/i', $line, $matches)) {
                $field = ucfirst(strtolower(trim($matches[1])));
                $value = trim($matches[2]);
                
                // Try to determine if it's arrival or departure based on context
                if (preg_match('/arrive|arrival|land|inbound/i', $value)) {
                    $arrival[$field] = $value;
                } elseif (preg_match('/depart|departure|takeoff|outbound/i', $value)) {
                    $departure[$field] = $value;
                } else {
                    // Default to arrival if unclear
                    $arrival[$field] = $value;
                }
            }
            // Generic field:value pairs
            elseif (preg_match('/^([^:=]+)[:=\s]+(.+)/', $line, $matches)) {
                $field = ucfirst(trim($matches[1]));
                $value = trim($matches[2]);
                $arrival[$field] = $value;
            }
            // Single line without clear structure
            else {
                if (!isset($arrival['Notes'])) $arrival['Notes'] = '';
                $arrival['Notes'] .= ($arrival['Notes'] ? ' | ' : '') . $line;
            }
        }
        
        // Get all unique field names
        $all_fields = array_unique(array_merge(array_keys($arrival), array_keys($departure)));
        
        return [
            'fields' => $all_fields,
            'arrival' => $arrival,
            'departure' => $departure
        ];
    }
}

/**
 * Extract numeric price from vehicle choice string (e.g., "Alfa Romeo Giulietta (€450)" => 450)
 */
function bst_extract_vehicle_price($vehicle_string) {
    if (empty($vehicle_string)) {
        return 0;
    }
    
    error_log("BST Extract Price: Input string = " . var_export($vehicle_string, true));
    
    // Match price in parentheses: (+€450) or (+$450) or (€450) or (€ 450) etc.
    // Updated regex to handle unicode euro symbol properly
    if (preg_match('/\(\+?[€$]\s?([0-9,]+\.?\d*)\)/u', $vehicle_string, $matches)) {
        error_log("BST Extract Price: Regex matched! Matches = " . var_export($matches, true));
        // Remove commas and convert to float
        $price = floatval(str_replace(',', '', $matches[1]));
        error_log("BST Extract Price: Extracted price = {$price}");
        return $price;
    }
    
    error_log("BST Extract Price: NO MATCH - returning 0");
    return 0;
}

/**
 * Render the Booking Invoicing tile content (display mode)
 */
function bst_render_invoicing_tile_content($booking) {
    $html = '<div class="tile-view-content">';
    
    // Invoice Number with link next to it (if invoice exists)
    $invoice_number = $booking->booking_invoice_number ?? 'Not generated';
    if (!empty($booking->finalization_entry_id) && !empty($booking->booking_invoice_number) && $booking->booking_invoice_number !== 'Not generated') {
        $encoded_entry_id = bst_encode_booking_id($booking->finalization_entry_id);
        $invoice_url = site_url('/bookinginvoice/') . '?eid=' . $encoded_entry_id;
        $invoice_number .= ' (<a href="' . esc_url($invoice_url) . '" target="_blank" style="color: #0066cc; text-decoration: none;">View Invoice</a>)';
    }
    $html .= '<div class="view-item"><strong>Invoice Number:</strong> ' . wp_kses_post($invoice_number) . '</div>';
    
    // If no finalization entry, show message
    if (empty($booking->finalization_entry_id)) {
        $html .= '<div class="view-item empty-info" style="font-style: italic; color: #666;">Invoice details will be available after final payment is processed.</div>';
        $html .= '</div>';
        return $html;
    }
    
    // Get GF10 entry data
    $gf_entry = GFAPI::get_entry($booking->finalization_entry_id);
    
    if (is_wp_error($gf_entry) || empty($gf_entry)) {
        $html .= '<div class="view-item empty-info" style="font-style: italic; color: #666;">Could not load finalization entry data.</div>';
        $html .= '</div>';
        return $html;
    }
    
    // Invoice Date - Get from booking_invoice_date field (only show if set)
    if (!empty($booking->booking_invoice_date)) {
        $date = $booking->booking_invoice_date;
        if ($date !== '0000-00-00' && $date !== '0000-00-00 00:00:00') {
            $timestamp = strtotime($date);
            if ($timestamp !== false) {
                $invoice_date = date('Y-m-d', $timestamp);
                $html .= bst_render_view_item('Invoice Date', $invoice_date);
            }
        }
    }
    
    // EU Percent (booking_eu_percent) - only show if has value
    if (!empty($booking->booking_eu_percent)) {
        $eu_percent = number_format(floatval($booking->booking_eu_percent), 2) . '%';
        $html .= bst_render_view_item('EU Percent', $eu_percent);
    }
    
    // VAT Rate (booking_vat_rate) - only show if has value
    if (!empty($booking->booking_vat_rate)) {
        $vat_rate = number_format(floatval($booking->booking_vat_rate), 2) . '%';
        $html .= bst_render_view_item('VAT Rate', $vat_rate);
    }
    
    // ===== Tour Package Section =====
    $html .= '<h4 class="tile-subsection-header">Tour Package</h4>';
    
    // Tour name and date with extension info
    $tour_display = ($booking->tour_text ?? '') . ' (' . ($booking->tour_date_text ?? '') . ')';
    if ($booking->tour_extension_added ?? false) {
        if (!empty($booking->tour_extension_text) && !empty($booking->tour_extension_date_text)) {
            // Remove price from extension text (anything in parentheses with € or $)
            $extension_text_clean = preg_replace('/\s*\([^)]*[€$][^)]*\)/', '', $booking->tour_extension_text);
            $tour_display = '<span class="address-block">' . $tour_display . '<br>+ ' . $extension_text_clean . ' (' . $booking->tour_extension_date_text . ')</span>';
        }
    }
    $html .= '<div class="view-item"><strong>Tour:</strong>' . ($booking->tour_extension_added ? $tour_display : ' ' . $tour_display) . '</div>';
    
    // Tour Package Amount from booking_tour_package_amount (only show if has value)
    $currency = $booking->tour_currency ?? 'EUR';
    $currency_symbol = $currency === 'USD' ? '$' : '€';
    if (!empty($booking->booking_tour_package_amount)) {
        $tour_package_amount = floatval($booking->booking_tour_package_amount);
        $html .= bst_render_view_item('Amount', $currency_symbol . number_format($tour_package_amount, 2));
    }
    
    // ===== Vehicles Section (only if using BST-owned vehicles AND has values) =====
    $tour_id = $booking->tour_id ?? 0;
    $using_bst_vehicles = false;
    if ($tour_id) {
        $using_bst_vehicles = get_field('using_bst_owned_vehicles', $tour_id) ?? false;
    }
    
    // Only show vehicle section if using BST vehicles AND at least one vehicle amount has a value
    if ($using_bst_vehicles) {
        $vehicle1_use_amount = floatval($booking->booking_vehicle_1_use_amount ?? 0);
        $vehicle2_use_amount = floatval($booking->booking_vehicle_2_use_amount ?? 0);
        
        // Only show section if at least one vehicle has a value
        if ($vehicle1_use_amount > 0 || $vehicle2_use_amount > 0) {
            $html .= '<h4 class="tile-subsection-header">Vehicle Use</h4>';
            
            // Vehicle 1 Use Amount (only show if has value)
            if ($vehicle1_use_amount > 0) {
                $html .= bst_render_view_item('Vehicle 1 Use', $currency_symbol . number_format($vehicle1_use_amount, 2));
            }
            
            // Vehicle 2 Use Amount (only show if has value)
            if ($vehicle2_use_amount > 0) {
                $html .= bst_render_view_item('Vehicle 2 Use', $currency_symbol . number_format($vehicle2_use_amount, 2));
            }
            
            // Show vehicle total if both vehicles have values
            if ($vehicle1_use_amount > 0 && $vehicle2_use_amount > 0) {
                $vehicle_total = $vehicle1_use_amount + $vehicle2_use_amount;
                $html .= '<div class="view-item" style="border-top: 1px solid #ddd; padding-top: 8px; margin-top: 8px;"><strong>Total:</strong> ' . $currency_symbol . number_format($vehicle_total, 2) . '</div>';
            }
        }
        
        // Show Totals section if both tour and vehicle sections have values
        $tour_package_amount = floatval($booking->booking_tour_package_amount ?? 0);
        $vehicle_total = $vehicle1_use_amount + $vehicle2_use_amount;
        
        if ($tour_package_amount > 0 && $vehicle_total > 0) {
            $html .= '<h4 class="tile-subsection-header">Totals</h4>';
            $html .= bst_render_view_item('Tour Amount', $currency_symbol . number_format($tour_package_amount, 2));
            $html .= bst_render_view_item('Vehicle Use Amount', $currency_symbol . number_format($vehicle_total, 2));
            
            $grand_total = $tour_package_amount + $vehicle_total;
            $html .= '<div class="view-item" style="border-top: 1px solid #ddd; padding-top: 8px; margin-top: 8px; font-weight: bold;"><strong>Total:</strong> ' . $currency_symbol . number_format($grand_total, 2) . '</div>';
        }
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Render Email Log tile content
 */
function bst_render_email_log_tile_content($booking) {
    if (!$booking || !$booking->id) {
        return '<div class="tile-view-content"><p>No email log available for new bookings.</p></div>';
    }
    
    // Get the email log viewer instance
    $email_log_viewer = new BST_Email_Log_Viewer();
    
    // Capture the output of the email log rendering (without modals - they're at page level)
    ob_start();
    $email_log_viewer->render_email_log($booking->id, false);
    $email_log_html = ob_get_clean();
    
    // Wrap in tile view content div
    $html = '<div class="tile-view-content">';
    $html .= $email_log_html;
    $html .= '</div>';
    
    return $html;
}

function bst_render_actions_tile_content($booking) {
    if (!$booking || !$booking->id) {
        return '<div class="tile-view-content"><p>No actions available for new bookings.</p></div>';
    }
    
    $html = '<div class="tile-view-content">';
    $html .= '<p style="color: #666; font-style: italic;">No actions defined at this time</p>';
    $html .= '</div>';
    
    return $html;
}
