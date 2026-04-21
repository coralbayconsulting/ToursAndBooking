<?php
/**
 * Template for displaying the tour bookings listing page.
 *
 * Available variables:
 *   - $tour_bookings: An array of tour booking objects.
 */
?>

<?php if (isset($_GET['booking_created']) && $_GET['booking_created'] === '1') : ?>
    <div class="notice notice-success is-dismissible">
        <p>
        <?php 
        $booking_type = isset($_GET['booking_type']) ? $_GET['booking_type'] : 'booking';
        switch($booking_type) {
            case 'paper':
                echo 'Paper booking created successfully and is ready for editing.';
                break;
            case 'waiting_list':
                echo 'Booking added to waiting list successfully.';
                break;
            case 'reservation':
                echo 'Reservation created successfully.';
                break;
            default:
                echo 'Booking created successfully.';
        }
        ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['booking_deleted']) && $_GET['booking_deleted'] === '1') : ?>
    <div class="notice notice-success is-dismissible">
        <p>Booking deleted successfully.</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['tour_bookings_deleted']) && $_GET['tour_bookings_deleted'] == '1') : ?>
    <div class="notice notice-success is-dismissible">
        <p>All tour bookings and customers (ID > 180) have been deleted.</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['gf9_import']) && $_GET['gf9_import'] === 'done') : ?>
    <div class="notice notice-success is-dismissible">
        <p>GF9 import completed. 
        <?php if (isset($_GET['imported'])) echo intval($_GET['imported']) . ' bookings imported. '; ?>
        <?php if (isset($_GET['skipped'])) echo intval($_GET['skipped']) . ' entries skipped (already exist).'; ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['gf9_import']) && $_GET['gf9_import'] === 'error') : ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo bst_get_import_error_message('gf10', isset($_GET['message']) ? $_GET['message'] : 'unknown'); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['audit_error']) && $_GET['audit_error'] === '1') : ?>
    <div class="notice notice-error is-dismissible">
        <p>Error running GF9 audit - check error log for details.</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['csv_import']) && $_GET['csv_import'] === 'done') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php echo bst_get_import_success_message('gf9_csv', 
            isset($_GET['imported']) ? intval($_GET['imported']) : 0,
            isset($_GET['skipped']) ? intval($_GET['skipped']) : 0,
            isset($_GET['errors']) ? intval($_GET['errors']) : 0
        ); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['csv_import']) && $_GET['csv_import'] === 'error') : ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo bst_get_import_error_message('gf9_csv', isset($_GET['message']) ? $_GET['message'] : 'unknown'); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['gf10_import']) && $_GET['gf10_import'] === 'done') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php echo bst_get_import_success_message('gf10',
            isset($_GET['imported']) ? intval($_GET['imported']) : 0,
            isset($_GET['skipped']) ? intval($_GET['skipped']) : 0,
            0 // GF10 doesn't track errors separately
        ); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['gf10_import']) && $_GET['gf10_import'] === 'error') : ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo bst_get_import_error_message('gf10', isset($_GET['message']) ? $_GET['message'] : 'unknown'); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['paper_import']) && $_GET['paper_import'] === 'done') : ?>
    <div class="notice notice-success is-dismissible">
        <p>Paper booking import completed. 
        <?php if (isset($_GET['imported'])) echo intval($_GET['imported']) . ' bookings imported. '; ?>
        <?php if (isset($_GET['errors'])) echo intval($_GET['errors']) . ' errors encountered.'; ?>
        </p>
        <?php if (isset($_GET['has_details']) && $_GET['has_details'] === '1') : ?>
            <?php 
            $error_details = get_transient('bst_import_errors');
            if ($error_details && is_array($error_details)) : ?>
                <details style="margin-top: 10px;">
                    <summary style="cursor: pointer; font-weight: bold;">Show Error Details</summary>
                    <ul style="margin-top: 10px; padding-left: 20px;">
                        <?php foreach ($error_details as $error) : ?>
                            <li style="font-family: monospace; font-size: 12px; margin: 5px 0;"><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php 
                // Clear the transient after displaying
                delete_transient('bst_import_errors');
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['paper_import']) && $_GET['paper_import'] === 'error') : ?>
    <div class="notice notice-error is-dismissible">
        <p>Error importing paper bookings: 
        <?php 
        $message = isset($_GET['message']) ? $_GET['message'] : 'unknown error';
        switch($message) {
            case 'file_upload_failed':
                echo 'File upload failed.';
                break;
            case 'file_read_failed':
                echo 'Could not read uploaded file.';
                break;
            case 'no_data':
                echo 'No data found in file.';
                break;
            default:
                echo 'Unknown error occurred.';
        }
        ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['paper_delete']) && $_GET['paper_delete'] === 'done') : ?>
    <div class="notice notice-success is-dismissible">
        <p>Paper booking deletion completed. 
        <?php if (isset($_GET['deleted'])) echo intval($_GET['deleted']) . ' bookings deleted.'; ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['import_success']) && $_GET['import_success'] === '1') : ?>
    <div class="notice notice-success is-dismissible">
        <p>Import completed successfully! 
        <?php 
        $updated = isset($_GET['updated']) ? intval($_GET['updated']) : 0;
        $created = isset($_GET['created']) ? intval($_GET['created']) : 0;
        $errors = isset($_GET['errors']) ? intval($_GET['errors']) : 0;
        
        if ($updated > 0) echo $updated . ' records updated. ';
        if ($created > 0) echo $created . ' records created. ';
        if ($errors > 0) echo $errors . ' errors encountered.';
        ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['import_error'])) : ?>
    <div class="notice notice-error is-dismissible">
        <p>Import failed: 
        <?php 
        switch ($_GET['import_error']) {
            case 'file_upload':
                echo 'Error uploading file.';
                break;
            case 'file_not_found':
                echo 'Uploaded file not found.';
                break;
            case 'empty_file':
                echo 'File is empty or invalid.';
                break;
            case 'no_headers':
                echo 'No valid headers found in file.';
                break;
            case 'no_valid_headers':
                echo 'None of the headers match database field names.';
                break;
            case 'invalid_field_names':
                echo isset($_GET['message']) ? urldecode($_GET['message']) : 'Invalid field names found in file.';
                break;
            default:
                echo 'Unknown error occurred.';
        }
        ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['export_success']) && $_GET['export_success'] === '1') : ?>
    <div class="notice notice-success is-dismissible">
        <p>Export completed successfully! 
        <?php 
        $exported_count = isset($_GET['exported_count']) ? intval($_GET['exported_count']) : 0;
        $export_type = isset($_GET['export_type']) ? $_GET['export_type'] : 'unknown';
        
        echo $exported_count . ' record' . ($exported_count !== 1 ? 's' : '') . ' exported';
        
        switch ($export_type) {
            case 'main':
                echo ' (Standard Export)';
                break;
            case 'commission':
                echo ' (Commission Export)';
                break;
            case 'db_fields':
                echo ' (Database Fields Export)';
                break;
        }
        echo '.';
        ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['sync_sold_slots']) && $_GET['sync_sold_slots'] === 'success') : ?>
    <div class="notice notice-success is-dismissible">
        <p>Sold slots sync completed successfully! 
        <?php 
        $updated_count = isset($_GET['updated_count']) ? intval($_GET['updated_count']) : 0;
        echo $updated_count . ' tour date' . ($updated_count !== 1 ? 's' : '') . ' updated.';
        ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['sync_sold_slots']) && $_GET['sync_sold_slots'] === 'error') : ?>
    <div class="notice notice-error is-dismissible">
        <p>Error during sold slots sync. 
        <?php 
        $error_count = isset($_GET['error_count']) ? intval($_GET['error_count']) : 0;
        if ($error_count > 0) {
            echo $error_count . ' error' . ($error_count !== 1 ? 's' : '') . ' encountered.';
        } else {
            echo 'Please check the error log for details.';
        }
        ?>
        </p>
    </div>
<?php endif; ?>

<?php
// Get filter and sort parameters early for the export button
$selected_tour = isset($_GET['filter_tour_id']) ? intval($_GET['filter_tour_id']) : 0;
// Filter parameters are now handled in the main plugin class
// We just need them here for the form display
$selected_tour = isset($_GET['filter_tour_id']) ? intval($_GET['filter_tour_id']) : 0;
$selected_date = isset($_GET['filter_tour_date_id']) ? intval($_GET['filter_tour_date_id']) : 0;
$selected_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Tour Bookings</h1>

<!-- Render the tour and date filter dropdowns -->
<?php
// Build list of ALL tours and dates (not just those with bookings)
$all_tours_list = array();
$tour_dates_data = array();

// Get all tours (all statuses used in admin scan, not publish-only)
$all_tours = get_posts(array(
    'post_type' => 'tour',
    'post_status' => function_exists( 'bst_post_statuses_for_admin_scan' ) ? bst_post_statuses_for_admin_scan( 'tour' ) : 'any',
    'numberposts' => -1,
    'orderby' => 'title',
    'order' => 'ASC'
));

