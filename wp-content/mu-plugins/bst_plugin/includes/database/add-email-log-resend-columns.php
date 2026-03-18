<?php
/**
 * Add source_entry_id and resent_from columns to email_log table
 * Run this once to add the columns
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'bst_email_log';

// Check if columns already exist
$source_entry_id_exists = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = '$table_name' 
    AND COLUMN_NAME = 'source_entry_id'
");

$resent_from_exists = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = '$table_name' 
    AND COLUMN_NAME = 'resent_from'
");

// Add source_entry_id column if it doesn't exist
if (!$source_entry_id_exists) {
    $wpdb->query("
        ALTER TABLE $table_name 
        ADD COLUMN source_entry_id BIGINT(20) UNSIGNED DEFAULT NULL 
        AFTER booking_id
    ");
    
    if ($wpdb->last_error) {
        error_log('Error adding source_entry_id column: ' . $wpdb->last_error);
    } else {
        error_log('Successfully added source_entry_id column to email_log table');
    }
}

// Add resent_from column if it doesn't exist
if (!$resent_from_exists) {
    $wpdb->query("
        ALTER TABLE $table_name 
        ADD COLUMN resent_from BIGINT(20) UNSIGNED DEFAULT NULL 
        AFTER sent_successfully
    ");
    
    if ($wpdb->last_error) {
        error_log('Error adding resent_from column: ' . $wpdb->last_error);
    } else {
        error_log('Successfully added resent_from column to email_log table');
    }
}
