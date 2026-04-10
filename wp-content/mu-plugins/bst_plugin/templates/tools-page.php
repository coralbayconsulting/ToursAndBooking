<?php
$error_log_path = bst_get_tools_error_log_path();
$release_cleanup_log_path = function_exists( 'bst_get_release_cleanup_log_path' ) ? bst_get_release_cleanup_log_path() : trailingslashit( WP_CONTENT_DIR ) . 'bst-release-cleanup.log';
$bst_last_release_cleanup_log = get_option( 'bst_last_release_cleanup_log', null );
$bst_migration_last_run       = get_option( 'bst_migration_last_run', null );
$upload_dir = wp_upload_dir();
$bst_release_cleanup_upload_path = ( empty( $upload_dir['error'] ) ) ? trailingslashit( $upload_dir['basedir'] ) . 'bst-plugin-logs/release-cleanup.log' : '';

// Handle a download request BEFORE any output so headers can be sent.
bst_tools_maybe_send_error_log_download();

// Handle cron frequency form
if (isset($_POST['update_cron_frequency']) && wp_verify_nonce($_POST['cron_nonce'], 'update_cron_frequency')) {
    $hook = sanitize_text_field($_POST['cron_hook']);
    $new_frequency = sanitize_text_field($_POST['new_frequency']);
    bst_tools_reschedule_cron($hook, $new_frequency);
    echo '<div class="notice notice-success"><p>Cron frequency updated for ' . esc_html($hook) . '</p></div>';
}

// Handle error log actions (clear / refresh). Download is handled before output.
if (!empty($_POST['log_action']) && isset($_POST['error_log_actions_nonce']) && wp_verify_nonce($_POST['error_log_actions_nonce'], 'bst_error_log_actions')) {
    $log_action = sanitize_text_field($_POST['log_action']);

    if ($log_action === 'clear') {
        if (file_exists($error_log_path) && is_writable($error_log_path)) {
            file_put_contents($error_log_path, '');
            echo '<div class="notice notice-success"><p>Error log cleared successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Could not clear error log. File may not exist or is not writable.</p></div>';
        }
    } elseif ($log_action === 'refresh') {
        // Simple reload; no extra output needed
    }
}

$frequency_options = bst_tools_get_cron_frequency_options();
$cron_rows = bst_tools_get_cron_table_rows();
$current_time = time();
?>

