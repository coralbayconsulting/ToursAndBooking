<?php
/**
 * Share button click logging (email / WhatsApp / copy link).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action(
    'init',
    static function () {
        if ( get_option( 'bst_share_log_table_version' ) === '1' ) {
            return;
        }
        if ( function_exists( 'bst_create_share_log_table' ) ) {
            bst_create_share_log_table();
            update_option( 'bst_share_log_table_version', '1', false );
        }
    },
    5
);

/**
 * Create or update the share log table (called from create-tables.php).
 */
function bst_create_share_log_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'bst_share_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        share_method VARCHAR(20) NOT NULL,
        page_url VARCHAR(500) NOT NULL,
        page_context VARCHAR(50) NOT NULL,
        page_title VARCHAR(255) DEFAULT NULL,
        object_id BIGINT(20) UNSIGNED DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY share_method_idx (share_method),
        KEY page_context_idx (page_context),
        KEY created_at_idx (created_at),
        KEY page_url_idx (page_url(191))
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    if ( $wpdb->last_error ) {
        error_log( 'BST Plugin: Error creating share log table: ' . $wpdb->last_error );
    }
}

/**
 * Insert a share log row.
 *
 * @param array $data Share event data.
 * @return bool
 */
function bst_log_share_event( array $data ) {
    global $wpdb;

    $method = sanitize_key( $data['method'] ?? '' );
    if ( ! in_array( $method, array( 'email', 'whatsapp', 'copy' ), true ) ) {
        return false;
    }

    $url = esc_url_raw( $data['url'] ?? '' );
    if ( $url === '' ) {
        return false;
    }

    $context = sanitize_key( $data['context'] ?? 'unknown' );
    if ( $context === '' ) {
        $context = 'unknown';
    }

    $title = sanitize_text_field( $data['title'] ?? '' );
    if ( strlen( $title ) > 255 ) {
        $title = mb_substr( $title, 0, 255 );
    }

    $object_id  = isset( $data['object_id'] ) ? absint( $data['object_id'] ) : 0;
    $user_agent = sanitize_text_field( $data['user_agent'] ?? '' );
    if ( strlen( $user_agent ) > 255 ) {
        $user_agent = mb_substr( $user_agent, 0, 255 );
    }

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'bst_share_log',
        array(
            'share_method'  => $method,
            'page_url'      => $url,
            'page_context'  => $context,
            'page_title'    => $title !== '' ? $title : null,
            'object_id'     => $object_id > 0 ? $object_id : null,
            'user_agent'    => $user_agent !== '' ? $user_agent : null,
            'created_at'    => current_time( 'mysql' ),
        ),
        array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
    );

    return $inserted !== false;
}

/**
 * AJAX handler for frontend share click logging.
 */
function bst_ajax_log_share_event() {
    check_ajax_referer( 'bst_share_log', 'nonce' );

    $logged = bst_log_share_event(
        array(
            'method'     => wp_unslash( $_POST['method'] ?? '' ),
            'url'        => wp_unslash( $_POST['url'] ?? '' ),
            'context'    => wp_unslash( $_POST['context'] ?? '' ),
            'title'      => wp_unslash( $_POST['title'] ?? '' ),
            'object_id'  => absint( $_POST['object_id'] ?? 0 ),
            'user_agent' => wp_unslash( $_POST['user_agent'] ?? '' ),
        )
    );

    if ( $logged ) {
        wp_send_json_success();
    }

    wp_send_json_error( array( 'message' => 'Could not log share event.' ), 400 );
}
add_action( 'wp_ajax_bst_log_share_event', 'bst_ajax_log_share_event' );
add_action( 'wp_ajax_nopriv_bst_log_share_event', 'bst_ajax_log_share_event' );

/**
 * Localize share tracking config when the share script is enqueued.
 */
function bst_localize_share_log_script() {
    if ( ! wp_script_is( 'bst-tour-share', 'enqueued' ) ) {
        return;
    }

    wp_localize_script(
        'bst-tour-share',
        'bstShareLog',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bst_share_log' ),
        )
    );
}
add_action( 'wp_enqueue_scripts', 'bst_localize_share_log_script', 20 );

/**
 * Summary stats for the Tools page.
 *
 * @param int $days Number of days to include.
 * @return array
 */
