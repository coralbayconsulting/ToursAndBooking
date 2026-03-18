<?php
// Ensure this file is being included by a parent file
if (!defined('WPINC')) {
    die;
}

// Include the tour booking renderers to access the phone formatting function
require_once plugin_dir_path(__FILE__) . '../includes/tour-booking-renderers.php';

global $wpdb;
$table_name = $wpdb->prefix . 'bst_customers';

// Handle search filter
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Handle sorting
$orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
$order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';

// Define valid sort columns and their SQL equivalents
$valid_orderby = array(
    // Sort by primary guest name: last name, then first name
    'name'   => 'last_name, first_name',
    'id'     => 'id',
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
// For multi-column name sort, apply the direction to BOTH columns explicitly
if ($orderby === 'name') {
    $dir = strtoupper($order);
    $order_clause = " ORDER BY last_name {$dir}, first_name {$dir}";
} else {
    $order_clause = " ORDER BY " . $valid_orderby[$orderby] . " " . strtoupper($order);
}

// Get total count for display
$count_query = "SELECT COUNT(*) FROM $table_name" . $where_clause;
if (!empty($query_params)) {
    $total_customers = $wpdb->get_var($wpdb->prepare($count_query, $query_params));
} else {
    $total_customers = $wpdb->get_var($count_query);
}

// Fetch customers with search filter and sorting
$query = "SELECT * FROM $table_name" . $where_clause . $order_clause;
if (!empty($query_params)) {
    $customers = $wpdb->get_results($wpdb->prepare($query, $query_params));
} else {
    $customers = $wpdb->get_results($query);
}

// Helper function to generate sortable column headers
function bst_get_sortable_link($column, $label, $current_orderby, $current_order, $search) {
    $new_order = ($current_orderby === $column && $current_order === 'asc') ? 'desc' : 'asc';
    $search_param = !empty($search) ? '&search=' . urlencode($search) : '';
    $url = admin_url('admin.php?page=bst-plugin-customer-list&orderby=' . $column . '&order=' . $new_order . $search_param);
    
    $arrow = '';
    if ($current_orderby === $column) {
        $arrow = $current_order === 'asc' ? ' ↑' : ' ↓';
    }
    
    return '<a href="' . esc_url($url) . '" style="text-decoration: none; color: inherit;">' . 
           $label . $arrow . '</a>';
}

// Handle import if form submitted
if (isset($_POST['import_bill_customers']) && current_user_can('manage_options') && isset($_FILES['bill_customers_file'])) {
    if (!isset($_FILES['bill_customers_file']) || $_FILES['bill_customers_file']['error'] !== UPLOAD_ERR_OK) {
        wp_redirect(admin_url('admin.php?page=bst-plugin-customer-list&import=error&message=file_upload_failed'));
        exit;
    }
    
    $file = $_FILES['bill_customers_file']['tmp_name'];
    if (!is_uploaded_file($file)) {
        wp_redirect(admin_url('admin.php?page=bst-plugin-customer-list&import=error&message=file_not_found'));
        exit;
    }
    
    $handle = fopen($file, 'r');
    if (!$handle) {
        wp_redirect(admin_url('admin.php?page=bst-plugin-customer-list&import=error&message=file_read_failed'));
        exit;
    }
    
    $imported = 0;
    $errors = 0;
    $row = 0;
    
    try {
        while (($line = fgets($handle)) !== false) {
            $fields = explode("\t", trim($line));
            if ($row === 0) {
                // Skip header row
                $row++;
                continue;
            }
            if (count($fields) >= 7) {
                list($first_name, $last_name, $partner_first, $partner_last, $email, $data_source, $credit) = $fields;
                if (!empty($email)) {
                    $current_user = wp_get_current_user();
                    $user_identifier = $current_user->user_login ?: 'bill_import';
                    $current_time = current_time('mysql');
                    
                    $result = $wpdb->replace($table_name, array(
                        'first_name'        => sanitize_text_field($first_name),
                        'last_name'         => sanitize_text_field($last_name),
                        'partner_first' => sanitize_text_field($partner_first),
                        'partner_last'  => sanitize_text_field($partner_last),
                        'email'             => sanitize_email($email),
                        'data_source'       => sanitize_text_field($data_source),
                        'credit'            => sanitize_text_field($credit),
                        'created_by'        => $user_identifier,
                        'created_date'      => $current_time,
                        'updated_by'        => $user_identifier,
                        'updated_date'      => $current_time
                    ));
                    
                    if ($result !== false) {
                        $imported++;
                    } else {
                        $errors++;
                    }
                } else {
                    $errors++;
                }
            } else {
                $errors++;
            }
            $row++;
        }
        fclose($handle);
        wp_redirect(admin_url("admin.php?page=bst-plugin-customer-list&import=done&imported=$imported&errors=$errors"));
        exit;
        
    } catch (Exception $e) {
        fclose($handle);
        wp_redirect(admin_url('admin.php?page=bst-plugin-customer-list&import=error&message=processing_failed'));
        exit;
    }
}

// Handle import result messages
if (isset($_GET['import'])) {
    if ($_GET['import'] === 'error') {
        $message = isset($_GET['message']) ? $_GET['message'] : 'unknown_error';
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>' . bst_get_import_error_message('customers', $message) . '</p>';
        echo '</div>';
    } elseif ($_GET['import'] === 'done') {
        $imported = isset($_GET['imported']) ? intval($_GET['imported']) : 0;
        $errors = isset($_GET['errors']) ? intval($_GET['errors']) : 0;
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . bst_get_import_success_message('customers', $imported, $errors, 0) . '</p>';
        echo '</div>';
    }
}

// Handle success message for other imports
if (isset($_GET['imported']) && !isset($_GET['import'])) {
    echo '<div class="notice notice-success is-dismissible">';
    echo '<p>' . bst_get_import_success_message('customers', intval($_GET['imported']), 0, 0) . '</p>';
    echo '</div>';
}
?>

<?php
// Properly enqueue CSS for this template
$css_url = content_url('mu-plugins/bst_plugin/css/bst-custom-admin.css');
$css_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/css/bst-custom-admin.css';
$css_version = file_exists($css_path) ? filemtime($css_path) : time();
echo '<link rel="stylesheet" type="text/css" href="' . esc_url($css_url . '?ver=' . $css_version) . '">';
?>
<div class="wrap">
    <h1 class="wp-heading-inline">Customers</h1>
    
    <!-- Filter Controls (matching tour bookings pattern) -->
    <div class="filter-controls cbc-filter-controls">
        <form method="get" class="cbc-filter-form">
            <input type="hidden" name="page" value="bst-plugin-customer-list">
            <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
            <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">
            
            <div class="cbc-filter-row">
                <label for="search" class="cbc-filter-label">Search by Name:</label>
                <input type="search" id="search" name="search" value="<?php echo esc_attr($search); ?>" 
                       placeholder="Search customers by name or email..." class="cbc-filter-input">
            </div>
            
            <div class="cbc-filter-actions">
                <input type="submit" class="button button-primary" value="Filter">
                <?php if (!empty($search)): ?>
                    <a href="<?php echo admin_url('admin.php?page=bst-plugin-customer-list'); ?>" class="button">Clear</a>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons (aligned to right) -->
            <div class="cbc-filter-right-actions">
                <a href="<?php echo admin_url('admin.php?page=bst-plugin-customer-form'); ?>" class="page-title-action">Add New Customer</a>
                <?php if (bst_user_has_sensitive_access()) : ?>
                <button type="button" id="export-customers" class="button button-secondary cbc-filter-export">
                    📊 Export Selection to Excel
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Record Count (matching tour bookings pattern) -->
    <div class="cbc-filter-summary">
        <?php 
        if (!empty($search)) {
            echo sprintf('Showing %d customers found (filtered)', $total_customers);
        } else {
            echo sprintf('Showing %d customers in selection', $total_customers);
        }
        ?>
    </div>
    
    <table class="wp-list-table widefat fixed striped cbc-customer-table">
        <thead>
            <tr>
                <th class="id-column">
                    <?php echo bst_get_sortable_link('id', 'ID', $orderby, $order, $search); ?>
                </th>
                <th class="name-column">
                    <?php echo bst_get_sortable_link('name', 'Name', $orderby, $order, $search); ?>
                </th>
                <th class="email-column">Email</th>
                <th class="phone-column">Phone</th>
                <th class="source-column">
                    <?php echo bst_get_sortable_link('source', 'Source', $orderby, $order, $search); ?>
                </th>
                <th class="credit-column">
                    <?php echo bst_get_sortable_link('credit', 'Credit', $orderby, $order, $search); ?>
                </th>
                <th class="actions-column" style="width: 10%;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($customers) : ?>
                <?php foreach ($customers as $customer) : ?>
                    <tr>
                        <td><?php echo esc_html($customer->id); ?></td>
                        <td>
                            <strong>
                                <?php
                                $first_name = esc_html($customer->first_name);
                                $last_name = esc_html($customer->last_name);
                                $partner_first = isset($customer->partner_first) ? esc_html($customer->partner_first) : '';
                                $partner_last = isset($customer->partner_last) ? esc_html($customer->partner_last) : '';
                                
                                // If no partner name, display customer only
                                if (empty($partner_first)) {
                                    echo $first_name . ' ' . $last_name;
                                } else {
                                    // Partner exists - check if last names are same/blank
                                    if (empty($partner_last) || $last_name === $partner_last) {
                                        // Same or blank last name: "First1 & First2 Last1"
                                        echo $first_name . ' & ' . $partner_first . ' ' . $last_name;
                                    } else {
                                        // Different last names: "First1 Last1 & First2 Last2"
                                        echo $first_name . ' ' . $last_name . ' & ' . $partner_first . ' ' . $partner_last;
                                    }
                                }
                                ?>
                            </strong>
                        </td>
                        <td><?php echo esc_html($customer->email); ?></td>
                        <td><?php echo esc_html(bst_format_phone_international($customer->phone)); ?></td>
                        <td><?php echo esc_html($customer->data_source); ?></td>
                        <td class="credit-column"><?php echo esc_html($customer->credit); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=bst-plugin-customer-form&action=edit&id=' . $customer->id . (!empty($search) ? '&search=' . urlencode($search) : '') . '&orderby=' . urlencode($orderby) . '&order=' . urlencode($order)); ?>" class="button button-small">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7">No customers found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Mobile Card View -->
    <div class="mobile-customer-cards">
        <?php if ($customers) : ?>
            <?php foreach ($customers as $customer) : ?>
                <div class="mobile-customer-card">
                    <div class="mobile-customer-header">
                        <h4 class="mobile-customer-name">
                            <?php
                            $first_name = esc_html($customer->first_name);
                            $last_name = esc_html($customer->last_name);
                            $partner_first = isset($customer->partner_first) ? esc_html($customer->partner_first) : '';
                            $partner_last = isset($customer->partner_last) ? esc_html($customer->partner_last) : '';
                            
                            // If no partner name, display customer only
                            if (empty($partner_first)) {
                                echo $first_name . ' ' . $last_name;
                            } else {
                                // Partner exists - check if last names are same/blank
                                if (empty($partner_last) || $last_name === $partner_last) {
                                    // Same or blank last name: "First1 & First2 Last1"
                                    echo $first_name . ' & ' . $partner_first . ' ' . $last_name;
                                } else {
                                    // Different last names: "First1 Last1 & First2 Last2"
                                    echo $first_name . ' ' . $last_name . ' & ' . $partner_first . ' ' . $partner_last;
                                }
                            }
                            echo ' (#' . esc_html($customer->id) . ')';
                            ?>
                        </h4>
                        <a href="<?php echo admin_url('admin.php?page=bst-plugin-customer-form&action=edit&id=' . $customer->id . (!empty($search) ? '&search=' . urlencode($search) : '') . '&orderby=' . urlencode($orderby) . '&order=' . urlencode($order)); ?>" class="mobile-customer-edit-btn">Edit</a>
                    </div>
                    
                    <div class="mobile-customer-body">
                        <div class="mobile-customer-detail">
                            <span class="mobile-customer-label">Email:</span>
                            <span class="mobile-customer-value"><?php echo esc_html($customer->email); ?></span>
                        </div>
                        
                        <?php if (!empty($customer->phone)) : ?>
                        <div class="mobile-customer-detail">
                            <span class="mobile-customer-label">Phone:</span>
                            <span class="mobile-customer-value"><?php echo esc_html(bst_format_phone_international($customer->phone)); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mobile-customer-detail">
                            <span class="mobile-customer-label">Source:</span>
                            <span class="mobile-customer-value"><?php echo esc_html($customer->data_source); ?></span>
                        </div>
                        
                        <?php if (!empty($customer->credit)) : ?>
                        <div class="mobile-customer-detail">
                            <span class="mobile-customer-label">Credit:</span>
                            <span class="mobile-customer-value mobile-customer-credit"><?php echo esc_html($customer->credit); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="mobile-customer-card">
                <div class="mobile-customer-body">
                    <p>No customers found.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (bst_user_has_sensitive_access()) : ?>
    <div style="margin-top: 15px;">
        <form method="post" enctype="multipart/form-data" style="display: flex; align-items: center; gap: 10px;">
            <input type="file" id="bill_customers_file" name="bill_customers_file" accept=".txt,.tsv" required style="margin: 0;" />
            <input type="submit" name="import_bill_customers" class="button" value="Import Customers" style="background: #00a32a; color: white; border-color: #00a32a;" />
        </form>
    </div>
    <?php endif; ?>
</div>

<style>
/* Customer list responsive styling */
@media (max-width: 782px) {
    .wp-list-table {
        display: none !important;
    }
    
    .mobile-customer-cards {
        display: block;
    }
}

@media (min-width: 783px) {
    .mobile-customer-cards {
        display: none;
    }
}

.mobile-customer-card {
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    margin-bottom: 15px;
    padding: 0;
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
    overflow: hidden;
}

.mobile-customer-header {
    background: #f8f9fa;
    padding: 12px 15px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mobile-customer-name {
    margin: 0;
    color: #0073aa;
    font-size: 14px;
    font-weight: 600;
    flex: 1;
}

.mobile-customer-edit-btn {
    background: #0073aa;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    margin-left: 10px;
}

.mobile-customer-body {
    padding: 15px;
}

.mobile-customer-detail {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 6px 0;
    font-size: 14px;
    line-height: 1.4;
}

.mobile-customer-detail:not(:last-child) {
    border-bottom: 1px solid #f0f0f1;
}

.mobile-customer-label {
    font-weight: 600;
    color: #50575e;
    min-width: 70px;
    flex-shrink: 0;
}

.mobile-customer-value {
    color: #1d2327;
    text-align: right;
    flex: 1;
    word-break: break-word;
}

.mobile-customer-credit {
    color: #d63638;
    font-weight: 600;
}



/* Financial column alignment - matching booking list pattern */
.credit-column {
    text-align: right;
}

/* Responsive filter controls */
@media (max-width: 782px) {
    .filter-controls {
        flex-direction: column !important;
        gap: 10px !important;
        align-items: stretch !important;
    }
    
    .filter-controls > div {
        margin-left: 0 !important;
        justify-content: center;
    }
    
    .filter-controls input[type="search"] {
        width: 100% !important;
        max-width: 300px;
    }
}
</style>

<?php if (bst_user_has_sensitive_access()) : ?>
<script>
jQuery(document).ready(function($) {
    // Export customers functionality
    $('#export-customers').click(function(e) {
        e.preventDefault();
        
        // Create a form with current search parameters
        var form = $('<form>', {
            'method': 'post',
            'action': '<?php echo admin_url('admin-post.php'); ?>'
        });
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'action',
            'value': 'bst_export_customers'
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'search',
            'value': '<?php echo esc_attr($search); ?>'
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'orderby',
            'value': '<?php echo esc_attr($orderby); ?>'
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'order',
            'value': '<?php echo esc_attr($order); ?>'
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': '_wpnonce',
            'value': '<?php echo wp_create_nonce('bst_export_customers'); ?>'
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    });
});
</script>
<?php endif; ?>
