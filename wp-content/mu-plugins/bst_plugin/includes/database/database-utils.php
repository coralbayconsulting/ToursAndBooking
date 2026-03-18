<?php

/**
 * Shared Database Utilities
 * 
 * This file contains shared utility functions for database operations
 * across all BST centralized CRUD functions to ensure consistency.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Get standardized current user identifier for audit trails
 * 
 * @return string User login or 'System' if no user logged in
 */
function bst_get_current_user_identifier() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        return $current_user->user_login ?: 'admin';
    }
    return 'System';
}

/**
 * Add standardized audit fields for record creation
 * 
 * @param array $data The data array to add audit fields to
 * @return array Data array with audit fields added
 */
function bst_add_audit_fields_create($data) {
    $user_identifier = bst_get_current_user_identifier();
    $current_time = current_time('mysql');
    
    $data['created_by'] = $user_identifier;
    $data['created_date'] = $current_time;
    $data['updated_by'] = $user_identifier;
    $data['updated_date'] = $current_time;
    
    return $data;
}

/**
 * Add standardized audit fields for record updates
 * 
 * @param array $data The data array to add audit fields to
 * @return array Data array with audit fields added
 */
function bst_add_audit_fields_update($data) {
    $data['updated_by'] = bst_get_current_user_identifier();
    $data['updated_date'] = current_time('mysql');
    
    return $data;
}
