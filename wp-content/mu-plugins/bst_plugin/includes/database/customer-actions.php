<?php

/**
 * Centralized Customer CRUD Operations
 * 
 * This file contains all the centralized functions for customer database operations.
 * All customer creation, updates, and deletions should go through these functions
 * to ensure consistency, proper error handling, and audit trail maintenance.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Create a new customer record
 * 
 * @param array $customer_data Customer data array with keys:
 *   - first_name, last_name, partner_first, partner_last, email, phone, credit, data_source, notes
 * @return array Result array with success status, customer_id (if successful), and error message (if failed)
 */
function bst_create_customer($customer_data) {
    global $wpdb;
    
    $result = array(
        'success' => false,
        'customer_id' => null,
        'error' => ''
    );
    
    try {
        $table = $wpdb->prefix . 'bst_customers';
        
        // Sanitize and prepare data
        $data = array(
            'first_name' => sanitize_text_field($customer_data['first_name'] ?? ''),
            'last_name' => sanitize_text_field($customer_data['last_name'] ?? ''),
            'partner_first' => sanitize_text_field($customer_data['partner_first'] ?? ''),
            'partner_last' => sanitize_text_field($customer_data['partner_last'] ?? ''),
            'email' => sanitize_email($customer_data['email'] ?? ''),
            'phone' => sanitize_text_field($customer_data['phone'] ?? ''),
            'credit' => sanitize_text_field($customer_data['credit'] ?? ''),
            'data_source' => sanitize_text_field($customer_data['data_source'] ?? ''),
            'notes' => sanitize_textarea_field($customer_data['notes'] ?? '')
        );
        
        // Add audit fields using shared utility
        $data = bst_add_audit_fields_create($data);
        
        // Insert customer
        $insert_result = $wpdb->insert($table, $data);
        
        if ($insert_result !== false) {
            $customer_id = $wpdb->insert_id;
            $result['success'] = true;
            $result['customer_id'] = $customer_id;
            
            // Trigger action hook for potential future integrations
            do_action('bst_customer_created', $customer_id, 'create');
            
            error_log(sprintf('BST Customer Created: ID %d by %s', $customer_id, bst_get_current_user_identifier()));
        } else {
            $result['error'] = 'Failed to insert customer: ' . $wpdb->last_error;
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Customer Create Exception: ' . $e->getMessage());
    }
    
    return $result;
}

/**
 * Update an existing customer record
 * 
 * @param int $customer_id Customer ID to update
 * @param array $customer_data Customer data array with updated values
 * @return array Result array with success status and error message (if failed)
 */
function bst_update_customer($customer_id, $customer_data) {
    global $wpdb;
    
    $result = array(
        'success' => false,
        'error' => ''
    );
    
    try {
        $table = $wpdb->prefix . 'bst_customers';
        
        // Validate customer exists
        $existing_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d",
            $customer_id
        ));
        
        if (!$existing_customer) {
            $result['error'] = 'Customer not found';
            return $result;
        }
        
        // Sanitize and prepare data - only include fields that are actually provided
        $data = array();
        
        // Only add fields to update array if they're provided in the input
        if (isset($customer_data['first_name'])) {
            $data['first_name'] = sanitize_text_field($customer_data['first_name']);
        }
        if (isset($customer_data['last_name'])) {
            $data['last_name'] = sanitize_text_field($customer_data['last_name']);
        }
        if (isset($customer_data['partner_first'])) {
            $data['partner_first'] = sanitize_text_field($customer_data['partner_first']);
        }
        if (isset($customer_data['partner_last'])) {
            $data['partner_last'] = sanitize_text_field($customer_data['partner_last']);
        }
        if (isset($customer_data['email'])) {
            $data['email'] = sanitize_email($customer_data['email']);
        }
        if (isset($customer_data['phone'])) {
            $data['phone'] = sanitize_text_field($customer_data['phone']);
        }
        if (isset($customer_data['credit'])) {
            $data['credit'] = sanitize_text_field($customer_data['credit']);
        }
        if (isset($customer_data['data_source'])) {
            $data['data_source'] = sanitize_text_field($customer_data['data_source']);
        }
        if (isset($customer_data['notes'])) {
            $data['notes'] = sanitize_textarea_field($customer_data['notes']);
        }
        
        // Debug: Log which fields are being updated
        error_log('BST Update Customer - Fields being updated: ' . print_r(array_keys($data), true));
        
        // Add audit fields using shared utility
        $data = bst_add_audit_fields_update($data);
        
        // Update customer
        $update_result = $wpdb->update(
            $table,
            $data,
            array('id' => $customer_id),
            null,
            array('%d')
        );
        
        if ($update_result !== false) {
            $result['success'] = true;
            
            // Trigger action hook for potential future integrations
            do_action('bst_customer_updated', $customer_id, 'update');
            
            error_log(sprintf('BST Customer Updated: ID %d by %s', $customer_id, bst_get_current_user_identifier()));
        } else {
            $result['error'] = 'Failed to update customer: ' . $wpdb->last_error;
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Customer Update Exception: ' . $e->getMessage());
    }
    
    return $result;
}

/**
 * Delete a customer by ID
 * 
 * @param int $customer_id Customer ID to delete
 * @return array Result array with success status and error message (if failed)
 */
