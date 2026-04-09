<?php

// --- AJAX Handlers for Tour Selection Dropdowns and Related Tour Data ---
// Canonical module: all tour/tour-date dropdown and related AJAX live here (legacy ajax-handlers.php removed).

/**
 * This file contains AJAX handlers for frontend tour selection functionality:
 * - Tour type, tour, and tour date dropdown population
 * - Package pricing and vehicle data retrieval
 * - Source code title lookup
 * - Package details from WordPress options
 * 
 * These functions are used by frontend forms and tour selection interfaces,
 * not specifically tied to Gravity Forms.
 */

/**
 * Populate tour type dropdown choices (used by various forms)
 * This function can be called by any form that needs tour type options
 */
function populate_tour_type_dropdown($form) {
    foreach ($form['fields'] as &$field) {
        if (!empty($field->cssClass) && strpos($field->cssClass, 'tourtypedropdown') !== false) {
            $choices = array();

            // Add a blank option as the first itempackage
            $choices[] = array('text' => 'Select a Tour Type', 'value' => '');

            // Fetch 'tour-type' custom post type data
            $args = array(
                'post_type' => 'tour-type',
                'posts_per_page' => -1,
                'meta_key' => 'listing_sort_order',
                'orderby' => array(
                    'meta_value_num' => 'ASC',
                    'title' => 'ASC'
                )
            );

            $query = new WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $choices[] = array('text' => get_the_title(), 'value' => get_the_ID());
                }
                wp_reset_postdata();
            }

            $field->choices = $choices;
        }
    }
    return $form;
}

// Populate tours based on the selected tour type (AJAX)
add_action('wp_ajax_populate_tour_dropdown', 'populate_tour_dropdown');
add_action('wp_ajax_nopriv_populate_tour_dropdown', 'populate_tour_dropdown');
function populate_tour_dropdown() {

    // Check if the tour type ID is set
    if (!isset($_POST['tour_type_id'])) {
        wp_send_json_error('No tour type ID provided');
        return;
    }

    $tour_type_id = intval($_POST['tour_type_id']);

    // Fetch tours based on the selected tour type ID
    $args = array(
        'post_type' => 'tour',
        'posts_per_page' => -1,
        'meta_key' => 'listing_sort_order',
        'orderby' => array(
            'meta_value_num' => 'ASC',
            'title' => 'ASC'
        ),
        'meta_query' => array(
            array(
                'key' => 'tour_type',
                'value' => $tour_type_id,
                'compare' => '='
            )
        )
    );

    $query = new WP_Query($args);
    $data = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $price = get_field('price'); // Assuming 'price' is the custom field for the tour price
            $currency = get_field('currency'); // Get the tour's currency
            if (empty($currency)) {
                $currency = 'EUR'; // Default to EUR if not set
            }
            $data[] = array(
                'id' => get_the_ID(),
                'name' => get_the_title(),
                'price' => $price,
                'currency' => $currency
            );
        }
        wp_reset_postdata();
    }
    wp_send_json_success($data);
}

// Populate tour dates based on the selected tour (AJAX)
add_action('wp_ajax_populate_tour_date_dropdown', 'populate_tour_date_dropdown');
add_action('wp_ajax_nopriv_populate_tour_date_dropdown', 'populate_tour_date_dropdown');
function populate_tour_date_dropdown() {
    // Check if the tour ID is set
    if (!isset($_POST['tour_id'])) {
        wp_send_json_error('No tour ID provided');
        return;
    }

    $tour_id = intval($_POST['tour_id']);

    // Fetch tour dates based on the selected tour ID
    $args = array(
        'post_type' => 'tour-date',
        'posts_per_page' => -1,
        'meta_key' => 'start_date',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => 'tour', 
                'value' => $tour_id,
                'compare' => '='
            )
        )
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $availability = intval(get_field('available_slots'));

            // Use standardized tour date title - extract date range from last parentheses
            // Expected format: "Tour Name (from Location) (11-13 Aug 2026)" or "Tour Name (11-13 Aug 2026)"
            // Extract only the date portion (last parentheses containing date pattern)
            $date_text = get_the_title(); // fallback to full title
            if (preg_match('/\(([^()]+)\)\s*$/', get_the_title(), $matches)) {
                $date_text = $matches[1];
            }
            
            if ($availability <= 0) {
                $date_text .= ' (Sold Out)';
            }

            $data[] = array(
                'id' => get_the_ID(),
                'name' => $date_text
            );
        }
        wp_reset_postdata();
    }
    wp_send_json_success($data);
}

