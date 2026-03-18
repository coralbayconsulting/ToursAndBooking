<?php
/**
 * BST Plugin Data Import Handlers
 * 
 * Handles various data import operations for the BST Plugin
 * including paper bookings and other bulk data imports.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// #region Paper Bookings Import

/**
 * Helper function to format phone numbers
 * Removes all formatting except the initial +
 * If no +, prepends +1 for USA/CAN
 */
function bst_format_phone_number($phone) {
    if (empty($phone) || $phone === 'none') {
        return null;
    }
    
    $phone = trim($phone);
    if (empty($phone)) {
        return null;
    }
    
    // Check if phone starts with +
    if (substr($phone, 0, 1) === '+') {
        // Keep the + and remove all other non-numeric characters
        $formatted = '+' . preg_replace('/[^0-9]/', '', substr($phone, 1));
    } else {
        // Remove all non-numeric characters and prepend +1
        $numeric_only = preg_replace('/[^0-9]/', '', $phone);
        $formatted = '+1' . $numeric_only;
    }
    
    return $formatted;
}

/**
 * Admin handler to import paper bookings from tab-delimited file
 */
add_action('admin_post_bst_import_paper_bookings', 'bst_import_paper_bookings_handler');
function bst_import_paper_bookings_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    if (!isset($_FILES['paper_bookings_file']) || $_FILES['paper_bookings_file']['error'] !== UPLOAD_ERR_OK) {
        wp_redirect(admin_url('admin.php?page=bst-tour-bookings&paper_import=error&message=file_upload_failed'));
        exit;
    }
    
    $file_path = $_FILES['paper_bookings_file']['tmp_name'];
    $file_content = file_get_contents($file_path);
    
    if ($file_content === false) {
        wp_redirect(admin_url('admin.php?page=bst-tour-bookings&paper_import=error&message=file_read_failed'));
        exit;
    }
    
    // Detect file encoding and convert to UTF-8 if needed
    $detected_encoding = mb_detect_encoding($file_content, ['UTF-8', 'UTF-16', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1', 'Windows-1252'], true);
    
    // Convert from detected encoding to UTF-8
    if ($detected_encoding && $detected_encoding !== 'UTF-8') {
        $file_content = mb_convert_encoding($file_content, 'UTF-8', $detected_encoding);
    }
    
    $lines = explode("\n", $file_content);
    if (count($lines) < 2) {
        wp_redirect(admin_url('admin.php?page=bst-tour-bookings&paper_import=error&message=no_data'));
        exit;
    }
    
    global $wpdb;
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    $error_details = array();
    
    // Skip header row
    $header = array_shift($lines);        foreach ($lines as $line_num => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Skip lines that are obviously incomplete (like just "USD")
        if (strlen($line) < 20) continue;
        
        $fields = explode("\t", $line);
        if (count($fields) < 56) { // Updated field count to match current file format
            $errors++;
            $guest_name = (isset($fields[0]) && isset($fields[1])) ? trim($fields[0]) . ' ' . trim($fields[1]) : 'Unknown';
            $error_details[] = "Line " . ($line_num + 2) . " ({$guest_name}): Only " . count($fields) . " fields, expected 56. Line content: '" . substr($line, 0, 100) . "'";
            continue;
        }
        
        // Parse fields according to the column order in the file
        $guest1_first_name = trim($fields[0]) ?: null;
        $guest1_last_name = trim($fields[1]) ?: null;
        $guest1_phone = bst_format_phone_number($fields[2]);
        // Parse email field but keep original for customer lookup
        $guest1_email_raw = trim($fields[3]); // Keep original for customer lookup
        $guest1_email = ($guest1_email_raw === 'none' || $guest1_email_raw === '') ? null : $guest1_email_raw;
        $guest1_address_line1 = trim($fields[4]) ?: null;
        $guest1_city = trim($fields[5]) ?: null;
        $guest1_state_province = trim($fields[6]) ?: null;
        $guest1_postal_code = trim($fields[7]) ?: null;
        $guest1_country = trim($fields[8]) ?: null;
        $guest1_shirt_size = trim($fields[9]) ?: null;
        $guest1_emergency_contact_name = trim($fields[10]) ?: null;
        $guest1_emergency_contact_phone = bst_format_phone_number($fields[11]);
        $guest1_emergency_contact_email = (trim($fields[12]) === 'none' || trim($fields[12]) === '') ? null : trim($fields[12]);
        $guest2_first_name = trim($fields[13]) ?: null;
        $guest2_last_name = trim($fields[14]) ?: null;
        $guest2_phone = bst_format_phone_number($fields[15]);
        $guest2_email = (trim($fields[16]) === 'none' || trim($fields[16]) === '') ? null : trim($fields[16]);
        $guest2_shirt_size = trim($fields[17]) ?: null;
        $guest2_emergency_contact_name = trim($fields[18]) ?: null;
        $guest2_emergency_contact_phone = bst_format_phone_number($fields[19]);
        $guest2_emergency_contact_email = (trim($fields[20]) === 'none' || trim($fields[20]) === '') ? null : trim($fields[20]);
        $tour_id = intval($fields[21]) ?: null;
        $tour_text = trim($fields[22]) ?: null;
        $tour_date_id = intval($fields[23]) ?: null;
        $tour_date_text = trim($fields[24]) ?: null;
        $tour_package_id = intval($fields[25]) ?: null;
        $tour_package_text = trim($fields[26]) ?: null;
        $package_people = intval($fields[27]) ?: 0;
        $package_rooms = floatval($fields[28]) ?: 0; // Changed to floatval to handle 0.5
        $package_vehicles = intval($fields[29]) ?: 0;
        $vehicle_choices = intval($fields[30]) ?: 0;
        $vehicle1 = trim($fields[31]) ?: null;
        $vehicle2 = trim($fields[32]) ?: null;
        
        // Ensure vehicle data is properly UTF-8 encoded and within length limits
        if (!empty($vehicle1)) {
            // Since we've already converted the file to UTF-8, just ensure length limits
            if (mb_strlen($vehicle1, 'UTF-8') > 255) {
                $vehicle1 = mb_substr($vehicle1, 0, 255, 'UTF-8');
            }
        }
        
        if (!empty($vehicle2)) {
            // Since we've already converted the file to UTF-8, just ensure length limits
            if (mb_strlen($vehicle2, 'UTF-8') > 255) {
                $vehicle2 = mb_substr($vehicle2, 0, 255, 'UTF-8');
            }
        }
        $tour_price = floatval(str_replace(['"', ','], '', $fields[33])) ?: 0;
        $tour_currency = trim($fields[34]) ?: 'USD';
        $coupon_code = trim($fields[35]) ?: null;
        $coupon_amount = floatval(str_replace(['"', ','], '', $fields[36])) ?: 0;
        $net_tour_price = floatval(str_replace(['"', ','], '', $fields[37])) ?: 0;
        $additional_charge = floatval(str_replace(['"', ','], '', $fields[38])) ?: 0;
        $total_paid = floatval(str_replace(['"', ','], '', $fields[39])) ?: 0;
        $balance_due = floatval(str_replace(['"', ','], '', $fields[40])) ?: 0;
        $deposit_payment_method = trim($fields[41]) ?: null;
        $deposit_payment_amount = floatval(str_replace(['"', ','], '', $fields[42])) ?: 0;
        
        // Convert date from MM/DD/YYYY to YYYY-MM-DD
        $deposit_payment_date = null;
        if (!empty(trim($fields[43]))) {
            $date_parts = explode('/', trim($fields[43]));
            if (count($date_parts) === 3) {
                $deposit_payment_date = sprintf('%04d-%02d-%02d', $date_parts[2], $date_parts[0], $date_parts[1]);
            }
        }
        
        $balance_payment_method = trim($fields[44]) ?: null;
        $balance_payment_amount = floatval(str_replace(['"', ','], '', $fields[45])) ?: 0;
        $additional_payment_method = trim($fields[46]) ?: null;
        $additional_payment_amount = floatval(str_replace(['"', ','], '', $fields[47])) ?: 0;
        $how_heard = trim($fields[48]) ?: null;
        $how_heard_other = trim($fields[49]) ?: null;
        $motor_club = trim($fields[50]) ?: null;
        $data_source = trim($fields[51]) ?: 'Paper Import';
        $booking_status = trim($fields[52]) ?: 'Completed';
        $booking_method = trim($fields[53]) ?: 'Offline';
        $booking_commission_percent = floatval($fields[54]);
        $booking_commission_reason = trim($fields[55]) ?: null;
        
        // Create or get customer (always try, even if email is 'none' or null)
        $customer_id = null;
        if ($guest1_first_name && $guest1_last_name) {
            $customer_id = bst_create_or_get_customer(
                $guest1_first_name,
                $guest1_last_name,
                $guest2_first_name,
                $guest2_last_name,
                $guest1_email_raw, // Use raw email value for customer lookup
                $guest1_phone,
                $data_source,
                $how_heard // Pass how_heard for credit determination
            );
        }
        
        // Set created/updated fields
        $created_by = is_user_logged_in() ? wp_get_current_user()->user_login : 'Admin';
        $created_date = $deposit_payment_date ?: current_time('mysql'); // Use deposit date as created date
        $updated_by = $created_by;
        $updated_date = current_time('mysql'); // Always use current time for updated_date
        
        // Insert booking record
        // Ensure the database connection is using UTF-8
        $wpdb->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        // Use centralized booking creation function
        $booking_data = array(
            'customer_id' => $customer_id,
            'guest1_first_name' => $guest1_first_name,
            'guest1_last_name' => $guest1_last_name,
            'guest1_phone' => $guest1_phone,
            'guest1_email' => $guest1_email,
            'guest1_address_line1' => $guest1_address_line1,
            'guest1_city' => $guest1_city,
            'guest1_state_province' => $guest1_state_province,
            'guest1_postal_code' => $guest1_postal_code,
            'guest1_country' => $guest1_country,
            'guest1_shirt_size' => $guest1_shirt_size,
            'guest1_emergency_contact_name' => $guest1_emergency_contact_name,
            'guest1_emergency_contact_phone' => $guest1_emergency_contact_phone,
            'guest1_emergency_contact_email' => $guest1_emergency_contact_email,
            'guest2_first_name' => $guest2_first_name,
            'guest2_last_name' => $guest2_last_name,
            'guest2_phone' => $guest2_phone,
            'guest2_email' => $guest2_email,
            'guest2_shirt_size' => $guest2_shirt_size,
            'guest2_emergency_contact_name' => $guest2_emergency_contact_name,
            'guest2_emergency_contact_phone' => $guest2_emergency_contact_phone,
            'guest2_emergency_contact_email' => $guest2_emergency_contact_email,
            'how_heard' => $how_heard,
            'how_heard_other' => $how_heard_other,
            'motor_club' => $motor_club,
            'tour_id' => $tour_id,
            'tour_text' => $tour_text,
            'tour_date_id' => $tour_date_id,
            'tour_date_text' => $tour_date_text,
            'tour_package_id' => $tour_package_id,
            'tour_package_text' => $tour_package_text,
            'package_people' => $package_people,
            'package_rooms' => $package_rooms,
            'package_vehicles' => $package_vehicles,
            'vehicle_choices' => $vehicle_choices,
            'vehicle1' => $vehicle1,
            'vehicle2' => $vehicle2,
            'tour_price' => $tour_price,
            'tour_currency' => $tour_currency,
            'coupon_code' => $coupon_code,
            'coupon_amount' => $coupon_amount,
            'net_tour_price' => $net_tour_price,
            'additional_charge' => $additional_charge,
            'total_paid' => $total_paid,
            'balance_due' => $balance_due,
            'deposit_payment_method' => $deposit_payment_method,
            'deposit_payment_amount' => $deposit_payment_amount,
            'deposit_payment_date' => $deposit_payment_date,
            'balance_payment_method' => $balance_payment_method,
            'balance_payment_amount' => $balance_payment_amount,
            'additional_payment_method' => $additional_payment_method,
            'additional_payment_amount' => $additional_payment_amount,
            'data_source' => $data_source,
            'booking_status' => $booking_status,
            'booking_method' => $booking_method,
            'booking_commission_percent' => $booking_commission_percent,
            'booking_commission_reason' => $booking_commission_reason
        );
        
        $create_result = bst_create_tour_booking($booking_data);
        
        if ($create_result['success']) {
            $imported++;
        } else {
            $errors++;
            $guest_name = bst_format_guest_name($guest1_first_name, $guest1_last_name, $guest2_first_name ?? '', $guest2_last_name ?? '');
            $error = $create_result['error'];
            
            // Add more specific error info
            $error_msg = "Line " . ($line_num + 2) . " ({$guest_name}): Booking creation failed - {$error}";
            
            // Check if it's a vehicle-related error
            if (strpos($error, 'vehicle') !== false || strpos($error, 'Data too long') !== false) {
                $error_msg .= " | Vehicle1 length: " . strlen($vehicle1) . " | Vehicle2 length: " . strlen($vehicle2);
                if (!empty($vehicle1)) $error_msg .= " | Vehicle1: '" . substr($vehicle1, 0, 50) . "'";
                if (!empty($vehicle2)) $error_msg .= " | Vehicle2: '" . substr($vehicle2, 0, 50) . "'";
            }
            
            $error_details[] = $error_msg;
        }
    }
    
    // Store error details in a transient for display
    if (!empty($error_details)) {
        set_transient('bst_import_errors', $error_details, 300); // Store for 5 minutes
    }
    
    $error_param = !empty($error_details) ? '&has_details=1' : '';
    wp_redirect(admin_url("admin.php?page=bst-tour-bookings&paper_import=done&imported={$imported}&skipped={$skipped}&errors={$errors}{$error_param}"));
    exit;
}

/**
 * Admin handler to delete paper bookings
 */
add_action('admin_post_bst_delete_paper_bookings', 'bst_delete_paper_bookings_handler');
function bst_delete_paper_bookings_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    // Use centralized bulk delete function
    $result = bst_bulk_delete_tour_bookings(
        "data_source LIKE %s OR data_source LIKE %s", 
        array('%Paper%', '%Bill - Offline%'), 
        'paper_bulk_delete'
    );
    
    if ($result['success']) {
        wp_redirect(admin_url("admin.php?page=bst-tour-bookings&paper_delete=done&deleted={$result['deleted_count']}"));
    } else {
        error_log('Paper bookings bulk delete failed: ' . $result['error']);
        wp_redirect(admin_url("admin.php?page=bst-tour-bookings&paper_delete=error"));
    }
    exit;
}