foreach ($all_tours as $tour) {
    $all_tours_list[$tour->ID] = $tour->post_title;
    
    // Get all tour dates for this tour
    $tour_dates = get_posts(array(
        'post_type' => 'tour-date',
        'post_status' => function_exists( 'bst_post_statuses_for_admin_scan' ) ? bst_post_statuses_for_admin_scan( 'tour-date' ) : 'any',
        'numberposts' => -1,
        'meta_query' => array(
            array(
                'key' => 'tour',
                'value' => $tour->ID,
                'compare' => '='
            )
        ),
        'meta_key' => 'start_date',
        'orderby' => 'meta_value',
        'order' => 'ASC'
    ));
    
    if (!empty($tour_dates)) {
        $tour_dates_data[$tour->ID] = array();
        
        foreach ($tour_dates as $tour_date) {
            $start_date = get_post_meta($tour_date->ID, 'start_date', true);
            $end_date = get_post_meta($tour_date->ID, 'end_date', true);
            
            if ($start_date && $end_date) {
                $tour_date_text = (date('M', strtotime($start_date)) == date('M', strtotime($end_date)))
                    ? date('j', strtotime($start_date)) . '-' . date('j M Y', strtotime($end_date))
                    : date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
            } elseif ($start_date) {
                $tour_date_text = date('j M Y', strtotime($start_date));
            } else {
                $tour_date_text = $tour_date->post_title;
            }
            
            $tour_dates_data[$tour->ID][$tour_date->ID] = array(
                'text' => $tour_date_text,
                'sort_date' => $start_date ? $start_date : '9999-12-31'
            );
        }
        
        // Ensure dates for this tour are sorted ascending by start date
        uasort(
            $tour_dates_data[$tour->ID],
            function ( $a, $b ) {
                $a_ts = isset( $a['sort_date'] ) ? strtotime( $a['sort_date'] ) : PHP_INT_MAX;
                $b_ts = isset( $b['sort_date'] ) ? strtotime( $b['sort_date'] ) : PHP_INT_MAX;
                if ( $a_ts === $b_ts ) {
                    return 0;
                }
                return ( $a_ts < $b_ts ) ? -1 : 1;
            }
        );
    }
}

// Get selected tour name for bulk email modal
$selected_tour_name = '';
if ($selected_tour > 0 && isset($all_tours_list[$selected_tour])) {
    $selected_tour_name = $all_tours_list[$selected_tour];
    if ($selected_date > 0 && isset($tour_dates_data[$selected_tour][$selected_date])) {
        $selected_tour_name .= ' - ' . $tour_dates_data[$selected_tour][$selected_date]['text'];
    }
}

// Since data is already filtered at SQL level, we just need to build status options
// for the filter dropdown based on what's available for current tour/date selection
$statuses_with_bookings = array();
$show_status_filter = false;

if (is_array($tour_bookings) && !empty($tour_bookings)) {
    // Get all possible statuses for current tour/date filter (ignoring status filter)
    global $wpdb;
    $tour_booking_table = $wpdb->prefix . "bst_tour_booking";
    
    $where_conditions_for_status = array();
    $where_params_for_status = array();
    
    if ($selected_tour > 0) {
        $where_conditions_for_status[] = "tour_id = %d";
        $where_params_for_status[] = $selected_tour;
    }
    
    if ($selected_date > 0) {
        $where_conditions_for_status[] = "tour_date_id = %d";
        $where_params_for_status[] = $selected_date;
    }
    
    $where_clause_for_status = '';
    if (!empty($where_conditions_for_status)) {
        $where_clause_for_status = ' WHERE ' . implode(' AND ', $where_conditions_for_status);
    }
    
    $status_sql = "SELECT DISTINCT booking_status FROM $tour_booking_table" . $where_clause_for_status . " ORDER BY booking_status";
    
    if (!empty($where_params_for_status)) {
        $status_results = $wpdb->get_col($wpdb->prepare($status_sql, $where_params_for_status));
    } else {
        $status_results = $wpdb->get_col($status_sql);
    }
    
    $statuses_with_bookings = array_filter($status_results, function($status) {
        return !empty(trim($status));
    });
    
    // Show status filter when multiple statuses exist, or when "All Active" is selected (needs dropdown to stay visible).
    $show_status_filter = count($statuses_with_bookings) > 1 || $selected_status === 'all_active';
}

// The filtered bookings are the tour_bookings themselves (already filtered and sorted by SQL)
$filtered_bookings = $tour_bookings;
?>

<!-- Filter Form and Export Button Container -->
<div class="cbc-bookings-header-bar">
    <!-- Filter Toggle Tabs (WordPress style) -->
    <div class="nav-tab-wrapper cbc-bookings-header-tabs">
        <a href="#" id="toggle-filters" class="nav-tab nav-tab-active">Filter by Tour/Date/Status</a>
        <a href="#" id="toggle-search" class="nav-tab">Search by Name</a>
    </div>
    
    <!-- Export Button -->
    <div class="cbc-bookings-header-export">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="bst_export_bookings_excel">
            <input type="hidden" name="filter_tour_id" value="<?php echo esc_attr($selected_tour); ?>">
            <input type="hidden" name="filter_tour_date_id" value="<?php echo esc_attr($selected_date); ?>">
            <input type="hidden" name="filter_status" value="<?php echo esc_attr($selected_status); ?>">
            <input type="hidden" name="search" value="<?php echo esc_attr($search); ?>">
            <input type="hidden" name="sort_by" value="<?php echo esc_attr($sort_by); ?>">
            <input type="hidden" name="sort_order" value="<?php echo esc_attr($sort_order); ?>">
            <?php wp_nonce_field('bst_export_bookings', 'export_nonce'); ?>
            <button type="submit" class="button button-primary bst-export-button">
                📊 Export Selection to Excel
            </button>
        </form>
    </div>
</div>

