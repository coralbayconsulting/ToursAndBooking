<?php
/**
 * Per-payment status (deposit / balance / additional / refund) + migration helpers.
 *
 * @package BST_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string Paid */
define( 'BST_PAYMENT_STATUS_PAID', 'Paid' );

/** @var string Pending (customer-facing also used for Processing) */
define( 'BST_PAYMENT_STATUS_PENDING', 'Pending' );

/** @var string Failed */
define( 'BST_PAYMENT_STATUS_FAILED', 'Failed' );

/** @var string Processing (SEPA / async — stored; display as Pending where requested) */
define( 'BST_PAYMENT_STATUS_PROCESSING', 'Processing' );

/** @var string Transferred — payment moved to another booking; not commissioned */
define( 'BST_PAYMENT_STATUS_TRANSFERRED', 'Transferred' );

/**
 * Allowed values for DB / UI.
 *
 * @return string[]
 */
function bst_payment_status_allowed_values() {
	return array(
		BST_PAYMENT_STATUS_PAID,
		BST_PAYMENT_STATUS_PENDING,
		BST_PAYMENT_STATUS_FAILED,
		BST_PAYMENT_STATUS_PROCESSING,
		BST_PAYMENT_STATUS_TRANSFERRED,
	);
}

/**
 * @param string|null $status Raw status.
 * @return string|null
 */
function bst_sanitize_payment_status( $status ) {
	if ( null === $status || '' === $status ) {
		return null;
	}
	$status = sanitize_text_field( (string) $status );
	return in_array( $status, bst_payment_status_allowed_values(), true ) ? $status : null;
}

/**
 * Whether a payment line should count for commission basis when its commission invoice is still empty.
 * Uses stored payment status + amount (legacy rows may have NULL status with amount set).
 *
 * @param string|null $status  Stored *_payment_status.
 * @param float       $amount  Line amount in tour currency.
 * @return bool
 */
function bst_payment_status_commission_eligible( $status, $amount ) {
	$st = ( null === $status || '' === $status ) ? '' : trim( (string) $status );
	if ( BST_PAYMENT_STATUS_TRANSFERRED === $st ) {
		return false;
	}
	if ( in_array( $st, array( BST_PAYMENT_STATUS_PENDING, BST_PAYMENT_STATUS_FAILED ), true ) ) {
		return false;
	}
	if ( in_array( $st, array( BST_PAYMENT_STATUS_PAID, BST_PAYMENT_STATUS_PROCESSING ), true ) ) {
		return floatval( $amount ) > 0;
	}
	// Legacy: status not set but amount recorded — treat as received.
	if ( '' === $st ) {
		return floatval( $amount ) > 0;
	}
	return false;
}

/*
 * Commission / CBC invoice basis — tour booking rows
 *
 * Invoice commission when a payment line is eligible (status + amount) and the CBC commission invoice field is empty.
 *
 * Refund:
 * - If no deposit/balance/additional commission was invoiced yet: uninvoiced refunds net against uninvoiced inflows
 *   (deposit → balance → additional). If they cancel, net basis is 0 (typical cancelled booking).
 * - If some inflow commission was already invoiced: reverse commission only on min(refund, invoiced inflow total)
 *   (deposit + balance + additional lines with a commission invoice). Any refund remainder nets against
 *   uninvoiced inflows in the same order.
 */

/**
 * Commission invoice still needed for this line? (Eligible received funds, no invoice number yet.)
 *
 * @param string|null $commission_invoice Stored invoice field for the line.
 * @param string|null $payment_status     Line payment status.
 * @param float       $amount             Line amount.
 * @return bool
 */
function bst_commission_line_needs_invoice( $commission_invoice, $payment_status, $amount ) {
	if ( ! empty( $commission_invoice ) ) {
		return false;
	}
	return bst_payment_status_commission_eligible( $payment_status, $amount );
}

/**
 * Refund line still needs a CBC commission invoice (eligible paid refund, no invoice number yet).
 *
 * @param object $booking Booking row.
 * @return bool
 */
function bst_commission_refund_needs_invoice( $booking ) {
	if ( floatval( $booking->refund_payment_amount ?? 0 ) <= 0 ) {
		return false;
	}
	if ( ! empty( $booking->refund_commission_invoice ) ) {
		return false;
	}
	return bst_payment_status_commission_eligible( $booking->refund_payment_status ?? '', $booking->refund_payment_amount ?? 0 );
}

