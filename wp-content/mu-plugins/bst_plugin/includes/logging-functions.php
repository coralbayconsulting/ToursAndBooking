<?php

// Logging section callback
function bst_logging_section_callback() {
    echo '<p>View and manage PHP error logs, WordPress debug logs, and configure logging settings:</p>';
}

// PHP Error Log Viewer callback
function bst_php_error_log_viewer_callback() {
    require_once(plugin_dir_path(__FILE__) . 'php-log-helper.php');
    
    ?>
    <div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
        <h4>PHP Error Log Viewer</h4>
        <p>View recent PHP errors from the server error log:</p>
        
        <div style="margin-bottom: 15px;">
            <label for="php_log_lines" style="display: inline-block; margin-right: 10px; font-weight: bold;">Lines to show:</label>
            <select id="php_log_lines" style="margin-right: 15px;">
                <option value="50">50</option>
                <option value="100" selected>100</option>
                <option value="200">200</option>
                <option value="500">500</option>
            </select>
            
            <button type="button" id="load_php_log" class="button button-primary">Load Log</button>
            <button type="button" id="clear_php_log_display" class="button" style="margin-left: 5px;">Clear Display</button>
            <span id="php_log_spinner" class="spinner" style="margin-left: 10px; display: none;"></span>
        </div>
        
        <div id="php_log_content" style="background: #fff; border: 1px solid #ccc; padding: 10px; min-height: 300px; max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
        
        <div style="margin-top: 10px;">
            <button type="button" id="download_php_log" class="button">Download Full Log</button>
            <span style="color: #666; font-size: 12px; margin-left: 10px;">Log path: <?php echo esc_html(bst_get_php_error_log_path() ?: 'Not configured'); ?></span>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#load_php_log').click(function() {
            var lines = $('#php_log_lines').val();
            var $spinner = $('#php_log_spinner');
            var $content = $('#php_log_content');
            
            $spinner.show();
            
            $.post(ajaxurl, {
                action: 'bst_load_php_log',
                lines: lines,
                nonce: '<?php echo wp_create_nonce('bst_load_log'); ?>'
            }, function(response) {
                $spinner.hide();
                if (response.success) {
                    $content.html(response.data);
                } else {
                    $content.html('<span style="color: red;">Error: ' + response.data + '</span>');
                }
            });
        });
        
        $('#clear_php_log_display').click(function() {
            $('#php_log_content').html('');
        });
        
        $('#download_php_log').click(function() {
            window.location.href = ajaxurl + '?action=bst_download_php_log&nonce=' + '<?php echo wp_create_nonce('bst_download_log'); ?>';
        });
    });
    </script>
    <?php
}

// WordPress Debug Log Viewer callback
function bst_wp_debug_log_viewer_callback() {
    ?>
    <div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
        <h4>WordPress Debug Log Viewer</h4>
        <p>View recent WordPress debug entries:</p>
        
        <div style="margin-bottom: 15px;">
            <label for="wp_log_lines" style="display: inline-block; margin-right: 10px; font-weight: bold;">Lines to show:</label>
            <select id="wp_log_lines" style="margin-right: 15px;">
                <option value="50">50</option>
                <option value="100" selected>100</option>
                <option value="200">200</option>
                <option value="500">500</option>
            </select>
            
            <button type="button" id="load_wp_log" class="button button-primary">Load Log</button>
            <button type="button" id="clear_wp_log_display" class="button" style="margin-left: 5px;">Clear Display</button>
            <span id="wp_log_spinner" class="spinner" style="margin-left: 10px; display: none;"></span>
        </div>
        
        <div id="wp_log_content" style="background: #fff; border: 1px solid #ccc; padding: 10px; min-height: 300px; max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
        
        <div style="margin-top: 10px;">
            <button type="button" id="download_wp_log" class="button">Download Full Log</button>
            <span style="color: #666; font-size: 12px; margin-left: 10px;">Log path: <?php echo esc_html(WP_CONTENT_DIR . '/debug.log'); ?></span>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#load_wp_log').click(function() {
            var lines = $('#wp_log_lines').val();
            var $spinner = $('#wp_log_spinner');
            var $content = $('#wp_log_content');
            
            $spinner.show();
            
            $.post(ajaxurl, {
                action: 'bst_load_wp_log',
                lines: lines,
                nonce: '<?php echo wp_create_nonce('bst_load_log'); ?>'
            }, function(response) {
                $spinner.hide();
                if (response.success) {
                    $content.html(response.data);
                } else {
                    $content.html('<span style="color: red;">Error: ' + response.data + '</span>');
                }
            });
        });
        
        $('#clear_wp_log_display').click(function() {
            $('#wp_log_content').html('');
        });
        
        $('#download_wp_log').click(function() {
            window.location.href = ajaxurl + '?action=bst_download_wp_log&nonce=' + '<?php echo wp_create_nonce('bst_download_log'); ?>';
        });
    });
    </script>
    <?php
}