<!-- Filter Forms -->
<div class="cbc-bookings-filters-wrapper">
    <!-- Standard Filters Form -->
    <form method="get" id="bst-tour-filter-form" style="<?php echo !empty($search) ? 'display: none;' : 'display: block;'; ?>">
        <input type="hidden" name="page" value="bst-tour-bookings">
        <input type="hidden" name="sort_by" value="<?php echo esc_attr($sort_by); ?>">
        <input type="hidden" name="sort_order" value="<?php echo esc_attr($sort_order); ?>">
        
        <div class="cbc-bookings-filters-row">
            <div class="cbc-bookings-filter-group">
                <label for="filter_tour_id" class="cbc-bookings-filter-label">Tour:</label>
                <select name="filter_tour_id" id="filter_tour_id">
                    <option value="0">All Tours</option>
                    <?php foreach ($all_tours_list as $tour_id => $tour_name): ?>
                        <option value="<?php echo esc_attr($tour_id); ?>" <?php selected($selected_tour, $tour_id); ?>>
                            <?php echo esc_html($tour_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="date-filter-container" class="cbc-bookings-filter-group" style="<?php echo $selected_tour == 0 ? 'display:none;' : ''; ?>">
                <label for="filter_tour_date_id" class="cbc-bookings-filter-label">Date:</label>
                <select name="filter_tour_date_id" id="filter_tour_date_id">
                    <option value="0">All Dates</option>
                    <?php if ($selected_tour > 0 && isset($tour_dates_data[$selected_tour])): ?>
                        <?php foreach ($tour_dates_data[$selected_tour] as $date_id => $date_info): ?>
                            <option value="<?php echo esc_attr($date_id); ?>" <?php selected($selected_date, $date_id); ?>>
                                <?php echo esc_html($date_info['text']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <?php if ($show_status_filter): ?>
            <div class="cbc-bookings-filter-group">
                <label for="filter_status" class="cbc-bookings-filter-label">Status:</label>
                <select name="filter_status" id="filter_status">
                    <option value="">All Statuses</option>
                    <option value="all_active" <?php selected( $selected_status, 'all_active' ); ?>>All Active (No WL/Cancelled)</option>
                    <?php foreach ($statuses_with_bookings as $status): ?>
                        <option value="<?php echo esc_attr($status); ?>" <?php selected($selected_status, $status); ?>>
                            <?php echo esc_html($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($search) || $selected_tour > 0 || $selected_date > 0 || !empty($selected_status)): ?>
            <div class="cbc-bookings-filter-group">
                <a href="<?php echo admin_url('admin.php?page=bst-tour-bookings'); ?>" class="button">Clear</a>
            </div>
            <?php endif; ?>
        </div>
    </form>
    
    <!-- Search Form -->
    <form method="get" id="bst-search-form" style="<?php echo (!empty($search) || isset($_GET['search_mode'])) ? '' : 'display: none;'; ?>">
        <input type="hidden" name="page" value="bst-tour-bookings">
        <input type="hidden" name="sort_by" value="<?php echo esc_attr($sort_by); ?>">
        <input type="hidden" name="sort_order" value="<?php echo esc_attr($sort_order); ?>">
        
        <div class="cbc-bookings-search-row">
            <div class="cbc-bookings-filter-group">
                <label for="search" class="cbc-bookings-filter-label" style="font-weight: 600;">Name:</label>
                <input type="search" id="search" name="search" value="<?php echo esc_attr($search); ?>" 
                       placeholder="Search by name..." class="cbc-bookings-search-input">
            </div>
            
            <div class="cbc-bookings-search-actions">
                <input type="submit" class="button button-primary" value="Search">
                <a href="<?php echo admin_url('admin.php?page=bst-tour-bookings&search_mode=1'); ?>" class="button">Clear</a>
            </div>
        </div>
    </form>
</div>

<!-- Booking Action Buttons -->
<div id="booking-actions" class="cbc-bookings-quick-actions" style="display: <?php echo ($selected_tour > 0 && $selected_date > 0) ? 'block' : 'none'; ?>;">
    <div class="cbc-bookings-quick-actions-inner">
        <h4 class="cbc-bookings-quick-actions-title">Quick Actions for Selected Tour & Date</h4>
        <div class="cbc-bookings-quick-actions-row">
            <a href="<?php echo esc_url(admin_url('admin.php?page=bst-tour-bookings&action=new&type=waiting_list&filter_tour_id=' . $selected_tour . '&filter_tour_date_id=' . $selected_date)); ?>" 
               class="button">
                ⏳ Add to Waiting List
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=bst-tour-bookings&action=new&type=reservation&filter_tour_id=' . $selected_tour . '&filter_tour_date_id=' . $selected_date)); ?>" 
               class="button">
                📅 Add Reservation
            </a>
            <button type="button" id="mark-complete-btn" class="button button-secondary cbc-bookings-mark-complete-btn">
                ✅ Mark Booked/Finalized as Completed
            </button>
            <button type="button" id="copy-emails-btn" class="button button-secondary cbc-bookings-copy-emails-btn">
                📧 Copy All Emails to Clipboard
            </button>
            <button type="button" id="send-email-btn" class="button button-primary cbc-bookings-send-email-btn" 
                    <?php if (empty($selected_date)): ?>disabled title="Please select a tour date first"<?php endif; ?>
                    style="margin-left: 8px;">
                ✉️ Send Email
            </button>
            
            <!-- Export List -->
            <div class="cbc-bookings-quick-actions-export">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: flex; gap: 8px; align-items: center;">
                    <input type="hidden" name="action" value="bst_export_custom_list">
                    <input type="hidden" name="filter_tour_id" value="<?php echo esc_attr($selected_tour); ?>">
                    <input type="hidden" name="filter_tour_date_id" value="<?php echo esc_attr($selected_date); ?>">
                    <input type="hidden" name="filter_status" value="<?php echo esc_attr($selected_status); ?>">
                    <input type="hidden" name="sort_by" value="<?php echo esc_attr($sort_by); ?>">
                    <input type="hidden" name="sort_order" value="<?php echo esc_attr($sort_order); ?>">
                    <?php wp_nonce_field('bst_export_custom_list', 'export_nonce'); ?>
                    
                    <label for="list-type" style="font-weight: 600; white-space: nowrap;">Export List:</label>
                    <select name="list_type" id="list-type" required style="min-width: 150px;">
                        <option value="">Choose a List...</option>
                        <option value="rooming">Rooming List</option>
                        <option value="vehicle">Vehicle List</option>
                        <option value="shirt">Shirt Size List</option>
                        <option value="travel_details">Travel Details List</option>
                    </select>
                    
                    <button type="submit" class="button button-primary">
                        📄 Export
                    </button>
                </form>
            </div>
        </div>
        <div>
            <span id="mark-complete-info" class="cbc-bookings-quick-actions-help">
                The "Mark as Completed" button will update all Booked and Finalized bookings in this selection to Completed status.
            </span>
        </div>
    </div>
</div>

<script>
// Function to show WordPress-style admin notices
function showAdminNotice(message, type = 'info') {
    // Remove any existing notices
    const existingNotices = document.querySelectorAll('.bst-admin-notice');
    existingNotices.forEach(notice => notice.remove());
    
    // Create notice element
    const notice = document.createElement('div');
    notice.className = `bst-admin-notice notice notice-${type}`;
    notice.style.cssText = 'position: fixed; top: 32px; left: 50%; transform: translateX(-50%); z-index: 10000; max-width: 600px; margin: 0; padding: 12px 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);';
    
    const paragraph = document.createElement('p');
    paragraph.textContent = message;
    paragraph.style.margin = '0';
    notice.appendChild(paragraph);
    
    // Add to page
    document.body.appendChild(notice);
    
    // Auto-remove after 4 seconds (unless it's a success message that will reload)
    if (type !== 'success') {
        setTimeout(() => {
            if (notice.parentNode) {
                notice.remove();
            }
        }, 4000);
    }
}

// Store tour dates data for JavaScript filtering
const tourDatesData = <?php echo wp_json_encode($tour_dates_data); ?>;

// Toggle between filter modes with WordPress-style tabs
document.getElementById('toggle-filters').addEventListener('click', function(e) {
    e.preventDefault();
    
    // Update tab appearance
    this.classList.add('nav-tab-active');
    document.getElementById('toggle-search').classList.remove('nav-tab-active');
    
    // Show/hide forms
    document.getElementById('bst-tour-filter-form').style.display = 'block';
    document.getElementById('bst-search-form').style.display = 'none';
});

document.getElementById('toggle-search').addEventListener('click', function(e) {
    e.preventDefault();
    
    // Update tab appearance
    this.classList.add('nav-tab-active');
    document.getElementById('toggle-filters').classList.remove('nav-tab-active');
    
    // Show/hide forms
    document.getElementById('bst-tour-filter-form').style.display = 'none';
    document.getElementById('bst-search-form').style.display = 'block';
    document.getElementById('search').focus();
});

// Set initial toggle state based on current filters
if (<?php echo wp_json_encode(!empty($search) || isset($_GET['search_mode'])); ?>) {
    document.getElementById('toggle-search').classList.add('nav-tab-active');
    document.getElementById('toggle-filters').classList.remove('nav-tab-active');
    document.getElementById('bst-search-form').style.display = 'block';
    document.getElementById('bst-tour-filter-form').style.display = 'none';
}

document.getElementById('filter_tour_id').addEventListener('change', function() {
    const tourId = this.value;
    const dateContainer = document.getElementById('date-filter-container');
    const dateSelect = document.getElementById('filter_tour_date_id');
    
    if (tourId == '0') {
        // Hide date filter when "All Tours" is selected
        dateContainer.style.display = 'none';
        dateSelect.value = '0';
        
        // Also reset the tour date filter in the URL by setting it to 0
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('filter_tour_id', '0');
        currentUrl.searchParams.set('filter_tour_date_id', '0');
        window.location.href = currentUrl.toString();
        return; // Don't proceed with the rest of the function
    } else {
        // Show date filter and populate with dates for selected tour
        dateContainer.style.display = 'flex';
        dateContainer.style.alignItems = 'center';
        
        // Clear existing options except "All Dates"
        dateSelect.innerHTML = '<option value="0">All Dates</option>';
        
        // Add dates for selected tour, sorted by start date (sort_date)
        if (tourDatesData[tourId]) {
            const entries = Object.entries(tourDatesData[tourId]);
            entries.sort(function(a, b) {
                const aDate = (a[1] && a[1].sort_date) ? a[1].sort_date : '';
                const bDate = (b[1] && b[1].sort_date) ? b[1].sort_date : '';
                return aDate.localeCompare(bDate);
            });
            
            entries.forEach(function(entry) {
                const dateId = entry[0];
                const dateInfo = entry[1];
                const option = document.createElement('option');
                option.value = dateId;
                option.textContent = dateInfo.text;
                dateSelect.appendChild(option);
            });
        }
    }
});

// Auto-submit form when selections change
document.getElementById('filter_tour_id').addEventListener('change', function() {
    updateBookingActions();
    // Small delay to ensure the date filter is properly updated before submitting
    setTimeout(() => {
        document.getElementById('bst-tour-filter-form').submit();
    }, 50);
});

document.getElementById('filter_tour_date_id').addEventListener('change', function() {
    updateBookingActions();
    document.getElementById('bst-tour-filter-form').submit();
});

// Function to update booking action buttons visibility and URLs
function updateBookingActions() {
    const tourId = document.getElementById('filter_tour_id').value;
    const dateId = document.getElementById('filter_tour_date_id').value;
    const actionsDiv = document.getElementById('booking-actions');
    
    // Update booking actions (paper bookings, etc.)
    if (actionsDiv) {
        if (tourId > 0 && dateId > 0) {
            // Show actions and update URLs
            actionsDiv.style.display = 'block';
            
            const baseUrl = '<?php echo admin_url('admin.php?page=bst-tour-bookings&action=new'); ?>';
            const urlParams = '&filter_tour_id=' + tourId + '&filter_tour_date_id=' + dateId;
            
            // Update button URLs
            const paperBtn = actionsDiv.querySelector('a[href*="type=paper"]');
            const waitingBtn = actionsDiv.querySelector('a[href*="type=waiting_list"]');
            const reservationBtn = actionsDiv.querySelector('a[href*="type=reservation"]');
            
            if (paperBtn) paperBtn.href = baseUrl + '&type=paper' + urlParams;
            if (waitingBtn) waitingBtn.href = baseUrl + '&type=waiting_list' + urlParams;
            if (reservationBtn) reservationBtn.href = baseUrl + '&type=reservation' + urlParams;
            
            // Update info text
            const infoSpan = actionsDiv.querySelector('span');
            if (infoSpan && tourId > 0) {
                const tourSelect = document.getElementById('filter_tour_id');
                const dateSelect = document.getElementById('filter_tour_date_id');
                const tourText = tourSelect.options[tourSelect.selectedIndex].text;
                const dateText = dateSelect.options[dateSelect.selectedIndex].text;
                
                let infoText = 'The "Mark as Completed" button will update all Booked and Finalized bookings for ' + tourText;
                if (dateId > 0 && dateText !== 'All Dates') {
                    infoText += ' on ' + dateText;
                }
                infoText += ' to Completed status.';
                infoSpan.textContent = infoText;
            }
        } else {
            actionsDiv.style.display = 'none';
        }
    }
}

// Only add status filter event listener if the status filter exists
const statusFilter = document.getElementById('filter_status');
if (statusFilter) {
    statusFilter.addEventListener('change', function() {
        document.getElementById('bst-tour-filter-form').submit();
    });
}

// Handle Mark as Complete button
const markCompleteBtn = document.getElementById('mark-complete-btn');
if (markCompleteBtn) {
    markCompleteBtn.addEventListener('click', function() {
        // Get current filter values
        const filterTourId = document.getElementById('filter_tour_id').value;
        const filterDateId = document.getElementById('filter_tour_date_id').value;
        const filterStatus = document.getElementById('filter_status') ? document.getElementById('filter_status').value : '';

        // First, get the count of bookings that would be updated
        const countAjaxData = {
            action: 'bst_get_bookings_count_for_completion',
            nonce: '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>',
            filter_tour_id: filterTourId,
            filter_tour_date_id: filterDateId,
            filter_status: filterStatus
        };

        // Disable button temporarily while getting count
        markCompleteBtn.disabled = true;
        markCompleteBtn.textContent = 'Checking...';

        // Get count first
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(countAjaxData)
        })
        .then(response => response.json())
        .then(countData => {
            // Re-enable button
            markCompleteBtn.disabled = false;
            markCompleteBtn.textContent = '✅ Mark Booked/Finalized as Completed';

            if (countData.success) {
                const count = countData.data.count;
                const tourInfo = countData.data.tour_info;
                const dateInfo = countData.data.date_info;

                if (count === 0) {
                    showAdminNotice('No Booked or Finalized bookings found in the current selection.', 'info');
                    return;
                }

                // Build confirmation message
                let confirmMessage = `Are you sure you want to mark ${count} booking${count !== 1 ? 's' : ''} as Completed?\n\n`;
                
                if (tourInfo) {
                    confirmMessage += `Tour: ${tourInfo}\n`;
                }
                if (dateInfo) {
                    confirmMessage += `Date: ${dateInfo}\n`;
                }
                
                confirmMessage += '\nThis action cannot be undone.';

                if (!confirm(confirmMessage)) {
                    return;
                }

                // Proceed with the update
                markCompleteBtn.disabled = true;
                markCompleteBtn.textContent = 'Processing...';

                // Prepare AJAX data for the actual update
                const updateAjaxData = {
                    action: 'bst_mark_bookings_complete',
                    nonce: '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>',
                    filter_tour_id: filterTourId,
                    filter_tour_date_id: filterDateId,
                    filter_status: filterStatus
                };

                // Send AJAX request for update
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(updateAjaxData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success notification and reload
                        showAdminNotice(data.data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500); // Show notice for 1.5 seconds before reload
                    } else {
                        showAdminNotice('Error: ' + data.data, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAdminNotice('An error occurred while updating bookings.', 'error');
                })
                .finally(() => {
                    // Re-enable button
                    markCompleteBtn.disabled = false;
                    markCompleteBtn.textContent = '✅ Mark Booked/Finalized as Completed';
                });
            } else {
                showAdminNotice('Error getting booking count: ' + countData.data, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAdminNotice('An error occurred while checking booking count.', 'error');
            markCompleteBtn.disabled = false;
            markCompleteBtn.textContent = '✅ Mark Booked/Finalized as Completed';
        });
    });
}

// Handle Copy Emails button
const copyEmailsBtn = document.getElementById('copy-emails-btn');
if (copyEmailsBtn) {
    copyEmailsBtn.addEventListener('click', function() {
        // Get current filter values
        const filterTourId = document.getElementById('filter_tour_id').value;
        const filterDateId = document.getElementById('filter_tour_date_id').value;
        const filterStatus = document.getElementById('filter_status') ? document.getElementById('filter_status').value : '';

        // Disable button temporarily
        copyEmailsBtn.disabled = true;
        copyEmailsBtn.textContent = 'Getting emails...';

        // Prepare AJAX data
        const ajaxData = {
            action: 'bst_get_booking_emails',
            nonce: '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>',
            filter_tour_id: filterTourId,
            filter_tour_date_id: filterDateId,
            filter_status: filterStatus
        };

        // Send AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(ajaxData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const emails = data.data.emails;
                const emailString = emails.join(', ');
                
                // Copy to clipboard - use fallback method for compatibility
                const textArea = document.createElement('textarea');
                textArea.value = emailString;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '0';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    const successful = document.execCommand('copy');
                    if (successful) {
                        let message = '✓ ' + emails.length + ' email address' + (emails.length !== 1 ? 'es' : '') + ' copied to clipboard!';
                        if (data.data.excluded_note) {
                            message += ' (' + data.data.excluded_note + ')';
                        }
                        showAdminNotice(message, 'success');
                    } else {
                        showAdminNotice('Failed to copy emails. Please try manually selecting and copying.', 'error');
                    }
                } catch (err) {
                    console.error('Copy failed:', err);
                    showAdminNotice('Failed to copy emails to clipboard. Please try again.', 'error');
                }
                
                document.body.removeChild(textArea);
            } else {
                showAdminNotice('Error: ' + data.data, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAdminNotice('An error occurred while getting email addresses.', 'error');
        })
        .finally(() => {
            // Re-enable button
            copyEmailsBtn.disabled = false;
            copyEmailsBtn.textContent = '📧 Copy All Emails to Clipboard';
        });
    });
}
</script>

