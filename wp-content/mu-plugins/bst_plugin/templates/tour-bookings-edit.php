<?php
// filepath: c:\Users\wayne\Local Sites\bluestradatours-staging\app\public\wp-content\mu-plugins\bst_plugin\templates\tour-bookings-edit.php
/**
 * Template for displaying the tour booking edit/add page.
 *
 * Available variables:
 *   - $booking: A tour booking object (if editing), or null (if adding).
 */

// Include helper functions for tile rendering
require_once plugin_dir_path(__FILE__) . '../includes/database/tour-booking-actions.php';
require_once plugin_dir_path(__FILE__) . '../includes/tour-booking-renderers.php';

// Fetch all tours for dropdown (any status except those excluded by WP for `any`, e.g. trash)
$tours = get_posts([
    'post_type' => 'tour',
    'posts_per_page' => -1,
    'post_status' => function_exists( 'bst_post_statuses_for_admin_scan' ) ? bst_post_statuses_for_admin_scan( 'tour' ) : 'any',
    'orderby' => 'title',
    'order' => 'ASC'
]);

// Fetch all customers for dropdown
global $wpdb;
$all_customers = $wpdb->get_results("SELECT id, first_name, last_name, email FROM {$wpdb->prefix}bst_customers ORDER BY last_name, first_name");

// Previous/Next navigation logic
$nav = isset($GLOBALS['bst_booking_nav']) ? $GLOBALS['bst_booking_nav'] : null;
$prev_id = $next_id = null;
if ($nav && is_array($nav['ids']) && $nav['current_index'] !== null) {
    $idx = $nav['current_index'];
    if ($idx > 0) $prev_id = $nav['ids'][$idx-1];
    if ($idx < count($nav['ids'])-1) $next_id = $nav['ids'][$idx+1];
}

// Helper to get value
function bst_val($booking, $field) {
    return isset($booking->$field) ? esc_attr($booking->$field) : '';
}
?>

<div class="wrap">
<h1 class="wp-heading-inline">Tour Booking</h1>

<!-- Page Actions -->
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <!-- Navigation and Return -->
    <div style="display: flex; align-items: center; gap: 10px;">
        <?php 
        // Build back URL preserving filter parameters
        $back_url_params = array('page' => 'bst-tour-bookings');
        $preserve_params = array('filter_tour_id', 'filter_tour_date_id', 'filter_status', 'search', 'sort_by', 'sort_order');
        foreach ($preserve_params as $param) {
            if (isset($_GET[$param]) && $_GET[$param] !== '') {
                $back_url_params[$param] = sanitize_text_field($_GET[$param]);
            }
        }
        $back_url = admin_url('admin.php?' . http_build_query($back_url_params));
        ?>
        <a href="<?php echo esc_url($back_url); ?>" class="button">← Back to List</a>
        
        <?php if ($prev_id || $next_id): ?>
            <div style="display: flex; align-items: center; gap: 5px;">
                <?php if ($prev_id): ?>
                    <?php 
                    // Build previous URL preserving filters
                    $prev_url_params = array('page' => 'bst-tour-bookings', 'action' => 'edit', 'id' => $prev_id);
                    foreach ($preserve_params as $param) {
                        if (isset($_GET[$param]) && $_GET[$param] !== '') {
                            $prev_url_params[$param] = sanitize_text_field($_GET[$param]);
                        }
                    }
                    $prev_url = admin_url('admin.php?' . http_build_query($prev_url_params));
                    ?>
                    <a href="<?php echo esc_url($prev_url); ?>" class="button">← Previous</a>
                <?php endif; ?>
                <?php if ($next_id): ?>
                    <?php 
                    // Build next URL preserving filters
                    $next_url_params = array('page' => 'bst-tour-bookings', 'action' => 'edit', 'id' => $next_id);
                    foreach ($preserve_params as $param) {
                        if (isset($_GET[$param]) && $_GET[$param] !== '') {
                            $next_url_params[$param] = sanitize_text_field($_GET[$param]);
                        }
                    }
                    $next_url = admin_url('admin.php?' . http_build_query($next_url_params));
                    ?>
                    <a href="<?php echo esc_url($next_url); ?>" class="button">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Inline editing styles */
.tile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #ddd;
    min-height: 30px; /* Ensure consistent height */
}
.tile-title {
    margin: 0;
    padding: 0; /* Remove any default padding */
    color: #0073aa;
    font-size: 16px;
    line-height: 1;
    display: flex;
    align-items: center;
    height: 24px; /* Match button height for perfect alignment */
}
.tile-actions {
    display: flex;
    gap: 5px;
    margin-left: 10px;
    align-items: center; /* Ensure buttons are vertically centered */
}
.tile-edit-btn, .tile-save-btn, .tile-cancel-btn {
    background: none;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 4px 6px;
    cursor: pointer;
    font-size: 12px;
    color: #666;
    transition: all 0.2s;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 24px; /* Fixed height for consistency */
}
.tile-edit-btn:hover {
    background: #f0f0f0;
    color: #0073aa;
    border-color: #0073aa;
}
.tile-save-btn {
    color: #00a32a;
    border-color: #00a32a;
}
.tile-save-btn:hover {
    background: #00a32a;
    color: white;
}
.tile-cancel-btn {
    color: #d63638;
    border-color: #d63638;
}
.tile-cancel-btn:hover {
    background: #d63638;
    color: white;
}
.tile-editing {
    background: #fff9e6 !important;
    border-color: #ffa500 !important;
}
.tile-edit-form {
    display: none;
    margin-top: 15px;
}
.tile-view-content {
    display: block;
}
.tile-editing .tile-edit-form {
    display: block;
}
.tile-editing .tile-view-content {
    display: none;
}

/* Edit form layout improvements */
.edit-form-section {
    margin-bottom: 20px;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
}
.edit-form-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.edit-form-section h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #333;
    font-weight: 600;
}
.edit-form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 12px;
}
.edit-form-field {
    display: flex;
    flex-direction: column;
}
.edit-form-field.full-width {
    grid-column: 1 / -1;
}
.edit-form-field label {
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 4px;
    color: #333;
}
.edit-form-field input, 
.edit-form-field select, 
.edit-form-field textarea {
    padding: 6px 8px;
    font-size: 13px;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-family: inherit;
}
.edit-form-field textarea {
    resize: vertical;
    min-height: 60px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .edit-form-row {
        grid-template-columns: 1fr;
    }
    .tile-header {
        flex-direction: row; /* Keep horizontal layout */
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap; /* Prevent wrapping */
    }
    .tile-title {
        flex: 1;
        min-width: 0; /* Allow text to compress if needed */
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .tile-actions {
        margin-left: 8px;
        flex-shrink: 0; /* Prevent buttons from shrinking */
    }
    .tile-edit-btn, .tile-save-btn, .tile-cancel-btn {
        font-size: 12px; /* Slightly smaller on mobile but more readable */
        padding: 6px 10px;
        height: 28px; /* Better touch target */
        min-width: 45px; /* Ensure minimum button width */
    }
    /* Hide invoice column on mobile in payment table */
    .payment-invoice-col {
        display: none;
    }
}

/* Date input specific styling */
.date-picker-input,
input[type="date"] {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    width: 100% !important;
    height: 26px !important;
    padding: 2px 8px !important;
    border: 1px solid #ccd0d4 !important;
    border-radius: 4px !important;
    font-size: 13px !important;
    background-color: #fff !important;
    position: relative;
}

input[type="date"]::-webkit-calendar-picker-indicator {
    background: transparent;
    bottom: 0;
    color: transparent;
    cursor: pointer;
    height: auto;
    left: 0;
    position: absolute;
    right: 0;
    top: 0;
    width: auto;
    opacity: 1;
}

input[type="date"]::-webkit-inner-spin-button {
    display: none;
}

input[type="date"]::-webkit-clear-button {
    display: none;
}
.edit-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}
@media (max-width: 768px) {
    .edit-form-grid {
        grid-template-columns: 1fr;
    }
}

.view-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 12px;
}
.view-item {
    margin-bottom: 8px;
    font-size: 13px;
}
.view-item strong {
    display: inline-block;
    width: 130px;
    font-weight: 600;
    font-size: 13px;
}
.links-tile .view-item strong {
    width: 160px;
}
.links-tile .view-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.links-tile .link-url {
    flex: 1;
    word-break: break-all;
    font-size: 12px;
    color: #0073aa;
    text-decoration: none;
}
.links-tile .link-url:hover {
    text-decoration: underline;
    color: #005a87;
}
.links-tile .copy-button {
    flex-shrink: 0;
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
    border-radius: 2px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #424242;
    transition: all 0.15s;
    width: 24px;
    height: 24px;
}
.links-tile .copy-button:hover {
    background: #e8e8e8;
    color: #000;
}
.links-tile .copy-button.copied {
    background: transparent;
    color: #107c10;
}
.links-tile .copy-icon {
    width: 16px;
    height: 16px;
}
.links-tile .copy-text {
    display: none;
}
.view-address {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 8px;
    margin-bottom: 8px;
}
.tile-subsection-header {
    margin-top: 20px;
    margin-bottom: 10px;
    font-size: 15px;
    font-weight: 700;
    color: #0073aa;
    border-bottom: 1px solid #0073aa;
    padding-bottom: 5px;
}
.guest-columns {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 20px !important;
    margin-bottom: 20px !important;
    width: 100% !important;
    clear: both !important;
}
@media (max-width: 768px) {
    .guest-columns {
        grid-template-columns: 1fr !important;
    }
}
.guest-column {
    border: 1px solid #ddd !important;
    padding: 15px !important;
    border-radius: 4px !important;
    background: #fafafa !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
}
.guest-column h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #0073aa;
    font-size: 16px;
}
.guest-column h4 {
    margin: 15px 0 8px 0;
    font-size: 14px;
    color: #333;
}
.address-block {
    line-height: 1.4;
    font-size: 13px;
    display: inline-block;
    vertical-align: top;
}
.travel-details-text {
    line-height: 1.4;
    font-size: 13px;
    display: inline-block;
    word-wrap: break-word;
    word-break: break-word;
    text-align: left;
    vertical-align: top;
    max-width: calc(100% - 140px); /* Account for the label width */
}
/* Unified responsive tile grid for all information sections */
.responsive-tile-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 20px !important;
    margin-bottom: 20px !important;
    width: 100% !important;
    clear: both !important;
}
@media (max-width: 768px) {
    .responsive-tile-grid {
        grid-template-columns: 1fr !important;
    }
}
/* Unified tile styling for all sections */
.responsive-tile {
    border: 1px solid #ddd !important;
    padding: 15px !important;
    border-radius: 4px !important;
    background: #fafafa !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    margin-bottom: 20px !important;
}
/* Unified responsive tile styling */
.responsive-tile h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #0073aa;
    font-size: 16px;
}
.responsive-tile h4 {
    margin: 15px 0 8px 0;
    font-size: 14px;
    color: #333;
}
.pricing-matrix {
    display: grid;
    grid-template-columns: auto 1fr;
    row-gap: 4px;
    column-gap: 8px;
    font-size: 13px;
}
.pricing-matrix .pricing-label {
    font-weight: 600;
    text-align: left;
}
.pricing-matrix .pricing-value {
    text-align: left;
}
.payment-matrix {
    margin-top: 15px;
}
.payment-matrix table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.payment-matrix th {
    background: #f0f0f0;
    font-weight: 600;
    padding: 8px;
    border: 1px solid #ddd;
    text-align: left;
}
.payment-matrix td {
    padding: 8px;
    border: 1px solid #ddd;
}
/* Right-align amounts in payment table */
.payment-matrix td:nth-child(3) {
    text-align: right;
}
/*
 * Financials payment table — VIEW (read-only tile): 100% width, no horizontal scroll, no wrap (ellipsis if needed).
 */
.tile-view-content .bst-financials-payment-wrap {
    overflow-x: visible;
    max-width: 100%;
}
.tile-view-content .bst-financials-payment-table--view {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 12px;
}
.tile-view-content .bst-financials-payment-table--view th,
.tile-view-content .bst-financials-payment-table--view td {
    padding: 4px 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
}
.tile-view-content .bst-financials-payment-table--view th:nth-child(1),
.tile-view-content .bst-financials-payment-table--view td:nth-child(1) { width: 8%; }
.tile-view-content .bst-financials-payment-table--view th:nth-child(2),
.tile-view-content .bst-financials-payment-table--view td:nth-child(2) { width: 17%; }
.tile-view-content .bst-financials-payment-table--view th:nth-child(3),
.tile-view-content .bst-financials-payment-table--view td:nth-child(3) { width: 14%; }
.tile-view-content .bst-financials-payment-table--view th:nth-child(4),
.tile-view-content .bst-financials-payment-table--view td:nth-child(4) { width: 11%; }
.tile-view-content .bst-financials-payment-table--view th:nth-child(5),
.tile-view-content .bst-financials-payment-table--view td:nth-child(5) { width: 9%; }
.tile-view-content .bst-financials-payment-table--view th:nth-child(6),
.tile-view-content .bst-financials-payment-table--view td:nth-child(6) { width: 13%; }
.tile-view-content .bst-financials-payment-table--view th:nth-child(7),
.tile-view-content .bst-financials-payment-table--view td:nth-child(7) { width: 18%; }
.tile-view-content .bst-financials-payment-table--view td:nth-child(3) {
    text-align: right;
}
/*
 * EDIT mode: same fixed grid — fits tile width without horizontal scroll; inputs use min-width 0 inside cells.
 */
.tile-edit-form .bst-financials-payment-table--edit {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 12px;
}
.tile-edit-form .bst-financials-payment-table--edit th,
.tile-edit-form .bst-financials-payment-table--edit td {
    padding: 4px 5px;
    vertical-align: middle;
    overflow: hidden;
}
.tile-edit-form .bst-financials-payment-table--edit th:nth-child(1),
.tile-edit-form .bst-financials-payment-table--edit td:nth-child(1) { width: 8%; }
.tile-edit-form .bst-financials-payment-table--edit th:nth-child(2),
.tile-edit-form .bst-financials-payment-table--edit td:nth-child(2) { width: 17%; }
.tile-edit-form .bst-financials-payment-table--edit th:nth-child(3),
.tile-edit-form .bst-financials-payment-table--edit td:nth-child(3) { width: 14%; }
.tile-edit-form .bst-financials-payment-table--edit th:nth-child(4),
.tile-edit-form .bst-financials-payment-table--edit td:nth-child(4) { width: 11%; }
.tile-edit-form .bst-financials-payment-table--edit th:nth-child(5),
.tile-edit-form .bst-financials-payment-table--edit td:nth-child(5) { width: 9%; }
/* Edit only: wider Date (YYYY-MM-DD), narrower Invoice (max e.g. CBC9999) — display/view widths unchanged */
.tile-edit-form .bst-financials-payment-table--edit th:nth-child(6),
.tile-edit-form .bst-financials-payment-table--edit td:nth-child(6) { width: 18%; }
.tile-edit-form .bst-financials-payment-table--edit th:nth-child(7),
.tile-edit-form .bst-financials-payment-table--edit td:nth-child(7) { width: 13%; }
.tile-edit-form .bst-financials-payment-table--edit td:nth-child(3) input {
    text-align: right;
}
.tile-edit-form .bst-financials-payment-table--edit input,
.tile-edit-form .bst-financials-payment-table--edit select {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}
.tile-edit-form .bst-financials-payment-scroll {
    overflow-x: visible;
    max-width: 100%;
}
.text-format {
    background: #f8f8f8;
    padding: 10px;
    border-radius: 4px;
    line-height: 1.5;
    font-size: 13px;
    margin-top: 8px;
}
.travel-grid {
    background: #f8f8f8;
    padding: 12px;
    border-radius: 4px;
    margin-top: 8px;
}
.travel-grid table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.travel-grid th {
    background: #e0e0e0;
    font-weight: 600;
    padding: 8px;
    border: 1px solid #ddd;
    text-align: left;
}
.travel-grid td {
    padding: 8px;
    border: 1px solid #ddd;
    vertical-align: top;
}
.travel-grid .field-label {
    font-weight: 600;
    background: #f0f0f0;
    width: 120px;
}
.emergency-contact-simple {
    display: inline;
    font-size: 13px;
    line-height: 1.3;
    word-wrap: break-word;
}
.empty-info {
    color: #666;
    font-style: italic;
}
.navigation-section {
    float: right;
}
.disabled-button {
    opacity: 0.5;
    cursor: not-allowed;
}
.view-mode-banner {
    clear: both;
    text-align: center;
    margin: 15px 0;
    padding: 8px 12px;
    background: #f0f0f1;
    border-left: 4px solid #72aee6;
    font-weight: 500;
}
.clear-section {
    clear: both;
    margin-bottom: 20px;
}
.notes-display {
    margin-top: 5px;
    padding: 10px;
    background: #f9f9f9;
    border: none;
    border-radius: 4px;
    white-space: pre-wrap;
    min-height: 40px;
}
/* Standalone notes section styling */
.responsive-tile:last-child {
    margin-bottom: 20px;
}
.payment-grid-wrapper {
    margin-bottom: 20px;
}
.payment-grid-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.payment-grid-header {
    background: #f0f0f0;
    font-weight: 600;
    padding: 8px;
    border: 1px solid #ddd;
    text-align: left;
}
.payment-grid-cell {
    padding: 8px;
    border: 1px solid #ddd;
}
.payment-grid-label {
    padding: 8px;
    border: 1px solid #ddd;
    font-weight: 600;
}
.payment-grid-input {
    width: 100%;
    border: none;
    background: transparent;
    padding: 2px;
}
.form-buttons-section {
    margin-top: 20px;
}
</style>

    <!-- Position Indicator -->
    <?php if ($nav && isset($nav['current_position']) && $nav['current_position']): ?>
    <div class="view-mode-banner" style="background-color: #f0f0f1; padding: 8px 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #0073aa; font-size: 14px;">
        Record <?php echo number_format($nav['current_position']); ?> of <?php echo number_format($nav['total_filtered']); ?> in selection
    </div>
    <?php endif; ?>
    
    <div class="clear-section"></div>

    <!-- Guest Information Columns -->
    <div class="guest-columns">
        <div class="guest-column" data-tile="guest1">
            <div class="tile-header">
                <h3 class="tile-title">Guest 1 Information</h3>
                <div class="tile-actions">
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_guest_tile_content($booking, 1); ?>
            
            <div class="tile-edit-form">
                <!-- Guest 1 edit form will be populated via JavaScript -->
            </div>
        </div>

        <!-- Guest 2 Information (Always present, hidden if package_people != 2) -->
        <div class="guest-column" data-tile="guest2" <?php if (($booking->package_people ?? 0) != 2) echo 'style="display: none;"'; ?>>
            <div class="tile-header">
                <h3 class="tile-title">Guest 2 Information</h3>
                <div class="tile-actions">
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_guest_tile_content($booking, 2); ?>
            
            <div class="tile-edit-form">
                <!-- Guest 2 edit form will be populated via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Tour Information and Pricing -->
    <div class="responsive-tile-grid">
        <div class="responsive-tile" data-tile="tour_package">
            <div class="tile-header">
                <h3 class="tile-title">Tour & Package Information</h3>
                <div class="tile-actions">
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_tour_package_tile_content($booking); ?>
            
            <div class="tile-edit-form">
                <!-- Tour package edit form will be populated via JavaScript -->
            </div>
        </div>

        <div class="responsive-tile" data-tile="financials">
            <div class="tile-header">
                <h3 class="tile-title">Booking Financials</h3>
                <div class="tile-actions">
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_financials_tile_content($booking); ?>
            
            <div class="tile-edit-form">
                <!-- Financials edit form will be populated via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Information Tiles - Single Flowing Grid -->
    <div class="responsive-tile-grid">
        <!-- Customer Information -->
        <div class="responsive-tile" data-tile="customer">
            <div class="tile-header">
                <h3 class="tile-title">Customer Information</h3>
                <div class="tile-actions">
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_customer_tile_content($booking); ?>
            
            <div class="tile-edit-form">
                <!-- Customer edit form will be populated via JavaScript -->
            </div>
        </div>

        <!-- Marketing Information -->
        <div class="responsive-tile" data-tile="marketing">
            <div class="tile-header">
                <h3 class="tile-title">Marketing Information</h3>
                <div class="tile-actions">
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_marketing_tile_content($booking); ?>
            
            <div class="tile-edit-form">
                <!-- Marketing edit form will be populated via JavaScript -->
            </div>
        </div>

        <!-- Gravity Forms Information -->
        <div class="responsive-tile" data-tile="gravity_forms">
            <div class="tile-header">
                <h3 class="tile-title">Gravity Forms Information</h3>
                <div class="tile-actions">
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_gravity_forms_tile_content($booking); ?>
            
            <div class="tile-edit-form">
                <!-- Gravity Forms edit form will be populated via JavaScript -->
            </div>
        </div>

        <!-- Administrative Information -->
        <div class="responsive-tile" data-tile="administrative">
            <div class="tile-header">
                <h3 class="tile-title">Administrative Information</h3>
                <div class="tile-actions">
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_administrative_tile_content($booking); ?>
            
            <div class="tile-edit-form">
                <!-- Administrative edit form will be populated via JavaScript -->
            </div>
        </div>

        <!-- Booking Invoicing -->
        <div class="responsive-tile" data-tile="invoicing">
            <div class="tile-header">
                <h3 class="tile-title">Pro Forma Invoice Summary</h3>
                <div class="tile-actions">
                    <?php if (!empty($booking->finalization_entry_id)): ?>
                    <button type="button" class="recalc-invoice-btn" title="Recalculate invoice fields from tour data" style="background: #2271b1; color: white; border: none; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin-right: 8px; font-size: 13px;">
                        <i class="fas fa-calculator"></i> Recalculate Invoice Data
                    </button>
                    <?php endif; ?>
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_invoicing_tile_content($booking); ?>
            
            <div class="tile-edit-form">
                <!-- Invoicing edit form will be populated via JavaScript -->
            </div>
        </div>

        <!-- Email Log -->
        <?php if ($booking && $booking->id): ?>
        <div class="responsive-tile" data-tile="email_log">
            <div class="tile-header">
                <h3 class="tile-title">Email Log</h3>
                <div class="tile-actions">
                    <!-- No edit button for email log - it's read-only -->
                    <button type="button" class="button button-small send-new-email" onclick="openSendEmailModal(<?php echo $booking->id; ?>)" style="margin-right: 8px;">
                        <i class="fas fa-envelope"></i> Send Email
                    </button>
                    <button type="button" class="button button-small refresh-email-log" onclick="refreshEmailLog(<?php echo $booking->id; ?>)">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>
            
            <div id="email-log-content-<?php echo $booking->id; ?>">
                <?php echo bst_render_email_log_tile_content($booking); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <?php if ($booking && $booking->id): ?>
        <div class="responsive-tile" data-tile="actions">
            <div class="tile-header">
                <h3 class="tile-title">Actions</h3>
                <div class="tile-actions">
                    <!-- Actions tile has no edit button -->
                </div>
            </div>
            
            <?php echo bst_render_actions_tile_content($booking); ?>
        </div>
        <?php endif; ?>

        <!-- Links -->
        <div class="responsive-tile" data-tile="links">
            <div class="tile-header">
                <h3 class="tile-title">Links</h3>
                <div class="tile-actions">
                    <!-- Links tile has no edit button -->
                </div>
            </div>
            
            <?php echo bst_render_links_tile_content($booking); ?>
        </div>

        <!-- System Information -->
        <div class="responsive-tile" data-tile="system">
            <div class="tile-header">
                <h3 class="tile-title">System Information</h3>
                <div class="tile-actions">
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_system_tile_content($booking); ?>
            
            <div class="tile-edit-form">
                <!-- System Information edit form will be populated via JavaScript -->
            </div>
        </div>

        <!-- Notes -->
        <div class="responsive-tile" data-tile="notes">
            <div class="tile-header">
                <h3 class="tile-title">Notes</h3>
                <div class="tile-actions">
                    <button type="button" class="tile-edit-btn" title="Edit this section">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            
            <?php echo bst_render_notes_tile_content($booking); ?>
            
            <div class="tile-edit-form">
                <!-- Notes edit form will be populated via JavaScript -->
            </div>
        </div>
    </div>

<!-- Delete Button - Positioned at bottom under form content -->
<?php if ($booking): ?>
<?php 
$total_paid = floatval($booking->total_paid ?? 0);
$has_payments = $total_paid > 0;
$delete_disabled = $has_payments ? 'disabled' : '';
$delete_title = $has_payments ? 'Cannot delete booking with payments. Total paid: ' . bst_format_currency($total_paid, $booking->tour_currency ?? 'EUR') : 'Delete this booking';
?>
<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center;">
    <button id="delete-booking-btn" class="button button-link-delete" <?php echo $delete_disabled; ?> title="<?php echo esc_attr($delete_title); ?>" style="color: #d63638; background: transparent; border: 1px solid #d63638; padding: 8px 12px; border-radius: 3px;">
        🗑️ Delete Booking
    </button>
    <p style="font-size: 12px; color: #666; margin-top: 8px; font-style: italic;">
        <?php if ($has_payments): ?>
            Booking cannot be deleted - has payments totaling <?php echo esc_html(bst_format_currency($total_paid, $booking->tour_currency ?? 'EUR')); ?>
        <?php else: ?>
            Warning: This action cannot be undone
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<!-- Email Content Modal -->
<div id="email-content-modal" class="bst-modal" style="display: none;">
    <div class="bst-modal-content">
        <div class="bst-modal-header">
            <h3>Email Content</h3>
            <button type="button" class="bst-modal-close" onclick="closeEmailModal()">&times;</button>
        </div>
        <div class="bst-modal-body" id="email-content-body">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<!-- Send Email Modal -->
