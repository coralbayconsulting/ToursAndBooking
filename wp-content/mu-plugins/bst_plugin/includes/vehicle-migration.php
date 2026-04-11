<?php
/**
 * One-time / Tools: migrate tour vehicle rows and bookings to Vehicle CPT IDs.
 *
 * Never pass a reduced repeater tree to {@see update_field()} (e.g. only name keys without copying field_* values)
 * — that can clear Class / Vehicle Price Addition when raw loads use field keys. Use full tree + coalesce.
 *
 * Tour `vehicle_pricing` is saved only via {@see update_field()} while programmatic bypasses are active:
 * {@see BST_Plugin::acf_tour_vehicle_pricing_cpt_query} must not restrict `post__in`, and
 * {@see bst_vehicle_migration_acf_pass_vehicle_pricing_vehicle_id} must allow the Vehicle post_object subfield.
 * Diagnostics: PHP `error_log` lines prefixed `[BST release cleanup]`.
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
/** @var string vehicle_pricing row → Class (number). */
const BST_VEHICLE_PRICING_ROW_CLASS_KEY      = 'field_67ad575f16fd4';
/** @var string vehicle_pricing row → Vehicle Price Addition. */
const BST_VEHICLE_PRICING_ROW_PRICE_ADD_KEY  = 'field_67b89cf27ef01';

/**
 * Copy ACF field_* values into name keys when names are missing/empty so {@see update_field()} never saves stripped rows.
 * Raw/meta-heavy loads often populate only field keys; a partial “names only” tree must never be written (it wipes pricing).
 *
 * @param array $pricing vehicle_pricing repeater (by reference).
 */
function bst_vehicle_migration_coalesce_vehicle_pricing_tree_keys( array &$pricing ) {
	foreach ( $pricing as $i => &$row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		if ( isset( $row[ BST_VEHICLE_PRICING_ROW_CLASS_KEY ] ) ) {
			if ( ! array_key_exists( 'class', $row ) || $row['class'] === '' || $row['class'] === null ) {
				$row['class'] = $row[ BST_VEHICLE_PRICING_ROW_CLASS_KEY ];
			}
		}
		if ( isset( $row[ BST_VEHICLE_PRICING_ROW_PRICE_ADD_KEY ] ) ) {
			if ( ! array_key_exists( 'vehicle_price_addition', $row ) || $row['vehicle_price_addition'] === '' || $row['vehicle_price_addition'] === null ) {
				$row['vehicle_price_addition'] = $row[ BST_VEHICLE_PRICING_ROW_PRICE_ADD_KEY ];
			}
		}
		$pk = bst_vehicle_migration_nested_repeater_primary_key( $row );
		if ( ! $pk || empty( $row[ $pk ] ) || ! is_array( $row[ $pk ] ) ) {
			continue;
		}
		foreach ( $row[ $pk ] as $j => &$vrow ) {
			if ( ! is_array( $vrow ) ) {
				continue;
			}
			if ( isset( $vrow[ BST_VEHICLE_ROW_TEXT_KEY ] ) ) {
				if ( ! array_key_exists( 'vehicle', $vrow ) || $vrow['vehicle'] === '' || $vrow['vehicle'] === null ) {
					$vrow['vehicle'] = $vrow[ BST_VEHICLE_ROW_TEXT_KEY ];
				}
			}
		}
		unset( $vrow );
	}
	unset( $row );
}

/**
 * Whether a repeater array uses non–zero-based keys (e.g. 1,2,3 with row_index_offset=1).
 *
 * @param array $nested Repeater rows.
 * @return bool True if {@see array_values()} should be applied so index 0 is the first row.
 */
function bst_vehicle_migration_nested_repeater_needs_array_reindex( array $nested ) {
	if ( empty( $nested ) || ! is_array( $nested ) ) {
		return false;
	}
	$keys  = array_keys( $nested );
	$first = reset( $keys );
	return $first !== 0 && $first !== '0';
}

/**
 * When both nested branches exist, copy vehicle label + CPT id from the primary branch into the sibling so both
 * stay row-aligned. Independent {@see array_values()} on each branch can reorder them differently and shift CPT
 * one row forward or back relative to labels.
 *
 * @param array  $row         One vehicle_pricing row (by reference).
 * @param string $primary_key {@see bst_vehicle_migration_nested_repeater_primary_key()}.
 * @param string $other_key   The other nested key (`vehicles` or field_*).
 */
function bst_vehicle_migration_sync_nested_vehicle_branches_from_primary( array &$row, $primary_key, $other_key ) {
	if ( empty( $row[ $primary_key ] ) || ! is_array( $row[ $primary_key ] ) ) {
		return;
	}
	$n      = count( $row[ $primary_key ] );
	$synced = array();
	for ( $j = 0; $j < $n; $j++ ) {
		$pvrow = isset( $row[ $primary_key ][ $j ] ) && is_array( $row[ $primary_key ][ $j ] ) ? $row[ $primary_key ][ $j ] : array();
		$vid   = bst_vehicle_migration_row_linked_post_id( $pvrow );
		$txt   = bst_vehicle_migration_row_vehicle_text( $pvrow );
		$synced[ $j ] = array();
		bst_vehicle_migration_assign_vehicle_id_on_nested_row( $synced[ $j ], $vid > 0 ? $vid : false );
		if ( '' !== $txt ) {
			$synced[ $j ]['vehicle'] = $txt;
			$synced[ $j ][ BST_VEHICLE_ROW_TEXT_KEY ] = $txt;
		}
	}
	$row[ $other_key ] = $synced;
}

