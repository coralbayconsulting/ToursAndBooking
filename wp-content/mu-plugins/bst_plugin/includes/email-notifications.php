<?php

/**
 * Email Notification System for BST Plugin
 * Handles immediate emails and daily digest functionality
 */

// Schedule daily digest cron job
add_action('wp_loaded', 'bst_schedule_digest_cron');
add_action('bst_daily_digest_cron', 'bst_send_daily_digests');

function bst_schedule_digest_cron() {
    // Use transient to check only once per day
    if (get_transient('bst_digest_cron_check')) {
        return;
    }
    set_transient('bst_digest_cron_check', true, DAY_IN_SECONDS);
    
    // Run every 5 minutes to check for users whose digest time has arrived.
    bst_schedule_cron_event_once(
        'bst_daily_digest_cron',
        time(),
        'bst_every_5_minutes',
        'daily digest dispatcher'
    );
}

// Add custom cron interval
add_filter('cron_schedules', 'bst_add_cron_intervals');
function bst_add_cron_intervals($schedules) {
    $schedules['bst_every_5_minutes'] = array(
        'interval' => 300, // 5 minutes in seconds
        'display' => __('Every 5 Minutes', 'bst-plugin')
    );
    return $schedules;
}

/**
 * Send daily digest emails to users whose time has arrived
 */
function bst_send_daily_digests() {
    // Get current time in UTC
    $current_utc = new DateTime('now', new DateTimeZone('UTC'));
    $current_time_string = $current_utc->format('H:i');
    
    // Get users who should receive digest at this time
    $users = bst_get_users_for_digest_time($current_time_string);
    
    foreach ($users as $user_id) {
        bst_send_user_digest($user_id);
    }
}

/**
 * Send digest email to a specific user
 */
function bst_send_user_digest($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    // Check if digest was already sent today
    if (!bst_should_send_digest_today($user_id)) {
        return false; // Already sent today
    }

    // Get user's preferences
    $preferences = bst_get_user_notification_preferences($user_id);
    
    if (!$preferences['email_digest']) {
        return false; // User doesn't want digest emails
    }

    // Get undismissed notifications for this user
    $notifications = bst_get_undismissed_notifications($user_id);
    
    if (empty($notifications)) {
        return false; // No notifications to send
    }

    // Filter notifications by user's preferred contexts
    $filtered_notifications = array_filter($notifications, function($notification) use ($preferences) {
        return in_array($notification['context'], $preferences['notification_contexts']);
    });

    if (empty($filtered_notifications)) {
        return false; // No relevant notifications
    }

    // Prepare email content
    $subject = sprintf(__('BST Daily Digest - %d New Notifications', 'bst-plugin'), count($filtered_notifications));
    
    $message = bst_format_digest_email($filtered_notifications, $user, $preferences);
    
    // Send email
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $sent = wp_mail($user->user_email, $subject, $message, $headers);
    
    if ($sent) {
        // Log successful digest send
        error_log("BST: Daily digest sent to user {$user_id} ({$user->user_email}) with " . count($filtered_notifications) . " notifications");
        
        // Mark digest as sent today (prevent duplicate sends)
        update_user_meta($user_id, 'bst_last_digest_sent', date('Y-m-d'));
    }
    
    return $sent;
}

/**
 * Get all undismissed notifications for a user
 */
function bst_get_undismissed_notifications($user_id) {
    global $wpdb;
    
    $dismissed_notices = get_user_meta($user_id, 'bst_dismissed_notices', true);
    if (!is_array($dismissed_notices)) {
        $dismissed_notices = [];
    }
    
    // Get all notifications from the last 7 days
    $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
    
    $notifications = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}bst_notifications 
        WHERE created_at >= %s 
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC
    ", $seven_days_ago), ARRAY_A);
    
    // Filter out dismissed notifications
    $undismissed = array_filter($notifications, function($notification) use ($dismissed_notices) {
        return !in_array($notification['id'], $dismissed_notices);
    });
    
    return $undismissed;
}

/**
 * Format digest email HTML
 */
