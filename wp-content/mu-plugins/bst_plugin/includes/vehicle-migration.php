<?php
/**
 * One-time / Tools: migrate tour vehicle rows and bookings to Vehicle CPT IDs.
 *
 * @package BST_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ACF field keys for Tour → vehicle_pricing → vehicles repeater (fallback if rows use keys instead of names). */
const BST_VEHICLE_PRICING_REPEATER_KEY       = 'field_67ad570616fd2';
const BST_VEHICLE_NESTED_REPEATER_KEY        = 'field_67ad574316fd3';
const BST_VEHICLE_ROW_TEXT_KEY               = 'field_67ad5facdf416';
const BST_VEHICLE_ROW_POST_OBJECT_KEY        = 'field_67f9e40b1c001';

/**
 * Convert 0-based PHP repeater keys from get_field() to the row index update_sub_field() expects.
 * ACF defaults to 1-based repeater positions; the row_index_offset setting can switch to 0-based.
 *
 * @param int $php_zero_based Index from foreach ( $arr as $i => $row ).
 * @return int
 */
function bst_vehicle_migration_acf_repeater_selector_index( $php_zero_based ) {
	$php_zero_based = (int) $php_zero_based;
	if ( function_exists( 'acf_get_setting' ) ) {
		$off = acf_get_setting( 'row_index_offset' );
		// Only use 0-based selectors when the setting is explicitly numeric zero (filtered).
		if ( is_numeric( $off ) && 0 === (int) $off ) {
			return $php_zero_based;
		}
	}
	return $php_zero_based + 1;
}

/**
 * Nested "vehicles" rows from one vehicle_pricing row (handles name or field_* key).
 *
 * @param array $pricing_row One row from vehicle_pricing.
 * @return array<int, array>
 */
function bst_vehicle_migration_get_nested_vehicle_rows( array $pricing_row ) {
	if ( ! empty( $pricing_row['vehicles'] ) && is_array( $pricing_row['vehicles'] ) ) {
		return $pricing_row['vehicles'];
	}
	if ( ! empty( $pricing_row[ BST_VEHICLE_NESTED_REPEATER_KEY ] ) && is_array( $pricing_row[ BST_VEHICLE_NESTED_REPEATER_KEY ] ) ) {
		return $pricing_row[ BST_VEHICLE_NESTED_REPEATER_KEY ];
	}
	return array();
}

/**
 * Label text from a vehicles repeater row.
 *
 * @param array $vrow One nested row.
 * @return string
 */
function bst_vehicle_migration_row_vehicle_text( array $vrow ) {
	if ( isset( $vrow['vehicle'] ) && $vrow['vehicle'] !== null && $vrow['vehicle'] !== '' ) {
		return trim( (string) $vrow['vehicle'] );
	}
	if ( isset( $vrow[ BST_VEHICLE_ROW_TEXT_KEY ] ) && $vrow[ BST_VEHICLE_ROW_TEXT_KEY ] !== null && $vrow[ BST_VEHICLE_ROW_TEXT_KEY ] !== '' ) {
		return trim( (string) $vrow[ BST_VEHICLE_ROW_TEXT_KEY ] );
	}
	return '';
}

/**
 * Linked CPT id from a vehicles repeater row (post object may be under name or field key).
 *
 * @param array $vrow One nested row.
 * @return int
 */
function bst_vehicle_migration_row_linked_post_id( array $vrow ) {
	$linked = isset( $vrow['vehicle_id'] ) ? $vrow['vehicle_id'] : ( isset( $vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] ) ? $vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] : 0 );
	if ( is_array( $linked ) && isset( $linked['ID'] ) ) {
		return (int) $linked['ID'];
	}
	return (int) $linked;
}

/**
 * Find an existing vehicle post ID for a base name: normalized key or compact (no-space) key only.
 * Intentionally no similar_text / levenshtein — close names (e.g. R1300GS vs R1300RT) must not match.
 *
 * @param string $base_name      Name without price suffix.
 * @param array  $norm_to_id     Map normalized key → vehicle post ID (updated when matches found).
 * @param array  $vehicles_by_id Map vehicle post ID → title.
 * @return int 0 if none.
 */
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