<div id="send-email-modal" class="bst-modal" style="display: none;">
    <div class="bst-modal-content" style="max-width: 1200px;">
        <div class="bst-modal-header">
            <h3>Send Email</h3>
            <button type="button" class="bst-modal-close" onclick="closeSendEmailModal()">&times;</button>
        </div>
        <div class="bst-modal-body" id="send-email-body">
            <!-- Tabs -->
            <div style="display: flex; border-bottom: 2px solid #ddd; margin-bottom: 12px;">
                <button type="button" class="email-modal-tab active" data-tab="compose" onclick="switchEmailTab('compose')" style="flex: 1; padding: 8px; background: #f0f0f0; border: none; border-bottom: 3px solid #2271b1; cursor: pointer; font-size: 13px; font-weight: 600; color: #2271b1; transition: all 0.2s;">
                    <i class="fas fa-edit"></i> Compose
                </button>
                <button type="button" class="email-modal-tab" data-tab="preview" onclick="switchEmailTab('preview')" style="flex: 1; padding: 8px; background: white; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 13px; color: #666; transition: all 0.2s;">
                    <i class="fas fa-eye"></i> Preview
                </button>
            </div>

            <!-- Compose Tab -->
            <div id="compose-tab" class="email-tab-content">
                <div style="display: grid; grid-template-columns: 1fr 300px; gap: 20px;">
                    <!-- Email Composition Form -->
                    <div>
                        <form id="send-email-form" style="display: grid; grid-template-columns: 70px 1fr 50px 1fr; gap: 10px 10px; align-items: center;">
                            <!-- Template -->
                            <label for="email-template-select" style="font-size: 12px; color: #333; font-weight: 500; text-align: right; white-space: nowrap;">Template:</label>
                            <select id="email-template-select" style="padding: 5px; font-size: 12px; border: 1px solid #ddd; border-radius: 3px;" onchange="loadEmailTemplate(this.value)">
                                <option value="">-- Start from scratch --</option>
                            </select>
                            
                            <!-- Type -->
                            <label for="email-type" style="font-size: 12px; color: #333; font-weight: 500; text-align: right; white-space: nowrap;">Type:</label>
                            <select id="email-type" style="padding: 5px; font-size: 12px; border: 1px solid #ddd; border-radius: 3px;">
                                <option value="Ad Hoc">Ad Hoc</option>
                                <option value="Finalization">Finalization</option>
                                <option value="Invoice">Invoice</option>
                                <option value="Notification">Notification</option>
                                <option value="Reservation">Reservation</option>
                            </select>

                            <!-- To -->
                            <label for="email-to-select" style="font-size: 12px; color: #333; font-weight: 500; text-align: right; white-space: nowrap;">To:</label>
                            <select id="email-to-select" style="padding: 4px; font-size: 12px;" required>
                                <option value="">Loading recipients...</option>
                            </select>
                            
                            <!-- CC -->
                            <label for="email-cc" style="font-size: 12px; color: #333; font-weight: 500; text-align: right; white-space: nowrap;">CC:</label>
                            <div style="position: relative;">
                                <input type="email" id="email-cc" style="padding: 4px; font-size: 12px; border: 1px solid #ddd; border-radius: 3px; width: 100%; padding-right: 30px;" placeholder="Optional" oninput="validateCcEmail(this)">
                                <span id="cc-validation-icon" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); display: none; font-size: 14px;"></span>
                            </div>

                            <!-- Subject (spans 2 columns for field) -->
                            <label for="email-subject" style="font-size: 12px; color: #333; font-weight: 500; text-align: right; white-space: nowrap; align-self: start; padding-top: 4px;">Subject:</label>
                            <div style="grid-column: 2 / 5;">
                                <input type="text" id="email-subject" style="padding: 4px; font-size: 12px; border: 1px solid #ddd; border-radius: 3px; width: 100%;" required placeholder="Required * (Merge fields supported)" oninput="updateSubjectCounter()" title="Merge fields can be inserted here">
                                <div style="font-size: 10px; color: #666; margin-top: 2px; text-align: right;">
                                    <span id="subject-counter">0/70</span>
                                    <span id="subject-warning" style="color: #d63638; display: none; margin-left: 8px;">⚠ Subject may be truncated in some email clients</span>
                                </div>
                            </div>

                            <!-- Attachment (spans 2 columns for field) -->
                            <label for="email-attachment" style="font-size: 12px; color: #333; font-weight: 500; text-align: right; white-space: nowrap; align-self: start; padding-top: 4px;">Attachment:</label>
                            <div style="grid-column: 2 / 5;">
                                <input type="file" id="email-attachment" style="font-size: 11px;" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.zip">
                                <p style="font-size: 10px; color: #666; margin: 4px 0 0 0;">Optional file attachment (PDF, images, documents). Max: <?php echo size_format(wp_max_upload_size()); ?></p>
                                <div id="attachment-preview" style="display: none; margin-top: 6px; padding: 6px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 3px; font-size: 11px;">
                                    <span class="dashicons dashicons-paperclip" style="color: #0073aa; font-size: 14px; vertical-align: middle;"></span>
                                    <span id="attachment-filename"></span>
                                    <button type="button" onclick="clearAttachment()" style="float: right; font-size: 10px; padding: 2px 6px; background: #f44336; color: white; border: none; border-radius: 2px; cursor: pointer;">Remove</button>
                                </div>
                            </div>

                            <!-- Email Content Editor (spans all columns) -->
                            <div style="grid-column: 1 / 5; margin-top: 4px;">
                                <div id="email-editor-main">
                                    <textarea id="email-content" style="width: 100%; height: 220px; padding: 6px; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; resize: vertical;" required placeholder="Type your message here..."></textarea>
                                </div>
                            </div>

                            <!-- Action Buttons (spans all columns) -->
                            <div style="grid-column: 1 / 5; display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <button type="button" id="save-as-template-btn" onclick="saveAsTemplate()" class="button" style="font-size: 11px; padding: 4px 10px; height: auto;">
                                        <i class="fas fa-save"></i> Save as Template
                                    </button>
                                    <span id="send-email-status" style="font-style: italic; color: #666; font-size: 11px;"></span>
                                </div>
                                <div>
                                    <button type="button" onclick="closeSendEmailModal()" class="button" style="margin-right: 8px; font-size: 12px; padding: 4px 12px;">Cancel</button>
                                    <button type="button" id="send-test-btn" onclick="sendTestEmail()" class="button" style="margin-right: 8px; font-size: 12px; padding: 4px 12px; background: #f0f0f0;">
                                        <i class="fas fa-flask"></i> Send Test to Me
                                    </button>
                                    <button type="button" id="send-email-btn" onclick="sendAdHocEmail()" class="button button-primary" style="font-size: 12px; padding: 4px 12px;">
                                        <i class="fas fa-paper-plane"></i> Send Email
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Merge Fields Sidebar -->
                    <div id="merge-fields-sidebar" style="border-left: 1px solid #ddd; padding-left: 15px; width: 240px; min-width: 240px; display: flex; flex-direction: column;">
                        <div style="position: sticky; top: 0; background: white; padding-bottom: 4px; border-bottom: 1px solid #eee; margin-bottom: 4px; z-index: 10;">
                            <h4 style="margin: 0 0 2px 0; color: #333; font-size: 12px;">Merge Fields</h4>
                            <p style="font-size: 10px; color: #666; margin: 0;">Click to insert into Subject or Content</p>
                        </div>
                        
                        <!-- Scrollable Merge Fields Categories -->
                        <div id="merge-fields-categories" style="flex: 1; overflow-y: auto; max-height: calc(100vh - 400px); padding-right: 5px;">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Tab -->
            <div id="preview-tab" class="email-tab-content" style="display: none;">
                <div style="max-width: 900px; margin: 0 auto;">
                    <!-- Preview Header -->
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 12px; border: 1px solid #ddd;">
                        <div style="display: grid; grid-template-columns: auto 1fr; gap: 10px; font-size: 12px;">
                            <strong>From:</strong>
                            <span id="preview-from"><?php echo esc_html(get_option('bst_from_email_name', 'Blue Strada Tours') . ' <' . get_option('bst_from_email_address', 'info@bluestradatours.com') . '>'); ?></span>
                            <strong>To:</strong>
                            <span id="preview-to"></span>
                            <strong>CC:</strong>
                            <span id="preview-cc" style="display: none;"></span>
                            <strong>Subject:</strong>
                            <span id="preview-subject"></span>
                        </div>
                    </div>

                    <!-- Preview Content -->
                    <div id="email-preview-content" style="padding: 20px; background: #fff; min-height: 350px; border: 1px solid #ddd; border-radius: 4px; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6;">
                        <p style="color: #999; font-style: italic; text-align: center;">Preview will appear here when you switch to this tab...</p>
                    </div>

                    <!-- Preview Actions -->
                    <div style="margin-top: 12px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" onclick="switchEmailTab('compose')" class="button" style="font-size: 12px; padding: 4px 12px;">
                            <i class="fas fa-arrow-left"></i> Back to Compose
                        </button>
                        <button type="button" onclick="sendAdHocEmail()" class="button button-primary" style="font-size: 12px; padding: 4px 12px;">
                            <i class="fas fa-paper-plane"></i> Send Email
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bst-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.bst-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 5px;
    width: 90%;
    max-width: 800px;
    max-height: 80%;
    overflow: hidden;
}

.bst-modal-header {
    background: #f1f1f1;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.bst-modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.bst-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bst-modal-close:hover {
    background: #ddd;
    border-radius: 3px;
}

.bst-modal-body {
    padding: 15px;
    max-height: 70vh;
    overflow-y: auto;
}

/* Email log badge styles */
.email-type-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.email-type-badge.reservation {
    background: #e3f2fd;
    color: #1976d2;
}

.email-type-badge.finalization {
    background: #f3e5f5;
    color: #7b1fa2;
}

.email-type-badge.invoice {
    background: #fff3e0;
    color: #f57c00;
}

.email-type-badge.notification {
    background: #e8f5e8;
    color: #2e7d32;
}

.email-type-badge.adhoc {
    background: #fff3cd;
    color: #856404;
}

/* Email editor tabs */
.email-editor-tab {
    transition: all 0.2s;
}

.email-editor-tab:hover {
    background: #f0f0f0;
}

.email-editor-tab.active {
    border-bottom-color: #2271b1 !important;
    color: #2271b1;
    font-weight: 600;
}

.email-editor-panel {
    min-height: 240px;
}

/* Email modal tabs */
.email-modal-tab:hover {
    background: #f8f8f8 !important;
}

.email-tab-content {
    animation: fadeIn 0.2s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeInOut {
    0% { opacity: 0; transform: translateY(-10px); }
    10% { opacity: 1; transform: translateY(0); }
    90% { opacity: 1; transform: translateY(0); }
    100% { opacity: 0; transform: translateY(-10px); }
}

/* Mobile responsive styles for modal */
@media (max-width: 768px) {
    .bst-modal-content {
        max-width: 95%;
        max-height: 95%;
        margin: 2% auto;
    }
    
    .bst-modal-body {
        padding: 15px;
        max-height: 80vh;
    }
    
    /* Make grid single column on mobile */
    .bst-modal-body > div[style*="grid"] {
        display: block !important;
    }
    
    /* Stack form elements on mobile */
    .bst-modal-body div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
    
    /* Merge fields sidebar below form on mobile */
    #merge-fields-sidebar {
        border-left: none !important;
        border-top: 1px solid #ddd !important;
        padding-left: 0 !important;
        padding-top: 20px !important;
        margin-top: 20px !important;
        width: 100% !important;
        min-width: auto !important;
    }
    
    #merge-fields-categories {
        max-height: 250px !important;
    }
}

@media (max-width: 480px) {
    .bst-modal-content {
        max-width: 98%;
        margin: 1% auto;
    }
    
    .bst-modal-header {
        padding: 10px 15px;
    }
    
    .bst-modal-body {
        padding: 10px;
    }
    
    /* Smaller text inputs on very small screens */
    .bst-modal input, .bst-modal select, .bst-modal textarea {
        font-size: 16px; /* Prevent zoom on iOS */
    }
}
</style>

<script>
// Copy to clipboard functionality for links tile
function copyToClipboard(url, button) {
    var $button = jQuery(button);
    var originalText = $button.find('.copy-text').text();
    
    // Check if modern clipboard API is available
    if (navigator.clipboard && navigator.clipboard.writeText) {
        // Use the modern clipboard API
        navigator.clipboard.writeText(url).then(function() {
            // Success feedback
            $button.addClass('copied');
            $button.find('.copy-text').text('Copied!');
            $button.find('.copy-icon').removeClass('dashicons-clipboard').addClass('dashicons-yes');
            
            // Reset after 2 seconds
            setTimeout(function() {
                $button.removeClass('copied');
                $button.find('.copy-text').text(originalText);
                $button.find('.copy-icon').removeClass('dashicons-yes').addClass('dashicons-clipboard');
            }, 2000);
        }).catch(function(err) {
            console.error('Clipboard API failed:', err);
            alert('Failed to copy URL');
        });
    } else {
        // Fallback for older browsers or non-secure contexts
        var textArea = document.createElement('textarea');
        textArea.value = url;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '0';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                $button.addClass('copied');
                $button.find('.copy-text').text('Copied!');
                $button.find('.copy-icon').removeClass('dashicons-clipboard').addClass('dashicons-yes');
                
                setTimeout(function() {
                    $button.removeClass('copied');
                    $button.find('.copy-text').text(originalText);
                    $button.find('.copy-icon').removeClass('dashicons-yes').addClass('dashicons-clipboard');
                }, 2000);
            } else {
                alert('Failed to copy URL');
            }
        } catch (err) {
            console.error('Copy command failed:', err);
            alert('Failed to copy URL');
        }
        
        document.body.removeChild(textArea);
    }
}

