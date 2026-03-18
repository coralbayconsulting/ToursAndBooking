<?php
/**
 * Add sent_successfully column to email log table
 * Run this file once to add the sent_successfully column if it doesn't exist
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

global $wpdb;

$table_name = $wpdb->prefix . 'bst_email_log';

// Check if column exists
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'sent_successfully'");

if (empty($column_exists)) {
    echo "Adding sent_successfully column to {$table_name}...\n";
    
    // Add the column after sent_by
    $result = $wpdb->query("
        ALTER TABLE `{$table_name}` 
        ADD COLUMN `sent_successfully` TINYINT(1) DEFAULT 1 
        AFTER `sent_by`
    ");
    
    if ($result === false) {
        echo "Error: " . $wpdb->last_error . "\n";
    } else {
        echo "Success! Column added.\n";
        
        // Update existing records to mark them as successful (since they were logged, they likely succeeded)
        $update_result = $wpdb->query("UPDATE `{$table_name}` SET `sent_successfully` = 1 WHERE `sent_successfully` IS NULL");
        echo "Updated {$update_result} existing records to mark as successfully sent.\n";
    }
} else {
    echo "Column 'sent_successfully' already exists in {$table_name}.\n";
}

// Verify the column
$columns = $wpdb->get_results("DESCRIBE {$table_name}");
echo "\nCurrent table structure:\n";
foreach ($columns as $column) {
    echo "  - {$column->Field} ({$column->Type})\n";
}
