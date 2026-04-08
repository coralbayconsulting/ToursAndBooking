<?php
/**
 * Tools: export distinct vehicle strings from tour vehicle_pricing repeaters.
 *
 * @package BST_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scan all tours; aggregate exact repeater text → tours, counts, linked CPT ids.
 *
 * @return array<string, array{tours: array<int,string>, rows: int, linked_ids: int[]}>
 */
function bst_aggregate_tour_vehicle_repeater_texts() {
	$out = array();

	if ( ! function_exists( 'get_field' ) ) {
		return $out;
	}

	$tour_ids = get_posts(
		array(
			'post_type'      => 'tour',
			'post_status'    => function_exists( 'bst_post_statuses_for_admin_scan' ) ? bst_post_statuses_for_admin_scan( 'tour' ) : 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	foreach ( $tour_ids as $tid ) {
		$tid   = (int) $tid;
		$title = get_the_title( $tid );
		$pricing = get_field( 'vehicle_pricing', $tid, true );
		if ( empty( $pricing ) || ! is_array( $pricing ) ) {
			continue;
		}

		foreach ( $pricing as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$nested = bst_vehicle_migration_get_nested_vehicle_rows( $row );
			foreach ( $nested as $vrow ) {
				if ( ! is_array( $vrow ) ) {
					continue;
				}
				$text = bst_vehicle_migration_row_vehicle_text( $vrow );
				if ( '' === $text ) {
					continue;
				}
				if ( ! isset( $out[ $text ] ) ) {
					$out[ $text ] = array(
						'tours'      => array(),
						'rows'       => 0,
						'linked_ids' => array(),
					);
				}
				$out[ $text ]['tours'][ $tid ] = $title;
				$out[ $text ]['rows']++;
				$lid = bst_vehicle_migration_row_linked_post_id( $vrow );
				if ( $lid > 0 ) {
					$out[ $text ]['linked_ids'][ $lid ] = true;
				}
			}
		}
	}

	uksort( $out, 'strnatcasecmp' );
	return $out;
}

/**
 * CPT post titles for vehicle IDs (same order as IDs); invalid IDs noted.
 *
 * @param int[] $ids Sorted vehicle post IDs.
 * @return string Pipe-separated titles.
 */
function bst_vehicle_cpt_titles_for_linked_ids( array $ids ) {
	$parts = array();
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			continue;
		}
		$post = get_post( $id );
		if ( $post && 'vehicle' === $post->post_type ) {
			$parts[] = $post->post_title;
		} else {
			$parts[] = sprintf( '#%d (not a vehicle)', $id );
		}
	}
	return implode( ' | ', $parts );
}

/**
 * Output CSV for browser download.
 */
function bst_stream_tour_vehicle_names_csv() {
	$data = bst_aggregate_tour_vehicle_repeater_texts();

	$filename = 'tour-vehicle-repeater-names-' . gmdate( 'Y-m-d-His' ) . '.csv';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$out = fopen( 'php://output', 'w' );
	if ( false === $out ) {
		wp_die( esc_html__( 'Could not open output stream.' ) );
	}

	fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

	$headers = array(
		'vehicle_text_exact',
		'base_name_stripped',
		'normalized_key',
		'compact_key',
		'repeater_row_count',
		'tour_count',
		'tour_ids',
		'tour_titles',
		'linked_vehicle_post_ids',
		'linked_vehicle_cpt_titles',
	);
	fputcsv( $out, $headers );

	foreach ( $data as $exact => $info ) {
		$base = function_exists( 'bst_vehicle_base_name_from_text' ) ? bst_vehicle_base_name_from_text( $exact ) : $exact;
		$norm = function_exists( 'bst_vehicle_normalize_key' ) ? bst_vehicle_normalize_key( $exact ) : strtolower( trim( $exact ) );
		$comp = function_exists( 'bst_vehicle_compact_key' ) ? bst_vehicle_compact_key( $exact ) : preg_replace( '/\s+/u', '', $norm );

		$tours = $info['tours'];
		asort( $tours, SORT_NATURAL | SORT_FLAG_CASE );
		$ids   = array_keys( $tours );
		sort( $ids, SORT_NUMERIC );

		$linked = array_keys( $info['linked_ids'] );
		sort( $linked, SORT_NUMERIC );
		$cpt_titles = bst_vehicle_cpt_titles_for_linked_ids( $linked );

		fputcsv(
			$out,
			array(
				$exact,
				$base,
				$norm,
				$comp,
				(int) $info['rows'],
				count( $tours ),
				implode( '|', array_map( 'strval', $ids ) ),
				implode( ' | ', array_values( $tours ) ),
				implode( '|', array_map( 'strval', $linked ) ),
				$cpt_titles,
			)
		);
	}

	fclose( $out );
}

/**
 * admin-post handler: CSV download.
 */
function bst_handle_admin_post_export_tour_vehicle_names() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.' ) );
	}
	check_admin_referer( 'bst_export_tour_vehicle_names' );
	bst_stream_tour_vehicle_names_csv();
	exit;
}

add_action( 'admin_post_bst_export_tour_vehicle_names', 'bst_handle_admin_post_export_tour_vehicle_names' );
