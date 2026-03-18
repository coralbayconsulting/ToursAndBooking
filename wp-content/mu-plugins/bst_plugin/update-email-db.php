<?php
/**
 * Email System Database Updater
 * Access this file directly via: /wp-content/mu-plugins/bst_plugin/update-email-db.php
 */

// Load WordPress
require_once '../../../../wp-config.php';

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h2>🔧 BST Email System Database Updater</h2>";

global $wpdb;
$email_log_table = $wpdb->prefix . 'bst_email_log';

// Check current table structure
echo "<h3>Current Table Structure Check:</h3>";

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$email_log_table'") == $email_log_table;

if (!$table_exists) {
    echo "❌ Email log table does not exist!<br>";
    
    // Include table creation
    require_once 'includes/database/create-tables.php';
    create_tour_booking_tables();
    
    echo "✅ Created email log table<br>";
} else {
    echo "✅ Email log table exists<br>";
    
    // Check columns
    $columns = $wpdb->get_results("DESCRIBE $email_log_table");
    $column_names = array_column($columns, 'Field');
    
    echo "Current columns: " . implode(', ', $column_names) . "<br>";
    
    // Check for sent_by column
    if (!in_array('sent_by', $column_names)) {
        echo "❌ Missing sent_by column - adding it...<br>";
        $result = $wpdb->query("ALTER TABLE $email_log_table ADD COLUMN sent_by VARCHAR(100) DEFAULT NULL AFTER sent_date");
        if ($result !== false) {
            echo "✅ Added sent_by column<br>";
        } else {
            echo "❌ Failed to add sent_by column: " . $wpdb->last_error . "<br>";
        }
    } else {
        echo "✅ sent_by column exists<br>";
    }
    
    // Check email_type enum
    $email_type_column = $wpdb->get_row("SHOW COLUMNS FROM $email_log_table LIKE 'email_type'");
    if ($email_type_column) {
        echo "Current email_type enum: " . $email_type_column->Type . "<br>";
        
        if (strpos($email_type_column->Type, 'notification') === false) {
            echo "❌ Missing notification in email_type enum - updating...<br>";
            $result = $wpdb->query("ALTER TABLE $email_log_table MODIFY email_type ENUM('reservation', 'finalization', 'invoice', 'notification') NOT NULL");
            if ($result !== false) {
                echo "✅ Updated email_type enum to include notification<br>";
            } else {
                echo "❌ Failed to update email_type enum: " . $wpdb->last_error . "<br>";
            }
        } else {
            echo "✅ email_type enum includes notification<br>";
        }
    }
}

echo "<hr>";

// Test email system
echo "<h3>Email System Test:</h3>";

// Test merge field processing
try {
    require_once 'includes/email-system/class-email-merge-fields.php';
    $merge_fields = new BST_Email_Merge_Fields();
    
    // Create test booking object
    $test_booking = (object) array(
        'id' => 'TEST-001',
        'guest1_first_name' => 'John',
        'guest1_last_name' => 'Doe',
        'guest1_email' => 'john@example.com',
        'tour_text' => 'Amazing City Tour',
        'tour_date_text' => '2024-06-15',
        'booking_status' => 'Waiting List',
        'tour_price' => 5000,
        'tour_currency' => 'EUR'
    );
    
    $test_content = "Hello {{guest1_first_name}}, you are on the waiting list for {{tour_name}} on {{tour_date}}!";
    $processed = $merge_fields->process_merge_fields($test_content, $test_booking);
    
    echo "✅ Merge fields working<br>";
    echo "Test: $test_content<br>";
    echo "Result: <strong>$processed</strong><br>";
    
} catch (Exception $e) {
    echo "❌ Merge field error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>✅ Database Update Complete</h3>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>Test creating a waiting list booking again</li>";
echo "<li>Check the email is sent with proper merge fields</li>";
echo "<li>Verify email log entries are created correctly</li>";
echo "</ul>";

echo "<p><a href='/wp-admin/edit.php?post_type=email-template'>← Back to Email Templates</a></p>";
?>