// Inline tile editing functionality
jQuery(document).ready(function($) {
    // Store original values for cancel functionality
    var originalValues = {};
    
    // Edit button click handler
    $(document).on('click', '.tile-edit-btn', function(e) {
        e.preventDefault();
        var $tile = $(this).closest('[data-tile]');
        var tileType = $tile.data('tile');
        
        // Store original values
        storeOriginalValues($tile);
        
        // Switch to edit mode
        $tile.addClass('tile-editing');
        
        // Update header buttons
        $(this).hide();
        $tile.find('.tile-actions').append(
            '<button type="button" class="tile-save-btn" title="Save changes"><i class="fas fa-save"></i></button>' +
            '<button type="button" class="tile-cancel-btn" title="Cancel editing"><i class="fas fa-times"></i></button>'
        );
        
        // Generate edit form based on tile type
        generateEditForm($tile, tileType);
    });
    
    // Save button click handler
    $(document).on('click', '.tile-save-btn', function(e) {
        e.preventDefault();
        var $tile = $(this).closest('[data-tile]');
        var tileType = $tile.data('tile');
        
        // Special handling for tour_package tile - check for tour price changes
        if (tileType === 'tour_package') {
            checkAndHandleTourPriceUpdate($tile, function() {
                // Proceed with normal save after price check
                saveTileData($tile, tileType);
            });
            return;
        }
        
        // Normal save for other tiles
        saveTileData($tile, tileType);
    });
    
    function checkAndHandleTourPriceUpdate($tile, callback) {
        // Get current tour price from the tour & package tile (including vehicles)
        var $tourPriceField = $tile.find('#tour_price');
        var currentTourPriceText = $tourPriceField.val();
        
        // Extract numeric value from formatted price
        var currentTourPrice = 0;
        if (currentTourPriceText && currentTourPriceText !== 'TBD') {
            var priceMatch = currentTourPriceText.match(/[€$]\s?([0-9,]+\.?\d*)/);
            if (priceMatch) {
                currentTourPrice = parseFloat(priceMatch[1].replace(/,/g, ''));
            }
        }
        
        // Get saved tour price from preserved original booking data (NOT from the current booking data which may have been updated)
        var savedTourPrice = parseFloat(window.originalTourPrice) || 0;

        // Check if prices differ significantly (more than 0.01 difference)
        if (Math.abs(currentTourPrice - savedTourPrice) > 0.01 && currentTourPrice > 0) {
            var currency = window.bstBookingData?.tour_currency || 'EUR';
            var currencySymbol = currency === 'USD' ? '$' : '€';
            
            var message = 'The tour price in this section (' + currencySymbol + currentTourPrice.toFixed(2) + ') differs from the saved tour price (' + currencySymbol + savedTourPrice.toFixed(2) + ').\n\n' +
                         'Do you want to update the booking\'s tour price to ' + currencySymbol + currentTourPrice.toFixed(2) + '?\n\n' +
                         'This will recalculate the Total Due and Balance Due in the financials.';
            
            // Create custom confirmation dialog with Yes/No buttons
            showTourPriceConfirmation(message, currentTourPrice, function(userChoice) {
                if (userChoice === 'yes') {
                    // User wants to update the tour price
                    updateBookingTourPrice(currentTourPrice, function(success) {
                        if (success) {
                            // Update global booking data
                            window.bstBookingData.tour_price = currentTourPrice.toFixed(2);
                            
                            // Update the tour price field in the current form to reflect the new price
                            var currency = window.bstBookingData?.tour_currency || 'EUR';
                            var formatted = bstFormatMoneyTourPriceDisplay(currentTourPrice, currency);
                            $tile.find('#tour_price').val(formatted);
                            
                            // Update financials tile display if visible
                            updateFinancialsDisplayAfterPriceChange(currentTourPrice);
                            
                            showMessage('Tour price updated to ' + formatted, 'success', true);
                        }
                        // Proceed with save regardless of price update result
                        callback();
                    });
                } else {
                    // User doesn't want to update tour price, just save other fields
                    callback();
                }
            });
        } else {
            // No price difference, proceed with normal save
            callback();
        }
    }
    
    function showTourPriceConfirmation(message, newPrice, callback) {
        // Create modal overlay
        var $overlay = $('<div class="tour-price-modal-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;"></div>');
        
        // Create modal content
        var $modal = $('<div class="tour-price-modal" style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-width: 500px; margin: 20px; text-align: center;"></div>');
        
        // Add content
        $modal.html(`
            <div style="margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px 0; color: #0073aa;">Update Tour Price?</h3>
                <p style="margin: 0; line-height: 1.5; white-space: pre-line;">${message}</p>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="tour-price-yes-btn" style="padding: 10px 20px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">Yes</button>
                <button type="button" class="tour-price-no-btn" style="padding: 10px 20px; background: #666; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">No</button>
            </div>
        `);
        
        $overlay.append($modal);
        $('body').append($overlay);
        
        // Add event handlers
        $overlay.find('.tour-price-yes-btn').on('click', function() {
            $overlay.remove();
            callback('yes');
        });
        
        $overlay.find('.tour-price-no-btn').on('click', function() {
            $overlay.remove();
            callback('no');
        });
        
        // Close on overlay click
        $overlay.on('click', function(e) {
            if (e.target === $overlay[0]) {
                $overlay.remove();
                callback('no');
            }
        });
        
        // Close on Escape key
        $(document).on('keydown.tourPriceModal', function(e) {
            if (e.key === 'Escape') {
                $(document).off('keydown.tourPriceModal');
                $overlay.remove();
                callback('no');
            }
        });
    }
    
    function updateBookingTourPrice(newPrice, callback) {
        $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'bst_update_tour_price',
                booking_id: <?php echo $booking->id ?? 0; ?>,
                tour_price: newPrice.toFixed(2),
                nonce: '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Keep baseline in sync with DB so the next save does not compare against the pre-update value.
                    if (response.data && response.data.new_price !== undefined && response.data.new_price !== null) {
                        window.originalTourPrice = parseFloat(response.data.new_price);
                    }
                    // Update tile content with fresh HTML from server
                    if (response.data.tour_package_html) {
                        var $tourPackageTile = $('[data-tile="tour_package"]');
                        $tourPackageTile.find('.tile-view-content').replaceWith($(response.data.tour_package_html));
                    }
                    
                    if (response.data.financials_html) {
                        var $financialsTile = $('[data-tile="financials"]');
                        $financialsTile.find('.tile-view-content').replaceWith($(response.data.financials_html));
                    }
                    
                    callback(true);
                } else {
                    showMessage('Error updating tour price: ' + (response.data?.message || response.data || 'Unknown error'), 'error');
                    callback(false);
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error updating tour price: ' + error, 'error');
                callback(false);
            }
        });
    }
    
    function updateFinancialsDisplayAfterPriceChange(newTourPrice) {
        var $financialsTile = $('[data-tile="financials"]');
        
        if ($financialsTile.length && !$financialsTile.hasClass('tile-editing')) {
            // Update the tour price display in financials view
            var currency = window.bstBookingData?.tour_currency || 'EUR';
            var currencySymbol = currency === 'USD' ? '$' : '€';
            
            // Helper function to format currency with commas
            function formatCurrency(value) {
                if (!value || isNaN(parseFloat(value))) return currencySymbol + '0.00';
                var formatted = parseFloat(value).toFixed(2);
                // Add commas for thousands
                formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                return currencySymbol + formatted;
            }
            
            // Update tour price in pricing matrix
            $financialsTile.find('.pricing-matrix .pricing-value').each(function() {
                var $this = $(this);
                var $label = $this.prev('.pricing-label');
                var labelText = $label.text();
                
                if (labelText.includes('Net Tour Price:')) {
                    var couponAmount = parseFloat(window.bstBookingData?.coupon_amount) || 0;
                    var netPrice = newTourPrice - couponAmount;
                    var newText = formatCurrency(netPrice);
                    $this.text(newText);
                } else if (labelText.includes('Tour Price:')) {
                    var newText = formatCurrency(newTourPrice);
                    $this.text(newText);
                } else if (labelText.includes('Total Due:')) {
                    var couponAmount = parseFloat(window.bstBookingData?.coupon_amount) || 0;
                    var additionalCharge = parseFloat(window.bstBookingData?.additional_charge) || 0;
                    var netPrice = newTourPrice - couponAmount;
                    var totalDue = netPrice + additionalCharge;
                    var newText = formatCurrency(totalDue);
                    $this.text(newText);
                } else if (labelText.includes('Balance Due:')) {
                    var couponAmount = parseFloat(window.bstBookingData?.coupon_amount) || 0;
                    var additionalCharge = parseFloat(window.bstBookingData?.additional_charge) || 0;
                    var netPrice = newTourPrice - couponAmount;
                    var totalDue = netPrice + additionalCharge;
                    
                    // Calculate total paid
                    var totalPaid = 0;
                    ['deposit_payment', 'balance_payment', 'additional_payment', 'refund_payment'].forEach(function(type) {
                        var amount = parseFloat(window.bstBookingData[type + '_amount']) || 0;
                        if (type === 'refund_payment') {
                            totalPaid -= amount;
                        } else {
                            totalPaid += amount;
                        }
                    });
                    
                    var balanceDue = totalDue - totalPaid;
                    var newText = formatCurrency(balanceDue);
                    $this.text(newText);
                }
            });
        }
    }
    
    // Function to update delete button state based on total payments
    function updateDeleteButtonState() {
        // Calculate current total paid from payment tiles
        var totalPaid = 0;
        ['deposit_payment', 'balance_payment', 'additional_payment', 'refund_payment'].forEach(function(type) {
            var amount = parseFloat($('[data-tile="payment"] input[name="' + type + '_amount"]').val()) || 0;
            if (type === 'refund_payment') {
                totalPaid -= amount;
            } else {
                totalPaid += amount;
            }
        });
        
        var $deleteButton = $('#delete-booking-btn');
        var $deleteMessage = $deleteButton.closest('div').find('p');
        
        if (totalPaid > 0) {
            // Disable button if payments exist
            $deleteButton.prop('disabled', true);
            $deleteButton.attr('title', 'Cannot delete booking with payments. Total paid: €' + totalPaid.toFixed(2));
            $deleteMessage.html('Booking cannot be deleted - has payments totaling €' + totalPaid.toFixed(2));
            $deleteMessage.css('color', '#d63638');
        } else {
            // Enable button if no payments
            $deleteButton.prop('disabled', false);
            $deleteButton.attr('title', 'Delete this booking');
            $deleteMessage.html('<em>Warning: This action cannot be undone</em>');
            $deleteMessage.css('color', '#666');
        }
    }
    
    function saveTileData($tile, tileType) {
        // Collect form data
        var formData = collectFormData($tile, tileType);
        
        // Add booking ID and action
        formData.action = 'bst_update_tile';
        formData.booking_id = <?php echo $booking->id ?? 0; ?>;
        formData.tile_type = tileType;
        formData.nonce = '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>';
        
        // Show loading state
        $tile.find('.tile-save-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        // Submit via AJAX
        $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Update the booking object with actual database values from the updated booking
                    if (response.data && response.data.updated_booking) {
                        // Use the updated booking from the database (has proper nulls, not empty strings)
                        Object.assign(window.bstBookingData, response.data.updated_booking);
                        var ub = response.data.updated_booking;
                        if (ub.tour_price !== undefined && ub.tour_price !== null && ub.tour_price !== '') {
                            window.originalTourPrice = parseFloat(ub.tour_price);
                        }
                    } else if (response.data && response.data.form_data) {
                        // Fallback to form data if updated_booking not available (for backwards compatibility)
                        Object.assign(window.bstBookingData, response.data.form_data);
                        var fd = response.data.form_data;
                        if (fd.tour_price !== undefined && fd.tour_price !== null && fd.tour_price !== '') {
                            window.originalTourPrice = parseFloat(fd.tour_price);
                        }
                    }
                    
                    // Update view content with server-rendered HTML (if available)
                    if (response.data && response.data.html) {
                        $tile.find('.tile-view-content').html(response.data.html);
                    } else {
                        console.error('No server-rendered HTML received for tile type:', tileType);
                        alert('Error: Server did not return rendered HTML for ' + tileType + ' tile. Please refresh the page.');
                    }
                    
                    // If status changed, update the administrative tile with server-rendered HTML
                    if (response.data && response.data.status_changed) {
                        if (response.data.administrative_html) {
                            $('[data-tile="administrative"]').find('.tile-view-content').html(response.data.administrative_html);
                        }
                        showMessage(getTileName(tileType) + ' updated. Status changed to: ' + response.data.new_status, 'success', true);
                    } else {
                        // Allow multiple messages for tour_package tiles since they can update prices
                        var allowMultiple = (tileType === 'tour_package');
                        showMessage(getTileName(tileType) + ' updated successfully', 'success', allowMultiple);
                    }
                    
                    // Handle tour_package changes that affect guest2 visibility
                    if (tileType === 'tour_package' && response.data && typeof response.data.show_guest2 !== 'undefined') {
                        var $guest2Tile = $('[data-tile="guest2"]');
                        
                        if (response.data.show_guest2) {
                            // Show guest2 tile if package_people = 2
                            $guest2Tile.show();
                            // Update its content with fresh HTML from server
                            if (response.data.guest2_html) {
                                $guest2Tile.find('.tile-view-content').replaceWith($(response.data.guest2_html));
                            }
                        } else {
                            // Hide guest2 tile if package_people != 2
                            $guest2Tile.hide();
                        }
                    }
                    
                    // Exit edit mode
                    exitEditMode($tile);
                    
                    // Update delete button state if this was a payment-related tile
                    if (tileType === 'payment') {
                        updateDeleteButtonState();
                    }
                } else {
                    // Extract the actual error message from the response
                    var errorMessage = 'Unknown error';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data.message) {
                            errorMessage = response.data.message;
                        } else {
                            errorMessage = JSON.stringify(response.data);
                        }
                    }
                    console.error('Server returned error:', response);
                    showMessage('Error updating section: ' + errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr, status, error);
                showMessage('Error updating section: ' + error, 'error');
            },
            complete: function() {
                $tile.find('.tile-save-btn').prop('disabled', false).html('<i class="fas fa-save"></i>');
            }
        });
    }
    
    // Cancel button click handler
    $(document).on('click', '.tile-cancel-btn', function(e) {
        e.preventDefault();
        var $tile = $(this).closest('[data-tile]');
        exitEditMode($tile);
    });
    
    // Handle how_heard field changes to show/hide "Other" field
    $(document).on('change', 'select[name="how_heard"]', function() {
        var $otherGroup = $('#how_heard_other_group');
        var selectedValue = $(this).val();
        if (selectedValue === 'Other' || selectedValue === 'Referred by a previous Blue Strada Guest') {
            $otherGroup.show();
        } else {
            $otherGroup.hide();
        }
    });
    
    // Real-time calculations for financials (pending note is view-only on the tile, not in edit mode)
    $(document).on('input change', '[data-tile="financials"] input[type="number"], [data-tile="financials"] select[name="tour_currency"], [data-tile="financials"] select[name$="_payment_status"]', function() {
        updateFinancialsCalculations($(this).closest('[data-tile="financials"]'));
    });
    
    // Customer lookup button click handler
    $(document).on('click', '#customer_lookup_btn', function(e) {
        e.preventDefault();
        var customerId = $('#customer_id').val();
        
        if (!customerId || customerId.trim() === '') {
            showMessage('Please enter a Customer ID to lookup', 'error');
            return;
        }
        
        lookupCustomer(customerId);
    });
    
    function updateFinancialsCalculations($tile) {
        if (!$tile.hasClass('tile-editing')) return;
        
        var $editForm = $tile.find('.tile-edit-form');
        
        // Get input values
        var tourPrice = parseFloat($editForm.find('input[name="tour_price"]').val()) || 0;
        var couponAmount = parseFloat($editForm.find('input[name="coupon_amount"]').val()) || 0;
        var additionalCharge = parseFloat($editForm.find('input[name="additional_charge"]').val()) || 0;
        
        // Payment amounts
        var depositAmount = parseFloat($editForm.find('input[name="deposit_payment_amount"]').val()) || 0;
        var balanceAmount = parseFloat($editForm.find('input[name="balance_payment_amount"]').val()) || 0;
        var additionalAmount = parseFloat($editForm.find('input[name="additional_payment_amount"]').val()) || 0;
        var refundAmount = parseFloat($editForm.find('input[name="refund_payment_amount"]').val()) || 0;
        
        // Payment discounts
        var depositDiscount = parseFloat($editForm.find('input[name="deposit_payment_discount"]').val()) || 0;
        var balanceDiscount = parseFloat($editForm.find('input[name="balance_payment_discount"]').val()) || 0;
        var additionalDiscount = parseFloat($editForm.find('input[name="additional_payment_discount"]').val()) || 0;
        var totalDiscount = depositDiscount + balanceDiscount + additionalDiscount;

        // Get currency
        var currency = $editForm.find('select[name="tour_currency"]').val() || 'EUR';
        
        // Calculate derived values
        var netTourPrice = tourPrice - couponAmount;
        var totalDue = netTourPrice + additionalCharge;
        var totalPaid = depositAmount + balanceAmount + additionalAmount - refundAmount;
        var balanceDue = totalDue - totalPaid - totalDiscount;
        
        // Update calculated field displays
        $editForm.find('.pricing-matrix .pricing-value').each(function() {
            var $this = $(this);
            var $label = $this.prev('.pricing-label');
            var labelText = $label.text();
            
            if (labelText.includes('Net Tour Price:')) {
                $this.find('input[disabled]').val(netTourPrice.toFixed(2));
            } else if (labelText.includes('Total Due:')) {
                $this.find('input[disabled]').val(totalDue.toFixed(2));
            } else if (labelText.includes('Total Paid:')) {
                $this.find('input[disabled]').val(totalPaid.toFixed(2));
            } else if (labelText.includes('Payment Discount:')) {
                $this.find('input[disabled]').val(totalDiscount.toFixed(2));
            } else if (labelText.includes('Balance Due:')) {
                $this.find('input[disabled]').val(balanceDue.toFixed(2));
            }
        });
    }
    
    function lookupCustomer(customerId) {
        // Show loading state
        var $lookupBtn = $('#customer_lookup_btn');
        var $customerDisplay = $('#customer_info_display');
        
        $lookupBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Looking up...');
        
        $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'bst_lookup_customer',
                customer_id: customerId,
                nonce: '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    var customer = response.data;
                    var customerHtml = '';
                    
                    if (customer.id) {
                        customerHtml = '<strong>Customer Found:</strong><br>' +
                                     '<a href="' + window.location.origin + '/wp-admin/admin.php?page=bst-plugin-customer-form&action=edit&id=' + customer.id + '" target="_blank" rel="noopener noreferrer">' +
                                     '#' + customer.id + ' - ' + customer.first_name + ' ' + customer.last_name + ' (' + customer.email + ')' +
                                     '</a>';
                    } else {
                        customerHtml = '<strong>Customer not found</strong><br>' +
                                     '<em>No customer exists with ID ' + customerId + '</em>';
                    }
                    
                    $('#customer_details').html(customerHtml);
                    $customerDisplay.show();
                } else {
                    showMessage('Customer not found with ID: ' + customerId, 'error');
                    $customerDisplay.hide();
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error looking up customer: ' + error, 'error');
                $customerDisplay.hide();
            },
            complete: function() {
                $lookupBtn.prop('disabled', false).html('<i class="fas fa-search"></i> Lookup');
            }
        });
    }
    
    // Helper functions
    function storeOriginalValues($tile) {
        var tileType = $tile.data('tile');
        originalValues[tileType] = {};
        
        // Store current display values - implementation varies by tile type
        // This will be expanded as we implement each tile type
    }
    
    function generateEditForm($tile, tileType) {
        var $editForm = $tile.find('.tile-edit-form');
        var formHtml = '';
        
        switch(tileType) {
            case 'notes':
                formHtml = generateNotesEditForm();
                break;
            case 'guest1':
                formHtml = generateGuestEditForm(1);
                break;
            case 'guest2':
                formHtml = generateGuestEditForm(2);
                break;
            case 'customer':
                formHtml = generateCustomerEditForm();
                break;
            case 'marketing':
                formHtml = generateMarketingEditForm();
                break;
            case 'gravity_forms':
                formHtml = generateGravityFormsEditForm();
                break;
            case 'administrative':
                formHtml = generateAdministrativeEditForm();
                break;
            case 'tour_package':
                formHtml = generateTourPackageEditForm();
                break;
            case 'financials':
                formHtml = generateFinancialsEditForm();
                break;
            case 'invoicing':
                formHtml = generateInvoicingEditForm();
                break;
            case 'system':
                formHtml = generateSystemEditForm();
                break;
            default:
                formHtml = '<p>Edit form not implemented for this section yet.</p>';
        }
        
        $editForm.html(formHtml);
        
        // Initialize dependencies for tour package form
        if (tileType === 'tour_package') {
            setTimeout(function() {
                initializeTourPackageDependencies($tile);
                
                // Add event handler for extension checkbox to recalculate tour price
                $tile.find('#tour_extension_added').on('change', function() {
                    calculateCompleteTourPrice($tile);
                });
            }, 10);
        }
        
        // Trigger real-time calculations for financials
        if (tileType === 'financials') {
            setTimeout(function() {
                updateFinancialsCalculations($tile);
            }, 10);
        }
    }
    
    function generateNotesEditForm() {
        var currentNotes = <?php echo json_encode($booking->notes ?? ''); ?>;
        return '<textarea name="notes" rows="8" style="width: 100%; height: 100%; min-height: 150px; border: 1px solid #ddd; padding: 10px; font-family: inherit; font-size: 14px; resize: vertical;">' + currentNotes + '</textarea>';
    }
    
    // JavaScript function to format phone numbers in international style
    function formatPhoneInternational(phone) {
        if (!phone) {
            return '';
        }
        
        // Remove all non-numeric characters except the leading +
        var hasPlus = (phone.indexOf('+') === 0);
        var cleaned = phone.replace(/[^0-9]/g, '');
        
        // If it already has proper formatting (contains spaces or dashes), return as is
        if (hasPlus && (phone.indexOf(' ') !== -1 || phone.indexOf('-') !== -1 || phone.indexOf('(') !== -1)) {
            return phone;
        }
        
        var length = cleaned.length;
        
        if (length == 10) {
            // US/Canada format: +1 (XXX) XXX-XXXX
            return '+1 (' + cleaned.substr(0, 3) + ') ' + cleaned.substr(3, 3) + '-' + cleaned.substr(6);
        } else if (length == 11 && cleaned.substr(0, 1) == '1') {
            // US/Canada with leading 1: +1 (XXX) XXX-XXXX
            return '+1 (' + cleaned.substr(1, 3) + ') ' + cleaned.substr(4, 3) + '-' + cleaned.substr(7);
        } else if (length == 12 && cleaned.substr(0, 2) == '44') {
            // UK format
            var area = cleaned.substr(2, 2);
            if (area == '20') {
                return '+44 20 ' + cleaned.substr(4, 4) + ' ' + cleaned.substr(8);
            } else {
                return '+44 ' + cleaned.substr(2, 4) + ' ' + cleaned.substr(6, 3) + ' ' + cleaned.substr(9);
            }
        } else if (length == 12 && cleaned.substr(0, 2) == '33') {
            // France format: +33 X XX XX XX XX
            return '+33 ' + cleaned.substr(2, 1) + ' ' + cleaned.substr(3, 2) + ' ' + cleaned.substr(5, 2) + ' ' + cleaned.substr(7, 2) + ' ' + cleaned.substr(9);
        } else if (length == 12 && cleaned.substr(0, 2) == '49') {
            // Germany format: +49 XXX XXXXXXX
            return '+49 ' + cleaned.substr(2, 3) + ' ' + cleaned.substr(5);
        } else if (length >= 10 && length <= 15) {
            // Generic international format
            var country, remaining;
            if (length <= 11) {
                country = cleaned.substr(0, 1);
                remaining = cleaned.substr(1);
            } else if (length <= 13) {
                country = cleaned.substr(0, 2);
                remaining = cleaned.substr(2);
            } else {
                country = cleaned.substr(0, 3);
                remaining = cleaned.substr(3);
            }
            
            var formatted = '+' + country + ' ';
            var remainingLength = remaining.length;
            
            if (remainingLength >= 9) {
                formatted += remaining.substr(0, 3) + ' ' + remaining.substr(3, 3) + ' ' + remaining.substr(6);
            } else if (remainingLength >= 6) {
                formatted += remaining.substr(0, 3) + ' ' + remaining.substr(3);
            } else {
                formatted += remaining;
            }
            
            return formatted;
        }
        
        // If we can't determine format, just add + prefix if it looks like international
        if (length > 7) {
            return '+' + cleaned;
        }
        
        return phone;
    }
    
    function generateGuestEditForm(guestNum) {
        var prefix = 'guest' + guestNum + '_';
        var booking = window.bstBookingData || {};
        
        // Get current values from the booking object
        var firstName = booking[prefix + 'first_name'] || '';
        var lastName = booking[prefix + 'last_name'] || '';
        var nickname = booking[prefix + 'nickname'] || '';
        var age = booking[prefix + 'age'] || '';
        var email = booking[prefix + 'email'] || '';
        var phone = booking[prefix + 'phone'] || '';
        var addressLine1 = booking[prefix + 'address_line1'] || '';
        var addressLine2 = booking[prefix + 'address_line2'] || '';
        var city = booking[prefix + 'city'] || '';
        var stateProvince = booking[prefix + 'state_province'] || '';
        var postalCode = booking[prefix + 'postal_code'] || '';
        var country = booking[prefix + 'country'] || '';
        var shirtSize = booking[prefix + 'shirt_size'] || '';
        var drivingStatus = booking[prefix + 'driving_status'] || '';
        var dietaryRestrictions = booking[prefix + 'dietary_restrictions'] || '';
        var medicalInsurance = booking[prefix + 'medical_insurance'] || '';
        var emergencyContactName = booking[prefix + 'emergency_contact_name'] || '';
        var emergencyContactPhone = booking[prefix + 'emergency_contact_phone'] || '';
        var emergencyContactEmail = booking[prefix + 'emergency_contact_email'] || '';
        var travelDetails = booking[prefix + 'travel_details'] || '';
        
        var form = `
            <div class="edit-form-section">
                <h4>Basic Information</h4>
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="${prefix}first_name">First Name</label>
                        <input type="text" id="${prefix}first_name" name="${prefix}first_name" value="${firstName}" />
                    </div>
                    <div class="edit-form-field">
                        <label for="${prefix}last_name">Last Name</label>
                        <input type="text" id="${prefix}last_name" name="${prefix}last_name" value="${lastName}" />
                    </div>
                    <div class="edit-form-field">
                        <label for="${prefix}nickname">Nickname</label>
                        <input type="text" id="${prefix}nickname" name="${prefix}nickname" value="${nickname}" />
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="${prefix}email">Email</label>
                        <input type="email" id="${prefix}email" name="${prefix}email" value="${email}" />
                    </div>
                    <div class="edit-form-field">
                        <label for="${prefix}phone">Phone</label>
                        <input type="tel" id="${prefix}phone" name="${prefix}phone" value="${phone}" />
                    </div>
                    <div class="edit-form-field">
                        <label for="${prefix}age">Age</label>
                        <input type="number" id="${prefix}age" name="${prefix}age" min="1" max="120" value="${age}" />
                    </div>
                </div>
            </div>
            
            <div class="edit-form-section">
                <h4>Address</h4>
                <div class="edit-form-row">
                    <div class="edit-form-field full-width">
                        <label for="${prefix}address_line1">Address Line 1</label>
                        <input type="text" id="${prefix}address_line1" name="${prefix}address_line1" value="${addressLine1}" />
                    </div>
                </div>
                <div class="edit-form-row">
                    <div class="edit-form-field full-width">
                        <label for="${prefix}address_line2">Address Line 2</label>
                        <input type="text" id="${prefix}address_line2" name="${prefix}address_line2" value="${addressLine2}" />
                    </div>
                </div>
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="${prefix}city">City</label>
                        <input type="text" id="${prefix}city" name="${prefix}city" value="${city}" />
                    </div>
                    <div class="edit-form-field">
                        <label for="${prefix}state_province">State/Province</label>
                        <input type="text" id="${prefix}state_province" name="${prefix}state_province" value="${stateProvince}" />
                    </div>
                </div>
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="${prefix}postal_code">Postal Code</label>
                        <input type="text" id="${prefix}postal_code" name="${prefix}postal_code" value="${postalCode}" />
                    </div>
                    <div class="edit-form-field">
                        <label for="${prefix}country">Country</label>
                        <input type="text" id="${prefix}country" name="${prefix}country" value="${country}" />
                    </div>
                </div>
            </div>
            
            <div class="edit-form-section">
                <h4>Additional Details</h4>
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="${prefix}shirt_size">Shirt Size</label>
                        <select id="${prefix}shirt_size" name="${prefix}shirt_size">
                            ${generateShirtSizeOptions(shirtSize)}
                        </select>
                    </div>
                    <div class="edit-form-field">
                        <label for="${prefix}driving_status">Driving Status</label>
                        <select id="${prefix}driving_status" name="${prefix}driving_status">
                            <option value="">Select Status</option>
                            <option value="Driver" ${drivingStatus === 'Driver' ? 'selected' : ''}>Driver</option>
                            <option value="Passenger" ${drivingStatus === 'Passenger' ? 'selected' : ''}>Passenger</option>
                            <option value="Either" ${drivingStatus === 'Either' ? 'selected' : ''}>Either</option>
                        </select>
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field full-width">
                        <label for="${prefix}dietary_restrictions">Food Preferences, Allergies And Intolerances</label>
                        <textarea id="${prefix}dietary_restrictions" name="${prefix}dietary_restrictions" rows="3">${dietaryRestrictions}</textarea>
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field full-width">
                        <label for="${prefix}medical_insurance">Medical/Insurance Information</label>
                        <textarea id="${prefix}medical_insurance" name="${prefix}medical_insurance" rows="3">${medicalInsurance}</textarea>
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field full-width">
                        <label for="${prefix}travel_details">Travel Details</label>
                        <textarea id="${prefix}travel_details" name="${prefix}travel_details" rows="3" placeholder="e.g., Arr: 2025-08-20 01:00 FCO UA1234 | Dep: 2025-08-29 10:00 FCO UA5678">${travelDetails}</textarea>
                    </div>
                </div>
            </div>
            
            <div class="edit-form-section">
                <h4>Emergency Contact</h4>
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="${prefix}emergency_contact_name">Name</label>
                        <input type="text" id="${prefix}emergency_contact_name" name="${prefix}emergency_contact_name" value="${emergencyContactName}" />
                    </div>
                    <div class="edit-form-field">
                        <label for="${prefix}emergency_contact_phone">Phone</label>
                        <input type="tel" id="${prefix}emergency_contact_phone" name="${prefix}emergency_contact_phone" value="${emergencyContactPhone}" />
                    </div>
                    <div class="edit-form-field">
                        <label for="${prefix}emergency_contact_email">Email</label>
                        <input type="email" id="${prefix}emergency_contact_email" name="${prefix}emergency_contact_email" value="${emergencyContactEmail}" />
                    </div>
                </div>
            </div>
        `;
        
        return form;
    }
    
    function generateCustomerEditForm() {
        var booking = window.bstBookingData || {};
        
        return `
            <div class="edit-form-section">
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="customer_id">Customer ID:</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="number" id="customer_id" name="customer_id" value="${booking.customer_id || ''}" 
                                   style="width: 100px;" placeholder="Enter ID">
                            <button type="button" id="customer_lookup_btn" class="button button-secondary" 
                                    style="padding: 6px 12px; font-size: 13px;">
                                <i class="fas fa-search"></i> Lookup
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="customer_info_display" style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px; display: none;">
                    <div id="customer_details"></div>
                </div>
            </div>
        `;
    }
    
    function generateMarketingEditForm() {
        var booking = window.bstBookingData || {};
        
        return `
            <div class="edit-form-section">
                <h4>Marketing Information</h4>
                
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="how_heard">How did you hear about us?</label>
                        <select id="how_heard" name="how_heard">
                            <option value="">Select...</option>
                            <option value="Referred by Bill Kniegge" ${booking.how_heard === 'Referred by Bill Kniegge' ? 'selected' : ''}>Referred by Bill Kniegge</option>
                            <option value="Referred by Claudio Angeletti" ${booking.how_heard === 'Referred by Claudio Angeletti' ? 'selected' : ''}>Referred by Claudio Angeletti</option>
                            <option value="Referred by Wayne Wilson" ${booking.how_heard === 'Referred by Wayne Wilson' ? 'selected' : ''}>Referred by Wayne Wilson</option>
                            <option value="Went on a previous Blue Strada tour" ${booking.how_heard === 'Went on a previous Blue Strada tour' ? 'selected' : ''}>Went on a previous Blue Strada tour</option>
                            <option value="Referred by a previous Blue Strada Guest" ${booking.how_heard === 'Referred by a previous Blue Strada Guest' ? 'selected' : ''}>Referred by a previous Blue Strada Guest</option>
                            <option value="Blue Strada Tours Email List" ${booking.how_heard === 'Blue Strada Tours Email List' ? 'selected' : ''}>Blue Strada Tours Email List</option>
                            <option value="Facebook" ${booking.how_heard === 'Facebook' ? 'selected' : ''}>Facebook</option>
                            <option value="Instagram" ${booking.how_heard === 'Instagram' ? 'selected' : ''}>Instagram</option>
                            <option value="LinkedIn" ${booking.how_heard === 'LinkedIn' ? 'selected' : ''}>LinkedIn</option>
                            <option value="Messenger" ${booking.how_heard === 'Messenger' ? 'selected' : ''}>Messenger</option>
                            <option value="Miatas at the Gap" ${booking.how_heard === 'Miatas at the Gap' ? 'selected' : ''}>Miatas at the Gap</option>
                            <option value="Motor Club" ${booking.how_heard === 'Motor Club' ? 'selected' : ''}>Motor Club</option>
                            <option value="Search engine" ${booking.how_heard === 'Search engine' ? 'selected' : ''}>Search engine</option>
                            <option value="TripAdvisor" ${booking.how_heard === 'TripAdvisor' ? 'selected' : ''}>TripAdvisor</option>
                            <option value="Twitter/X" ${booking.how_heard === 'Twitter/X' ? 'selected' : ''}>Twitter/X</option>
                            <option value="WhatsApp" ${booking.how_heard === 'WhatsApp' ? 'selected' : ''}>WhatsApp</option>
                            <option value="YouTube" ${booking.how_heard === 'YouTube' ? 'selected' : ''}>YouTube</option>
                            <option value="Other" ${booking.how_heard === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div class="edit-form-field" id="how_heard_other_group" style="display: ${(booking.how_heard === 'Other' || booking.how_heard === 'Referred by a previous Blue Strada Guest') ? 'flex' : 'none'};">
                        <label for="how_heard_other">Please specify:</label>
                        <input type="text" id="how_heard_other" name="how_heard_other" value="${booking.how_heard_other || ''}" placeholder="Additional details">
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="source">Source:</label>
                        <input type="text" id="source" name="source" value="${booking.source || ''}">
                    </div>
                    <div class="edit-form-field">
                        <label for="referrer">Referrer:</label>
                        <input type="text" id="referrer" name="referrer" value="${booking.referrer || ''}">
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="motor_club">Motor Club:</label>
                        <input type="text" id="motor_club" name="motor_club" value="${booking.motor_club || ''}">
                    </div>
                </div>
            </div>
        `;
    }
    
    function generateGravityFormsEditForm() {
        var booking = window.bstBookingData || {};
        
        return `
            <div class="edit-form-section">
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="booking_form_id">Booking ID:</label>
                        <input type="text" id="booking_form_id" name="booking_form_id" value="${booking.booking_entry_id || ''}" maxlength="4" pattern="[0-9]*">
                    </div>
                    <div class="edit-form-field">
                        <label for="finalization_form_id">Finalization ID:</label>
                        <input type="text" id="finalization_form_id" name="finalization_form_id" value="${booking.finalization_entry_id || ''}" maxlength="4" pattern="[0-9]*">
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="add_pmt_form_id">Add'l Pmt ID:</label>
                        <input type="text" id="add_pmt_form_id" name="add_pmt_form_id" value="${booking.additional_payment_entry_id || ''}" maxlength="4" pattern="[0-9]*">
                    </div>
                    <div class="edit-form-field">
                        <!-- Empty field to maintain grid alignment -->
                    </div>
                </div>
            </div>
        `;
    }
    
    function generateAdministrativeEditForm() {
        var booking = window.bstBookingData || {};
        
        return `
            <div class="edit-form-section">
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="booking_status">Booking Status:</label>
                        <select id="booking_status" name="booking_status">
                            <option value="">Select Status...</option>
                            <option value="Waiting List" ${booking.booking_status === 'Waiting List' ? 'selected' : ''}>Waiting List</option>
                            <option value="Reserved" ${booking.booking_status === 'Reserved' ? 'selected' : ''}>Reserved</option>
                            <option value="Pending" ${booking.booking_status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Processing" ${booking.booking_status === 'Processing' ? 'selected' : ''}>Processing</option>
                            <option value="Payment Failed" ${booking.booking_status === 'Payment Failed' ? 'selected' : ''}>Payment Failed</option>
                            <option value="Booked" ${booking.booking_status === 'Booked' ? 'selected' : ''}>Booked</option>
                            <option value="Finalized" ${booking.booking_status === 'Finalized' ? 'selected' : ''}>Finalized</option>
                            <option value="Completed" ${booking.booking_status === 'Completed' ? 'selected' : ''}>Completed</option>
                            <option value="Transfer" ${booking.booking_status === 'Transfer' ? 'selected' : ''}>Transfer</option>
                            <option value="Cancelled" ${booking.booking_status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                        </select>
                    </div>
                    <div class="edit-form-field">
                        <label for="booking_method">Booking Method:</label>
                        <select id="booking_method" name="booking_method">
                            <option value="">Select Method...</option>
                            <option value="Offline" ${booking.booking_method === 'Offline' ? 'selected' : ''}>Offline</option>
                            <option value="Web" ${booking.booking_method === 'Web' ? 'selected' : ''}>Web</option>
                        </select>
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="booking_commission_percent">Commission (%):</label>
                        <select id="booking_commission_percent" name="booking_commission_percent">
                            <option value="0" ${(booking.booking_commission_percent || 0) * 100 == 0 ? 'selected' : ''}>0</option>
                            <option value="2" ${(booking.booking_commission_percent || 0) * 100 == 2 ? 'selected' : ''}>2</option>
                            <option value="5" ${(booking.booking_commission_percent || 0) * 100 == 5 ? 'selected' : ''}>5</option>
                        </select>
                    </div>
                    <div class="edit-form-field">
                        <label for="booking_commission_reason">Commission Reason:</label>
                        <input type="text" id="booking_commission_reason" name="booking_commission_reason" value="${booking.booking_commission_reason || ''}">
                    </div>
                </div>
            </div>
        `;
    }
    
    function generateFinancialsEditForm() {
        var booking = window.bstBookingData || {};
        
        // Calculate derived values for display
        var tourPrice = parseFloat(booking.tour_price) || 0;
        var couponAmount = parseFloat(booking.coupon_amount) || 0;
        var additionalCharge = parseFloat(booking.additional_charge) || 0;
        var netTourPrice = tourPrice - couponAmount;
        
        // Calculate payments
        var depositAmount = parseFloat(booking.deposit_payment_amount) || 0;
        var balanceAmount = parseFloat(booking.balance_payment_amount) || 0;
        var additionalAmount = parseFloat(booking.additional_payment_amount) || 0;
        var refundAmount = parseFloat(booking.refund_payment_amount) || 0;
        var totalPaid = depositAmount + balanceAmount + additionalAmount - refundAmount;
        
        var totalDue = netTourPrice + additionalCharge;
        var balanceDue = totalDue - totalPaid;
        
        function formatCurrency(value) {
            return (parseFloat(value) || 0).toFixed(2);
        }
        
        // Helper function to format dates properly for date inputs
        function formatDateForInput(dateValue) {
            if (!dateValue) {
                return '';
            }
            
            // Handle various date formats
            let dateStr = dateValue.toString();
            
            // If it's already in YYYY-MM-DD format, return as is
            if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
                return dateStr;
            }
            
            // If it contains time (YYYY-MM-DD HH:MM:SS), extract date part
            if (dateStr.includes(' ')) {
                dateStr = dateStr.split(' ')[0];
                if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
                    return dateStr;
                }
            }
            
            // Try to parse as Date and format
            try {
                let date = new Date(dateValue);
                if (!isNaN(date.getTime())) {
                    let year = date.getFullYear();
                    let month = String(date.getMonth() + 1).padStart(2, '0');
                    let day = String(date.getDate()).padStart(2, '0');
                    let formatted = `${year}-${month}-${day}`;
                    return formatted;
                }
            } catch (e) {
                // Could not parse date
            }
            
            return '';
        }
        
        return `
            <div class="edit-form-section">
                <h4>Pricing Information</h4>
                
                <div class="pricing-matrix">
                    <div class="pricing-label">Currency:</div>
                    <div class="pricing-value">
                        <select name="tour_currency" style="width: 120px;">
                            <option value="EUR" ${(booking.tour_currency || 'EUR') === 'EUR' ? 'selected' : ''}>EUR</option>
                            <option value="USD" ${booking.tour_currency === 'USD' ? 'selected' : ''}>USD</option>
                        </select>
                    </div>
                    
                    <div class="pricing-label">Tour Price:</div>
                    <div class="pricing-value" style="display: flex; align-items: center; gap: 8px;">
                        <input type="number" step="0.01" name="tour_price" value="${booking.tour_price || ''}" 
                               id="tour_price_input" style="width: 120px; text-align: right;" 
                               ${booking.tour_price_override ? '' : 'disabled'}>
                        <label style="font-size: 12px; display: flex; align-items: center; gap: 4px;">
                            <input type="checkbox" name="tour_price_override" id="tour_price_override" 
                                   ${booking.tour_price_override ? 'checked' : ''} 
                                   onchange="document.getElementById('tour_price_input').disabled = !this.checked;">
                            Override
                        </label>
                    </div>
                    
                    <div class="pricing-label">Coupon Code:</div>
                    <div class="pricing-value">
                        <input type="text" name="coupon_code" value="${booking.coupon_code || ''}" style="width: 120px;">
                    </div>
                    
                    <div class="pricing-label">Coupon Amount:</div>
                    <div class="pricing-value">
                        <input type="number" step="0.01" name="coupon_amount" value="${booking.coupon_amount || ''}" style="width: 120px; text-align: right;">
                    </div>
                    
                    <div class="pricing-label">Net Tour Price:</div>
                    <div class="pricing-value">
                        <input type="number" step="0.01" value="${formatCurrency(netTourPrice)}" disabled style="width: 120px; text-align: right; background: #f5f5f5;">
                    </div>
                    
                    <div class="pricing-label">Additional Charge:</div>
                    <div class="pricing-value">
                        <input type="number" step="0.01" name="additional_charge" value="${booking.additional_charge || ''}" style="width: 120px; text-align: right;">
                    </div>
                    
                    <div class="pricing-label">Total Due:</div>
                    <div class="pricing-value">
                        <input type="number" step="0.01" value="${formatCurrency(totalDue)}" disabled style="width: 120px; text-align: right; background: #f5f5f5;">
                    </div>
                    
                    <div class="pricing-label">Total Paid:</div>
                    <div class="pricing-value">
                        <input type="number" step="0.01" value="${formatCurrency(totalPaid)}" disabled style="width: 120px; text-align: right; background: #f5f5f5;">
                    </div>
                    
                    <div class="pricing-label">Payment Discount:</div>
                    <div class="pricing-value">
                        <input type="number" step="0.01" name="payment_discount_amount" value="${formatCurrency(booking.payment_discount_amount || 0)}" disabled style="width: 120px; text-align: right; background: #f5f5f5;">
                    </div>
                    
                    <div class="pricing-label">Balance Due:</div>
                    <div class="pricing-value">
                        <input type="number" step="0.01" value="${formatCurrency(balanceDue)}" disabled style="width: 120px; text-align: right; background: #f5f5f5;">
                    </div>
                </div>
            </div>
            
            <div class="edit-form-section">
                <h4>Payment Information</h4>
                
                <div class="bst-payment-matrix bst-financials-payment-scroll" style="width:100%;">
                <table class="bst-financials-payment-table bst-financials-payment-table--edit" style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background: #f0f0f0;">
                            <th style="padding: 4px; border: 1px solid #ddd; font-weight: 600;">Type</th>
                            <th style="padding: 4px; border: 1px solid #ddd; font-weight: 600;">Method</th>
                            <th style="padding: 4px; border: 1px solid #ddd; font-weight: 600;">Amount</th>
                            <th style="padding: 4px; border: 1px solid #ddd; font-weight: 600;">Status</th>
                            <th style="padding: 4px; border: 1px solid #ddd; font-weight: 600;">Discount</th>
                            <th style="padding: 4px; border: 1px solid #ddd; font-weight: 600;">Date</th>
                            <th class="payment-invoice-col" style="padding: 4px; border: 1px solid #ddd; font-weight: 600;">Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 4px; border: 1px solid #ddd;"><strong>Deposit</strong></td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <select name="deposit_payment_method" style="width: 100%;">
                                    <option value="">-</option>
                                    <option value="Bank Wire" ${booking.deposit_payment_method === 'Bank Wire' ? 'selected' : ''}>Bank Transfer</option>
                                    <option value="Cash" ${booking.deposit_payment_method === 'Cash' ? 'selected' : ''}>Cash</option>
                                    <option value="Check" ${booking.deposit_payment_method === 'Check' ? 'selected' : ''}>Check</option>
                                    <option value="Credit Card" ${booking.deposit_payment_method === 'Credit Card' ? 'selected' : ''}>Credit Card</option>
                                    <option value="Transferred" ${booking.deposit_payment_method === 'Transferred' ? 'selected' : ''}>Transferred</option>
                                </select>
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="number" step="0.01" name="deposit_payment_amount" value="${booking.deposit_payment_amount || ''}" style="width: 100%; text-align: right;">
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <select name="deposit_payment_status" style="width: 100%; max-width: 100%;">
                                    <option value="">-</option>
                                    <option value="Paid" ${booking.deposit_payment_status === 'Paid' ? 'selected' : ''}>Paid</option>
                                    <option value="Pending" ${booking.deposit_payment_status === 'Pending' ? 'selected' : ''}>Pending</option>
                                    <option value="Failed" ${booking.deposit_payment_status === 'Failed' ? 'selected' : ''}>Failed</option>
                                    <option value="Processing" ${booking.deposit_payment_status === 'Processing' ? 'selected' : ''}>Processing</option>
                                    <option value="Transferred" ${booking.deposit_payment_status === 'Transferred' ? 'selected' : ''}>Transferred</option>
                                </select>
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="number" step="0.01" name="deposit_payment_discount" value="${booking.deposit_payment_discount || ''}" style="width: 100%; text-align: right;">
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="text" name="deposit_payment_date" value="${formatDateForInput(booking.deposit_payment_date)}" 
                                       pattern="\\d{4}-\\d{2}-\\d{2}" placeholder="YYYY-MM-DD"
                                       style="width: 100%; height: 26px; padding: 2px 8px; border: 1px solid #ccd0d4; border-radius: 4px; font-size: 13px; background-color: #fff;" 
                                       class="bst-date-text">
                            </td>
                            <td class="payment-invoice-col" style="padding: 4px; border: 1px solid #ddd;">
                                <input type="text" name="deposit_commission_invoice" value="${booking.deposit_commission_invoice || ''}" style="width: 100%;">
                            </td>
                        </tr>
                        
                        <tr>
                            <td style="padding: 4px; border: 1px solid #ddd;"><strong>Balance</strong></td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <select name="balance_payment_method" style="width: 100%;">
                                    <option value="">-</option>
                                    <option value="Bank Wire" ${booking.balance_payment_method === 'Bank Wire' ? 'selected' : ''}>Bank Transfer</option>
                                    <option value="Cash" ${booking.balance_payment_method === 'Cash' ? 'selected' : ''}>Cash</option>
                                    <option value="Check" ${booking.balance_payment_method === 'Check' ? 'selected' : ''}>Check</option>
                                    <option value="Credit Card" ${booking.balance_payment_method === 'Credit Card' ? 'selected' : ''}>Credit Card</option>
                                    <option value="Transferred" ${booking.balance_payment_method === 'Transferred' ? 'selected' : ''}>Transferred</option>
                                </select>
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="number" step="0.01" name="balance_payment_amount" value="${booking.balance_payment_amount || ''}" style="width: 100%; text-align: right;">
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <select name="balance_payment_status" style="width: 100%; max-width: 100%;">
                                    <option value="">-</option>
                                    <option value="Paid" ${booking.balance_payment_status === 'Paid' ? 'selected' : ''}>Paid</option>
                                    <option value="Pending" ${booking.balance_payment_status === 'Pending' ? 'selected' : ''}>Pending</option>
                                    <option value="Failed" ${booking.balance_payment_status === 'Failed' ? 'selected' : ''}>Failed</option>
                                    <option value="Processing" ${booking.balance_payment_status === 'Processing' ? 'selected' : ''}>Processing</option>
                                    <option value="Transferred" ${booking.balance_payment_status === 'Transferred' ? 'selected' : ''}>Transferred</option>
                                </select>
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="number" step="0.01" name="balance_payment_discount" value="${booking.balance_payment_discount || ''}" style="width: 100%; text-align: right;">
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="text" name="balance_payment_date" value="${formatDateForInput(booking.balance_payment_date)}" 
                                       pattern="\\d{4}-\\d{2}-\\d{2}" placeholder="YYYY-MM-DD"
                                       style="width: 100%; height: 26px; padding: 2px 8px; border: 1px solid #ccd0d4; border-radius: 4px; font-size: 13px; background-color: #fff;" 
                                       class="bst-date-text">
                            </td>
                            <td class="payment-invoice-col" style="padding: 4px; border: 1px solid #ddd;">
                                <input type="text" name="balance_commission_invoice" value="${booking.balance_commission_invoice || ''}" style="width: 100%;">
                            </td>
                        </tr>
                        
                        <tr>
                            <td style="padding: 4px; border: 1px solid #ddd;"><strong>Add'l</strong></td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <select name="additional_payment_method" style="width: 100%;">
                                    <option value="">-</option>
                                    <option value="Bank Wire" ${booking.additional_payment_method === 'Bank Wire' ? 'selected' : ''}>Bank Transfer</option>
                                    <option value="Cash" ${booking.additional_payment_method === 'Cash' ? 'selected' : ''}>Cash</option>
                                    <option value="Check" ${booking.additional_payment_method === 'Check' ? 'selected' : ''}>Check</option>
                                    <option value="Credit Card" ${booking.additional_payment_method === 'Credit Card' ? 'selected' : ''}>Credit Card</option>
                                </select>
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="number" step="0.01" name="additional_payment_amount" value="${booking.additional_payment_amount || ''}" style="width: 100%; text-align: right;">
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <select name="additional_payment_status" style="width: 100%; max-width: 100%;">
                                    <option value="">-</option>
                                    <option value="Paid" ${booking.additional_payment_status === 'Paid' ? 'selected' : ''}>Paid</option>
                                    <option value="Pending" ${booking.additional_payment_status === 'Pending' ? 'selected' : ''}>Pending</option>
                                    <option value="Failed" ${booking.additional_payment_status === 'Failed' ? 'selected' : ''}>Failed</option>
                                    <option value="Processing" ${booking.additional_payment_status === 'Processing' ? 'selected' : ''}>Processing</option>
                                    <option value="Transferred" ${booking.additional_payment_status === 'Transferred' ? 'selected' : ''}>Transferred</option>
                                </select>
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="number" step="0.01" name="additional_payment_discount" value="${booking.additional_payment_discount || ''}" style="width: 100%; text-align: right;">
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="text" name="additional_payment_date" value="${formatDateForInput(booking.additional_payment_date)}" 
                                       pattern="\\d{4}-\\d{2}-\\d{2}" placeholder="YYYY-MM-DD"
                                       style="width: 100%; height: 26px; padding: 2px 8px; border: 1px solid #ccd0d4; border-radius: 4px; font-size: 13px; background-color: #fff;" 
                                       class="bst-date-text">
                            </td>
                            <td class="payment-invoice-col" style="padding: 4px; border: 1px solid #ddd;">
                                <input type="text" name="additional_payment_commission_invoice" value="${booking.additional_payment_commission_invoice || ''}" style="width: 100%;">
                            </td>
                        </tr>
                        
                        <tr>
                            <td style="padding: 4px; border: 1px solid #ddd;"><strong>Refund</strong></td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <select name="refund_payment_method" style="width: 100%;">
                                    <option value="">-</option>
                                    <option value="Bank Wire" ${booking.refund_payment_method === 'Bank Wire' ? 'selected' : ''}>Bank Transfer</option>
                                    <option value="Cash" ${booking.refund_payment_method === 'Cash' ? 'selected' : ''}>Cash</option>
                                    <option value="Check" ${booking.refund_payment_method === 'Check' ? 'selected' : ''}>Check</option>
                                    <option value="Credit Card" ${booking.refund_payment_method === 'Credit Card' ? 'selected' : ''}>Credit Card</option>
                                </select>
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="number" step="0.01" name="refund_payment_amount" value="${booking.refund_payment_amount || ''}" style="width: 100%; text-align: right;">
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <select name="refund_payment_status" style="width: 100%; max-width: 100%;">
                                    <option value="">-</option>
                                    <option value="Paid" ${booking.refund_payment_status === 'Paid' ? 'selected' : ''}>Paid</option>
                                    <option value="Pending" ${booking.refund_payment_status === 'Pending' ? 'selected' : ''}>Pending</option>
                                    <option value="Failed" ${booking.refund_payment_status === 'Failed' ? 'selected' : ''}>Failed</option>
                                    <option value="Processing" ${booking.refund_payment_status === 'Processing' ? 'selected' : ''}>Processing</option>
                                    <option value="Transferred" ${booking.refund_payment_status === 'Transferred' ? 'selected' : ''}>Transferred</option>
                                </select>
                            </td>
                            <td style="padding: 4px; border: 1px solid #ddd;">-</td>
                            <td style="padding: 4px; border: 1px solid #ddd;">
                                <input type="text" name="refund_payment_date" value="${formatDateForInput(booking.refund_payment_date)}" 
                                       pattern="\\d{4}-\\d{2}-\\d{2}" placeholder="YYYY-MM-DD"
                                       style="width: 100%; height: 26px; padding: 2px 8px; border: 1px solid #ccd0d4; border-radius: 4px; font-size: 13px; background-color: #fff;" 
                                       class="bst-date-text">
                            </td>
                            <td class="payment-invoice-col" style="padding: 4px; border: 1px solid #ddd;">
                                <input type="text" name="refund_commission_invoice" value="${booking.refund_commission_invoice || ''}" style="width: 100%;">
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        `;
    }
    
    function generateInvoicingEditForm() {
        var booking = window.bstBookingData || {};
        var currency = booking.tour_currency || 'EUR';
        var currencySymbol = currency === 'USD' ? '$' : '€';
        
        return `
            <div class="edit-form-section">
                <h4>Invoice Information</h4>
                
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="booking_invoice_number">Invoice Number</label>
                        <input type="text" id="booking_invoice_number" name="booking_invoice_number" 
                               value="${booking.booking_invoice_number || ''}" 
                               placeholder="e.g., 2026-0001" />
                    </div>
                    <div class="edit-form-field">
                        <label for="booking_invoice_date">Invoice Date</label>
                        <input type="text" id="booking_invoice_date" name="booking_invoice_date" 
                               value="${booking.booking_invoice_date ? booking.booking_invoice_date.split(' ')[0] : ''}" 
                               placeholder="YYYY-MM-DD" 
                               pattern="\\d{4}-\\d{2}-\\d{2}" />
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="booking_eu_percent">EU Percent (%)</label>
                        <input type="number" step="0.01" id="booking_eu_percent" name="booking_eu_percent" 
                               value="${booking.booking_eu_percent || ''}" 
                               placeholder="0.00" />
                    </div>
                    <div class="edit-form-field">
                        <label for="booking_vat_rate">VAT Rate (%)</label>
                        <input type="number" step="0.01" id="booking_vat_rate" name="booking_vat_rate" 
                               value="${booking.booking_vat_rate || ''}" 
                               placeholder="0.00" />
                    </div>
                </div>
                
                <h4>Tour Package</h4>
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="booking_tour_package_amount">Tour Package Amount (${currencySymbol})</label>
                        <input type="number" step="0.01" id="booking_tour_package_amount" name="booking_tour_package_amount" 
                               value="${booking.booking_tour_package_amount || ''}" 
                               placeholder="0.00" />
                    </div>
                </div>
                
                <h4>Vehicle Amounts</h4>
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="booking_vehicle_1_use_amount">Vehicle 1 Use Amount</label>
                        <input type="number" step="0.01" id="booking_vehicle_1_use_amount" name="booking_vehicle_1_use_amount" 
                               value="${booking.booking_vehicle_1_use_amount || ''}" 
                               placeholder="0.00" />
                    </div>
                    <div class="edit-form-field">
                        <label for="booking_vehicle_2_use_amount">Vehicle 2 Use Amount</label>
                        <input type="number" step="0.01" id="booking_vehicle_2_use_amount" name="booking_vehicle_2_use_amount" 
                               value="${booking.booking_vehicle_2_use_amount || ''}" 
                               placeholder="0.00" />
                    </div>
                </div>
            </div>
        `;
    }
    
    function collectFormData($tile, tileType) {
        var data = {};
        
        // Fields that are UI-only and should not be saved to database
        var uiOnlyFields = [
            'tour_price_override',
            'total_due'  // This is the only calculated field that's NOT in the database
        ];
        
        // Fields that are read-only and should not be updated
        var readOnlyFields = [
            'id',  // System Information: Record ID is read-only
            'updated_by',  // System Information: Updated automatically
            'updated_date'  // System Information: Updated automatically
        ];
        
        $tile.find('.tile-edit-form input, .tile-edit-form select, .tile-edit-form textarea').each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            if (name) {
                // Skip UI-only fields that aren't database fields
                if (uiOnlyFields.includes(name)) {
                    return; // Skip UI-only fields
                }
                
                // Skip read-only fields
                if (readOnlyFields.includes(name)) {
                    return; // Skip read-only fields
                }
                
                var value = $field.val();

                if (name === 'tour_package_id' && value) {
                    value = bstPackageOptionNumericId(value);
                }
                
                // Special handling for checkboxes
                if ($field.attr('type') === 'checkbox') {
                    value = $field.is(':checked') ? '1' : '0';
                }
                
                // Convert empty strings to null for consistent database storage
                if (value === '' || value === undefined) {
                    value = null;
                }
                
                // Special handling for tour_price field (extract numeric value from formatted text)
                if (name === 'tour_price' && value && value !== 'TBD') {
                    // Extract numeric value from formatted price (e.g., "€ 1,250.00" -> "1250.00")
                    var numericMatch = value.match(/([0-9,]+\.?\d*)/);
                    if (numericMatch) {
                        value = numericMatch[1].replace(/,/g, '');
                    }
                }
                
                // Special handling for commission percentage (convert from percentage to decimal)
                if (name === 'booking_commission_percent') {
                    value = parseFloat(value || 0) / 100;
                }
                
                data[name] = value;
            }
        });
        
        // Calculate derived values that need to be saved based on tile type
        if (tileType === 'financials') {
            // Financial calculations
            var tourPrice = parseFloat(data.tour_price) || 0;
            var couponAmount = parseFloat(data.coupon_amount) || 0;
            var additionalCharge = parseFloat(data.additional_charge) || 0;
            
            // Payment amounts
            var depositAmount = parseFloat(data.deposit_payment_amount) || 0;
            var balanceAmount = parseFloat(data.balance_payment_amount) || 0;
            var additionalAmount = parseFloat(data.additional_payment_amount) || 0;
            var refundAmount = parseFloat(data.refund_payment_amount) || 0;
            
            // Payment discount amounts
            var depositDiscount = parseFloat(data.deposit_payment_discount) || 0;
            var balanceDiscount = parseFloat(data.balance_payment_discount) || 0;
            var additionalDiscount = parseFloat(data.additional_payment_discount) || 0;
            
            // Calculate derived values
            data.net_tour_price = tourPrice - couponAmount;
            data.total_paid = depositAmount + balanceAmount + additionalAmount - refundAmount;
            data.payment_discount_amount = depositDiscount + balanceDiscount + additionalDiscount;
            data.balance_due = (data.net_tour_price + additionalCharge) - data.total_paid - data.payment_discount_amount;
        } else if (tileType === 'tour_package') {
            // Vehicle selects use CPT id as option value; POST needs vehicle*_id plus legacy display text (incl. price suffix).
            var $v1Opt = $tile.find('#vehicle1 option:selected');
            if ($v1Opt.length && $v1Opt.val()) {
                data.vehicle1_id = parseInt($v1Opt.val(), 10) || 0;
                data.vehicle1 = $v1Opt.attr('data-text') || $v1Opt.text() || '';
            } else {
                data.vehicle1_id = 0;
                data.vehicle1 = '';
            }
            var $v2Opt = $tile.find('#vehicle2 option:selected');
            if ($v2Opt.length && $v2Opt.val()) {
                data.vehicle2_id = parseInt($v2Opt.val(), 10) || 0;
                data.vehicle2 = $v2Opt.attr('data-text') || $v2Opt.text() || '';
            } else {
                data.vehicle2_id = 0;
                data.vehicle2 = '';
            }
            
            // Don't override vehicle_choices - use the value from the form field which preserves the saved value
            // vehicle_choices represents how many vehicles need to be chosen, not how many the package includes
            
            // Calculate financial derived values for consistency
            var currentTourPrice = parseFloat(data.tour_price) || parseFloat(window.bstBookingData?.tour_price) || 0;
            var currentCouponAmount = parseFloat(window.bstBookingData?.coupon_amount) || 0;
            var currentAdditionalCharge = parseFloat(window.bstBookingData?.additional_charge) || 0;
            var currentDepositAmount = parseFloat(window.bstBookingData?.deposit_payment_amount) || 0;
            var currentBalanceAmount = parseFloat(window.bstBookingData?.balance_payment_amount) || 0;
            var currentAdditionalAmount = parseFloat(window.bstBookingData?.additional_payment_amount) || 0;
            var currentRefundAmount = parseFloat(window.bstBookingData?.refund_payment_amount) || 0;
            
            // Calculate derived financial values
            data.net_tour_price = currentTourPrice - currentCouponAmount;
            data.additional_charge = currentAdditionalCharge;
            data.total_paid = currentDepositAmount + currentBalanceAmount + currentAdditionalAmount - currentRefundAmount;
            data.balance_due = (data.net_tour_price + data.additional_charge) - data.total_paid;
        } else {
            // For other tiles, ensure we maintain financial consistency if we have the base data
            var currentTourPrice = parseFloat(window.bstBookingData?.tour_price) || 0;
            var currentCouponAmount = parseFloat(window.bstBookingData?.coupon_amount) || 0;
            var currentAdditionalCharge = parseFloat(window.bstBookingData?.additional_charge) || 0;
            var currentDepositAmount = parseFloat(window.bstBookingData?.deposit_payment_amount) || 0;
            var currentBalanceAmount = parseFloat(window.bstBookingData?.balance_payment_amount) || 0;
            var currentAdditionalAmount = parseFloat(window.bstBookingData?.additional_payment_amount) || 0;
            var currentRefundAmount = parseFloat(window.bstBookingData?.refund_payment_amount) || 0;
            
            // Always calculate derived financial values for consistency
            data.net_tour_price = currentTourPrice - currentCouponAmount;
            data.additional_charge = currentAdditionalCharge;
            data.total_paid = currentDepositAmount + currentBalanceAmount + currentAdditionalAmount - currentRefundAmount;
            data.balance_due = (data.net_tour_price + data.additional_charge) - data.total_paid;
        }
        
        return data;
    }

    /** Package <option> value may be "id|numericPrice" for admin pricing; server expects numeric id only. */
    function bstPackageOptionNumericId(val) {
        if (val === undefined || val === null || val === '') {
            return '';
        }
        var s = String(val);
        var i = s.indexOf('|');
        return i >= 0 ? s.substring(0, i) : s;
    }
    
    function generateSystemEditForm() {
        var booking = window.bstBookingData || {};
        
        return `
            <div class="edit-form-section">
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="id">Record ID:</label>
                        <input type="text" id="id" name="id" value="${booking.id || ''}" readonly style="background: #f5f5f5; cursor: not-allowed;">
                    </div>
                    <div class="edit-form-field">
                        <label for="data_source">Data Source:</label>
                        <input type="text" id="data_source" name="data_source" value="${booking.data_source || ''}">
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="created_by">Created By:</label>
                        <input type="text" id="created_by" name="created_by" value="${booking.created_by || ''}">
                    </div>
                    <div class="edit-form-field">
                        <label for="created_date">Created Date:</label>
                        <input type="text" id="created_date" name="created_date" value="${booking.created_date || ''}">
                    </div>
                </div>
                
                <div class="edit-form-row">
                    <div class="edit-form-field">
                        <label for="updated_by">Updated By:</label>
                        <input type="text" id="updated_by" name="updated_by" value="${booking.updated_by || ''}" readonly style="background: #f5f5f5; cursor: not-allowed;">
                    </div>
                    <div class="edit-form-field">
                        <label for="updated_date">Updated Date:</label>
                        <input type="text" id="updated_date" name="updated_date" value="${booking.updated_date || ''}" readonly style="background: #f5f5f5; cursor: not-allowed;">
                    </div>
                </div>
            </div>
        `;
    }
    
    // Centralized shirt size mapping - used for both display and dropdown generation
    function getShirtSizeMap() {
        return {
            'XS': 'XS (Men)',
            'S': 'S (Men)',
            'M': 'M (Men)',
            'L': 'L (Men)',
            'XL': 'XL (Men)',
            'XXL': 'XXL (Men)',
            '3XL': '3XL (Men)',
            'XS-L': 'XS (Ladies)',
            'S-L': 'S (Ladies)',
            'M-L': 'M (Ladies)',
            'L-L': 'L (Ladies)',
            'XL-L': 'XL (Ladies)',
            'XXL-L': 'XXL (Ladies)',
            '3XL-L': '3XL (Ladies)'
        };
    }
    
    // Helper function to get shirt size display text
    function getShirtSizeDisplay(sizeValue) {
        if (!sizeValue) return '';
        var sizeMap = getShirtSizeMap();
        return sizeMap[sizeValue] || sizeValue;
    }
    
    // Helper function to generate shirt size dropdown options
    function generateShirtSizeOptions(selectedValue) {
        var sizeMap = getShirtSizeMap();
        var optionsHtml = '<option value="">Select Size</option>';
        
        // Generate options from the size map
        Object.keys(sizeMap).forEach(function(sizeValue) {
            var displayText = sizeMap[sizeValue];
            var selected = selectedValue === sizeValue ? 'selected' : '';
            optionsHtml += `<option value="${sizeValue}" ${selected}>${displayText}</option>`;
        });
        
        return optionsHtml;
    }

    /** Display string for #tour_price (symbol + grouped decimals); keeps collectFormData parsing working. */
    function bstFormatMoneyTourPriceDisplay(amount, currencyCode) {
        if (amount === undefined || amount === null || amount === '' || amount === 'TBD') {
            return 'TBD';
        }
        var n = typeof amount === 'number' ? amount : parseFloat(String(amount).replace(/[^0-9.\-]/g, ''));
        if (isNaN(n) || n === 0) {
            return 'TBD';
        }
        var sym = (currencyCode === 'USD') ? '$' : '€';
        return sym + ' ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    
    function generateTourPackageEditForm() {
        var booking = window.bstBookingData || {};
        var currency = booking.tour_currency || 'EUR';
        
        var tourPriceField = '<div class="edit-form-field">' +
                '<label for="tour_price">Tour Price</label>' +
                '<input type="text" id="tour_price" name="tour_price" value="' + bstFormatMoneyTourPriceDisplay(booking.tour_price, currency) + '" readonly style="text-align: right; background: #f5f5f5;">' +
            '</div>';
        
        var formHtml = '<div class="edit-form-section">' +
            '<h4>Tour Information</h4>' +
            '<div class="edit-form-row">' +
                '<div class="edit-form-field">' +
                    '<label for="tour_id">Tour</label>' +
                    '<select id="tour_id" name="tour_id">' +
                        '<option value="">Select a Tour</option>' +
                    '</select>' +
                '</div>' +
                '<div class="edit-form-field">' +
                    '<label for="tour_date_id">Tour Date</label>' +
                    '<select id="tour_date_id" name="tour_date_id">' +
                        '<option value="">Select a Tour Date</option>' +
                    '</select>' +
                '</div>' +
            '</div>' +
            '<div class="edit-form-row">' +
                '<div class="edit-form-field">' +
                    '<label for="tour_package_id">Package</label>' +
                    '<select id="tour_package_id" name="tour_package_id">' +
                        '<option value="">Select a Package</option>' +
                    '</select>' +
                '</div>' +
                tourPriceField +
            '</div>' +
        '</div>';
        
        // Determine vehicle visibility - use vehicle_choices, not package_vehicles
        var vehicleChoices = parseInt(booking.vehicle_choices) || 0;
        var vehicle1Style = (vehicleChoices >= 1) ? '' : ' style="display: none;"';
        var vehicle2Style = (vehicleChoices >= 2) ? '' : ' style="display: none;"';
        
        // Add vehicle section to form
        formHtml += '<div class="edit-form-section">' +
            '<div class="edit-form-row">' +
                '<div class="edit-form-field" id="vehicle1-field"' + vehicle1Style + '>' +
                    '<label for="vehicle1">Vehicle 1</label>' +
                    '<select id="vehicle1" name="vehicle1">' +
                        '<option value="">Select a Vehicle</option>' +
                    '</select>' +
                    '<p class="description bst-vehicle-limited-note" style="display:none;margin-top:6px;" aria-live="polite"></p>' +
                '</div>' +
                '<div class="edit-form-field" id="vehicle2-field"' + vehicle2Style + '>' +
                    '<label for="vehicle2">Vehicle 2</label>' +
                    '<select id="vehicle2" name="vehicle2">' +
                        '<option value="">Select a Vehicle</option>' +
                    '</select>' +
                    '<p class="description bst-vehicle-limited-note" style="display:none;margin-top:6px;" aria-live="polite"></p>' +
                '</div>' +
            '</div>' +
        '</div>';
        
        // Add extension checkbox if tour or tour date offers extension (like single-tour.php)
        var showExtension = false;
        var extensionLabel = 'Extension';
        var extensionPrice = 0;
        
        if (window.tourExtensionSettings) {
            // Check if tour offers extension (tour-level setting)
            if (window.tourExtensionSettings.offered == '1' || window.tourExtensionSettings.offered === 1) {
                showExtension = true;
            }
            // Check if date offers extension (date-level setting)
            if (window.tourExtensionSettings.dateOffered == '1' || window.tourExtensionSettings.dateOffered === 1) {
                showExtension = true;
            }
            
            // Server-computed add-on (tour ACF + vehicle CPT ids); fallback to package row only if missing.
            if (booking.bst_extension_addon_amount !== undefined && booking.bst_extension_addon_amount !== null) {
                extensionPrice = parseFloat(booking.bst_extension_addon_amount) || 0;
            } else if (window.tourExtensionSettings.pricing && booking.tour_package_id) {
                var packageKey = 'package_' + booking.tour_package_id;
                extensionPrice = parseFloat(window.tourExtensionSettings.pricing[packageKey]) || 0;
            }
            
            // Format extension label with dates and price like single-tour
            var tourCurrencyCode = booking.tour_currency || 'EUR';
            var symbol = (tourCurrencyCode === 'USD') ? '$' : '€';
            var extensionTitle = window.tourExtensionSettings.title || 'Extension';
            var formattedPrice = symbol + extensionPrice.toFixed(0);
            
            // Calculate extension dates if available
            var dateText = '';
            var extensionDays = parseInt(window.tourExtensionSettings.extensionDays) || 0;
            var tourDateEndDate = window.tourExtensionSettings.tourDateEndDate;
            
            if (tourDateEndDate && extensionDays > 0) {
                var tourEndDateStr = String(tourDateEndDate);
                var tourEndYear = parseInt(tourEndDateStr.substring(0, 4));
                var tourEndMonth = parseInt(tourEndDateStr.substring(4, 6));
                var tourEndDay = parseInt(tourEndDateStr.substring(6, 8));
                
                var extStartDate = new Date(tourEndYear, tourEndMonth - 1, tourEndDay);
                var extEndDate = new Date(extStartDate);
                extEndDate.setDate(extEndDate.getDate() + extensionDays);
                
                var startMonth = extStartDate.toLocaleDateString('en-US', { month: 'short' });
                var endMonth = extEndDate.toLocaleDateString('en-US', { month: 'short' });
                var startDay = extStartDate.getDate();
                var endDay = extEndDate.getDate();
                
                if (startMonth === endMonth) {
                    dateText = startDay + '-' + endDay + ' ' + endMonth;
                } else {
                    dateText = startDay + ' ' + startMonth + ' - ' + endDay + ' ' + endMonth;
                }
            }
            
            // Format label with dates and price in parentheses
            extensionLabel = 'Add ' + extensionTitle;
            if (dateText) {
                var extYear = extEndDate.getFullYear();
                extensionLabel += ' (' + dateText + ' ' + extYear + ' - ' + formattedPrice + ')';
            } else {
                extensionLabel += ' (' + formattedPrice + ')';
            }
        }
        
        // Show extension checkbox if offered
        if (showExtension) {
            var extensionChecked = (booking.tour_extension_added == '1' || booking.tour_extension_added === 1) ? ' checked' : '';
            formHtml += '<div class="edit-form-section">' +
                '<div class="edit-form-row">' +
                    '<div class="edit-form-field">' +
                        '<label>' +
                            '<input type="checkbox" id="tour_extension_added" name="tour_extension_added" value="1" data-price="' + extensionPrice + '"' + extensionChecked + '> ' +
                            extensionLabel +
                        '</label>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }
        
        // Add transmission fields conditionally based on normalized tour metadata.
        var showTransmissionFields = false;
        
        // Check if tour_type_code is "driving".
        if (booking.tour_type_code && booking.tour_type_code.toLowerCase() === 'driving') {
            showTransmissionFields = true;
        }
        
        formHtml += '<div class="edit-form-section">' +
            '<h4>Package Details</h4>' +
            '<div class="edit-form-row">' +
                '<div class="edit-form-field" style="display: none;">' +
                    '<label for="participant_sex">Participant Sex</label>' +
                    '<select id="participant_sex" name="participant_sex">' +
                        '<option value="">Select</option>' +
                        '<option value="Woman"' + (booking.participant_sex === 'Woman' ? ' selected' : '') + '>Woman</option>' +
                        '<option value="Man"' + (booking.participant_sex === 'Man' ? ' selected' : '') + '>Man</option>' +
                    '</select>' +
                '</div>' +
                '<div class="edit-form-field" style="display: none;">' +
                    '<label for="sharing_preference">Sharing Preference</label>' +
                    '<select id="sharing_preference" name="sharing_preference">' +
                        '<option value="">Select</option>' +
                        '<option value="same"' + (booking.sharing_preference === 'same' ? ' selected' : '') + '>With a person of the same sex</option>' +
                        '<option value="any"' + (booking.sharing_preference === 'any' ? ' selected' : '') + '>With a person of any sex</option>' +
                    '</select>' +
                '</div>' +
            '</div>' +
            '<div class="edit-form-row">' +
                '<div class="edit-form-field" style="display: none;">' +
                    '<label for="bed_preference">Bed Preference</label>' +
                    '<select id="bed_preference" name="bed_preference">' +
                        '<option value="">Select</option>' +
                        '<option value="King/queen bed"' + (booking.bed_preference === 'King/queen bed' ? ' selected' : '') + '>King/queen bed</option>' +
                        '<option value="Two single beds"' + (booking.bed_preference === 'Two single beds' ? ' selected' : '') + '>Two single beds</option>' +
                        '<option value="Any bed"' + (booking.bed_preference === 'Any bed' ? ' selected' : '') + '>Any bed</option>' +
                    '</select>' +
                '</div>' +
            '</div>' +
            '<div class="edit-form-row">' +
                '<div class="edit-form-field">' +
                    '<label for="hotel_nights_before">Hotel Nights Before</label>' +
                    '<input type="number" id="hotel_nights_before" name="hotel_nights_before" value="' + (booking.hotel_nights_before || '') + '" min="0" max="10">' +
                '</div>' +
                '<div class="edit-form-field">' +
                    '<label for="hotel_nights_after">Hotel Nights After</label>' +
                    '<input type="number" id="hotel_nights_after" name="hotel_nights_after" value="' + (booking.hotel_nights_after || '') + '" min="0" max="10">' +
                '</div>' +
            '</div>' +
            '<div class="edit-form-row">' +
                '<div class="edit-form-field">' +
                    '<label for="package_people">Package People</label>' +
                    '<input type="number" id="package_people" name="package_people" value="' + (booking.package_people || '') + '" min="1" max="10" readonly style="background: #f5f5f5;">' +
                '</div>' +
                '<div class="edit-form-field">' +
                    '<label for="package_rooms">Package Rooms</label>' +
                    '<input type="number" id="package_rooms" name="package_rooms" value="' + (booking.package_rooms || '') + '" step="0.5" min="0" readonly style="background: #f5f5f5;">' +
                '</div>' +
            '</div>' +
            '<div class="edit-form-row">' +
                '<div class="edit-form-field">' +
                    '<label for="package_vehicles">Package Vehicles</label>' +
                    '<input type="number" id="package_vehicles" name="package_vehicles" value="' + (booking.package_vehicles || '') + '" min="0" readonly style="background: #f5f5f5;">' +
                '</div>' +
                '<div class="edit-form-field">' +
                    '<label for="vehicle_choices">Vehicle Choices</label>' +
                    '<input type="number" id="vehicle_choices" name="vehicle_choices" value="' + (booking.vehicle_choices || '0') + '" min="0" max="2" readonly style="background: #f5f5f5;">' +
                '</div>' +
            '</div>' +
        '</div>';
        
        return formHtml;
    }
    
    function initializeTourPackageDependencies($tile) {
        // Populate tours dropdown on load
        loadTours($tile);
        
        // Set up change handlers for dependent dropdowns
        $tile.find('#tour_id').on('change', function() {
            var tourId = $(this).val();
            
            if (tourId) {
                loadTourDates($tile, tourId);
            } else {
                // Clear dependent dropdowns
                $tile.find('#tour_date_id').html('<option value="">Select a Tour Date</option>');
                $tile.find('#tour_package_id').html('<option value="">Select a Package</option>');
                clearVehicles($tile);
            }
        });
        
        $tile.find('#tour_date_id').on('change', function() {
            var tourId = $tile.find('#tour_id').val();
            var tourDateId = $(this).val();
            if (tourId) {
                loadPackages($tile, tourId, tourDateId);
                var pkgId = $tile.find('#tour_package_id').val();
                if (pkgId) {
                    var $opt = $tile.find('#tour_package_id option:selected');
                    updateTourPrice($tile, $opt);
                    updatePackageDetails($tile, $opt);
                }
            }
        });
        
        $tile.find('#tour_package_id').on('change', function() {
            var tourId = $tile.find('#tour_id').val();
            var packageId = $(this).val();
            var $selectedOption = $(this).find('option:selected');
            
            if (tourId && packageId) {
                // Calculate and update tour price
                updateTourPrice($tile, $selectedOption);
                
                // Update package details and show/hide conditional fields (this will load vehicles if needed)
                updatePackageDetails($tile, $selectedOption);
            } else {
                clearVehicles($tile);
                // Reset tour price to TBD when no package selected
                $tile.find('#tour_price').val('TBD');
                clearPackageDetails($tile);
            }
        });
    }
    
    function loadTours($tile) {
        var booking = window.bstBookingData || {};
        var $tourSelect = $tile.find('#tour_id');
        
        // Add loading state
        $tourSelect.html('<option value="">Loading tours...</option>');
        
        // Use the existing tours data from PHP - all published tours sorted by name
        var tours = <?php echo json_encode(array_map(function($tour) {
            return ['id' => $tour->ID, 'title' => $tour->post_title];
        }, $tours)); ?>;
        
        var options = '<option value="">Select a Tour</option>';
        tours.forEach(function(tour) {
            var selected = booking.tour_id == tour.id ? ' selected' : '';
            options += '<option value="' + tour.id + '"' + selected + '>' + tour.title + '</option>';
        });
        
        $tourSelect.html(options);
        
        // If there's a pre-selected tour, load dates first; loadPackages runs after dates load (needs date for availability).
        if (booking.tour_id) {
            loadTourDates($tile, booking.tour_id);
        }
    }
    
    function loadTourDates($tile, tourId) {
        var booking = window.bstBookingData || {};
        var $dateSelect = $tile.find('#tour_date_id');
        
        if (!tourId) {
            $dateSelect.html('<option value="">Select a Tour Date</option>');
            return;
        }
        
        $dateSelect.html('<option value="">Loading dates...</option>');
        
        $.ajax({
            url: window.ajaxurl,
            type: 'POST',
            data: {
                action: 'populate_tour_date_dropdown',
                tour_id: tourId
            },
            success: function(response) {
                if (response.success) {
                    var options = '<option value="">Select a Tour Date</option>';
                    
                    // Extract numeric ID from booking.tour_date_id (format: "123|text")
                    var bookingDateId = '';
                    if (booking.tour_date_id) {
                        bookingDateId = booking.tour_date_id.toString().split('|')[0];
                    }
                    
                    response.data.forEach(function(date) {
                        var selected = bookingDateId == date.id ? ' selected' : '';
                        options += '<option value="' + date.id + '"' + selected + '>' + date.name + '</option>';
                    });
                    $dateSelect.html(options);
                    var tourIdNow = $tile.find('#tour_id').val();
                    if (tourIdNow) {
                        loadPackages($tile, tourIdNow, $dateSelect.val() || '');
                    }
                } else {
                    $dateSelect.html('<option value="">No dates available</option>');
                }
            },
            error: function() {
                $dateSelect.html('<option value="">Error loading dates</option>');
            }
        });
    }
    
    function loadPackages($tile, tourId, tourDateId) {
        var booking = window.bstBookingData || {};
        var $packageSelect = $tile.find('#tour_package_id');
        
        if (!tourId) {
            $packageSelect.html('<option value="">Select a Package</option>');
            return;
        }
        
        $packageSelect.html('<option value="">Loading packages...</option>');
        
        var data = {
            action: 'get_package_pricing',
            tour_id: tourId
        };
        
        if (tourDateId) {
            data.tour_date_id = tourDateId;
        }
        
        $.ajax({
            url: window.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success && response.data && response.data.packages) {
                    var currency = window.bstBookingData?.tour_currency || 'EUR';
                    var options = '<option value="">Select a Package</option>';
                    response.data.packages.forEach(function(pkg) {
                        var selected = booking.tour_package_id == pkg['data.id'] ? ' selected' : '';
                        var packageText = pkg.text;
                        var rawPrice = (pkg.value !== undefined && pkg.value !== null) ? String(pkg.value) : '';
                        var optVal = pkg['data.id'] + '|' + rawPrice;
                        
                        options += '<option value="' + optVal + '"' + selected + '>' + packageText + '</option>';
                    });
                    $packageSelect.html(options);
                    
                    // If there's a pre-selected package, update tour price and package details
                    if (booking.tour_package_id) {
                        // Update tour price and package details for pre-selected package (this will load vehicles if needed)
                        var $selectedOption = $packageSelect.find('option:selected');
                        updateTourPrice($tile, $selectedOption);
                        updatePackageDetails($tile, $selectedOption);
                    }
                } else {
                    $packageSelect.html('<option value="">No packages available</option>');
                }
            },
            error: function() {
                $packageSelect.html('<option value="">Error loading packages</option>');
            }
        });
    }
    
    function formatBstVehicleLimitedNote(soldOther, maxSlots) {
        var tpl = window.bstVehicleLimitedNoteTemplate || '{{x}} of {{y}} sold on other bookings';
        return tpl.replace(/\{\{x\}\}/g, String(soldOther)).replace(/\{\{y\}\}/g, String(maxSlots));
    }

    function updateVehicleLimitedNotes($tile) {
        function sync($sel) {
            var $note = $sel.siblings('.bst-vehicle-limited-note');
            if (!$note.length) {
                return;
            }
            var $opt = $sel.find('option:selected');
            var vid = $sel.val();
            if (!vid) {
                $note.hide().text('');
                return;
            }
            var maxV = $opt.attr('data-limited-max');
            var otherV = $opt.attr('data-limited-sold-other');
            if (maxV === undefined || maxV === '' || otherV === undefined || otherV === '') {
                $note.hide().text('');
                return;
            }
            var maxSlots = parseInt(maxV, 10);
            var soldOther = parseInt(otherV, 10);
            if (isNaN(maxSlots) || maxSlots <= 0 || isNaN(soldOther)) {
                $note.hide().text('');
                return;
            }
            $note.text(formatBstVehicleLimitedNote(soldOther, maxSlots)).show();
        }
        sync($tile.find('#vehicle1'));
        sync($tile.find('#vehicle2'));
    }

    function loadVehicles($tile, tourId, packageId) {
        var booking = window.bstBookingData || {};
        var vehicleChoices = parseInt(booking.vehicle_choices, 10) || 0;
        var $vehicle1Select = $tile.find('#vehicle1');
        var $vehicle2Select = $tile.find('#vehicle2');
        
        if (!tourId || !packageId) {
            clearVehicles($tile);
            return;
        }
        
        $vehicle1Select.html('<option value="">Loading vehicles...</option>');
        $vehicle2Select.html('<option value="">Loading vehicles...</option>');
        
        // Get currency for vehicle pricing display
        var currency = booking.tour_currency || 'EUR';
        var tourDateRaw = $tile.find('#tour_date_id').val() || '';
        var tourDateId = parseInt(String(tourDateRaw).split('|')[0], 10) || 0;
        var bookingId = parseInt(booking.id, 10) || 0;
        
        $.ajax({
            url: window.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_vehicle_data',
                tour_id: tourId,
                package_id: packageId,
                currency: currency,
                tour_date_id: tourDateId,
                booking_id: bookingId,
                vehicle_labels_for: 'staff',
                show_archived: 1
            },
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    // Store vehicle pricing data globally for tour price calculations
                    if (!window.bstVehiclePricing) {
                        window.bstVehiclePricing = {};
                    }
                    window.bstVehiclePricing[tourId + '_' + packageId] = response.data;
                    
                    // Clear and populate vehicle dropdowns
                    $vehicle1Select.html('<option value="">Select a Vehicle</option>');
                    $vehicle2Select.html('<option value="">Select a Vehicle</option>');
                    
                    // Populate vehicle dropdowns based on which ones are visible (not user's saved choice)
                    if (response.data.length >= 1) {
                        response.data.forEach(function(vehicle) {
                            var vid = vehicle.vehicle_id ? String(vehicle.vehicle_id) : '';
                            var vtext = vehicle.text || '';
                            var $opt = $('<option></option>')
                                .attr('value', vid)
                                .attr('data-price', vehicle.price || 0)
                                .attr('data-text', vtext)
                                .text(vtext);
                            if (vehicle.limited_max != null && vehicle.limited_sold_other_bookings != null) {
                                $opt.attr('data-limited-max', vehicle.limited_max)
                                    .attr('data-limited-sold-other', vehicle.limited_sold_other_bookings);
                            }
                            
                            if ($vehicle1Select.closest('.edit-form-field').is(':visible')) {
                                $vehicle1Select.append($opt.clone());
                            }
                            if ($vehicle2Select.closest('.edit-form-field').is(':visible')) {
                                $vehicle2Select.append($opt.clone());
                            }
                        });
                        
                        // Don't modify visibility - respect the initial HTML state set during generation
                        
                        // Set selected values from saved CPT id (match option value as string).
                        function selectVehicleDropdown($select, booking, slot) {
                            var idKey = slot === 2 ? 'vehicle2_id' : 'vehicle1_id';
                            var raw = booking[idKey];
                            if (raw === undefined || raw === null || raw === '' || raw === 0) {
                                return;
                            }
                            var want = String(raw);
                            $select.val(want);
                            if ($select.val() === want) {
                                return;
                            }
                            $select.find('option').each(function() {
                                if (String(this.value) === want) {
                                    $select.val(this.value);
                                    return false;
                                }
                            });
                        }
                        selectVehicleDropdown($vehicle1Select, booking, 1);
                        if (vehicleChoices >= 2) {
                            selectVehicleDropdown($vehicle2Select, booking, 2);
                        }
                        
                        // Add change handlers for vehicle selection
                        $vehicle1Select.off('change.vehiclePrice').on('change.vehiclePrice', function() {
                            updateVehicleLimitedNotes($tile);
                            updateExtensionPriceAndLabel($tile, function() {
                                calculateCompleteTourPrice($tile);
                            });
                        });
                        
                        $vehicle2Select.off('change.vehiclePrice').on('change.vehiclePrice', function() {
                            updateVehicleLimitedNotes($tile);
                            updateExtensionPriceAndLabel($tile, function() {
                                calculateCompleteTourPrice($tile);
                            });
                        });
                        
                        updateVehicleLimitedNotes($tile);
                        
                        updateExtensionPriceAndLabel($tile, function() {
                            calculateCompleteTourPrice($tile);
                        });
                    }
                } else {
                    clearVehicles($tile);
                }
            },
            error: function() {
                clearVehicles($tile);
            }
        });
    }
    
    function clearVehicles($tile) {
        var $vehicle1 = $tile.find('#vehicle1');
        var $vehicle2 = $tile.find('#vehicle2');
        
        $vehicle1.html('<option value="">Select a Vehicle</option>');
        $vehicle2.html('<option value="">Select a Vehicle</option>');
        
        $tile.find('.bst-vehicle-limited-note').hide().text('');
        
        // Remove vehicle change handlers
        $vehicle1.off('change.vehiclePrice');
        $vehicle2.off('change.vehiclePrice');
        
        // Don't modify visibility - respect the initial HTML state
        
        // Use full calculator (base + extension from data-price) to avoid base-only flash before async refresh.
        calculateCompleteTourPrice($tile);
        var $ext = $tile.find('#tour_extension_added');
        if ($ext.length && $ext.is(':checked')) {
            updateExtensionPriceAndLabel($tile, function() {
                calculateCompleteTourPrice($tile);
            });
        }
    }
    
    function updateTourPrice($tile, $selectedPackageOption) {
        var $tourPriceField = $tile.find('#tour_price');
        
        if (!$selectedPackageOption || !$selectedPackageOption.val()) {
            $tourPriceField.val('TBD');
            // Package details clearing is handled by caller
            return;
        }
        
        // Extract price from the selected package option
        // The option value contains both package ID and price in format from buildProductValue
        var packageValue = $selectedPackageOption.val();
        var packageText = $selectedPackageOption.text();
        
        // Try to extract price from package value (format: "id|price")
        var price = null;
        if (packageValue && packageValue.includes('|')) {
            var parts = packageValue.split('|');
            if (parts.length >= 2) {
                price = parseFloat(parts[1]);
            }
        }
        
        // If price extraction failed, try to parse from text (look for currency symbols and numbers)
        if (!price || isNaN(price)) {
            var priceMatch = packageText.match(/[€$]\s?([0-9,]+\.?\d*)/);
            if (priceMatch) {
                price = parseFloat(priceMatch[1].replace(/,/g, ''));
            }
        }
        
        if (price && !isNaN(price)) {
            // Store base tour price in global booking data (package only; vehicle/extension layered in calculateCompleteTourPrice)
            if (window.bstBookingData) {
                window.bstBookingData.tour_price = price.toFixed(2);
            }
            
            // Show base + current vehicle upgrades + extension (from checkbox data-price) in one step.
            // Writing package-only here caused a visible flicker: saved total → base-only → full after async.
            calculateCompleteTourPrice($tile);
        } else {
            $tourPriceField.val('TBD');
        }
        
        // Package details updating is handled by caller
    }
    
    // Central function to calculate complete tour price from scratch
    function calculateCompleteTourPrice($tile) {
        var currency = window.bstBookingData?.tour_currency || 'EUR';
        var $tourPriceField = $tile.find('#tour_price');
        
        // Get base tour price from booking data (package price only)
        var baseTourPrice = parseFloat(window.bstBookingData?.tour_price) || 0;
        if (baseTourPrice === 0) {
            return; // No base price to work with
        }
        
        // Start with base price
        var totalPrice = baseTourPrice;
        
        // Add vehicle costs
        var $vehicle1Select = $tile.find('#vehicle1');
        var $vehicle2Select = $tile.find('#vehicle2');
        
        totalPrice += bstVehicleUpgradeFromOption($vehicle1Select.find('option:selected'));
        totalPrice += bstVehicleUpgradeFromOption($vehicle2Select.find('option:selected'));
        
        // Add extension price if checkbox is checked
        var $extensionCheckbox = $tile.find('#tour_extension_added');
        var extensionPrice = 0;
        if ($extensionCheckbox.length > 0 && $extensionCheckbox.is(':checked')) {
            extensionPrice = parseFloat($extensionCheckbox.attr('data-price')) || 0;
            totalPrice += extensionPrice;
        }
        
        // Update display (same formatting as initial load / bstFormatMoneyTourPriceDisplay)
        $tourPriceField.val(bstFormatMoneyTourPriceDisplay(totalPrice, currency));
        
        // Update global booking data
        if (window.bstBookingData) {
            window.bstBookingData.tour_price_with_vehicles = totalPrice.toFixed(2);
        }
    }
    
    // Alias for backward compatibility
    function recalculateTourPriceWithVehicles($tile) {
        calculateCompleteTourPrice($tile);
    }

    /** Numeric upgrade from a vehicle <option> (prefer attr; jQuery .data on options can be unreliable). */
    function bstVehicleUpgradeFromOption($opt) {
        if (!$opt || !$opt.length) {
            return 0;
        }
        var raw = $opt.attr('data-price');
        if (raw === undefined || raw === null || raw === '') {
            raw = $opt.data('price');
        }
        var n = parseFloat(raw);
        return isNaN(n) ? 0 : n;
    }
    
    function updateExtensionPriceAndLabel($tile, onDone) {
        onDone = typeof onDone === 'function' ? onDone : function() {};
        var $extensionCheckbox = $tile.find('#tour_extension_added');
        if ($extensionCheckbox.length === 0) {
            onDone();
            return;
        }
        if (!window.tourExtensionSettings || !window.tourExtensionSettings.pricing) {
            onDone();
            return;
        }
        var booking = window.bstBookingData || {};
        // Prefer current form selection — booking.* is stale until the tile is saved.
        var packageId = bstPackageOptionNumericId($tile.find('#tour_package_id').val() || booking.tour_package_id);
        var tourId = $tile.find('#tour_id').val() || booking.tour_id;
        if (!packageId || !tourId) {
            onDone();
            return;
        }
        var v1 = parseInt(String($tile.find('#vehicle1').val() || '0'), 10) || 0;
        var v2 = parseInt(String($tile.find('#vehicle2').val() || '0'), 10) || 0;

        function applyExtensionLabel(extensionPrice) {
            extensionPrice = Math.round(parseFloat(extensionPrice) || 0);
            $extensionCheckbox.data('price', extensionPrice);
            $extensionCheckbox.attr('data-price', extensionPrice);
            var extensionDays = parseInt(window.tourExtensionSettings.extensionDays) || 0;
            var tourCurrencyCode = booking.tour_currency || 'EUR';
            var symbol = (tourCurrencyCode === 'USD') ? '$' : '€';
            var extensionTitle = window.tourExtensionSettings.title || 'Extension';
            var formattedPrice = symbol + extensionPrice.toFixed(0);
            var dateText = '';
            var tourDateEndDate = window.tourExtensionSettings.tourDateEndDate;
            var extEndDate = null;
            if (tourDateEndDate && extensionDays > 0) {
                var tourEndDateStr = String(tourDateEndDate);
                var tourEndYear = parseInt(tourEndDateStr.substring(0, 4));
                var tourEndMonth = parseInt(tourEndDateStr.substring(4, 6));
                var tourEndDay = parseInt(tourEndDateStr.substring(6, 8));
                var extStartDate = new Date(tourEndYear, tourEndMonth - 1, tourEndDay);
                extEndDate = new Date(extStartDate);
                extEndDate.setDate(extEndDate.getDate() + extensionDays);
                var startMonth = extStartDate.toLocaleDateString('en-US', { month: 'short' });
                var endMonth = extEndDate.toLocaleDateString('en-US', { month: 'short' });
                var startDay = extStartDate.getDate();
                var endDay = extEndDate.getDate();
                if (startMonth === endMonth) {
                    dateText = startDay + '-' + endDay + ' ' + endMonth;
                } else {
                    dateText = startDay + ' ' + startMonth + ' - ' + endDay + ' ' + endMonth;
                }
            }
            var newLabelText = 'Add ' + extensionTitle;
            if (dateText && extEndDate) {
                var extYear = extEndDate.getFullYear();
                newLabelText += ' (' + dateText + ' ' + extYear + ' - ' + formattedPrice + ')';
            } else {
                newLabelText += ' (' + formattedPrice + ')';
            }
            var $label = $extensionCheckbox.closest('label');
            if ($label.length > 0) {
                $label.contents().filter(function() {
                    return this.nodeType === 3;
                }).remove();
                $label.append(' ' + newLabelText);
            }
        }

        function fallbackClientExtensionAmount() {
            var packageKey = 'package_' + packageId;
            var extensionPrice = parseFloat(window.tourExtensionSettings.pricing[packageKey]) || 0;
            var extensionDays = parseInt(window.tourExtensionSettings.extensionDays) || 0;
            var adminDrivingDays = parseFloat(window.tourExtensionSettings.adminVehicleDrivingDays) || 0;
            if (adminDrivingDays > 0 && extensionDays > 0) {
                var vehicle1Upcharge = bstVehicleUpgradeFromOption($tile.find('#vehicle1').find('option:selected'));
                if (vehicle1Upcharge > 0) {
                    extensionPrice += Math.round(vehicle1Upcharge / adminDrivingDays * extensionDays);
                }
                var vehicle2Upcharge = bstVehicleUpgradeFromOption($tile.find('#vehicle2').find('option:selected'));
                if (vehicle2Upcharge > 0) {
                    extensionPrice += Math.round(vehicle2Upcharge / adminDrivingDays * extensionDays);
                }
            }
            return Math.round(extensionPrice);
        }

        jQuery.ajax({
            url: window.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'bst_extension_addon_amount',
                nonce: window.bstTourBookingsNonce,
                tour_id: tourId,
                tour_package_id: packageId,
                vehicle1_id: v1,
                vehicle2_id: v2
            }
        }).done(function(res) {
            var amt = (res && res.success && res.data && res.data.amount !== undefined) ? parseFloat(res.data.amount) : fallbackClientExtensionAmount();
            applyExtensionLabel(amt);
        }).fail(function() {
            applyExtensionLabel(fallbackClientExtensionAmount());
        }).always(function() {
            onDone();
        });
    }
    
    function updatePackageDetails($tile, $selectedPackageOption) {
        var packageId = bstPackageOptionNumericId($selectedPackageOption.val());
        
        // Fetch package details from WordPress options via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_package_details',
                package_id: packageId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    var packageData = response.data;
                    var packagePeople = parseInt(packageData.people) || 1;
                    var packageRooms = parseFloat(packageData.rooms) || 1;
                    var packageVehicles = parseInt(packageData.vehicles) || 1;
                    
                    // Update the form fields
                    $tile.find('#package_people').val(packagePeople);
                    $tile.find('#package_rooms').val(packageRooms);
                    $tile.find('#package_vehicles').val(packageVehicles);
                    
                    // Check if tour has vehicles defined before setting vehicle_choices
                    var tourId = $tile.find('#tour_id').val();
                    if (tourId && packageVehicles > 0) {
                        // Check if this tour/package has actual vehicle options available
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'get_vehicle_data',
                                tour_id: tourId,
                                package_id: packageId,
                                currency: window.bstBookingData?.tour_currency || 'EUR',
                                booking_id: parseInt(String((window.bstBookingData && window.bstBookingData.id) || ''), 10) || 0,
                                vehicle_labels_for: 'staff',
                                show_archived: 1
                            },
                            success: function(vehicleResponse) {
                                var currentVehicleChoices = 0;
                                
                                // If tour has vehicles defined, use package_vehicles; otherwise 0
                                if (vehicleResponse.success && vehicleResponse.data && vehicleResponse.data.length > 0) {
                                    currentVehicleChoices = packageVehicles;
                                }
                                
                                $tile.find('#vehicle_choices').val(currentVehicleChoices);
                                updateVehicleFieldVisibility($tile, currentVehicleChoices, tourId, packageId);
                            }
                        });
                    } else {
                        // No package vehicles or no tour selected
                        $tile.find('#vehicle_choices').val(0);
                        updateVehicleFieldVisibility($tile, 0, tourId, packageId);
                    }
                    
                    // Show/hide conditional fields based on package details
                    var $participantSexField = $tile.find('#participant_sex').closest('.edit-form-field');
                    var $sharingPreferenceField = $tile.find('#sharing_preference').closest('.edit-form-field');
                    var $bedPreferenceField = $tile.find('#bed_preference').closest('.edit-form-field');
                    
                    // Show participant sex and sharing preference if package_rooms = 0.5
                    if (packageRooms === 0.5) {
                        $participantSexField.show();
                        $sharingPreferenceField.show();
                    } else {
                        $participantSexField.hide();
                        $sharingPreferenceField.hide();
                        // Clear hidden field values
                        $tile.find('#participant_sex').val('');
                        $tile.find('#sharing_preference').val('');
                    }
                    
                    // Show bed preference if package_people = 2 and package_rooms = 1
                    if (packagePeople === 2 && packageRooms === 1) {
                        $bedPreferenceField.show();
                    } else {
                        $bedPreferenceField.hide();
                        // Clear hidden field value
                        $tile.find('#bed_preference').val('');
                    }
                    
                    // Guest2 visibility will be handled after successful tile save
                    // Do not update guest2 visibility here - let the save response handle it
                    
                    // Update global booking data
                    if (window.bstBookingData) {
                        window.bstBookingData.package_people = packagePeople;
                        window.bstBookingData.package_rooms = packageRooms;
                        window.bstBookingData.package_vehicles = packageVehicles;
                        // vehicle_choices is updated in updateVehicleFieldVisibility
                    }
                } else {
                    console.error('Failed to fetch package details:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error fetching package details:', error);
            }
        });
    }
    
    function updateVehicleFieldVisibility($tile, vehicleChoices, tourId, packageId) {
        var $vehicle1Field = $tile.find('#vehicle1-field');
        var $vehicle2Field = $tile.find('#vehicle2-field');
        
        if (vehicleChoices >= 1) {
            $vehicle1Field.css('display', '');
        } else {
            $vehicle1Field.css('display', 'none');
            // Clear vehicle1 value if hidden
            $tile.find('#vehicle1').val('');
        }
        
        if (vehicleChoices >= 2) {
            $vehicle2Field.css('display', '');
        } else {
            $vehicle2Field.css('display', 'none');
            // Clear vehicle2 value if hidden
            $tile.find('#vehicle2').val('');
        }
        
        // Update global booking data
        if (window.bstBookingData) {
            window.bstBookingData.vehicle_choices = vehicleChoices;
        }
        
        // Re-populate vehicles after showing/hiding fields
        if (vehicleChoices > 0 && tourId && packageId) {
            loadVehicles($tile, tourId, packageId);
        } else if (tourId && packageId) {
            // No vehicle UI: extension amount still depends on package (and tour); refresh label/data-price and total.
            updateExtensionPriceAndLabel($tile, function() {
                calculateCompleteTourPrice($tile);
            });
        }
    }
    
    function clearPackageDetails($tile) {
        // Clear package detail fields
        $tile.find('#package_people').val('');
        $tile.find('#package_rooms').val('');
        $tile.find('#package_vehicles').val('');
        $tile.find('#vehicle_choices').val('0');
        
        // Hide all vehicle dropdowns when no package is selected
        $tile.find('#vehicle1').closest('.edit-form-field').hide();
        $tile.find('#vehicle2').closest('.edit-form-field').hide();
        
        // Hide all conditional fields and clear their values
        var $participantSexField = $tile.find('#participant_sex').closest('.edit-form-field');
        var $sharingPreferenceField = $tile.find('#sharing_preference').closest('.edit-form-field');
        var $bedPreferenceField = $tile.find('#bed_preference').closest('.edit-form-field');
        
        $participantSexField.hide();
        $sharingPreferenceField.hide();
        $bedPreferenceField.hide();
        
        $tile.find('#participant_sex').val('');
        $tile.find('#sharing_preference').val('');
        $tile.find('#bed_preference').val('');
        $tile.find('#vehicle1').val('');
        $tile.find('#vehicle2').val('');
        
        // Hide Guest 2 tile when no package is selected
        $('[data-tile="guest2"]').hide();
    }
    
    function exitEditMode($tile) {
        $tile.removeClass('tile-editing');
        $tile.find('.tile-save-btn, .tile-cancel-btn').remove();
        $tile.find('.tile-edit-btn').show();
        $tile.find('.tile-edit-form').empty();
    }
    
    // Recalculate invoice data button handler
    $('.reprocess-gf10-btn').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var bookingId = <?php echo $booking ? $booking->id : 0; ?>;

        if (!bookingId) {
            showMessage('No booking ID found', 'error');
            return;
        }

        if (!confirm('Reprocess the GF10 finalization entry for this booking? This will update all guest fields, invoice fields, and booking status from the stored entry data.')) {
            return;
        }

        $button.prop('disabled', true);
        var originalHtml = $button.html();
        $button.html('<i class="fas fa-spinner fa-spin"></i> Reprocessing...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bst_reprocess_gf10',
                nonce: '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>',
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    location.reload();
                } else {
                    showMessage(response.data.message || 'Reprocess failed', 'error');
                    $button.prop('disabled', false);
                    $button.html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                showMessage('AJAX error: ' + error, 'error');
                $button.prop('disabled', false);
                $button.html(originalHtml);
            }
        });
    });

    $('.recalc-invoice-btn').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var bookingId = <?php echo $booking ? $booking->id : 0; ?>;
        
        if (!bookingId) {
            showMessage('No booking ID found', 'error');
            return;
        }
        
        // Disable button and show loading
        $button.prop('disabled', true);
        var originalHtml = $button.html();
        $button.html('<i class="fas fa-spinner fa-spin"></i> Recalculating...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bst_recalculate_invoice_data',
                nonce: '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>',
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    // Reload the page to show updated invoice data
                    location.reload();
                } else {
                    showMessage(response.data.message || 'Recalculation failed', 'error');
                    $button.prop('disabled', false);
                    $button.html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                showMessage('AJAX error: ' + error, 'error');
                $button.prop('disabled', false);
                $button.html(originalHtml);
            }
        });
    });
    
    // Delete booking button handler
    $('#delete-booking-btn').on('click', function(e) {
        e.preventDefault();
        
        // Check if button is disabled (due to payments)
        if ($(this).prop('disabled')) {
            showMessage('Cannot delete booking with payments', 'error');
            return;
        }
        
        var bookingId = <?php echo $booking ? $booking->id : 0; ?>;
        if (!bookingId) {
            showMessage('No booking ID found', 'error');
            return;
        }
        
        // Confirm deletion
        var guestName = '<?php echo $booking ? esc_js(bst_format_name($booking->guest1_first_name, $booking->guest1_last_name)) : 'this booking'; ?>';
        var confirmMessage = 'Are you sure you want to delete the booking for ' + guestName + '?\n\nThis action cannot be undone.';
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        // Show loading state
        var $deleteBtn = $(this);
        var originalText = $deleteBtn.html();
        $deleteBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
        
        // Submit delete request
        $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'bst_delete_booking',
                booking_id: bookingId,
                nonce: '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Booking deleted successfully', 'success');
                    
                    // Build return URL with preserved filters using the same approach as in tour-bookings-new.php
                    var returnUrl = '<?php echo admin_url('admin.php'); ?>';
                    var returnParams = new URLSearchParams();
                    
                    // Add the base page parameter
                    returnParams.set('page', 'bst-tour-bookings');
                    
                    // Add the booking_deleted parameter
                    returnParams.set('booking_deleted', '1');
                    
                    // Try to get filters from current page URL parameters first
                    var currentParams = new URLSearchParams(window.location.search);
                    var filterParams = ['filter_tour_id', 'filter_tour_date_id', 'filter_package_id', 'guest1_first_name', 'guest1_last_name', 'guest1_email', 'tour_id', 'tour_date_id', 'tour_package_id', 'booking_status', 'tour_currency', 'how_heard', 'source', 'referrer', 'current_page', 'per_page'];
                    
                    filterParams.forEach(function(param) {
                        var value = currentParams.get(param);
                        if (value) {
                            returnParams.set(param, value);
                        }
                    });
                    
                    // If no filters found in current URL, try referrer as fallback
                    if (returnParams.toString() === 'booking_deleted=1') {
                        var referrer = document.referrer;
                        if (referrer && referrer.includes('page=bst-tour-bookings')) {
                            try {
                                var referrerUrl = new URL(referrer);
                                filterParams.forEach(function(param) {
                                    var value = referrerUrl.searchParams.get(param);
                                    if (value) {
                                        returnParams.set(param, value);
                                    }
                                });
                            } catch (e) {
                                // Could not parse referrer URL, using filters from current URL only
                            }
                        }
                    }
                    
                    // Build final return URL
                    if (returnParams.toString()) {
                        returnUrl += '?' + returnParams.toString();
                    }
                    
                    // Redirect to bookings list after a short delay
                    setTimeout(function() {
                        window.location.href = returnUrl;
                    }, 1500);
                } else {
                    showMessage('Error deleting booking: ' + (response.data || 'Unknown error'), 'error');
                    $deleteBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error deleting booking: ' + error, 'error');
                $deleteBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Update Customer from Booking button handler
    $(document).on('click', '#update-customer-from-booking', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var bookingId = $btn.data('booking-id');
        var customerId = $btn.data('customer-id');
        var originalText = $btn.html();
        
        // Disable button and show loading
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'update_customer_from_booking',
                booking_id: bookingId,
                customer_id: customerId,
                nonce: '<?php echo wp_create_nonce('update_customer_from_booking'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Customer updated successfully with booking information!', 'success');
                    // Refresh the customer display
                    location.reload();
                } else {
                    showMessage('Error: ' + (response.data || 'Unknown error occurred'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error updating customer: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
    

    
    function getTileName(tileType) {
        var tileNames = {
            'marketing': 'Marketing',
            'guest1': 'Guest 1',
            'guest2': 'Guest 2', 
            'notes': 'Notes',
            'customer': 'Customer',
            'gravity_forms': 'Gravity Forms',
            'administrative': 'Administrative',
            'links': 'Links',
            'system': 'System',
            'financials': 'Financials',
            'tour_package': 'Tour Package',
            'payment': 'Payment'
        };
        return tileNames[tileType] || 'Section';
    }

    window.showMessage = function(message, type, allowMultiple) {
        // Only remove existing success messages if allowMultiple is false (default behavior)
        if (type === 'success' && !allowMultiple) {
            jQuery('.notice-success').remove();
        }
        
        var $message = jQuery('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        jQuery('.wrap h1').after($message);
    };
    
    // Make booking data and AJAX URL available to JavaScript
    window.bstBookingData = <?php echo json_encode($booking ?: new stdClass()); ?>;
    // Store original tour price separately to preserve it from being overwritten
    // This should NEVER be updated during package changes - it represents the database value
    window.originalTourPrice = <?php echo json_encode($booking->tour_price ?? 0); ?>;
    window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    window.bstTourBookingsNonce = '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>';
    window.bstVehicleLimitedNoteTemplate = <?php echo wp_json_encode(
        __( '{{x}} of {{y}} sold on other bookings', 'bst-plugin' )
    ); ?>;
    
    <?php
    // Get extension settings from the tour if booking has a tour_id
    if (!empty($booking->tour_id)) {
        $tour_extension_offered = get_field('extension_offered', $booking->tour_id);
        $tour_extension_title = get_field('extension_title', $booking->tour_id);
        $tour_extension_pricing = get_field('extension_pricing', $booking->tour_id);
        $tour_extension_days = get_field('extension_driving_days', $booking->tour_id);
        $admin_vehicle_driving_days = get_field('admin_vehicle_driving_days', $booking->tour_id);
        $using_bst_owned_vehicles = get_field('using_bst_owned_vehicles', $booking->tour_id);
        
        // Get date extension setting and end date
        $date_extension_offered = '0';
        $tour_date_end_date = null;
        if (!empty($booking->tour_date_id)) {
            $tour_date_id_parts = explode('|', $booking->tour_date_id);
            $tour_date_id = $tour_date_id_parts[0];
            $date_extension_offered = get_post_meta($tour_date_id, 'extension_offered', true);
            $tour_date_end_date = get_post_meta($tour_date_id, 'end_date', true);
            
            // Convert date from YYYY-MM-DD to YYYYMMDD format for JavaScript
            if ($tour_date_end_date) {
                $tour_date_end_date = str_replace('-', '', $tour_date_end_date);
            }
        }
        
        echo "// Tour settings for invoicing and package management\n";
        echo "window.bstTourData = " . json_encode([
            'using_bst_owned_vehicles' => $using_bst_owned_vehicles
        ]) . ";\n";
        
        echo "// Extension settings from tour post\n";
        echo "window.tourExtensionSettings = " . json_encode([
            'offered' => $tour_extension_offered,
            'title' => $tour_extension_title,
            'pricing' => $tour_extension_pricing,
            'extensionDays' => $tour_extension_days,
            'adminVehicleDrivingDays' => $admin_vehicle_driving_days,
            'dateOffered' => $date_extension_offered,
            'tourDateEndDate' => $tour_date_end_date
        ]) . ";\n";
    } else {
        echo "window.bstTourData = null;\n";
        echo "window.tourExtensionSettings = null;\n";
    }
    ?>
    
    // All tile rendering now uses server-side HTML exclusively
});

// Email log refresh function (outside jQuery ready function)
function refreshEmailLog(bookingId) {
    jQuery.ajax({
        url: window.ajaxurl,
        method: 'POST',
        data: {
            action: 'bst_get_booking_email_log',
            booking_id: bookingId,
            nonce: window.bstTourBookingsNonce
        },
        success: function(response) {
            if (response.success) {
                jQuery('#email-log-content-' + bookingId).html(response.data);
            } else {
                console.error('Failed to refresh email log:', response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error refreshing email log:', error);
        }
    });
}

// Email log functions from class-email-log-viewer.php
function viewEmailContent(logId) {
    console.log('viewEmailContent called with logId:', logId);
    console.log('ajaxurl:', window.ajaxurl);
    console.log('nonce:', window.bstTourBookingsNonce);
    
    // Show modal immediately with loading state
    jQuery('#email-content-body').html('<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading email content...</div>');
    jQuery('#email-content-modal').show();
    
    jQuery.ajax({
        url: window.ajaxurl,
        method: 'POST',
        data: {
            action: 'bst_get_email_content',
            log_id: logId,
            nonce: window.bstTourBookingsNonce
        },
        success: function(response) {
            console.log('AJAX success response:', response);
            if (response.success) {
                jQuery('#email-content-body').html(response.data);
            } else {
                console.error('AJAX response not successful:', response);
                jQuery('#email-content-body').html('<div style="color: red; padding: 20px;">Failed to load email content: ' + (response.data || 'Unknown error') + '</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', xhr.responseText);
            jQuery('#email-content-body').html('<div style="color: red; padding: 20px;">Error loading email content: ' + error + '</div>');
        }
    });
}

function resendEmail(logId) {
    if (confirm('Are you sure you want to resend this email?')) {
        jQuery.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'bst_resend_email',
                log_id: logId,
                nonce: window.bstTourBookingsNonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Email resent successfully', 'success');
                    // Refresh the email log
                    var bookingId = window.bstBookingData.id;
                    if (bookingId) {
                        refreshEmailLog(bookingId);
                    }
                } else {
                    showMessage('Failed to resend email: ' + response.data, 'error');
                }
            },
            error: function() {
                showMessage('Error resending email', 'error');
            }
        });
    }
}

function sendFinalizationEmail(bookingId) {
    if (confirm('Are you sure you want to send the finalization email?')) {
        showMessage('Sending finalization email...', 'info');
        jQuery.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'bst_send_finalization_email',
                booking_id: bookingId,
                nonce: window.bstTourBookingsNonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Finalization email sent successfully', 'success');
                    // Refresh the email log
                    refreshEmailLog(bookingId);
                    // Reload page to update actions tile
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage('Failed to send finalization email: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error sending finalization email: ' + error, 'error');
            }
        });
    }
}

