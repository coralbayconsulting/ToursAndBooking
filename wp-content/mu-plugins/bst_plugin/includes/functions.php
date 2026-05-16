<?php

// Include custom post types
require_once plugin_dir_path(__DIR__) . 'includes/custom-post-types.php';

// Include admin settings
require_once plugin_dir_path(__DIR__) . 'includes/admin-settings.php';

// Include exchange rates functions
require_once plugin_dir_path(__DIR__) . 'includes/exchange-rates.php';

// Include tour dropdown AJAX handlers
require_once plugin_dir_path(__DIR__) . 'includes/tour-dropdown-handlers.php';

// Include customer actions (centralized CRUD operations)
require_once plugin_dir_path(__DIR__) . 'includes/database/customer-actions.php';

// Start the session if it hasn't been started already and headers not sent.
// Some production requests can contain an invalid/overlong PHPSESSID value
// (e.g. from a corrupted cookie or URL parameter), which triggers warnings
// and can break the session read. We defensively ignore invalid IDs here.
if (!session_id() && !headers_sent()) {
    $sid_name = session_name();
    $sid_re   = '/^[A-Za-z0-9\-\x2C]+$/'; // allowed: A-Z a-z 0-9 - ,

    // Prefer cookies only (avoid using session id from URL params if configured).
    // If the cookie contains an invalid value, we unset it below before session_start().
    if (ini_get('session.use_only_cookies') !== '1') {
        @ini_set('session.use_only_cookies', '1');
    }

    // If GET carries a session id, ignore it by unsetting.
    if (isset($_GET[$sid_name])) {
        $incoming = (string) $_GET[$sid_name];
        if ($incoming !== '' && (!preg_match($sid_re, $incoming) || strlen($incoming) > 128)) {
            unset($_GET[$sid_name]);
        }
    }

    // If cookie carries an invalid session id, unset it so PHP generates a new one.
    if (isset($_COOKIE[$sid_name])) {
        $incoming = (string) $_COOKIE[$sid_name];
        if ($incoming !== '' && (!preg_match($sid_re, $incoming) || strlen($incoming) > 128)) {
            unset($_COOKIE[$sid_name]);
        }
    }

    session_start();
}

/**
 * Check whether a cron hook currently has a scheduled event.
 *
 * Uses the cron option directly so we still detect events registered with
 * non-default $args (wp_next_scheduled / wp_get_scheduled_event only match
 * the args you pass, which can cause duplicate scheduling attempts and
 * wp_schedule_event returning false when the row already exists).
 *
 * @param string $hook Cron hook name.
 * @return bool
 */
function bst_is_cron_hook_scheduled($hook) {
    $crons = _get_cron_array();
    if (empty($crons) || !is_array($crons)) {
        return false;
    }
    foreach ($crons as $timestamp => $cron) {
        if (!is_numeric($timestamp)) {
            continue;
        }
        if (!is_array($cron) || empty($cron[$hook]) || !is_array($cron[$hook])) {
            continue;
        }
        return true;
    }
    return false;
}

/**
 * True if cron already defines this hook (any args or recurrence).
 * Bootstrap code uses this so we never call wp_schedule_event() when Tools (or WordPress)
 * already registered the task — avoids no-op saves, bogus errors, and overwriting a
 * recurrence you chose in the BST Tools UI.
 *
 * Pair with {@see wp_cache_delete} for `alloptions` on Redis/Memcached when you need the
 * freshest view right before scheduling; {@see bst_schedule_cron_event_once} handles that.
 *
 * @param string $hook Cron hook name.
 * @return bool
 */
function bst_wp_cron_hook_is_registered($hook) {
    if (bst_is_cron_hook_scheduled($hook)) {
        return true;
    }
    return function_exists('wp_next_scheduled') && (bool) wp_next_scheduled($hook);
}

/**
 * First-time cron registration only: if the hook already exists, returns immediately — no writes.
 * Frequency or next-run changes use the BST Tools page (clear + reschedule), not this bootstrap.
 *
 * @param string $hook         Cron hook name.
 * @param int    $timestamp    First run timestamp.
 * @param string $recurrence   Cron recurrence key (daily, hourly, etc).
 * @param string $label        Human-friendly label for logs.
 * @param bool   $log_success  Whether to log successful scheduling.
 * @return bool True when scheduled or already registered; false only on hard failure.
 */
function bst_schedule_cron_event_once($hook, $timestamp, $recurrence, $label, $log_success = true) {
    if (bst_wp_cron_hook_is_registered($hook)) {
        return true;
    }

    // `cron` is autoloaded; a stale `alloptions` cache entry (common with Redis/Memcached)
    // makes registered hooks invisible and would cause duplicate wp_schedule_event calls below.
    if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache() && function_exists('wp_cache_delete')) {
        wp_cache_delete('alloptions', 'options');
    }

    if (bst_wp_cron_hook_is_registered($hook)) {
        return true;
    }

    $scheduled = wp_schedule_event($timestamp, $recurrence, $hook, array(), true);

    if ($scheduled === true) {
        if ($log_success) {
            error_log('BST Cron: Scheduled ' . $label . ' (' . $hook . ') with recurrence "' . $recurrence . '".');
        }
        return true;
    }

    if (is_wp_error($scheduled)) {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache() && function_exists('wp_cache_delete')) {
            wp_cache_delete('alloptions', 'options');
        }
        $has_hook = bst_wp_cron_hook_is_registered($hook);
        $err_code = $scheduled->get_error_code();
        // Core: identical serialized `cron` array makes update_option() return false; core maps that to
        // could_not_set. That is routine when the hook is already persisted — never treat as fatal.
        if ('could_not_set' === $err_code && $has_hook) {
            return true;
        }
        error_log(
            'BST Cron: Failed to schedule ' . $label . ' (' . $hook . '). ' .
            $scheduled->get_error_code() . ': ' . $scheduled->get_error_message()
        );
        if ($has_hook) {
            return true;
        }
        return false;
    }

    if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache() && function_exists('wp_cache_delete')) {
        wp_cache_delete('alloptions', 'options');
    }
    if (bst_wp_cron_hook_is_registered($hook)) {
        return true;
    }

    error_log(
        'BST Cron: Failed to schedule ' . $label . ' (' . $hook . '). ' .
        'wp_schedule_event returned: ' . var_export($scheduled, true) .
        '; wp_next_scheduled: ' . var_export(function_exists('wp_next_scheduled') ? wp_next_scheduled($hook) : null, true)
    );
    return false;
}

/**
 * Calculate deposit amount based on tour's deposit settings
 * 
 * @param int $tour_id Tour post ID
 * @param float $net_tour_price Net tour price
 * @param int $package_people Number of people in package (1 or 2)
 * @return float Calculated deposit amount
 */
function bst_calculate_deposit($tour_id, $net_tour_price, $package_people = 1) {
    $deposit_type = get_field('deposit_type', $tour_id);
    
    if ($deposit_type === 'Percentage') {
        $deposit_percent = get_field('deposit_percent', $tour_id);
        if (empty($deposit_percent)) {
            return 0; // No fallback - return 0 if not configured
        }
        // Treat deposit_percent as whole number (e.g., 20 = 20%)
        return $net_tour_price * ($deposit_percent / 100);
    } elseif ($deposit_type === 'Fixed Amount') {
        if ($package_people == 2) {
            $deposit_amount = get_field('deposit_fixed_double', $tour_id);
        } else {
            $deposit_amount = get_field('deposit_fixed_single', $tour_id);
        }
        
        if (empty($deposit_amount)) {
            return 0; // No fallback - return 0 if not configured
        }
        
        return floatval($deposit_amount);
    }
    
    // No fallback - return 0 if deposit type not set
    return 0;
}

/**
 * Check if current user has access to sensitive BST operations
 * Only Wayne can see sensitive operations (case-insensitive)
 * 
 * @return bool
 */
function bst_user_has_sensitive_access() {
    $current_user = wp_get_current_user();
    return (strtolower($current_user->user_login) === 'wayne');
}

