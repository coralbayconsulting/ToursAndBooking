<?php
/**
 * Update Email Template Merge Field Syntax
 * Converts double braces {{field}} to single braces {field}
 * 
 * Run this once to update all existing email templates
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// Check if user has admin privileges
if (!current_user_can('manage_options')) {
    die('Sorry, you need to be an administrator to run this script.');
}

echo "<h2>Updating Email Template Merge Field Syntax</h2>\n";
echo "<p>Converting {{field}} to {field}...</p>\n";

// Get all email templates
$templates = get_posts(array(
    'post_type' => 'email-template',
    'post_status' => 'any',
    'posts_per_page' => -1
));

if (empty($templates)) {
    echo "<p style='color: orange;'>No email templates found.</p>\n";
    exit;
}

echo "<p>Found " . count($templates) . " email template(s) to update.</p>\n";
echo "<hr>\n";

$updated_count = 0;
$error_count = 0;

foreach ($templates as $template) {
    echo "<h3>Processing: {$template->post_title} (ID: {$template->ID})</h3>\n";
    
    // Update post content
    $new_content = $template->post_content;
    $original_content = $new_content;
    $new_content = str_replace('{{', '{', $new_content);
    $new_content = str_replace('}}', '}', $new_content);
    
    $content_changed = ($original_content !== $new_content);
    
    // Update subject line meta
    $subject = get_post_meta($template->ID, '_bst_email_subject', true);
    $original_subject = $subject;
    if ($subject) {
        $subject = str_replace('{{', '{', $subject);
        $subject = str_replace('}}', '}', $subject);
    }
    $subject_changed = ($original_subject !== $subject);
    
    // Perform updates if needed
    $updated = false;
    
    if ($content_changed) {
        $result = wp_update_post(array(
            'ID' => $template->ID,
            'post_content' => $new_content
        ));
        
        if (is_wp_error($result)) {
            echo "<p style='color: red;'>✗ Error updating content: " . $result->get_error_message() . "</p>\n";
            $error_count++;
        } else {
            echo "<p style='color: green;'>✓ Updated post content</p>\n";
            $updated = true;
        }
    } else {
        echo "<p style='color: gray;'>- No changes needed in content</p>\n";
    }
    
    if ($subject_changed) {
        $result = update_post_meta($template->ID, '_bst_email_subject', $subject);
        if ($result !== false) {
            echo "<p style='color: green;'>✓ Updated subject line</p>\n";
            $updated = true;
        } else {
            echo "<p style='color: red;'>✗ Error updating subject line</p>\n";
            $error_count++;
        }
    } else {
        echo "<p style='color: gray;'>- No changes needed in subject</p>\n";
    }
    
    if ($updated) {
        $updated_count++;
    }
    
    echo "<hr>\n";
}

echo "<h2>Summary</h2>\n";
echo "<p><strong>Total templates processed:</strong> " . count($templates) . "</p>\n";
echo "<p><strong>Templates updated:</strong> " . $updated_count . "</p>\n";
if ($error_count > 0) {
    echo "<p style='color: red;'><strong>Errors encountered:</strong> " . $error_count . "</p>\n";
} else {
    echo "<p style='color: green;'><strong>All updates completed successfully!</strong></p>\n";
}

echo "<p><a href='" . admin_url('edit.php?post_type=email-template') . "'>View Email Templates</a></p>\n";
