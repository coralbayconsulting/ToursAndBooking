<?php
// Ensure this file is being included by a parent file
if (!defined('WPINC')) {
    die;
}

global $wpdb;
$table_name = $wpdb->prefix . 'bst_customers';

// Fetch customer data for editing
$customer = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
    $customer_id = intval($_GET['id']);
    $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $customer_id));
}

// Handle search parameter for maintaining filter context
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
$order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';

// Define valid sort columns and their SQL equivalents (same as list page)
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

// Get all customer IDs for navigation (with same order and filter as list page)
$nav_query = "SELECT id FROM $table_name" . $where_clause . $order_clause;
$all_customer_ids = $wpdb->get_col($wpdb->prepare($nav_query, $query_params));
$current_index = null;
$prev_id = $next_id = null;
$total_in_selection = count($all_customer_ids);

if ($customer && $all_customer_ids) {
    $current_index = array_search($customer->id, $all_customer_ids);
    if ($current_index !== false) {
        if ($current_index > 0) $prev_id = $all_customer_ids[$current_index - 1];
        if ($current_index < count($all_customer_ids) - 1) $next_id = $all_customer_ids[$current_index + 1];
    }
}

// Build navigation URLs with search and sort parameters
$url_params = array();
if (!empty($search)) $url_params[] = 'search=' . urlencode($search);
if ($orderby !== 'name') $url_params[] = 'orderby=' . urlencode($orderby);
if ($order !== 'asc') $url_params[] = 'order=' . urlencode($order);
$param_string = !empty($url_params) ? '&' . implode('&', $url_params) : '';

$back_url = admin_url('admin.php?page=bst-plugin-customer-list' . $param_string);
$prev_url = $prev_id ? admin_url('admin.php?page=bst-plugin-customer-form&action=edit&id=' . $prev_id . $param_string) : '';
$next_url = $next_id ? admin_url('admin.php?page=bst-plugin-customer-form&action=edit&id=' . $next_id . $param_string) : '';

// Helper function to get value from customer object
function bst_cust_val($customer, $field) {
    return $customer ? esc_attr($customer->$field) : '';
}
?>