// AJAX handler to fetch package pricing data based on the selected tour
add_action('wp_ajax_get_package_pricing', 'get_package_pricing');
add_action('wp_ajax_nopriv_get_package_pricing', 'get_package_pricing');
function get_package_pricing() {
    // Check if the tour ID is set
    if (!isset($_POST['tour_id'])) {
        wp_send_json_error('No tour ID provided');
        return;
    }

    $tour_id = intval($_POST['tour_id']);
    $tour_date_id = isset($_POST['tour_date_id']) ? intval($_POST['tour_date_id']) : null;
    
    $data = array();

    // Return an empty array if the tour ID is 0
    if ($tour_id == 0) {
        wp_send_json_success($data);
        return;
    }

    // Get the tour's currency from POST parameter first, then database as fallback
    $currency_param = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : '';
    $tour_currency = get_field('currency', $tour_id);
    
    // Use the passed currency parameter if available, otherwise use database value
    if (!empty($currency_param)) {
        $tour_currency = $currency_param;
    } elseif (empty($tour_currency)) {
        $tour_currency = 'EUR'; // Default to EUR if not set
    }


    // Check available slots for the selected date if provided
    $available_slots = null;
    if ($tour_date_id) {
        // Get availability directly from the stored field
        $available_slots = intval(get_field('available_slots', $tour_date_id));
    }

    // Retrieve the base tour price and package additions
    $package_pricing = get_field('package_pricing', $tour_id);
    //$base_price = $package_pricing['base_price'];
    $package_prices = array(
        1 => $package_pricing['package_1'],
        2 => $package_pricing['package_2'],
        3 => $package_pricing['package_3'],
        4 => $package_pricing['package_4'],
        5 => $package_pricing['package_5'],
    );

    // Fetch global package names
    $package_names = array();
    for ($i = 1; $i <= 5; $i++) {
        $package_names[$i] = get_option('bst_package_' . $i . '_name', 'Package ' . $i);
    }

    // Get tour-type from taxonomy relationship (tour -> tour-type-code taxonomy -> tour-type post)
    $tour_type_id = null;
    $taxonomy_terms = get_the_terms($tour_id, 'tour-type-code');
    
    if ($taxonomy_terms && !is_wp_error($taxonomy_terms)) {
        $taxonomy_term = $taxonomy_terms[0];
        
        // Find the tour-type post that has the same taxonomy term
        $tour_type_posts = get_posts([
            'post_type' => 'tour-type',
            'tax_query' => [
                [
                    'taxonomy' => 'tour-type-code',
                    'field' => 'term_id',
                    'terms' => $taxonomy_term->term_id
                ]
            ],
            'posts_per_page' => 1
        ]);
        
        if (!empty($tour_type_posts)) {
            $tour_type_id = $tour_type_posts[0]->ID;
        }
    }
    
    $booking_limited_by = strtolower(get_field('booking_limited_by', $tour_type_id));
    
    // Map booking_limited_by to the corresponding package option suffix
    $capacity_key = match($booking_limited_by) {
        'people' => 'people',
        'rooms' => 'rooms',
        'vehicles' => 'vehicles',
    };

    // Create the data array
    for ($i = 1; $i <= 5; $i++) {
        $price = $package_prices[$i];
        
        // Skip packages with price of 0 or less
        if ($price <= 0) {
            continue;
        }
        
        $formatted_price = format_currency($price, $tour_currency);
        
        // Check if this package exceeds available slots (dynamic check based on booking capacity limit type)
        $text = $package_names[$i] . ' - ' . $formatted_price;
        if ($available_slots !== null) {
            // Get package capacity requirement from WordPress options based on booking limit type
            $package_capacity = intval(get_option('bst_package_' . $i . '_' . $capacity_key, 0));
            
            // If package requires more capacity than available slots, mark as unavailable
            if ($package_capacity > 0 && $package_capacity > $available_slots) {
                $text = $package_names[$i] . ' (Not Available)';
            }
        }
        
        $data[] = array(
            'data.id' => $i,
            'value' => $price,
            'text' => $text
        );
    }
    
    // Include currency information in the response for JavaScript to use
    $response = array(
        'packages' => $data,
        'currency' => $tour_currency,
        'currency_symbol' => $tour_currency === 'USD' ? '$' : '€'
    );
    
    wp_send_json_success($response);
}