function cancelBooking(bookingId) {
    if (confirm('Are you sure you want to cancel this booking? This will set the status to Cancelled and reset the tour price to 0.')) {
        showMessage('Cancelling booking...', 'info');
        jQuery.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'bst_cancel_booking',
                booking_id: bookingId,
                nonce: window.bstTourBookingsNonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Booking cancelled successfully', 'success');
                    // Reload page to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage('Failed to cancel booking: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error cancelling booking: ' + error, 'error');
            }
        });
    }
}

function deleteEmail(logId) {
    if (confirm('Are you sure you want to permanently delete this email from the log?')) {
        jQuery.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'bst_delete_email',
                log_id: logId,
                nonce: window.bstTourBookingsNonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove the row from the table
                    jQuery('tr[data-log-id="' + logId + '"]').fadeOut(300, function() {
                        jQuery(this).remove();
                    });
                } else {
                    showMessage('Failed to delete email: ' + response.data, 'error');
                }
            },
            error: function() {
                showMessage('Error deleting email', 'error');
            }
        });
    }
}

function closeEmailModal() {
    jQuery('#email-content-modal').hide();
}

// Send Email Modal Functions
function openSendEmailModal(bookingId) {
    // Reset editor ready flag
    window.bstEmailEditorReady = false;
    window.bstLastEmailField = null;
    
    // Show modal immediately
    jQuery('#send-email-modal').show();
    jQuery('#send-email-status').text('Loading...');
    
    // Reset to compose tab
    switchEmailTab('compose');
    
    // Reset email type to Ad Hoc
    jQuery('#email-type').val('Ad Hoc');
    
    // Reset template dropdown
    jQuery('#email-template-select').val('');
    
    // Load data immediately - no need to wait
    loadBookingDataForEmail(bookingId);
    
    // Load email templates
    loadEmailTemplates();
    
    // Initialize editor with readiness tracking
    initEmailContentEditor();
    
    // Try to restore draft
    restoreDraft(bookingId);
    
    // Start auto-save timer
    startAutoSave(bookingId);
}

