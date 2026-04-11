<?php
/**
 * Tour vehicle_pricing → Vehicle CPT migration and release “re-link from labels”.
 *
 * **Requirements (unchanged):** {@see BST_Plugin::acf_tour_vehicle_pricing_cpt_query} must not restrict `post__in` while
 * migration runs ({@see bst_vehicle_migration_push_post_object_query_bypass()}), and
 * {@see bst_vehicle_migration_acf_pass_vehicle_pricing_vehicle_id} must allow validate_value for `field_67f9e40b1c001`.
 *
 * **Re-link / save strategy:** Write each `vehicle_id` with {@see update_sub_field()} using **0-based** row indices
 * (`$package_index`, `$vehicle_index`). Do **not** bulk-save the whole repeater — that misaligns nested values.
 * Fallback: `update_post_meta` on the canonical field-key meta name. Logs: `[BST release cleanup]`.
 *
 * @package BST_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BST_VEHICLE_PRICING_REPEATER_KEY = 'field_67ad570616fd2';
const BST_VEHICLE_NESTED_REPEATER_KEY  = 'field_67ad574316fd3';
const BST_VEHICLE_ROW_TEXT_KEY         = 'field_67ad5facdf416';
const BST_VEHICLE_ROW_POST_OBJECT_KEY  = 'field_67f9e40b1c001';

// -------------------------------------------------------------------------
// Cache / bypass / log
// -------------------------------------------------------------------------

function bst_vehicle_migration_bust_caches_for_tour( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 ) {
		return;
	}
	clean_post_cache( $tour_id );
	wp_cache_delete( $tour_id, 'post_meta' );
	if ( function_exists( 'acf_reset_local' ) ) {
		acf_reset_local();
	}
	if ( function_exists( 'acf_flush_value_cache' ) ) {
		acf_flush_value_cache( $tour_id );
	}
}

function bst_vehicle_migration_push_post_object_query_bypass() {
	$g = &$GLOBALS['bst_vehicle_migration_po_query_bypass'];
	$g = (int) $g + 1;
}

function bst_vehicle_migration_pop_post_object_query_bypass() {
	if ( empty( $GLOBALS['bst_vehicle_migration_po_query_bypass'] ) ) {
		return;
	}
	--$GLOBALS['bst_vehicle_migration_po_query_bypass'];
	if ( $GLOBALS['bst_vehicle_migration_po_query_bypass'] < 0 ) {
		$GLOBALS['bst_vehicle_migration_po_query_bypass'] = 0;
	}
}

function bst_vehicle_migration_is_post_object_query_bypassed() {
	return ! empty( $GLOBALS['bst_vehicle_migration_po_query_bypass'] );
}

function bst_vehicle_migration_release_cleanup_log( $message ) {
	$message = trim( (string) $message );
	if ( '' === $message ) {
		return;
	}
	error_log( '[BST release cleanup] ' . $message );
}

// -------------------------------------------------------------------------
// Nested repeater helpers (used by vehicle-helpers.php, custom-post-types.php)
// -------------------------------------------------------------------------

function bst_vehicle_migration_nested_repeater_primary_key( array $pricing_row ) {
	$by_name = ( ! empty( $pricing_row['vehicles'] ) && is_array( $pricing_row['vehicles'] ) ) ? $pricing_row['vehicles'] : null;
	$by_key  = ( ! empty( $pricing_row[ BST_VEHICLE_NESTED_REPEATER_KEY ] ) && is_array( $pricing_row[ BST_VEHICLE_NESTED_REPEATER_KEY ] ) )
		? $pricing_row[ BST_VEHICLE_NESTED_REPEATER_KEY ] : null;
	if ( $by_key && $by_name && $by_key !== $by_name ) {
		return BST_VEHICLE_NESTED_REPEATER_KEY;
	}
	if ( $by_key ) {
		return BST_VEHICLE_NESTED_REPEATER_KEY;
	}
	if ( $by_name ) {
		return 'vehicles';
	}
	return null;
}

function bst_vehicle_migration_get_nested_vehicle_rows( array $pricing_row ) {
	$k = bst_vehicle_migration_nested_repeater_primary_key( $pricing_row );
	if ( ! $k ) {
		return array();
	}
	$nested = $pricing_row[ $k ];
	return is_array( $nested ) ? $nested : array();
}

function bst_vehicle_migration_row_vehicle_text( array $vrow ) {
	if ( isset( $vrow['vehicle'] ) && $vrow['vehicle'] !== null && $vrow['vehicle'] !== '' ) {
		return trim( (string) $vrow['vehicle'] );
	}
	if ( isset( $vrow[ BST_VEHICLE_ROW_TEXT_KEY ] ) && $vrow[ BST_VEHICLE_ROW_TEXT_KEY ] !== null && $vrow[ BST_VEHICLE_ROW_TEXT_KEY ] !== '' ) {
		return trim( (string) $vrow[ BST_VEHICLE_ROW_TEXT_KEY ] );
	}
	return '';
}

function bst_vehicle_migration_row_linked_post_id( array $vrow ) {
	$linked = isset( $vrow['vehicle_id'] ) ? $vrow['vehicle_id'] : ( isset( $vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] ) ? $vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] : 0 );
	if ( is_array( $linked ) && isset( $linked['ID'] ) ) {
		return (int) $linked['ID'];
	}
	if ( is_object( $linked ) && isset( $linked->ID ) ) {
		return (int) $linked->ID;
	}
	return (int) $linked;
}

function bst_vehicle_migration_assign_vehicle_id_on_nested_row( array &$vrow, $new_id ) {
	$new_id = (int) $new_id;
	if ( $new_id <= 0 ) {
		$vrow['vehicle_id']                        = false;
		$vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] = false;
		return;
	}
	$vrow['vehicle_id']                        = $new_id;
	$vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] = $new_id;
}

function bst_vehicle_migration_assign_nested_vehicle_id_mirrored( array &$pricing_row, $j, $primary_key, $new_id ) {
	if ( ! isset( $pricing_row[ $primary_key ][ $j ] ) || ! is_array( $pricing_row[ $primary_key ][ $j ] ) ) {
		return;
	}
	bst_vehicle_migration_assign_vehicle_id_on_nested_row( $pricing_row[ $primary_key ][ $j ], $new_id );
	$other = ( BST_VEHICLE_NESTED_REPEATER_KEY === $primary_key ) ? 'vehicles' : BST_VEHICLE_NESTED_REPEATER_KEY;
	if ( ! empty( $pricing_row[ $other ] ) && is_array( $pricing_row[ $other ] ) && isset( $pricing_row[ $other ][ $j ] ) && is_array( $pricing_row[ $other ][ $j ] ) ) {
		bst_vehicle_migration_assign_vehicle_id_on_nested_row( $pricing_row[ $other ][ $j ], $new_id );
	}
}

// -------------------------------------------------------------------------
// Meta key: outer row uses row_index_offset; inner nested row index is 0-based in storage.
// -------------------------------------------------------------------------

function bst_vehicle_migration_acf_repeater_selector_index( $php_zero_based ) {
	$php_zero_based = (int) $php_zero_based;
	if ( function_exists( 'acf_get_setting' ) ) {
		$off = acf_get_setting( 'row_index_offset' );
		if ( is_numeric( $off ) && 0 === (int) $off ) {
			return $php_zero_based;
		}
	}
	return $php_zero_based + 1;
}

function bst_vehicle_migration_vehicle_id_value_meta_key( $package_index_0, $vehicle_index_0 ) {
	$pi = bst_vehicle_migration_acf_repeater_selector_index( (int) $package_index_0 );
	$pj = (int) $vehicle_index_0;
	return BST_VEHICLE_PRICING_REPEATER_KEY . '_' . $pi . '_' . BST_VEHICLE_NESTED_REPEATER_KEY . '_' . $pj . '_' . BST_VEHICLE_ROW_POST_OBJECT_KEY;
}

function bst_vehicle_migration_acf_save_returned_ok( $r ) {
	return function_exists( 'bst_acf_save_returned_ok' ) ? bst_acf_save_returned_ok( $r ) : ( false !== $r );
}

/**
 * Persist one or more vehicle_id cells: update_sub_field (preferred), then postmeta.
 *
 * @param int   $tour_id Tour ID.
 * @param array $cells   list of [ 'meta_row' => int, 'meta_subrow' => int, 'vehicle_id' => int ].
 * @param array $results Ref for error strings.
 * @return bool
 */