/**
 * Sum of deposit / balance / additional amounts that already have a CBC commission invoice.
 *
 * @param object $booking Booking row.
 * @return float
 */
function bst_commission_invoiced_inflow_total( $booking ) {
	$total = 0.0;
	$lines = array(
		array( 'deposit_commission_invoice', 'deposit_payment_amount' ),
		array( 'balance_commission_invoice', 'balance_payment_amount' ),
		array( 'additional_payment_commission_invoice', 'additional_payment_amount' ),
	);
	foreach ( $lines as $line ) {
		$inv_field = $line[0];
		$amt_field = $line[1];
		if ( ! empty( $booking->{$inv_field} ) ) {
			$total += floatval( $booking->{$amt_field} ?? 0 );
		}
	}
	return $total;
}

/**
 * Refund commission reversal basis: only the portion of the refund that unwinds invoiced inflows.
 *
 * @param object $booking Booking row.
 * @return float Non-negative amount in booking currency (0 when no reversal applies).
 */
function bst_commission_refund_reversal_amount( $booking ) {
	if ( floatval( $booking->refund_payment_amount ?? 0 ) <= 0 ) {
		return 0.0;
	}
	if ( ! bst_payment_status_commission_eligible( $booking->refund_payment_status ?? '', $booking->refund_payment_amount ?? 0 ) ) {
		return 0.0;
	}
	$invoiced = bst_commission_invoiced_inflow_total( $booking );
	if ( $invoiced <= 0 ) {
		return 0.0;
	}
	return min( floatval( $booking->refund_payment_amount ?? 0 ), $invoiced );
}

/**
 * Reduce uninvoiced inflow lines by a refund (deposit → balance → additional).
 *
 * @param array{deposit:float,balance:float,additional:float} $amounts           Gross uninvoiced per line.
 * @param float                                                 $refund_remaining  Refund amount left to net.
 * @return array{deposit:float,balance:float,additional:float}
 */
function bst_commission_apply_refund_netting_to_inflows( array $amounts, $refund_remaining ) {
	$out               = $amounts;
	$refund_remaining  = max( 0.0, floatval( $refund_remaining ) );
	foreach ( array( 'deposit', 'balance', 'additional' ) as $key ) {
		if ( $refund_remaining <= 0 ) {
			break;
		}
		$line = floatval( $out[ $key ] ?? 0 );
		if ( $line <= 0 ) {
			continue;
		}
		$consumed       = min( $line, $refund_remaining );
		$out[ $key ]    = $line - $consumed;
		$refund_remaining -= $consumed;
	}
	return $out;
}

/**
 * Per-line amounts (deposit, balance, additional) still needing a CBC commission invoice.
 * Applies refund netting for the “wash” case (see file comment). When some inflows were already
 * commissioned, any refund remainder after the capped reversal nets against uninvoiced lines.
 *
 * @param object $booking Booking row.
 * @return array{deposit:float,balance:float,additional:float}
 */
function bst_commission_uninvoiced_inflow_amounts( $booking ) {
	$out = array(
		'deposit'    => 0.0,
		'balance'    => 0.0,
		'additional' => 0.0,
	);
	if ( ! function_exists( 'bst_commission_line_needs_invoice' ) ) {
		return $out;
	}

	$dep = bst_commission_line_needs_invoice( $booking->deposit_commission_invoice ?? '', $booking->deposit_payment_status ?? '', $booking->deposit_payment_amount ?? 0 )
		? floatval( $booking->deposit_payment_amount ?? 0 ) : 0.0;
	$bal = bst_commission_line_needs_invoice( $booking->balance_commission_invoice ?? '', $booking->balance_payment_status ?? '', $booking->balance_payment_amount ?? 0 )
		? floatval( $booking->balance_payment_amount ?? 0 ) : 0.0;
	$add = bst_commission_line_needs_invoice( $booking->additional_payment_commission_invoice ?? '', $booking->additional_payment_status ?? '', $booking->additional_payment_amount ?? 0 )
		? floatval( $booking->additional_payment_amount ?? 0 ) : 0.0;

	$gross = array(
		'deposit'    => $dep,
		'balance'    => $bal,
		'additional' => $add,
	);

	// Mixed case: some inflows commissioned — cap reversal at invoiced total; net any remainder against uninvoiced lines.
	if ( function_exists( 'bst_commission_refund_reduces_basis' ) && bst_commission_refund_reduces_basis( $booking ) ) {
		$reversal  = bst_commission_refund_reversal_amount( $booking );
		$remainder = floatval( $booking->refund_payment_amount ?? 0 ) - $reversal;
		return bst_commission_apply_refund_netting_to_inflows( $gross, $remainder );
	}

	// No uninvoiced refund to apply — gross uninvoiced inflows stand.
	if ( ! function_exists( 'bst_commission_refund_needs_invoice' ) || ! bst_commission_refund_needs_invoice( $booking ) ) {
		return $gross;
	}

	return bst_commission_apply_refund_netting_to_inflows( $gross, floatval( $booking->refund_payment_amount ?? 0 ) );
}

