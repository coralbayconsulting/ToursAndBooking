<?php
/**
 * BST Plugin User Roles and Capabilities
 * 
 * This file manages custom user roles for the BST Plugin.
 * Creates a "Tour Manager" role that can manage tours and bookings
 * without full WordPress admin privileges.
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Create or update the Tour Manager role
 */
function bst_create_tour_manager_role() {
    // Get the Editor role to use as a base
    $editor = get_role('editor');
    
    // Remove the role if it exists (for updates)
    remove_role('tour_manager');
    
    // Create the Tour Manager role with editor capabilities as base
    $tour_manager = add_role(
        'tour_manager',
        'Tour Manager',
        $editor ? $editor->capabilities : array()
    );
    
    if ($tour_manager) {
        // Add custom capabilities for managing tours and bookings
        $tour_manager->add_cap('manage_bst_tours');
        $tour_manager->add_cap('manage_bst_bookings');
        $tour_manager->add_cap('manage_bst_customers');
        $tour_manager->add_cap('view_bst_dashboard');
        $tour_manager->add_cap('export_bst_data');
        
        // Remove capabilities that Tour Managers shouldn't have
        $tour_manager->remove_cap('manage_options');  // No access to WP settings
        $tour_manager->remove_cap('edit_theme_options'); // No theme customization
        $tour_manager->remove_cap('install_plugins'); // No plugin management
        $tour_manager->remove_cap('activate_plugins');
        $tour_manager->remove_cap('update_plugins');
        $tour_manager->remove_cap('delete_plugins');
        $tour_manager->remove_cap('install_themes');
        $tour_manager->remove_cap('update_themes');
        $tour_manager->remove_cap('delete_themes');
        $tour_manager->remove_cap('edit_users'); // No user management
        $tour_manager->remove_cap('delete_users');
        $tour_manager->remove_cap('create_users');
        $tour_manager->remove_cap('remove_users');
        $tour_manager->remove_cap('promote_users');
    }
    
    // Also add the custom capabilities to administrators
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_bst_tours');
        $admin->add_cap('manage_bst_bookings');
        $admin->add_cap('manage_bst_customers');
        $admin->add_cap('view_bst_dashboard');
        $admin->add_cap('export_bst_data');
        $admin->add_cap('manage_bst_settings');
        $admin->add_cap('manage_bst_tools');
        $admin->add_cap('manage_bst_exchange_rates');
        
        // Add capabilities for tour-type custom post type
        $admin->add_cap('edit_tour_type');
        $admin->add_cap('read_tour_type');
        $admin->add_cap('delete_tour_type');
        $admin->add_cap('edit_tour_types');
        $admin->add_cap('edit_others_tour_types');
        $admin->add_cap('publish_tour_types');
        $admin->add_cap('read_private_tour_types');
        $admin->add_cap('delete_tour_types');
        $admin->add_cap('delete_private_tour_types');
        $admin->add_cap('delete_published_tour_types');
        $admin->add_cap('delete_others_tour_types');
        $admin->add_cap('edit_private_tour_types');
        $admin->add_cap('edit_published_tour_types');
        
        // Add capabilities for source-code custom post type
        $admin->add_cap('edit_source_code');
        $admin->add_cap('read_source_code');
        $admin->add_cap('delete_source_code');
        $admin->add_cap('edit_source_codes');
        $admin->add_cap('edit_others_source_codes');
        $admin->add_cap('publish_source_codes');
        $admin->add_cap('read_private_source_codes');
        $admin->add_cap('delete_source_codes');
        $admin->add_cap('delete_private_source_codes');
        $admin->add_cap('delete_published_source_codes');
        $admin->add_cap('delete_others_source_codes');
        $admin->add_cap('edit_private_source_codes');
        $admin->add_cap('edit_published_source_codes');
    }
}

/**
 * Check if current user can manage tours and bookings
 */
function bst_user_can_manage_tours() {
    return current_user_can('manage_bst_tours') || current_user_can('manage_options');
}

/**
 * Check if current user can manage bookings
 */
function bst_user_can_manage_bookings() {
    return current_user_can('manage_bst_bookings') || current_user_can('manage_options');
}

/**
 * Check if current user can manage customers
 */
function bst_user_can_manage_customers() {
    return current_user_can('manage_bst_customers') || current_user_can('manage_options');
}

/**
 * Check if current user can access BST dashboard
 */
function bst_user_can_view_dashboard() {
    return current_user_can('view_bst_dashboard') || current_user_can('manage_options');
}

/**
 * Check if current user can access settings (admin only)
 */
function bst_user_can_manage_settings() {
    return current_user_can('manage_options');
}

/**
 * Check if current user can access tools (admin only)
 */
function bst_user_can_manage_tools() {
    return current_user_can('manage_options');
}

/**
 * Check if current user can manage exchange rates (admin only)
 */
function bst_user_can_manage_exchange_rates() {
    return current_user_can('manage_options');
}

/**
 * Maintain roles/capabilities on init via versioned option (mu-plugin compatible).
 * Activation hook is not used: mu-plugins are always active and have no activation.
 * Increment $current_version when adding or changing capabilities.
 */
add_action('init', function() {
    $role_version = get_option('bst_role_version', '0');
    $current_version = '1.2';

    if ($role_version !== $current_version) {
        bst_create_tour_manager_role();
        update_option('bst_role_version', $current_version);
    }
}, 5);
