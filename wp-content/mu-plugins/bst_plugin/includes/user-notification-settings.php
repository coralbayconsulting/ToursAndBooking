<?php

/**
 * User Notification Settings
 * Adds personal notification preferences to user profiles
 */

// Add notification settings to user profile
add_action('show_user_profile', 'bst_show_notification_settings');
add_action('edit_user_profile', 'bst_show_notification_settings');

// Save notification settings
add_action('personal_options_update', 'bst_save_notification_settings');
add_action('edit_user_profile_update', 'bst_save_notification_settings');

function bst_show_notification_settings($user) {
    // Only show for users who can manage BST bookings
    if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
        return;
    }

    // Get current settings
    $email_notifications = get_user_meta($user->ID, 'bst_email_notifications_enabled', true);
    $email_digest = get_user_meta($user->ID, 'bst_email_digest_enabled', true);
    $digest_time = get_user_meta($user->ID, 'bst_digest_time', true);
    $digest_timezone = get_user_meta($user->ID, 'bst_digest_timezone', true);
    $notification_contexts = get_user_meta($user->ID, 'bst_notification_contexts', true);

    // Set defaults
    if ($email_notifications === '') $email_notifications = '1';
    if ($email_digest === '') $email_digest = '1';
    if (!$digest_time) $digest_time = '08:00';
    if (!$digest_timezone) $digest_timezone = 'Europe/Rome';
    if (!is_array($notification_contexts)) {
        $notification_contexts = ['gf9_submission', 'gf10_finalization', 'waiting_list', 'bank_wire_pending', 'reservation_not_booked', 'tour_finalization_needed'];
    }

    ?>
    <h3><?php _e('BST Notification Settings', 'bst-plugin'); ?></h3>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="bst_email_notifications_enabled"><?php _e('Email Notifications', 'bst-plugin'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       name="bst_email_notifications_enabled" 
                       id="bst_email_notifications_enabled" 
                       value="1" 
                       <?php checked($email_notifications, '1'); ?> />
                <label for="bst_email_notifications_enabled">
                    <?php _e('Receive immediate email notifications for booking events', 'bst-plugin'); ?>
                </label>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="bst_email_digest_enabled"><?php _e('Daily Digest', 'bst-plugin'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       name="bst_email_digest_enabled" 
                       id="bst_email_digest_enabled" 
                       value="1" 
                       <?php checked($email_digest, '1'); ?> />
                <label for="bst_email_digest_enabled">
                    <?php _e('Receive daily digest of undismissed notifications', 'bst-plugin'); ?>
                </label>
            </td>
        </tr>
        
        <tr id="bst_digest_time_row" style="<?php echo $email_digest ? '' : 'display:none;'; ?>">
            <th scope="row">
                <label for="bst_digest_time"><?php _e('Digest Time', 'bst-plugin'); ?></label>
            </th>
            <td>
                <input type="time" 
                       name="bst_digest_time" 
                       id="bst_digest_time" 
                       value="<?php echo esc_attr($digest_time); ?>" 
                       style="width: 100px;" />
                <p class="description">
                    <?php _e('Time to send daily digest (24-hour format)', 'bst-plugin'); ?>
                </p>
            </td>
        </tr>
        
        <tr id="bst_digest_timezone_row" style="<?php echo $email_digest ? '' : 'display:none;'; ?>">
            <th scope="row">
                <label for="bst_digest_timezone"><?php _e('Time Zone', 'bst-plugin'); ?></label>
            </th>
            <td>
                <select name="bst_digest_timezone" id="bst_digest_timezone">
                    <?php
                    $common_timezones = [
                        'Europe/Rome' => 'Rome (CET/CEST)',
                        'America/New_York' => 'New York (EST/EDT)',
                        'America/Los_Angeles' => 'Los Angeles (PST/PDT)',
                        'Europe/London' => 'London (GMT/BST)',
                        'Europe/Paris' => 'Paris (CET/CEST)',
                        'Asia/Tokyo' => 'Tokyo (JST)',
                        'Australia/Sydney' => 'Sydney (AEST/AEDT)',
                        'UTC' => 'UTC (Coordinated Universal Time)'
                    ];
                    
                    foreach ($common_timezones as $tz => $label) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($tz),
                            selected($digest_timezone, $tz, false),
                            esc_html($label)
                        );
                    }
                    ?>
                </select>
                <p class="description">
                    <?php _e('Your preferred time zone for digest delivery', 'bst-plugin'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label><?php _e('Notification Types', 'bst-plugin'); ?></label>
            </th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php _e('Select which types of notifications you want to receive', 'bst-plugin'); ?></span>
                    </legend>
                    
                    <label for="bst_context_gf9">
                        <input type="checkbox" 
                               name="bst_notification_contexts[]" 
                               id="bst_context_gf9" 
                               value="gf9_submission" 
                               <?php checked(in_array('gf9_submission', $notification_contexts)); ?> />
                        <?php _e('Booking a Tour on the Web', 'bst-plugin'); ?>
                    </label><br>
                    
                    <label for="bst_context_gf10">
                        <input type="checkbox" 
                               name="bst_notification_contexts[]" 
                               id="bst_context_gf10" 
                               value="gf10_finalization" 
                               <?php checked(in_array('gf10_finalization', $notification_contexts)); ?> />
                        <?php _e('Finalizing a Booking on the Web', 'bst-plugin'); ?>
                    </label><br>
                    
                    <label for="bst_context_waiting">
                        <input type="checkbox" 
                               name="bst_notification_contexts[]" 
                               id="bst_context_waiting" 
                               value="waiting_list" 
                               <?php checked(in_array('waiting_list', $notification_contexts)); ?> />
                        <?php _e('Waiting List Additions', 'bst-plugin'); ?>
                    </label><br>
                    
                    <label for="bst_context_bank_wire_pending">
                        <input type="checkbox"
                               name="bst_notification_contexts[]"
                               id="bst_context_bank_wire_pending"
                               value="bank_wire_pending"
                               <?php checked(in_array('bank_wire_pending', $notification_contexts)); ?> />
                        <?php _e('Pending Payments for more than 3 days', 'bst-plugin'); ?>
                    </label><br>
                    
                    <label for="bst_context_reservation_not_booked">
                        <input type="checkbox" 
                               name="bst_notification_contexts[]" 
                               id="bst_context_reservation_not_booked" 
                               value="reservation_not_booked" 
                               <?php checked(in_array('reservation_not_booked', $notification_contexts)); ?> />
                        <?php _e('Reservation not booked for more than 3 days', 'bst-plugin'); ?>
                    </label><br>
                    
                    <label for="bst_context_tour_finalization_needed">
                        <input type="checkbox" 
                               name="bst_notification_contexts[]" 
                               id="bst_context_tour_finalization_needed" 
                               value="tour_finalization_needed" 
                               <?php checked(in_array('tour_finalization_needed', $notification_contexts)); ?> />
                        <?php _e('Tour Finalization Needed (within 70 days)', 'bst-plugin'); ?>
                    </label>
                    
                    <p class="description">
                        <?php _e('Select which booking events you want to be notified about', 'bst-plugin'); ?>
                    </p>
                </fieldset>
            </td>
        </tr>
    </table>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#bst_email_digest_enabled').change(function() {
            if ($(this).is(':checked')) {
                $('#bst_digest_time_row, #bst_digest_timezone_row').show();
            } else {
                $('#bst_digest_time_row, #bst_digest_timezone_row').hide();
            }
        });
    });
    </script>
    <?php
}