// Code to disable admin notices
function pr_disable_admin_notices() {
    global $wp_filter;
    if ( is_user_admin() ) {
        if ( isset( $wp_filter['user_admin_notices'] ) ) {
            unset( $wp_filter['user_admin_notices'] );
        }
    } elseif ( isset( $wp_filter['admin_notices'] ) ) {
        unset( $wp_filter['admin_notices'] );
    }
    if ( isset( $wp_filter['all_admin_notices'] ) ) {
        unset( $wp_filter['all_admin_notices'] );
    }
}
add_action('admin_init', 'pr_disable_admin_notices');

// Function to set source and referrer in the session
function set_source_and_referrer() {
    // Check if the source parameter exists in the query string
    if (isset($_GET['source'])) {
        $_SESSION['source'] = sanitize_text_field($_GET['source']);
    }

    // Check if the user was referred from an external site
    if (!isset($_SESSION['referrer']) && isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
        $referrer = $_SERVER['HTTP_REFERER'];
        $host = parse_url($referrer, PHP_URL_HOST);
        $current_host = parse_url(home_url(), PHP_URL_HOST);

        // Only set the referrer if it's from an external site (excluding gravityapi.com - form preview/testing)
        if ($host && $host !== $current_host && stripos($host, 'gravityapi.com') === false) {
            $_SESSION['referrer'] = $host;
        }
    }
}
add_action('init', 'set_source_and_referrer');

// Function to format currency values according to the user's locale
function format_currency($price, $currency = 'EUR') {
    // Get the user's locale
    $locale = get_locale();

    // Create a NumberFormatter instance for the user's locale and currency formatting
    $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

    // Format the price as currency
    return $formatter->formatCurrency($price, $currency);
}

function bst_live_tour_title( $tour_id ) {
    $tour_id = (int) $tour_id;
    if ( $tour_id <= 0 ) {
        return '';
    }
    $p = get_post( $tour_id );
    return ( $p && 'tour' === $p->post_type ) ? (string) $p->post_title : '';
}

/**
 * Normalize a tour-date ACF/meta date string to Y-m-d (site calendar day where possible).
 *
 * @param string $raw start_date or end_date meta / ACF value.
 * @return string Y-m-d or empty if not parseable.
 */
function bst_tour_date_acf_date_meta_to_ymd( $raw ) {
    $raw = trim( (string) $raw );
    if ( '' === $raw ) {
        return '';
    }
    if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $parts ) ) {
        if ( checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
            return $raw;
        }
        return '';
    }
    if ( preg_match( '/^\d{8}$/', $raw ) ) {
        $yy = substr( $raw, 0, 4 );
        $mo = substr( $raw, 4, 2 );
        $dd = substr( $raw, 6, 2 );
        if ( checkdate( (int) $mo, (int) $dd, (int) $yy ) ) {
            return $yy . '-' . $mo . '-' . $dd;
        }
        return '';
    }
    $parsed = DateTime::createFromFormat( 'm/d/Y', $raw );
    if ( $parsed instanceof DateTime ) {
        return $parsed->format( 'Y-m-d' );
    }
    $start_ts = strtotime( $raw );
    if ( false !== $start_ts ) {
        return wp_date( 'Y-m-d', $start_ts );
    }
    return '';
}

/**
 * First tour-date post ID from a booking's tour_date_id (may include pipe suffix).
 *
 * @param int|string $tour_date_id Booking tour_date_id column.
 * @return int Tour-date post ID or 0.
 */
function bst_booking_tour_date_post_id( $tour_date_id ) {
    $raw = trim( (string) $tour_date_id );
    if ( '' === $raw ) {
        return 0;
    }
    $parts = explode( '|', $raw );

    return (int) trim( $parts[0] );
}

/**
 * Whether a tour date should appear on public schedule UIs.
 * Hides tour dates that start in calendar years before the current calendar year.
 *
 * @param string $start_raw Raw start_date from meta or ACF.
 * @param int    $tour_id   (unused) Parent tour post ID (kept for backward compatibility with call sites).
 * @return bool
 */
function bst_tour_date_show_on_public_schedule( $start_raw, $tour_id = 0 ) {
    $ymd = bst_tour_date_acf_date_meta_to_ymd( $start_raw );
    if ( '' === $ymd ) {
        return false;
    }
    return (int) substr( $ymd, 0, 4 ) >= (int) current_time( 'Y' );
}

/**
 * Whether a tour-date's calendar start date is today or earlier (site timezone).
 * Used to hide overslot / limited-vehicle oversold dashboard warnings once the departure day has begun.
 *
 * @param int $tour_date_id Tour date post ID.
 * @return bool True after the tour has started; false if unknown/invalid date (warnings stay visible).
 */
function bst_tour_date_has_started_for_dashboard( $tour_date_id ) {
    $tour_date_id = (int) $tour_date_id;
    if ( $tour_date_id <= 0 ) {
        return false;
    }
    $p = get_post( $tour_date_id );
    if ( ! $p || 'tour-date' !== $p->post_type ) {
        return false;
    }
    $start_raw = get_post_meta( $tour_date_id, 'start_date', true );
    if ( ( $start_raw === '' || null === $start_raw ) && function_exists( 'get_field' ) ) {
        $acf = get_field( 'start_date', $tour_date_id );
        $start_raw = ( is_scalar( $acf ) && '' !== $acf ) ? (string) $acf : '';
    }
    $start_ymd = bst_tour_date_acf_date_meta_to_ymd( $start_raw );
    if ( '' === $start_ymd ) {
        return false;
    }

    return strcmp( current_time( 'Y-m-d' ), $start_ymd ) >= 0;
}

function bst_live_tour_date_text( $tour_date_id ) {
    $tour_date_id = (int) $tour_date_id;
    if ( $tour_date_id <= 0 ) {
        return '';
    }
    $p = get_post( $tour_date_id );
    if ( ! $p || 'tour-date' !== $p->post_type ) {
        return '';
    }
    $start_date = get_post_meta( $tour_date_id, 'start_date', true );
    $end_date   = get_post_meta( $tour_date_id, 'end_date', true );
    if ( $start_date && $end_date ) {
        return ( date( 'M', strtotime( $start_date ) ) === date( 'M', strtotime( $end_date ) ) )
            ? date( 'j', strtotime( $start_date ) ) . '-' . date( 'j M Y', strtotime( $end_date ) )
            : date( 'j M', strtotime( $start_date ) ) . ' - ' . date( 'j M Y', strtotime( $end_date ) );
    }
    if ( $start_date ) {
        return date( 'j M Y', strtotime( $start_date ) );
    }
    return (string) $p->post_title;
}

function bst_live_package_name( $package_id ) {
    $package_id = (int) $package_id;
    if ( $package_id <= 0 ) {
        return '';
    }
    return (string) get_option( 'bst_package_' . $package_id . '_name', '' );
}

function bst_live_booking_tour_display( $booking ) {
    $tour_title = bst_live_tour_title( $booking->tour_id ?? 0 );
    $tour_date  = bst_live_tour_date_text( $booking->tour_date_id ?? 0 );
    $package    = bst_live_package_name( $booking->tour_package_id ?? 0 );
    $out = $tour_title;
    if ( '' !== $tour_date ) {
        $out .= ' (' . $tour_date . ')';
    }
    if ( '' !== $package ) {
        $out .= ' - ' . $package;
    }
    return $out;
}

/**
 * Tour extension title from Tour ACF (`extension_title`) when extension is offered — not booking snapshot text.
 *
 * @param int $tour_id Tour post ID.
 * @return string
 */
function bst_live_extension_title_for_tour( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 || ! function_exists( 'get_field' ) ) {
		return '';
	}
	$p = get_post( $tour_id );
	if ( ! $p || 'tour' !== $p->post_type ) {
		return '';
	}
	if ( ! get_field( 'extension_offered', $tour_id ) ) {
		return '';
	}
	$title = get_field( 'extension_title', $tour_id );
	return is_string( $title ) ? trim( $title ) : '';
}

/**
 * Extension date range from tour ACF + tour-date end date (same basis as rooming-list export), not booking columns.
 *
 * @param object $booking Row or stub with tour_id, tour_date_id (may be "id|suffix").
 * @return string e.g. "9 Dec 2025 - 13 Dec 2025", or empty.
 */