/**
 * Helper function to create or get customer for paper booking import operations
 */
function bst_create_or_get_customer($first_name, $last_name, $partner_first, $partner_last, $email, $phone, $data_source, $how_heard = null) {
    global $wpdb;
    
    // Check if customer already exists by email (if email is provided and not 'none')
    if (!empty($email) && $email !== 'none') {
        $existing_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_customers WHERE email = %s",
            $email
        ));
        
        if ($existing_customer) {
            return $existing_customer->id;
        }
        
        // If no customer found by email, also try by name with null/empty email
        $existing_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_customers WHERE first_name = %s AND last_name = %s AND (email IS NULL OR email = '' OR email = 'none')",
            $first_name,
            $last_name
        ));
        
        if ($existing_customer) {
            return $existing_customer->id;
        }
    } else {
        // If no email or email is 'none', search by name with any empty-ish email
        $existing_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_customers WHERE first_name = %s AND last_name = %s AND (email IS NULL OR email = '' OR email = 'none')",
            $first_name,
            $last_name
        ));
        
        if ($existing_customer) {
            return $existing_customer->id;
        }
    }
    
    // For paper booking imports: Determine credit based on how_heard
    // Default is "Bill" unless specifically referred by Claudio Angeletti or Wayne Wilson
    $credit = 'Bill'; // Default credit for paper bookings
    if (!empty($how_heard)) {
        if (stripos($how_heard, 'Referred by Claudio Angeletti') !== false) {
            $credit = 'Claudio';
        } elseif (stripos($how_heard, 'Referred by Wayne Wilson') !== false) {
            $credit = 'Wayne';
        }
    }
    
    // Create new customer using centralized function
    $customer_data = array(
        'first_name' => $first_name,
        'last_name' => $last_name,
        'partner_first' => $partner_first,
        'partner_last' => $partner_last,
        'email' => $email,
        'phone' => $phone,
        'credit' => $credit,
        'data_source' => $data_source
    );
    
    $create_result = bst_create_customer($customer_data);
    if ($create_result['success']) {
        return $create_result['customer_id'];
    } else {
        error_log('BST Customer Creation Failed in import: ' . $create_result['error']);
        return null;
    }
}

