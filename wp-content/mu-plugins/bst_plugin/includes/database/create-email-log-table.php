<?php
/**
 * Create the BST Email Log table
 * Run this file once to create the wp_bst_email_log table
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

global $wpdb;

$table_name = $wpdb->prefix . 'bst_email_log';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS $table_name (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    template_id BIGINT(20) UNSIGNED DEFAULT NULL,
    email_type VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    content LONGTEXT NOT NULL,
    sent_date DATETIME NOT NULL,
    sent_by VARCHAR(100) NOT NULL,
    sent_successfully TINYINT(1) DEFAULT 0,
    gmail_message_id VARCHAR(255) DEFAULT NULL,
    gmail_thread_id VARCHAR(255) DEFAULT NULL,
    direction VARCHAR(20) NOT NULL DEFAULT 'outbound',
    message_id VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY booking_id_idx (booking_id),
    KEY email_type_idx (email_type),
    KEY sent_date_idx (sent_date)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

if ($wpdb->last_error) {
    echo "Error creating table: " . $wpdb->last_error . "\n";
} else {
    echo "Success! Table {$table_name} created or already exists.\n";
    
    // Verify the table was created
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
    if ($table_exists) {
        echo "Verified: Table exists in database.\n";
        
        // Show table structure
        $columns = $wpdb->get_results("DESCRIBE {$table_name}");
        echo "\nTable structure:\n";
        foreach ($columns as $column) {
            echo "  - {$column->Field} ({$column->Type})\n";
        }
    } else {
        echo "Warning: Table creation reported success but table not found in database.\n";
    }
}