function bst_live_extension_date_range_for_booking( $booking ) {
	$tour_id = (int) ( $booking->tour_id ?? 0 );
	if ( $tour_id <= 0 || ! function_exists( 'get_field' ) ) {
		return '';
	}
	if ( ! get_field( 'extension_offered', $tour_id ) ) {
		return '';
	}
	$td_raw = isset( $booking->tour_date_id ) ? (string) $booking->tour_date_id : '';
	$parts  = explode( '|', $td_raw );
	$tour_date_id = (int) trim( $parts[0] );
	if ( $tour_date_id <= 0 ) {
		return '';
	}
	$extension_days = (int) get_field( 'extension_driving_days', $tour_id );
	$tour_end_date  = get_field( 'end_date', $tour_date_id );
	if ( $extension_days <= 0 || empty( $tour_end_date ) ) {
		return '';
	}
	$end_ts = strtotime( (string) $tour_end_date );
	if ( ! $end_ts ) {
		return '';
	}
	$ext_end_ts = strtotime( (string) $tour_end_date . ' +' . $extension_days . ' days' );
	if ( ! $ext_end_ts ) {
		return '';
	}
	return date( 'j M Y', $end_ts ) . ' - ' . date( 'j M Y', $ext_end_ts );
}

/**
 * One-line extension label for UI/email: live title + live date range when the booking has extension selected.
 *
 * @param object $booking Booking row or object with tour_id, tour_date_id, tour_extension_added.
 * @return string Empty if extension not selected or tour offers no displayable extension data.
 */
function bst_live_booking_extension_display_label( $booking ) {
	if ( ! is_object( $booking ) ) {
		return '';
	}
	if ( empty( $booking->tour_extension_added ) || (int) $booking->tour_extension_added !== 1 ) {
		return '';
	}
	$title = function_exists( 'bst_live_extension_title_for_tour' ) ? bst_live_extension_title_for_tour( (int) ( $booking->tour_id ?? 0 ) ) : '';
	$dates = function_exists( 'bst_live_extension_date_range_for_booking' ) ? bst_live_extension_date_range_for_booking( $booking ) : '';
	if ( '' === $title && '' === $dates ) {
		return '';
	}
	if ( '' !== $dates ) {
		return '' !== $title ? $title . ' (' . $dates . ')' : $dates;
	}
	return $title;
}

/**
 * Extension add-on amount: package extension row + prorated vehicle upgrade for extension days.
 * Works with any object that has tour_id, tour_package_id, vehicle1_id, vehicle2_id (e.g. booking row, or stub for single-tour before a booking exists).
 *
 * @param object $booking Object with tour_id, tour_package_id, vehicle1_id, vehicle2_id (other keys ignored).
 * @return float Non-negative amount in tour currency units (rounded like legacy JS).
 */
function bst_booking_extension_addon_amount( $booking ) {
	if ( ! is_object( $booking ) ) {
		return 0.0;
	}
	$tour_id    = (int) ( $booking->tour_id ?? 0 );
	$package_id = (int) ( $booking->tour_package_id ?? 0 );
	if ( $tour_id <= 0 || $package_id <= 0 || ! function_exists( 'get_field' ) ) {
		return 0.0;
	}
	$pricing = get_field( 'extension_pricing', $tour_id );
	if ( ! is_array( $pricing ) ) {
		return 0.0;
	}
	$key = 'package_' . $package_id;
	$base = isset( $pricing[ $key ] ) ? floatval( $pricing[ $key ] ) : 0.0;

	$extension_days = (int) get_field( 'extension_driving_days', $tour_id );
	$admin_days     = floatval( get_field( 'admin_vehicle_driving_days', $tour_id ) );
	if ( $admin_days <= 0.0 || $extension_days <= 0 || ! function_exists( 'bst_tour_vehicle_upgrade_amount' ) ) {
		return round( $base );
	}

	$v1 = (int) ( $booking->vehicle1_id ?? 0 );
	$v2 = (int) ( $booking->vehicle2_id ?? 0 );
	$total = $base;

	if ( $v1 > 0 ) {
		$up = bst_tour_vehicle_upgrade_amount( $tour_id, $v1 );
		if ( $up > 0.0 ) {
			$total += round( $up / $admin_days * $extension_days );
		}
	}
	if ( $v2 > 0 ) {
		$up = bst_tour_vehicle_upgrade_amount( $tour_id, $v2 );
		if ( $up > 0.0 ) {
			$total += round( $up / $admin_days * $extension_days );
		}
	}

	return (float) round( $total );
}

/**
 * Vehicle label for GF prepopulate: always from Vehicle CPT via {@see bst_vehicle_display_title()}, never from stored booking text.
 *
 * @param int $vehicle_post_id Vehicle CPT post ID.
 * @return string Empty if id invalid or helper missing.
 */
function bst_vehicle_label_for_gf_from_id( $vehicle_post_id ) {
	$vehicle_post_id = (int) $vehicle_post_id;
	if ( $vehicle_post_id <= 0 || ! function_exists( 'bst_vehicle_display_title' ) ) {
		return '';
	}
	return trim( (string) bst_vehicle_display_title( $vehicle_post_id ) );
}

// Enqueue custom admin CSS for specific admin pages
function bst_enqueue_custom_admin_css($hook) {
    global $typenow;

    // Enqueue for the 'tour' and 'tour-date' post types, or BST plugin admin pages
    if (($typenow == 'tour') || 
        ($typenow == 'tour-date') || 
        ($hook === 'bst-tours-booking_page_bst-tour-bookings') ||
        ($hook === 'bst-tours-booking_page_bst-plugin-customer-list') ||
        ($hook === 'toplevel_page_bst-plugin') ||
        (strpos($hook, 'bst-') !== false)) {
        
        // Use a more reliable URL path for Azure
        $css_url = content_url('mu-plugins/bst_plugin/css/bst-custom-admin.css');
        $css_file_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/css/bst-custom-admin.css';
        $version = file_exists($css_file_path) ? filemtime($css_file_path) : time();
        
        wp_enqueue_style('bst-custom-admin-css', $css_url, array(), $version);
    }
    
}
add_action('admin_enqueue_scripts', 'bst_enqueue_custom_admin_css');

// Handle customer export
add_action('admin_post_bst_export_customers', 'bst_export_customers_handler');
function bst_export_customers_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }
    
    if (!wp_verify_nonce($_POST['_wpnonce'], 'bst_export_customers')) {
        wp_die('Security check failed', 'Error', array('response' => 403));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'bst_customers';
    
    // Handle search filter (same logic as list page)
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'name';
    $order = isset($_POST['order']) && $_POST['order'] === 'desc' ? 'desc' : 'asc';
    
    // Define valid sort columns and their SQL equivalents (same as list page)
    $valid_orderby = array(
        'name' => 'last_name, first_name',
        'source' => 'data_source',
        'credit' => 'credit'
    );
    
    // Default to name if invalid orderby
    if (!array_key_exists($orderby, $valid_orderby)) {
        $orderby = 'name';
    }
    
    $where_clause = '';
    $query_params = array();
    
    if (!empty($search)) {
        $where_clause = " WHERE (first_name LIKE %s OR last_name LIKE %s OR partner_first LIKE %s OR partner_last LIKE %s OR email LIKE %s)";
        $search_param = '%' . $wpdb->esc_like($search) . '%';
        $query_params = array($search_param, $search_param, $search_param, $search_param, $search_param);
    }
    
    // Build ORDER BY clause
    $order_clause = " ORDER BY " . $valid_orderby[$orderby] . " " . strtoupper($order);
    
    // Fetch customers with same order as list page
    $query = "SELECT * FROM $table_name" . $where_clause . $order_clause;
    $customers = $wpdb->get_results($wpdb->prepare($query, $query_params));
    
    // Generate filename
    $filename = 'customers_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV Headers
    fputcsv($output, array(
        'ID',
        'First Name',
        'Last Name',
        'Partner First Name',
        'Partner Last Name',
        'Email',
        'Phone',
        'Credit',
        'Data Source',
        'Notes',
        'Created By',
        'Created Date',
        'Updated By',
        'Updated Date'
    ));
    
    // Add customer data
    foreach ($customers as $customer) {
        fputcsv($output, array(
            $customer->id,
            $customer->first_name,
            $customer->last_name,
            $customer->partner_first,
            $customer->partner_last,
            $customer->email,
            $customer->phone,
            $customer->credit,
            $customer->data_source,
            $customer->notes,
            $customer->created_by,
            $customer->created_date,
            $customer->updated_by,
            $customer->updated_date
        ));
    }
    
    fclose($output);
    exit;
}