function bst_vehicle_migration_persist_vehicle_id_cells( $tour_id, array $cells, array &$results ) {
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 || empty( $cells ) ) {
		return false;
	}

	$post_ids = array_unique(
		array(
			$tour_id,
			'post_' . $tour_id,
		)
	);

	foreach ( $cells as $c ) {
		$vid = isset( $c['vehicle_id'] ) ? (int) $c['vehicle_id'] : 0;
		$pi  = isset( $c['meta_row'] ) ? (int) $c['meta_row'] : -1;
		$pj  = isset( $c['meta_subrow'] ) ? (int) $c['meta_subrow'] : -1;
		if ( $vid <= 0 || $pi < 0 || $pj < 0 ) {
			continue;
		}

		$selectors = array(
			array( BST_VEHICLE_PRICING_REPEATER_KEY, $pi, BST_VEHICLE_NESTED_REPEATER_KEY, $pj, BST_VEHICLE_ROW_POST_OBJECT_KEY ),
			array( 'vehicle_pricing', $pi, 'vehicles', $pj, 'vehicle_id' ),
		);
		$pi_off = bst_vehicle_migration_acf_repeater_selector_index( $pi );
		$pj_off = bst_vehicle_migration_acf_repeater_selector_index( $pj );
		if ( $pi_off !== $pi || $pj_off !== $pj ) {
			$selectors = array_merge(
				$selectors,
				array(
					array( BST_VEHICLE_PRICING_REPEATER_KEY, $pi_off, BST_VEHICLE_NESTED_REPEATER_KEY, $pj_off, BST_VEHICLE_ROW_POST_OBJECT_KEY ),
					array( 'vehicle_pricing', $pi_off, 'vehicles', $pj_off, 'vehicle_id' ),
				)
			);
		}

		$written = false;
		if ( function_exists( 'update_sub_field' ) ) {
			foreach ( $post_ids as $pid ) {
				foreach ( $selectors as $sel ) {
					$r = update_sub_field( $sel, $vid, $pid );
					if ( bst_vehicle_migration_acf_save_returned_ok( $r ) ) {
						bst_vehicle_migration_release_cleanup_log(
							sprintf( 'persist OK tour=%d pi=%d pj=%d vid=%d pid=%s sel=%s', $tour_id, $pi, $pj, $vid, is_string( $pid ) ? $pid : (string) $pid, wp_json_encode( $sel ) )
						);
						$written = true;
						break 2;
					}
				}
			}
		}

		if ( $written ) {
			continue;
		}

		$value_key = bst_vehicle_migration_vehicle_id_value_meta_key( $pi, $pj );
		$ref_key   = '_' . $value_key;
		update_post_meta( $tour_id, $value_key, $vid );
		update_post_meta( $tour_id, $ref_key, BST_VEHICLE_ROW_POST_OBJECT_KEY );

		$pi_acf   = bst_vehicle_migration_acf_repeater_selector_index( $pi );
		$pj_meta  = $pj;
		$name_val = 'vehicle_pricing_' . $pi_acf . '_vehicles_' . $pj_meta . '_vehicle_id';
		$name_ref = '_' . $name_val;
		update_post_meta( $tour_id, $name_val, $vid );
		update_post_meta( $tour_id, $name_ref, BST_VEHICLE_ROW_POST_OBJECT_KEY );

		$read = (int) get_post_meta( $tour_id, $value_key, true );
		if ( $read !== $vid ) {
			$results[] = sprintf( 'Error: could not persist vehicle_id tour %d row %d/%d (vehicle %d).', $tour_id, $pi, $pj, $vid );
			bst_vehicle_migration_release_cleanup_log( sprintf( 'persist FAIL tour=%d key=%s expected=%d got=%d', $tour_id, $value_key, $vid, $read ) );
			return false;
		}
		bst_vehicle_migration_release_cleanup_log( sprintf( 'persist postmeta OK tour=%d pi=%d pj=%d vid=%d', $tour_id, $pi, $pj, $vid ) );
	}

	bst_vehicle_migration_bust_caches_for_tour( $tour_id );
	return true;
}