/**
 * Normalize repeater PHP keys only when needed, then sync duplicate nested branches.
 *
 * ACF maps PHP index 0 to the first repeater row. Keys 1,2,3… leave [0] empty in the UI. Calling {@see array_values()}
 * on every load fixes that but, if both `vehicles` and field_* arrays exist, doing it separately can misalign rows;
 * we rebuild the sibling from the primary branch after reindexing the primary.
 *
 * @param array $pricing vehicle_pricing repeater (by reference).
 */
function bst_vehicle_migration_reindex_vehicle_pricing_repeaters( array &$pricing ) {
	if ( bst_vehicle_migration_nested_repeater_needs_array_reindex( $pricing ) ) {
		$pricing = array_values( $pricing );
	}
	foreach ( $pricing as $i => &$row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$pk = bst_vehicle_migration_nested_repeater_primary_key( $row );
		if ( ! $pk || empty( $row[ $pk ] ) || ! is_array( $row[ $pk ] ) ) {
			continue;
		}
		if ( bst_vehicle_migration_nested_repeater_needs_array_reindex( $row[ $pk ] ) ) {
			$row[ $pk ] = array_values( $row[ $pk ] );
		}
		$other = ( BST_VEHICLE_NESTED_REPEATER_KEY === $pk ) ? 'vehicles' : BST_VEHICLE_NESTED_REPEATER_KEY;
		if ( empty( $row[ $other ] ) || ! is_array( $row[ $other ] ) ) {
			continue;
		}
		bst_vehicle_migration_sync_nested_vehicle_branches_from_primary( $row, $pk, $other );
	}
	unset( $row );
}

/**
 * Clear WP/ACF caches for a tour before reading or writing repeater meta (helps migration round-trip).
 *
 * @param int $tour_id Tour post ID.
 */
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

/**
 * While non-zero, acf_tour_vehicle_pricing_cpt_query must not restrict post_object choices (migration writes).
 * Paired with {@see bst_vehicle_migration_acf_pass_vehicle_pricing_vehicle_id} on acf/validate_value for the same field.
 */
function bst_vehicle_migration_push_post_object_query_bypass() {
	$g = &$GLOBALS['bst_vehicle_migration_po_query_bypass'];
	$g = (int) $g + 1;
}

/**
 * @return void
 */
function bst_vehicle_migration_pop_post_object_query_bypass() {
	if ( empty( $GLOBALS['bst_vehicle_migration_po_query_bypass'] ) ) {
		return;
	}
	--$GLOBALS['bst_vehicle_migration_po_query_bypass'];
	if ( $GLOBALS['bst_vehicle_migration_po_query_bypass'] < 0 ) {
		$GLOBALS['bst_vehicle_migration_po_query_bypass'] = 0;
	}
}

/**
 * @return bool
 */
function bst_vehicle_migration_is_post_object_query_bypassed() {
	return ! empty( $GLOBALS['bst_vehicle_migration_po_query_bypass'] );
}

/**
 * Structured diagnostic line for release cleanup (always error_log; search for prefix in PHP error log).
 *
 * @param string $message Single line, no HTML.
 */
function bst_vehicle_migration_release_cleanup_log( $message ) {
	$message = trim( (string) $message );
	if ( '' === $message ) {
		return;
	}
	error_log( '[BST release cleanup] ' . $message );
}

/**
 * All plausible postmeta keys for a vehicle_id cell (canonical write path + legacy inner-index variant for diagnostics).
 *
 * @param int $pi Parent ordinal 0-based.
 * @param int $pj Nested ordinal 0-based.
 * @return array<int, string>
 */
function bst_vehicle_migration_vehicle_id_postmeta_key_candidates( $pi, $pj ) {
	$pi   = (int) $pi;
	$pj   = (int) $pj;
	$keys = array(
		bst_vehicle_migration_vehicle_id_value_meta_key( $pi, $pj ),
	);
	$pi_seg = bst_vehicle_migration_acf_repeater_selector_index( $pi );
	$pj_seg = bst_vehicle_migration_nested_repeater_meta_row_segment( $pj );
	// Legacy: some runs used parent offset rule for nested segment (wrong for standard ACF keys).
	$pj_wrong_offset = bst_vehicle_migration_acf_repeater_selector_index( $pj );
	$pj_zero_raw     = (int) $pj;
	$keys[]          = BST_VEHICLE_PRICING_REPEATER_KEY . '_' . $pi_seg . '_' . BST_VEHICLE_NESTED_REPEATER_KEY . '_' . $pj_wrong_offset . '_' . BST_VEHICLE_ROW_POST_OBJECT_KEY;
	$keys[]          = 'vehicle_pricing_' . $pi_seg . '_vehicles_' . $pj_seg . '_vehicle_id';
	$keys[]          = 'vehicle_pricing_' . $pi_seg . '_vehicles_' . $pj_zero_raw . '_vehicle_id';

	return array_values( array_unique( array_filter( $keys ) ) );
}