// Handle invoices export
add_action('admin_post_bst_export_invoices', 'bst_export_invoices_handler');
function bst_export_invoices_handler() {
    if (!current_user_can('edit_posts')) {
        wp_die('Unauthorized', 'Error', array('response' => 403));
    }

    if (!wp_verify_nonce($_POST['_wpnonce'], 'bst_export_invoices')) {
        wp_die('Security check failed', 'Error', array('response' => 403));
    }

    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';

    // Read filters (same logic as bst_invoices_page)
    $filter_year  = isset($_POST['filter_year'])  ? intval($_POST['filter_year'])  : 0;
    $filter_month = isset($_POST['filter_month']) ? intval($_POST['filter_month']) : 0;
    $allowed_sort = array('invoice_number', 'invoice_date', 'name');
    $sort_by      = (isset($_POST['sort_by']) && in_array($_POST['sort_by'], $allowed_sort))
                      ? $_POST['sort_by'] : 'invoice_number';
    $sort_order   = (isset($_POST['sort_order']) && strtoupper($_POST['sort_order']) === 'DESC') ? 'DESC' : 'ASC';

    $order_map = array(
        'invoice_number' => 'booking_invoice_number ' . $sort_order,
        'invoice_date'   => 'booking_invoice_date '   . $sort_order . ', booking_invoice_number ASC',
        'name'           => 'guest1_last_name '       . $sort_order . ', guest1_first_name ' . $sort_order,
    );
    $order_sql = 'ORDER BY ' . $order_map[$sort_by];

    $where_conditions = array('booking_invoice_number IS NOT NULL');
    $where_params     = array();

    if ($filter_year > 0) {
        $where_conditions[] = 'YEAR(booking_invoice_date) = %d';
        $where_params[]     = $filter_year;
    }
    if ($filter_month > 0) {
        $where_conditions[] = 'MONTH(booking_invoice_date) = %d';
        $where_params[]     = $filter_month;
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    $sql = "SELECT * FROM {$booking_table} {$where_sql} {$order_sql}";

    if (!empty($where_params)) {
        $invoices = $wpdb->get_results($wpdb->prepare($sql, $where_params), OBJECT);
    } else {
        $invoices = $wpdb->get_results($sql, OBJECT);
    }

    // Build filename
    $filename_parts = array('invoices');
    if ($filter_year)  $filename_parts[] = $filter_year;
    if ($filter_month) $filename_parts[] = str_pad($filter_month, 2, '0', STR_PAD_LEFT);
    $filename_parts[] = date('Y-m-d');
    $filename = implode('_', $filename_parts) . '.csv';

    // Stream CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM so Excel opens it correctly
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, array(
        'Invoice #',
        'Invoice Date',
        'Booking ID',
        'Name',
        'Tour',
        'Tour Package Amount',
        'Vehicle 1 Name',
        'Vehicle 1 Amount',
        'Vehicle 2 Name',
        'Vehicle 2 Amount',
        'Other Services',
        'EU %',
        'VAT Rate %',
        'VAT on Vehicle Services',
        'Total',
        'Deposit Method',
        'Deposit Type',
        'Balance Method',
        'Balance Type',
        'Additional Method',
        'Additional Type',
        'Invoice URL',
    ));

    foreach ($invoices as $booking) {
        $currency        = $booking->tour_currency ?? 'EUR';
        $currency_symbol = ($currency === 'USD') ? '$' : '€';

        // Invoice date
        $invoice_date = '';
        if (!empty($booking->booking_invoice_date)) {
            $ts = strtotime($booking->booking_invoice_date);
            if ($ts) $invoice_date = date('Y-m-d', $ts);
        }

        // Name
        $name = trim(($booking->guest1_first_name ?? '') . ' ' . ($booking->guest1_last_name ?? ''));

        // Tour display
        $tour_display = function_exists('bst_live_tour_title') ? bst_live_tour_title($booking->tour_id ?? 0) : '';
        if (!empty($booking->tour_year))         $tour_display .= ' (' . $booking->tour_year . ')';
        $live_tour_date = function_exists('bst_live_tour_date_text') ? bst_live_tour_date_text($booking->tour_date_id ?? 0) : '';
        $live_package   = function_exists('bst_live_package_name') ? bst_live_package_name($booking->tour_package_id ?? 0) : '';
        if (!empty($live_tour_date))             $tour_display .= ' (' . $live_tour_date . ')';
        if (!empty($live_package))               $tour_display .= ' – ' . $live_package;

        // Amounts
        $tour_packages  = floatval($booking->booking_tour_package_amount  ?? 0);
        $vehicle1_amt   = floatval($booking->booking_vehicle_1_use_amount ?? 0);
        $vehicle2_amt   = floatval($booking->booking_vehicle_2_use_amount ?? 0);
        $other_services = $vehicle1_amt + $vehicle2_amt;
        $eu_percent     = floatval($booking->booking_eu_percent ?? 100);
        $vat_rate       = floatval($booking->booking_vat_rate   ?? 22);
        $vehicle_vat    = round($other_services * ($eu_percent / 100) * ($vat_rate / 100), 2);
        $total          = $tour_packages + $other_services + $vehicle_vat;

        // Vehicle names from Vehicle CPT ids only (no stored booking vehicle text).
        $vehicle1_name = function_exists( 'bst_booking_vehicle_display_text' ) ? bst_booking_vehicle_display_text( $booking, 1 ) : '';
        $vehicle2_name = function_exists( 'bst_booking_vehicle_display_text' ) ? bst_booking_vehicle_display_text( $booking, 2 ) : '';

        // Deposit method + type
        $deposit_method = $booking->deposit_payment_method ?? '';
        $deposit_type   = '';
        if (!empty($booking->booking_entry_id) && class_exists('GFAPI')) {
            $dep_entry = GFAPI::get_entry($booking->booking_entry_id);
            if ($dep_entry && is_array($dep_entry)) {
                if ($deposit_method === 'Credit Card') {
                    $deposit_type = bst_get_card_type($dep_entry, 9);
                    $deposit_type = $deposit_type ? 'Stripe/' . str_replace('Sepa', 'SEPA', ucwords($deposit_type)) : '';
                } elseif ($deposit_method === 'Bank Wire') {
                    $rc = rgar($dep_entry, '232', 'Other');
                    $deposit_type = ($rc === 'Other') ? 'EUR' : $rc;
                }
            }
        }

        // Balance method + type
        $balance_method = $booking->balance_payment_method ?? '';
        $balance_type   = '';
        if (!empty($booking->finalization_entry_id) && class_exists('GFAPI')) {
            $bal_entry = GFAPI::get_entry($booking->finalization_entry_id);
            if ($bal_entry && is_array($bal_entry)) {
                if ($balance_method === 'Credit Card') {
                    $balance_type = bst_get_card_type($bal_entry, 10);
                    $balance_type = $balance_type ? 'Stripe/' . str_replace('Sepa', 'SEPA', ucwords($balance_type)) : '';
                } elseif ($balance_method === 'Bank Wire') {
                    $rc = rgar($bal_entry, '280', 'Other');
                    $balance_type = ($rc === 'Other') ? 'EUR' : $rc;
                }
            }
        }

        // Additional method — no GF entry yet; credit card via Airwallex
        $additional_method = $booking->additional_payment_method ?? '';
        $additional_type   = ($additional_method === 'Credit Card') ? 'Airwallex' : '';

        // Invoice URL
        $invoice_url = '';
        if (!empty($booking->finalization_entry_id) && function_exists('bst_encode_booking_id')) {
            $encoded     = bst_encode_booking_id($booking->finalization_entry_id);
            $invoice_url = site_url('/bookinginvoice/') . '?eid=' . $encoded;
        }

        fputcsv($output, array(
            $booking->booking_invoice_number,
            $invoice_date,
            $booking->id,
            $name,
            $tour_display,
            number_format($tour_packages, 2, '.', ''),
            $vehicle1_name,
            number_format($vehicle1_amt, 2, '.', ''),
            $vehicle2_name,
            number_format($vehicle2_amt, 2, '.', ''),
            number_format($other_services, 2, '.', ''),
            number_format($eu_percent, 2, '.', ''),
            number_format($vat_rate, 2, '.', ''),
            number_format($vehicle_vat, 2, '.', ''),
            number_format($total, 2, '.', ''),
            $deposit_method,
            $deposit_type,
            $balance_method,
            $balance_type,
            $additional_method,
            $additional_type,
            $invoice_url,
        ));
    }

    fclose($output);
    exit;
}