/**
 * Refund line reduces commission basis (negative) when refund is commission-eligible, refund not yet invoiced,
 * and a deposit/balance/additional commission was already invoiced.
 *
 * @param object $booking Booking row.
 * @return bool
 */
function bst_commission_refund_reduces_basis( $booking ) {
	if ( floatval( $booking->refund_payment_amount ?? 0 ) <= 0 ) {
		return false;
	}
	if ( ! empty( $booking->refund_commission_invoice ) ) {
		return false;
	}
	if ( ! bst_payment_status_commission_eligible( $booking->refund_payment_status ?? '', $booking->refund_payment_amount ?? 0 ) ) {
		return false;
	}
	$has_invoiced = ! empty( $booking->deposit_commission_invoice )
		|| ! empty( $booking->balance_commission_invoice )
		|| ! empty( $booking->additional_payment_commission_invoice );
	return $has_invoiced;
}

/**
 * Net commission basis in booking currency (before commission % and EUR conversion in exports).
 * Use this everywhere a single “should we invoice / how much basis” number is needed.
 *
 * @param object $booking Booking row.
 * @return float
 */
function bst_commission_booking_net_basis_original_currency( $booking ) {
	$nets  = bst_commission_uninvoiced_inflow_amounts( $booking );
	$basis = floatval( $nets['deposit'] ?? 0 ) + floatval( $nets['balance'] ?? 0 ) + floatval( $nets['additional'] ?? 0 );
	if ( bst_commission_refund_reduces_basis( $booking ) ) {
		$basis -= bst_commission_refund_reversal_amount( $booking );
	}
	return $basis;
}

/**
 * Customer-facing label: Processing is shown as Pending.
 *
 * @param string|null $status Stored status.
 * @return string
 */
function bst_payment_status_label_for_display( $status ) {
	if ( BST_PAYMENT_STATUS_PROCESSING === $status ) {
		return BST_PAYMENT_STATUS_PENDING;
	}
	return $status ? $status : '';
}

/**
 * @param string|null $status Stored status.
 * @return bool
 */
function bst_payment_status_is_unsettled( $status ) {
	return in_array( $status, array( BST_PAYMENT_STATUS_PENDING, BST_PAYMENT_STATUS_PROCESSING, BST_PAYMENT_STATUS_FAILED ), true );
}

/**
 * Whether a payment line counts as received/settled for booking summary / confirmation display.
 * Prefer this over inferring from amount + date alone. Legacy rows with empty status but amount &gt; 0 count as received.
 *
 * @param string|null  $status Stored *_payment_status.
 * @param float|string $amount Line amount in tour currency.
 * @return bool
 */
function bst_payment_line_received_for_display( $status, $amount ) {
	$amt = floatval( $amount );
	$st  = ( null === $status || '' === $status ) ? '' : trim( (string) $status );
	if ( BST_PAYMENT_STATUS_TRANSFERRED === $st ) {
		return false;
	}
	if ( BST_PAYMENT_STATUS_PENDING === $st || BST_PAYMENT_STATUS_FAILED === $st ) {
		return false;
	}
	if ( in_array( $st, array( BST_PAYMENT_STATUS_PAID, BST_PAYMENT_STATUS_PROCESSING ), true ) ) {
		return $amt > 0;
	}
	if ( '' === $st ) {
		return $amt > 0;
	}
	return false;
}

/**
 * Derive deposit line status from GF9 entry (single payment on form).
 *
 * @param array $entry GF entry.
 * @return string|null
 */