// Auto-save draft functionality
var autoSaveTimer = null;

function startAutoSave(bookingId) {
    // Clear any existing timer
    if (autoSaveTimer) {
        clearInterval(autoSaveTimer);
    }
    
    // Auto-save every 30 seconds
    autoSaveTimer = setInterval(function() {
        saveDraft(bookingId);
    }, 30000);
}

function saveDraft(bookingId) {
    var draft = {
        subject: jQuery('#email-subject').val(),
        content: getEditorContent(),
        cc: jQuery('#email-cc').val(),
        emailType: jQuery('#email-type').val(),
        timestamp: new Date().getTime()
    };
    
    try {
        localStorage.setItem('bst_email_draft_' + bookingId, JSON.stringify(draft));
    } catch (e) {
        console.log('Could not save draft to localStorage:', e);
    }
}

function restoreDraft(bookingId) {
    try {
        var draftJson = localStorage.getItem('bst_email_draft_' + bookingId);
        if (draftJson) {
            var draft = JSON.parse(draftJson);
            
            // Check if draft is less than 24 hours old
            var age = new Date().getTime() - draft.timestamp;
            if (age < 24 * 60 * 60 * 1000) {
                // Ask user if they want to restore
                if (confirm('A draft email from ' + formatDraftAge(age) + ' was found. Restore it?')) {
                    jQuery('#email-subject').val(draft.subject || '');
                    jQuery('#email-cc').val(draft.cc || '');
                    jQuery('#email-type').val(draft.emailType || 'Ad Hoc');
                    
                    if (draft.content) {
                        // Wait a bit for editor to initialize
                        setTimeout(function() {
                            setEditorContent(draft.content);
                        }, 500);
                    }
                    
                    jQuery('#send-email-status').text('Draft restored').css('color', '#2271b1');
                    setTimeout(function() {
                        jQuery('#send-email-status').text('').css('color', '#666');
                    }, 3000);
                }
            } else {
                // Draft is too old, delete it
                localStorage.removeItem('bst_email_draft_' + bookingId);
            }
        }
    } catch (e) {
        console.log('Could not restore draft:', e);
    }
}