/**
 * Sync sold slots, reserved slots, and availability for a specific tour date based on current bookings
 * This function updates sold_slots, reserved_slots, and availability meta fields for a single tour date
 * 
 * @param int $tour_date_id The ID of the tour-date post to sync
 * @return array Results with success status and any error messages
 */
function bst_sync_tour_date_sold_slots($tour_date_id, $context = 'unknown') {
    global $wpdb;
    
    $results = array(
        'success' => false,
        'sold_updated' => false,
        'reserved_updated' => false,
        'availability_updated' => false,
        'old_sold_value' => 0,
        'new_sold_value' => 0,
        'old_reserved_value' => 0,
        'new_reserved_value' => 0,
        'old_availability_value' => 0,
        'new_availability_value' => 0,
        'error' => ''
    );
    
    try {
        // Validate tour date ID
        $tour_date_post = get_post($tour_date_id);
        if (!$tour_date_post || $tour_date_post->post_type !== 'tour-date') {
            $results['error'] = 'Invalid tour date ID';
            return $results;
        }
        
        $booking_table = $wpdb->prefix . 'bst_tour_booking';
        
        // Get current values
        $old_sold = get_field('sold_slots', $tour_date_id);
        $old_sold = $old_sold ? intval($old_sold) : 0;
        
        $old_reserved = get_field('reserved_slots', $tour_date_id);
        $old_reserved = $old_reserved ? intval($old_reserved) : 0;
        
        $old_availability = get_field('available_slots', $tour_date_id);
        $old_availability = $old_availability ? intval($old_availability) : 0;
        
        // Calculate sold slots from active bookings
        // Processing and Payment Failed count as sales (affect availability) since they represent actual booking attempts
        $sold_sql = "SELECT COALESCE(SUM(package_vehicles), 0) as total_sold 
                     FROM $booking_table 
                     WHERE tour_date_id = %s 
                     AND booking_status IN ('Pending', 'Processing', 'Payment Failed', 'Booked', 'Finalized', 'Completed')";
        
        $new_sold = $wpdb->get_var($wpdb->prepare($sold_sql, $tour_date_id));
        $new_sold = intval($new_sold);
        
        // Calculate reserved slots from Reserved status bookings
        $reserved_sql = "SELECT COALESCE(SUM(package_vehicles), 0) as total_reserved 
                         FROM $booking_table 
                         WHERE tour_date_id = %s 
                         AND booking_status = 'Reserved'";
        
        $new_reserved = $wpdb->get_var($wpdb->prepare($reserved_sql, $tour_date_id));
        $new_reserved = intval($new_reserved);
        
        // Calculate availability: max_slots - sold_slots - offline_sold_slots - reserved_slots
        $max_slots = intval(get_post_meta($tour_date_id, 'max_slots', true));
        $offline_sold = intval(get_post_meta($tour_date_id, 'offline_sold_slots', true));
        $new_availability = $max_slots - $new_sold - $offline_sold - $new_reserved;
        
        // Check for overbooking before ensuring availability is never negative
        $total_slots_used = $new_sold + $offline_sold + $new_reserved;
        $old_total_slots_used = $old_sold + $offline_sold + $old_reserved;
        
        if ($total_slots_used > $max_slots) {
            error_log("BST Overbooking DETECTED - Tour Date ID: $tour_date_id, Max: $max_slots, Sold: $new_sold, Offline: $offline_sold, Reserved: $new_reserved, Total Used: $total_slots_used");
            
            // For sync operations, only notify if this is newly detected overbooking
            $should_notify = true;
            if (strpos($context, 'bulk_sync') !== false) {
                // Check if overbooking existed before this sync
                if ($old_total_slots_used > $max_slots) {
                    error_log("BST Notification - Sync detected existing overbooking, skipping notification for tour date $tour_date_id");
                    $should_notify = false;
                } else {
                    error_log("BST Notification - Sync detected NEW overbooking, will notify for tour date $tour_date_id");
                }
            }
            
            if ($should_notify) {
                // Try to get the most recent booking information for this tour date
                $booking_info = bst_get_recent_booking_for_overbooking($tour_date_id, $context);
                bst_send_overbooking_notification($tour_date_id, $max_slots, $new_sold, $offline_sold, $new_reserved, $context, $booking_info);
            }
        }
        // Only log overbooking when it's detected - too verbose otherwise
        
        // Ensure availability is never negative
        $new_availability = max(0, $new_availability);
        
        $results['old_sold_value'] = $old_sold;
        $results['new_sold_value'] = $new_sold;
        $results['old_reserved_value'] = $old_reserved;
        $results['new_reserved_value'] = $new_reserved;
        $results['old_availability_value'] = $old_availability;
        $results['new_availability_value'] = $new_availability;
        
        $updates_made = false;
        $log_parts = array();
        
        // Update sold_slots if changed
        if ($old_sold !== $new_sold) {
            $sold_update_result = update_field('sold_slots', $new_sold, $tour_date_id);
            
            if ($sold_update_result !== false) {
                $results['sold_updated'] = true;
                $updates_made = true;
                $log_parts[] = "sold: {$old_sold}→{$new_sold}";
            } else {
                $results['error'] = 'Failed to update sold_slots field';
            }
        }
        
        // Update reserved_slots if changed
        if ($old_reserved !== $new_reserved) {
            $reserved_update_result = update_field('reserved_slots', $new_reserved, $tour_date_id);
            
            if ($reserved_update_result !== false) {
                $results['reserved_updated'] = true;
                $updates_made = true;
                $log_parts[] = "reserved: {$old_reserved}→{$new_reserved}";
            } else {
                if (empty($results['error'])) {
                    $results['error'] = 'Failed to update reserved_slots field';
                } else {
                    $results['error'] .= '; Failed to update reserved_slots field';
                }
            }
        }
        
        // Update availability if changed
        if ($old_availability !== $new_availability) {
            $availability_update_result = update_field('available_slots', $new_availability, $tour_date_id);
            
            if ($availability_update_result !== false) {
                $results['availability_updated'] = true;
                $updates_made = true;
                $log_parts[] = "availability: {$old_availability}→{$new_availability}";
            } else {
                if (empty($results['error'])) {
                    $results['error'] = 'Failed to update availability field';
                } else {
                    $results['error'] .= '; Failed to update availability field';
                }
            }
        }
        
        if (empty($results['error'])) {
            $results['success'] = true;
            
            if ($updates_made) {
                // Get full tour-date title for logging
                $tour_date_title = get_the_title($tour_date_id);
                if (empty($tour_date_title)) {
                    // Fallback to tour name if tour-date title not available
                    $tour_field = get_field('tour', $tour_date_id);
                    $tour_date_title = 'Unknown Tour';
                    if ($tour_field) {
                        if (is_object($tour_field) && isset($tour_field->post_title)) {
                            $tour_date_title = $tour_field->post_title;
                        } elseif (is_array($tour_field) && isset($tour_field['post_title'])) {
                            $tour_date_title = $tour_field['post_title'];
                        } elseif (is_numeric($tour_field)) {
                            $tour_date_title = get_the_title($tour_field);
                        }
                    }
                }
                
                // Log the changes (availability is now already calculated)
                $log_message = sprintf('Auto-sync: %s (ID:%d) %s', 
                    $tour_date_title, $tour_date_id, implode(', ', $log_parts));
                error_log('BST Auto-Sync: ' . $log_message);
            }
        }
        
    } catch (Exception $e) {
        $results['error'] = 'Exception: ' . $e->getMessage();
        error_log('BST Auto-Sync Error: ' . $e->getMessage() . ' for tour date ID: ' . $tour_date_id);
    }
    
    return $results;
}