function bst_derive_deposit_payment_status_from_gf9_entry( $entry ) {
	$payment_method = rgar( $entry, '118' );
	if ( '' === (string) $payment_method ) {
		return null;
	}
	$eps = isset( $entry['payment_status'] ) ? $entry['payment_status'] : '';
	if ( 'Failed' === $eps ) {
		return BST_PAYMENT_STATUS_FAILED;
	}
	if ( 'Processing' === $eps ) {
		return BST_PAYMENT_STATUS_PROCESSING;
	}
	if ( 'Bank Wire' === $payment_method ) {
		$wire_received_date   = rgar( $entry, '209' );
		$wire_received_amount = rgar( $entry, '210' );
		if ( empty( $wire_received_date ) || empty( $wire_received_amount ) ) {
			return BST_PAYMENT_STATUS_PENDING;
		}
		return BST_PAYMENT_STATUS_PAID;
	}
	// Credit card / other: paid when we have an amount.
	$amt = floatval( preg_replace( '/[^0-9.]/', '', rgar( $entry, '177' ) ) );
	if ( empty( $amt ) ) {
		$amt = floatval( rgar( $entry, 'payment_amount' ) );
	}
	if ( $amt > 0 ) {
		return BST_PAYMENT_STATUS_PAID;
	}
	return BST_PAYMENT_STATUS_PENDING;
}

/**
 * Derive balance line status from GF10 entry.
 *
 * @param array $entry GF entry.
 * @return string|null
 */
function bst_derive_balance_payment_status_from_gf10_entry( $entry ) {
	$payment_method = rgar( $entry, '118' );
	if ( '' === (string) $payment_method ) {
		return null;
	}
	$eps = isset( $entry['payment_status'] ) ? $entry['payment_status'] : '';
	if ( 'Failed' === $eps ) {
		return BST_PAYMENT_STATUS_FAILED;
	}
	if ( 'Processing' === $eps ) {
		return BST_PAYMENT_STATUS_PROCESSING;
	}
	if ( 'Bank Wire' === $payment_method ) {
		$wire_received_date   = rgar( $entry, '213' );
		$wire_received_amount = rgar( $entry, '214' );
		if ( empty( $wire_received_date ) || empty( $wire_received_amount ) ) {
			return BST_PAYMENT_STATUS_PENDING;
		}
		return BST_PAYMENT_STATUS_PAID;
	}
	$amt = floatval( rgar( $entry, '208' ) );
	if ( empty( $amt ) ) {
		$amt = floatval( rgar( $entry, '191' ) );
	}
	if ( $amt > 0 ) {
		return BST_PAYMENT_STATUS_PAID;
	}
	return BST_PAYMENT_STATUS_PENDING;
}

/**
 * Merge GF-derived booking status with per-line statuses: any unsettled line → Pending (except Failed wins).
 *
 * @param string      $gf_status Status from bst_calculate_gf*_payment_details.
 * @param string|null $deposit_status
 * @param string|null $balance_status
 * @param string|null $additional_status
 * @param string|null $refund_status
 * @return string
 */
function bst_merge_booking_status_with_payment_line_statuses( $gf_status, $deposit_status, $balance_status, $additional_status, $refund_status ) {
	$lines = array( $deposit_status, $balance_status, $additional_status, $refund_status );
	if ( 'Payment Failed' === $gf_status ) {
		return 'Payment Failed';
	}
	foreach ( $lines as $s ) {
		if ( BST_PAYMENT_STATUS_FAILED === $s ) {
			return 'Payment Failed';
		}
	}
	foreach ( $lines as $s ) {
		if ( BST_PAYMENT_STATUS_PENDING === $s || BST_PAYMENT_STATUS_PROCESSING === $s ) {
			return 'Pending';
		}
	}
	if ( 'Processing' === $gf_status ) {
		return 'Pending';
	}
	return $gf_status ? $gf_status : 'Pending';
}

/**
 * Sum of deposit / balance / additional line amounts where status is Pending or Processing
 * (expected money not yet settled — Status column shows detail per line).
 *
 * @param object $booking Booking row object.
 * @return float
 */
function bst_booking_pending_inflow_amount( $booking ) {
	$sum = 0;
	foreach ( array( 'deposit', 'balance', 'additional' ) as $prefix ) {
		$st = isset( $booking->{$prefix . '_payment_status'} ) ? (string) $booking->{$prefix . '_payment_status'} : '';
		if ( in_array( $st, array( BST_PAYMENT_STATUS_PENDING, BST_PAYMENT_STATUS_PROCESSING ), true ) ) {
			$sum += floatval( $booking->{$prefix . '_payment_amount'} ?? 0 );
		}
	}
	return $sum;
}

