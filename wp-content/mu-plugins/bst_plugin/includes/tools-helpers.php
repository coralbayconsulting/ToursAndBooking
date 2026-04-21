<?php
/**
 * BST Plugin – Tools page helpers
 *
 * Logic for the Tools admin page: error log path detection, cron table data.
 * Keeps templates thin and testable.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalized filesystem path from PHP's error_log INI, or empty if syslog / unset / not a path.
 *
 * @return string Absolute path or ''.
 */
function bst_tools_normalize_ini_error_log_path() {
    $ini = ini_get( 'error_log' );
    if ( ! is_string( $ini ) || '' === trim( $ini ) ) {
        return '';
    }
    $ini = trim( $ini );
    if ( 0 === stripos( $ini, 'syslog' ) ) {
        return '';
    }
    // Absolute Unix/Windows path only (what we can read in Tools).
    if ( ! preg_match( '#^([a-zA-Z]:[/\\\\]|[/\\\\])#', $ini ) ) {
        return '';
    }
    return wp_normalize_path( $ini );
}

/**
 * Human-readable value of PHP error_log INI for admin UI.
 *
 * @return string
 */
function bst_tools_get_ini_error_log_display() {
    $raw = ini_get( 'error_log' );
    if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
        return __( '(not set — PHP may send errors to stderr / the host log stream instead of a file)', 'bst-plugin' );
    }
    return trim( $raw );
}

/**
 * Get the error log file path to show in Tools (tail / download / clear).
 *
 * Priority: (1) PHP INI error_log when that file exists and is readable — this is where
 * error_log() from cron (e.g. exchange rates), plugins, and BST release cleanup lines go.
 * (2) wp-content/debug.log / error.log (WordPress WP_DEBUG_LOG or copies). (3) hosted-env
 * fallbacks (Azure paths, etc.). (4) display fallback when the file is missing.
 *
 * This is not tied to Apache; PHP-FPM / php.ini / pool config set error_log.
 *
 * @return string Path to the error log file (existing or best guess for display).
 */
function bst_get_tools_error_log_path() {
    $ini_path = bst_tools_normalize_ini_error_log_path();
    if ( $ini_path && file_exists( $ini_path ) && is_readable( $ini_path ) ) {
        return $ini_path;
    }

    $wp_debug = trailingslashit( WP_CONTENT_DIR ) . 'debug.log';
    if ( file_exists( $wp_debug ) && is_readable( $wp_debug ) ) {
        return wp_normalize_path( $wp_debug );
    }

    $wp_err = trailingslashit( WP_CONTENT_DIR ) . 'error.log';
    if ( file_exists( $wp_err ) && is_readable( $wp_err ) ) {
        return wp_normalize_path( $wp_err );
    }

    $potential_paths = array(
        '/home/LogFiles/php_errors.log',
        '/home/LogFiles/application.log',
        $wp_debug,
        $wp_err,
        $ini_path,
        '/var/log/php_errors.log',
    );

    foreach ( $potential_paths as $path ) {
        if ( $path && file_exists( $path ) && filesize( $path ) > 0 ) {
            return wp_normalize_path( $path );
        }
    }
    foreach ( $potential_paths as $path ) {
        if ( $path && file_exists( $path ) ) {
            return wp_normalize_path( $path );
        }
    }

    if ( is_dir( '/home/logfiles/wordpress/logs' ) ) {
        $log_files = glob( '/home/logfiles/wordpress/logs/wordpress_*.log' );
        if ( $log_files ) {
            usort(
                $log_files,
                function ( $a, $b ) {
                    return filemtime( $b ) - filemtime( $a );
                }
            );
            return wp_normalize_path( $log_files[0] );
        }
    }

    if ( $ini_path ) {
        return $ini_path;
    }
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        return wp_normalize_path( $wp_debug );
    }

    return wp_normalize_path( $wp_debug );
}

/**
 * If this is a valid error log download request, send the file and exit.
 * Call at the very top of the Tools template before any output.
 */
function bst_tools_maybe_send_error_log_download() {
    if (!isset($_POST['download_error_log']) || !isset($_POST['error_log_nonce']) ||
        !wp_verify_nonce($_POST['error_log_nonce'], 'download_error_log')) {
        return;
    }

    $path = bst_get_tools_error_log_path();
    if (!file_exists($path)) {
        error_log('BST Tools: error log file not found at ' . $path);
        return;
    }

    if (ob_get_length()) {
        ob_end_clean();
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="error_log_' . date('Y-m-d_H-i-s') . '.txt"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        if (readfile($path) !== false) {
            exit;
        }
    }

    wp_redirect(admin_url('admin.php?page=bst-tools&download_error=headers_sent'));
    exit;
}