/**
 * Read linked vehicle CPT id for one pending cell from get_field (formatted, matches admin).
 *
 * @param int   $tour_id Tour post ID.
 * @param array $c       One pending_cells item.
 * @return int
 */
function bst_vehicle_migration_read_vehicle_id_for_pending_cell( $tour_id, array $c ) {
	$tour_id = (int) $tour_id;
	if ( ! function_exists( 'get_field' ) ) {
		return 0;
	}
	$pricing = get_field( 'vehicle_pricing', $tour_id, false );
	if ( empty( $pricing ) || ! is_array( $pricing ) ) {
		return 0;
	}
	if ( isset( $c['i'], $c['j'], $pricing[ $c['i'] ] ) && is_array( $pricing[ $c['i'] ] ) ) {
		$nested = bst_vehicle_migration_get_nested_vehicle_rows( $pricing[ $c['i'] ] );
		if ( isset( $nested[ $c['j'] ] ) && is_array( $nested[ $c['j'] ] ) ) {
			return bst_vehicle_migration_row_linked_post_id( $nested[ $c['j'] ] );
		}
	}
	$pi = isset( $c['meta_row'] ) ? (int) $c['meta_row'] : 0;
	$pj = isset( $c['meta_subrow'] ) ? (int) $c['meta_subrow'] : 0;
	$prows = array_values( $pricing );
	if ( ! isset( $prows[ $pi ] ) || ! is_array( $prows[ $pi ] ) ) {
		return 0;
	}
	$nested = bst_vehicle_migration_get_nested_vehicle_rows( $prows[ $pi ] );
	$nrows  = array_values( $nested );
	if ( ! isset( $nrows[ $pj ] ) || ! is_array( $nrows[ $pj ] ) ) {
		return 0;
	}
	return bst_vehicle_migration_row_linked_post_id( $nrows[ $pj ] );
}

/**
 * Confirm each pending cell’s vehicle_id after save: canonical postmeta first, then alternate keys (legacy indexing),
 * then get_field() as a last resort when ACF wrote values but meta key shape differs.
 *
 * @param int   $tour_id Tour post ID.
 * @param array $cells   pending_cells.
 * @return string[] Empty if OK; else human-readable mismatch lines.
 */
function bst_vehicle_migration_verify_pending_cells( $tour_id, array $cells ) {
	$tour_id = (int) $tour_id;
	$issues  = array();
	bst_vehicle_migration_bust_caches_for_tour( $tour_id );

	foreach ( $cells as $c ) {
		$expected = isset( $c['vehicle_id'] ) ? (int) $c['vehicle_id'] : 0;
		$pi       = isset( $c['meta_row'] ) ? (int) $c['meta_row'] : null;
		$pj       = isset( $c['meta_subrow'] ) ? (int) $c['meta_subrow'] : null;
		if ( null === $pi || null === $pj ) {
			$issues[] = sprintf( 'tour %d verify: missing meta_row/meta_subrow', $tour_id );
			continue;
		}

		$canonical   = bst_vehicle_migration_vehicle_id_value_meta_key( $pi, $pj );
		$from_canon  = (int) get_post_meta( $tour_id, $canonical, true );
		if ( $from_canon === $expected ) {
			continue;
		}

		$matched_alt = false;
		foreach ( bst_vehicle_migration_vehicle_id_postmeta_key_candidates( $pi, $pj ) as $k ) {
			if ( $k === $canonical ) {
				continue;
			}
			if ( (int) get_post_meta( $tour_id, $k, true ) === $expected ) {
				$matched_alt = true;
				bst_vehicle_migration_release_cleanup_log(
					sprintf(
						'VERIFY note tour=%d pi=%d pj=%d expected=%d matched postmeta key %s (canonical had %d)',
						$tour_id,
						$pi,
						$pj,
						$expected,
						$k,
						$from_canon
					)
				);
				break;
			}
		}
		if ( $matched_alt ) {
			continue;
		}

		$from_field = (int) bst_vehicle_migration_read_vehicle_id_for_pending_cell( $tour_id, $c );
		if ( $from_field === $expected ) {
			bst_vehicle_migration_release_cleanup_log(
				sprintf(
					'VERIFY note tour=%d pi=%d pj=%d expected=%d matched get_field (canonical postmeta had %d)',
					$tour_id,
					$pi,
					$pj,
					$expected,
					$from_canon
				)
			);
			continue;
		}

		$issues[] = sprintf(
			'tour %d package[%d] vehicle[%d] expected vehicle_id %d; postmeta canonical=%d; get_field=%d',
			$tour_id,
			$pi,
			$pj,
			$expected,
			$from_canon,
			$from_field
		);
	}

	return $issues;
}

