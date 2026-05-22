<?php
/*
Plugin Name: BST Plugin
Description: A plugin to manage tours and the booking thereof.
Version: 1.0
Author: Wayne Wilson, Coral Bay Consulting
*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Load Composer autoloader for Google API Client
$composer_autoload = ABSPATH . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// Suppress only known, noisy PHP 8.1+ deprecation warnings from WordPress core/theme,
// and delegate everything else to the previous error handler so we don't hide real issues.
add_action('init', function() {
    $previous_handler = null;

    $previous_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$previous_handler) {
        if ($errno === E_DEPRECATED) {
            $is_null_strpos  = strpos($errstr, 'strpos(): Passing null to parameter') !== false;
            $is_null_str_replace = strpos($errstr, 'str_replace(): Passing null to parameter') !== false;
            $is_core_context = (strpos($errfile, 'wp-includes') !== false || strpos($errfile, 'wp-admin') !== false);

            if ($is_core_context && ($is_null_strpos || $is_null_str_replace)) {
                // Silently suppress these specific core deprecations without logging
                return true; // Suppress only these specific core warnings
            }
        }

        // Delegate to any previous handler if one exists
        if ($previous_handler && is_callable($previous_handler)) {
            return $previous_handler($errno, $errstr, $errfile, $errline);
        }

        // Fall back to PHP's internal handler
        return false;
    });
}, 1);

// Define plugin constants
define('BST_PLUGIN_VERSION', '1.0.0');
define('BST_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Include user roles and capabilities
require_once plugin_dir_path(__FILE__) . 'includes/user-roles.php';

// ACF / SCF Local JSON (field groups in bst_plugin/acf-json — commit to Git)
require_once plugin_dir_path(__FILE__) . 'includes/acf-json.php';

// Include the main plugin class
require_once plugin_dir_path(__FILE__) . 'includes/class-bst-plugin.php';

// Include waiting list functionality
require_once plugin_dir_path(__FILE__) . 'includes/waiting-list-functions.php';

// Include Gravity Forms email capture
require_once plugin_dir_path(__FILE__) . 'includes/gravity-forms-email-capture.php';

// SEO: ACF override fields, head output, XML sitemap, and migration tool
require_once plugin_dir_path(__FILE__) . 'includes/seo-fields.php';
require_once plugin_dir_path(__FILE__) . 'includes/seo-head.php';
require_once plugin_dir_path(__FILE__) . 'includes/seo-sitemap.php';
require_once plugin_dir_path(__FILE__) . 'includes/llms-txt.php';

// Enable excerpt support for Pages so the Excerpt panel appears in the block editor.
add_action( 'init', function() {
	add_post_type_support( 'page', 'excerpt' );
} );

// Register tour listing card image size (600×338 matches displayed card dimensions).
add_action( 'after_setup_theme', function() {
	add_image_size( 'tour-listing', 600, 338, true );
} );

// Remove unused Gravity Forms image choice sizes (not used on this site).
add_action( 'wp_loaded', function() {
	remove_image_size( 'gform-image-choice-sm' );
	remove_image_size( 'gform-image-choice-md' );
	remove_image_size( 'gform-image-choice-lg' );
} );

// Remove Colibri theme footer credit from page output.
add_action( 'template_redirect', function() {
	ob_start( function( $buffer ) {
		return preg_replace(
			'/\s*Created for free using WordPress and\s*<a[^>]*>Colibri<\/a>/',
			' All Rights Reserved.',
			$buffer
		);
	} );
} );

// Initialize the plugin
BST_Plugin::get_instance();


/**
 * Standardized import message handling
 */
function bst_get_import_success_message($type, $imported = 0, $skipped = 0, $errors = 0) {
    $type_labels = array(
        'gf9_csv' => 'GF9 CSV',
        'gf10' => 'GF10',
        'customers' => 'Customer',
        'tour_bookings' => 'Tour Booking'
    );
    
    $label = isset($type_labels[$type]) ? $type_labels[$type] : ucfirst($type);
    
    $message = "{$label} import completed.";
    
    if ($imported > 0) {
        $message .= " {$imported} " . ($type === 'customers' ? 'customers' : 'entries') . " imported.";
    }
    
    if ($skipped > 0) {
        $message .= " {$skipped} entries skipped (already exist).";
    }
    
    if ($errors > 0) {
        $message .= " {$errors} errors encountered.";
    }
    
    return $message;
}

function bst_get_import_error_message($type, $error_code = 'unknown') {
    $type_labels = array(
        'gf9_csv' => 'GF9 CSV',
        'gf10' => 'GF10', 
        'customers' => 'Customer',
        'tour_bookings' => 'Tour Booking'
    );
    
    $label = isset($type_labels[$type]) ? $type_labels[$type] : ucfirst($type);
    
    $error_messages = array(
        'file_upload_failed' => 'File upload failed. Please check the file and try again.',
        'file_not_found' => 'File not found. Please ensure the file was uploaded correctly.',
        'file_read_failed' => 'Could not read the uploaded file. Please check file permissions.',
        'no_headers' => 'CSV file appears to be empty or missing headers.',
        'processing_failed' => 'Import processing failed. Please check the file format and try again.',
        'invalid_format' => 'Invalid file format. Please upload a valid CSV file.',
        'unknown' => 'An unknown error occurred during import.'
    );
    
    $error_msg = isset($error_messages[$error_code]) ? $error_messages[$error_code] : $error_messages['unknown'];
    
    return "Error importing {$label}: {$error_msg}";
}