<style>
/* Tour booking edit page styling for customer form */
.form-section {
    background: #fff;
    border: 1px solid #ddd;
    margin: 10px 0 20px 0;
    padding: 20px;
    border-radius: 5px;
}
.form-section h3 {
    margin: 0 0 15px 0;
    padding: 0 0 10px 0;
    border-bottom: 1px solid #eee;
    font-size: 18px;
    color: #333;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}



/* Mobile responsive design */
@media (max-width: 782px) {
    .form-grid {
        grid-template-columns: 1fr !important;
        gap: 10px;
    }
    
    .form-section {
        margin: 10px 0;
        padding: 15px;
    }
    
    .form-row input,
    .form-row textarea,
    .form-row select {
        padding: 12px 10px;
        font-size: 16px; /* Prevents iOS zoom */
        width: 100%;
        box-sizing: border-box;
    }
    
    .form-actions {
        padding: 15px;
        text-align: center;
    }
    
    .form-actions .button {
        width: auto;
        margin: 5px 5px 5px 0;
        padding: 8px 16px;
        font-size: 14px;
        display: inline-block;
    }
    

    
    .record-indicator {
        font-size: 13px !important;
        text-align: center;
        margin: 5px 0 !important;
        display: block !important;
        color: #666 !important;
    }
    
    /* Mobile page title */
    .wrap h1 {
        font-size: 24px !important;
        margin-bottom: 15px !important;
    }
    
    /* Improve viewport meta handling */
    .wrap {
        margin: 10px 10px 0 10px !important;
        max-width: calc(100vw - 20px);
    }
}
.form-row {
    display: flex;
    flex-direction: column;
}
.form-row label {
    font-weight: 600;
    margin-bottom: 3px;
    color: #333;
    font-size: 13px;
}
.form-row input,
.form-row textarea,
.form-row select {
    padding: 8px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    background: #fff;
}
.form-row input:focus,
.form-row textarea:focus,
.form-row select:focus {
    border-color: #007cba;
    box-shadow: 0 0 0 1px #007cba;
    outline: none;
}
.required {
    color: #d63638;
}
.form-actions {
    margin-top: 20px;
    padding: 20px;
    background: #f9f9f9;
    border-top: 1px solid #ddd;
    text-align: left;
}
.form-actions .button {
    margin-right: 10px;
}
.form-section:last-child {
    margin-bottom: 10px;
}
/* Navigation button styles (matching tour bookings) */
.button {
    padding: 8px 16px;
    font-size: 13px;
    line-height: 1.5;
    margin: 0 5px 0 0;
    text-decoration: none;
    border: 1px solid #007cba;
    background: #007cba;
    color: #fff;
    border-radius: 3px;
    cursor: pointer;
    display: inline-block;
}
.button:hover {
    background: #005a87;
    border-color: #005a87;
}
.button-secondary {
    background: #fff;
    color: #007cba;
}
.button-secondary:hover {
    background: #f0f0f0;
}
/* Success message styles */
.bst-success-message {
    background: #00a32a;
    color: #fff;
    padding: 12px 15px;
    margin: 10px 0 20px 0;
    border-radius: 4px;
    display: none;
    font-weight: 500;
}
.bst-success-message.show {
    display: block;
}
</style>

<div class="wrap">
    <h1><?php echo $customer ? 'Edit Customer' : 'Add New Customer'; ?></h1>
    
    <!-- Success message area -->
    <div id="bst-success-message" class="bst-success-message">
        Customer saved successfully!
    </div>
    
    <!-- Navigation buttons (matching tour bookings style) -->
    <?php if ($customer): ?>
        <!-- Page Actions -->
        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <!-- Navigation and Return -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="<?php echo esc_url($back_url); ?>" class="button">← Back to List</a>
                
                <?php if ($prev_id || $next_id): ?>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <?php if ($prev_id): ?>
                            <a href="<?php echo esc_url($prev_url); ?>" class="button">← Previous</a>
                        <?php endif; ?>
                        <?php if ($next_id): ?>
                            <a href="<?php echo esc_url($next_url); ?>" class="button">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Position Indicator -->
        <?php if ($current_index !== false): ?>
        <div style="background-color: #f0f0f1; padding: 8px 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #0073aa; font-size: 14px; text-align: center;">
            Record <?php echo $current_index + 1; ?> of <?php echo $total_in_selection; ?> in selection
            <?php if (!empty($search)): ?>
                (filtered)
            <?php endif; ?>
            <?php if ($orderby !== 'name' || $order !== 'asc'): ?>
                (sorted by <?php echo ucfirst($orderby); ?> <?php echo $order === 'desc' ? '↓' : '↑'; ?>)
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <form id="customer-form" method="post">
        <?php wp_nonce_field('customer_form', 'customer_form_nonce'); ?>
        <input type="hidden" name="customer_id" value="<?php echo $customer ? esc_attr($customer->id) : ''; ?>">
        
        <!-- Customer Information -->
        <div class="form-section">
            <h3>Customer Information</h3>
            <div class="form-grid">
                <div class="form-row">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" id="first_name" value="<?php echo bst_cust_val($customer, 'first_name'); ?>" required>
                </div>
                <div class="form-row">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" id="last_name" value="<?php echo bst_cust_val($customer, 'last_name'); ?>" required>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-row">
                    <label for="partner_first">Partner First Name</label>
                    <input type="text" name="partner_first" id="partner_first" value="<?php echo bst_cust_val($customer, 'partner_first'); ?>">
                </div>
                <div class="form-row">
                    <label for="partner_last">Partner Last Name</label>
                    <input type="text" name="partner_last" id="partner_last" value="<?php echo bst_cust_val($customer, 'partner_last'); ?>">
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="form-section">
            <h3>Contact Information</h3>
            <div class="form-row">
                <label for="email">Email <span class="required">*</span></label>
                <input type="email" name="email" id="email" value="<?php echo bst_cust_val($customer, 'email'); ?>" required>
            </div>
            <div class="form-row">
                <label for="phone">Phone</label>
                <input type="tel" name="phone" id="phone" value="<?php echo bst_cust_val($customer, 'phone'); ?>">
            </div>
        </div>

        <!-- Additional Information -->
        <div class="form-section">
            <h3>Additional Information</h3>
            <div class="form-grid">
                <div class="form-row">
                    <label for="credit">Credit</label>
                    <select name="credit" id="credit">
                        <option value="">Select Credit</option>
                        <option value="Bill" <?php selected(bst_cust_val($customer, 'credit'), 'Bill'); ?>>Bill</option>
                        <option value="Claudio" <?php selected(bst_cust_val($customer, 'credit'), 'Claudio'); ?>>Claudio</option>
                        <option value="Wayne" <?php selected(bst_cust_val($customer, 'credit'), 'Wayne'); ?>>Wayne</option>
                        <option value="Web" <?php selected(bst_cust_val($customer, 'credit'), 'Web'); ?>>Web</option>
                    </select>
                </div>
                <div class="form-row">
                    <label for="data_source">Data Source</label>
                    <input type="text" name="data_source" id="data_source" value="<?php echo bst_cust_val($customer, 'data_source'); ?>">
                </div>
            </div>
            <div class="form-row">
                <label for="notes">Notes</label>
                <textarea name="notes" id="notes" rows="4"><?php echo $customer ? esc_textarea($customer->notes) : ''; ?></textarea>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="button button-primary">Save Customer</button>
            <a href="<?php echo esc_url($back_url); ?>" class="button">Cancel</a>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#customer-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var formData = $form.serialize();
        
        // Disable submit button and show loading state
        $submitBtn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=save_customer',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message in the page layout
                    $('#bst-success-message').addClass('show');
                    
                    // Hide the message after 5 seconds
                    setTimeout(function() {
                        $('#bst-success-message').removeClass('show');
                    }, 5000);
                    
                    // If this was a new customer, update the URL and navigation to reflect the new ID
                    if (response.data && response.data.customer_id && !<?php echo $customer ? $customer->id : 'null'; ?>) {
                        var newUrl = '<?php echo admin_url('admin.php?page=bst-plugin-customer-form&action=edit&id='); ?>' + response.data.customer_id + '<?php echo $param_string; ?>';
                        window.history.replaceState({}, '', newUrl);
                        
                        // Update the hidden customer_id field
                        $('input[name="customer_id"]').val(response.data.customer_id);
                        
                        // Update the page title
                        $('h1').text('Edit Customer');
                    }
                    
                    $submitBtn.prop('disabled', false).text('Save Customer');
                } else {
                    alert('Error: ' + (response.data || 'Failed to save customer'));
                    $submitBtn.prop('disabled', false).text('Save Customer');
                }
            },
            error: function() {
                alert('An error occurred while saving the customer.');
                $submitBtn.prop('disabled', false).text('Save Customer');
            }
        });
    });
});
</script>