<?php
// Calculate record count for display (data already filtered and sorted at SQL level)
$total_records = count($filtered_bookings);

// Calculate totals for package guests, vehicles and rooms when tour and date are selected
$totals_text = '';
if ($selected_tour > 0 && $selected_date > 0 && !empty($filtered_bookings)) {
    $total_guests = 0;
    $total_vehicles = 0;
    $total_rooms = 0;
    
    // Exclude certain statuses from totals calculation
    $excluded_statuses = array('Waiting List', 'Transfer', 'Cancelled');
    
    foreach ($filtered_bookings as $booking) {
        // Skip bookings with excluded statuses
        if (in_array($booking->booking_status, $excluded_statuses)) {
            continue;
        }
        
        $total_guests += intval($booking->package_people ?? 0);
        $total_vehicles += intval($booking->package_vehicles ?? 0);
        $total_rooms += floatval($booking->package_rooms ?? 0);
    }
    
    // Format rooms to only show decimal if needed
    $rooms_display = ($total_rooms == floor($total_rooms)) ? number_format($total_rooms, 0) : number_format($total_rooms, 1);
    
    $totals_text = sprintf(' (Guests: %d, Vehicles: %d, Rooms: %s)', $total_guests, $total_vehicles, $rooms_display);
}
?>

<!-- Record Count Display -->
<div style="margin: 10px 0; padding: 8px 12px; background: #f0f0f1; border-left: 4px solid #72aee6; font-weight: 500;">
    Showing <?php echo number_format($total_records); ?> record<?php echo $total_records !== 1 ? 's' : ''; ?> in selection<?php echo $totals_text; ?>
</div>