/**
 * Row index segment for ACF nested postmeta keys (respects row_index_offset).
 *
 * @param int $php_zero_based Ordinal from migration loops (0 = first row).
 * @return int
 */
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

/**
 * Nested repeater row index segment in ACF postmeta keys (vehicles under one package row).
 *
 * Standard ACF keys look like `field_outer_1_field_nested_0_field_vehicle` — the **outer** row uses
 * {@see bst_vehicle_migration_acf_repeater_selector_index()} (honours row_index_offset); the **inner** row index in
 * the meta name is **0-based** (first nested row = 0). The “one off” UI bug is usually **PHP array keys** 1,2,3 on
 * the nested array passed to {@see update_field()}, not this segment — see {@see bst_vehicle_migration_reindex_vehicle_pricing_repeaters()}.
 *
 * @param int $php_zero_based Inner ordinal from migration loops (0 = first vehicle in that package).
 * @return int
 */
function bst_vehicle_migration_nested_repeater_meta_row_segment( $php_zero_based ) {
	$php_zero_based = (int) $php_zero_based;
	/**
	 * @param int $segment        Default nested segment (0-based in meta keys).
	 * @param int $php_zero_based Inner row ordinal passed in.
	 */
	return (int) apply_filters( 'bst_vehicle_migration_nested_repeater_meta_row_segment', $php_zero_based, $php_zero_based );
}

/**
 * Postmeta key for Tour → vehicle_pricing[pi].vehicles[pj].vehicle_id (field_67f9e40b1c001).
 *
 * @param int $package_index_0 Parent row ordinal (0-based).
 * @param int $vehicle_index_0 Nested row ordinal (0-based).
 * @return string
 */
function bst_vehicle_migration_vehicle_id_value_meta_key( $package_index_0, $vehicle_index_0 ) {
	$pi = bst_vehicle_migration_acf_repeater_selector_index( (int) $package_index_0 );
	$pj = bst_vehicle_migration_nested_repeater_meta_row_segment( (int) $vehicle_index_0 );
	return BST_VEHICLE_PRICING_REPEATER_KEY . '_' . $pi . '_' . BST_VEHICLE_NESTED_REPEATER_KEY . '_' . $pj . '_' . BST_VEHICLE_ROW_POST_OBJECT_KEY;
}

/**
 * Whether an ACF save call succeeded ({@see bst_acf_save_returned_ok} when available).
 *
 * @param mixed $r Return value from update_field / acf_update_value.
 * @return bool
 */
function bst_vehicle_migration_acf_save_returned_ok( $r ) {
	return function_exists( 'bst_acf_save_returned_ok' ) ? bst_acf_save_returned_ok( $r ) : ( false !== $r );
}

/**
 * Write vehicle_id cells using the same meta keys ACF uses (works when update_field rejects nested repeaters).
 *
 * @param int    $tour_id Tour post ID.
 * @param array  $cells   pending_cells.
 * @param array  $results Log (append warnings on verify miss).
 * @return bool True if every cell written and verified.
 */
function bst_vehicle_migration_apply_pricing_vehicle_ids_via_postmeta( $tour_id, array $cells, array &$results ) {
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 || empty( $cells ) ) {
		return false;
	}

	$all_ok = true;
	foreach ( $cells as $c ) {
		$vid = isset( $c['vehicle_id'] ) ? (int) $c['vehicle_id'] : 0;
		$pi  = isset( $c['meta_row'] ) ? (int) $c['meta_row'] : null;
		$pj  = isset( $c['meta_subrow'] ) ? (int) $c['meta_subrow'] : null;
		if ( null === $pi || null === $pj ) {
			$all_ok    = false;
			$results[] = sprintf( 'Error: missing meta_row/meta_subrow for tour %d postmeta write.', $tour_id );
			continue;
		}

		$value_key = bst_vehicle_migration_vehicle_id_value_meta_key( $pi, $pj );
		$ref_key   = '_' . $value_key;

		if ( $vid > 0 ) {
			update_post_meta( $tour_id, $value_key, $vid );
			update_post_meta( $tour_id, $ref_key, BST_VEHICLE_ROW_POST_OBJECT_KEY );
			$read = get_post_meta( $tour_id, $value_key, true );
			if ( (int) $read !== $vid ) {
				$all_ok = false;
				bst_vehicle_migration_release_cleanup_log(
					sprintf(
						'postmeta verify FAILED tour=%d key=%s expected=%d got=%s',
						$tour_id,
						$value_key,
						$vid,
						is_scalar( $read ) ? (string) $read : '?'
					)
				);
				$results[] = sprintf(
					'Error: postmeta verify failed tour %1$d row %2$d/%3$d (vehicle_id %4$d).',
					$tour_id,
					$pi,
					$pj,
					$vid
				);
			}
		} else {
			delete_post_meta( $tour_id, $value_key );
			delete_post_meta( $tour_id, $ref_key );
		}

		// Legacy name-based meta keys — use same inner segment rule as field-key path.
		$pi_acf   = bst_vehicle_migration_acf_repeater_selector_index( (int) $pi );
		$pj_acf   = bst_vehicle_migration_nested_repeater_meta_row_segment( (int) $pj );
		$name_val = 'vehicle_pricing_' . $pi_acf . '_vehicles_' . $pj_acf . '_vehicle_id';
		$name_ref = '_' . $name_val;
		if ( $vid > 0 ) {
			update_post_meta( $tour_id, $name_val, $vid );
			update_post_meta( $tour_id, $name_ref, BST_VEHICLE_ROW_POST_OBJECT_KEY );
		} else {
			delete_post_meta( $tour_id, $name_val );
			delete_post_meta( $tour_id, $name_ref );
		}
	}

	bst_vehicle_migration_bust_caches_for_tour( $tour_id );
	return $all_ok;
}