/**
 * Auto-sync sold slots when bookings are modified
 * This function should be called whenever a booking is created, updated, or deleted
 * 
 * @param int $tour_date_id The tour date ID to sync
 * @param string $context Optional context for logging (e.g., 'import', 'gravity_form', 'manual_edit')
 */
function bst_auto_sync_sold_slots($tour_date_id, $context = 'unknown') {
    if (empty($tour_date_id)) {
        return;
    }
    
    $results = bst_sync_tour_date_sold_slots($tour_date_id, $context);

    if ( function_exists( 'bst_sync_limited_vehicle_sold_for_tour_date' ) ) {
        $lv = bst_sync_limited_vehicle_sold_for_tour_date( (int) $tour_date_id );
        if ( is_wp_error( $lv ) ) {
            error_log(
                sprintf(
                    'BST Auto-Sync limited vehicles failed for tour date %d: %s',
                    (int) $tour_date_id,
                    $lv->get_error_message()
                )
            );
        } elseif ( is_array( $lv ) && ! empty( $lv['rows_updated'] ) ) {
            error_log(
                sprintf(
                    'BST Auto-Sync limited vehicles: tour date %d, rows updated %d',
                    (int) $tour_date_id,
                    (int) $lv['rows_updated']
                )
            );
        }
    }
    
    // Log context if any updates occurred
    if (isset($results['sold_updated']) && $results['sold_updated'] || 
        isset($results['reserved_updated']) && $results['reserved_updated'] || 
        isset($results['availability_updated']) && $results['availability_updated']) {
        error_log(sprintf('BST Auto-Sync triggered by %s: Tour Date ID %d updated', $context, $tour_date_id));
    }
    
    return $results;
}

/**
 * Sync sold slots for all tour dates based on current bookings
 * This function loops through all tour dates and updates their sold_slots
 * meta field based on the sum of package_vehicles from all non-cancelled bookings
 * 
 * @return array Results with success status, updated count, and any error messages
 */
function bst_sync_sold_slots() {
    global $wpdb;
    
    $results = array(
        'success' => false,
        'updated_count' => 0,
        'errors' => array(),
        'log_entries' => array()
    );
    
    try {
        // Get all tour-date posts regardless of status (publish, draft, pending, etc.)
        $tour_dates = get_posts(array(
            'post_type' => 'tour-date',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'meta_query' => array() // No restrictions, get all tour dates
        ));
        
        if (empty($tour_dates)) {
            $results['errors'][] = 'No tour dates found';
            return $results;
        }
        
        $booking_table = $wpdb->prefix . 'bst_tour_booking';
        
        // Build array with tour info for sorting
        $tour_dates_with_info = array();
        foreach ($tour_dates as $tour_date_post) {
            $tour_date_id = $tour_date_post->ID;
            
            // Get tour name from the 'tour' field relationship
            $tour_field = get_field('tour', $tour_date_id);
            $tour_name = 'Unknown Tour';
            
            if ($tour_field) {
                if (is_object($tour_field) && isset($tour_field->post_title)) {
                    $tour_name = html_entity_decode($tour_field->post_title, ENT_QUOTES, 'UTF-8');
                } elseif (is_array($tour_field) && isset($tour_field['post_title'])) {
                    $tour_name = html_entity_decode($tour_field['post_title'], ENT_QUOTES, 'UTF-8');
                } elseif (is_numeric($tour_field)) {
                    $tour_name = html_entity_decode(get_the_title($tour_field), ENT_QUOTES, 'UTF-8');
                }
            }
            
            // Get start date for sorting
            $start_date = get_field('start_date', $tour_date_id);
            $sort_date = $start_date ? strtotime($start_date) : 0;
            
            $tour_dates_with_info[] = array(
                'post' => $tour_date_post,
                'tour_name' => $tour_name,
                'sort_date' => $sort_date
            );
        }
        
        // Sort by tour name, then by start date
        usort($tour_dates_with_info, function($a, $b) {
            $name_compare = strcmp($a['tour_name'], $b['tour_name']);
            if ($name_compare !== 0) {
                return $name_compare;
            }
            return $a['sort_date'] - $b['sort_date'];
        });
        
        foreach ($tour_dates_with_info as $tour_info) {
            $tour_date_post = $tour_info['post'];
            $tour_date_id = $tour_date_post->ID;
            $tour_name = $tour_info['tour_name'];
            
            // Use standardized tour date title - extract date range from parentheses
            $tour_date_text = $tour_date_post->post_title; // fallback
            if (preg_match('/\((.*)\)$/', $tour_date_post->post_title, $matches)) {
                $tour_date_text = $matches[1];
            }
            
            // Use the single tour date sync function for consistency
            $sync_result = bst_sync_tour_date_sold_slots($tour_date_id, 'bulk_sync');
            
            if ($sync_result['success']) {
                // Check if any updates were made
                $updated = $sync_result['sold_updated'] || $sync_result['reserved_updated'] || $sync_result['availability_updated'];
                
                if ($updated) {
                    // Build update details
                    $update_parts = array();
                    if ($sync_result['sold_updated']) {
                        $update_parts[] = "sold: {$sync_result['old_sold_value']}→{$sync_result['new_sold_value']}";
                    }
                    if ($sync_result['reserved_updated']) {
                        $update_parts[] = "reserved: {$sync_result['old_reserved_value']}→{$sync_result['new_reserved_value']}";
                    }
                    if ($sync_result['availability_updated']) {
                        $update_parts[] = "availability: {$sync_result['old_availability_value']}→{$sync_result['new_availability_value']}";
                    }
                    
                    // Log the change with formatted tour date text
                    $log_message = sprintf('UPDATED: %s (%s) - %s', $tour_name, $tour_date_text, implode(', ', $update_parts));
                    error_log('BST Sync: ' . $log_message);
                    $results['log_entries'][] = $log_message;
                    $results['updated_count']++;
                } else {
                    // No need to log when no change was needed - too verbose
                    // Only count it in the processed count
                }
            } else {
                // Log error
                $error_message = sprintf('Failed to sync ID %d (%s): %s', $tour_date_id, $tour_name, $sync_result['error']);
                $results['errors'][] = $error_message;
                error_log('BST Sync Error: ' . $error_message);
            }
        }
        
        $results['success'] = true; // Success if we processed all dates, even if some values didn't change
        $results['processed_count'] = count($tour_dates); // Add actual processed count
        
        // Send admin notification if any updates were made during MANUAL bulk sync
        // (Don't send for automated daily sync - that has its own notification)
        if ($results['updated_count'] > 0 && !doing_action('bst_daily_availability_sync')) {
            bst_send_sync_update_notification($results);
        }
        
        // Log summary - always show when sync runs, but emphasize when there are changes
        $summary = sprintf('Sync complete: %d processed, %d updated, %d errors', $results['processed_count'], $results['updated_count'], count($results['errors']));
        if ($results['updated_count'] > 0 || count($results['errors']) > 0) {
            error_log('BST Sync: *** ' . $summary . ' ***');
        } else {
            error_log('BST Sync: ' . $summary);
        }
        
    } catch (Exception $e) {
        $results['errors'][] = 'Exception: ' . $e->getMessage();
        error_log('BST Sync Exception: ' . $e->getMessage());
    }
    
    return $results;
}

/**
 * Handle the admin post action for syncing sold slots
 */