/**
 * When bulk update_field( vehicle_pricing ) fails, write each vehicle_id cell via update_sub_field (nested repeater).
 *
 * @param int    $tour_id Tour post ID.
 * @param array  $cells   List of arrays with keys i, j, vehicle_id (package row index, vehicle row index, CPT id).
 * @param array  $results Log lines (append warnings here).
 * @return bool True if every cell saved.
 */
function bst_vehicle_migration_apply_pricing_vehicle_ids_via_subfields( $tour_id, array $cells, array &$results ) {
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 || empty( $cells ) || ! function_exists( 'update_sub_field' ) ) {
		return false;
	}

	$all_ok = true;
	foreach ( $cells as $c ) {
		$i   = isset( $c['i'] ) ? (int) $c['i'] : -1;
		$j   = isset( $c['j'] ) ? (int) $c['j'] : -1;
		$vid = isset( $c['vehicle_id'] ) ? (int) $c['vehicle_id'] : 0;
		if ( $i < 0 || $j < 0 ) {
			$all_ok = false;
			continue;
		}

		$aci = bst_vehicle_migration_acf_repeater_selector_index( $i );
		$acj = bst_vehicle_migration_acf_repeater_selector_index( $j );

		$selectors = array(
			array( 'vehicle_pricing', $aci, 'vehicles', $acj, 'vehicle_id' ),
			array(
				BST_VEHICLE_PRICING_REPEATER_KEY,
				$aci,
				BST_VEHICLE_NESTED_REPEATER_KEY,
				$acj,
				BST_VEHICLE_ROW_POST_OBJECT_KEY,
			),
		);

		$wrote = false;
		foreach ( $selectors as $selector ) {
			if ( update_sub_field( $selector, $vid, $tour_id ) ) {
				$wrote = true;
				break;
			}
		}

		if ( ! $wrote ) {
			$all_ok = false;
			$results[] = sprintf(
				'Warning: update_sub_field failed for tour %1$d (%2$s) package row %3$d vehicle row %4$d (vehicle_id %5$d).',
				$tour_id,
				get_the_title( $tour_id ),
				$i,
				$j,
				$vid
			);
		}
	}

	return $all_ok;
}

/**
 * Create vehicle CPT, set type/active, register in maps.
 *
 * @param string $canonical_title Post title (no price suffix).
 * @param int    $tour_id         Tour ID for type_code.
 * @param array  $norm_to_id      Ref.
 * @param array  $vehicles_by_id  Ref.
 * @return int New post ID or 0.
 */
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

/**
 * Permanently delete all Vehicle CPT posts (used before a forced migration reset).
 *
 * @return int Number of posts deleted.
 */
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

/**
 * Resolve vehicle ID for migration: reuse repeater vehicle_id if valid, else match/create.
 *
 * @param array $vehicle_item Row from vehicles repeater.
 * @param int   $tour_id      Tour post ID.
 * @param array $norm_to_id   Ref.
 * @param array $vehicles_by_id Ref.
 * @param bool  $ignore_linked When true (force reset), do not trust repeater vehicle_id; derive from label only.
 * @return int
 */
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
 * Run vehicle CPT migration: tours (repeater vehicle_id) + bookings (vehicle1_id/vehicle2_id).
 *
 * @param bool $force_reset When true (Tools → force rerun), delete all Vehicle CPT posts first, ignore stored
 *                          repeater IDs, and recompute booking vehicle*_id from text every time.
 * @param bool $repair_repeater_links_from_text When true, re-derive each tour repeater row's vehicle_id from the
 *                          legacy label text only (ignore stored post object). Does not delete Vehicle CPTs.
 *                          Use after bad per-cell saves misaligned IDs with rows. Safe with fixed update_sub_field indexing.
 * @return string[] Log lines for admin UI.
 */