function bst_save_notification_settings($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    // Save email notification settings
    $email_notifications = isset($_POST['bst_email_notifications_enabled']) ? '1' : '0';
    update_user_meta($user_id, 'bst_email_notifications_enabled', $email_notifications);

    $email_digest = isset($_POST['bst_email_digest_enabled']) ? '1' : '0';
    update_user_meta($user_id, 'bst_email_digest_enabled', $email_digest);

    // Save digest time and timezone
    if (isset($_POST['bst_digest_time'])) {
        $digest_time = sanitize_text_field($_POST['bst_digest_time']);
        // Validate time format (HH:MM)
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $digest_time)) {
            update_user_meta($user_id, 'bst_digest_time', $digest_time);
        }
    }

    if (isset($_POST['bst_digest_timezone'])) {
        $timezone = sanitize_text_field($_POST['bst_digest_timezone']);
        // Validate timezone
        if (in_array($timezone, timezone_identifiers_list())) {
            update_user_meta($user_id, 'bst_digest_timezone', $timezone);
        }
    }

    // Save notification contexts
    $contexts = [];
    if (isset($_POST['bst_notification_contexts']) && is_array($_POST['bst_notification_contexts'])) {
        $allowed_contexts = ['gf9_submission', 'gf10_finalization', 'waiting_list', 'bank_wire_pending', 'reservation_not_booked', 'tour_finalization_needed'];
        foreach ($_POST['bst_notification_contexts'] as $context) {
            if (in_array($context, $allowed_contexts)) {
                $contexts[] = sanitize_text_field($context);
            }
        }
    }
    update_user_meta($user_id, 'bst_notification_contexts', $contexts);
}

/**
 * Get user's notification preferences
 */
function bst_get_user_notification_preferences($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    return [
        'email_notifications' => get_user_meta($user_id, 'bst_email_notifications_enabled', true) === '1',
        'email_digest' => get_user_meta($user_id, 'bst_email_digest_enabled', true) === '1',
        'digest_time' => get_user_meta($user_id, 'bst_digest_time', true) ?: '08:00',
        'digest_timezone' => get_user_meta($user_id, 'bst_digest_timezone', true) ?: 'Europe/Rome',
        'notification_contexts' => get_user_meta($user_id, 'bst_notification_contexts', true) ?: ['gf9_submission', 'gf10_finalization', 'waiting_list', 'bank_wire_pending', 'reservation_not_booked', 'tour_finalization_needed']
    ];
}

/**
 * Check if user should receive notifications for a specific context
 */
function bst_user_wants_notification($user_id, $context) {
    $preferences = bst_get_user_notification_preferences($user_id);
    return in_array($context, $preferences['notification_contexts']);
}

/**
 * Get all users who want digest emails at a specific time
 */
function bst_get_users_for_digest_time($target_time_utc) {
    global $wpdb;
    
    $users = [];
    
    // Get all users with digest enabled
    $digest_users = $wpdb->get_results("
        SELECT user_id, meta_value as digest_time
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'bst_email_digest_enabled' 
        AND meta_value = '1'
    ");
    
    foreach ($digest_users as $user_data) {
        $user_id = $user_data->user_id;
        $preferences = bst_get_user_notification_preferences($user_id);
        
        // Convert user's local time to UTC
        $user_timezone = new DateTimeZone($preferences['digest_timezone']);
        $utc_timezone = new DateTimeZone('UTC');
        
        $user_time = new DateTime($preferences['digest_time'], $user_timezone);
        $user_time->setTimezone($utc_timezone);
        
        // Check if this matches our target time (within 5 minutes)
        $target_timestamp = strtotime($target_time_utc);
        $user_timestamp = $user_time->getTimestamp();
        
        if (abs($target_timestamp - $user_timestamp) <= 300) { // 5 minutes tolerance
            $users[] = $user_id;
        }
    }
    
    return $users;
}