function bst_handle_sync_sold_slots() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['sync_nonce'], 'bst_sync_sold_slots')) {
        wp_die('Security check failed');
    }
    
    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_die('Insufficient permissions');
    }
    
    // Perform the sync
    $results = bst_sync_sold_slots();
    
    // Redirect with results - redirect back to tools page where the action was initiated
    $redirect_url = admin_url('admin.php?page=bst_tools_page');
    
    if ($results['success']) {
        $redirect_url = add_query_arg(array(
            'sync_sold_slots' => 'success',
            'updated_count' => $results['updated_count']
        ), $redirect_url);
    } else {
        $redirect_url = add_query_arg(array(
            'sync_sold_slots' => 'error',
            'error_count' => count($results['errors'])
        ), $redirect_url);
    }
    
    wp_redirect($redirect_url);
    exit;
}
add_action('admin_post_bst_sync_sold_slots', 'bst_handle_sync_sold_slots');

/**
 * Hook into booking creation/update/deletion to auto-sync sold slots
 * These hooks can be called from various entry points
 */

// Hook for when bookings are saved via AJAX or form submissions
add_action('bst_booking_saved', 'bst_handle_booking_saved', 10, 2);
function bst_handle_booking_saved($tour_date_id_or_booking_id, $context = 'manual') {
    // Handle both direct tour_date_id and booking_id cases
    if (is_numeric($tour_date_id_or_booking_id)) {
        // Check if this is a tour_date_id (ACF post with 'tour-date' type) or booking_id
        $post = get_post($tour_date_id_or_booking_id);
        if ($post && $post->post_type === 'tour-date') {
            // It's a tour_date_id, use directly
            $tour_date_id = $tour_date_id_or_booking_id;
        } else {
            // It's likely a booking_id, fetch the tour_date_id
            global $wpdb;
            $booking_table = $wpdb->prefix . 'bst_tour_booking';
            
            $tour_date_id = $wpdb->get_var($wpdb->prepare(
                "SELECT tour_date_id FROM $booking_table WHERE id = %d",
                $tour_date_id_or_booking_id
            ));
        }
        
        if ($tour_date_id) {
            bst_auto_sync_sold_slots($tour_date_id, $context);
        }
    }
}

// Hook for when bookings are deleted
add_action('bst_booking_deleted', 'bst_handle_booking_deleted', 10, 2);
function bst_handle_booking_deleted($tour_date_id, $context = 'manual') {
    if ($tour_date_id) {
        bst_auto_sync_sold_slots($tour_date_id, $context);
    }
}

// Hook for bulk operations (imports, etc.)
add_action('bst_bookings_bulk_updated', 'bst_handle_bulk_booking_update', 10, 2);
function bst_handle_bulk_booking_update($tour_date_ids, $context = 'bulk') {
    if (!is_array($tour_date_ids)) {
        $tour_date_ids = array($tour_date_ids);
    }
    
    foreach ($tour_date_ids as $tour_date_id) {
        if ($tour_date_id) {
            bst_auto_sync_sold_slots($tour_date_id, $context);
        }
    }
}

/**
 * Get tour date display information with badges and status text
 * Centralized logic for consistent availability display across all pages
 * 
 * @param string $start_date Tour date start date
 * @param string $end_date Tour date end date  
 * @param int $availability Available slots (stored value)
 * @return array Array with date_text, badge_class, badge_text, and status_badge
 */
function bst_get_tour_date_display_info($start_date, $end_date, $availability, $tour_extension_offered = false, $date_extension_offered = false, $extension_days = 0) {
    $current_date = date('Y-m-d');
    $start_date_formatted = date('Y-m-d', strtotime($start_date));
    $end_date_formatted = date('Y-m-d', strtotime($end_date));
    
    $available_slots = intval($availability);
    
    // Get low availability threshold from settings (default to 2)
    $low_threshold = intval(get_option('bst_low_availability_threshold', 2));
    
    // Generate base date text (no status info)
    if (date('M', strtotime($start_date)) == date('M', strtotime($end_date))) {
        $date_text = date('j', strtotime($start_date)) . '-' . date('j M Y', strtotime($end_date));
    } else {
        $date_text = date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
    }
    
    // Add extension date range if extension is offered
    if ($tour_extension_offered && ($date_extension_offered == '1' || $date_extension_offered === true) && $extension_days > 0) {
        // Calculate extension end date
        $extension_end_timestamp = strtotime($end_date_formatted . ' +' . $extension_days . ' days');
        
        // Format the extension end date without year
        $extension_end_date_no_year = date('j M', $extension_end_timestamp);
        
        // Get the year from the original end date
        $year = date('Y', strtotime($end_date));
        
        // Build the full date range with extension: "30 Aug - 7 Sep 2026 (30 Aug - 11 Sep with extension)"
        $start_day_month = date('j M', strtotime($start_date));
        $date_text .= ' (' . $start_day_month . ' - ' . $extension_end_date_no_year . ' with extension)';
    }
    
    // Initialize return values
    $badge_class = '';
    $badge_text = '';
    $status_badge_class = '';
    $status_badge_text = '';
    
    if ($current_date >= $start_date_formatted && $current_date <= $end_date_formatted) {
        // Tour is in progress
        $badge_class = 'yellow';
        $badge_text = 'IN PROGRESS';
    } elseif ($current_date > $end_date_formatted) {
        // Tour is completed - use gray badge
        $status_badge_class = 'gray';
        $status_badge_text = 'DONE';
    } elseif ($available_slots <= 0) {
        // Sold out - keep red badge
        $badge_class = 'red';
        $badge_text = 'SOLD OUT';
    } elseif ($available_slots <= $low_threshold) {
        // Low availability - show orange badge
        $badge_class = 'orange';
        $badge_text = $available_slots . ' LEFT';
    } else {
        // Normal availability - use green badge (white background)
        $badge_class = 'green';
        $badge_text = $available_slots . ' LEFT';
    }
    
    return array(
        'date_text' => $date_text,
        'badge_class' => $badge_class,
        'badge_text' => $badge_text,
        'status_badge_class' => $status_badge_class,
        'status_badge_text' => $status_badge_text
    );
}

/**
 * Send admin notification when overbooking is detected during booking operations
 */
function bst_send_overbooking_notification($tour_date_id, $max_slots, $sold_slots, $offline_sold, $reserved_slots, $context = '', $booking_info = null) {
    error_log("BST Notification Function - Called with context: '$context'");
    
    // Only send notifications for booking-related overbooking, not periodic checks
    $booking_contexts = array('manual', 'ajax_create', 'ajax_update', 'gf9_submission', 'gf10_finalization', 'waiting_list', 'tile_update', 'admin_test', 'bulk_sync');
    $is_booking_related = false;
    foreach ($booking_contexts as $booking_context) {
        if (strpos($context, $booking_context) !== false) {
            $is_booking_related = true;
            break;
        }
    }
    
    error_log("BST Notification Function - Context '$context' is booking related: " . ($is_booking_related ? 'YES' : 'NO'));
    
    if (!$is_booking_related) {
        error_log("BST Notification Function - Skipping notification for context: '$context'");
        return; // Don't send notifications for scheduled sync operations
    }
    
    error_log("BST Notification Function - Proceeding to send notification for context: '$context'");
    
    // Check if we already sent a notification for this overbooking situation recently
    $notification_key = 'bst_overbooking_notified_' . $tour_date_id;
    $last_notification = get_transient($notification_key);
    $current_overbooked_by = ($sold_slots + $offline_sold + $reserved_slots) - $max_slots;
    
    // Only send if we haven't notified about this level of overbooking in the last hour
    if ($last_notification && $last_notification >= $current_overbooked_by) {
        return;
    }
    
    // Set transient to prevent duplicate notifications for 1 hour
    set_transient($notification_key, $current_overbooked_by, HOUR_IN_SECONDS);
    
    // Get tour date information
    $tour_date_post = get_post($tour_date_id);
    $tour_id = get_field('tour', $tour_date_id);
    
    if (is_object($tour_id)) {
        $tour_id = $tour_id->ID;
    } elseif (is_array($tour_id)) {
        $tour_id = $tour_id['ID'];
    }
    
    $tour_date_title = html_entity_decode(get_the_title($tour_date_id), ENT_QUOTES, 'UTF-8');
    $start_date = get_field('start_date', $tour_date_id);
    
    // Calculate totals
    $total_used = $sold_slots + $offline_sold + $reserved_slots;
    $overbooked_by = $total_used - $max_slots;
    
    // Get admin email
    $admin_email = get_option('admin_email');
    
    // Format the email
    $subject = 'OVERBOOKING DETECTED - Immediate Attention Required';
    
    $message = "ALERT: Overbooking has been detected!\n\n";
    $message .= "{$tour_date_title}\n\n";
    
    // Add booking context information
    if ($booking_info) {
        $message .= "TRIGGERED BY BOOKING:\n";
        if (isset($booking_info['guest1_first_name']) && isset($booking_info['guest1_last_name'])) {
            $message .= "Guest: {$booking_info['guest1_first_name']} {$booking_info['guest1_last_name']}\n";
        }
        if (isset($booking_info['booking_status'])) {
            $message .= "Status: {$booking_info['booking_status']}\n";
        }
        if (isset($booking_info['package_vehicles'])) {
            $message .= "Vehicles: {$booking_info['package_vehicles']}\n";
        }
        $message .= "Context: " . ucfirst(str_replace('_', ' ', $context)) . "\n\n";
    }
    
    $message .= "CAPACITY BREAKDOWN:\n";
    $message .= "Max Slots: {$max_slots}\n";
    $message .= "Sold Slots: {$sold_slots}\n";
    $message .= "Offline Sold: {$offline_sold}\n";
    $message .= "Reserved Slots: {$reserved_slots}\n";
    $message .= "Total Used: {$total_used}\n";
    $message .= "OVERBOOKED BY: {$overbooked_by} slot(s)\n\n";
    $message .= "Time Detected: " . current_time('Y-m-d H:i:s') . "\n\n";
    $message .= "This requires immediate attention to resolve the overbooking situation.\n\n";
    $message .= "Admin Panel: " . admin_url('edit.php?post_type=tour-date');
    
    // Send the email
    wp_mail($admin_email, $subject, $message);
}

