<?php
/**
 * PHPUnit bootstrap — minimal WordPress stubs so pure helpers can load outside WP.
 *
 * @package BST_Plugin
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $str
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? trim( $str ) : '';
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook
	 * @param callable $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// No-op: migrations run on init in WordPress only.
	}
}

require dirname( __DIR__ ) . '/includes/booking-payment-status.php';