// #endregion

// #region Additional Import Handlers

// Future import handlers can be added here as needed
// Example: Customer import, tour data import, etc.

// #endregion

// #region General Import Updates Handler

/**
 * Custom logging functions for import operations
 */

/**
 * Log import messages with multiple destinations
 * 
 * @param string $message The message to log
 * @param string $level Log level (info, error, warning)
 */
function bst_log_import_message($message, $level = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $formatted_message = "[{$timestamp}] BST Import {$level}: {$message}";
    
    // Always log to WordPress debug log (if enabled)
    error_log($formatted_message);
    
    // Also log to a dedicated import log file
    $import_log_file = WP_CONTENT_DIR . '/bst-import.log';
    file_put_contents($import_log_file, $formatted_message . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    // For errors, also try to log to system error log if different from WordPress
    if ($level === 'error' && function_exists('syslog')) {
        syslog(LOG_ERR, "BST Import Error: {$message}");
    }
}

/**
 * Log import errors specifically
 */
function bst_log_import_error($message) {
    bst_log_import_message($message, 'error');
}

/**
 * Log import warnings
 */
function bst_log_import_warning($message) {
    bst_log_import_message($message, 'warning');
}

/**
 * Admin handler to import updates from tab-delimited file
 * Allows updating any fields in the bst_tour_booking table based on column headers
 */
add_action('admin_post_bst_import_updates', 'bst_import_updates_handler');

function bst_import_updates_handler() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['import_nonce'], 'bst_import_updates')) {
        wp_die('Security check failed', 'Error', array('response' => 403));
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        wp_redirect(add_query_arg(['page' => 'bst-tour-bookings', 'import_error' => 'file_upload'], admin_url('admin.php')));
        exit;
    }
    
    $file_path = $_FILES['import_file']['tmp_name'];
    
    if (!file_exists($file_path)) {
        wp_redirect(add_query_arg(['page' => 'bst-tour-bookings', 'import_error' => 'file_not_found'], admin_url('admin.php')));
        exit;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'bst_tour_booking';
    
    // Read file
    $file_content = file_get_contents($file_path);
    $lines = explode("\n", $file_content);
    
    if (empty($lines)) {
        wp_redirect(add_query_arg(['page' => 'bst-tour-bookings', 'import_error' => 'empty_file'], admin_url('admin.php')));
        exit;
    }
    
    // Parse headers (first line)
    $headers = str_getcsv(trim($lines[0]), "\t");
    
    // Clean headers to remove any BOM or extra whitespace
    $headers = array_map(function($header) {
        // Remove BOM if present
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        // Trim whitespace
        return trim($header);
    }, $headers);
    
    error_log('Data import parsed headers: ' . implode(' | ', $headers));
    
    if (empty($headers)) {
        bst_log_import_error('No headers found in import file');
        wp_redirect(add_query_arg(['page' => 'bst-tour-bookings', 'import_error' => 'no_headers'], admin_url('admin.php')));
        exit;
    }
    
    // Get all column names from the table
    $table_columns = $wpdb->get_col("DESCRIBE $table_name", 0);
    
    // Validate headers against table columns, ignoring export_reason
    $valid_headers = array();
    $invalid_headers = array();
    foreach ($headers as $header) {
        $header = trim($header);
        
        // Skip export_reason column - it's not a database field
        if ($header === 'export_reason') {
            continue;
        }
        
        if (in_array($header, $table_columns)) {
            $valid_headers[] = $header;
        } else {
            $invalid_headers[] = $header;
        }
    }
    
    // If there are any invalid headers (other than export_reason), abort import
    if (!empty($invalid_headers)) {
        $error_message = 'Invalid field names found: ' . implode(', ', $invalid_headers);
        wp_redirect(add_query_arg([
            'page' => 'bst-tour-bookings', 
            'import_error' => 'invalid_field_names',
            'message' => urlencode($error_message)
        ], admin_url('admin.php')));
        exit;
    }
    
    if (empty($valid_headers)) {
        bst_log_import_error('No valid headers found. Headers: ' . implode(', ', $headers) . '. Valid columns: ' . implode(', ', $table_columns));
        wp_redirect(add_query_arg(['page' => 'bst-tour-bookings', 'import_error' => 'no_valid_headers'], admin_url('admin.php')));
        exit;
    }
    
    // Log import start with better logging
    bst_log_import_message('Data import starting with valid headers: ' . implode(', ', $valid_headers) . '. Invalid headers: ' . implode(', ', $invalid_headers));
    
    $updated_count = 0;
    $created_count = 0;
    $error_count = 0;
    
    // Process data rows
    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;
        
        $data = str_getcsv($line, "\t");
        
        // Pad the data array to match headers count if needed (handles missing trailing empty columns)
        while (count($data) < count($headers)) {
            $data[] = '';
        }
        
        // Ensure we don't have more columns than headers
        if (count($data) > count($headers)) {
            bst_log_import_error('Row ' . ($i + 1) . ' has more columns than headers. Expected: ' . count($headers) . ', Got: ' . count($data));
            $error_count++;
            continue;
        }
        
        // Build update data
        $update_data = array();
        $record_id = null;
        
        for ($j = 0; $j < count($headers); $j++) {
            $header = trim($headers[$j]);
            
            // Skip export_reason column - it's not a database field
            if ($header === 'export_reason') {
                continue;
            }
            
            if (in_array($header, $valid_headers)) {
                $value = trim($data[$j]);
                
                if ($header === 'id') {
                    $record_id = !empty($value) ? intval($value) : null;
                } else {
                    $update_data[$header] = $value;
                }
            }
        }
        
        if (empty($update_data)) {
            bst_log_import_error('No valid update data for row ' . ($i + 1) . '. Line: ' . $line);
            $error_count++;
            continue;
        }
        
        // Add timestamps
        $update_data['updated_by'] = wp_get_current_user()->user_login;
        $update_data['updated_date'] = current_time('mysql');
        
        try {
            if ($record_id && $record_id > 0) {
                // Update existing record using centralized function
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE id = %d", $record_id));
                if ($existing) {
                    $result = bst_update_tour_booking($record_id, $update_data, 'data_import_update');
                    if ($result['success']) {
                        $updated_count++;
                    } else {
                        bst_log_import_error('Data import update failed for ID ' . $record_id . ': ' . $result['error']);
                        $error_count++;
                    }
                } else {
                    // ID specified but record doesn't exist, create new with that ID
                    $update_data['id'] = $record_id;
                    $result = bst_create_tour_booking($update_data, 'data_import_insert');
                    if ($result['success']) {
                        $created_count++;
                    } else {
                        bst_log_import_error('Data import insert failed for ID ' . $record_id . ': ' . $result['error']);
                        $error_count++;
                    }
                }
            } else {
                // No ID specified, create new record
                $result = bst_create_tour_booking($update_data, 'data_import_new');
                if ($result['success']) {
                    $created_count++;
                } else {
                    bst_log_import_error('Data import create failed: ' . $result['error']);
                    $error_count++;
                }
            }
        } catch (Exception $e) {
            bst_log_import_error('Data import exception for row ' . ($i + 1) . ': ' . $e->getMessage());
            $error_count++;
        }
    }
    
    // Log completion summary
    bst_log_import_message("Import completed: {$updated_count} updated, {$created_count} created, {$error_count} errors");
    
    // Redirect with results
    $redirect_args = array(
        'page' => 'bst-tour-bookings',
        'import_success' => '1',
        'updated' => $updated_count,
        'created' => $created_count,
        'errors' => $error_count
    );
    
    wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
    exit;
}

// #endregion