/**
 * Persist vehicle_pricing: prefer ACF {@see update_field()} / {@see acf_update_value()} with filter bypasses; if those
 * return false (common with nested repeaters / SCF), write vehicle_id cells via the same postmeta keys ACF uses.
 *
 * Requires {@see bst_vehicle_migration_push_post_object_query_bypass()} and
 * {@see bst_vehicle_migration_acf_pass_vehicle_pricing_vehicle_id} during the run.
 *
 * @param int    $tour_id       Tour post ID.
 * @param array  $pricing       Normalized vehicle_pricing tree.
 * @param array  $pending_cells Cells we changed (for verification / postmeta).
 * @param array  $results       User-visible result lines (append).
 * @return bool True if get_field verification passes for pending cells.
 */
function bst_vehicle_migration_save_vehicle_pricing_for_tour( $tour_id, array $pricing, array $pending_cells, array &$results ) {
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 || empty( $pending_cells ) ) {
		bst_vehicle_migration_release_cleanup_log( sprintf( 'save_vehicle_pricing aborted: invalid tour_id=%d or empty pending_cells', $tour_id ) );
		return false;
	}
	if ( ! function_exists( 'update_field' ) ) {
		bst_vehicle_migration_release_cleanup_log( 'save_vehicle_pricing aborted: update_field() not available (ACF inactive?)' );
		$results[] = 'Error: ACF update_field() not available; cannot save vehicle_pricing.';
		return false;
	}

	$bypass = isset( $GLOBALS['bst_vehicle_migration_po_query_bypass'] ) ? (int) $GLOBALS['bst_vehicle_migration_po_query_bypass'] : 0;
	if ( $bypass < 1 ) {
		bst_vehicle_migration_release_cleanup_log(
			sprintf(
				'CRITICAL tour=%d: post_object query bypass depth is %d (must be >= 1). BST_Plugin::acf_tour_vehicle_pricing_cpt_query may restrict vehicle IDs.',
				$tour_id,
				$bypass
			)
		);
	}

	$offset = function_exists( 'acf_get_setting' ) ? acf_get_setting( 'row_index_offset' ) : null;
	bst_vehicle_migration_release_cleanup_log(
		sprintf(
			'save_vehicle_pricing start tour=%d title=%s pending_cells=%d acf_row_index_offset=%s bypass_depth=%d',
			$tour_id,
			str_replace( array( "\r", "\n" ), ' ', get_the_title( $tour_id ) ),
			count( $pending_cells ),
			null !== $offset && is_scalar( $offset ) ? (string) $offset : wp_json_encode( $offset ),
			$bypass
		)
	);

	bst_vehicle_migration_coalesce_vehicle_pricing_tree_keys( $pricing );
	bst_vehicle_migration_reindex_vehicle_pricing_repeaters( $pricing );

	$field_defs = array(
		array( 'vehicle_pricing', 'name vehicle_pricing' ),
		array( BST_VEHICLE_PRICING_REPEATER_KEY, 'key ' . BST_VEHICLE_PRICING_REPEATER_KEY ),
	);
	$post_refs = array( $tour_id, 'post_' . $tour_id );

	$saved_via = null;

	foreach ( $post_refs as $post_ref ) {
		foreach ( $field_defs as $fd ) {
			$field_id = $fd[0];
			$label    = $fd[1];
			$r        = update_field( $field_id, $pricing, $post_ref );
			$ref_s    = is_int( $post_ref ) ? (string) $post_ref : $post_ref;
			if ( ! bst_vehicle_migration_acf_save_returned_ok( $r ) ) {
				bst_vehicle_migration_release_cleanup_log(
					sprintf(
						'update_field FAILED tour=%d field=%s post_ref=%s return=%s',
						$tour_id,
						$label,
						$ref_s,
						false === $r ? 'false' : wp_json_encode( $r )
					)
				);
				continue;
			}
			$saved_via = 'update_field:full_tree:' . $label . ':' . $ref_s;
			bst_vehicle_migration_release_cleanup_log(
				sprintf(
					'update_field OK tour=%d field=%s post_ref=%s return=%s',
					$tour_id,
					$label,
					$ref_s,
					null === $r ? 'null' : wp_json_encode( $r )
				)
			);
			break 2;
		}
	}

	if ( ! $saved_via && function_exists( 'acf_get_field' ) && function_exists( 'acf_update_value' ) ) {
		$field = acf_get_field( BST_VEHICLE_PRICING_REPEATER_KEY );
		if ( is_array( $field ) ) {
			foreach ( array( $tour_id, 'post_' . $tour_id ) as $pid ) {
				$r = acf_update_value( $pricing, $pid, $field );
				if ( bst_vehicle_migration_acf_save_returned_ok( $r ) ) {
					$saved_via = 'acf_update_value:full_tree:' . ( is_int( $pid ) ? (string) $pid : $pid );
					bst_vehicle_migration_release_cleanup_log(
						sprintf(
							'acf_update_value OK tour=%d post_id=%s return=%s',
							$tour_id,
							is_int( $pid ) ? (string) $pid : $pid,
							null === $r ? 'null' : wp_json_encode( $r )
						)
					);
					break;
				}
				bst_vehicle_migration_release_cleanup_log(
					sprintf(
						'acf_update_value FAILED tour=%d post_id=%s return=%s',
						$tour_id,
						is_int( $pid ) ? (string) $pid : $pid,
						false === $r ? 'false' : wp_json_encode( $r )
					)
				);
			}
		}
	}

	if ( ! $saved_via ) {
		$top_n = is_array( $pricing ) ? count( $pricing ) : 0;
		$k0    = array();
		if ( isset( $pricing[0] ) && is_array( $pricing[0] ) ) {
			$k0 = array_keys( $pricing[0] );
		}
		bst_vehicle_migration_release_cleanup_log(
			sprintf(
				'all ACF API attempts failed tour=%d top_level_rows=%d first_row_keys=%s — using direct postmeta for vehicle_id cells',
				$tour_id,
				$top_n,
				wp_json_encode( $k0 )
			)
		);
		if ( ! bst_vehicle_migration_apply_pricing_vehicle_ids_via_postmeta( $tour_id, $pending_cells, $results ) ) {
			$results[] = sprintf(
				'Error: could not save vehicle_pricing for tour %d (%s) (update_field, acf_update_value, and postmeta all failed). See [BST release cleanup] in error_log.',
				$tour_id,
				get_the_title( $tour_id )
			);
			return false;
		}
		$saved_via = 'direct_postmeta';
	}

	bst_vehicle_migration_release_cleanup_log( sprintf( 'SAVED_VIA=%s tour=%d', $saved_via, $tour_id ) );

	bst_vehicle_migration_bust_caches_for_tour( $tour_id );
	$mismatches = bst_vehicle_migration_verify_pending_cells( $tour_id, $pending_cells );
	if ( ! empty( $mismatches ) ) {
		foreach ( $mismatches as $m ) {
			bst_vehicle_migration_release_cleanup_log( 'VERIFY_FAIL ' . $m );
		}
		$results[] = sprintf(
			'Error: vehicle_pricing save verification failed for tour %d (%s). See [BST release cleanup] VERIFY_FAIL lines.',
			$tour_id,
			get_the_title( $tour_id )
		);
		return false;
	}

	bst_vehicle_migration_release_cleanup_log(
		sprintf( 'save_vehicle_pricing complete tour=%d verified_cells=%d', $tour_id, count( $pending_cells ) )
	);
	return true;
}

