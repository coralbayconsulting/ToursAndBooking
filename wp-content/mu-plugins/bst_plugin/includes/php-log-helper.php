<?php
/**
 * PHP Log Helper Functions for BST Plugin
 */

if (!function_exists('bst_normalize_path')) {
    /**
     * Normalize path separators for the current OS
     * @param string $path The path to normalize
     * @return string Normalized path
     */
    function bst_normalize_path($path) {
        // Replace all slashes with the directory separator for current OS
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}

if (!function_exists('bst_get_php_error_log_path')) {
    /**
     * Get the PHP error log path (system errors)
     * @return string The path to the system error log file
     */
    function bst_get_php_error_log_path() {
        return bst_normalize_path(trailingslashit(WP_CONTENT_DIR) . 'error.log');
    }
}

if (!function_exists('bst_get_wp_debug_log_path')) {
    /**
     * Get the WordPress debug log path
     * @return string The path to the WordPress debug log file
     */
    function bst_get_wp_debug_log_path() {
        return bst_normalize_path(trailingslashit(WP_CONTENT_DIR) . 'debug.log');
    }
}

if (!function_exists('bst_get_bst_debug_log_path')) {
    /**
     * Get the BST debug log path (same as WordPress debug log now)
     * @return string The path to the BST debug log file
     */
    function bst_get_bst_debug_log_path() {
        return bst_normalize_path(trailingslashit(WP_CONTENT_DIR) . 'debug.log');
    }
}

if (!function_exists('bst_is_log_readable')) {
    /**
     * Check if a log file is readable
     * @param string $path Path to the log file
     * @return bool True if readable, false otherwise
     */
    function bst_is_log_readable($path) {
        return file_exists($path) && is_readable($path);
    }
}

if (!function_exists('bst_get_log_size')) {
    /**
     * Get the size of a log file in human-readable format
     * @param string $path Path to the log file
     * @return string Human-readable file size
     */
    function bst_get_log_size($path) {
        if (!file_exists($path)) {
            return 'N/A';
        }
        
        $size = filesize($path);
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
}