// Logging Settings callback
function bst_logging_settings_callback() {
    $wp_debug = defined('WP_DEBUG') && WP_DEBUG;
    $wp_debug_log = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    $php_log_errors = ini_get('log_errors');
    $php_error_log = ini_get('error_log');
    
    ?>
    <div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
        <h4>Current Logging Configuration</h4>
        
        <table class="widefat" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th>Setting</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>WordPress Debug</strong></td>
                    <td>
                        <span class="dashicons <?php echo $wp_debug ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>" 
                              style="color: <?php echo $wp_debug ? 'green' : 'red'; ?>;"></span>
                        <?php echo $wp_debug ? 'Enabled' : 'Disabled'; ?>
                    </td>
                    <td>WP_DEBUG constant in wp-config.php</td>
                </tr>
                <tr>
                    <td><strong>WordPress Debug Logging</strong></td>
                    <td>
                        <span class="dashicons <?php echo $wp_debug_log ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>" 
                              style="color: <?php echo $wp_debug_log ? 'green' : 'red'; ?>;"></span>
                        <?php echo $wp_debug_log ? 'Enabled' : 'Disabled'; ?>
                    </td>
                    <td>WP_DEBUG_LOG constant in wp-config.php</td>
                </tr>
                <tr>
                    <td><strong>PHP Error Logging</strong></td>
                    <td>
                        <span class="dashicons <?php echo $php_log_errors ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>" 
                              style="color: <?php echo $php_log_errors ? 'green' : 'red'; ?>;"></span>
                        <?php echo $php_log_errors ? 'Enabled' : 'Disabled'; ?>
                    </td>
                    <td>PHP log_errors setting</td>
                </tr>
                <tr>
                    <td><strong>PHP Error Log Path</strong></td>
                    <td colspan="2">
                        <code><?php echo esc_html($php_error_log ?: 'Not set (using system default)'); ?></code>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
            <h4 style="margin-top: 0;">Configuration Notes:</h4>
            <ul style="margin-bottom: 0;">
                <li>WordPress debug settings are controlled by constants in wp-config.php</li>
                <li>PHP error logging is controlled by server configuration</li>
                <li>Debug logs may contain sensitive information - handle with care</li>
                <li>Large log files can impact server performance</li>
            </ul>
        </div>
    </div>
    <?php
}

// AJAX handler for loading PHP error log
add_action('wp_ajax_bst_load_php_log', 'bst_load_php_log_ajax');

function bst_load_php_log_ajax() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'bst_load_log')) {
        wp_die('Invalid nonce');
    }
    
    require_once(plugin_dir_path(__FILE__) . 'php-log-helper.php');
    
    $lines = intval($_POST['lines']);
    $log_path = bst_get_php_error_log_path();
    
    if (!$log_path || !file_exists($log_path)) {
        wp_send_json_error('PHP error log file not found');
        return;
    }
    
    if (!is_readable($log_path)) {
        wp_send_json_error('PHP error log file is not readable');
        return;
    }
    
    $content = bst_tail_file($log_path, $lines);
    wp_send_json_success(esc_html(implode("\n", $content)));
}

// AJAX handler for loading WordPress debug log
add_action('wp_ajax_bst_load_wp_log', 'bst_load_wp_log_ajax');

function bst_load_wp_log_ajax() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'bst_load_log')) {
        wp_die('Invalid nonce');
    }
    
    $lines = intval($_POST['lines']);
    $log_path = WP_CONTENT_DIR . '/debug.log';
    
    if (!file_exists($log_path)) {
        wp_send_json_error('WordPress debug log file not found');
        return;
    }
    
    if (!is_readable($log_path)) {
        wp_send_json_error('WordPress debug log file is not readable');
        return;
    }
    
    $content = bst_tail_file($log_path, $lines);
    wp_send_json_success(esc_html(implode("\n", $content)));
}

// AJAX handler for downloading PHP error log
add_action('wp_ajax_bst_download_php_log', 'bst_download_php_log_ajax');

function bst_download_php_log_ajax() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_GET['nonce'], 'bst_download_log')) {
        wp_die('Invalid nonce');
    }
    
    require_once(plugin_dir_path(__FILE__) . 'php-log-helper.php');
    
    $log_path = bst_get_php_error_log_path();
    
    if (!$log_path || !file_exists($log_path)) {
        wp_die('PHP error log file not found');
    }
    
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="php_error_log_' . date('Y-m-d_H-i-s') . '.txt"');
    readfile($log_path);
    exit;
}

// AJAX handler for downloading WordPress debug log
add_action('wp_ajax_bst_download_wp_log', 'bst_download_wp_log_ajax');

function bst_download_wp_log_ajax() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_GET['nonce'], 'bst_download_log')) {
        wp_die('Invalid nonce');
    }
    
    $log_path = WP_CONTENT_DIR . '/debug.log';
    
    if (!file_exists($log_path)) {
        wp_die('WordPress debug log file not found');
    }
    
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="wp_debug_log_' . date('Y-m-d_H-i-s') . '.txt"');
    readfile($log_path);
    exit;
}

// Helper function to get last N lines from a file
if (!function_exists('bst_tail_file')) {
    function bst_tail_file($filename, $lines = 100) {
        $handle = fopen($filename, "r");
        if (!$handle) {
            return array("Unable to open file");
        }
        
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = array();
        
        while ($linecounter > 0) {
            $t = " ";
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[$lines - $linecounter - 1] = fgets($handle);
            if ($beginning) break;
        }
        fclose($handle);
        return array_reverse($text);
    }
}