function bst_migrate_vehicle_cpt_links( $force_reset = false, $repair_repeater_links_from_text = false ) {
	global $wpdb;

	$results        = array();
	$norm_to_id     = array();
	$vehicles_by_id = array();
	$force_reset    = (bool) $force_reset;
	$repair_repeater_links_from_text = (bool) $repair_repeater_links_from_text;

	if ( $repair_repeater_links_from_text && ! $force_reset ) {
		$results[] = 'Repair mode: re-deriving tour repeater vehicle_id from label text (ignoring stored Vehicle CPT links). Vehicle posts are not deleted.';
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

	foreach ( $tour_posts as $tid ) {
		$tid = (int) $tid;
		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			$results[] = 'ACF/SCF not available; aborting tour updates.';
			return $results;
		}

		// Use formatted values so repeater subfields use names (vehicle, vehicle_id), not raw field keys.
		$pricing = get_field( 'vehicle_pricing', $tid, true );
		if ( empty( $pricing ) || ! is_array( $pricing ) ) {
			continue;
		}

		$tours_with_pricing++;
		$pending_cells = array();
		foreach ( $pricing as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$nested = bst_vehicle_migration_get_nested_vehicle_rows( $row );
			if ( empty( $nested ) ) {
				continue;
			}
			// Write back into the same nested key shape we read (name or field key).
			$nested_key = ( ! empty( $row['vehicles'] ) && is_array( $row['vehicles'] ) ) ? 'vehicles' : BST_VEHICLE_NESTED_REPEATER_KEY;
			foreach ( $nested as $j => $vrow ) {
				if ( ! is_array( $vrow ) ) {
					continue;
				}
				$rows_seen++;
				$ignore_linked = $force_reset || $repair_repeater_links_from_text;
				$new_id        = bst_vehicle_migration_resolve_row_id( $vrow, $tid, $norm_to_id, $vehicles_by_id, $ignore_linked );
				$old           = bst_vehicle_migration_row_linked_post_id( $vrow );
				$write         = false;

				if ( $force_reset ) {
					if ( $old !== $new_id ) {
						$pricing[ $i ][ $nested_key ][ $j ]['vehicle_id'] = $new_id;
						$write = true;
						$rows_touched++;
					}
				} elseif ( $repair_repeater_links_from_text ) {
					if ( $new_id > 0 && $old !== $new_id ) {
						$pricing[ $i ][ $nested_key ][ $j ]['vehicle_id'] = $new_id;
						$write = true;
						$rows_touched++;
					}
				} elseif ( $new_id > 0 && $old !== $new_id ) {
					$pricing[ $i ][ $nested_key ][ $j ]['vehicle_id'] = $new_id;
					$write = true;
					$rows_touched++;
				}
				if ( $write ) {
					$pending_cells[] = array(
						'i'          => $i,
						'j'          => $j,
						'vehicle_id' => $new_id,
					);
				}
			}
		}

		if ( ! empty( $pending_cells ) ) {
			$ok = update_field( 'vehicle_pricing', $pricing, $tid );
			if ( ! $ok ) {
				$ok = update_field( BST_VEHICLE_PRICING_REPEATER_KEY, $pricing, $tid );
			}
			if ( ! $ok ) {
				$ok = bst_vehicle_migration_apply_pricing_vehicle_ids_via_subfields( $tid, $pending_cells, $results );
			}
			if ( $ok ) {
				$tours_updated++;
			} else {
				$results[] = sprintf(
					'Warning: could not save vehicle_pricing for tour %d (%s) (bulk update_field and per-cell update_sub_field both failed).',
					$tid,
					get_the_title( $tid )
				);
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

	$table = $wpdb->prefix . 'bst_tour_booking';
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
			$b_updated++;
		}
	}

	$results[] = sprintf( 'Bookings updated with vehicle IDs: %d', $b_updated );
	$results[] = sprintf( 'Vehicle CPT count after migration: %d', count( $vehicles_by_id ) );

	return $results;
}