<!-- Mobile Sort Dropdown (hidden on desktop) -->
<div id="mobile-sort-controls" style="display: none; margin: 10px 0; padding: 8px 12px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
    <label for="mobile-sort-select" style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">Sort by:</label>
    <select id="mobile-sort-select" style="width: 100%; padding: 8px; font-size: 14px;">
        <option value="id_asc" <?php echo ($sort_by === 'id' && $sort_order === 'asc') ? 'selected' : ''; ?>>ID (Low to High)</option>
        <option value="id_desc" <?php echo ($sort_by === 'id' && $sort_order === 'desc') ? 'selected' : ''; ?>>ID (High to Low)</option>
        <option value="name_asc" <?php echo ($sort_by === 'name' && $sort_order === 'asc') ? 'selected' : ''; ?>>Name (A to Z)</option>
        <option value="name_desc" <?php echo ($sort_by === 'name' && $sort_order === 'desc') ? 'selected' : ''; ?>>Name (Z to A)</option>
        <option value="date_asc" <?php echo ($sort_by === 'date' && $sort_order === 'asc') ? 'selected' : ''; ?>>Date (Oldest First)</option>
        <option value="date_desc" <?php echo ($sort_by === 'date' && $sort_order === 'desc') ? 'selected' : ''; ?>>Date (Newest First)</option>
        <option value="tour_asc" <?php echo ($sort_by === 'tour' && $sort_order === 'asc') ? 'selected' : ''; ?>>Tour (A to Z)</option>
        <option value="tour_desc" <?php echo ($sort_by === 'tour' && $sort_order === 'desc') ? 'selected' : ''; ?>>Tour (Z to A)</option>
    </select>
</div>

