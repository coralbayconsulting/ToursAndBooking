<?php
/**
 * Force table recreation - access this file in your browser once
 * URL: http://bluestradatours-development.local/wp-content/mu-plugins/bst_plugin/includes/database/force-create-tables.php
 */

// Load WordPress - go up 6 levels from database folder
require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/wp-load.php');

// Delete the transient to force table recreation
delete_transient('bst_tour_booking_tables_created');
delete_transient('bst_table_creation_lock');

echo "<h1>BST Plugin - Force Table Creation</h1>";
echo "<p>Transient deleted. Triggering table creation...</p>";

// Include and run the create tables function
require_once(__DIR__ . '/create-tables.php');

echo "<h2>Done!</h2>";
echo "<p>Check the debug log for details. You can now <a href='/wp-admin/'>go back to WordPress admin</a>.</p>";
echo "<p><strong>IMPORTANT:</strong> For security, please delete this file after use.</p>";