/**
 * HTML fragment: " (pending €X Method)" for summary lines (Total Paid / Balance Due).
 * One segment per inflow line with Pending/Processing; Bank Wire shown as Bank Transfer.
 *
 * @param object $booking Booking row.
 * @return string Safe HTML (empty if nothing pending).
 */
function bst_booking_pending_payment_note_html( $booking ) {
	if ( ! function_exists( 'bst_format_currency' ) ) {
		return '';
	}
	$currency = $booking->tour_currency ?? 'EUR';
	$segments = array();
	foreach ( array( 'deposit', 'balance', 'additional' ) as $prefix ) {
		$st = isset( $booking->{$prefix . '_payment_status'} ) ? (string) $booking->{$prefix . '_payment_status'} : '';
		if ( ! in_array( $st, array( BST_PAYMENT_STATUS_PENDING, BST_PAYMENT_STATUS_PROCESSING ), true ) ) {
			continue;
		}
		$amt = floatval( $booking->{$prefix . '_payment_amount'} ?? 0 );
		if ( $amt <= 0 ) {
			continue;
		}
		$raw = isset( $booking->{$prefix . '_payment_method'} ) ? (string) $booking->{$prefix . '_payment_method'} : '';
		$method = ( 'Bank Wire' === $raw ) ? 'Bank Transfer' : $raw;
		if ( '' === $method ) {
			$method = '—';
		}
		$segments[] = trim( bst_format_currency( $amt, $currency ) ) . ' ' . $method;
	}
	if ( empty( $segments ) ) {
		return '';
	}
	$inner = 'pending ' . implode( '; ', $segments );
	return ' <span class="bst-financials-pending-note" style="color:#666;font-size:0.9em;">(' . esc_html( $inner ) . ')</span>';
}

/**
 * Expected deposit wire amount (tour currency): GF field 230, else bst_calculate_deposit minus discount.
 *
 * @param object $booking Row object.
 * @return float
 */
function bst_get_expected_deposit_wire_amount_for_booking( $booking ) {
	if ( ! empty( $booking->booking_entry_id ) && class_exists( 'GFAPI' ) ) {
		$entry = GFAPI::get_entry( intval( $booking->booking_entry_id ) );
		if ( $entry && ! is_wp_error( $entry ) ) {
			$v = floatval( rgar( $entry, '230' ) );
			if ( $v > 0 ) {
				return $v;
			}
		}
	}
	$tour_id         = intval( $booking->tour_id ?? 0 );
	$net             = floatval( $booking->net_tour_price ?? 0 );
	$package_people  = intval( $booking->package_people ?? 1 );
	$deposit_discount = floatval( $booking->deposit_payment_discount ?? 0 );
	if ( $tour_id && $net > 0 && function_exists( 'bst_calculate_deposit' ) ) {
		$raw = bst_calculate_deposit( $tour_id, $net, $package_people );
		return max( 0, $raw - $deposit_discount );
	}
	return 0;
}

/**
 * Expected balance wire amount (tour currency): GF field 279, else balance_due helper.
 *
 * @param object $booking Row object.
 * @return float
 */
function bst_get_expected_balance_wire_amount_for_booking( $booking ) {
	if ( ! empty( $booking->finalization_entry_id ) && class_exists( 'GFAPI' ) ) {
		$entry = GFAPI::get_entry( intval( $booking->finalization_entry_id ) );
		if ( $entry && ! is_wp_error( $entry ) ) {
			$v = floatval( rgar( $entry, '279' ) );
			if ( $v > 0 ) {
				return $v;
			}
		}
	}
	$net               = floatval( $booking->net_tour_price ?? 0 );
	$additional        = floatval( $booking->additional_charge ?? 0 );
	$total_paid        = floatval( $booking->total_paid ?? 0 );
	$payment_discount  = floatval( $booking->payment_discount_amount ?? 0 );
	if ( function_exists( 'bst_calculate_balance_due' ) ) {
		return max( 0, bst_calculate_balance_due( $net, $total_paid, $payment_discount, $additional ) );
	}
	return max( 0, floatval( $booking->balance_due ?? 0 ) );
}

/**
 * Ensure DB columns exist (idempotent).
 */