<div class="wrap">
    <h1>Tools</h1>

    <?php if ( is_array( $bst_migration_last_run ) && ! empty( $bst_migration_last_run['text'] ) ) : ?>
    <div class="notice notice-info" style="margin:12px 0 20px;">
        <p><strong>Last vehicle migration (database)</strong> — <?php echo esc_html( isset( $bst_migration_last_run['time'] ) ? $bst_migration_last_run['time'] : '' ); ?>. This is saved even when PHP-FPM log lines are missing.</p>
        <div class="bst-tools-error-log-box" style="max-height:320px; overflow-y:auto; white-space:pre-wrap; font-family:monospace; font-size:12px; background:#fff; padding:12px;">
            <?php echo esc_html( $bst_migration_last_run['text'] ); ?>
        </div>
    </div>
    <?php endif; ?>

    <h2>Deployment Tools</h2>
    <table class="form-table">
        <?php do_settings_fields('bst_tools_page', 'bst_deployment_section'); ?>
    </table>

    <h2>Admin Operations</h2>
    <table class="form-table">
        <?php do_settings_fields('bst_tools_page', 'bst_admin_operations_section'); ?>
    </table>

    <h2>Scheduled Tasks</h2>
    <div class="bst-tools-section">
        <p>Manage your automated BST plugin tasks and schedules.</p>

        <?php if (empty($cron_rows)) : ?>
            <p>No scheduled events found.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped bst-tools-cron-table">
                <thead>
                    <tr>
                        <th class="col-hook">Event Hook</th>
                        <th class="description-col col-description">Description</th>
                        <th class="col-next">Next Run</th>
                        <th class="col-frequency">Frequency</th>
                        <th class="col-status">Status</th>
                        <th class="cron-actions-cell col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cron_rows as $row) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['hook']); ?></strong></td>
                            <td class="description-col"><?php echo esc_html($row['description']); ?></td>
                            <td>
                                <?php if ($row['next_run'] > $current_time) : ?>
                                    <?php echo esc_html(date('Y-m-d H:i:s', $row['next_run'])); ?>
                                    <br><small>In <?php echo esc_html(human_time_diff($current_time, $row['next_run'])); ?></small>
                                <?php else : ?>
                                    <span class="bst-cron-status-overdue">Overdue</span><br><small><?php echo esc_html(date('Y-m-d H:i:s', $row['next_run'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($row['frequency_label']); ?></td>
                            <td><span class="<?php echo esc_attr($row['status_class']); ?>"><?php echo esc_html($row['status_label']); ?></span></td>
                            <td class="cron-actions-cell">
                                <form method="post" class="cron-form-inline">
                                    <?php wp_nonce_field('update_cron_frequency', 'cron_nonce'); ?>
                                    <input type="hidden" name="cron_hook" value="<?php echo esc_attr($row['hook']); ?>">
                                    <select name="new_frequency">
                                        <?php foreach ($frequency_options as $freq_key => $freq_label) : ?>
                                            <option value="<?php echo esc_attr($freq_key); ?>" <?php selected($row['frequency'], $freq_key); ?>><?php echo esc_html($freq_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="submit" name="update_cron_frequency" value="Update" class="button button-small">
                                </form>
                                <?php if ($row['action_name']) : ?>
                                    <button type="button" class="button button-small" onclick="runCronEvent('<?php echo esc_js($row['action_name']); ?>', this)">Run</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
    function runCronEvent(action, button) {
        var originalText = button.textContent;
        button.textContent = 'Running...';
        button.disabled = true;
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: action, _wpnonce: '<?php echo esc_js(wp_create_nonce('bst_manual_cron')); ?>' },
            success: function(response) {
                if (response.success) { alert('Success: ' + response.data); }
                else { alert('Error: ' + response.data); }
            },
            error: function() { alert('Network error occurred'); },
            complete: function() {
                button.textContent = originalText;
                button.disabled = false;
            }
        });
    }
    </script>

    <h2>Error Log</h2>
    <div class="bst-tools-section">
        <p>View and manage PHP error logs for debugging issues.</p>
        <p class="description" style="max-width:920px;">
            Code that calls PHP’s <code>error_log()</code> (including BST release cleanup, Airwallex/exchange-rate tasks, and most plugins) writes to the file configured in PHP’s <code>error_log</code> directive — set in <strong>php.ini</strong> or your <strong>PHP-FPM pool</strong> (or equivalent). That is independent of the web server brand (nginx, IIS, etc.); it is not an Apache-only feature.
            WordPress’s <code>WP_DEBUG_LOG</code> in <code>wp-config.php</code> is separate: when enabled, WordPress also appends to <code>wp-content/debug.log</code>.
            <strong>This page shows one file:</strong> we prefer the INI path when that file exists and is readable; otherwise we fall back to <code>debug.log</code> or other known locations. On a new testing host, if the INI path is empty or points somewhere the web user cannot read, use the <strong>Release cleanup &amp; migration log</strong> section below (database copy) or align <code>WP_DEBUG_LOG</code> + <code>debug.log</code> with your host docs.
        </p>
        <p><strong>PHP <code>error_log</code> (INI):</strong> <code><?php echo esc_html( function_exists( 'bst_tools_get_ini_error_log_display' ) ? bst_tools_get_ini_error_log_display() : ini_get( 'error_log' ) ); ?></code></p>
        <p><strong>File used by this viewer:</strong> <code><?php echo esc_html( $error_log_path ); ?></code></p>

        <?php if (isset($_GET['download_error']) && $_GET['download_error'] === 'headers_sent') : ?>
            <div class="notice notice-error"><p>Error: Could not download log file. Headers already sent. This may be due to output before the download request.</p></div>
        <?php endif; ?>

        <?php if (file_exists($error_log_path)) : ?>
            <?php
            $file_size = filesize($error_log_path);
            ?>
            <p><strong>File size:</strong> <?php echo esc_html(size_format($file_size)); ?></p>
            <?php if ($file_size > 0) : ?>
                <?php
                $lines = file($error_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $total_lines = count($lines);
                $display_lines = array_slice($lines, -100);
                ?>
                <p><strong>Showing last <?php echo count($display_lines); ?> lines (of <?php echo (int) $total_lines; ?> total)</strong></p>
                <div class="bst-tools-error-log-box" style="max-height:400px; overflow-y:auto;">
                    <?php foreach ($display_lines as $line) : ?>
                        <div><?php echo esc_html($line); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p><em>Error log is empty.</em></p>
            <?php endif; ?>
        <?php else : ?>
            <p><em>Error log file not found.</em></p>
        <?php endif; ?>

        <div class="bst-tools-error-log-actions" style="margin-top:15px; margin-bottom:20px;">
            <form method="post" class="bst-tools-error-log-actions-row" style="display:flex; flex-wrap:wrap; gap:10px; margin:0;">
                <?php wp_nonce_field('bst_error_log_actions', 'error_log_actions_nonce'); ?>
                <?php wp_nonce_field('download_error_log', 'error_log_nonce'); ?>
                <button type="submit" name="download_error_log" value="1" class="button button-primary" <?php echo !file_exists($error_log_path) ? 'disabled' : ''; ?>>
                    Download Log
                </button>
                <button type="submit" name="log_action" value="clear" class="button button-secondary"
                    <?php echo !file_exists($error_log_path) ? 'disabled' : ''; ?>
                    onclick="return confirm('Are you sure you want to clear the error log? This action cannot be undone.');">
                    Clear Log
                </button>
                <button type="submit" name="log_action" value="refresh" class="button refresh">
                    Refresh
                </button>
            </form>
        </div>
    </div>

    <h2>Release cleanup &amp; migration log</h2>
    <div class="bst-tools-section">
        <p><strong>Last run (database)</strong> — this is updated every time release cleanup finishes. Use it if <code>wp-content</code> log files are missing or not writable.</p>
        <?php if ( is_array( $bst_last_release_cleanup_log ) && ! empty( $bst_last_release_cleanup_log['time'] ) ) : ?>
            <p><strong>Recorded at:</strong> <?php echo esc_html( $bst_last_release_cleanup_log['time'] ); ?></p>
            <div class="bst-tools-error-log-box" style="max-height:400px; overflow-y:auto; white-space:pre-wrap; font-family:monospace; font-size:12px;">
                <?php echo esc_html( isset( $bst_last_release_cleanup_log['text'] ) ? $bst_last_release_cleanup_log['text'] : '' ); ?>
            </div>
        <?php else : ?>
            <p><em>No release cleanup output stored yet. Run <strong>Release data cleanup</strong> from plugin settings.</em></p>
        <?php endif; ?>

        <p style="margin-top:18px;"><strong>Append-only files</strong> (when the server allows writes):</p>
        <ul style="list-style:disc; margin-left:1.25em;">
            <li><code><?php echo esc_html( $release_cleanup_log_path ); ?></code></li>
            <?php if ( $bst_release_cleanup_upload_path ) : ?>
                <li><code><?php echo esc_html( $bst_release_cleanup_upload_path ); ?></code> (uploads)</li>
            <?php endif; ?>
        </ul>
        <p><strong>File tail (wp-content log):</strong></p>
        <?php if ( file_exists( $release_cleanup_log_path ) ) : ?>
            <?php
            $rc_size = filesize( $release_cleanup_log_path );
            ?>
            <p><strong>File size:</strong> <?php echo esc_html( size_format( $rc_size ) ); ?></p>
            <?php if ( $rc_size > 0 ) : ?>
                <?php
                $rc_lines = file( $release_cleanup_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
                $rc_lines = is_array( $rc_lines ) ? $rc_lines : array();
                $rc_total = count( $rc_lines );
                $rc_display = array_slice( $rc_lines, -100 );
                ?>
                <p><strong>Showing last <?php echo count( $rc_display ); ?> lines (of <?php echo (int) $rc_total; ?> total)</strong></p>
                <div class="bst-tools-error-log-box" style="max-height:400px; overflow-y:auto;">
                    <?php foreach ( $rc_display as $line ) : ?>
                        <div><?php echo esc_html( $line ); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p><em>Log file is empty.</em></p>
            <?php endif; ?>
        <?php else : ?>
            <p><em>File not created yet. Run release data cleanup once; warnings and summaries will be written here.</em></p>
        <?php endif; ?>
    </div>

    <div class="postbox bst-tools-postbox">
        <h2 class="hndle" style="padding:12px 16px;">ID Encoding Tool</h2>
        <div class="inside">
            <p>Encode booking/entry IDs to generate secure URL codes for testing.</p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="id_to_encode">ID to Encode:</label></th>
                    <td>
                        <input type="number" id="id_to_encode" placeholder="Enter ID" class="bst-tools-id-encode-input">
                        <button type="button" class="button" onclick="encodeBookingId()">Encode</button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="encoded_result">Encoded Code:</label></th>
                    <td>
                        <input type="text" id="encoded_result" readonly class="bst-tools-id-encode-result">
                        <button type="button" class="button" onclick="copyEncodedCode()" id="copy_button" style="display:none;">Copy</button>
                        <span id="copy_message" class="bst-tools-copy-message" style="display: none;">Copied!</span>
                    </td>
                </tr>
            </table>
            <script>
            function bst_encode_booking_id(id) {
                if (!id || isNaN(id)) return '';
                var secretKey = 0x2BA7;
                var encodedNum = parseInt(id, 10) ^ secretKey;
                var base62Chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                var base62 = '';
                while (encodedNum > 0) {
                    base62 = base62Chars[encodedNum % 62] + base62;
                    encodedNum = Math.floor(encodedNum / 62);
                }
                if (!base62) base62 = '0';
                return base62;
            }
            function encodeBookingId() {
                var id = document.getElementById('id_to_encode').value;
                var result = bst_encode_booking_id(id);
                document.getElementById('encoded_result').value = result;
                document.getElementById('copy_button').style.display = result ? 'inline-block' : 'none';
                document.getElementById('copy_message').style.display = 'none';
            }
            function copyEncodedCode() {
                var resultField = document.getElementById('encoded_result');
                resultField.select();
                resultField.setSelectionRange(0, 99999);
                document.execCommand('copy');
                var message = document.getElementById('copy_message');
                message.style.display = 'inline';
                setTimeout(function() { message.style.display = 'none'; }, 2000);
            }
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('id_to_encode').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); encodeBookingId(); }
                });
            });
            </script>
        </div>
    </div>

    <div class="postbox bst-tools-postbox">
        <h2 class="hndle" style="padding:12px 16px;">Commissions</h2>
        <div class="inside">
            <p>Export commission data for bookings and annual summaries.</p>
            <?php
            $selected_tour = isset($_GET['filter_tour_id']) ? (int) $_GET['filter_tour_id'] : 0;
            $selected_date = isset($_GET['filter_tour_date_id']) ? (int) $_GET['filter_tour_date_id'] : 0;
            $selected_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
            $sort_by = isset($_GET['sort_by']) ? sanitize_text_field($_GET['sort_by']) : 'booking_date';
            $sort_order = isset($_GET['sort_order']) ? sanitize_text_field($_GET['sort_order']) : 'DESC';
            $current_year = date('Y');
            ?>
            <div class="bst-tools-commission-row" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:15px;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bst-tools-commission-form-mb" style="margin:0;">
                    <input type="hidden" name="action" value="bst_export_commission_bookings_xlsx">
                    <input type="hidden" name="filter_tour_id" value="<?php echo esc_attr($selected_tour); ?>">
                    <input type="hidden" name="filter_tour_date_id" value="<?php echo esc_attr($selected_date); ?>">
                    <input type="hidden" name="filter_status" value="<?php echo esc_attr($selected_status); ?>">
                    <input type="hidden" name="sort_by" value="<?php echo esc_attr($sort_by); ?>">
                    <input type="hidden" name="sort_order" value="<?php echo esc_attr($sort_order); ?>">
                    <?php wp_nonce_field('bst_export_commission_xlsx', 'export_nonce'); ?>
                    <button type="submit" class="button button-primary" title="Export commission bookings as Excel file with formulas and formatting">
                        Export Commission Bookings (XLSX)
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bst-tools-commission-form-row" style="margin:0;">
                    <input type="hidden" name="action" value="bst_export_annual_commission">
                    <?php wp_nonce_field('bst_export_annual_commission', 'annual_export_nonce'); ?>
                    <label for="commission_year">Year:</label>
                    <select name="commission_year" id="commission_year" class="bst-tools-commission-year">
                        <?php for ($year = 2025; $year <= 2030; $year++) : ?>
                            <option value="<?php echo (int) $year; ?>" <?php selected($current_year, (string) $year); ?>><?php echo (int) $year; ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="button button-secondary" title="Export annual commission summary by month and group">
                        Export Annual Commission Summary
                    </button>
                </form>
            </div>

            <hr class="bst-tools-hr">
            <p class="bst-tools-mb-10">Downloads a CSV of every invoice number recorded across all bookings, listing the payments attributed to each invoice with per-currency subtotals.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="bst_export_invoiced_amounts">
                <?php wp_nonce_field('bst_export_invoiced_amounts', 'invoiced_amounts_nonce'); ?>
                <button type="submit" class="button button-primary">Export Invoiced Amounts</button>
            </form>
        </div>
    </div>
</div>