<?php if ($filtered_bookings) : ?>
    <?php
    // Resolve live package name from global settings by package ID (no tour_package_text fallback).
    $bst_live_package_label = static function ( $package_id ) {
        static $cache = array();
        $package_id = (int) $package_id;
        if ( $package_id <= 0 ) {
            return '';
        }
        if ( ! isset( $cache[ $package_id ] ) ) {
            $cache[ $package_id ] = (string) get_option( 'bst_package_' . $package_id . '_name', '' );
        }
        return $cache[ $package_id ];
    };
    ?>
    <div class="wp-list-table-container bookings-table-wrapper">
        <table class="wp-list-table widefat fixed striped" style="table-layout: auto; width: 100%;">
        <thead>
            <tr>
                <th class="id-column" style="width: 30px; min-width: 30px;">
                    <?php
                    $id_sort_order = ($sort_by === 'id' && $sort_order === 'asc') ? 'desc' : 'asc';
                    $id_sort_indicator = ($sort_by === 'id') ? ($sort_order === 'asc' ? ' ↑' : ' ↓') : '';
                    $id_url = add_query_arg([
                        'page' => 'bst-tour-bookings',
                        'filter_tour_id' => $selected_tour,
                        'filter_tour_date_id' => $selected_date,
                        'filter_status' => $selected_status,
                        'search' => $search,
                        'sort_by' => 'id',
                        'sort_order' => $id_sort_order
                    ]);
                    ?>
                    <a href="<?php echo esc_url($id_url); ?>" style="text-decoration: none; color: inherit;">
                        ID<?php echo $id_sort_indicator; ?>
                    </a>
                </th>
                <th class="booking-id-column" style="width: 35px; min-width: 35px;">Book</th>
                <th class="finalization-id-column" style="width: 35px; min-width: 35px;">Final</th>
                <th class="booking-date-column" style="width: 75px; min-width: 75px;">
                    <?php
                    $date_sort_order = ($sort_by === 'date' && $sort_order === 'asc') ? 'desc' : 'asc';
                    $date_sort_indicator = ($sort_by === 'date') ? ($sort_order === 'asc' ? ' ↑' : ' ↓') : '';
                    $date_url = add_query_arg([
                        'page' => 'bst-tour-bookings',
                        'filter_tour_id' => $selected_tour,
                        'filter_tour_date_id' => $selected_date,
                        'filter_status' => $selected_status,
                        'search' => $search,
                        'sort_by' => 'date',
                        'sort_order' => $date_sort_order
                    ]);
                    ?>
                    <a href="<?php echo esc_url($date_url); ?>" style="text-decoration: none; color: inherit;">
                        Date<?php echo $date_sort_indicator; ?>
                    </a>
                </th>
                <th class="name-column" style="min-width: 130px;">
                    <?php
                    $name_sort_order = ($sort_by === 'name' && $sort_order === 'asc') ? 'desc' : 'asc';
                    $name_sort_indicator = ($sort_by === 'name') ? ($sort_order === 'asc' ? ' ↑' : ' ↓') : '';
                    $name_url = add_query_arg([
                        'page' => 'bst-tour-bookings',
                        'filter_tour_id' => $selected_tour,
                        'filter_tour_date_id' => $selected_date,
                        'filter_status' => $selected_status,
                        'search' => $search,
                        'sort_by' => 'name',
                        'sort_order' => $name_sort_order
                    ]);
                    ?>
                    <a href="<?php echo esc_url($name_url); ?>" style="text-decoration: none; color: inherit;">
                        Name<?php echo $name_sort_indicator; ?>
                    </a>
                </th>
                <th class="tour-column" style="min-width: 170px;">
                    <?php
                    $tour_sort_order = ($sort_by === 'tour' && $sort_order === 'asc') ? 'desc' : 'asc';
                    $tour_sort_indicator = ($sort_by === 'tour') ? ($sort_order === 'asc' ? ' ↑' : ' ↓') : '';
                    $tour_url = add_query_arg([
                        'page' => 'bst-tour-bookings',
                        'filter_tour_id' => $selected_tour,
                        'filter_tour_date_id' => $selected_date,
                        'filter_status' => $selected_status,
                        'search' => $search,
                        'sort_by' => 'tour',
                        'sort_order' => $tour_sort_order
                    ]);
                    ?>
                    <a href="<?php echo esc_url($tour_url); ?>" style="text-decoration: none; color: inherit;">
                        Tour<?php echo $tour_sort_indicator; ?>
                    </a>
                </th>
                <th class="tour-price-column" style="text-align: right; width: 85px;">Price</th>
                <th class="coupon-amount-column" style="text-align: right; width: 70px;">Coup</th>
                <th class="additional-charge-column" style="text-align: right; width: 60px;">Add'l</th>
                <th class="total-paid-column" style="text-align: right; width: 85px;">Paid</th>
                <th class="balance-due-column" style="text-align: right; width: 90px;">Balance</th>
                <th class="status-column">Status</th>
                <th class="actions-column" style="width: 30px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filtered_bookings as $booking) : ?>
                <tr>
                    <td class="id-column"><?php echo esc_html($booking->id); ?></td>

                    <!-- Booking ID with link -->
                    <td class="booking-id-column">
                        <?php if ($booking->booking_status === 'Reserved' && empty($booking->booking_entry_id)) : ?>
                            <a href="<?php echo esc_url(bst_get_reservation_url($booking->id)); ?>" target="_blank">
                                url
                            </a>
                        <?php elseif (!empty($booking->booking_entry_id)) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=gf_entries&view=entry&id=9&lid=' . $booking->booking_entry_id)); ?>" target="_blank">
                                <?php echo esc_html($booking->booking_entry_id); ?>
                            </a>
                        <?php endif; ?>
                    </td>                    <!-- Finalization ID with link -->
                    <td class="finalization-id-column">
                        <?php if ($booking->booking_status === 'Booked' && empty($booking->finalization_entry_id)) : ?>
                            <a href="<?php echo esc_url(bst_get_finalization_url($booking->id)); ?>" target="_blank">
                                url
                            </a>
                        <?php elseif (!empty($booking->finalization_entry_id)) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=gf_entries&view=entry&id=10&lid=' . $booking->finalization_entry_id)); ?>" target="_blank">
                                <?php echo esc_html($booking->finalization_entry_id); ?>
                            </a>
                        <?php endif; ?>
                    </td>

                    <!-- Booking Date -->
                    <td class="booking-date-column">
                        <?php 
                        $created_date = $booking->created_date;
                        // Always show just the date portion (YYYY-MM-DD format)
                        echo esc_html(date('Y-m-d', strtotime($created_date)));
                        ?>
                    </td>

                    <!-- Name (composite field with guest1 and guest2 logic) -->
                    <td class="name-column">
                        <?php
                        $guest1_first = esc_html($booking->guest1_first_name);
                        $guest1_last = esc_html($booking->guest1_last_name);
                        $guest2_first = isset($booking->guest2_first_name) ? esc_html($booking->guest2_first_name) : '';
                        $guest2_last = isset($booking->guest2_last_name) ? esc_html($booking->guest2_last_name) : '';
                        
                        // If no guest2 name, display guest1 only
                        if (empty($guest2_first)) {
                            echo $guest1_first . ' ' . $guest1_last;
                        } else {
                            // Guest2 exists - check if last names are same/blank
                            if (empty($guest2_last) || $guest1_last === $guest2_last) {
                                // Same or blank last name: "First1 & First2 Last1"
                                echo $guest1_first . ' & ' . $guest2_first . ' ' . $guest1_last;
                            } else {
                                // Different last names: "First1 Last1 & First2 Last2"
                                echo $guest1_first . ' ' . $guest1_last . ' & ' . $guest2_first . ' ' . $guest2_last;
                            }
                        }
                        ?>
                    </td>

                    <!-- Booked Tour (composite field) -->
                    <td class="tour-column">
                        <?php
                        $tour_label = 'Unknown Tour';
                        if ( ! empty( $booking->tour_id ) ) {
                            $live_tour = get_post( (int) $booking->tour_id );
                            if ( $live_tour && 'tour' === $live_tour->post_type ) {
                                $tour_label = $live_tour->post_title;
                            }
                        }
                        $tour_date_id = $booking->tour_date_id;
                        $tour_date_text = function_exists('bst_live_tour_date_text') ? bst_live_tour_date_text($booking->tour_date_id ?? 0) : '';
                        $paren = '';
                        if (!empty($tour_date_id)) {
                            $parts = explode('|', $tour_date_id);
                            $tour_date_id_val = trim($parts[0]);
                            // Try to get the tour date text from the tour-date post
                            $tour_date_post = get_post($tour_date_id_val);
                            if ($tour_date_post && $tour_date_post->post_type === 'tour-date') {
                                $start_date = get_post_meta($tour_date_id_val, 'start_date', true);
                                $end_date = get_post_meta($tour_date_id_val, 'end_date', true);
                                if ($start_date && $end_date) {
                                    $tour_date_text = (date('M', strtotime($start_date)) == date('M', strtotime($end_date)))
                                        ? date('j', strtotime($start_date)) . '-' . date('j M Y', strtotime($end_date))
                                        : date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
                                } elseif ($start_date) {
                                    $tour_date_text = date('j M Y', strtotime($start_date));
                                } else {
                                    $tour_date_text = $tour_date_post->post_title;
                                }
                            }
                        }
                        $date_label = $tour_date_text !== '' ? $tour_date_text : $tour_date_id;
                        if ($date_label) {
                            $paren = $date_label;
                        }
                        
                        $package_label = $bst_live_package_label( $booking->tour_package_id ?? 0 );
                        if ( '' === $package_label && ! empty( $booking->tour_package_id ) ) {
                            $package_label = 'Unknown Package';
                        }
                        $tour_display = $tour_label . ($paren ? ' (' . $paren . ')' : '');
                        if ( '' !== $package_label ) {
                            $tour_display .= ' - ' . $package_label;
                        }
                        if (!empty($booking->tour_extension_added) && $booking->tour_extension_added == 1) {
                            $tour_display .= ' (w/extension)';
                        }
                        echo esc_html($tour_display);
                        ?>
                    </td>

                    <?php 
                    // Get currency symbol
                    $currency = $booking->tour_currency ?? 'EUR';
                    $currency_symbol = ($currency === 'USD') ? '$' : '€';
                    ?>
                    
                    <!-- Tour Price -->
                    <td class="tour-price-column" style="text-align: right;"><?php echo esc_html($currency_symbol . number_format($booking->tour_price, 0)); ?></td>

                    <!-- Coupon Amount -->
                    <td class="coupon-amount-column" style="text-align: right;"><?php echo ($booking->coupon_amount > 0) ? esc_html('-' . $currency_symbol . number_format($booking->coupon_amount, 2)) : ''; ?></td>

                    <!-- Additional Charge -->
                    <td class="additional-charge-column" style="text-align: right;"><?php echo (($booking->additional_charge ?? 0) > 0) ? esc_html($currency_symbol . number_format($booking->additional_charge, 0)) : ''; ?></td>

                    <!-- Total Paid -->
                    <td class="total-paid-column" style="text-align: right;"><?php echo esc_html($currency_symbol . number_format($booking->total_paid, 2)); ?></td>

                    <!-- Balance Due -->
                    <td class="balance-due-column" style="text-align: right;"><?php echo esc_html($currency_symbol . number_format($booking->balance_due, 2)); ?></td>

                    <!-- Status -->
                    <td class="status-column"><?php echo esc_html($booking->booking_status ?? ''); ?></td>

                    <!-- Actions -->
                    <td class="actions-column">
                        <?php 
                        $view_url = admin_url('admin.php?page=view_booking&id=' . $booking->id);
                        
                        // Add current filter and sort parameters to the view URL
                        $view_params = array();
                        if ($selected_tour > 0) {
                            $view_params['filter_tour_id'] = $selected_tour;
                        }
                        if ($selected_date > 0) {
                            $view_params['filter_tour_date_id'] = $selected_date;
                        }
                        if (!empty($selected_status)) {
                            $view_params['filter_status'] = $selected_status;
                        }
                        if (!empty($search)) {
                            $view_params['search'] = $search;
                        }
                        if (!empty($sort_by)) {
                            $view_params['sort_by'] = $sort_by;
                        }
                        if (!empty($sort_order)) {
                            $view_params['sort_order'] = $sort_order;
                        }
                        
                        if (!empty($view_params)) {
                            $view_url = add_query_arg($view_params, $view_url);
                        }
                        ?>
                        <a href="<?php echo esc_url($view_url); ?>" class="button button-small view-booking" title="View Booking" data-booking-id="<?php echo $booking->id; ?>">
                            View
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div> <!-- Close bookings-table-wrapper -->
    
    <!-- Mobile card view -->
    <div class="mobile-bookings-container">
        <?php foreach ($filtered_bookings as $booking): ?>
            <div class="mobile-booking-card">
                <div class="mobile-card-header">
                    <span class="mobile-booking-id">
                        #<?php echo esc_html($booking->id); ?>
                        <?php 
                        // Add creation date portion - use same field as desktop Date column
                        if (!empty($booking->created_date)) {
                            $create_date = date('n/j/y', strtotime($booking->created_date));
                            echo ' - ' . esc_html($create_date);
                        }
                        ?>
                    </span>
                    <div class="mobile-header-actions">
                        <?php 
                        $view_url = admin_url('admin.php?page=view_booking&id=' . $booking->id);
                        
                        // Add current filter and sort parameters to the view URL
                        $view_params = array();
                        if ($selected_tour > 0) {
                            $view_params['filter_tour_id'] = $selected_tour;
                        }
                        if ($selected_date > 0) {
                            $view_params['filter_tour_date_id'] = $selected_date;
                        }
                        if (!empty($selected_status)) {
                            $view_params['filter_status'] = $selected_status;
                        }
                        if (!empty($search)) {
                            $view_params['search'] = $search;
                        }
                        if (!empty($sort_by)) {
                            $view_params['sort_by'] = $sort_by;
                        }
                        if (!empty($sort_order)) {
                            $view_params['sort_order'] = $sort_order;
                        }
                        
                        if (!empty($view_params)) {
                            $view_url = add_query_arg($view_params, $view_url);
                        }
                        ?>
                        <a href="<?php echo esc_url($view_url); ?>" class="button button-small view-booking" title="View Booking" data-booking-id="<?php echo $booking->id; ?>">
                            View
                        </a>
                    </div>
                </div>
                
                <div class="mobile-customer-name">
                    <span class="mobile-name-text"><?php echo esc_html(bst_format_guest_name($booking->guest1_first_name, $booking->guest1_last_name, $booking->guest2_first_name ?? '', $booking->guest2_last_name ?? '')); ?></span>
                    <span class="mobile-booking-status-badge"><?php echo esc_html($booking->booking_status ?? ''); ?></span>
                </div>
                
                <div class="mobile-tour-info">
                    <?php 
                    // Use same logic as desktop version
                    $tour_label = 'Unknown Tour';
                    if (!empty($booking->tour_id)) {
                        $live_tour = get_post((int) $booking->tour_id);
                        if ($live_tour && $live_tour->post_type === 'tour') {
                            $tour_label = $live_tour->post_title;
                        }
                    }
                    $tour_date_id = $booking->tour_date_id;
                    $tour_date_text = function_exists('bst_live_tour_date_text') ? bst_live_tour_date_text($booking->tour_date_id ?? 0) : '';
                    $paren = '';
                    
                    if (!empty($tour_date_id)) {
                        $parts = explode('|', $tour_date_id);
                        $tour_date_id_val = trim($parts[0]);
                        // Try to get the tour date text from the tour-date post
                        $tour_date_post = get_post($tour_date_id_val);
                        if ($tour_date_post && $tour_date_post->post_type === 'tour-date') {
                            $start_date = get_post_meta($tour_date_id_val, 'start_date', true);
                            $end_date = get_post_meta($tour_date_id_val, 'end_date', true);
                            if ($start_date && $end_date) {
                                $tour_date_text = (date('M', strtotime($start_date)) == date('M', strtotime($end_date)))
                                    ? date('j', strtotime($start_date)) . '-' . date('j M Y', strtotime($end_date))
                                    : date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
                            } elseif ($start_date) {
                                $tour_date_text = date('j M Y', strtotime($start_date));
                            } else {
                                $tour_date_text = $tour_date_post->post_title;
                            }
                        } else {
                            // If we can't get the post, fall back to existing data
                            if (!empty($tour_date_text)) {
                                // Use existing tour_date_text
                            } else {
                                // Parse from ACF data like desktop version
                                $tour_dates = get_field('tour_dates', $booking->tour_id);
                                if ($tour_dates) {
                                    foreach ($tour_dates as $date) {
                                        if ($date['tour_date_id'] == $tour_date_id) {
                                            $tour_date_text = $date['tour_date_text'];
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        $date_label = $tour_date_text !== '' ? $tour_date_text : $tour_date_id;
                        if ($date_label) {
                            $paren = $date_label;
                        }
                    }
                    
                    // Display tour info
                    $package_text = $bst_live_package_label( $booking->tour_package_id ?? 0 );
                    if ( '' === $package_text && ! empty( $booking->tour_package_id ) ) {
                        $package_text = 'Unknown Package';
                    }
                    $tour_display = $tour_label . ($paren ? ' (' . $paren . ')' : '');
                    if ($package_text) {
                        $tour_display .= ' - ' . $package_text;
                    }
                    if (!empty($booking->tour_extension_added) && $booking->tour_extension_added == 1) {
                        $tour_display .= ' (w/extension)';
                    }
                    
                    echo esc_html($tour_display);
                    ?>
                </div>
                
                <!-- Financial Information -->
                <div class="mobile-financial-info">
                    <?php 
                    $tour_price = floatval($booking->tour_price ?? 0);
                    $coupon_amount = floatval($booking->coupon_amount ?? 0);
                    $net_price = floatval($booking->net_tour_price ?? 0);
                    $additional = floatval($booking->additional_charge ?? 0);
                    $total_due = $net_price + $additional;
                    $total_paid = floatval($booking->total_paid ?? 0);
                    $balance_due = floatval($booking->balance_due ?? 0);
                    $currency = $booking->tour_currency ?? 'EUR';
                    
                    // Convert currency code to symbol
                    $currency_symbol = ($currency === 'USD') ? '$' : '€';
                    ?>
                    <div class="mobile-financial-row">
                        <span class="mobile-financial-label">Price:</span>
                        <span class="mobile-financial-value"><?php echo esc_html($currency_symbol . number_format($tour_price, 0)); ?></span>
                    </div>
                    <?php if ($coupon_amount > 0): ?>
                    <div class="mobile-financial-row">
                        <span class="mobile-financial-label">Coupon:</span>
                        <span class="mobile-financial-value"><?php echo esc_html('-' . $currency_symbol . number_format($coupon_amount, 2)); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($additional > 0): ?>
                    <div class="mobile-financial-row">
                        <span class="mobile-financial-label">Add'l:</span>
                        <span class="mobile-financial-value"><?php echo esc_html($currency_symbol . number_format($additional, 0)); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="mobile-financial-row">
                        <span class="mobile-financial-label">Paid:</span>
                        <span class="mobile-financial-value"><?php echo esc_html($currency_symbol . number_format($total_paid, 2)); ?></span>
                    </div>
                    <div class="mobile-financial-row">
                        <span class="mobile-financial-label">Balance:</span>
                        <span class="mobile-financial-value mobile-balance-<?php echo $balance_due > 0 ? 'due' : 'paid'; ?>">
                            <?php echo esc_html($currency_symbol . number_format($balance_due, 2)); ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
<?php else : ?>
    <p>No tour bookings found.</p>
<?php endif; ?>

<?php if (bst_user_has_sensitive_access()) : ?>
<!-- Operations Table -->
<div class="wp-list-table-container">
<table class="wp-list-table widefat fixed bst-operations-table">
    <thead>
        <tr>
            <th class="bst-operations-header">General Operations</th>
            <th class="bst-operations-header">GF9 Operations</th>
            <th class="bst-operations-header">GF10 Operations</th>
        </tr>
    </thead>
    <tbody>
        <!-- Row 1: Imports -->
        <tr>
            <td class="bst-operations-row bst-operations-row-border">
                <!-- Import Updates -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="bst-form-margin">
                    <input type="hidden" name="action" value="bst_import_updates">
                    <input type="file" name="import_file" accept=".txt,.tsv,.tab" required class="bst-file-input">
                    <?php wp_nonce_field('bst_import_updates', 'import_nonce'); ?>
                    <button type="submit" class="button button-secondary bst-import-button">
                        Import Updates
                    </button>
                </form>
            </td>
            <td class="bst-operations-row bst-operations-row-border">
                <!-- Import GF9 CSV Button -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="bst-form-margin">
                    <input type="hidden" name="action" value="bst_import_gf9_csv">
                    <input type="file" name="gf9_csv_file" accept=".csv" required class="bst-file-input">
                    <button type="submit" class="button button-secondary bst-import-button">
                        Import GF9 CSV
                    </button>
                </form>
            </td>
            <td class="bst-operations-row bst-operations-row-border">
                <!-- GF10 Import operations -->
            </td>
        </tr>
        
        <!-- Spacing row -->
        <tr class="bst-operations-spacing-row">
            <td colspan="3"></td>
        </tr>
        
        <!-- Row 2: Exports and Processing -->
        <tr>
            <td class="bst-operations-row bst-operations-row-border">
                <!-- Export Selection to Excel with DB Field Names -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bst-form-margin">
                    <input type="hidden" name="action" value="bst_export_bookings_excel_db_fields">
                    <input type="hidden" name="page" value="bst-tour-bookings">
                    <input type="hidden" name="filter_tour_id" value="<?php echo esc_attr($selected_tour); ?>">
                    <input type="hidden" name="filter_tour_date_id" value="<?php echo esc_attr($selected_date); ?>">
                    <input type="hidden" name="filter_status" value="<?php echo esc_attr($selected_status); ?>">
                    <input type="hidden" name="sort_by" value="<?php echo esc_attr($sort_by); ?>">
                    <input type="hidden" name="sort_order" value="<?php echo esc_attr($sort_order); ?>">
                    <?php wp_nonce_field('bst_export_bookings', 'export_nonce'); ?>
                    <button type="submit" class="button button-primary bst-export-button">
                        Export Selection (DB Fields)
                    </button>
                </form>
            </td>
            <td class="bst-operations-row bst-operations-row-border">
                <!-- Process ID 9 Bookings -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bst-form-margin">
                    <input type="hidden" name="action" value="bst_import_gf9_tour_bookings">
                    <button type="submit" class="button button-secondary bst-process-button">
                        Process GF9 Entries
                    </button>
                </form>
            </td>
            <td class="bst-operations-row bst-operations-row-border">
                <!-- Process ID 10 Bookings -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bst-form-margin">
                    <input type="hidden" name="action" value="bst_import_gf10_tour_bookings">
                    <button type="submit" class="button button-secondary bst-process-button">
                        Process GF10 Entries
                    </button>
                </form>
            </td>
        </tr>
        
        <!-- Spacing row -->
        <tr class="bst-operations-spacing-row">
            <td colspan="3" class="bst-operations-spacing-cell"></td>
        </tr>
        
        <!-- Row 3: Delete Operations -->
        <tr>
            <td class="bst-operations-row bst-operations-row-border">
                <!-- Reserved for future delete operations -->
                <span class="bst-operations-spacing"><!-- Spacing --></span>
            </td>
            <td class="bst-operations-row bst-operations-row-border">
                <!-- GF9 Delete operations -->
                <span class="bst-operations-spacing"><!-- Spacing --></span>
            </td>
            <td class="bst-operations-row bst-operations-row-border">
                <!-- GF10 Delete operations -->
                <span class="bst-operations-spacing"><!-- Spacing --></span>
            </td>
        </tr>
    </tbody>
</table>
</div> <!-- Close operations table wrapper -->

<!-- Mobile-only operations -->
<div class="mobile-operations">
    <h3>Admin Operations</h3>
    <!-- Note: Sync Sold Slots has been moved to the Tools page -->
    <p><em>Tour availability sync functionality has been moved to the <a href="<?php echo admin_url('admin.php?page=bst-tools'); ?>">Tools page</a>.</em></p>
</div>

<?php endif; ?>

<script>
// Export success messages via JavaScript alerts
function handleExportSubmission(formElement, exportType) {
    // Small delay to allow the download to start, then show success message
    setTimeout(function() {
        let message = 'Export completed successfully!';
        
        if (exportType === 'commission') {
            // Commission export doesn't use current selection - it exports all bookings needing commission
            message += ' Commission bookings exported (Commission Export).';
        } else {
            // Regular exports use the current selection count
            const recordCount = <?php echo $total_records; ?>;
            message += ' ' + recordCount + ' record' + (recordCount !== 1 ? 's' : '') + ' exported';
            
            switch(exportType) {
                case 'main':
                    message += ' (Standard Export)';
                    break;
                case 'db_fields':
                    message += ' (Database Fields Export)';
                    break;
            }
            message += '.';
        }
        
        alert(message);
    }, 1000); // 1 second delay to ensure download starts
    
    return true; // Allow form submission to proceed
}

// Add event listeners to export forms
document.addEventListener('DOMContentLoaded', function() {
    // Main export button (top of page)
    const mainExportForm = document.querySelector('form[action*="admin-post.php"] input[value="bst_export_bookings_excel"]');
    if (mainExportForm) {
        mainExportForm.closest('form').addEventListener('submit', function() {
            return handleExportSubmission(this, 'main');
        });
    }
    
    // DB Fields export button
    const dbFieldsExportForm = document.querySelector('form[action*="admin-post.php"] input[value="bst_export_bookings_excel_db_fields"]');
    if (dbFieldsExportForm) {
        dbFieldsExportForm.closest('form').addEventListener('submit', function() {
            return handleExportSubmission(this, 'db_fields');
        });
    }
    
    // Commission export button
    const commissionExportForm = document.getElementById('export-commission-bookings-form');
    if (commissionExportForm) {
        commissionExportForm.addEventListener('submit', function() {
            return handleExportSubmission(this, 'commission');
        });
    }
});

// Capture and restore state for view booking navigation
(function() {
    // Simple approach: URL parameters preserve all state, no JavaScript state management needed
    document.addEventListener('DOMContentLoaded', function() {

    });
})();

// Mobile sort dropdown handler
document.addEventListener('DOMContentLoaded', function() {
    const mobileSortSelect = document.getElementById('mobile-sort-select');
    
    if (mobileSortSelect) {
        mobileSortSelect.addEventListener('change', function() {
            const sortValue = this.value;
            const [sortBy, sortOrder] = sortValue.split('_');
            
            // Get current URL parameters
            const url = new URL(window.location);
            
            // Update sort parameters
            url.searchParams.set('sort_by', sortBy);
            url.searchParams.set('sort_order', sortOrder);
            
            // Redirect to updated URL
            window.location.href = url.toString();
        });
    }
});
</script>

<style>
/* Automatic transmission column styling */
.auto-column {
    width: 15px !important;
    text-align: center !important;
}

/* Mobile responsive styles for booking list table - Updated <?php echo time(); ?> */
@media (max-width: 782px) {
    /* Show mobile sort controls on mobile */
    #mobile-sort-controls {
        display: block !important;
    }
    
    /* Hide the standard table on mobile */
    .bookings-table-wrapper .wp-list-table {
        display: none;
    }
    
    /* Mobile card view container */
    .mobile-bookings-container {
        display: block;
    }
    
    /* Individual booking cards */
    .mobile-booking-card {
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 10px;
        padding: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    /* Card header with ID and View button */
    .mobile-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #eee;
    }
    
    .mobile-booking-id {
        font-weight: bold;
        color: #0073aa;
        font-size: 16px;
    }
    
    .mobile-header-actions {
        flex-shrink: 0;
    }
    
    .mobile-header-actions .button {
        font-size: 12px !important;
        padding: 4px 8px !important;
        min-height: unset !important;
        line-height: 1.2 !important;
        border-radius: 3px !important;
        height: auto !important;
        vertical-align: middle !important;
        min-width: unset !important;
        max-width: 60px !important;
    }
    
    /* Customer name row with status */
    .mobile-customer-name {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    
    .mobile-name-text {
        font-weight: 600;
        font-size: 15px;
        color: #23282d;
    }
    
    /* Status badge on the same line as name */
    .mobile-booking-status-badge {
        background: #007cba;
        color: white;
        padding: 3px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 500;
        white-space: nowrap;
        flex-shrink: 0;
    }
    
    /* Tour information */
    .mobile-tour-info {
        color: #666;
        font-size: 14px;
        line-height: 1.4;
        margin-bottom: 12px;
    }
    
    /* Financial information */
    .mobile-financial-info {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 3px;
        margin-bottom: 10px;
        font-size: 13px;
    }
    
    .mobile-financial-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }
    
    .mobile-financial-row:last-child {
        margin-bottom: 0;
    }
    
    .mobile-financial-label {
        color: #666;
        font-weight: 500;
    }
    
    .mobile-financial-value {
        font-weight: 600;
        color: #23282d;
    }
    
    .mobile-balance-due {
        color: #d63638 !important; /* Red for balance due */
    }
    
    .mobile-balance-paid {
        color: #00a32a !important; /* Green for fully paid */
    }
    
    /* Transmission information */
    .mobile-transmission-info {
        margin-bottom: 8px;
        font-size: 13px;
    }
    
    /* Adjust filter form for mobile */
    div[style*="display: flex; align-items: center; justify-content: space-between"] {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 15px !important;
    }
    
    #bst-tour-filter-form {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
    }
    
    #bst-tour-filter-form > div {
        flex-direction: column !important;
        align-items: stretch !important;
        margin-bottom: 10px;
    }
    
    #bst-tour-filter-form label {
        margin-right: 0 !important;
        margin-bottom: 5px !important;
        font-weight: bold;
        display: block;
    }
    
    #bst-tour-filter-form select {
        margin-right: 0 !important;
        width: 100%;
        max-width: none;
        font-size: 16px; /* Prevent zoom on iOS */
    }
    
    /* Stack new booking button below filters */
    div[style*="flex-shrink: 0"] {
        display: none !important; /* Hide export button on mobile */
    }
    
    /* Hide operations table on mobile (contains export buttons) */
    table.bst-operations-table {
        display: none !important;
    }
    
    /* Quick actions section for mobile */
    #booking-actions > div {
        padding: 10px !important;
    }
    
    #booking-actions h4 {
        font-size: 16px !important;
        margin-bottom: 8px !important;
    }
    
    #booking-actions > div > div {
        flex-direction: column !important;
        gap: 8px !important;
        align-items: stretch !important;
    }
    
    #booking-actions .button {
        width: 100%;
        text-align: center;
        margin: 0;
    }
    
    /* Hide operations table on mobile, show only sync button */
    .bst-operations-table {
        display: none;
    }
    
    /* Mobile operations - show only sync */
    .mobile-operations {
        display: block;
        text-align: center;
        padding: 20px;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 20px;
    }
    
    .mobile-operations h3 {
        margin: 0 0 15px 0;
        font-size: 16px;
        color: #23282d;
    }
    
    /* Record count display */
    div[style*="background: #f0f0f1"] {
        margin: 10px -20px !important;
        border-radius: 0 !important;
        font-size: 14px;
    }
    
    /* Input and select styling */
    input[type="text"],
    input[type="number"],
    select {
        font-size: 16px; /* Prevent zoom on iOS */
        padding: 8px;
    }
}