/**
 * Which nested repeater key is authoritative when both `vehicles` and field_* exist (must match
 * {@see bst_vehicle_migration_get_nested_vehicle_rows()}).
 *
 * @param array $pricing_row One vehicle_pricing row.
 * @return string|null 'vehicles', BST_VEHICLE_NESTED_REPEATER_KEY, or null if neither.
 */
function bst_vehicle_migration_nested_repeater_primary_key( array $pricing_row ) {
	$by_name = ( ! empty( $pricing_row['vehicles'] ) && is_array( $pricing_row['vehicles'] ) ) ? $pricing_row['vehicles'] : null;
	$by_key  = ( ! empty( $pricing_row[ BST_VEHICLE_NESTED_REPEATER_KEY ] ) && is_array( $pricing_row[ BST_VEHICLE_NESTED_REPEATER_KEY ] ) )
		? $pricing_row[ BST_VEHICLE_NESTED_REPEATER_KEY ] : null;
	// After mixed API/postmeta saves, both branches can exist; field-key data usually holds the CPT id ACF loads.
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

/**
 * Nested "vehicles" rows from one vehicle_pricing row (handles name or field_* key).
 *
 * @param array $pricing_row One row from vehicle_pricing.
 * @return array<int, array>
 */
function bst_vehicle_migration_get_nested_vehicle_rows( array $pricing_row ) {
	$k = bst_vehicle_migration_nested_repeater_primary_key( $pricing_row );
	if ( ! $k ) {
		return array();
	}
	$nested = $pricing_row[ $k ];
	return is_array( $nested ) ? $nested : array();
}

/**
 * Set vehicle CPT id on primary nested row and mirror to the sibling branch when both exist (same index $j).
 *
 * @param array $pricing_row One vehicle_pricing row (by reference).
 * @param mixed $j           Nested row key/index.
 * @param string $primary_key From {@see bst_vehicle_migration_nested_repeater_primary_key()}.
 * @param int   $new_id      Vehicle post ID.
 */
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
	if ( is_object( $linked ) && isset( $linked->ID ) ) {
		return (int) $linked->ID;
	}
	return (int) $linked;
}

