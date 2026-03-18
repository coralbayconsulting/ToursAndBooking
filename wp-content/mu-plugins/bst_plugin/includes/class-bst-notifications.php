<?php

class BST_Notifications {
    
    private static $instance = null;
    private static $hooks_registered = false;
    
    /**
     * Singleton pattern - get the single instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Only register hooks once
        if (!self::$hooks_registered) {
            add_action('admin_notices', array($this, 'display_admin_notices'));
            add_action('wp_ajax_bst_dismiss_notice', array($this, 'dismiss_notice'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_notice_scripts'));
            self::$hooks_registered = true;
        }
    }
    
    /**
     * Add a new notification
     * 
     * @param string $id Unique ID for the notice
     * @param string $message The notice message (can include HTML)
     * @param string $type Notice type: 'success', 'error', 'warning', 'info'
     * @param bool $dismissible Whether the notice can be dismissed
     * @param array $capabilities User capabilities required to see this notice (optional)
     * @param int $expiry_days Days until notice expires (optional, 0 = never expires)
     */
    public static function add_notice($id, $message, $type = 'info', $dismissible = true, $capabilities = array('manage_options'), $expiry_days = 0) {
        $notices = get_option('bst_admin_notices', array());
        
        $notice = array(
            'id' => $id,
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible,
            'capabilities' => $capabilities,
            'created' => current_time('timestamp'),
            'expiry_days' => $expiry_days
        );
        
        $notices[$id] = $notice;
        update_option('bst_admin_notices', $notices);
    }
    
    /**
     * Remove a notification completely
     */
    public static function remove_notice($id) {
        $notices = get_option('bst_admin_notices', array());
        if (isset($notices[$id])) {
            unset($notices[$id]);
            update_option('bst_admin_notices', $notices);
        }
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        $notices = get_option('bst_admin_notices', array());
        
        $current_user_id = get_current_user_id();
        $current_time = current_time('timestamp');
        
        foreach ($notices as $notice) {
            // Check if user has required capabilities
            if (!empty($notice['capabilities'])) {
                $has_capability = false;
                foreach ($notice['capabilities'] as $cap) {
                    if (current_user_can($cap)) {
                        $has_capability = true;
                        break;
                    }
                }
                if (!$has_capability) {
                    continue;
                }
            }
            
            // Check if notice has expired
            if ($notice['expiry_days'] > 0) {
                $expiry_time = $notice['created'] + ($notice['expiry_days'] * DAY_IN_SECONDS);
                if ($current_time > $expiry_time) {
                    continue;
                }
            }
            
            // Check if user has dismissed this notice
            if ($notice['dismissible']) {
                $dismissed_notices = get_user_meta($current_user_id, 'bst_dismissed_notices', true);
                if (!is_array($dismissed_notices)) {
                    $dismissed_notices = array();
                }
                if (in_array($notice['id'], $dismissed_notices)) {
                    continue;
                }
            }
            
            // Display the notice
            $dismissible_class = $notice['dismissible'] ? 'is-dismissible' : '';
            $notice_class = sanitize_html_class($notice['type']);
            
            echo '<div class="notice notice-' . $notice_class . ' ' . $dismissible_class . '" data-notice-id="' . esc_attr($notice['id']) . '">';
            echo '<p>' . wp_kses_post($notice['message']) . '</p>';
            if ($notice['dismissible']) {
                echo '<button type="button" class="notice-dismiss bst-notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>';
            }
            echo '</div>';
        }
    }
    
    /**
     * Handle AJAX notice dismissal
     */
    public function dismiss_notice() {
        check_ajax_referer('bst_notice_nonce', 'nonce');
        
        $notice_id = sanitize_text_field($_POST['notice_id']);
        $current_user_id = get_current_user_id();
        
        // Get user's dismissed notices
        $dismissed_notices = get_user_meta($current_user_id, 'bst_dismissed_notices', true);
        if (!is_array($dismissed_notices)) {
            $dismissed_notices = array();
        }
        
        // Add this notice to dismissed list
        if (!in_array($notice_id, $dismissed_notices)) {
            $dismissed_notices[] = $notice_id;
            update_user_meta($current_user_id, 'bst_dismissed_notices', $dismissed_notices);
        }
        
        wp_die();
    }
    
    /**
     * Enqueue scripts for notice handling
     */
    public function enqueue_notice_scripts($hook) {
        // Only load on admin pages
        if (!is_admin()) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Inline script for notice dismissal
        $script = "
        jQuery(document).ready(function($) {
            $(document).on('click', '.bst-notice-dismiss', function(e) {
                e.preventDefault();
                var notice = $(this).closest('.notice');
                var noticeId = notice.data('notice-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bst_dismiss_notice',
                        notice_id: noticeId,
                        nonce: '" . wp_create_nonce('bst_notice_nonce') . "'
                    },
                    success: function() {
                        notice.fadeOut();
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script('jquery', $script);
    }
    
    /**
     * Get all active notices for current user (useful for dashboard widget)
     */
    public static function get_user_notices($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $notices = get_option('bst_admin_notices', array());
        $dismissed_notices = get_user_meta($user_id, 'bst_dismissed_notices', true);
        if (!is_array($dismissed_notices)) {
            $dismissed_notices = array();
        }
        
        $active_notices = array();
        $current_time = current_time('timestamp');
        
        foreach ($notices as $notice) {
            // Check capabilities
            if (!empty($notice['capabilities'])) {
                $has_capability = false;
                foreach ($notice['capabilities'] as $cap) {
                    if (user_can($user_id, $cap)) {
                        $has_capability = true;
                        break;
                    }
                }
                if (!$has_capability) {
                    continue;
                }
            }
            
            // Check expiry
            if ($notice['expiry_days'] > 0) {
                $expiry_time = $notice['created'] + ($notice['expiry_days'] * DAY_IN_SECONDS);
                if ($current_time > $expiry_time) {
                    continue;
                }
            }
            
            // Check if dismissed
            if ($notice['dismissible'] && in_array($notice['id'], $dismissed_notices)) {
                continue;
            }
            
            $active_notices[] = $notice;
        }
        
        return $active_notices;
    }
}

// Initialize notifications using singleton
BST_Notifications::get_instance();