/* Desktop: hide mobile elements */
@media (min-width: 783px) {
    .mobile-bookings-container,
    .mobile-operations {
        display: none;
    }
}

/* Tablet adjustments (between mobile and desktop) */
@media (min-width: 783px) and (max-width: 1024px) {
    .wp-list-table {
        font-size: 14px;
    }
    
    .wp-list-table th,
    .wp-list-table td {
        padding: 8px 10px;
    }
    
    /* Adjust filter form for tablet */
    #bst-tour-filter-form {
        gap: 12px !important;
    }
    
    /* Better wrap behavior for filters */
    #bst-tour-filter-form > div {
        min-width: 200px;
    }
    
    /* Compress some columns on tablet */
    .coupon-amount-column,
    .additional-charge-column {
        width: 80px;
    }
    
    /* Right-justify all currency fields */
    .tour-price-column,
    .coupon-amount-column,
    .additional-charge-column,
    .total-paid-column,
    .balance-due-column {
        text-align: right !important;
    }
}

/* Ensure proper table container exists */
.bookings-table-wrapper {
    position: relative;
}

/* Desktop: hide mobile elements */
@media (min-width: 783px) {
    .mobile-bookings-container,
    .mobile-operations {
        display: none;
    }
}

/* Hide scroll bars but keep functionality */
@media (max-width: 782px) {
    .wp-list-table-container {
        scrollbar-width: thin;
        scrollbar-color: #ccc transparent;
    }
    
    .wp-list-table-container::-webkit-scrollbar {
        height: 6px;
    }
    
    .wp-list-table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    
    .wp-list-table-container::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 3px;
    }
    
    .wp-list-table-container::-webkit-scrollbar-thumb:hover {
        background: #999;
    }
}
</style>

<script>
var pageBookingsData = [];
<?php if (!empty($filtered_bookings) && $selected_tour > 0 && $selected_date > 0): ?>
<?php 
    $bookings_for_js = array();
    foreach ($filtered_bookings as $booking) {
        $bookings_for_js[] = array(
            'id' => (int)$booking->id,
            'name' => trim(($booking->guest1_first_name ?? '') . ' ' . ($booking->guest1_last_name ?? '')),
            'email' => $booking->guest1_email ?? '',
            'booking_status' => $booking->booking_status ?? ''
        );
    }
?>
pageBookingsData = <?php echo wp_json_encode($bookings_for_js); ?>;
<?php endif; ?>


</script>

<?php
if ( $selected_tour > 0 && $selected_date > 0 ) {
	$bst_bulk_finalization_args = array(
		'require_tour_date_id' => false,
		'attach_bookings_list_send_handler' => true,
	);
	include __DIR__ . '/partials/bst-bulk-email-modal.php';
}
?>

</div> <!-- Close wrap -->