function bst_ensure_payment_status_columns() {
	global $wpdb;
	$table = $wpdb->prefix . 'bst_tour_booking';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix.
	$exists = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'deposit_payment_status'" );
	if ( ! empty( $exists ) ) {
		return;
	}
	$wpdb->query(
		"ALTER TABLE `{$table}`
			ADD COLUMN `deposit_payment_status` VARCHAR(20) NULL DEFAULT NULL AFTER `deposit_payment_discount`,
			ADD COLUMN `balance_payment_status` VARCHAR(20) NULL DEFAULT NULL AFTER `balance_payment_discount`,
			ADD COLUMN `additional_payment_status` VARCHAR(20) NULL DEFAULT NULL AFTER `additional_payment_discount`,
			ADD COLUMN `refund_payment_status` VARCHAR(20) NULL DEFAULT NULL AFTER `refund_payment_date`"
	);
	if ( $wpdb->last_error ) {
		error_log( 'BST payment status columns: ' . $wpdb->last_error );
	}
}

/**
 * One-time backfill for existing bookings.
 */
function bst_migrate_payment_statuses_v1() {
	if ( get_option( 'bst_payment_status_migrated_v1' ) ) {
		return;
	}
	bst_ensure_payment_status_columns();
	global $wpdb;
	$table = $wpdb->prefix . 'bst_tour_booking';
	$rows  = $wpdb->get_results( "SELECT * FROM `{$table}`" );
	if ( ! $rows ) {
		update_option( 'bst_payment_status_migrated_v1', 1 );
		return;
	}
	foreach ( $rows as $booking ) {
		$update = array();

		// Deposit
		if ( ! empty( $booking->deposit_payment_method ) ) {
			if ( 'Bank Wire' === $booking->deposit_payment_method ) {
				$orig_dep_amt = floatval( $booking->deposit_payment_amount );
				$amt            = $orig_dep_amt;
				if ( $amt <= 0 && ! empty( $booking->deposit_payment_date ) ) {
					$amt = bst_get_expected_deposit_wire_amount_for_booking( $booking );
					if ( $amt > 0 ) {
						$update['deposit_payment_amount'] = $amt;
					}
				}
				// Paid if amount was already stored (e.g. conversion/import) even when payment date missing.
				if ( $orig_dep_amt > 0 ) {
					$update['deposit_payment_status'] = BST_PAYMENT_STATUS_PAID;
				} elseif ( ! empty( $booking->deposit_payment_date ) && floatval( $booking->deposit_payment_amount ) > 0 ) {
					$update['deposit_payment_status'] = BST_PAYMENT_STATUS_PAID;
				} else {
					$update['deposit_payment_status'] = BST_PAYMENT_STATUS_PENDING;
				}
			} else {
				$update['deposit_payment_status'] = floatval( $booking->deposit_payment_amount ) > 0 ? BST_PAYMENT_STATUS_PAID : BST_PAYMENT_STATUS_PENDING;
			}
		}

		// Balance
		if ( ! empty( $booking->balance_payment_method ) ) {
			if ( 'Bank Wire' === $booking->balance_payment_method ) {
				$amt = floatval( $booking->balance_payment_amount );
				if ( $amt <= 0 ) {
					$amt = bst_get_expected_balance_wire_amount_for_booking( $booking );
					if ( $amt > 0 ) {
						$update['balance_payment_amount'] = $amt;
					}
				}
				$orig_bal_amt = floatval( $booking->balance_payment_amount );
				if ( $orig_bal_amt > 0 ) {
					$update['balance_payment_status'] = BST_PAYMENT_STATUS_PAID;
				} elseif ( ! empty( $booking->balance_payment_date ) && floatval( $booking->balance_payment_amount ) > 0 ) {
					$update['balance_payment_status'] = BST_PAYMENT_STATUS_PAID;
				} else {
					$update['balance_payment_status'] = BST_PAYMENT_STATUS_PENDING;
				}
			} else {
				$update['balance_payment_status'] = floatval( $booking->balance_payment_amount ) > 0 ? BST_PAYMENT_STATUS_PAID : BST_PAYMENT_STATUS_PENDING;
			}
		}

		// Additional
		if ( ! empty( $booking->additional_payment_method ) ) {
			$update['additional_payment_status'] = floatval( $booking->additional_payment_amount ) > 0 ? BST_PAYMENT_STATUS_PAID : BST_PAYMENT_STATUS_PENDING;
		}

		// Refund — success → Paid
		if ( ! empty( $booking->refund_payment_method ) && floatval( $booking->refund_payment_amount ) > 0 ) {
			$update['refund_payment_status'] = BST_PAYMENT_STATUS_PAID;
		}

		// Option B: total_paid / balance_due only where we filled missing wire amounts
		if ( isset( $update['deposit_payment_amount'] ) || isset( $update['balance_payment_amount'] ) ) {
			$dep = isset( $update['deposit_payment_amount'] ) ? floatval( $update['deposit_payment_amount'] ) : floatval( $booking->deposit_payment_amount );
			$bal = isset( $update['balance_payment_amount'] ) ? floatval( $update['balance_payment_amount'] ) : floatval( $booking->balance_payment_amount );
			$add = floatval( $booking->additional_payment_amount );
			$ref = floatval( $booking->refund_payment_amount );
			$net = floatval( $booking->net_tour_price );
			$additional_ch = floatval( $booking->additional_charge );
			$disc            = floatval( $booking->deposit_payment_discount ?? 0 )
				+ floatval( $booking->balance_payment_discount ?? 0 )
				+ floatval( $booking->additional_payment_discount ?? 0 );
			$total_paid      = $dep + $bal + $add - $ref;
			$update['total_paid']  = $total_paid;
			$update['balance_due'] = bst_calculate_balance_due( $net, $total_paid, $disc, $additional_ch );
		}

		if ( ! empty( $update ) ) {
			$wpdb->update( $table, $update, array( 'id' => $booking->id ) );
		}
	}
	update_option( 'bst_payment_status_migrated_v1', 1 );
}