// AJAX handler to fetch vehicle data based on the selected tour package
add_action('wp_ajax_get_vehicle_data', 'get_vehicle_data');
add_action('wp_ajax_nopriv_get_vehicle_data', 'get_vehicle_data');
function get_vehicle_data() {
    // Check if the tour ID and package price are set
    if (!isset($_POST['tour_id']) || !isset($_POST['package_id'])) {
        wp_send_json_error('No tour ID or package id provided');
        return;
    }

    $tour_id = intval($_POST['tour_id']);
    $selected_package_id = intval($_POST['package_id']);
    $tour_date_id        = isset($_POST['tour_date_id']) ? intval($_POST['tour_date_id']) : 0;
    
    // Get tour-type from taxonomy relationship
    $tour_type_id = null;
    $taxonomy_terms = get_the_terms($tour_id, 'tour-type-code');
    
    if ($taxonomy_terms && !is_wp_error($taxonomy_terms)) {
        $taxonomy_term = $taxonomy_terms[0];
        
        $tour_type_posts = get_posts([
            'post_type' => 'tour-type',
            'tax_query' => [
                [
                    'taxonomy' => 'tour-type-code',
                    'field' => 'term_id',
                    'terms' => $taxonomy_term->term_id
                ]
            ],
            'posts_per_page' => 1
        ]);
        
        if (!empty($tour_type_posts)) {
            $tour_type_id = $tour_type_posts[0]->ID;
        }
    }
    
    $vehicle_descriptor = get_field('vehicle_descriptor', $tour_type_id);
    
    // Get currency from POST data (passed from frontend)
    $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'EUR';


    // Fetch vehicle pricing data from the selected tour
    $vehicle_pricing = get_field('vehicle_pricing', $tour_id);
    $data = array();
    $vehicle_row_order = 0;
    // No need to add "Select a..." option here as it's static in HTML

    // Step through the classes
    if ($vehicle_pricing) {
        foreach ($vehicle_pricing as $vehicle) {
            $class = $vehicle['class'];
            $price = isset($vehicle['vehicle_price_addition']) ? floatval($vehicle['vehicle_price_addition']) : 0;
            
            if (empty($vehicle['vehicles']) || !is_array($vehicle['vehicles'])) {
                continue;
            }
            foreach ($vehicle['vehicles'] as $vehicle_item) {
                $linked_id = isset($vehicle_item['vehicle_id']) ? $vehicle_item['vehicle_id'] : 0;
                if (is_array($linked_id) && isset($linked_id['ID'])) {
                    $linked_id = (int) $linked_id['ID'];
                } else {
                    $linked_id = (int) $linked_id;
                }

                // Booking lists use the Vehicle CPT title only (repeater text field is legacy / being removed).
                if ($linked_id <= 0) {
                    continue;
                }
                $vp = get_post($linked_id);
                if (!$vp || 'vehicle' !== $vp->post_type) {
                    continue;
                }
                $vehicle_id = $linked_id;

                if ($vehicle_id > 0 && function_exists('bst_vehicle_is_available_for_booking')
                    && ! bst_vehicle_is_available_for_booking($vehicle_id)) {
                    /* Still list vehicles on this tour’s pricing so legacy tours stay understandable; cannot be booked. */
                    $vehicle_name = get_the_title($vehicle_id) . ' ' . __('(No longer available for assignment)', 'bst-plugin');
                    $data[]       = array(
                        'text'       => $vehicle_name,
                        'value'      => 0,
                        'price'      => 0,
                        'data-id'    => $class,
                        'vehicle_id' => $vehicle_id,
                        'unavailable' => true,
                        'retired'    => true,
                        '_order'     => $vehicle_row_order++,
                    );
                    continue;
                }

                $limited_unavailable = false;
                if ($tour_date_id > 0 && function_exists('bst_limited_vehicle_slots_remaining')) {
                    $remaining = bst_limited_vehicle_slots_remaining($tour_date_id, $vehicle_id);
                    if ($remaining !== null && $remaining <= 0) {
                        $limited_unavailable = true;
                    }
                }

                $formatted_price = '';
                if (!$limited_unavailable && $price != 0) {
                    $symbol = ($currency === 'USD') ? '$' : '€';
                    $abs_price = abs($price);
                    $sign = ($price > 0) ? '+' : '-';
                    $formatted_price = ' (' . $sign . $symbol . number_format($abs_price, 0) . ')';
                }

                if ($limited_unavailable) {
                    /* translators: appended to vehicle title when limited inventory is exhausted */
                    $vehicle_name = get_the_title($vehicle_id) . ' ' . __('(Unavailable)', 'bst-plugin');
                } else {
                    $vehicle_name = get_the_title($vehicle_id) . $formatted_price;
                }

                $data[] = array(
                    'text' => $vehicle_name,
                    'value' => $limited_unavailable ? 0 : $price, // Price for calculations; 0 when unavailable
                    'price' => $limited_unavailable ? 0 : $price,
                    'data-id' => $class, // Vehicle class ID
                    'vehicle_id' => $vehicle_id,
                    'unavailable' => $limited_unavailable,
                    '_order' => $vehicle_row_order++,
                );
            }
        }
    }

    if (count($data) > 1) {
        usort(
            $data,
            static function ($a, $b) {
                $ida = isset($a['vehicle_id']) ? (int) $a['vehicle_id'] : 0;
                $idb = isset($b['vehicle_id']) ? (int) $b['vehicle_id'] : 0;
                $sa = 0.0;
                $sb = 0.0;
                if ($ida && function_exists('get_field')) {
                    $v = get_field('listing_sort_order', $ida);
                    $sa = is_numeric($v) ? (float) $v : 0.0;
                }
                if ($idb && function_exists('get_field')) {
                    $v = get_field('listing_sort_order', $idb);
                    $sb = is_numeric($v) ? (float) $v : 0.0;
                }
                if ($sa !== $sb) {
                    return $sa <=> $sb;
                }
                return ((int) ($a['_order'] ?? 0)) <=> ((int) ($b['_order'] ?? 0));
            }
        );
    }

    foreach ($data as $i => $row) {
        unset($data[$i]['_order']);
    }

    wp_send_json_success($data);
}