function bst_format_digest_email($notifications, $user, $preferences) {
    $site_name = get_bloginfo('name');
    $site_url = get_site_url();
    $dashboard_url = admin_url('admin.php?page=bst-dashboard');
    
    // Group notifications by context
    $grouped = [];
    foreach ($notifications as $notification) {
        $context = $notification['context'];
        if (!isset($grouped[$context])) {
            $grouped[$context] = [];
        }
        $grouped[$context][] = $notification;
    }
    
    // Get user's local time for the greeting
    $user_timezone = new DateTimeZone($preferences['digest_timezone']);
    $local_time = new DateTime('now', $user_timezone);
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html($site_name); ?> - Daily Digest</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; }
            .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .notification-group { margin-bottom: 30px; }
            .context-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 5px; }
            .notification { background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 10px; }
            .notification.gf9_submission { border-left-color: #46b450; }
            .notification.gf10_finalization { border-left-color: #00a0d2; }
            .notification.waiting_list { border-left-color: #ffb900; }
            .notification.bank_wire_pending { border-left-color: #dc3232; }
            .notification.reservation_not_booked { border-left-color: #ff6900; }
            .notification.tour_finalization_needed { border-left-color: #8f2ce6; }
            .notification-message { margin-bottom: 10px; }
            .notification-date { font-size: 12px; color: #666; }
            .footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .button { display: inline-block; background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; margin: 10px 5px; }
            .stats { background: #e8f4fd; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><?php echo esc_html($site_name); ?></h1>
                <p>Daily Booking Notifications Digest</p>
            </div>
            
            <div class="content">
                <h2>Hello <?php echo esc_html($user->display_name); ?>!</h2>
                <p>Here's your daily digest for <?php echo $local_time->format('l, F j, Y'); ?> at <?php echo $local_time->format('g:i A T'); ?>.</p>
                
                <div class="stats">
                    <strong><?php echo count($notifications); ?> notification(s)</strong> waiting for your attention.
                </div>
                
                <?php foreach ($grouped as $context => $context_notifications): ?>
                    <div class="notification-group">
                        <div class="context-title">
                            <?php 
                            switch ($context) {
                                case 'gf9_submission':
                                    echo '📋 Booking a Tour on the Web';
                                    break;
                                case 'gf10_finalization':
                                    echo '✅ Finalizing a Booking on the Web';
                                    break;
                                case 'waiting_list':
                                    echo '⏳ Waiting List Additions';
                                    break;
                                case 'bank_wire_pending':
                                    echo '💳 Bank Transfer Pending for more than 3 days';
                                    break;
                                case 'reservation_not_booked':
                                    echo '📅 Reservation not booked for more than 3 days';
                                    break;
                                case 'tour_finalization_needed':
                                    echo '⚠️ Tour Finalization Needed (within 70 days)';
                                    break;
                                default:
                                    echo ucfirst(str_replace('_', ' ', $context));
                            }
                            echo ' (' . count($context_notifications) . ')';
                            ?>
                        </div>
                        
                        <?php foreach ($context_notifications as $notification): ?>
                            <div class="notification <?php echo esc_attr($context); ?>">
                                <div class="notification-message">
                                    <?php echo wp_kses_post($notification['message']); ?>
                                </div>
                                <div class="notification-date">
                                    <?php 
                                    $created = new DateTime($notification['created_at'], new DateTimeZone('UTC'));
                                    $created->setTimezone($user_timezone);
                                    echo $created->format('M j, Y g:i A T');
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="<?php echo esc_url($dashboard_url); ?>" class="button">
                        View Dashboard
                    </a>
                    <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>" class="button" style="background: #666;">
                        Notification Settings
                    </a>
                </div>
            </div>
            
            <div class="footer">
                <p>You're receiving this digest because you have email notifications enabled in your BST settings.</p>
                <p>To change your notification preferences, <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">update your profile settings</a>.</p>
                <p><?php echo esc_html($site_name); ?> | <?php echo esc_html($site_url); ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    
    return ob_get_clean();
}

/**
 * Send immediate email notification
 */
function bst_send_immediate_notification($user_id, $notification_data) {
    $preferences = bst_get_user_notification_preferences($user_id);
    
    if (!$preferences['email_notifications']) {
        return false; // User doesn't want immediate emails
    }
    
    if (!bst_user_wants_notification($user_id, $notification_data['context'])) {
        return false; // User doesn't want this type of notification
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Format subject based on context
    $context_titles = [
        'gf9_submission' => 'Booking a Tour on the Web',
        'gf10_finalization' => 'Finalizing a Booking on the Web',
        'waiting_list' => 'Waiting List Addition',
        'bank_wire_pending' => 'Bank Transfer Pending for more than 3 days',
        'reservation_not_booked' => 'Reservation not booked for more than 3 days',
        'tour_finalization_needed' => 'Tour Finalization Needed'
    ];
    
    $subject = sprintf(
        '%s - %s', 
        get_bloginfo('name'), 
        $context_titles[$notification_data['context']] ?? 'New Notification'
    );
    
    // Simple email format for immediate notifications
    $message = sprintf(
        "<html><body style='font-family: Arial, sans-serif;'>
        <h2>%s</h2>
        <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa;'>
        %s
        </div>
        <p><a href='%s' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none;'>View Dashboard</a></p>
        </body></html>",
        esc_html($context_titles[$notification_data['context']] ?? 'New Notification'),
        wp_kses_post($notification_data['message']),
        admin_url('admin.php?page=bst-dashboard')
    );
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    return wp_mail($user->user_email, $subject, $message, $headers);
}

/**
 * Get users who should receive immediate notifications for a context
 */
function bst_get_users_for_immediate_notification($context) {
    global $wpdb;
    
    // Get all users with immediate notifications enabled
    $immediate_users = $wpdb->get_results("
        SELECT user_id 
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'bst_email_notifications_enabled' 
        AND meta_value = '1'
    ");
    
    $user_ids = [];
    foreach ($immediate_users as $user_data) {
        if (bst_user_wants_notification($user_data->user_id, $context)) {
            // Check if user has appropriate capabilities
            $user = get_userdata($user_data->user_id);
            if ($user && (user_can($user, 'manage_options') || user_can($user, 'edit_posts'))) {
                $user_ids[] = $user_data->user_id;
            }
        }
    }
    
    return $user_ids;
}

// Prevent duplicate digest sends on the same day
function bst_should_send_digest_today($user_id) {
    $last_sent = get_user_meta($user_id, 'bst_last_digest_sent', true);
    $today = date('Y-m-d');
    
    return $last_sent !== $today;
}
