<?php
/**
 * Template for displaying the Invoices list page.
 *
 * Available variables (set by bst_invoices_page()):
 *   - $invoices:      Array of booking objects where invoice has been generated.
 *   - $filter_year:   Currently selected year filter (int or 0 for all).
 *   - $filter_month:  Currently selected month filter (int 1-12 or 0 for all).
 *   - $available_years: Array of years that have invoices.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Currency formatter helper
function bst_invoices_format_currency( $amount, $symbol = '€' ) {
    if ( $amount === null || $amount === '' ) {
        return '—';
    }
    return $symbol . number_format( floatval( $amount ), 2 );
}

/**
 * Build a URL for a sortable column header, preserving current filters.
 * Returns an array: ['url' => string, 'next_order' => string, 'indicator' => string]
 */
function bst_invoices_sort_link( $col_key, $current_sort_by, $current_sort_order, $filter_year, $filter_month ) {
    $is_active  = ( $current_sort_by === $col_key );
    $next_order = ( $is_active && $current_sort_order === 'ASC' ) ? 'DESC' : 'ASC';
    $args = array(
        'page'         => 'bst-invoices',
        'sort_by'      => $col_key,
        'sort_order'   => $next_order,
    );
    if ( $filter_year )  $args['filter_year']  = $filter_year;
    if ( $filter_month ) $args['filter_month'] = $filter_month;
    $url = admin_url( 'admin.php?' . http_build_query( $args ) );

    if ( $is_active ) {
        $indicator = $current_sort_order === 'ASC' ? ' ▲' : ' ▼';
    } else {
        $indicator = ' <span style="color:#bbb;">⇅</span>';
    }
    return array( 'url' => $url, 'indicator' => $indicator );
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Invoices</h1>
    <hr class="wp-header-end">

    <!-- Filters -->
    <form method="get" action="" style="margin: 15px 0;">
        <input type="hidden" name="page" value="bst-invoices">
        <?php if ( $sort_by !== 'invoice_number' ) : ?>
            <input type="hidden" name="sort_by" value="<?php echo esc_attr( $sort_by ); ?>">
        <?php endif; ?>
        <?php if ( $sort_order !== 'ASC' ) : ?>
            <input type="hidden" name="sort_order" value="<?php echo esc_attr( $sort_order ); ?>">
        <?php endif; ?>

        <label for="filter_year"><strong>Year:</strong></label>
        <select id="filter_year" name="filter_year" style="margin-right: 10px;">
            <option value="0">All Years</option>
            <?php foreach ( $available_years as $year ) : ?>
                <option value="<?php echo esc_attr( $year ); ?>"
                    <?php selected( $filter_year, $year ); ?>>
                    <?php echo esc_html( $year ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="filter_month"><strong>Month:</strong></label>
        <select id="filter_month" name="filter_month" style="margin-right: 10px;">
            <option value="0">All Months</option>
            <?php
            $month_names = array(
                1  => 'January', 2  => 'February',  3  => 'March',
                4  => 'April',   5  => 'May',        6  => 'June',
                7  => 'July',    8  => 'August',     9  => 'September',
                10 => 'October', 11 => 'November',  12 => 'December',
            );
            foreach ( $month_names as $num => $name ) :
            ?>
                <option value="<?php echo esc_attr( $num ); ?>"
                    <?php selected( $filter_month, $num ); ?>>
                    <?php echo esc_html( $name ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="button button-primary">Filter</button>
        <?php if ( $filter_year || $filter_month ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bst-invoices' ) ); ?>" class="button">Clear</a>
        <?php endif; ?>
    </form>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-top: -38px; float: right;">
        <input type="hidden" name="action"       value="bst_export_invoices">
        <input type="hidden" name="filter_year"  value="<?php echo esc_attr( $filter_year ); ?>">
        <input type="hidden" name="filter_month" value="<?php echo esc_attr( $filter_month ); ?>">
        <input type="hidden" name="sort_by"      value="<?php echo esc_attr( $sort_by ); ?>">
        <input type="hidden" name="sort_order"   value="<?php echo esc_attr( $sort_order ); ?>">
        <input type="hidden" name="_wpnonce"     value="<?php echo wp_create_nonce( 'bst_export_invoices' ); ?>">
        <button type="submit" class="button" style="background:#00a32a; color:white; border-color:#00a32a;">
            📊 Export Selection to Excel
        </button>
    </form>

    <!-- Results count -->
    <p style="color: #666; font-size: 13px;">
        <?php
        $count = count( $invoices );
        echo esc_html( $count . ' invoice' . ( $count !== 1 ? 's' : '' ) . ' found' );
        if ( $filter_year || $filter_month ) {
            $parts = array();
            if ( $filter_year ) $parts[] = $filter_year;
            if ( $filter_month ) $parts[] = $month_names[ $filter_month ];
            echo esc_html( ' for ' . implode( ' ', $parts ) );
        }
        ?>
    </p>

    <?php if ( empty( $invoices ) ) : ?>
        <div class="notice notice-info inline"><p>No invoices found<?php echo ( $filter_year || $filter_month ) ? ' for the selected period.' : '.'; ?></p></div>
    <?php else : ?>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
        <thead>
            <tr>
                <?php
                $sl_num  = bst_invoices_sort_link( 'invoice_number', $sort_by, $sort_order, $filter_year, $filter_month );
                $sl_date = bst_invoices_sort_link( 'invoice_date',   $sort_by, $sort_order, $filter_year, $filter_month );
                $sl_name = bst_invoices_sort_link( 'name',           $sort_by, $sort_order, $filter_year, $filter_month );
                ?>
                <th style="width: 110px;">
                    <a href="<?php echo esc_url( $sl_num['url'] ); ?>" style="text-decoration:none; color:inherit; white-space:nowrap;">
                        Invoice #<?php echo $sl_num['indicator']; ?>
                    </a>
                </th>
                <th style="width: 90px;">Booking ID</th>
                <th style="width: 100px;">
                    <a href="<?php echo esc_url( $sl_date['url'] ); ?>" style="text-decoration:none; color:inherit; white-space:nowrap;">
                        Invoice Date<?php echo $sl_date['indicator']; ?>
                    </a>
                </th>
                <th>
                    <a href="<?php echo esc_url( $sl_name['url'] ); ?>" style="text-decoration:none; color:inherit; white-space:nowrap;">
                        Name<?php echo $sl_name['indicator']; ?>
                    </a>
                </th>
                <th>Tour</th>
                <th style="width: 120px; text-align: right;">Tour Packages</th>
                <th style="width: 120px; text-align: right;">Other Services</th>
                <th style="width: 100px; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $invoices as $booking ) :

            // --- Invoice link ---
            $invoice_number = $booking->booking_invoice_number ?? '';
            $invoice_url    = '';
            if ( ! empty( $booking->finalization_entry_id ) && function_exists( 'bst_encode_booking_id' ) ) {
                $encoded   = bst_encode_booking_id( $booking->finalization_entry_id );
                $invoice_url = site_url( '/bookinginvoice/' ) . '?eid=' . $encoded;
            }

            // --- Booking edit link ---
            $booking_edit_url = admin_url( 'admin.php?page=view_booking&id=' . intval( $booking->id ) );

            // --- Invoice date ---
            $invoice_date = '';
            if ( ! empty( $booking->booking_invoice_date ) ) {
                $ts = strtotime( $booking->booking_invoice_date );
                if ( $ts ) {
                    $invoice_date = date( 'Y-m-d', $ts );
                }
            }

            // --- Name (same default logic as invoice: use guest1) ---
            $name = trim(
                ( $booking->guest1_first_name ?? '' ) . ' ' .
                ( $booking->guest1_last_name  ?? '' )
            );

            // --- Tour (same logic as invoice) ---
            $tour_display = $booking->tour_text ?? '';
            $tour_year    = $booking->tour_year ?? '';
            $tour_dates   = $booking->tour_date_text ?? '';
            $tour_pkg     = $booking->tour_package_type ?? '';
            if ( ! empty( $tour_year ) ) {
                $tour_display .= ' (' . $tour_year . ')';
            }
            if ( ! empty( $tour_dates ) ) {
                $tour_display .= ' (' . $tour_dates . ')';
            }
            if ( ! empty( $tour_pkg ) ) {
                $tour_display .= ' – ' . $tour_pkg;
            }

            // --- Amounts (same logic as invoice) ---
            $currency        = $booking->tour_currency ?? 'EUR';
            $currency_symbol = ( $currency === 'USD' ) ? '$' : '€';

            $tour_packages   = floatval( $booking->booking_tour_package_amount ?? 0 );
            $vehicle1        = floatval( $booking->booking_vehicle_1_use_amount ?? 0 );
            $vehicle2        = floatval( $booking->booking_vehicle_2_use_amount ?? 0 );
            $other_services  = $vehicle1 + $vehicle2;

            // VAT on other services (same formula as invoice)
            $eu_percent      = floatval( $booking->booking_eu_percent ?? 100 );
            $vat_rate        = floatval( $booking->booking_vat_rate   ?? 22 );
            $vehicle_vat     = $other_services * ( $eu_percent / 100 ) * ( $vat_rate / 100 );

            $total           = $tour_packages + $other_services + $vehicle_vat;

        ?>
            <tr>
                <td>
                    <?php if ( $invoice_url ) : ?>
                        <a href="<?php echo esc_url( $invoice_url ); ?>" target="_blank">
                            <?php echo esc_html( $invoice_number ); ?>
                        </a>
                    <?php else : ?>
                        <?php echo esc_html( $invoice_number ); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo esc_url( $booking_edit_url ); ?>" target="_blank">
                        #<?php echo esc_html( $booking->id ); ?>
                    </a>
                </td>
                <td><?php echo esc_html( $invoice_date ); ?></td>
                <td><?php echo esc_html( $name ); ?></td>
                <td><?php echo esc_html( $tour_display ); ?></td>
                <td style="text-align: right;">
                    <?php echo esc_html( $currency_symbol . number_format( $tour_packages, 2 ) ); ?>
                </td>
                <td style="text-align: right;">
                    <?php
                    if ( $other_services > 0 ) {
                        echo esc_html( $currency_symbol . number_format( $other_services, 2 ) );
                    } else {
                        echo '<span style="color:#aaa;">—</span>';
                    }
                    ?>
                </td>
                <td style="text-align: right;">
                    <?php echo esc_html( $currency_symbol . number_format( $total, 2 ) ); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align: right; padding-right: 10px;">Totals:</th>
                <?php
                // Sum amounts across all invoices in the current filtered result
                $sum_packages       = 0;
                $sum_other_services = 0;
                $sum_total          = 0;
                foreach ( $invoices as $b ) {
                    $tp   = floatval( $b->booking_tour_package_amount ?? 0 );
                    $v1   = floatval( $b->booking_vehicle_1_use_amount ?? 0 );
                    $v2   = floatval( $b->booking_vehicle_2_use_amount ?? 0 );
                    $os   = $v1 + $v2;
                    $eu   = floatval( $b->booking_eu_percent ?? 100 );
                    $vr   = floatval( $b->booking_vat_rate   ?? 22 );
                    $vvat = $os * ( $eu / 100 ) * ( $vr / 100 );
                    $sum_packages       += $tp;
                    $sum_other_services += $os;
                    $sum_total          += $tp + $os + $vvat;
                }
                ?>
                <th style="text-align: right;">€<?php echo number_format( $sum_packages, 2 ); ?></th>
                <th style="text-align: right;"><?php echo $sum_other_services > 0 ? '€' . number_format( $sum_other_services, 2 ) : '—'; ?></th>
                <th style="text-align: right;">€<?php echo number_format( $sum_total, 2 ); ?></th>
            </tr>
        </tfoot>
    </table>

    <?php endif; ?>
</div>