// AJAX handler to get source code title by code
add_action('wp_ajax_bst_get_source_code_title', 'bst_get_source_code_title_ajax');
function bst_get_source_code_title_ajax() {
    check_ajax_referer('bst_tour_bookings_nonce', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    $source_code = sanitize_text_field($_POST['source_code'] ?? '');
    
    if (empty($source_code)) {
        wp_send_json_success('');
    }
    
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
        wp_send_json_success($post->post_title);
    }
    
    wp_send_json_success('');
}

// AJAX handler to get deposit settings for a tour
add_action('wp_ajax_get_tour_deposit_settings', 'get_tour_deposit_settings');
add_action('wp_ajax_nopriv_get_tour_deposit_settings', 'get_tour_deposit_settings');
function get_tour_deposit_settings() {
    if (!isset($_POST['tour_id'])) {
        wp_send_json_error('No tour ID provided');
        return;
    }

    $tour_id = $_POST['tour_id'];
    
    // If tour_id is not numeric, try to find the post by slug
    if (!is_numeric($tour_id)) {
        $post = get_page_by_path($tour_id, OBJECT, 'tour');
        if ($post) {
            $tour_id = $post->ID;
        }
    }
    
    $tour_id = intval($tour_id);
    
    $deposit_settings = array(
        'type' => get_field('deposit_type', $tour_id),
        'percent' => get_field('deposit_percent', $tour_id),
        'fixedSingle' => get_field('deposit_fixed_single', $tour_id),
        'fixedDouble' => get_field('deposit_fixed_double', $tour_id)
    );
    
    wp_send_json_success($deposit_settings);
}

// Get package details from WordPress options
add_action('wp_ajax_get_package_details', 'get_package_details');
add_action('wp_ajax_nopriv_get_package_details', 'get_package_details');
function get_package_details() {
    $package_id = sanitize_text_field($_POST['package_id']);
    
    if (empty($package_id)) {
        wp_send_json_error('No package ID provided');
        return;
    }
    
    // Get package details from WordPress options
    $package_name = get_option("bst_package_{$package_id}_name", '');
    $package_people = get_option("bst_package_{$package_id}_people", '1');
    $package_rooms = get_option("bst_package_{$package_id}_rooms", '1');
    $package_vehicles = get_option("bst_package_{$package_id}_vehicles", '1');
    
    $package_data = array(
        'name' => $package_name,
        'people' => $package_people,
        'rooms' => $package_rooms,
        'vehicles' => $package_vehicles
    );
    
    wp_send_json_success($package_data);
}