/**
 * One-time fix: offline / converted imports often stored bank-wire amounts without dates; mark those lines Paid when amount > 0.
 * Scoped to Offline booking_method (paper / import), not web GF flows.
 */
function bst_migrate_offline_wire_status_v1() {
	if ( get_option( 'bst_migrate_offline_wire_status_v1_done' ) ) {
		return;
	}
	global $wpdb;
	$table = $wpdb->prefix . 'bst_tour_booking';

	$scope = "( `booking_method` = 'Offline' OR `data_source` LIKE '%Paper%' OR `data_source` LIKE '%Bill%' OR `data_source` LIKE '%Import%' )";
	$wpdb->query(
		"UPDATE `{$table}` SET `deposit_payment_status` = '" . esc_sql( BST_PAYMENT_STATUS_PAID ) . "'
		WHERE {$scope}
		AND `deposit_payment_method` = 'Bank Wire'
		AND `deposit_payment_amount` > 0
		AND `deposit_payment_status` IN ('" . esc_sql( BST_PAYMENT_STATUS_PENDING ) . "','" . esc_sql( BST_PAYMENT_STATUS_PROCESSING ) . "')"
	);
	$wpdb->query(
		"UPDATE `{$table}` SET `balance_payment_status` = '" . esc_sql( BST_PAYMENT_STATUS_PAID ) . "'
		WHERE {$scope}
		AND `balance_payment_method` = 'Bank Wire'
		AND `balance_payment_amount` > 0
		AND `balance_payment_status` IN ('" . esc_sql( BST_PAYMENT_STATUS_PENDING ) . "','" . esc_sql( BST_PAYMENT_STATUS_PROCESSING ) . "')"
	);
	$wpdb->query(
		"UPDATE `{$table}` SET `additional_payment_status` = '" . esc_sql( BST_PAYMENT_STATUS_PAID ) . "'
		WHERE {$scope}
		AND `additional_payment_method` = 'Bank Wire'
		AND `additional_payment_amount` > 0
		AND `additional_payment_status` IN ('" . esc_sql( BST_PAYMENT_STATUS_PENDING ) . "','" . esc_sql( BST_PAYMENT_STATUS_PROCESSING ) . "')"
	);

	update_option( 'bst_migrate_offline_wire_status_v1_done', 1 );
}

/**
 * Payment line status for bulk imports (paper/offline): amount present means received; date optional.
 *
 * @param string|null $method Payment method.
 * @param float       $amount Amount in tour currency.
 * @return string|null Paid or Pending.
 */
function bst_import_line_payment_status( $method, $amount ) {
	if ( empty( $method ) ) {
		return null;
	}
	return floatval( $amount ) > 0 ? BST_PAYMENT_STATUS_PAID : BST_PAYMENT_STATUS_PENDING;
}

add_action(
	'init',
	function () {
		bst_ensure_payment_status_columns();
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			bst_migrate_payment_statuses_v1();
			bst_migrate_offline_wire_status_v1();
		}
	},
	20
);