function formatDraftAge(milliseconds) {
    var minutes = Math.floor(milliseconds / 60000);
    if (minutes < 60) {
        return minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' ago';
    }
    var hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return hours + ' hour' + (hours !== 1 ? 's' : '') + ' ago';
    }
    return 'earlier today';
}

function clearDraft(bookingId) {
    try {
        localStorage.removeItem('bst_email_draft_' + bookingId);
    } catch (e) {
        console.log('Could not clear draft:', e);
    }
}

// Subject character counter
function updateSubjectCounter() {
    var subject = jQuery('#email-subject').val();
    var length = subject.length;
    jQuery('#subject-counter').text(length + '/70');
    
    if (length > 70) {
        jQuery('#subject-counter').css('color', '#d63638');
        jQuery('#subject-warning').show();
    } else if (length > 50) {
        jQuery('#subject-counter').css('color', '#dba617');
        jQuery('#subject-warning').hide();
    } else {
        jQuery('#subject-counter').css('color', '#666');
        jQuery('#subject-warning').hide();
    }
}

// CC email validation
function validateCcEmail(input) {
    var email = jQuery(input).val().trim();
    var icon = jQuery('#cc-validation-icon');
    
    if (email === '') {
        icon.hide();
        jQuery(input).css('border-color', '#ddd');
        return;
    }
    
    // Simple email validation regex
    var emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
    
    if (emailRegex.test(email)) {
        icon.html('<i class=\"fas fa-check-circle\" style=\"color: #00a32a;\"></i>').show();
        jQuery(input).css('border-color', '#00a32a');
    } else {
        icon.html('<i class=\"fas fa-times-circle\" style=\"color: #d63638;\"></i>').show();
        jQuery(input).css('border-color', '#d63638');
    }
}

