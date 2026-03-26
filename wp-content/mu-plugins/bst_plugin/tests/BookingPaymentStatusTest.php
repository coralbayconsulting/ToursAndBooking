<?php
/**
 * Unit tests for booking-payment-status.php (pure helpers).
 *
 * @package BST_Plugin
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers bst_payment_line_received_for_display
 * @covers bst_payment_status_commission_eligible
 * @covers bst_commission_line_needs_invoice
 * @covers bst_commission_uninvoiced_inflow_amounts
 * @covers bst_commission_booking_net_basis_original_currency
 * @covers bst_commission_refund_reduces_basis
 */
class BookingPaymentStatusTest extends TestCase {

	public function test_payment_line_received_paid_with_amount() {
		$this->assertTrue( bst_payment_line_received_for_display( BST_PAYMENT_STATUS_PAID, 100 ) );
	}

	public function test_payment_line_received_paid_zero_amount() {
		$this->assertFalse( bst_payment_line_received_for_display( BST_PAYMENT_STATUS_PAID, 0 ) );
	}

	public function test_payment_line_received_processing_with_amount() {
		$this->assertTrue( bst_payment_line_received_for_display( BST_PAYMENT_STATUS_PROCESSING, 50 ) );
	}

	public function test_payment_line_received_pending() {
		$this->assertFalse( bst_payment_line_received_for_display( BST_PAYMENT_STATUS_PENDING, 100 ) );
	}

	public function test_payment_line_received_failed() {
		$this->assertFalse( bst_payment_line_received_for_display( BST_PAYMENT_STATUS_FAILED, 100 ) );
	}

	public function test_payment_line_received_transferred() {
		$this->assertFalse( bst_payment_line_received_for_display( BST_PAYMENT_STATUS_TRANSFERRED, 100 ) );
	}

	public function test_payment_line_received_legacy_empty_status_positive_amount() {
		$this->assertTrue( bst_payment_line_received_for_display( '', 25 ) );
		$this->assertTrue( bst_payment_line_received_for_display( null, 25 ) );
	}

	public function test_commission_eligible_matches_display_for_inflow_lines() {
		$this->assertTrue( bst_payment_status_commission_eligible( BST_PAYMENT_STATUS_PAID, 10 ) );
		$this->assertFalse( bst_payment_status_commission_eligible( BST_PAYMENT_STATUS_TRANSFERRED, 10 ) );
		$this->assertFalse( bst_payment_status_commission_eligible( BST_PAYMENT_STATUS_PENDING, 10 ) );
	}

	public function test_commission_line_needs_invoice() {
		$this->assertTrue( bst_commission_line_needs_invoice( '', BST_PAYMENT_STATUS_PAID, 100 ) );
		$this->assertFalse( bst_commission_line_needs_invoice( 'INV-1', BST_PAYMENT_STATUS_PAID, 100 ) );
		$this->assertFalse( bst_commission_line_needs_invoice( '', BST_PAYMENT_STATUS_PENDING, 100 ) );
	}

	public function test_uninvoiced_inflow_amounts_full_cancel_nets_to_zero() {
		$b = (object) array(
			'deposit_commission_invoice'           => '',
			'balance_commission_invoice'           => '',
			'additional_payment_commission_invoice' => '',
			'deposit_payment_status'               => BST_PAYMENT_STATUS_PAID,
			'balance_payment_status'               => BST_PAYMENT_STATUS_PAID,
			'additional_payment_status'            => BST_PAYMENT_STATUS_PAID,
			'deposit_payment_amount'               => 500,
			'balance_payment_amount'               => 1500,
			'additional_payment_amount'            => 0,
			'refund_commission_invoice'            => '',
			'refund_payment_status'                => BST_PAYMENT_STATUS_PAID,
			'refund_payment_amount'                => 2000,
		);
		$nets = bst_commission_uninvoiced_inflow_amounts( $b );
		$this->assertSame( 0.0, $nets['deposit'] );
		$this->assertSame( 0.0, $nets['balance'] );
		$this->assertSame( 0.0, $nets['additional'] );
		$this->assertSame( 0.0, bst_commission_booking_net_basis_original_currency( $b ) );
	}

	public function test_net_basis_partial_refund_after_invoiced_inflow_subtracts_refund() {
		$b = (object) array(
			'deposit_commission_invoice'           => 'CBC-1',
			'balance_commission_invoice'           => '',
			'additional_payment_commission_invoice' => '',
			'deposit_payment_status'               => BST_PAYMENT_STATUS_PAID,
			'balance_payment_status'               => BST_PAYMENT_STATUS_PAID,
			'additional_payment_status'            => '',
			'deposit_payment_amount'               => 500,
			'balance_payment_amount'               => 500,
			'additional_payment_amount'            => 0,
			'refund_commission_invoice'            => '',
			'refund_payment_status'                => BST_PAYMENT_STATUS_PAID,
			'refund_payment_amount'                => 200,
		);
		// Uninvoiced inflows: balance 500 only; refund_reduces_basis true → basis 500 - 200.
		$this->assertSame( 300.0, bst_commission_booking_net_basis_original_currency( $b ) );
	}
}
