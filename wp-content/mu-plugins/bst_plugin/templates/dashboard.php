<?php
/**
 * BST Plugin Dashboard Template
 * 
 * This is the main dashboard for the BST Plugin showing key booking statuses and management tools.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$current_user = wp_get_current_user();

// Get booking data for dashboard tiles
$booking_table = $wpdb->prefix . 'bst_tour_booking';

function bst_dashboard_live_tour_title( $tour_id ) {
    $tour_id = (int) $tour_id;
    if ( $tour_id <= 0 ) {
        return '';
    }
    $p = get_post( $tour_id );
    return ( $p && 'tour' === $p->post_type ) ? (string) $p->post_title : '';
}

function bst_dashboard_live_tour_date_text( $tour_date_id ) {
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

function bst_dashboard_live_package_name( $package_id ) {
    $package_id = (int) $package_id;
    if ( $package_id <= 0 ) {
        return '';
    }
    return (string) get_option( 'bst_package_' . $package_id . '_name', '' );
}

function bst_dashboard_live_tour_display( $booking ) {
    $tour_title = bst_dashboard_live_tour_title( $booking->tour_id ?? 0 );
    $tour_date  = bst_dashboard_live_tour_date_text( $booking->tour_date_id ?? 0 );
    $package    = bst_dashboard_live_package_name( $booking->tour_package_id ?? 0 );
    $out = $tour_title;
    if ( '' !== $tour_date ) {
        $out .= ' (' . $tour_date . ')';
    }
    if ( '' !== $package ) {
        $out .= ' - ' . $package;
    }
    return $out;
}

// Get waiting list bookings with availability check
$waiting_list_bookings = $wpdb->get_results("
    SELECT b.id, b.guest1_first_name, b.guest1_last_name, b.guest2_first_name, b.guest2_last_name, b.created_date,
           b.tour_id, b.tour_date_id, b.package_vehicles, b.tour_package_id
    FROM $booking_table b
    WHERE b.booking_status = 'Waiting List' 
    ORDER BY b.id ASC 
    LIMIT 10
");

// Get overbooked tour dates
function get_overbooked_tour_dates_for_dashboard() {
    $args = array(
        'post_type' => 'tour-date',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'max_slots',
                'value' => 0,
                'compare' => '>'
            )
        )
    );
    
    $tour_dates = get_posts($args);
    $overbooked_dates = array();
    
    foreach ($tour_dates as $tour_date) {
        $max_slots = intval(get_post_meta($tour_date->ID, 'max_slots', true));
        $sold_slots = intval(get_post_meta($tour_date->ID, 'sold_slots', true));
        $offline_sold = intval(get_post_meta($tour_date->ID, 'offline_sold_slots', true));
        $reserved_slots = intval(get_post_meta($tour_date->ID, 'reserved_slots', true));
        
        $total_used = $sold_slots + $offline_sold + $reserved_slots;
        
        if ($total_used > $max_slots) {
            $overbooked_dates[] = array(
                'id' => $tour_date->ID,
                'title' => html_entity_decode(get_the_title($tour_date->ID), ENT_QUOTES, 'UTF-8'),
                'max_slots' => $max_slots,
                'total_used' => $total_used,
                'sold_slots' => $sold_slots,
                'offline_sold' => $offline_sold,
                'reserved_slots' => $reserved_slots,
                'overbooked_by' => $total_used - $max_slots
            );
        }
    }
    
    return $overbooked_dates;
}

$overbooked_tour_dates = get_overbooked_tour_dates_for_dashboard();

$lv_oversold_rows = function_exists( 'bst_limited_vehicle_dashboard_oversold_rows' ) ? bst_limited_vehicle_dashboard_oversold_rows() : array();

// Get reserved bookings
$reserved_bookings = $wpdb->get_results("
    SELECT id, guest1_first_name, guest1_last_name, guest2_first_name, guest2_last_name, created_date, tour_id, tour_date_id, tour_package_id
    FROM $booking_table 
    WHERE booking_status = 'Reserved' 
    ORDER BY created_date DESC 
    LIMIT 10
");

// Get pending bookings
$pending_bookings = $wpdb->get_results("
    SELECT id, guest1_first_name, guest1_last_name, guest2_first_name, guest2_last_name, created_date, tour_id, tour_date_id
    FROM $booking_table 
    WHERE booking_status = 'Pending' 
    ORDER BY created_date DESC 
    LIMIT 10
");

// Get processing bookings (SEPA, etc. awaiting payment confirmation)
$processing_bookings = $wpdb->get_results("
    SELECT id, guest1_first_name, guest1_last_name, guest2_first_name, guest2_last_name, created_date, deposit_payment_method, booking_entry_id, tour_id, tour_date_id, tour_package_id
    FROM $booking_table 
    WHERE booking_status = 'Processing' 
    ORDER BY created_date DESC 
    LIMIT 10
");

// Get payment failed bookings
$payment_failed_bookings = $wpdb->get_results("
    SELECT id, guest1_first_name, guest1_last_name, guest2_first_name, guest2_last_name, created_date, deposit_payment_method, booking_entry_id, tour_id, tour_date_id, tour_package_id
    FROM $booking_table 
    WHERE booking_status = 'Payment Failed' 
    ORDER BY created_date DESC 
    LIMIT 10
");

// Get bank wire pending bookings:
// - booking_status = 'Pending' (always means a bank wire is awaited), OR
// - any bank wire payment method set but amount not yet received
$bank_wire_pending = $wpdb->get_results("
    SELECT b.id, b.guest1_first_name, b.guest1_last_name, b.guest2_first_name, b.guest2_last_name, b.created_date,
           b.deposit_payment_method, b.deposit_payment_amount, b.deposit_payment_status,
           b.balance_payment_method, b.balance_payment_amount, b.balance_payment_status,
           b.additional_payment_method, b.additional_payment_amount, b.additional_payment_status,
           b.booking_status, b.finalization_entry_id, b.tour_price, b.net_tour_price, b.balance_due, b.tour_currency,
           b.tour_id, b.tour_date_id, b.tour_package_id, b.package_people, b.booking_entry_id,
           gf9.date_created as booking_date, gf10.date_created as finalization_date
    FROM $booking_table b
    LEFT JOIN {$wpdb->prefix}gf_entry gf9 ON b.booking_entry_id = gf9.id
    LEFT JOIN {$wpdb->prefix}gf_entry gf10 ON b.finalization_entry_id = gf10.id
    WHERE (
        b.booking_status = 'Pending' OR
        (b.deposit_payment_method = 'Bank Wire' AND (b.deposit_payment_amount IS NULL OR b.deposit_payment_amount = 0)) OR
        (b.balance_payment_method = 'Bank Wire' AND (b.balance_payment_amount IS NULL OR b.balance_payment_amount = 0)) OR
        (b.additional_payment_method = 'Bank Wire' AND (b.additional_payment_amount IS NULL OR b.additional_payment_amount = 0)) OR
        (b.deposit_payment_method = 'Bank Wire' AND b.deposit_payment_status IN ('Pending', 'Processing')) OR
        (b.balance_payment_method = 'Bank Wire' AND b.balance_payment_status IN ('Pending', 'Processing')) OR
        (b.additional_payment_method = 'Bank Wire' AND b.additional_payment_status IN ('Pending', 'Processing'))
    )
    ORDER BY b.created_date ASC 
    LIMIT 10
");

// Get reservations not booked
$reservations_not_booked = $wpdb->get_results("
    SELECT id, guest1_first_name, guest1_last_name, guest2_first_name, guest2_last_name, created_date, tour_id, tour_date_id, tour_package_id
    FROM $booking_table 
    WHERE booking_status = 'Reserved'
    ORDER BY created_date ASC 
    LIMIT 10
");

// Get most recent web bookings (last 5, pending and booked only)
$recent_bookings = $wpdb->get_results("
    SELECT b.id, b.guest1_first_name, b.guest1_last_name, b.guest2_first_name, b.guest2_last_name,
           b.created_date, b.booking_status, b.tour_id, b.tour_date_id, b.tour_package_id
    FROM $booking_table b
    WHERE b.booking_status IN ('Pending', 'Booked') 
      AND b.booking_method = 'Web'
    ORDER BY b.created_date DESC 
    LIMIT 5
");

// Get bookings awaiting transfer (transfer status)
$transfer_bookings = $wpdb->get_results("
    SELECT b.id, b.guest1_first_name, b.guest1_last_name, b.guest2_first_name, b.guest2_last_name,
           b.created_date, b.booking_status, b.tour_id, b.tour_date_id, b.tour_package_id
    FROM $booking_table b
    WHERE b.booking_status = 'transfer'
    ORDER BY b.created_date ASC
");

// Get refunds due (negative balance OR pending refund payment)
$refunds_due = $wpdb->get_results("
    SELECT b.id, b.guest1_first_name, b.guest1_last_name, b.guest2_first_name, b.guest2_last_name,
           b.created_date, b.booking_status, b.balance_due, b.tour_currency, b.tour_id, b.tour_date_id, b.tour_package_id,
           b.refund_payment_status, b.refund_payment_amount
    FROM $booking_table b
    WHERE b.balance_due < 0
       OR (b.refund_payment_status = 'Pending' AND COALESCE(b.refund_payment_amount, 0) > 0)
    ORDER BY b.balance_due ASC
");

// Get tours needing finalization (web bookings with "Booked" status that don't have finalization entry)
$finalization_sent_days = get_option('bst_finalization_sent_days', 120);
$finalization_sent_date = date('Y-m-d', strtotime('+' . $finalization_sent_days . ' days'));
$finalization_needed = $wpdb->get_results($wpdb->prepare("
    SELECT b.id, b.guest1_first_name, b.guest1_last_name, b.guest2_first_name, b.guest2_last_name, 
           b.guest1_email, b.guest2_email,
           CONCAT(b.guest1_first_name, ' ', b.guest1_last_name) as guest1_name,
           CONCAT(b.guest2_first_name, ' ', b.guest2_last_name) as guest2_name,
           td_meta.meta_value as start_date, b.finalization_email_sent, b.created_date, b.tour_date_id,
           b.tour_id, b.tour_package_id,
           b.balance_due, b.tour_currency, b.finalization_entry_id, b.booking_entry_id,
           el.last_finalization_sent
    FROM $booking_table b
    LEFT JOIN {$wpdb->prefix}posts td ON b.tour_date_id = td.ID AND td.post_type = 'tour-date'
    LEFT JOIN {$wpdb->prefix}postmeta td_meta ON td.ID = td_meta.post_id AND td_meta.meta_key = 'start_date'
    LEFT JOIN (
        SELECT booking_id, MAX(sent_date) as last_finalization_sent
        FROM {$wpdb->prefix}bst_email_log
        WHERE email_type = 'finalization' AND sent_successfully = 1
        GROUP BY booking_id
    ) el ON b.id = el.booking_id
    WHERE b.booking_method = 'Web'
    AND b.booking_status = 'Booked'
    AND td_meta.meta_value IS NOT NULL
    AND (
        (LENGTH(td_meta.meta_value) = 8 AND STR_TO_DATE(td_meta.meta_value, '%%Y%%m%%d') <= %s AND STR_TO_DATE(td_meta.meta_value, '%%Y%%m%%d') >= CURDATE()) OR
        (LENGTH(td_meta.meta_value) = 10 AND td_meta.meta_value <= %s AND td_meta.meta_value >= CURDATE())
    )
    AND (b.finalization_entry_id IS NULL OR b.finalization_entry_id = 0 OR b.finalization_entry_id = '')
    ORDER BY 
        CASE 
            WHEN LENGTH(td_meta.meta_value) = 8 THEN STR_TO_DATE(td_meta.meta_value, '%%Y%%m%%d')
            ELSE td_meta.meta_value
        END ASC
", $finalization_sent_date, $finalization_sent_date));

// For dashboard display, we only want to highlight the NEXT booking(s) in queue, not all convertible ones
$convertible_waiting_list = [];
if (!empty($waiting_list_bookings)) {
    // Group bookings by tour date to handle queue properly
    $bookings_by_tour_date = [];
    foreach ($waiting_list_bookings as $booking) {
        $bookings_by_tour_date[$booking->tour_date_id][] = $booking;
    }
    
    // Process each tour date separately to respect queue order
    foreach ($bookings_by_tour_date as $current_tour_date_id => $tour_bookings) {
        // Get availability directly from the stored field
        $available_slots = intval(get_field('available_slots', $current_tour_date_id));
        
        // If no slots available, skip this tour date
        if ($available_slots <= 0) {
            continue;
        }
        
        // Sort this tour's bookings by ID (queue order)
        usort($tour_bookings, function($a, $b) {
            return $a->id - $b->id;
        });
        
        // Only highlight the FIRST booking in queue (next in line)
        if (!empty($tour_bookings)) {
            $next_booking = $tour_bookings[0]; // First booking in queue (lowest ID)
            $required_vehicles = intval($next_booking->package_vehicles);
            
            // Only highlight if this booking can actually fit
            if ($available_slots >= $required_vehicles) {
                $convertible_waiting_list[] = $next_booking->id;
            }
        }
    }
}

?>

<div class="wrap">
    <h1>Dashboard</h1>
    
    <!-- Welcome Message -->
    <div class="welcome-message" style="margin-bottom: 30px; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa; border-radius: 4px;">
        <p style="margin: 0; font-size: 16px;">
            Welcome back, <strong><?php echo esc_html($current_user->display_name); ?></strong>! 
            Here's an overview of items that may need your attention.
        </p>
    </div>

    <style>
    /* Dashboard specific styles */
    .dashboard-tiles {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    
    @media (max-width: 768px) {
        .dashboard-tiles {
            grid-template-columns: 1fr;
        }
    }
    
    .dashboard-tile {
        background: white;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 0;
        overflow: hidden;
    }
    
    .dashboard-tile-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0;
        padding: 15px 20px;
        border-bottom: 1px solid #ddd;
        background: #f9f9f9;
    }
    
    .dashboard-tile-title {
        margin: 0;
        color: #0073aa;
        font-size: 16px;
        font-weight: 600;
    }
    
    .dashboard-tile-count {
        background: #0073aa;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        min-width: 20px;
        text-align: center;
    }
    
    /* Special styling for urgent tiles */
    .dashboard-tile.overbooked-dates .dashboard-tile-count {
        background: #dc3545; /* Bright red for overbooking alerts */
        animation: pulse 2s infinite;
    }
    
    .dashboard-tile.overbooked-dates .dashboard-tile-header {
        background: white;
        border-left: 4px solid #dc3545;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    
    .dashboard-tile.bank-wire-pending .dashboard-tile-count {
        background: #dc3232; /* Red for urgent bank wire issues */
    }
    
    .dashboard-tile.reservation-not-booked .dashboard-tile-count {
        background: #ff6900; /* Orange for reservation follow-ups */
    }
    
    .dashboard-tile.finalization-needed .dashboard-tile-count {
        background: #8f2ce6; /* Purple for finalization tasks */
    }
    
    .dashboard-tile.transfer-bookings .dashboard-tile-count {
        background: #ff9500; /* Orange for transfer bookings */
    }
    
    .dashboard-tile.refunds-due .dashboard-tile-count {
        background: #dc3232; /* Red for refunds due */
    }
    
    .dashboard-tile.bank-wire-pending .dashboard-tile-header {
        border-left: 4px solid #dc3232;
    }
    
    .dashboard-tile.reservation-not-booked .dashboard-tile-header {
        border-left: 4px solid #ff6900;
    }
    
    .dashboard-tile.finalization-needed .dashboard-tile-header {
        border-left: 4px solid #8f2ce6;
    }
    
    .dashboard-tile.transfer-bookings .dashboard-tile-header {
        border-left: 4px solid #ff9500;
    }
    
    .dashboard-tile.refunds-due .dashboard-tile-header {
        border-left: 4px solid #dc3232;
    }
    
    .dashboard-tile.limited-vehicle-oversold .dashboard-tile-count {
        background: #c05621;
    }
    .dashboard-tile.limited-vehicle-oversold .dashboard-tile-header {
        border-left: 4px solid #c05621;
    }
    .bst-lv-dash-detail-meta {
        color: #666;
        font-size: 0.9em;
        margin-top: 6px;
    }
    .bst-lv-dash-bookings a {
        margin-right: 6px;
    }
    
    /* Convertible waiting list styling */
    .dashboard-booking-item.convertible-waiting-list {
        background: #f0f8ff;
        border: 2px solid #00a32a;
        border-radius: 6px;
        margin-bottom: 8px;
        padding: 8px;
        animation: pulse-green 2s infinite;
    }
    
    @keyframes pulse-green {
        0% { box-shadow: 0 0 0 0 rgba(0, 163, 42, 0.4); }
        70% { box-shadow: 0 0 0 8px rgba(0, 163, 42, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 163, 42, 0); }
    }
    
    .dashboard-tile-content {
        padding: 20px;
    }
    
    .dashboard-booking-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    
    .dashboard-booking-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
        gap: 10px;
    }
    
    .dashboard-booking-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    
    .dashboard-booking-item:first-child {
        padding-top: 0;
    }
    
    .dashboard-booking-info {
        flex: 1;
        min-width: 0;
    }
    
    .dashboard-booking-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 2px;
    }
    
    .dashboard-booking-name a {
        text-decoration: none;
        color: #0073aa;
    }
    
    .dashboard-booking-name a:hover {
        text-decoration: underline;
    }
    
    .dashboard-booking-tour {
        font-size: 13px;
        color: #666;
        margin-bottom: 2px;
        word-break: break-word;
    }
    
    .dashboard-booking-date {
        font-size: 12px;
        color: #999;
    }
    
    .dashboard-tile-footer {
        padding: 15px 20px;
        border-top: 1px solid #f0f0f0;
        background: #fafafa;
        text-align: center;
    }
    
    .dashboard-tile-footer a {
        text-decoration: none;
        color: #0073aa;
        font-weight: 600;
        font-size: 14px;
    }
    
    .dashboard-tile-footer a:hover {
        text-decoration: underline;
    }
    
    .dashboard-no-items {
        color: #666;
        font-style: italic;
        text-align: center;
        padding: 30px 0;
    }
    </style>

    <!-- Status Tiles Grid -->
    <div class="dashboard-tiles">
        
        <?php if (!empty($overbooked_tour_dates)): ?>
        <!-- Overbooked Tour Dates Tile -->
        <div class="dashboard-tile overbooked-dates">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">🚨 Overbooked Tour Dates</h3>
                <span class="dashboard-tile-count"><?php echo count($overbooked_tour_dates); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <ul class="dashboard-booking-list">
                    <?php foreach ($overbooked_tour_dates as $date_info): ?>
                    <li class="dashboard-booking-item">
                        <div class="dashboard-booking-info">
                            <div class="dashboard-booking-name">
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $date_info['id'] . '&action=edit')); ?>" 
                                   style="color: #0073aa; text-decoration: none; font-weight: 600;">
                                    <?php echo esc_html($date_info['title']); ?>
                                </a>
                            </div>
                            <div class="dashboard-booking-tour">
                                <span style="color: #dc3545; font-weight: 500;">Overbooked by: <?php echo $date_info['overbooked_by']; ?> slot(s)</span>
                            </div>
                            <div class="dashboard-booking-date" style="color: #666; font-size: 0.9em;">
                                Used: <?php echo $date_info['total_used']; ?> / <?php echo $date_info['max_slots']; ?> slots
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="dashboard-tile-footer">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=tour-date')); ?>">
                    View All Tour Dates →
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $lv_oversold_rows ) ) : ?>
        <!-- Limited vehicle oversold -->
        <div class="dashboard-tile limited-vehicle-oversold">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">🚐 <?php esc_html_e( 'Oversold Limited Vehicles', 'bst-plugin' ); ?></h3>
                <span class="dashboard-tile-count"><?php echo (int) count( $lv_oversold_rows ); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <ul class="dashboard-booking-list">
                    <?php foreach ( $lv_oversold_rows as $lv_row ) : ?>
                    <li class="dashboard-booking-item">
                        <div class="dashboard-booking-info">
                            <div class="dashboard-booking-name">
                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $lv_row['vehicle_id'] . '&action=edit' ) ); ?>">
                                    <?php echo esc_html( $lv_row['vehicle_title'] ); ?>
                                </a>
                                <span style="color: #c05621; font-weight: 600;">
                                    <?php
                                    printf(
                                        /* translators: 1: sold count, 2: max count */
                                        esc_html__( ' — sold %1$d, max %2$d', 'bst-plugin' ),
                                        (int) $lv_row['sold'],
                                        (int) $lv_row['max']
                                    );
                                    ?>
                                </span>
                            </div>
                            <div class="bst-lv-dash-detail-meta">
                                <?php esc_html_e( 'Tour date:', 'bst-plugin' ); ?>
                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $lv_row['tour_date_id'] . '&action=edit' ) ); ?>">
                                    <?php echo esc_html( $lv_row['tour_date_title'] ); ?>
                                </a>
                                <?php
                                printf(
                                    /* translators: %d: tour-date post ID */
                                    esc_html__( ' (ID %d)', 'bst-plugin' ),
                                    (int) $lv_row['tour_date_id']
                                );
                                ?>
                            </div>
                            <div class="bst-lv-dash-detail-meta bst-lv-dash-bookings">
                                <?php if ( ! empty( $lv_row['booking_ids'] ) ) : ?>
                                    <strong><?php esc_html_e( 'Bookings with this vehicle:', 'bst-plugin' ); ?></strong>
                                    <?php
                                    $b_first = true;
                                    foreach ( $lv_row['booking_ids'] as $bid ) {
                                        $bid = (int) $bid;
                                        if ( ! $b_first ) {
                                            echo ', ';
                                        }
                                        $b_first = false;
                                        ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=view_booking&id=' . $bid ) ); ?>">#<?php echo (int) $bid; ?></a>
                                        <?php
                                    }
                                    ?>
                                <?php else : ?>
                                    <em><?php esc_html_e( 'No bookings found with vehicle1_id / vehicle2_id set to this vehicle on this tour date.', 'bst-plugin' ); ?></em>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($refunds_due)): ?>
        <!-- Refunds Due Tile -->
        <div class="dashboard-tile refunds-due">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">💰 Refunds Due</h3>
                <span class="dashboard-tile-count"><?php echo count($refunds_due); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <ul class="dashboard-booking-list">
                    <?php foreach ($refunds_due as $booking): ?>
                    <li class="dashboard-booking-item">
                        <div class="dashboard-booking-info">
                            <div class="dashboard-booking-name">
                                <?php echo esc_html(bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '')); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" 
                                   style="margin-left: 8px; font-size: 12px; color: #0073aa; text-decoration: none; font-weight: normal;">
                                    [Booking <?php echo $booking->id; ?>]
                                </a>
                            </div>
                            <div class="dashboard-booking-tour"><?php echo esc_html( bst_dashboard_live_tour_display( $booking ) ); ?></div>
                            <div class="dashboard-booking-date" style="color: #dc3232; font-weight: 600;">
                                <?php
                                // Get the correct currency symbol based on booking currency
                                $currency_symbol = '€'; // Default to Euro
                                if (!empty($booking->tour_currency)) {
                                    $currencies = array(
                                        'EUR' => '€',
                                        'USD' => '$',
                                        'CAD' => 'C$',
                                        'AUD' => 'A$',
                                        'GBP' => '£',
                                        'NZD' => 'NZ$',
                                        'JPY' => '¥',
                                        'ZAR' => 'R'
                                    );
                                    $currency_symbol = isset($currencies[strtoupper($booking->tour_currency)]) 
                                        ? $currencies[strtoupper($booking->tour_currency)] 
                                        : '€';
                                }
                                $refund_amount = abs($booking->balance_due); // Make positive for display
                                ?>
                                Refund due: <?php echo $currency_symbol . number_format($refund_amount, 2); ?> (Status: <?php echo esc_html($booking->booking_status); ?>)
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="dashboard-tile-footer">
                <span style="color: #666; font-style: italic;">Bookings with negative balance or pending refund payment</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($waiting_list_bookings)): ?>
        <!-- Waiting List Bookings Tile -->
        <div class="dashboard-tile">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">📝 Waiting List</h3>
                <span class="dashboard-tile-count"><?php echo count($waiting_list_bookings); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <ul class="dashboard-booking-list">
                    <?php foreach ($waiting_list_bookings as $booking): ?>
                    <?php $can_convert = in_array($booking->id, $convertible_waiting_list); ?>
                    <li class="dashboard-booking-item <?php echo $can_convert ? 'convertible-waiting-list' : ''; ?>">
                        <div class="dashboard-booking-info">
                            <div class="dashboard-booking-name">
                                <?php echo esc_html(bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '')); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" 
                                   style="margin-left: 8px; font-size: 12px; color: #0073aa; text-decoration: none; font-weight: normal;">
                                    [Booking <?php echo $booking->id; ?>]
                                </a>
                                <?php if ($can_convert): ?>
                                <span style="margin-left: 8px; background: #00a32a; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold;">
                                    ✓ CAN CONVERT
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="dashboard-booking-tour"><?php echo esc_html( bst_dashboard_live_tour_display( $booking ) ); ?></div>
                            <div class="dashboard-booking-date" <?php echo $can_convert ? 'style="color: #00a32a; font-weight: 600;"' : ''; ?>>
                                <?php if ($can_convert): ?>
                                    ✓ Space available! Ready to convert
                                <?php else: ?>
                                    <?php echo esc_html(date('M j, Y', strtotime($booking->created_date))); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="dashboard-tile-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=bst-tour-bookings&filter_status=Waiting+List')); ?>">
                    View All Waiting List Bookings →
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($processing_bookings)): ?>
        <!-- Processing Bookings Tile -->
        <div class="dashboard-tile processing-bookings">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">⏳ Processing Payments</h3>
                <span class="dashboard-tile-count"><?php echo count($processing_bookings); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <ul class="dashboard-booking-list">
                    <?php foreach ($processing_bookings as $booking): ?>
                    <li class="dashboard-booking-item">
                        <div class="dashboard-booking-info">
                            <div class="dashboard-booking-name">
                                <?php echo esc_html(bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '')); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" 
                                   style="margin-left: 8px; font-size: 12px; color: #0073aa; text-decoration: none; font-weight: normal;">
                                    [Booking <?php echo $booking->id; ?>]
                                </a>
                            </div>
                            <div class="dashboard-booking-tour"><?php echo esc_html( bst_dashboard_live_tour_display( $booking ) ); ?></div>
                            <div class="dashboard-booking-date" style="color: #f0ad4e; font-weight: 600;">
                                <?php 
                                $payment_method_display = !empty($booking->deposit_payment_method) ? $booking->deposit_payment_method : 'Card';
                                // Add card type for Credit Card payments
                                if ($payment_method_display === 'Credit Card' && !empty($booking->booking_entry_id)) {
                                    $entry = GFAPI::get_entry($booking->booking_entry_id);
                                    if (!is_wp_error($entry)) {
                                        $card_type = rgar($entry, '228.4'); // Field 228.4 is Stripe Card Type
                                        if (!empty($card_type)) {
                                            $payment_method_display .= ' (' . ucwords($card_type) . ')';
                                        }
                                    }
                                }
                                echo "Awaiting {$payment_method_display} confirmation"; 
                                ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="dashboard-tile-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tour_bookings&status=Processing')); ?>">
                    View all Processing bookings →
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($payment_failed_bookings)): ?>
        <!-- Payment Failed Tile -->
        <div class="dashboard-tile payment-failed-bookings">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">❌ Payment Failed</h3>
                <span class="dashboard-tile-count"><?php echo count($payment_failed_bookings); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <ul class="dashboard-booking-list">
                    <?php foreach ($payment_failed_bookings as $booking): ?>
                    <li class="dashboard-booking-item">
                        <div class="dashboard-booking-info">
                            <div class="dashboard-booking-name">
                                <?php echo esc_html(bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '')); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" 
                                   style="margin-left: 8px; font-size: 12px; color: #0073aa; text-decoration: none; font-weight: normal;">
                                    [Booking <?php echo $booking->id; ?>]
                                </a>
                            </div>
                            <div class="dashboard-booking-tour"><?php echo esc_html( bst_dashboard_live_tour_display( $booking ) ); ?></div>
                            <div class="dashboard-booking-date" style="color: #dc3232; font-weight: 600;">
                                <?php 
                                $payment_method_display = !empty($booking->deposit_payment_method) ? $booking->deposit_payment_method : 'Card';
                                // Add card type for Credit Card payments
                                if ($payment_method_display === 'Credit Card' && !empty($booking->booking_entry_id)) {
                                    $entry = GFAPI::get_entry($booking->booking_entry_id);
                                    if (!is_wp_error($entry)) {
                                        $card_type = rgar($entry, '228.4'); // Field 228.4 is Stripe Card Type
                                        if (!empty($card_type)) {
                                            $payment_method_display .= ' (' . ucwords($card_type) . ')';
                                        }
                                    }
                                }
                                echo "{$payment_method_display} payment failed - requires follow-up"; 
                                ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="dashboard-tile-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tour_bookings&status=Payment%20Failed')); ?>">
                    View all Failed payments →
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($bank_wire_pending)): ?>
        <!-- Bank Transfer Pending Tile -->
        <div class="dashboard-tile bank-wire-pending">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">🏦 Bank Transfer Pending</h3>
                <span class="dashboard-tile-count"><?php echo count($bank_wire_pending); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <ul class="dashboard-booking-list">
                    <?php foreach ($bank_wire_pending as $booking): ?>
                    <?php 
                    $missing_payments = array();

                    $bst_wire_status_pending = static function ( $status ) {
                        return in_array( trim( (string) $status ), array( 'Pending', 'Processing' ), true );
                    };

                    // Deposit bank wire
                    if ( $booking->deposit_payment_method === 'Bank Wire' ) {
                        $dep_st = isset( $booking->deposit_payment_status ) ? (string) $booking->deposit_payment_status : '';
                        $dep_amt = floatval( $booking->deposit_payment_amount ?? 0 );
                        if ( 'Paid' !== $dep_st && 'Failed' !== $dep_st ) {
                            if ( $dep_amt <= 0 || $bst_wire_status_pending( $dep_st ) ) {
                                $deposit_amount = $dep_amt > 0
                                    ? $dep_amt
                                    : bst_calculate_deposit( $booking->tour_id ?? 0, $booking->net_tour_price, $booking->package_people ?? 2 );
                                $reference_date = $booking->booking_date ?: $booking->created_date;
                                $days_since = floor( ( time() - strtotime( $reference_date ) ) / ( 24 * 60 * 60 ) );
                                $missing_payments[] = array(
                                    'type'    => 'Deposit',
                                    'amount'  => $deposit_amount,
                                    'context' => "booked {$days_since} days ago",
                                );
                            }
                        }
                    }

                    // Balance / finalization bank wire
                    if ( $booking->balance_payment_method === 'Bank Wire' ) {
                        $bal_st = isset( $booking->balance_payment_status ) ? (string) $booking->balance_payment_status : '';
                        $bal_amt = floatval( $booking->balance_payment_amount ?? 0 );
                        if ( 'Paid' !== $bal_st && 'Failed' !== $bal_st ) {
                            if ( $bal_amt <= 0 || $bst_wire_status_pending( $bal_st ) ) {
                                $pending_bal = $bal_amt > 0 ? $bal_amt : floatval( $booking->balance_due ?? 0 );
                                $reference_date = $booking->finalization_date ?: $booking->created_date;
                                $days_since = floor( ( time() - strtotime( $reference_date ) ) / ( 24 * 60 * 60 ) );
                                $missing_payments[] = array(
                                    'type'    => 'Finalization',
                                    'amount'  => $pending_bal,
                                    'context' => "finalized {$days_since} days ago",
                                );
                            }
                        }
                    }

                    // Additional bank wire
                    if ( $booking->additional_payment_method === 'Bank Wire' ) {
                        $add_st = isset( $booking->additional_payment_status ) ? (string) $booking->additional_payment_status : '';
                        $add_amt = floatval( $booking->additional_payment_amount ?? 0 );
                        if ( 'Paid' !== $add_st && 'Failed' !== $add_st ) {
                            if ( $add_amt <= 0 || $bst_wire_status_pending( $add_st ) ) {
                                $missing_payments[] = array(
                                    'type'    => 'Additional',
                                    'amount'  => $add_amt > 0 ? $add_amt : null,
                                    'context' => null,
                                );
                            }
                        }
                    }

                    if ( empty( $missing_payments ) && $booking->booking_status === 'Pending' ) {
                        $days_since = floor( ( time() - strtotime( $booking->created_date ) ) / ( 24 * 60 * 60 ) );
                        $missing_payments[] = array(
                            'type'    => 'Bank transfer',
                            'amount'  => null,
                            'context' => "submitted {$days_since} days ago",
                        );
                    }

                    // Build currency symbol
                    $currency_symbol = '€';
                    if (!empty($booking->tour_currency)) {
                        $currencies = array('EUR' => '€', 'USD' => '$', 'CAD' => 'C$', 'AUD' => 'A$', 'GBP' => '£', 'NZD' => 'NZ$', 'JPY' => '¥', 'ZAR' => 'R');
                        $currency_symbol = $currencies[strtoupper($booking->tour_currency)] ?? '€';
                    }
                    ?>
                    <li class="dashboard-booking-item">
                        <div class="dashboard-booking-info">
                            <div class="dashboard-booking-name">
                                <?php echo esc_html(bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '')); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" 
                                   style="margin-left: 8px; font-size: 12px; color: #0073aa; text-decoration: none; font-weight: normal;">
                                    [Booking <?php echo $booking->id; ?>]
                                </a>
                            </div>
                            <div class="dashboard-booking-tour"><?php echo esc_html( bst_dashboard_live_tour_display( $booking ) ); ?></div>
                            <div class="dashboard-booking-date" style="color: #dc3232; font-weight: 600;">
                                <?php if (!empty($missing_payments)): ?>
                                    <?php foreach ($missing_payments as $pmt): ?>
                                        <div>
                                            <?php
                                            $ptype = $pmt['type'] ?? '';
                                            if ( 'Bank transfer' === $ptype ) {
                                                echo 'Awaiting bank transfer';
                                            } else {
                                                echo 'Awaiting ' . esc_html( strtolower( $ptype ) ) . ' payment';
                                            }
                                            if ( isset( $pmt['amount'] ) && $pmt['amount'] !== null && floatval( $pmt['amount'] ) > 0 ) {
                                                echo ': ' . esc_html( $currency_symbol . number_format( floatval( $pmt['amount'] ), 2 ) );
                                            }
                                            if ( ! empty( $pmt['context'] ) ) {
                                                echo ' (' . esc_html( $pmt['context'] ) . ')';
                                            }
                                            ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    Bank transfer pending
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="dashboard-tile-footer">
                <span style="color: #666; font-style: italic;">Bookings and finalizations needing bank transfer info</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($reservations_not_booked)): ?>
        <!-- Reservations Not Booked Tile -->
        <div class="dashboard-tile reservation-not-booked">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">⏰ Reservations Not Booked</h3>
                <span class="dashboard-tile-count"><?php echo count($reservations_not_booked); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <ul class="dashboard-booking-list">
                    <?php foreach ($reservations_not_booked as $booking): ?>
                    <?php $days_reserved = floor((time() - strtotime($booking->created_date)) / (24 * 60 * 60)); ?>
                    <li class="dashboard-booking-item">
                        <div class="dashboard-booking-info">
                            <div class="dashboard-booking-name">
                                <?php echo esc_html(bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '')); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" 
                                   style="margin-left: 8px; font-size: 12px; color: #0073aa; text-decoration: none; font-weight: normal;">
                                    [Booking <?php echo $booking->id; ?>]
                                </a>
                            </div>
                            <div class="dashboard-booking-tour"><?php echo esc_html( bst_dashboard_live_tour_display( $booking ) ); ?></div>
                            <div class="dashboard-booking-date" style="color: #ff6900; font-weight: 600;">
                                Reserved <?php echo $days_reserved; ?> days ago
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="dashboard-tile-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=bst-tour-bookings&filter_status=Reserved')); ?>">
                    View All Reserved Bookings →
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($finalization_needed)): ?>
        <!-- Tour Finalization Needed Tile -->
        <div class="dashboard-tile finalization-needed">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">📋 Finalization Needed</h3>
                <span class="dashboard-tile-count"><?php echo count($finalization_needed); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <?php 
                // Group bookings by tour date ID (unique identifier)
                $grouped_bookings = [];
                foreach ($finalization_needed as $booking) {
                    // Use tour_date_id as the key to prevent duplicates
                    $key = $booking->tour_date_id;
                    $tour_date = bst_dashboard_live_tour_date_text( $booking->tour_date_id );
                    if ( empty( $tour_date ) ) {
                        $tour_date = 'TBD';
                    }
                    if (!isset($grouped_bookings[$key])) {
                        $grouped_bookings[$key] = [
                            'tour_text' => bst_dashboard_live_tour_title( $booking->tour_id ),
                            'tour_date' => $tour_date,
                            'start_date' => $booking->start_date,
                            'tour_date_id' => $booking->tour_date_id,
                            'bookings' => []
                        ];
                    }
                    $grouped_bookings[$key]['bookings'][] = $booking;
                }
                
                // Display grouped bookings
                foreach ($grouped_bookings as $group): 
                    $group_count = count($group['bookings']);
                    
                    // Get total: those needing finalization + those who already finalized
                    // Include ALL booking methods (Web and offline/admin) since they still need finalization
                    $total_bookings = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}bst_tour_booking
                        WHERE tour_date_id = %d
                        AND (
                            booking_status = 'Booked'
                            OR (finalization_entry_id IS NOT NULL AND finalization_entry_id != 0 AND finalization_entry_id != '')
                        )",
                        $group['tour_date_id']
                    ));
                    
                    // Get last finalization email batch info for this tour date
                    $last_finalization_batch = $wpdb->get_row($wpdb->prepare(
                        "SELECT batch_timestamp as sent_date,
                                total_emails as email_count,
                                successful_emails,
                                failed_emails
                        FROM {$wpdb->prefix}bst_email_batch
                        WHERE tour_date_id = %d
                        AND email_type = 'Finalization'
                        ORDER BY batch_timestamp DESC
                        LIMIT 1",
                        $group['tour_date_id']
                    ));
                    
                    // Calculate days until tour and overdue status for the group header
                    $days_until_tour_header = $group['start_date'] ? floor((strtotime($group['start_date']) - time()) / (24 * 60 * 60)) : 0;
                    // Calculate overdue threshold: typical 60-day deadline minus grace period
                    // Since dashboard shows aggregate data and can't parse individual booking terms,
                    // we use a reasonable estimate: 60 days (typical deadline) - 7 days (grace) = 53 days
                    $overdue_grace_days = get_option('bst_finalization_overdue_grace_days', 7);
                    $overdue_threshold_days = 60 - $overdue_grace_days; // Typical deadline minus grace period
                    $is_group_overdue = $days_until_tour_header < $overdue_threshold_days;
                    
                    $group_id = 'finalization-group-' . sanitize_title($group['tour_text'] . '-' . $group['tour_date']);
                ?>
                <div class="finalization-tour-group" style="margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <div class="finalization-group-header" style="background: #f8f9fa; padding: 12px 15px; cursor: pointer; border-radius: 4px 4px 0 0;" onclick="toggleFinalizationGroup('<?php echo $group_id; ?>')">
                        <!-- Row 1: Tour name, date, days until tour, send email button -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                                <span class="finalization-toggle-arrow" id="<?php echo $group_id; ?>-arrow" style="font-size: 14px; transition: transform 0.2s;">▶</span>
                                <strong style="font-size: 14px;"><?php echo esc_html($group['tour_text']); ?> (<?php echo esc_html($group['tour_date']); ?>)</strong>
                                <span style="color: <?php echo $is_group_overdue ? '#dc3232' : '#666'; ?>; font-size: 13px;">– <?php echo $days_until_tour_header; ?> days until tour</span>
                            </div>
                            <div>
                                <button type="button" 
                                        class="button button-small button-primary" 
                                        onclick="event.stopPropagation(); openDashboardFinalizationEmailModal(<?php echo $group['tour_date_id']; ?>, '<?php echo esc_js($group['tour_text']); ?>', <?php echo htmlspecialchars(json_encode($group['bookings']), ENT_QUOTES, 'UTF-8'); ?>);">
                                    ✉️ Send Email
                                </button>
                            </div>
                        </div>
                        <!-- Row 2: Finalization count and overdue badge -->
                        <div style="display: flex; align-items: center; gap: 10px; padding-left: 24px;">
                            <span style="padding: 2px 8px; background: #e8e8e8; color: #555; font-size: 12px; font-weight: 600; border-radius: 3px;"><?php echo $group_count; ?> of <?php echo $total_bookings; ?> need finalization</span>
                            <?php if ($is_group_overdue): ?>
                                <span style="padding: 2px 8px; background: #dc3232; color: white; font-size: 12px; font-weight: 600; border-radius: 3px;">⚠ OVERDUE</span>
                            <?php endif; ?>
                        </div>
                        <!-- Row 3: Last finalization email batch info -->
                        <div style="padding-left: 24px; margin-top: 6px; font-size: 12px; color: #666;">
                            <?php if ($last_finalization_batch): ?>
                                <span>Last finalization email: <?php echo date('M j, Y g:i A', strtotime($last_finalization_batch->sent_date)); ?> to <?php echo $last_finalization_batch->email_count; ?> booking<?php echo $last_finalization_batch->email_count != 1 ? 's' : ''; ?></span>
                            <?php else: ?>
                                <span>No finalization emails sent yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <ul class="dashboard-booking-list finalization-group-bookings" id="<?php echo $group_id; ?>" style="margin: 0; padding: 0 0 0 30px; display: none;">
                        <?php foreach ($group['bookings'] as $booking): ?>
                        <?php 
                        $days_until_tour = $booking->start_date ? floor((strtotime($booking->start_date) - time()) / (24 * 60 * 60)) : 0;
                        // Use same overdue threshold calculation as group header
                        $overdue_grace_days = get_option('bst_finalization_overdue_grace_days', 7);
                        $overdue_threshold_days = 60 - $overdue_grace_days;
                        $is_overdue = $days_until_tour < $overdue_threshold_days;
                        ?>
                        <li class="dashboard-booking-item" style="border-top: 1px solid #eee; padding: 8px 12px;">
                            <div class="dashboard-booking-info">
                                <div class="dashboard-booking-name">
                                    <?php echo esc_html(bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '')); ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" 
                                       style="margin-left: 8px; font-size: 12px; color: #0073aa; text-decoration: none; font-weight: normal;">
                                        [Booking <?php echo $booking->id; ?>]
                                    </a>
                                </div>
                                <div class="dashboard-booking-date" style="color: #666; font-weight: 600; font-size: 13px; margin-top: 4px;">
                                    <?php 
                                    $balance_due = floatval($booking->balance_due ?? 0);
                                    $currency_symbol = bst_get_currency_symbol($booking->tour_currency ?? 'EUR');
                                    
                                    // Calculate finalization due date using centralized helper function
                                    $due_date_display = '';
                                    if (function_exists('bst_calculate_balance_due_date')) {
                                        $balance_due_date = bst_calculate_balance_due_date($booking);
                                        if (!empty($balance_due_date) && !empty($booking->booking_entry_id)) {
                                            $entry = GFAPI::get_entry($booking->booking_entry_id);
                                            if ($entry && !is_wp_error($entry)) {
                                                $form_id = intval($entry['form_id']);
                                                $reg_terms_field = GFAPI::get_field($form_id, '36');
                                                $reg_terms_consented = rgar($entry, '36.1');
                                                $reg_terms_text = '';
                                                
                                                if ($reg_terms_field && $reg_terms_consented) {
                                                    $reg_terms_text = $reg_terms_field->get_value_export($entry, '36.3');
                                                }
                                                
                                                // Get payment deadline for display text
                                                $payment_deadline = array('value' => null, 'unit' => null);
                                                if ($form_id === 4) {
                                                    $payment_deadline = array('value' => 60, 'unit' => 'days');
                                                } elseif (!empty($reg_terms_text)) {
                                                    $payment_deadline = bst_parse_balance_deadline($reg_terms_text);
                                                }
                                                
                                                if ($payment_deadline['value']) {
                                                    $deadline_days = ($payment_deadline['unit'] === 'months') 
                                                        ? $payment_deadline['value'] . ' months' 
                                                        : $payment_deadline['value'] . ' days';
                                                    $due_date_display = ' (on ' . $balance_due_date . ' - ' . $deadline_days . ')';
                                                }
                                            }
                                        }
                                    }
                                    
                                    echo 'Balance due: ' . esc_html($currency_symbol . number_format($balance_due, 2)) . esc_html($due_date_display);
                                    ?>
                                </div>
                                <?php if (!empty($booking->last_finalization_sent)): ?>
                                <div style="color: #666; font-size: 12px; margin-top: 2px;">
                                    Last finalization email: <?php echo esc_html(date('M j, Y g:i a', strtotime($booking->last_finalization_sent))); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="dashboard-tile-footer">
                <span style="color: #666; font-style: italic;">Web bookings requiring finalization email</span>
            </div>
        </div>
        <script>
        function toggleFinalizationGroup(groupId) {
            var list = document.getElementById(groupId);
            var arrow = document.getElementById(groupId + '-arrow');
            if (list.style.display === 'none') {
                list.style.display = 'block';
                arrow.innerHTML = '▼';
            } else {
                list.style.display = 'none';
                arrow.innerHTML = '▶';
            }
        }
        </script>
        <?php 
        // Include email modals for finalization emails
        if (!empty($finalization_needed)) {
            $email_log_viewer = new BST_Email_Log_Viewer();
            $email_log_viewer->render_email_modals();
        }
        ?>
        <?php endif; ?>

        <?php if (!empty($transfer_bookings)): ?>
        <!-- Transfer Bookings Tile -->
        <div class="dashboard-tile transfer-bookings">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">Bookings Awaiting Transfer</h3>
                <span class="dashboard-tile-count"><?php echo count($transfer_bookings); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <ul class="dashboard-booking-list">
                    <?php foreach ($transfer_bookings as $booking): ?>
                    <li class="dashboard-booking-item">
                        <div class="dashboard-booking-info">
                            <div class="dashboard-booking-name">
                                <?php echo esc_html(bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '')); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" 
                                   style="margin-left: 8px; font-size: 12px; color: #0073aa; text-decoration: none; font-weight: normal;">
                                    [Booking <?php echo $booking->id; ?>]
                                </a>
                            </div>
                            <div class="dashboard-booking-tour"><?php echo esc_html( bst_dashboard_live_tour_display( $booking ) ); ?></div>
                            <div class="dashboard-booking-date">
                                <?php echo esc_html(date('M j, Y', strtotime($booking->created_date))); ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="dashboard-tile-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=bst-tour-bookings&filter_status=transfer')); ?>">
                    View Transfer Bookings →
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($recent_bookings)): ?>
        <!-- Most Recent Web Bookings Tile -->
        <div class="dashboard-tile">
            <div class="dashboard-tile-header">
                <h3 class="dashboard-tile-title">📈 Most Recent Web Bookings</h3>
                <span class="dashboard-tile-count"><?php echo count($recent_bookings); ?></span>
            </div>
            <div class="dashboard-tile-content">
                <ul class="dashboard-booking-list">
                    <?php foreach ($recent_bookings as $booking): ?>
                    <li class="dashboard-booking-item">
                        <div class="dashboard-booking-info">
                            <div class="dashboard-booking-name">
                                <?php echo esc_html(bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '')); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=view_booking&id=' . $booking->id)); ?>" 
                                   style="margin-left: 8px; font-size: 12px; color: #0073aa; text-decoration: none; font-weight: normal;">
                                    [Booking <?php echo $booking->id; ?>]
                                </a>
                            </div>
                            <div class="dashboard-booking-tour"><?php echo esc_html( bst_dashboard_live_tour_display( $booking ) ); ?></div>
                            <div class="dashboard-booking-date">
                                <strong>Status:</strong> <?php echo esc_html($booking->booking_status); ?> - <?php echo esc_html(date('M j, Y', strtotime($booking->created_date))); ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="dashboard-tile-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=bst-tour-bookings')); ?>">
                    View All Bookings →
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php 
    // Show message if no actionable items
    $has_items = !empty($waiting_list_bookings) || !empty($reserved_bookings) || 
                 !empty($bank_wire_pending) || !empty($reservations_not_booked) || 
                 !empty($finalization_needed) || !empty($transfer_bookings) || !empty($refunds_due) || !empty($recent_bookings);
    if (!$has_items): 
    ?>
    <div style="text-align: center; padding: 40px; background: white; border: 1px solid #c3c4c7; border-radius: 4px; color: #666;">
        <h3 style="color: #666; margin-bottom: 10px;">All Caught Up!</h3>
        <p style="margin: 0; font-size: 16px;">There are no items requiring immediate attention at this time.</p>
    </div>
    <?php endif; ?>

<?php
if ( ! empty( $finalization_needed ) ) {
	$bst_bulk_finalization_args = array(
		'require_tour_date_id' => true,
	);
	include __DIR__ . '/partials/bst-bulk-email-modal.php';
}
?>
</div>