// Attachment handling
jQuery(document).ready(function($) {
    $('#email-attachment').change(function() {
        var file = this.files[0];
        if (file) {
            $('#attachment-filename').text(file.name);
            $('#attachment-preview').show();
        }
    });
});

function clearAttachment() {
    jQuery('#email-attachment').val('');
    jQuery('#attachment-preview').hide();
    jQuery('#attachment-filename').text('');
}

function loadEmailTemplates() {
    console.log('Loading email templates...');
    jQuery.ajax({
        url: window.ajaxurl,
        method: 'POST',
        data: {
            action: 'bst_get_manual_email_templates',
            nonce: window.bstTourBookingsNonce
        },
        success: function(response) {
            console.log('Template response:', response);
            if (response.success && response.data) {
                var select = jQuery('#email-template-select');
                select.find('option:not(:first)').remove(); // Keep "Start from scratch" option
                
                if (response.data.length === 0) {
                    console.log('No templates found');
                    select.append('<option value="" disabled>No email templates available</option>');
                } else {
                    console.log('Found ' + response.data.length + ' templates');
                    jQuery.each(response.data, function(index, template) {
                        select.append('<option value="' + template.id + '" data-type="' + template.type + '">' + 
                                     template.title + '</option>');
                    });
                }
            } else {
                console.error('Template loading failed:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading templates:', error);
            console.error('XHR:', xhr);
        }
    });
}

function loadEmailTemplate(templateId) {
    if (!templateId) {
        // Reset to blank
        jQuery('#email-type').val('Ad Hoc');
        return;
    }
    
    // Check if there's existing content
    var currentContent = getEditorContent();
    var currentSubject = jQuery('#email-subject').val();
    
    if ((currentContent && currentContent.trim()) || (currentSubject && currentSubject.trim())) {
        if (!confirm('Loading a template will replace your current content. Continue?')) {
            jQuery('#email-template-select').val(''); // Reset dropdown
            return;
        }
    }
    
    // Load template content
    jQuery.ajax({
        url: window.ajaxurl,
        method: 'POST',
        data: {
            action: 'bst_get_email_template_content',
            template_id: templateId,
            booking_id: window.bstBookingData.id,
            nonce: window.bstTourBookingsNonce
        },
        success: function(response) {
            console.log('Template load response:', response);
            if (response.success && response.data) {
                console.log('Setting subject to:', response.data.subject);
                console.log('Setting content to (raw with tags):', response.data.content);
                
                // Set subject (raw with tags)
                jQuery('#email-subject').val(response.data.subject || '');
                updateSubjectCounter(); // Update character counter
                
                // Set content in editor (raw with tags so user can edit and send to different recipients)
                setEditorContent(response.data.content || '');
                
                // Set email type from template
                var selectedOption = jQuery('#email-template-select option:selected');
                var emailType = selectedOption.data('type') || 'Ad Hoc';
                console.log('Setting email type to:', emailType);
                jQuery('#email-type').val(emailType.charAt(0).toUpperCase() + emailType.slice(1));
            } else {
                console.error('Template load failed:', response);
                alert('Failed to load template: ' + (response.data || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Template load error:', xhr, status, error);
            alert('Error loading template: ' + error);
        }
    });
}

function switchEmailTab(tabName) {
    // Hide all tabs
    jQuery('.email-tab-content').hide();
    jQuery('.email-modal-tab').each(function() {
        jQuery(this).css({
            'background': 'white',
            'border-bottom-color': 'transparent',
            'color': '#666',
            'font-weight': 'normal'
        }).removeClass('active');
    });
    
    // Show selected tab
    jQuery('#' + tabName + '-tab').show();
    jQuery('.email-modal-tab[data-tab="' + tabName + '"]').css({
        'background': '#f0f0f0',
        'border-bottom-color': '#2271b1',
        'color': '#2271b1',
        'font-weight': '600'
    }).addClass('active');
    
    // If switching to preview, generate it
    if (tabName === 'preview') {
        generateEmailPreview();
    }
}

function toggleEmailPreview() {
    // Legacy function - just switch to preview tab
    switchEmailTab('preview');
}

function getEditorContent() {
    // Try to get content from TinyMCE if available
    if (typeof wp !== 'undefined' && wp.editor) {
        var content = wp.editor.getContent('email-content');
        if (content !== undefined) {
            return content;
        }
    } else if (typeof tinymce !== 'undefined') {
        var editor = tinymce.get('email-content');
        if (editor) {
            return editor.getContent();
        }
    }
    // Fall back to textarea
    return jQuery('#email-content').val();
}

function setEditorContent(content) {
    console.log('setEditorContent called with:', content.substring(0, 100) + '...');
    
    // Try to get existing TinyMCE editor
    if (typeof tinymce !== 'undefined') {
        var editor = tinymce.get('email-content');
        if (editor) {
            console.log('Using existing TinyMCE editor to set content');
            editor.setContent(content);
            return;
        }
    }
    
    // Try WordPress editor API
    if (typeof wp !== 'undefined' && wp.editor) {
        var wpEditor = wp.editor.getContent && wp.editor.getEditor ? wp.editor : null;
        if (wpEditor) {
            console.log('Using wp.editor to set content');
            // Set textarea value first
            jQuery('#email-content').val(content);
            // Try to update TinyMCE if it exists
            if (typeof tinymce !== 'undefined') {
                var editor = tinymce.get('email-content');
                if (editor) {
                    editor.setContent(content);
                }
            }
            return;
        }
    }
    
    // Fallback: set textarea value directly
    console.log('Using textarea fallback to set content');
    jQuery('#email-content').val(content);
    
    // Try to trigger any editor updates
    jQuery('#email-content').trigger('change');
}

function generateEmailPreview() {
    var content = getEditorContent();
    var subject = jQuery('#email-subject').val();
    var bookingId = window.bstBookingData ? window.bstBookingData.id : null;
    var ccValue = jQuery('#email-cc').val();
    
    // Update preview header
    var toSelect = jQuery('#email-to-select option:selected');
    var toText = toSelect.length ? toSelect.text() : 'Not selected';
    jQuery('#preview-to').text(toText);

    if (ccValue && ccValue.trim()) {
        jQuery('#preview-cc').text(ccValue.trim()).show();
    } else {
        jQuery('#preview-cc').text('').hide();
    }
    
    if (!content || !content.trim()) {
        jQuery('#email-preview-content').html('<p style="color: #999; font-style: italic; text-align: center; padding: 40px;">No content to preview. Type your message in the editor first.</p>');
        return;
    }
    
    if (!bookingId) {
        jQuery('#email-preview-content').html('<p style="color: #d63638; text-align: center; padding: 40px;">Error: No booking data available</p>');
        return;
    }
    
    jQuery('#email-preview-content').html('<p style="color: #999; font-style: italic; text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Generating preview...</p>');
    
    jQuery.ajax({
        url: window.ajaxurl,
        method: 'POST',
        data: {
            action: 'bst_preview_email_content',
            content: content,
            subject: subject,
            cc: ccValue,
            booking_id: bookingId,
            nonce: window.bstTourBookingsNonce
        },
        success: function(response) {
            if (response.success && response.data) {
                jQuery('#email-preview-content').html(response.data.content);
                // Update subject with processed merge fields
                if (response.data.subject) {
                    jQuery('#preview-subject').text(response.data.subject);
                } else {
                    jQuery('#preview-subject').text(subject || '(No subject)');
                }

                if (response.data.cc) {
                    jQuery('#preview-cc').text(response.data.cc).show();
                } else if (ccValue && ccValue.trim()) {
                    jQuery('#preview-cc').text(ccValue.trim()).show();
                } else {
                    jQuery('#preview-cc').text('').hide();
                }
            } else {
                jQuery('#email-preview-content').html('<p style="color: #d63638; text-align: center; padding: 40px;">Preview failed: ' + (response.data || 'Unknown error') + '</p>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Preview error:', error);
            jQuery('#email-preview-content').html('<p style="color: #d63638; text-align: center; padding: 40px;">Error generating preview: ' + error + '</p>');
        }
    });
}

function saveAsTemplate() {
    var subject = jQuery('#email-subject').val().trim();
    var content = getEditorContent();
    var emailType = jQuery('#email-type').val();
    
    if (!subject) {
        alert('Please enter an email subject before saving as template');
        return;
    }
    
    if (!content || !content.trim()) {
        alert('Please enter email content before saving as template');
        return;
    }
    
    var templateName = prompt('Enter a name for this template:', subject);
    
    if (!templateName) {
        return; // User cancelled
    }
    
    // Disable button and show status
    var btn = jQuery('#save-as-template-btn');
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
    
    jQuery.ajax({
        url: window.ajaxurl,
        method: 'POST',
        data: {
            action: 'bst_save_email_template',
            template_name: templateName,
            subject: subject,
            content: content,
            email_type: emailType,
            nonce: window.bstTourBookingsNonce
        },
        success: function(response) {
            if (response.success) {
                alert('Template "' + templateName + '" saved successfully!');
                // Reload template list
                loadEmailTemplates();
            } else {
                alert('Failed to save template: ' + (response.data || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error saving template:', error);
            alert('Error saving template: ' + error);
        },
        complete: function() {
            btn.prop('disabled', false).html(originalHtml);
        }
    });
}

function initEmailContentEditor() {
    // Remove any existing editor first to prevent duplicates
    if (typeof wp !== 'undefined' && wp.editor) {
        wp.editor.remove('email-content');
    } else if (typeof tinymce !== 'undefined') {
        var existingEditor = tinymce.get('email-content');
        if (existingEditor) {
            existingEditor.remove();
        }
    }
    
    // Increased delay to ensure cleanup and proper initialization
    setTimeout(function() {
        // Check if TinyMCE is available
        if (typeof wp !== 'undefined' && wp.editor && wp.editor.initialize) {
            try {
                // Use WordPress editor
                wp.editor.initialize('email-content', {
                    tinymce: {
                        wpautop: true,
                        plugins: 'charmap colorpicker lists textcolor link paste',
                        toolbar1: 'bold italic underline strikethrough | bullist numlist | link unlink | forecolor backcolor | undo redo | code',
                        height: 250,
                        content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
                        setup: function(editor) {
                            // Mark editor as ready
                            editor.on('init', function() {
                                window.bstEmailEditorReady = true;
                                console.log('wp.editor initialized successfully');
                            });
                        }
                    },
                    quicktags: true
                });
                return;
            } catch (e) {
                console.error('Error initializing wp.editor:', e);
            }
        }
        
        if (typeof tinymce !== 'undefined') {
            try {
                // Use standalone TinyMCE if available
                tinymce.init({
                    selector: '#email-content',
                    height: 250,
                    menubar: false,
                    plugins: [
                        'advlist autolink lists link charmap',
                        'searchreplace visualblocks code',
                        'insertdatetime paste code'
                    ],
                    toolbar: 'bold italic underline strikethrough | bullist numlist | link unlink | forecolor backcolor | undo redo | code',
                    content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
                    setup: function(editor) {
                        // Mark editor as ready once initialized
                        editor.on('init', function() {
                            window.bstEmailEditorReady = true;
                            console.log('TinyMCE editor initialized successfully');
                        });
                    }
                });
                return;
            } catch (e) {
                console.error('Error initializing tinymce:', e);
            }
        }
        
        // Add some basic styling to the textarea
        jQuery('#email-content').css({
            'font-family': 'Arial, sans-serif',
            'font-size': '14px',
            'line-height': '1.5'
        });
        window.bstEmailEditorReady = true; // Mark as ready when using textarea
    }, 200); // Increased from 50ms to 200ms for better initialization
}

function closeSendEmailModal() {
    // Reset editor ready flag
    window.bstEmailEditorReady = false;
    
    // Clear auto-save timer
    if (autoSaveTimer) {
        clearInterval(autoSaveTimer);
        autoSaveTimer = null;
    }
    
    // Clean up TinyMCE editor
    if (typeof wp !== 'undefined' && wp.editor) {
        wp.editor.remove('email-content');
    } else if (typeof tinymce !== 'undefined') {
        var editor = tinymce.get('email-content');
        if (editor) {
            editor.remove();
        }
    }
    
    // Hide modal and clear fields
    jQuery('#send-email-modal').hide();
    jQuery('#email-template-select').val('');
    jQuery('#email-to-select').val('').show();
    jQuery('#email-cc').val('');
    jQuery('#email-subject').val('');
    jQuery('#email-content').val('');
    jQuery('#email-type').val('Ad Hoc');
    jQuery('#send-email-status').text('').css('color', '#666');
    
    // Reset buttons
    jQuery('#send-email-btn, #send-test-btn').prop('disabled', false);
    jQuery('#send-email-btn').html('<i class="fas fa-paper-plane"></i> Send Email');
    
    // Reset character counter and validation
    jQuery('#subject-counter').text('0/70').css('color', '#666');
    jQuery('#subject-warning').hide();
    jQuery('#cc-validation-icon').hide();
    jQuery('#email-cc').css('border-color', '#ddd');
    
    // Hide preview panel
    jQuery('#email-preview-panel').hide();
    jQuery('#show-preview-btn').html('<i class="fas fa-eye"></i> Show Preview');
    
    // Clear merge fields sidebar
    jQuery('#merge-fields-categories').html('');
}

function loadBookingDataForEmail(bookingId) {
    // Check if modal elements exist
    var toSelect = jQuery('#email-to-select');
    if (toSelect.length === 0) {
        jQuery('#send-email-status').text('Error: Modal not properly initialized');
        return;
    }
    
    // Load both booking data and merge fields in parallel for faster loading
    loadRecipientData(bookingId);
    loadMergeFields();
}

function loadRecipientData(bookingId) {
    jQuery.ajax({
        url: window.ajaxurl,
        method: 'POST',
        timeout: 30000, // 30 second timeout - much more generous
        data: {
            action: 'bst_get_booking_email_data',
            booking_id: bookingId,
            nonce: window.bstTourBookingsNonce
        },
        success: function(response) {
            var toSelect = jQuery('#email-to-select');
            
            if (response.success) {
                var data = response.data;
                
                // Populate recipient dropdown with guest emails - optimized for speed
                var options = [];
                var hasEmails = false;
                
                if (data.guest1_email) {
                    options.push('<option value="' + data.guest1_email + '">' + data.guest1_name + ' (' + data.guest1_email + ')</option>');
                    hasEmails = true;
                }
                
                if (data.guest2_email && data.guest2_email !== data.guest1_email) {
                    options.push('<option value="' + data.guest2_email + '">' + data.guest2_name + ' (' + data.guest2_email + ')</option>');
                    hasEmails = true;
                }
                
                if (data.guest1_email && data.guest2_email && data.guest1_email !== data.guest2_email) {
                    options.push('<option value="' + data.guest1_email + ',' + data.guest2_email + '">Both Guests</option>');
                }
                
                if (!hasEmails) {
                    options.push('<option value="">No guest email addresses found</option>');
                    jQuery('#send-email-status').text('Warning: No guest email addresses found');
                } else {
                    jQuery('#send-email-status').text('Ready to compose email');
                }
                
                // Single DOM operation instead of multiple appends
                toSelect.html(options.join(''));
                
                // Auto-select first option if only one guest email
                if (hasEmails && options.length === 1) {
                    toSelect.val(toSelect.children().first().val());
                }
                
            } else {
                toSelect.html('<option value="">Error: ' + (response.data || 'Could not load guest data') + '</option>');
                jQuery('#send-email-status').text('Error: ' + (response.data || 'Could not load booking data'));
            }
        },
        error: function(xhr, status, error) {
            var errorMsg = 'Error loading booking data';
            if (status === 'timeout') {
                errorMsg = 'Timeout loading recipients';
            } else if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMsg = xhr.responseJSON.data;
            }
            jQuery('#send-email-status').text(errorMsg);
            var toSelect = jQuery('#email-to-select');
            toSelect.html('<option value="">Failed to load recipients</option>');
        }
    });
}

function sendAdHocEmail(skipConfirmation) {
    var bookingId = window.bstBookingData.id;
    var emailTo = jQuery('#email-to-select').val();
    var emailCc = jQuery('#email-cc').val().trim();
    var subject = jQuery('#email-subject').val().trim();
    var emailType = jQuery('#email-type').val();
    
    // Get message content using helper function
    var message = getEditorContent();
    if (message) {
        message = message.trim();
    }
    
    // Validation
    if (!emailTo) {
        showMessage('Please select a recipient email address', 'error');
        return;
    }
    
    if (!subject) {
        showMessage('Please enter an email subject', 'error');
        jQuery('#email-subject').focus();
        return;
    }
    
    if (!message) {
        showMessage('Please enter a message', 'error');
        if (typeof tinymce !== 'undefined') {
            var editor = tinymce.get('email-content');
            if (editor && !editor.isHidden()) {
                editor.focus();
            } else {
                jQuery('#email-content').focus();
            }
        } else {
            jQuery('#email-content').focus();
        }
        return;
    }
    
    // Validate CC email if provided
    if (emailCc) {
        var emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
        if (!emailRegex.test(emailCc)) {
            showMessage('Please enter a valid CC email address', 'error');
            jQuery('#email-cc').focus();
            return;
        }
    }
    
    // Show confirmation dialog (unless skipped for test email)
    if (!skipConfirmation) {
        var recipientName = jQuery('#email-to-select option:selected').text();
        var confirmMsg = 'Send email to ' + recipientName + '?\n\nSubject: ' + subject;
        if (emailCc) {
            confirmMsg += '\nCC: ' + emailCc;
        }
        
        if (!confirm(confirmMsg)) {
            return;
        }
    }
    
    // Clear draft after confirming send
    clearDraft(bookingId);
    
    // Proceed with sending
    performEmailSend(bookingId, emailTo, emailCc, subject, message, emailType, false);
}

function sendTestEmail() {
    var bookingId = window.bstBookingData.id;
    var emailCc = jQuery('#email-cc').val().trim();
    var subject = jQuery('#email-subject').val().trim();
    var emailType = jQuery('#email-type').val();
    
    // Get message content
    var message = getEditorContent();
    if (message) {
        message = message.trim();
    }
    
    // Validation
    if (!subject) {
        showMessage('Please enter an email subject', 'error');
        jQuery('#email-subject').focus();
        return;
    }
    
    if (!message) {
        showMessage('Please enter a message', 'error');
        return;
    }
    
    // Get current user email
    var testEmail = '<?php echo wp_get_current_user()->user_email; ?>';
    
    if (!testEmail) {
        showMessage('Could not determine your email address', 'error');
        return;
    }
    
    if (!confirm('Send test email to ' + testEmail + '?\n\nSubject: [TEST] ' + subject)) {
        return;
    }
    
    // Prepend [TEST] to subject
    var testSubject = '[TEST] ' + subject;
    
    // Perform email send
    performEmailSend(bookingId, testEmail, emailCc, testSubject, message, 'Test', true);
}

function performEmailSend(bookingId, emailTo, emailCc, subject, message, emailType, isTest) {
    // Disable send buttons and show status
    jQuery('#send-email-btn, #send-test-btn').prop('disabled', true);
    jQuery('#send-email-btn').html('<i class="fas fa-spinner fa-spin"></i> Sending...');
    jQuery('#send-email-status').text('Sending email...').css('color', '#666');
    
    // Prepare FormData to support file upload
    var formData = new FormData();
    formData.append('action', 'bst_send_adhoc_email_compose');
    formData.append('booking_id', bookingId);
    formData.append('email_to', emailTo);
    formData.append('email_cc', emailCc);
    formData.append('subject', subject);
    formData.append('message', message);
    formData.append('email_type', emailType);
    formData.append('nonce', window.bstTourBookingsNonce);
    
    // Add attachment if present
    var fileInput = document.getElementById('email-attachment');
    if (fileInput && fileInput.files.length > 0) {
        formData.append('email_attachment', fileInput.files[0]);
    }
    
    jQuery.ajax({
        url: window.ajaxurl,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                refreshEmailLog(bookingId);
                showMessage('Email sent successfully', 'success');
                
                if (isTest) {
                    // Test email - show success and re-enable buttons
                    jQuery('#send-email-status').text('Test email sent!').css('color', '#00a32a');
                    jQuery('#send-email-btn').html('<i class="fas fa-paper-plane"></i> Send Email').prop('disabled', false);
                    jQuery('#send-test-btn').prop('disabled', false);
                } else {
                    // Real email - close modal
                    closeSendEmailModal();
                }
            } else {
                // Error - show message and re-enable buttons
                jQuery('#send-email-status').text('Error: ' + (response.data || 'Failed to send email')).css('color', '#d63638');
                jQuery('#send-email-btn').html('<i class="fas fa-paper-plane"></i> Send Email').prop('disabled', false);
                jQuery('#send-test-btn').prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error sending email:', error);
            jQuery('#send-email-status').text('Error sending email: ' + error).css('color', '#d63638');
            jQuery('#send-email-btn').html('<i class="fas fa-paper-plane"></i> Send Email').prop('disabled', false);
            jQuery('#send-test-btn').prop('disabled', false);
        }
    });
}

function loadMergeFields() {
    var bookingId = window.bstBookingData ? window.bstBookingData.id : null;
    
    jQuery.ajax({
        url: window.ajaxurl,
        method: 'POST', 
        data: {
            action: 'bst_get_merge_fields',
            booking_id: bookingId,
            nonce: window.bstTourBookingsNonce
        },
        success: function(response) {
            if (response.success && response.data.fields) {
                populateMergeFieldsSidebar(response.data.fields);
            } else {
                jQuery('#merge-fields-categories').html('<p style="padding: 6px; font-size: 11px; color: red;">Failed to load merge fields</p>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading merge fields:', error);
            jQuery('#merge-fields-categories').html('<p style="padding: 6px; font-size: 11px; color: red;">Error loading merge fields</p>');
        }
    });
}

function populateMergeFieldsSidebar(fields) {
    var sidebar = jQuery('#merge-fields-sidebar');
    var categoriesContainer = jQuery('#merge-fields-categories');
    
    // If categories container doesn't exist, populate the main sidebar (fallback)
    var container = categoriesContainer.length > 0 ? categoriesContainer : sidebar;
    
    var html = '';
    
    // If we're using the fallback, include just the header (no search)
    if (categoriesContainer.length === 0) {
        html += '<h4>Merge Fields</h4>';
    }
    
    var hasAnyFields = false;
    
    // Create expandable sections like the email template
    for (var category in fields) {
        if (fields.hasOwnProperty(category) && category !== 'uncategorized') {
            // Check if category has any fields with values
            var categoryFields = [];
            
            for (var fieldKey in fields[category]) {
                if (fields[category].hasOwnProperty(fieldKey)) {
                    var field = fields[category][fieldKey];
                    var fieldLabel = field.label || '{{' + fieldKey + '}}';
                    var fieldValue = field.value || '';
                    
                    // Only include fields that have values
                    if (fieldValue && fieldValue !== '' && fieldValue !== 'undefined' && fieldValue !== '(empty)') {
                        categoryFields.push({
                            label: fieldLabel,
                            value: fieldValue,
                            key: fieldKey
                        });
                    }
                }
            }
            
            if (categoryFields.length > 0) {
                hasAnyFields = true;
                var categoryId = 'merge-category-' + category.replace(/[^a-z0-9]+/gi, '-').toLowerCase();
                
                // Create expandable section header
                html += '<div class="merge-field-category" style="margin-bottom: 6px; border: 1px solid #ddd; border-radius: 4px; background: white;">';
                html += '<div class="merge-category-header" style="background: #f8f9fa; padding: 8px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd;" onclick="toggleMergeCategory(\'' + categoryId + '\')">';
                html += '<span style="font-weight: bold; color: #1d2327; font-size: 11px;"><span id="' + categoryId + '-arrow" style="margin-right: 5px;">▶</span>' + category + '</span>';
                html += '<span style="color: #666; font-size: 10px;">(' + categoryFields.length + ' fields)</span>';
                html += '</div>';
                
                // Create collapsible content
                html += '<div id="' + categoryId + '" class="merge-category-content" style="display: none; padding: 6px;">';
                
                categoryFields.forEach(function(field) {
                    // Convert fieldValue to string and escape quotes
                    var fieldValueStr = String(field.value);
                    var escapedValue = fieldValueStr.replace(/"/g, '&quot;');
                    
                    html += '<div class="merge-field-item" data-field-label="' + field.label + '" data-field-value="' + escapedValue + '" style="padding: 4px 6px; cursor: pointer; border-radius: 2px; margin-bottom: 2px; border: 1px solid #e1e1e1; background: white; font-size: 10px; line-height: 1.2; display: block;">';
                    html += '<strong style="color: #2271b1;">' + field.label + '</strong><br>';
                    html += '<span style="color: #007cba;">→ ' + (fieldValueStr.length > 20 ? fieldValueStr.substring(0, 20) + '...' : fieldValueStr) + '</span>';
                    html += '</div>';
                });
                
                html += '</div></div>';
            }
        }
    }
    
    if (!hasAnyFields) {
        html += '<p style="color: #666; font-style: italic; text-align: center; margin-top: 20px; font-size: 11px;">No merge fields available.</p>';
    }
    
    container.html(html);
    
    // Track last focused field for merge field insertion
    jQuery(document).off('focusin.bstEmailField').on('focusin.bstEmailField', '#email-subject, #email-content, #email-cc', function() {
        window.bstLastEmailField = this.id;
    });

    // Add click handlers for merge fields - insert the merge tag
    // Use mousedown to detect focus BEFORE the click blurs the field
    jQuery(document).on('mousedown', '.merge-field-item', function(e) {
        e.preventDefault(); // Prevent blur of the active field during click
        
        var fieldLabel = jQuery(this).data('field-label');
        
        // Determine target at the moment of mousedown (before focus is lost)
        var target = determineInsertionTarget();
        
        insertMergeFieldValue(fieldLabel, target);
    });
    
    // Add hover effects
    jQuery('.merge-field-item').on('mouseenter', function() {
        jQuery(this).css('background-color', '#e9ecef');
    }).on('mouseleave', function() {
        jQuery(this).css('background-color', '#f8f9fa');
    });
}

/**
 * Determine insertion target at the moment of click (called from mousedown)
 * Checks actual focused element without relying on global state
 */
function determineInsertionTarget() {
    var focusedElement = document.activeElement;
    
    // Check if a form field is actually focused
    if (focusedElement) {
        var tagName = focusedElement.tagName.toLowerCase();
        var id = focusedElement.id;
        
        // If it's a textarea or input, and it's one of our email fields, use it
        if ((tagName === 'textarea' || tagName === 'input') && 
            (id === 'email-subject' || id === 'email-content' || id === 'email-cc')) {
            return id;
        }
    }

    // If TinyMCE editor has focus, target email content
    if (typeof tinymce !== 'undefined') {
        var editor = tinymce.get('email-content');
        if (editor && !editor.isHidden() && editor.hasFocus && editor.hasFocus()) {
            return 'email-content';
        }
    }

    // Use last focused field if available
    if (window.bstLastEmailField === 'email-subject' || window.bstLastEmailField === 'email-content' || window.bstLastEmailField === 'email-cc') {
        return window.bstLastEmailField;
    }
    
    // If nothing relevant is focused, check which fields exist and are visible
    var subjectField = jQuery('#email-subject');
    var contentField = jQuery('#email-content');
    
    // If both exist, prefer content (more common use case)
    if (contentField.length > 0 && contentField.is(':visible')) {
        return 'email-content';
    }

    // If textarea is hidden but TinyMCE exists, still target content
    if (typeof tinymce !== 'undefined') {
        var fallbackEditor = tinymce.get('email-content');
        if (fallbackEditor && !fallbackEditor.isHidden()) {
            return 'email-content';
        }
    }
    
    // If only subject exists, use that
    if (subjectField.length > 0 && subjectField.is(':visible')) {
        return 'email-subject';
    }
    
    // If only content exists, use that
    if (contentField.length > 0) {
        return 'email-content';
    }
    
    return null;
}

function insertMergeTextIntoField(fieldElement, text) {
    if (!fieldElement) {
        return false;
    }

    var tagName = fieldElement.tagName ? fieldElement.tagName.toLowerCase() : '';
    var inputType = fieldElement.type ? fieldElement.type.toLowerCase() : '';

    // Inputs like type="email" do not support selection; append to end.
    if (tagName === 'input' && inputType === 'email') {
        fieldElement.value = (fieldElement.value || '') + text;
        fieldElement.focus();
        return true;
    }

    try {
        var startPos = fieldElement.selectionStart;
        var endPos = fieldElement.selectionEnd;
        var beforeText = fieldElement.value.substring(0, startPos);
        var afterText = fieldElement.value.substring(endPos);

        fieldElement.value = beforeText + text + afterText;
        if (typeof fieldElement.setSelectionRange === 'function') {
            var newPos = startPos + text.length;
            fieldElement.setSelectionRange(newPos, newPos);
        }
        fieldElement.focus();
        return true;
    } catch (e) {
        fieldElement.value = (fieldElement.value || '') + text;
        fieldElement.focus();
        return true;
    }
}

/**
 * Determine which field should receive the merge field insertion
 * Used as fallback when target is not explicitly provided
 * Returns: 'email-subject', 'email-content', or null
 */
function getActiveMergeFieldTarget() {
    var focusedElement = document.activeElement;
    
    // Check if a form field is actually focused
    if (focusedElement) {
        var tagName = focusedElement.tagName.toLowerCase();
        var id = focusedElement.id;
        
        // If it's a textarea or input, and it's one of our email fields, use it
        if ((tagName === 'textarea' || tagName === 'input') && 
            (id === 'email-subject' || id === 'email-content')) {
            return id;
        }
    }
    
    // If nothing relevant is focused, check which fields exist and are visible
    var subjectField = jQuery('#email-subject');
    var contentField = jQuery('#email-content');
    
    // If both exist, prefer content (more common use case)
    if (contentField.length > 0 && contentField.is(':visible')) {
        return 'email-content';
    }
    
    // If only subject exists, use that
    if (subjectField.length > 0 && subjectField.is(':visible')) {
        return 'email-subject';
    }
    
    // If only content exists, use that
    if (contentField.length > 0) {
        return 'email-content';
    }
    
    return null;
}

/**
 * Insert merge field value into the appropriate target field
 * Handles both subject line and email content with validation
 */
function insertMergeFieldValue(value, targetField) {
    // Determine target field
    var target = targetField || getActiveMergeFieldTarget();
    
    if (!target) {
        console.error('No valid merge field target found');
        alert('Please click in the email subject or content field first.');
        return false;
    }
    
    // Convert value to string to avoid TinyMCE issues
    var stringValue = String(value);
    var contentInserted = false;
    
    // Special handling for subject line (plain input field)
    if (target === 'email-subject') {
        var subjectField = jQuery('#email-subject');
        if (subjectField.length > 0) {
            insertMergeTextIntoField(subjectField[0], stringValue);
            
            console.log('Inserted merge field into subject: ' + stringValue);
            showMergeFieldNotification('Inserted into Subject: ' + stringValue, 'success');
            return true;
        }
    }

    // Handle CC input field
    if (target === 'email-cc') {
        var ccField = jQuery('#email-cc');
        if (ccField.length > 0) {
            insertMergeTextIntoField(ccField[0], stringValue);
            
            console.log('Inserted merge field into CC: ' + stringValue);
            showMergeFieldNotification('Inserted into CC: ' + stringValue, 'success');
            return true;
        }
    }
    
    // Handling for email content - try TinyMCE first
    if (target === 'email-content') {
        // Try to insert into TinyMCE editor first
        if (typeof tinymce !== 'undefined') {
            var editor = tinymce.get('email-content');
            if (editor && !editor.isHidden() && editor.initialized) {
                try {
                    var beforeContent = editor.getContent();
                    editor.insertContent(stringValue);
                    var afterContent = editor.getContent();
                    
                    // Verify insertion was successful
                    if (afterContent !== beforeContent && afterContent.includes(stringValue)) {
                        console.log('Successfully inserted into TinyMCE: ' + stringValue);
                        showMergeFieldNotification('Inserted into Content: ' + stringValue, 'success');
                        return true;
                    } else {
                        console.warn('TinyMCE insertion may have failed, falling back to textarea');
                        contentInserted = false; // Force textarea fallback
                    }
                } catch (e) {
                    console.error('TinyMCE insertion error:', e);
                    contentInserted = false; // Fall through to other methods
                }
            }
        }
        
        // Try to insert into WordPress editor if TinyMCE didn't work
        if (!contentInserted && typeof wp !== 'undefined' && wp.editor) {
            try {
                wp.editor.insert('email-content', stringValue);
                console.log('Inserted via wp.editor: ' + stringValue);
                showMergeFieldNotification('Inserted into Content: ' + stringValue, 'success');
                return true;
            } catch (e) {
                console.error('wp.editor insertion error:', e);
                contentInserted = false; // Fall through to textarea method
            }
        }
        
        // Fallback to textarea
        var textarea = jQuery('#email-content');
        if (textarea.length > 0) {
            try {
                insertMergeTextIntoField(textarea[0], stringValue);

                // Verify insertion was successful
                var verifyContent = textarea.val();
                if (verifyContent.includes(stringValue)) {
                    // Trigger change event for any listeners
                    textarea.trigger('change');

                    console.log('Inserted into textarea fallback: ' + stringValue);
                    showMergeFieldNotification('Inserted into Content: ' + stringValue, 'success');
                    return true;
                }

                throw new Error('Content verification failed');
            } catch (e) {
                console.error('Textarea insertion failed:', e);
                showMergeFieldNotification('Could not insert merge field. Please try again or paste manually.', 'error');
                return false;
            }
        }
    }
    
    console.error('Could not find any valid target field for merge field insertion');
    showMergeFieldNotification('Could not insert merge field. Please try again.', 'error');
    return false;
}

/**
 * Show notification for merge field insertion with better styling
 */
function showMergeFieldNotification(message, type) {
    type = type || 'success';
    var $notification = jQuery('<div class="bst-merge-notification">' + message + '</div>');
    var bgColor = type === 'success' ? '#46b450' : '#d63638';
    var textColor = 'white';
    
    $notification.css({
        position: 'fixed',
        top: '32px',
        right: '20px',
        background: bgColor,
        color: textColor,
        padding: '12px 15px',
        borderRadius: '4px',
        boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
        zIndex: 100001,
        fontSize: '12px',
        animation: 'fadeInOut 3s ease-in-out'
    });
    
    jQuery('body').append($notification);
    
    setTimeout(function() {
        $notification.fadeOut(function() {
            $notification.remove();
        });
    }, 3000);
}

function insertMergeField(field) {
    // Try to insert into TinyMCE editor first
    if (typeof tinymce !== 'undefined') {
        var editor = tinymce.get('email-content');
        if (editor && !editor.isHidden()) {
            editor.insertContent(field);
            return;
        }
    }
    
    // Fallback to plain textarea
    var contentArea = jQuery('#email-content');
    if (contentArea.length > 0) {
        var currentContent = contentArea.val();
        var cursorPos = contentArea[0].selectionStart || currentContent.length;
        
        // Insert field at cursor position
        var newContent = currentContent.substring(0, cursorPos) + field + currentContent.substring(cursorPos);
        contentArea.val(newContent);
        
        // Move cursor after inserted field
        var newCursorPos = cursorPos + field.length;
        contentArea[0].setSelectionRange(newCursorPos, newCursorPos);
        contentArea.focus();
    }
}

function toggleMergeCategory(categoryId) {
    var content = jQuery('#' + categoryId);
    var arrow = jQuery('#' + categoryId + '-arrow');
    
    if (content.is(':visible')) {
        content.slideUp(200);
        arrow.text('▶');
    } else {
        content.slideDown(200);
        arrow.text('▼');
    }
}

function showAllMergeFields() {
    // Populate merge fields list
    var mergeFields = [
        '{{booking_id}}', '{{booking_status}}', '{{booking_name}}',
        '{{guest1_first_name}}', '{{guest1_last_name}}', '{{guest1_email}}', '{{guest1_phone}}',
        '{{guest2_first_name}}', '{{guest2_last_name}}', '{{guest2_email}}', '{{guest2_phone}}',
        '{{tour_name}}', '{{tour_start_date}}', '{{tour_end_date}}', '{{tour_duration}}',
        '{{total_cost}}', '{{deposit_amount}}', '{{balance_amount}}', '{{tour_currency}}',
        '{{package_people}}', '{{customer_first_name}}', '{{customer_last_name}}', '{{customer_email}}'
    ];
    
    var mergeFieldsList = jQuery('#merge-fields-list');
    mergeFieldsList.empty();
    
    jQuery.each(mergeFields, function(index, field) {
        var fieldElement = jQuery('<div style="padding: 5px; border: 1px solid #ddd; cursor: pointer; background: #f8f9fa;" onclick="copyMergeField(\'' + field + '\')">' + field + '</div>');
        mergeFieldsList.append(fieldElement);
    });
    
    jQuery('#merge-fields-modal').show();
}

function closeMergeFieldsModal() {
    jQuery('#merge-fields-modal').hide();
}

function copyMergeField(field) {
    // Copy to clipboard
    navigator.clipboard.writeText(field).then(function() {
        alert('Copied ' + field + ' to clipboard');
        closeMergeFieldsModal();
    }).catch(function(err) {
        // Fallback for older browsers
        var textArea = document.createElement('textarea');
        textArea.value = field;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Copied ' + field + ' to clipboard');
        closeMergeFieldsModal();
    });
}
</script>