function bst_share_log_get_summary( $days = 30 ) {
    global $wpdb;

    $table = $wpdb->prefix . 'bst_share_log';
    $since = gmdate( 'Y-m-d H:i:s', strtotime( '-' . absint( $days ) . ' days' ) );

    $totals = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT share_method, COUNT(*) AS total
             FROM $table
             WHERE created_at >= %s
             GROUP BY share_method",
            $since
        ),
        OBJECT_K
    );

    $all_time = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    $period   = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE created_at >= %s", $since )
    );

    return array(
        'days'     => $days,
        'all_time' => $all_time,
        'period'   => $period,
        'email'    => isset( $totals['email'] ) ? (int) $totals['email']->total : 0,
        'whatsapp' => isset( $totals['whatsapp'] ) ? (int) $totals['whatsapp']->total : 0,
        'copy'     => isset( $totals['copy'] ) ? (int) $totals['copy']->total : 0,
    );
}

/**
 * Top shared pages in the last N days.
 *
 * @param int $days  Days to include.
 * @param int $limit Max rows.
 * @return array
 */
function bst_share_log_get_top_pages( $days = 30, $limit = 10 ) {
    global $wpdb;

    $table = $wpdb->prefix . 'bst_share_log';
    $since = gmdate( 'Y-m-d H:i:s', strtotime( '-' . absint( $days ) . ' days' ) );

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT page_title, page_url, page_context, COUNT(*) AS total,
                    SUM(CASE WHEN share_method = 'email' THEN 1 ELSE 0 END) AS email_count,
                    SUM(CASE WHEN share_method = 'whatsapp' THEN 1 ELSE 0 END) AS whatsapp_count,
                    SUM(CASE WHEN share_method = 'copy' THEN 1 ELSE 0 END) AS copy_count
             FROM $table
             WHERE created_at >= %s
             GROUP BY page_url, page_title, page_context
             ORDER BY total DESC
             LIMIT %d",
            $since,
            absint( $limit )
        )
    );
}

/**
 * Recent share log rows.
 *
 * @param int $limit Max rows.
 * @return array
 */
function bst_share_log_get_recent( $limit = 50 ) {
    global $wpdb;

    $table = $wpdb->prefix . 'bst_share_log';

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC, id DESC LIMIT %d",
            absint( $limit )
        )
    );
}

/**
 * Render the share log section on the BST Tools page.
 */
function bst_share_log_render_tools_section() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $summary   = bst_share_log_get_summary( 30 );
    $top_pages = bst_share_log_get_top_pages( 30, 10 );
    $recent    = bst_share_log_get_recent( 50 );
    ?>
    <div class="bst-tools-section">
        <p>Tracks clicks on share buttons (email, WhatsApp, copy link). Counts reflect user intent, not confirmed sends.</p>

        <p>
            <strong>Last 30 days:</strong>
            <?php echo esc_html( number_format_i18n( $summary['period'] ) ); ?> events
            (Email: <?php echo esc_html( number_format_i18n( $summary['email'] ) ); ?>,
            WhatsApp: <?php echo esc_html( number_format_i18n( $summary['whatsapp'] ) ); ?>,
            Copy: <?php echo esc_html( number_format_i18n( $summary['copy'] ) ); ?>)
            &nbsp;|&nbsp;
            <strong>All time:</strong> <?php echo esc_html( number_format_i18n( $summary['all_time'] ) ); ?>
        </p>

        <?php if ( ! empty( $top_pages ) ) : ?>
            <h3>Most shared pages (last 30 days)</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Context</th>
                        <th>Email</th>
                        <th>WhatsApp</th>
                        <th>Copy</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $top_pages as $row ) : ?>
                        <tr>
                            <td>
                                <?php if ( ! empty( $row->page_title ) ) : ?>
                                    <strong><?php echo esc_html( $row->page_title ); ?></strong><br>
                                <?php endif; ?>
                                <a href="<?php echo esc_url( $row->page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row->page_url ); ?></a>
                            </td>
                            <td><?php echo esc_html( $row->page_context ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( (int) $row->email_count ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( (int) $row->whatsapp_count ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( (int) $row->copy_count ) ); ?></td>
                            <td><strong><?php echo esc_html( number_format_i18n( (int) $row->total ) ); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><em>No share activity recorded yet.</em></p>
        <?php endif; ?>

        <?php if ( ! empty( $recent ) ) : ?>
            <h3 style="margin-top:24px;">Recent activity</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Method</th>
                        <th>Page</th>
                        <th>Context</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row->created_at ); ?></td>
                            <td><?php echo esc_html( ucfirst( $row->share_method ) ); ?></td>
                            <td>
                                <?php if ( ! empty( $row->page_title ) ) : ?>
                                    <?php echo esc_html( $row->page_title ); ?><br>
                                <?php endif; ?>
                                <a href="<?php echo esc_url( $row->page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row->page_url ); ?></a>
                            </td>
                            <td><?php echo esc_html( $row->page_context ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
