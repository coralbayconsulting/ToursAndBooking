<?php
/**
 * Tools: remap booking vehicle1_id / vehicle2_id from legacy text only (no CPT creates).
 *
 * Run after consolidating Vehicle CPT duplicates so exact normalized/compact matching resolves to one canonical post.
 *
 * @package BST_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * For each booking, set vehicle*_id by matching legacy vehicle1/vehicle2 text to existing Vehicle CPT
 * titles (normalized / compact key only), same helpers as migration find — never creates posts.
 *
 * @return string[] Log lines.
 */
function bst_remap_booking_vehicle_ids_from_legacy_text() {
	global $wpdb;

	$results        = array();
	$norm_to_id     = array();
	$vehicles_by_id = array();

	$vehicles = get_posts(
		array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	foreach ( $vehicles as $p ) {
		$vehicles_by_id[ (int) $p->ID ] = $p->post_title;
		$norm_to_id[ bst_vehicle_normalize_key( $p->post_title ) ] = (int) $p->ID;
	}

	$table    = $wpdb->prefix . 'bst_tour_booking';
	$bookings = $wpdb->get_results( "SELECT id, vehicle1, vehicle2, vehicle1_id, vehicle2_id FROM {$table}", ARRAY_A );
	$rows     = 0;
	$slots    = 0;

	foreach ( $bookings as $b ) {
		$bid = (int) $b['id'];
		$u   = array();

		foreach ( array( 1, 2 ) as $slot ) {
			$col  = 1 === $slot ? 'vehicle1' : 'vehicle2';
			$icol = 1 === $slot ? 'vehicle1_id' : 'vehicle2_id';
			$text = isset( $b[ $col ] ) ? trim( (string) $b[ $col ] ) : '';
			if ( '' === $text ) {
				continue;
			}
			$base = bst_vehicle_base_name_from_text( $text );
			$vid  = bst_vehicle_migration_find_existing_id( $base, $norm_to_id, $vehicles_by_id );
			if ( $vid <= 0 && $base !== trim( $text ) ) {
				$vid = bst_vehicle_migration_find_existing_id( trim( $text ), $norm_to_id, $vehicles_by_id );
			}
			if ( $vid <= 0 ) {
				continue;
			}
			$old = isset( $b[ $icol ] ) ? (int) $b[ $icol ] : 0;
			if ( $old !== $vid ) {
				$u[ $icol ] = $vid;
				$slots++;
			}
		}

		if ( ! empty( $u ) ) {
			$formats = array_fill( 0, count( $u ), '%d' );
			$wpdb->update( $table, $u, array( 'id' => $bid ), $formats, array( '%d' ) );
			$rows++;
		}
	}

	$results[] = sprintf(
		'Remap from legacy text: %d booking row(s) updated, %d vehicle slot(s) changed. Vehicle CPT count used for lookup: %d.',
		$rows,
		$slots,
		count( $vehicles_by_id )
	);

	return $results;
}

/**
 * admin-post: run remap and redirect back to Tools with notice.
 */
function bst_handle_admin_post_remap_booking_vehicle_ids() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.' ) );
	}
	check_admin_referer( 'bst_remap_booking_vehicle_ids' );

	if ( ! function_exists( 'bst_remap_booking_vehicle_ids_from_legacy_text' ) ) {
		wp_die( esc_html__( 'Remap function not available.' ) );
	}

	$lines = bst_remap_booking_vehicle_ids_from_legacy_text();
	$msg   = implode( ' ', $lines );
	set_transient( 'bst_tools_notice_' . get_current_user_id(), $msg, 120 );

	wp_safe_redirect( admin_url( 'admin.php?page=bst_tools_page' ) );
	exit;
}

add_action( 'admin_post_bst_remap_booking_vehicle_ids', 'bst_handle_admin_post_remap_booking_vehicle_ids' );