function bst_vehicle_migration_verify_cells_meta( $tour_id, array $cells ) {
	$tour_id = (int) $tour_id;
	$issues    = array();
	bst_vehicle_migration_bust_caches_for_tour( $tour_id );
	foreach ( $cells as $c ) {
		$expected = isset( $c['vehicle_id'] ) ? (int) $c['vehicle_id'] : 0;
		$pi       = isset( $c['meta_row'] ) ? (int) $c['meta_row'] : -1;
		$pj       = isset( $c['meta_subrow'] ) ? (int) $c['meta_subrow'] : -1;
		if ( $expected <= 0 || $pi < 0 || $pj < 0 ) {
			continue;
		}
		$k = bst_vehicle_migration_vehicle_id_value_meta_key( $pi, $pj );
		if ( (int) get_post_meta( $tour_id, $k, true ) === $expected ) {
			continue;
		}
		$issues[] = sprintf( 'tour %d pi=%d pj=%d expected %d; meta %s=%s', $tour_id, $pi, $pj, $expected, $k, get_post_meta( $tour_id, $k, true ) );
	}
	return $issues;
}

function bst_vehicle_migration_find_existing_id( $base_name, array &$norm_to_id, array $vehicles_by_id ) {
	$base_name = trim( (string) $base_name );
	if ( '' === $base_name ) {
		return 0;
	}
	$key     = bst_vehicle_normalize_key( $base_name );
	$compact = bst_vehicle_compact_key( $base_name );
	if ( isset( $norm_to_id[ $key ] ) ) {
		return (int) $norm_to_id[ $key ];
	}
	foreach ( $vehicles_by_id as $vid => $title ) {
		if ( $compact && $compact === bst_vehicle_compact_key( $title ) ) {
			$norm_to_id[ $key ] = (int) $vid;
			return (int) $vid;
		}
	}
	return 0;
}