/**
 * Set Vehicle CPT id on a nested repeater row. ACF often stores the post object under both name and field_* key;
 * writing only one can leave stale meta and make update_field() fail (especially after raw DB loads).
 *
 * @param array $vrow   Nested vehicles row (by reference).
 * @param int   $new_id Vehicle post ID.
 */
function bst_vehicle_migration_assign_vehicle_id_on_nested_row( array &$vrow, $new_id ) {
	$new_id = (int) $new_id;
	// Post object field (allow null): use false for empty so ACF accepts the row on bulk save.
	if ( $new_id <= 0 ) {
		$vrow['vehicle_id']                        = false;
		$vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] = false;
		return;
	}
	$vrow['vehicle_id']                        = $new_id;
	$vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] = $new_id;
}

/**
 * After get_field(…, true), nested vehicle_id cells may still be WP_Post objects on unchanged rows.
 * Passing that mixed tree to update_field() often returns false (ACF cannot round-trip objects). Force every
 * nested row to scalar IDs (or false) before save.
 *
 * @param array $pricing vehicle_pricing repeater (by reference).
 */
function bst_vehicle_migration_normalize_vehicle_pricing_for_save( array &$pricing ) {
	foreach ( $pricing as $i => &$row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$nested_key = bst_vehicle_migration_nested_repeater_primary_key( $row );
		if ( ! $nested_key ) {
			continue;
		}
		foreach ( $row[ $nested_key ] as $j => $vrow ) {
			if ( ! is_array( $vrow ) ) {
				continue;
			}
			$vid = bst_vehicle_migration_row_linked_post_id( $vrow );
			bst_vehicle_migration_assign_nested_vehicle_id_mirrored( $pricing[ $i ], $j, $nested_key, $vid );
		}
	}
	unset( $row );
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
 * @param bool $force_reset When true, delete all Vehicle CPT posts, recreate them from tour repeater **text** (scan
 *                          only — does **not** save `vehicle_pricing`), then refresh booking `vehicle*_id`. Tour CPT
 *                          links are set only when `$repair_repeater_links_from_text` is true in the same run.
 * @param bool $repair_repeater_links_from_text When true, save tour `vehicle_pricing` by re-deriving each nested row’s
 *                          Vehicle CPT id from label text (ignore stored post object). Does not delete Vehicle posts.
 * @return string[] Log lines for admin UI.
 */
function bst_migrate_vehicle_cpt_links( $force_reset = false, $repair_repeater_links_from_text = false ) {
	global $wpdb;

	if ( function_exists( 'bst_ensure_tour_booking_vehicle_id_columns' ) ) {
		bst_ensure_tour_booking_vehicle_id_columns();
	}

	$results        = array();
	$norm_to_id     = array();
	$vehicles_by_id = array();
	$force_reset    = (bool) $force_reset;
	$repair_repeater_links_from_text = (bool) $repair_repeater_links_from_text;

	if ( $repair_repeater_links_from_text && ! $force_reset ) {
		$results[] = 'Re-link mode: saving tour vehicle_pricing from label text (ignoring stored Vehicle CPT links). Vehicle posts are not deleted.';
	}
	if ( $force_reset && ! $repair_repeater_links_from_text ) {
		$results[] = 'Force reset only: recreated Vehicle CPTs from tour labels (inventory); tour vehicle_pricing was not saved. Check "Re-link tour repeater from labels" and run again to write CPT links on tours.';
	}

	if ( function_exists( 'acf_get_setting' ) ) {
		$results[] = sprintf(
			'ACF row_index_offset=%s (see error_log [BST release cleanup] save_vehicle_pricing lines for context).',
			var_export( acf_get_setting( 'row_index_offset' ), true )
		);
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

	// Entire migration: UI picker filter (car vs motorcycle) must not block programmatic saves or create_vehicle()'s update_field.
	// Nested inner bypass removed — single scope covers tour loop + booking loop + vehicle CPT field updates.
	bst_vehicle_migration_push_post_object_query_bypass();
	try {

	foreach ( $tour_posts as $tid ) {
		$tid = (int) $tid;
		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			$results[] = 'ACF/SCF not available; aborting tour updates.';
			return $results;
		}

		bst_vehicle_migration_bust_caches_for_tour( $tid );
		// Raw (false) matches postmeta branches the migration writes; formatted can skew nested vehicles vs field_*.
		$pricing = get_field( 'vehicle_pricing', $tid, false );
		if ( empty( $pricing ) || ! is_array( $pricing ) ) {
			continue;
		}
		bst_vehicle_migration_reindex_vehicle_pricing_repeaters( $pricing );

		$tours_with_pricing++;

		// Force reset alone: recreate Vehicle CPTs from repeater label text only — never save vehicle_pricing here.
		if ( $force_reset && ! $repair_repeater_links_from_text ) {
			foreach ( $pricing as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$nested = bst_vehicle_migration_get_nested_vehicle_rows( $row );
				if ( empty( $nested ) ) {
					continue;
				}
				foreach ( $nested as $vrow ) {
					if ( ! is_array( $vrow ) ) {
						continue;
					}
					++$rows_seen;
					bst_vehicle_migration_resolve_row_id( $vrow, $tid, $norm_to_id, $vehicles_by_id, true );
				}
			}
			continue;
		}

		$pending_cells = array();
		$pi              = 0;
		foreach ( $pricing as $i => $row ) {
			if ( ! is_array( $row ) ) {
				++$pi;
				continue;
			}
			$nested = bst_vehicle_migration_get_nested_vehicle_rows( $row );
			if ( empty( $nested ) ) {
				++$pi;
				continue;
			}
			// Must write the same branch {@see bst_vehicle_migration_get_nested_vehicle_rows()} uses; if both `vehicles`
			// and field_* exist and differ, preferring `vehicles` left CPT ids on the branch ACF actually loads.
			$nested_key = bst_vehicle_migration_nested_repeater_primary_key( $row );
			if ( null === $nested_key ) {
				++$pi;
				continue;
			}
			$pj = 0;
			foreach ( $nested as $j => $vrow ) {
				if ( is_array( $vrow ) ) {
					$rows_seen++;
					$ignore_linked = $repair_repeater_links_from_text;
					$new_id        = bst_vehicle_migration_resolve_row_id( $vrow, $tid, $norm_to_id, $vehicles_by_id, $ignore_linked );
					$old           = bst_vehicle_migration_row_linked_post_id( $vrow );
					$write         = false;

					if ( $repair_repeater_links_from_text ) {
						if ( $new_id > 0 && $old !== $new_id ) {
							bst_vehicle_migration_assign_nested_vehicle_id_mirrored( $pricing[ $i ], $j, $nested_key, $new_id );
							$write = true;
							$rows_touched++;
						}
					} elseif ( $new_id > 0 && $old !== $new_id ) {
						bst_vehicle_migration_assign_nested_vehicle_id_mirrored( $pricing[ $i ], $j, $nested_key, $new_id );
						$write = true;
						$rows_touched++;
					}
					if ( $write ) {
						$pending_cells[] = array(
							'meta_row'    => $pi,
							'meta_subrow' => $pj,
							'i'           => $i,
							'j'           => $j,
							'vehicle_id'  => $new_id,
						);
					}
				}
				++$pj;
			}
			++$pi;
		}

		if ( ! empty( $pending_cells ) ) {
			if ( function_exists( 'acf_get_field_groups' ) ) {
				acf_get_field_groups( array( 'post_id' => $tid ) );
			}
			// Unchanged rows may still have vehicle_id as WP_Post; update_field rejects that — normalize first.
			bst_vehicle_migration_normalize_vehicle_pricing_for_save( $pricing );
			bst_vehicle_migration_bust_caches_for_tour( $tid );
			// Single save path: ACF update_field() while post_object query bypass + validate_value bypass are active
			// (see BST_Plugin::acf_tour_vehicle_pricing_cpt_query and bst_vehicle_migration_acf_pass_vehicle_pricing_vehicle_id).
			$ok = bst_vehicle_migration_save_vehicle_pricing_for_tour( $tid, $pricing, $pending_cells, $results );
			if ( $ok ) {
				bst_vehicle_migration_bust_caches_for_tour( $tid );
				$tours_updated++;
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

	} finally {
		bst_vehicle_migration_pop_post_object_query_bypass();
	}
}

/**
 * During vehicle migration, allow saving Tour → vehicle_pricing → Vehicle (CPT) even when the id is not in the
 * car/motorcycle picker list. Pairs with {@see BST_Plugin::acf_tour_vehicle_pricing_cpt_query}: that filter narrows
 * `post__in` for the admin picker; without this validate bypass, ACF/SCF can still reject programmatic update_field().
 *
 * @param mixed $valid Prior validation (true, false, or error string).
 * @param mixed $value Submitted value.
 * @param array $field Field array.
 * @param string $input Input name.
 * @return mixed
 */
function bst_vehicle_migration_acf_pass_vehicle_pricing_vehicle_id( $valid, $value, $field, $input ) {
	if ( function_exists( 'bst_vehicle_migration_is_post_object_query_bypassed' ) && bst_vehicle_migration_is_post_object_query_bypassed() ) {
		return true;
	}
	return $valid;
}
add_filter( 'acf/validate_value/key=field_67f9e40b1c001', 'bst_vehicle_migration_acf_pass_vehicle_pricing_vehicle_id', 5, 4 );