/**
 * Get recent booking information to include in overbooking notifications
 */
function bst_get_recent_booking_for_overbooking($tour_date_id, $context = '') {
    global $wpdb;
    $booking_table = $wpdb->prefix . 'bst_tour_booking';
    
    // Get the most recently created booking for this tour date
    $booking = $wpdb->get_row($wpdb->prepare("
        SELECT guest1_first_name, guest1_last_name, booking_status, package_vehicles, 
               created_date
        FROM $booking_table 
        WHERE tour_date_id = %d 
        AND booking_status IN ('Pending', 'Booked', 'Finalized', 'Completed', 'Reserved')
        ORDER BY COALESCE(created_date, '1970-01-01 00:00:00') DESC 
        LIMIT 1
    ", $tour_date_id), ARRAY_A);
    
    return $booking;
}

/**
 * Send admin notification when bulk sync makes changes to availability data
 */
function bst_send_sync_update_notification($results) {
    $admin_email = get_option('admin_email');
    
    $subject = 'Availability Sync Made Changes - Data Discrepancies Detected';
    
    $message = "The availability sync process detected and corrected data discrepancies.\n\n";
    $message .= "SYNC SUMMARY:\n";
    $processed_count = isset($results['processed_count']) ? $results['processed_count'] : count($results['log_entries']);
    $message .= "Total tour dates processed: " . $processed_count . "\n";
    $message .= "Tour dates with changes: " . $results['updated_count'] . "\n";
    $message .= "Tour dates unchanged: " . ($processed_count - $results['updated_count']) . "\n\n";
    
    if (!empty($results['errors'])) {
        $message .= "Errors encountered: " . count($results['errors']) . "\n\n";
    }
    
    $message .= "CHANGES MADE:\n";
    foreach ($results['log_entries'] as $log_entry) {
        if (strpos($log_entry, 'UPDATED:') === 0) {
            $message .= "• " . substr($log_entry, 9) . "\n"; // Remove "UPDATED: " prefix
        }
    }
    
    $message .= "\nTime: " . current_time('Y-m-d H:i:s') . "\n\n";
    $message .= "These changes indicate that availability data was out of sync with actual bookings.\n";
    $message .= "This could happen if:\n";
    $message .= "- Bookings were modified outside the normal process\n";
    $message .= "- Database inconsistencies occurred\n";
    $message .= "- Manual adjustments were made to availability fields\n\n";
    $message .= "Admin Panel: " . admin_url('admin.php?page=bst-settings');
    
    wp_mail($admin_email, $subject, $message);
}

/**
 * Customize Stripe charge description for better transaction list readability
 * 
 * @param string $description Default description generated by Gravity Forms
 * @param array $strings_array Array of strings that make up the description
 * @param array $entry Gravity Forms entry object
 * @param array $form Gravity Forms form object
 * @return string Customized description
 */
add_filter('gform_stripe_charge_description', 'bst_customize_stripe_description', 10, 5);

function bst_customize_stripe_description($description, $strings_array, $entry, $submission_data, $feed) {
    $form_id = null;
    if (!empty($feed) && isset($feed['form_id'])) {
        $form_id = $feed['form_id'];
    } elseif (!empty($entry) && isset($entry['form_id'])) {
        $form_id = $entry['form_id'];
    }
    
    if (empty($form_id)) {
        return $description;
    }
    
    // Get customer name, tour and date info - Form 9 and Form 10 use different field structures
    $first_name = '';
    $last_name = '';
    $tour = '';
    $tour_date = '';
    
    if ($form_id == 9) {
        // Booking form — tour labels from canonical IDs via gravity-forms helpers (not GF text columns).
        $first_name = rgar($entry, '31.3');
        $last_name = rgar($entry, '31.6');
        $tour = '';
        $tour_date = '';
        if ( function_exists( 'bst_gf9_entry_live_tour_parts' ) ) {
            $parts      = bst_gf9_entry_live_tour_parts( $entry );
            $tour       = $parts['tour_text'] ?? '';
            $tour_date = $parts['tour_date_text'] ?? '';
        }
    } elseif ($form_id == 10) {
        $first_name = rgar($entry, '31.3');
        $last_name = rgar($entry, '31.6');
        $tour = '';
        $tour_date = '';
        global $wpdb;
        $bid = (int) rgar($entry, '261');
        if ( $bid > 0 && $wpdb ) {
            $b = $wpdb->get_row( $wpdb->prepare(
                "SELECT tour_id, tour_date_id FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
                $bid
            ) );
            if ( $b ) {
                if ( function_exists( 'bst_live_tour_title' ) ) {
                    $tour = bst_live_tour_title( (int) ( $b->tour_id ?? 0 ) );
                }
                if ( function_exists( 'bst_live_tour_date_text' ) ) {
                    $td_raw = explode( '|', (string) ( $b->tour_date_id ?? '' ) );
                    $tid    = isset( $td_raw[0] ) ? (int) trim( $td_raw[0] ) : 0;
                    $tour_date = $tid > 0 ? bst_live_tour_date_text( $tid ) : '';
                }
            }
        }
    } else {
        // Other forms - return default description
        return $description;
    }
    
    // Build description: "Customer Name: Tour, dates - Payment Type"
    $new_description = '';
    
    // Name: Tour
    $customer_name = trim($first_name . ' ' . $last_name);
    if (!empty($customer_name) && !empty($tour)) {
        $new_description = $customer_name . ': ' . $tour;
    } elseif (!empty($tour)) {
        $new_description = $tour;
    } elseif (!empty($customer_name)) {
        $new_description = $customer_name;
    }
    
    // Add tour date with comma
    if (!empty($tour_date)) {
        if (!empty($new_description)) {
            $new_description .= ', ' . $tour_date;
        } else {
            $new_description = $tour_date;
        }
    }
    
    // Add payment type with dash
    $payment_label = ($form_id == 9) ? 'Deposit' : 'Finalization';
    if (!empty($new_description)) {
        $new_description .= ' - ' . $payment_label;
    } else {
        $new_description = $payment_label;
    }
    
    // Stripe has a 1000 char limit for description (statement descriptor is only 22)
    return substr($new_description, 0, 1000);
}