function bst_vehicle_migration_create_vehicle( $canonical_title, $tour_id, array &$norm_to_id, array &$vehicles_by_id ) {
	$canonical_title = trim( sanitize_text_field( (string) $canonical_title ) );
	if ( '' === $canonical_title ) {
		return 0;
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'vehicle',
			'post_status' => 'publish',
			'post_title'  => $canonical_title,
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	$id = (int) $id;
	if ( function_exists( 'update_field' ) ) {
		update_field( 'vehicle_type', bst_vehicle_type_for_tour_id( $tour_id ), $id );
		$active = get_field( 'vehicle_active', $id );
		if ( $active === null || $active === '' ) {
			update_field( 'vehicle_active', 1, $id );
		}
		if ( function_exists( 'bst_vehicle_set_default_transmission_if_empty' ) ) {
			bst_vehicle_set_default_transmission_if_empty( $id, $canonical_title, $tour_id );
		}
	}
	$vehicles_by_id[ $id ] = $canonical_title;
	$norm_to_id[ bst_vehicle_normalize_key( $canonical_title ) ] = $id;
	return $id;
}

function bst_vehicle_migration_delete_all_vehicles() {
	$ids = get_posts(
		array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$n = 0;
	foreach ( $ids as $id ) {
		if ( wp_delete_post( (int) $id, true ) ) {
			$n++;
		}
	}
	return $n;
}

function bst_vehicle_migration_resolve_row_id( array $vehicle_item, $tour_id, array &$norm_to_id, array &$vehicles_by_id, $ignore_linked = false ) {
	$raw  = bst_vehicle_migration_row_vehicle_text( $vehicle_item );
	$base = function_exists( 'bst_vehicle_base_name_from_text' ) ? bst_vehicle_base_name_from_text( $raw ) : trim( $raw );
	$base = trim( $base );
	if ( ! $ignore_linked ) {
		$linked = bst_vehicle_migration_row_linked_post_id( $vehicle_item );
		if ( $linked > 0 ) {
			$p = get_post( $linked );
			if ( $p && 'vehicle' === $p->post_type ) {
				$norm_to_id[ bst_vehicle_normalize_key( $p->post_title ) ] = $linked;
				return $linked;
			}
		}
	}
	if ( '' === $base ) {
		return 0;
	}
	$existing = bst_vehicle_migration_find_existing_id( $base, $norm_to_id, $vehicles_by_id );
	if ( $existing > 0 ) {
		return $existing;
	}
	return bst_vehicle_migration_create_vehicle( $base, $tour_id, $norm_to_id, $vehicles_by_id );
}

/**
 * @param int   $tour_id       Tour ID.
 * @param array $pricing       Unused; kept for call compatibility.
 * @param array $pending_cells Cells to write.
 * @param array $results       Log.
 * @return bool
 */
function bst_vehicle_migration_save_vehicle_pricing_for_tour( $tour_id, array $pricing, array $pending_cells, array &$results ) {
	unset( $pricing );
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 || empty( $pending_cells ) ) {
		return false;
	}
	if ( ! bst_vehicle_migration_persist_vehicle_id_cells( $tour_id, $pending_cells, $results ) ) {
		return false;
	}
	$mismatches = bst_vehicle_migration_verify_cells_meta( $tour_id, $pending_cells );
	if ( ! empty( $mismatches ) ) {
		foreach ( $mismatches as $m ) {
			bst_vehicle_migration_release_cleanup_log( 'VERIFY_FAIL ' . $m );
		}
		$results[] = sprintf( 'Error: vehicle_id verification failed for tour %d (%s).', $tour_id, get_the_title( $tour_id ) );
		return false;
	}
	bst_vehicle_migration_release_cleanup_log( sprintf( 'save_vehicle_pricing complete tour=%d cells=%d', $tour_id, count( $pending_cells ) ) );
	return true;
}

/**
 * @param bool $force_reset
 * @param bool $repair_repeater_links_from_text Re-link: set CPT from label text; always rewrite when resolved id &gt; 0.
 * @return string[]
 */
function bst_migrate_vehicle_cpt_links( $force_reset = false, $repair_repeater_links_from_text = false ) {
	global $wpdb;

	if ( function_exists( 'bst_ensure_tour_booking_vehicle_id_columns' ) ) {
		bst_ensure_tour_booking_vehicle_id_columns();
	}

	$results                         = array();
	$norm_to_id                      = array();
	$vehicles_by_id                  = array();
	$force_reset                     = (bool) $force_reset;
	$repair_repeater_links_from_text = (bool) $repair_repeater_links_from_text;

	if ( $repair_repeater_links_from_text && ! $force_reset ) {
		$results[] = 'Re-link mode: saving tour vehicle_pricing from label text (ignoring stored Vehicle CPT links). Vehicle posts are not deleted.';
	}
	if ( $force_reset && ! $repair_repeater_links_from_text ) {
		$results[] = 'Force reset only: recreated Vehicle CPTs from tour labels (inventory); tour vehicle_pricing was not saved. Check "Re-link tour repeater from labels" and run again to write CPT links on tours.';
	}
	if ( function_exists( 'acf_get_setting' ) ) {
		$results[] = sprintf( 'ACF row_index_offset=%s.', var_export( acf_get_setting( 'row_index_offset' ), true ) );
	}

	if ( $force_reset ) {
		$deleted = bst_vehicle_migration_delete_all_vehicles();
		$results[] = sprintf( 'Force reset: permanently deleted %d Vehicle CPT post(s).', $deleted );
	} else {
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
	}

	$tours_updated      = 0;
	$rows_touched       = 0;
	$tours_with_pricing = 0;
	$rows_seen          = 0;
	$tour_posts         = get_posts(
		array(
			'post_type'      => 'tour',
			'post_status'    => function_exists( 'bst_post_statuses_for_admin_scan' ) ? bst_post_statuses_for_admin_scan( 'tour' ) : 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	bst_vehicle_migration_push_post_object_query_bypass();
	try {

		foreach ( $tour_posts as $tid ) {
			$tid = (int) $tid;
			if ( ! function_exists( 'get_field' ) ) {
				$results[] = 'ACF not available; aborting tour updates.';
				return $results;
			}

			bst_vehicle_migration_bust_caches_for_tour( $tid );
			$pricing = get_field( 'vehicle_pricing', $tid, true );
			if ( empty( $pricing ) || ! is_array( $pricing ) ) {
				continue;
			}

			$tours_with_pricing++;

			if ( $force_reset && ! $repair_repeater_links_from_text ) {
				foreach ( $pricing as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$nested = bst_vehicle_migration_get_nested_vehicle_rows( $row );
					foreach ( $nested as $vrow ) {
						if ( is_array( $vrow ) ) {
							++$rows_seen;
							bst_vehicle_migration_resolve_row_id( $vrow, $tid, $norm_to_id, $vehicles_by_id, true );
						}
					}
				}
				continue;
			}

			$pending_cells = array();
			$pi            = 0;

			foreach ( $pricing as $row ) {
				if ( ! is_array( $row ) ) {
					++$pi;
					continue;
				}

				$nested = bst_vehicle_migration_get_nested_vehicle_rows( $row );
				$nested = array_values( $nested );
				if ( empty( $nested ) ) {
					++$pi;
					continue;
				}

				foreach ( $nested as $pj => $vrow ) {
					if ( ! is_array( $vrow ) ) {
						continue;
					}
					++$rows_seen;
					$ignore_linked = $repair_repeater_links_from_text;
					$new_id        = bst_vehicle_migration_resolve_row_id( $vrow, $tid, $norm_to_id, $vehicles_by_id, $ignore_linked );
					$old           = bst_vehicle_migration_row_linked_post_id( $vrow );

					$should_write = false;
					if ( $repair_repeater_links_from_text ) {
						$should_write = ( $new_id > 0 );
					} else {
						$should_write = ( $new_id > 0 && $old !== $new_id );
					}

					if ( $should_write ) {
						$pending_cells[] = array(
							'meta_row'    => $pi,
							'meta_subrow' => (int) $pj,
							'vehicle_id'  => $new_id,
						);
						++$rows_touched;
					}
				}
				++$pi;
			}

			if ( ! empty( $pending_cells ) ) {
				if ( function_exists( 'acf_get_field_groups' ) ) {
					acf_get_field_groups( array( 'post_id' => $tid ) );
				}
				$ok = bst_vehicle_migration_save_vehicle_pricing_for_tour( $tid, array(), $pending_cells, $results );
				if ( $ok ) {
					bst_vehicle_migration_bust_caches_for_tour( $tid );
					++$tours_updated;
				}
			}
		}

		$results[] = sprintf(
			'Tours with vehicle_pricing: %d; nested vehicle rows seen: %d; tours saved: %d; repeater links set/changed: %d',
			$tours_with_pricing,
			$rows_seen,
			$tours_updated,
			$rows_touched
		);
		if ( $tours_with_pricing > 0 && 0 === $rows_seen ) {
			$results[] = 'No nested vehicle rows found under vehicle_pricing (unexpected). Check ACF field names/sync.';
		}

		$table    = $wpdb->prefix . 'bst_tour_booking';
		$bookings = $wpdb->get_results( "SELECT id, tour_id, vehicle1, vehicle2, vehicle1_id, vehicle2_id FROM {$table}", ARRAY_A );
		$b_updated = 0;

		foreach ( $bookings as $b ) {
			$bid     = (int) $b['id'];
			$tour_id = (int) $b['tour_id'];
			$u       = array();

			$v1 = isset( $b['vehicle1'] ) ? (string) $b['vehicle1'] : '';
			$v2 = isset( $b['vehicle2'] ) ? (string) $b['vehicle2'] : '';

			$old1 = isset( $b['vehicle1_id'] ) ? (int) $b['vehicle1_id'] : 0;
			$old2 = isset( $b['vehicle2_id'] ) ? (int) $b['vehicle2_id'] : 0;

			$fill1 = $force_reset || ( $v1 !== '' && ( ! isset( $b['vehicle1_id'] ) || (int) $b['vehicle1_id'] === 0 ) );
			if ( $fill1 ) {
				if ( $v1 !== '' ) {
					$base = bst_vehicle_base_name_from_text( $v1 );
					$vid  = bst_vehicle_migration_find_existing_id( $base, $norm_to_id, $vehicles_by_id );
					if ( $vid <= 0 && $base !== '' ) {
						$vid = bst_vehicle_migration_create_vehicle( $base, $tour_id > 0 ? $tour_id : 0, $norm_to_id, $vehicles_by_id );
					}
					if ( $force_reset || $vid > 0 ) {
						$new1 = $vid > 0 ? $vid : 0;
						if ( $new1 !== $old1 ) {
							$u['vehicle1_id'] = $new1;
						}
					}
				} elseif ( $force_reset && $old1 !== 0 ) {
					$u['vehicle1_id'] = 0;
				}
			}

			$fill2 = $force_reset || ( $v2 !== '' && ( ! isset( $b['vehicle2_id'] ) || (int) $b['vehicle2_id'] === 0 ) );
			if ( $fill2 ) {
				if ( $v2 !== '' ) {
					$base = bst_vehicle_base_name_from_text( $v2 );
					$vid  = bst_vehicle_migration_find_existing_id( $base, $norm_to_id, $vehicles_by_id );
					if ( $vid <= 0 && $base !== '' ) {
						$vid = bst_vehicle_migration_create_vehicle( $base, $tour_id > 0 ? $tour_id : 0, $norm_to_id, $vehicles_by_id );
					}
					if ( $force_reset || $vid > 0 ) {
						$new2 = $vid > 0 ? $vid : 0;
						if ( $new2 !== $old2 ) {
							$u['vehicle2_id'] = $new2;
						}
					}
				} elseif ( $force_reset && $old2 !== 0 ) {
					$u['vehicle2_id'] = 0;
				}
			}

			if ( ! empty( $u ) ) {
				$formats = array_fill( 0, count( $u ), '%d' );
				$wpdb->update( $table, $u, array( 'id' => $bid ), $formats, array( '%d' ) );
				++$b_updated;
			}
		}

		$results[] = sprintf( 'Bookings updated with vehicle IDs: %d', $b_updated );
		$results[] = sprintf( 'Vehicle CPT count after migration: %d', count( $vehicles_by_id ) );

		return $results;

	} finally {
		bst_vehicle_migration_pop_post_object_query_bypass();
	}
}

function bst_vehicle_migration_acf_pass_vehicle_pricing_vehicle_id( $valid, $value, $field, $input ) {
	if ( function_exists( 'bst_vehicle_migration_is_post_object_query_bypassed' ) && bst_vehicle_migration_is_post_object_query_bypassed() ) {
		return true;
	}
	return $valid;
}
add_filter( 'acf/validate_value/key=field_67f9e40b1c001', 'bst_vehicle_migration_acf_pass_vehicle_pricing_vehicle_id', 5, 4 );