function bst_delete_customer_by_id($customer_id) {
    global $wpdb;
    
    $result = array(
        'success' => false,
        'error' => ''
    );
    
    try {
        $table = $wpdb->prefix . 'bst_customers';
        
        // Validate customer exists and get info for logging
        $existing_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, first_name, last_name FROM $table WHERE id = %d",
            $customer_id
        ));
        
        if (!$existing_customer) {
            $result['error'] = 'Customer not found';
            return $result;
        }
        
        // Delete customer
        $delete_result = $wpdb->delete(
            $table,
            array('id' => $customer_id),
            array('%d')
        );
        
        if ($delete_result !== false && $delete_result > 0) {
            $result['success'] = true;
            
            // Trigger action hook for potential future integrations
            do_action('bst_customer_deleted', $customer_id, 'delete');
            
            $customer_name = trim($existing_customer->first_name . ' ' . $existing_customer->last_name);
            error_log(sprintf('BST Customer Deleted: ID %d (%s) by %s', $customer_id, $customer_name, bst_get_current_user_identifier()));
        } else {
            $result['error'] = 'Failed to delete customer or customer not found';
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Customer Delete Exception: ' . $e->getMessage());
    }
    
    return $result;
}

/**
 * Delete customers matching specific criteria
 * 
 * @param string $where_clause SQL WHERE clause (without "WHERE" keyword)
 * @param array $where_params Parameters for the WHERE clause
 * @return array Result array with success status, deleted count, and error message (if failed)
 */
function bst_delete_customers_by_criteria($where_clause, $where_params = array()) {
    global $wpdb;
    
    $result = array(
        'success' => false,
        'deleted_count' => 0,
        'error' => ''
    );
    
    try {
        $table = $wpdb->prefix . 'bst_customers';
        
        // Get count before deletion for logging
        $count_query = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        if (!empty($where_params)) {
            $count_before = $wpdb->get_var($wpdb->prepare($count_query, $where_params));
        } else {
            $count_before = $wpdb->get_var($count_query);
        }
        
        // Delete customers matching criteria
        $delete_query = "DELETE FROM $table WHERE $where_clause";
        if (!empty($where_params)) {
            $delete_result = $wpdb->query($wpdb->prepare($delete_query, $where_params));
        } else {
            $delete_result = $wpdb->query($delete_query);
        }
        
        if ($delete_result !== false) {
            $result['success'] = true;
            $result['deleted_count'] = $delete_result;
            
            // Trigger action hook for potential future integrations
            do_action('bst_customers_bulk_deleted', $delete_result, 'bulk_delete_criteria');
            
            error_log(sprintf('BST Customers Bulk Delete: %d customers deleted by %s (criteria: %s)', $delete_result, bst_get_current_user_identifier(), $where_clause));
        } else {
            $result['error'] = 'Failed to delete customers: ' . $wpdb->last_error;
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Customer Bulk Delete Criteria Exception: ' . $e->getMessage());
    }
    
    return $result;
}

/**
 * Get customer by ID
 * 
 * @param int $customer_id Customer ID to retrieve
 * @return array Result array with success status, customer data (if found), and error message (if failed)
 */
function bst_get_customer_by_id($customer_id) {
    global $wpdb;
    
    $result = array(
        'success' => false,
        'customer' => null,
        'error' => ''
    );
    
    try {
        $table = $wpdb->prefix . 'bst_customers';
        
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $customer_id
        ));
        
        if ($customer) {
            $result['success'] = true;
            $result['customer'] = $customer;
        } else {
            $result['error'] = 'Customer not found';
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Customer Get Exception: ' . $e->getMessage());
    }
    
    return $result;
}

// =============================================================================
// AJAX Handler Functions (refactored to use centralized functions)
// =============================================================================

/**
 * AJAX handler for saving customers (create/update)
 * Refactored to use centralized CRUD functions
 */
function bst_ajax_save_customer() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    check_ajax_referer('customer_form', 'customer_form_nonce');
    
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    
    $customer_data = array(
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'partner_first' => $_POST['partner_first'] ?? '',
        'partner_last' => $_POST['partner_last'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'credit' => $_POST['credit'] ?? '',
        'data_source' => $_POST['data_source'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    );
    
    if ($customer_id) {
        // Update existing customer
        $result = bst_update_customer($customer_id, $customer_data);
        if ($result['success']) {
            wp_send_json_success(array('customer_id' => $customer_id));
        } else {
            wp_send_json_error($result['error']);
        }
    } else {
        // Create new customer
        $result = bst_create_customer($customer_data);
        if ($result['success']) {
            wp_send_json_success(array('customer_id' => $result['customer_id']));
        } else {
            wp_send_json_error($result['error']);
        }
    }
}

/**
 * AJAX handler for deleting a single customer
 * Refactored to use centralized CRUD functions
 */
function bst_ajax_delete_customer() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    check_ajax_referer('delete_customer_nonce');
    
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    
    if (!$customer_id) {
        wp_send_json_error('Invalid customer ID');
        return;
    }
    
    $result = bst_delete_customer_by_id($customer_id);
    
    if ($result['success']) {
        wp_send_json_success(array('message' => 'Customer deleted successfully'));
    } else {
        wp_send_json_error($result['error']);
    }
}

// Register AJAX handlers
add_action('wp_ajax_save_customer', 'bst_ajax_save_customer');
add_action('wp_ajax_delete_customer', 'bst_ajax_delete_customer');