/**
 * Cron frequency options for the Tools page dropdown.
 *
 * @return array<string, string>
 */
function bst_tools_get_cron_frequency_options() {
    return array(
        'disabled'   => 'Disabled',
        'hourly'     => 'Hourly',
        'twicedaily' => 'Twice Daily',
        'daily'      => 'Daily',
        'daily_after_midnight' => 'Daily after midnight',
        'weekly'     => 'Weekly',
    );
}

/**
 * Human-readable descriptions for BST cron event hooks.
 *
 * @return array<string, string>
 */
function bst_tools_get_cron_event_descriptions() {
    return array(
        'bst_daily_availability_sync' => 'Daily sync of tour availability data',
        'bst_fetch_exchange_rates_event' => 'Update currency exchange rates',
    );
}

/**
 * Reschedule a cron hook with new frequency (called from Tools form handler).
 *
 * @param string $hook          Cron hook name.
 * @param string $new_frequency Schedule key (e.g. daily, hourly) or 'disabled'.
 */
function bst_tools_reschedule_cron($hook, $new_frequency) {
    wp_clear_scheduled_hook($hook);
    if ($new_frequency === 'disabled') {
        return;
    }
    if ($hook === 'bst_daily_availability_sync') {
        $next_time = strtotime('3:00 AM today');
        if ($next_time < time()) {
            $next_time = strtotime('3:00 AM tomorrow');
        }
        wp_schedule_event($next_time, $new_frequency, $hook);
    } elseif ($hook === 'bst_fetch_exchange_rates_event') {
        wp_schedule_event(strtotime('tomorrow 00:30:00 UTC'), $new_frequency, $hook);
    } else {
        wp_schedule_event(time() + 60, $new_frequency, $hook);
    }
}

/**
 * Get run action name for manual cron trigger (Tools page "Run" button).
 *
 * @param string $hook Cron hook name.
 * @return string Empty string or AJAX action name.
 */
function bst_tools_get_run_action_for_hook($hook) {
    if ($hook === 'bst_daily_availability_sync') {
        return 'bst_run_availability_sync';
    }
    if ($hook === 'bst_fetch_exchange_rates_event') {
        return 'bst_run_exchange_rates';
    }
    return '';
}

/**
 * Build rows for the Tools page cron table (BST events only).
 *
 * @return array<int, array{hook: string, description: string, next_run: int, frequency: string, frequency_label: string, status_label: string, status_class: string, action_name: string}>
 */
function bst_tools_get_cron_table_rows() {
    $cron_events = _get_cron_array();
    $schedules = wp_get_schedules();
    $descriptions = bst_tools_get_cron_event_descriptions();
    $current_time = time();
    $processed_hooks = array();
    $rows = array();

    foreach ($cron_events as $timestamp => $events) {
        foreach ($events as $hook => $event_data) {
            if (in_array($hook, $processed_hooks, true)) {
                continue;
            }
            $is_bst = (strpos($hook, 'bst_') === 0);
            $is_exchange = (strpos($hook, 'exchange') !== false || strpos($hook, 'currency') !== false);
            if (!$is_bst && !$is_exchange) {
                continue;
            }

            foreach ($event_data as $event) {
                $processed_hooks[] = $hook;
                $frequency = isset($event['schedule']) ? $event['schedule'] : 'single';
                $frequency_label = isset($schedules[ $frequency ]['display']) ? $schedules[ $frequency ]['display'] : ucfirst($frequency);
                $description = isset($descriptions[ $hook ]) ? $descriptions[ $hook ] : 'Custom event';
                $action_name = bst_tools_get_run_action_for_hook($hook);

                if ($frequency === 'single') {
                    $status_label = 'One-time';
                    $status_class = 'bst-cron-status-onetime';
                } elseif ($timestamp <= $current_time) {
                    $status_label = '⚠ Overdue';
                    $status_class = 'bst-cron-status-overdue';
                } else {
                    $status_label = '✓ Scheduled';
                    $status_class = 'bst-cron-status-ok';
                }

                $rows[] = array(
                    'hook'            => $hook,
                    'description'     => $description,
                    'next_run'        => $timestamp,
                    'frequency'       => $frequency,
                    'frequency_label' => $frequency_label,
                    'status_label'    => $status_label,
                    'status_class'    => $status_class,
                    'action_name'     => $action_name,
                );
                break;
            }
        }
    }

    return $rows;
}
