<?php
/**
 * Tour vehicle_pricing → Vehicle CPT migration and release “re-link from labels”.
 *
 * **Requirements (unchanged):** {@see BST_Plugin::acf_tour_vehicle_pricing_cpt_query} must not restrict `post__in` while
 * migration runs ({@see bst_vehicle_migration_push_post_object_query_bypass()}), and
 * {@see bst_vehicle_migration_acf_pass_vehicle_pricing_vehicle_id} must allow validate_value for `field_67f9e40b1c001`.
 *
 * Vehicle identity: one CPT per distinct **canonical name**; {@see bst_vehicle_exact_text_key()} strips parentheticals
 * (upgrade/class), HTML, and collapses whitespace so it matches `post_title` the same way (no fuzzy/compact keys).
 *
 * **Re-link / save strategy:** Load {@see get_field()} with `false` (raw keys, same as DB), assign ids with
 * {@see bst_vehicle_migration_assign_nested_vehicle_id_mirrored()}, then {@see update_field()} using the repeater field key.
 * When {@see acf_get_setting()} `row_index_offset` is 1 (default), bulk repeater values must use **1-based** row keys to
 * match ACF; raw {@see get_field()} uses 0-based PHP arrays — {@see bst_vehicle_migration_reindex_repeater_value_for_acf()} fixes that.
 * Fallback: {@see update_sub_field()} per cell with offset-adjusted row numbers.
 * Formatted `get_field(..., true)` mixes named keys and often makes nested `update_field()` fail; a short
 * {@see bst_vehicle_migration_with_acf_validate_bypass()} around the save avoids unrelated ACF validation blocking migration.
 * Direct postmeta writes do not match every ACF storage variant; a single repeater save keeps labels and CPT ids aligned.
 * Logs: `[BST release cleanup]`.
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
/** @var string Package row number subfield (ACF). */
const BST_VEHICLE_PKG_CLASS_KEY        = 'field_67ad575f16fd4';
/** @var string Package row price addition subfield (ACF). */
const BST_VEHICLE_PKG_PRICE_ADD_KEY    = 'field_67b89cf27ef01';

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

/**
 * While migration writes vehicle_pricing, allow all ACF field validation to pass (programmatic save, not admin UI).
 *
 * @param callable $callback Invoked between add/remove filter.
 * @return mixed Return value of $callback.
 */
function bst_vehicle_migration_with_acf_validate_bypass( $callback ) {
	if ( ! is_callable( $callback ) ) {
		return null;
	}
	add_filter( 'acf/validate_value', 'bst_vehicle_migration_filter_acf_validate_value_always_ok', 99999, 4 );
	try {
		return call_user_func( $callback );
	} finally {
		remove_filter( 'acf/validate_value', 'bst_vehicle_migration_filter_acf_validate_value_always_ok', 99999 );
	}
}

/**
 * @param bool        $valid  Prior validity.
 * @param mixed       $value  Submitted value.
 * @param array       $field  Field settings.
 * @param string|bool $input  Input name.
 * @return bool
 */
function bst_vehicle_migration_filter_acf_validate_value_always_ok( $valid, $value, $field, $input ) {
	return true;
}

/**
 * Empty string on ACF number subfields often makes acf_update_value fail; normalize to null.
 *
 * @param array $pricing Raw vehicle_pricing repeater (by reference).
 */
function bst_vehicle_migration_sanitize_raw_pricing_numbers( array &$pricing ) {
	foreach ( $pricing as &$row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		foreach ( array( BST_VEHICLE_PKG_CLASS_KEY, 'class' ) as $k ) {
			if ( array_key_exists( $k, $row ) && '' === $row[ $k ] ) {
				$row[ $k ] = null;
			}
		}
		foreach ( array( BST_VEHICLE_PKG_PRICE_ADD_KEY, 'vehicle_price_addition' ) as $k ) {
			if ( array_key_exists( $k, $row ) && '' === $row[ $k ] ) {
				$row[ $k ] = null;
			}
		}
	}
	unset( $row );
}

/**
 * Build a field-key-only vehicle_pricing value. Mixed name keys + field_* keys for the same repeater often
 * make {@see update_field()} / {@see acf_update_value()} return false; ACF expects one consistent shape.
 *
 * @param array $pricing Raw repeater from {@see get_field()} false.
 * @return array
 */
function bst_vehicle_migration_normalize_pricing_field_keys( array $pricing ) {
	$out = array();
	foreach ( array_values( $pricing ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$class = null;
		if ( array_key_exists( BST_VEHICLE_PKG_CLASS_KEY, $row ) ) {
			$class = $row[ BST_VEHICLE_PKG_CLASS_KEY ];
		} elseif ( array_key_exists( 'class', $row ) ) {
			$class = $row['class'];
		}
		$add = null;
		if ( array_key_exists( BST_VEHICLE_PKG_PRICE_ADD_KEY, $row ) ) {
			$add = $row[ BST_VEHICLE_PKG_PRICE_ADD_KEY ];
		} elseif ( array_key_exists( 'vehicle_price_addition', $row ) ) {
			$add = $row['vehicle_price_addition'];
		}
		$new = array();
		if ( null !== $class ) {
			$new[ BST_VEHICLE_PKG_CLASS_KEY ] = $class;
		}
		if ( null !== $add ) {
			$new[ BST_VEHICLE_PKG_PRICE_ADD_KEY ] = $add;
		}
		$nested_src = array();
		if ( ! empty( $row[ BST_VEHICLE_NESTED_REPEATER_KEY ] ) && is_array( $row[ BST_VEHICLE_NESTED_REPEATER_KEY ] ) ) {
			$nested_src = $row[ BST_VEHICLE_NESTED_REPEATER_KEY ];
		} elseif ( ! empty( $row['vehicles'] ) && is_array( $row['vehicles'] ) ) {
			$nested_src = $row['vehicles'];
		}
		$nested_out = array();
		foreach ( array_values( $nested_src ) as $vrow ) {
			if ( ! is_array( $vrow ) ) {
				continue;
			}
			$text = null;
			if ( array_key_exists( BST_VEHICLE_ROW_TEXT_KEY, $vrow ) ) {
				$text = $vrow[ BST_VEHICLE_ROW_TEXT_KEY ];
			} elseif ( array_key_exists( 'vehicle', $vrow ) ) {
				$text = $vrow['vehicle'];
			}
			$linked = isset( $vrow['vehicle_id'] ) ? $vrow['vehicle_id'] : ( isset( $vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] ) ? $vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] : 0 );
			if ( is_array( $linked ) && isset( $linked['ID'] ) ) {
				$linked = (int) $linked['ID'];
			} elseif ( is_object( $linked ) && isset( $linked->ID ) ) {
				$linked = (int) $linked->ID;
			} else {
				$linked = (int) $linked;
			}
			$nv = array();
			if ( null !== $text && '' !== (string) $text ) {
				$nv[ BST_VEHICLE_ROW_TEXT_KEY ] = $text;
			}
			// Post object has allow_null=1; false often fails programmatic saves — use null for empty.
			$nv[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] = $linked > 0 ? $linked : null;
			$arch_val = null;
			$arch_k = defined( 'BST_VEHICLE_VEHICLE_CHOICE_ARCHIVED_KEY' ) ? BST_VEHICLE_VEHICLE_CHOICE_ARCHIVED_KEY : 'field_68a1b2c3d4e5';
			if ( array_key_exists( $arch_k, $vrow ) ) {
				$arch_val = (int) (bool) $vrow[ $arch_k ];
			} elseif ( array_key_exists( 'vehicle_choice_archived', $vrow ) ) {
				$arch_val = (int) (bool) $vrow['vehicle_choice_archived'];
			}
			if ( null !== $arch_val ) {
				$nv[ $arch_k ] = $arch_val;
			}
			$nested_out[] = $nv;
		}
		$new[ BST_VEHICLE_NESTED_REPEATER_KEY ] = $nested_out;
		$out[]                                  = $new;
	}
	return $out;
}

/**
 * ACF repeater row numbers follow {@see acf_get_setting()} `row_index_offset` (default 1 = first row is 1).
 * {@see get_field(…, false)} returns 0-based PHP arrays; bulk {@see update_field()} / {@see acf_update_value()} expect
 * repeater value keys to match that offset for nested repeaters too.
 *
 * @param array $pricing Field-key-normalized repeater (see {@see bst_vehicle_migration_normalize_pricing_field_keys()}).
 * @return array
 */
function bst_vehicle_migration_reindex_repeater_value_for_acf( array $pricing ) {
	$off = function_exists( 'acf_get_setting' ) ? (int) acf_get_setting( 'row_index_offset' ) : 0;
	if ( 0 === $off ) {
		return $pricing;
	}
	$out = array();
	$oi  = $off;
	foreach ( array_values( $pricing ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$nkey = BST_VEHICLE_NESTED_REPEATER_KEY;
		if ( ! empty( $row[ $nkey ] ) && is_array( $row[ $nkey ] ) ) {
			$nested_new = array();
			$ji         = $off;
			foreach ( array_values( $row[ $nkey ] ) as $vrow ) {
				$nested_new[ $ji ] = $vrow;
				++$ji;
			}
			$row[ $nkey ] = $nested_new;
		}
		$out[ $oi ] = $row;
		++$oi;
	}
	return $out;
}

/**
 * Same rows as {@see bst_vehicle_migration_normalize_pricing_field_keys()} but ACF subfield names (class, vehicles, …).
 * Used as a fallback when field-key-only bulk {@see update_field()} fails (mirrors limited_vehicles save strategy).
 * Preserves outer/nested array keys (needed when rows are 1-based).
 *
 * @param array $pricing Field-key-normalized repeater (see normalize_pricing_field_keys output).
 * @return array
 */
function bst_vehicle_migration_field_key_pricing_to_named( array $pricing ) {
	$out = array();
	foreach ( $pricing as $ikey => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$nr = array();
		if ( array_key_exists( BST_VEHICLE_PKG_CLASS_KEY, $row ) ) {
			$nr['class'] = $row[ BST_VEHICLE_PKG_CLASS_KEY ];
		}
		if ( array_key_exists( BST_VEHICLE_PKG_PRICE_ADD_KEY, $row ) ) {
			$nr['vehicle_price_addition'] = $row[ BST_VEHICLE_PKG_PRICE_ADD_KEY ];
		}
		$nested_src = ( ! empty( $row[ BST_VEHICLE_NESTED_REPEATER_KEY ] ) && is_array( $row[ BST_VEHICLE_NESTED_REPEATER_KEY ] ) )
			? $row[ BST_VEHICLE_NESTED_REPEATER_KEY ]
			: array();
		$vehicles = array();
		foreach ( $nested_src as $jkey => $vrow ) {
			if ( ! is_array( $vrow ) ) {
				continue;
			}
			$vr = array();
			if ( array_key_exists( BST_VEHICLE_ROW_TEXT_KEY, $vrow ) && '' !== (string) $vrow[ BST_VEHICLE_ROW_TEXT_KEY ] ) {
				$vr['vehicle'] = $vrow[ BST_VEHICLE_ROW_TEXT_KEY ];
			}
			$vid = bst_vehicle_migration_row_linked_post_id( $vrow );
			$vr['vehicle_id'] = $vid > 0 ? $vid : null;
			$arch_k = defined( 'BST_VEHICLE_VEHICLE_CHOICE_ARCHIVED_KEY' ) ? BST_VEHICLE_VEHICLE_CHOICE_ARCHIVED_KEY : 'field_68a1b2c3d4e5';
			if ( array_key_exists( $arch_k, $vrow ) ) {
				$vr['vehicle_choice_archived'] = (int) (bool) $vrow[ $arch_k ];
			} elseif ( array_key_exists( 'vehicle_choice_archived', $vrow ) ) {
				$vr['vehicle_choice_archived'] = (int) (bool) $vrow['vehicle_choice_archived'];
			}
			$vehicles[ $jkey ] = $vr;
		}
		$nr['vehicles'] = $vehicles;
		$out[ $ikey ]   = $nr;
	}
	return $out;
}

/**
 * Resolve the vehicle_pricing repeater field array for ACF.
 * Note: {@see acf_get_field()} second argument is the local-json flag, not a post ID — do not pass tour id.
 * Calling {@see acf_get_field_groups()} for the tour primes synced JSON fields before lookup.
 *
 * @param int $tour_id Tour post ID.
 * @return array|null Field array or null.
 */
function bst_vehicle_migration_get_vehicle_pricing_field_object( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( ! function_exists( 'acf_get_field' ) ) {
		return null;
	}
	$try = function () {
		$f = acf_get_field( BST_VEHICLE_PRICING_REPEATER_KEY );
		if ( is_array( $f ) && ! empty( $f['key'] ) ) {
			return $f;
		}
		$f = acf_get_field( 'vehicle_pricing' );
		if ( is_array( $f ) && ! empty( $f['key'] ) ) {
			return $f;
		}
		return null;
	};
	$f = $try();
	if ( $f ) {
		return $f;
	}
	if ( $tour_id > 0 && function_exists( 'acf_get_field_groups' ) ) {
		acf_get_field_groups( array( 'post_id' => $tour_id ) );
	}
	return $try();
}

/**
 * Persist vehicle_pricing: try {@see update_field()} variants, then {@see acf_update_value()} with a resolved field object.
 *
 * @param int   $tour_id Tour post ID.
 * @param array $pricing Raw repeater value.
 * @return array{0: bool, 1: mixed, 2: mixed} [ ok, method_or_selector, post_id_used ]
 */
function bst_vehicle_migration_try_update_vehicle_pricing( $tour_id, array $pricing ) {
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 ) {
		return array( false, null, null );
	}
	if ( function_exists( 'acf_get_field_groups' ) ) {
		acf_get_field_groups( array( 'post_id' => $tour_id ) );
	}

	$last_results = array();
	$field_obj    = null;

	$attempt_save = function () use ( $tour_id, $pricing, &$last_results, &$field_obj ) {
		$reindexed        = bst_vehicle_migration_reindex_repeater_value_for_acf( $pricing );
		$named_reindexed  = bst_vehicle_migration_field_key_pricing_to_named( $reindexed );
		$named_pricing    = bst_vehicle_migration_field_key_pricing_to_named( $pricing );

		// Prefer offset-correct (usually 1-based) keys when row_index_offset is 1 — bulk save often fails with 0-based only.
		$variants = array(
			array( 'reindexed', $reindexed, $named_reindexed ),
			array( 'zero_based', $pricing, $named_pricing ),
		);
		$attempts = array();
		foreach ( $variants as $pair ) {
			$p = $pair[1];
			$n = $pair[2];
			$attempts[] = array( BST_VEHICLE_PRICING_REPEATER_KEY, $tour_id, $p );
			$attempts[] = array( BST_VEHICLE_PRICING_REPEATER_KEY, 'post_' . $tour_id, $p );
			$attempts[] = array( 'vehicle_pricing', $tour_id, $p );
			$attempts[] = array( 'vehicle_pricing', 'post_' . $tour_id, $p );
			$attempts[] = array( BST_VEHICLE_PRICING_REPEATER_KEY, $tour_id, $n );
			$attempts[] = array( BST_VEHICLE_PRICING_REPEATER_KEY, 'post_' . $tour_id, $n );
			$attempts[] = array( 'vehicle_pricing', $tour_id, $n );
			$attempts[] = array( 'vehicle_pricing', 'post_' . $tour_id, $n );
		}
		foreach ( $attempts as $triple ) {
			if ( ! function_exists( 'update_field' ) ) {
				break;
			}
			$selector = $triple[0];
			$post_ref = $triple[1];
			$value    = $triple[2];
			$r              = update_field( $selector, $value, $post_ref );
			$last_results[] = array( $selector, $post_ref, $r );
			if ( bst_vehicle_migration_acf_save_returned_ok( $r ) ) {
				return array( true, $selector, $post_ref );
			}
		}

		$field_obj = bst_vehicle_migration_get_vehicle_pricing_field_object( $tour_id );
		if ( $field_obj && function_exists( 'acf_update_value' ) ) {
			$post_ids = array( $tour_id, 'post_' . $tour_id );
			if ( function_exists( 'acf_get_valid_post_id' ) ) {
				$post_ids[] = acf_get_valid_post_id( $tour_id );
			}
			$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );
			foreach ( array( $reindexed, $pricing, $named_reindexed, $named_pricing ) as $val ) {
				foreach ( $post_ids as $pid ) {
					$r              = acf_update_value( $val, $pid, $field_obj );
					$last_results[] = array( 'acf_update_value', $pid, $r );
					if ( bst_vehicle_migration_acf_save_returned_ok( $r ) ) {
						return array( true, 'acf_update_value:' . ( isset( $field_obj['key'] ) ? (string) $field_obj['key'] : 'field' ), $pid );
					}
				}
			}
		}

		return array( false, null, null );
	};

	// Do not wrap in acf_disable_filters(): unlike limited-vehicles saves, disabling ACF filters here caused
	// update_field/acf_update_value to return false for tour vehicle_pricing while validation was already bypassed.
	list( $ok, $sel, $pid ) = $attempt_save();

	if ( $ok ) {
		return array( true, $sel, $pid );
	}

	// Caller may fall back to update_sub_field(); do not error_log here (avoids noise when subfield save succeeds).
	return array( false, null, null );
}

/**
 * Read linked Vehicle CPT id for one nested cell (same indexing as migration: package row key + 0-based nested index).
 *
 * @param int         $tour_id Tour post ID.
 * @param int|string  $pi      Package repeater key from {@see get_field()} false.
 * @param int         $pj      Nested row index (0-based).
 * @return int|null   Stored id, or null if missing.
 */
function bst_vehicle_migration_get_vehicle_id_at_pricing_cell( $tour_id, $pi, $pj ) {
	$tour_id = (int) $tour_id;
	$pj      = (int) $pj;
	if ( $tour_id <= 0 || $pj < 0 ) {
		return null;
	}
	bst_vehicle_migration_bust_caches_for_tour( $tour_id );
	$pricing = get_field( 'vehicle_pricing', $tour_id, false );
	if ( empty( $pricing ) || ! is_array( $pricing ) ) {
		return null;
	}
	if ( ! array_key_exists( $pi, $pricing ) || ! is_array( $pricing[ $pi ] ) ) {
		return null;
	}
	$nested = bst_vehicle_migration_get_nested_vehicle_rows( $pricing[ $pi ] );
	$nested = array_values( $nested );
	if ( ! isset( $nested[ $pj ] ) || ! is_array( $nested[ $pj ] ) ) {
		return null;
	}
	return (int) bst_vehicle_migration_row_linked_post_id( $nested[ $pj ] );
}

/**
 * Row numbers to try with {@see update_sub_field()} for nested repeaters (ACF/SCF vary: outer often 1-based, inner may be 0-based).
 *
 * @param int $pi 0-based package row index.
 * @param int $pj 0-based nested row index.
 * @param int $off {@see acf_get_setting()} row_index_offset.
 * @return array<int, array{0: int, 1: int}>
 */
function bst_vehicle_migration_subfield_row_index_candidates( $pi, $pj, $off ) {
	$pi  = (int) $pi;
	$pj  = (int) $pj;
	$off = (int) $off;
		$raw = array(
		array( $pi + $off, $pj + $off ),
		array( $pi + $off, $pj ),
		array( $pi + 1, $pj + 1 ),
		array( $pi, $pj ),
		array( $pi + 1, $pj ),
	);
	$seen = array();
	$out  = array();
	foreach ( $raw as $pair ) {
		if ( $pair[0] < 0 || $pair[1] < 0 ) {
			continue;
		}
		$k = $pair[0] . ':' . $pair[1];
		if ( isset( $seen[ $k ] ) ) {
			continue;
		}
		$seen[ $k ] = true;
		$out[]      = $pair;
	}
	return $out;
}

/**
 * Last-resort save: set each Vehicle (CPT) cell with {@see update_sub_field()} using row numbers that match
 * {@see acf_get_setting()} `row_index_offset` (outer/inner = meta_row/meta_subrow + offset).
 *
 * @param int   $tour_id       Tour post ID.
 * @param array $pending_cells Same shape as {@see bst_vehicle_migration_save_vehicle_pricing_for_tour()}.
 * @return array{0: bool, 1: mixed, 2: mixed} [ ok, method, post_ref ]
 */
function bst_vehicle_migration_try_update_vehicle_pricing_via_subfields( $tour_id, array $pending_cells ) {
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 || empty( $pending_cells ) || ! function_exists( 'update_sub_field' ) ) {
		return array( false, null, null );
	}
	$off = function_exists( 'acf_get_setting' ) ? (int) acf_get_setting( 'row_index_offset' ) : 0;

	foreach ( $pending_cells as $c ) {
		$vid = isset( $c['vehicle_id'] ) ? (int) $c['vehicle_id'] : 0;
		$pi  = isset( $c['meta_row'] ) ? $c['meta_row'] : null;
		$pj  = isset( $c['meta_subrow'] ) ? (int) $c['meta_subrow'] : -1;
		if ( $vid <= 0 || $pi === null || $pj < 0 ) {
			continue;
		}

		$already = bst_vehicle_migration_get_vehicle_id_at_pricing_cell( $tour_id, $pi, $pj );
		if ( $already === $vid ) {
			continue;
		}

		$selector_variants = function ( $outer, $inner ) {
			return array(
				array( BST_VEHICLE_PRICING_REPEATER_KEY, $outer, BST_VEHICLE_NESTED_REPEATER_KEY, $inner, BST_VEHICLE_ROW_POST_OBJECT_KEY ),
				array( 'vehicle_pricing', $outer, 'vehicles', $inner, 'vehicle_id' ),
			);
		};

		$post_refs = array( $tour_id, 'post_' . $tour_id );
		if ( function_exists( 'acf_get_valid_post_id' ) ) {
			$v = acf_get_valid_post_id( $tour_id );
			if ( $v ) {
				$post_refs[] = $v;
			}
		}
		$post_refs = array_values( array_unique( array_filter( $post_refs ) ) );

		$cell_ok   = false;
		$tried_log = array();
		$now       = null;
		foreach ( bst_vehicle_migration_subfield_row_index_candidates( (int) $pi, (int) $pj, $off ) as $pair ) {
			$outer = $pair[0];
			$inner = $pair[1];
			foreach ( $post_refs as $pref ) {
				foreach ( $selector_variants( $outer, $inner ) as $sel ) {
					$r = update_sub_field( $sel, $vid, $pref );
					if ( bst_vehicle_migration_acf_save_returned_ok( $r ) ) {
						$cell_ok = true;
						break 3;
					}
					$tried_log[] = array( $outer, $inner, $pref, $r );
				}
			}
		}

		if ( ! $cell_ok ) {
			$now = bst_vehicle_migration_get_vehicle_id_at_pricing_cell( $tour_id, $pi, $pj );
			if ( $now === $vid ) {
				$cell_ok = true;
			}
		}

		if ( ! $cell_ok ) {
			$last = end( $tried_log );
			bst_vehicle_migration_release_cleanup_log(
				sprintf(
					'update_sub_field FAILED tour=%d meta_pi=%s meta_pj=%d off=%d vid=%d already=%s now_after_tries=%s last_try=%s',
					$tour_id,
					is_scalar( $pi ) ? (string) $pi : '?',
					$pj,
					$off,
					$vid,
					null === $already ? 'null' : (string) $already,
					null === $now ? 'null' : (string) $now,
					is_array( $last ) ? wp_json_encode( $last ) : ''
				)
			);
			return array( false, 'update_sub_field', null );
		}
	}

	return array( true, 'update_sub_field', $tour_id );
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
		$vrow['vehicle_id']                        = null;
		$vrow[ BST_VEHICLE_ROW_POST_OBJECT_KEY ] = null;
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

function bst_vehicle_migration_acf_save_returned_ok( $r ) {
	return function_exists( 'bst_acf_save_returned_ok' ) ? bst_acf_save_returned_ok( $r ) : ( false !== $r );
}

/**
 * Verify saved vehicle_id cells using raw {@see get_field()} (same shape as migration save).
 *
 * @param int   $tour_id Tour ID.
 * @param array $cells   list of [ 'meta_row' => int|string, 'meta_subrow' => int, 'vehicle_id' => int ].
 * @return string[] Issue messages.
 */
function bst_vehicle_migration_verify_cells_get_field( $tour_id, array $cells ) {
	$tour_id = (int) $tour_id;
	$issues  = array();
	bst_vehicle_migration_bust_caches_for_tour( $tour_id );
	$pricing = get_field( 'vehicle_pricing', $tour_id, false );
	if ( empty( $pricing ) || ! is_array( $pricing ) ) {
		foreach ( $cells as $c ) {
			if ( ! empty( $c['vehicle_id'] ) ) {
				$issues[] = sprintf( 'tour %d: vehicle_pricing empty after save', $tour_id );
				break;
			}
		}
		return $issues;
	}
	foreach ( $cells as $c ) {
		$expected = isset( $c['vehicle_id'] ) ? (int) $c['vehicle_id'] : 0;
		$pi       = isset( $c['meta_row'] ) ? $c['meta_row'] : null;
		$pj       = isset( $c['meta_subrow'] ) ? (int) $c['meta_subrow'] : -1;
		if ( $expected <= 0 || $pi === null || $pj < 0 ) {
			continue;
		}
		if ( ! isset( $pricing[ $pi ] ) || ! is_array( $pricing[ $pi ] ) ) {
			$issues[] = sprintf( 'tour %d missing package row %s', $tour_id, is_scalar( $pi ) ? (string) $pi : '?' );
			continue;
		}
		$nested = bst_vehicle_migration_get_nested_vehicle_rows( $pricing[ $pi ] );
		$nested = array_values( $nested );
		if ( ! isset( $nested[ $pj ] ) || ! is_array( $nested[ $pj ] ) ) {
			$issues[] = sprintf( 'tour %d pi=%s pj=%d nested row missing', $tour_id, is_scalar( $pi ) ? (string) $pi : '?', $pj );
			continue;
		}
		$got = bst_vehicle_migration_row_linked_post_id( $nested[ $pj ] );
		if ( (int) $got === (int) $expected ) {
			continue;
		}
		$issues[] = sprintf( 'tour %d pi=%s pj=%d expected %d got %d', $tour_id, is_scalar( $pi ) ? (string) $pi : '?', $pj, $expected, $got );
	}
	return $issues;
}

function bst_vehicle_migration_find_existing_id( $base_name, array &$norm_to_id, array $vehicles_by_id ) {
	if ( ! function_exists( 'bst_vehicle_exact_text_key' ) ) {
		return 0;
	}
	$key = bst_vehicle_exact_text_key( $base_name );
	if ( '' === $key ) {
		return 0;
	}
	if ( isset( $norm_to_id[ $key ] ) ) {
		return (int) $norm_to_id[ $key ];
	}
	foreach ( $vehicles_by_id as $vid => $title ) {
		if ( $key === bst_vehicle_exact_text_key( $title ) ) {
			$norm_to_id[ $key ] = (int) $vid;
			return (int) $vid;
		}
	}
	return 0;
}

function bst_vehicle_migration_create_vehicle( $canonical_title, $tour_id, array &$norm_to_id, array &$vehicles_by_id ) {
	if ( ! function_exists( 'bst_vehicle_exact_text_key' ) ) {
		return 0;
	}
	$canonical_title = bst_vehicle_exact_text_key( (string) $canonical_title );
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
	$norm_to_id[ bst_vehicle_exact_text_key( $canonical_title ) ] = $id;
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
	$base = function_exists( 'bst_vehicle_exact_text_key' ) ? bst_vehicle_exact_text_key( $raw ) : trim( (string) $raw );
	if ( ! $ignore_linked ) {
		$linked = bst_vehicle_migration_row_linked_post_id( $vehicle_item );
		if ( $linked > 0 ) {
			$p = get_post( $linked );
			if ( $p && 'vehicle' === $p->post_type ) {
				$norm_to_id[ bst_vehicle_exact_text_key( $p->post_title ) ] = $linked;
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
 * @param array $pricing       Full `vehicle_pricing` array from {@see get_field()} (same tour as $tour_id).
 * @param array $pending_cells Cells to write [ 'meta_row' => repeater key, 'meta_subrow' => int, 'vehicle_id' => int ].
 * @param array $results       Log.
 * @return bool
 */
function bst_vehicle_migration_save_vehicle_pricing_for_tour( $tour_id, array $pricing, array $pending_cells, array &$results ) {
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 || empty( $pending_cells ) ) {
		return false;
	}
	foreach ( $pending_cells as $c ) {
		$vid = isset( $c['vehicle_id'] ) ? (int) $c['vehicle_id'] : 0;
		$pi  = isset( $c['meta_row'] ) ? $c['meta_row'] : null;
		$pj  = isset( $c['meta_subrow'] ) ? (int) $c['meta_subrow'] : -1;
		if ( $vid <= 0 || $pi === null || $pj < 0 ) {
			continue;
		}
		if ( ! isset( $pricing[ $pi ] ) || ! is_array( $pricing[ $pi ] ) ) {
			$results[] = sprintf( 'Error: tour %d missing vehicle_pricing row %s.', $tour_id, is_scalar( $pi ) ? (string) $pi : '' );
			return false;
		}
		$pk = bst_vehicle_migration_nested_repeater_primary_key( $pricing[ $pi ] );
		if ( ! $pk ) {
			$results[] = sprintf( 'Error: tour %d row %s has no nested vehicles key.', $tour_id, is_scalar( $pi ) ? (string) $pi : '' );
			return false;
		}
		bst_vehicle_migration_assign_nested_vehicle_id_mirrored( $pricing[ $pi ], $pj, $pk, $vid );
	}
	if ( ! function_exists( 'update_field' ) ) {
		$results[] = 'ACF update_field not available.';
		return false;
	}
	bst_vehicle_migration_sanitize_raw_pricing_numbers( $pricing );
	$pricing = bst_vehicle_migration_normalize_pricing_field_keys( $pricing );

	$db_matches_targets = true;
	foreach ( $pending_cells as $c ) {
		$evid = isset( $c['vehicle_id'] ) ? (int) $c['vehicle_id'] : 0;
		$epi  = isset( $c['meta_row'] ) ? $c['meta_row'] : null;
		$epj  = isset( $c['meta_subrow'] ) ? (int) $c['meta_subrow'] : -1;
		if ( $evid <= 0 || $epi === null || $epj < 0 ) {
			continue;
		}
		$stored = bst_vehicle_migration_get_vehicle_id_at_pricing_cell( $tour_id, $epi, $epj );
		if ( (int) $stored !== (int) $evid ) {
			$db_matches_targets = false;
			break;
		}
	}
	if ( $db_matches_targets ) {
		bst_vehicle_migration_bust_caches_for_tour( $tour_id );
		$mismatches = bst_vehicle_migration_verify_cells_get_field( $tour_id, $pending_cells );
		if ( ! empty( $mismatches ) ) {
			foreach ( $mismatches as $m ) {
				bst_vehicle_migration_release_cleanup_log( 'VERIFY_FAIL ' . $m );
			}
			$results[] = sprintf( 'Error: vehicle_id verification failed for tour %d (%s).', $tour_id, get_the_title( $tour_id ) );
			return false;
		}
		bst_vehicle_migration_release_cleanup_log(
			sprintf(
				'save_vehicle_pricing skipped write (database already matched label-resolved vehicle ids) tour=%d cells=%d',
				$tour_id,
				count( $pending_cells )
			)
		);
		return true;
	}

	list( $ok_save, $sel_used, $pid_used ) = bst_vehicle_migration_with_acf_validate_bypass(
		function () use ( $tour_id, $pricing ) {
			return bst_vehicle_migration_try_update_vehicle_pricing( $tour_id, $pricing );
		}
	);
	if ( ! $ok_save ) {
		list( $ok_save, $sel_used, $pid_used ) = bst_vehicle_migration_with_acf_validate_bypass(
			function () use ( $tour_id, $pending_cells ) {
				return bst_vehicle_migration_try_update_vehicle_pricing_via_subfields( $tour_id, $pending_cells );
			}
		);
	}
	if ( ! $ok_save ) {
		bst_vehicle_migration_release_cleanup_log(
			sprintf(
				'save_vehicle_pricing FAILED tour=%d (%s): bulk update_field/acf_update_value and update_sub_field both failed. Sync Tour field group from Local JSON if field definitions are stale.',
				$tour_id,
				get_the_title( $tour_id )
			)
		);
		$results[] = sprintf( 'Error: vehicle_pricing save failed for tour %d (%s). See error_log [BST release cleanup] save_vehicle_pricing FAILED.', $tour_id, get_the_title( $tour_id ) );
		return false;
	}
	bst_vehicle_migration_bust_caches_for_tour( $tour_id );
	$mismatches = bst_vehicle_migration_verify_cells_get_field( $tour_id, $pending_cells );
	if ( ! empty( $mismatches ) ) {
		foreach ( $mismatches as $m ) {
			bst_vehicle_migration_release_cleanup_log( 'VERIFY_FAIL ' . $m );
		}
		$results[] = sprintf( 'Error: vehicle_id verification failed for tour %d (%s).', $tour_id, get_the_title( $tour_id ) );
		return false;
	}
	bst_vehicle_migration_release_cleanup_log(
		sprintf(
			'save_vehicle_pricing complete tour=%d cells=%d selector=%s post_id=%s',
			$tour_id,
			count( $pending_cells ),
			is_scalar( $sel_used ) ? (string) $sel_used : '?',
			is_scalar( $pid_used ) ? (string) $pid_used : '?'
		)
	);
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
		$results[] = 'Force reset only: recreated Vehicle CPTs from tour labels (inventory); tour vehicle_pricing was not saved. Use the “Re-link tour pricing from labels” button (release cleanup on Tools) to write CPT links on tours.';
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
			if ( function_exists( 'bst_vehicle_exact_text_key' ) ) {
				$norm_to_id[ bst_vehicle_exact_text_key( $p->post_title ) ] = (int) $p->ID;
			}
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
			$pricing = get_field( 'vehicle_pricing', $tid, false );
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

			foreach ( $pricing as $pi => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$nested = bst_vehicle_migration_get_nested_vehicle_rows( $row );
				$nested = array_values( $nested );
				if ( empty( $nested ) ) {
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
			}

			if ( ! empty( $pending_cells ) ) {
				if ( function_exists( 'acf_get_field_groups' ) ) {
					acf_get_field_groups( array( 'post_id' => $tid ) );
				}
				$ok = bst_vehicle_migration_save_vehicle_pricing_for_tour( $tid, $pricing, $pending_cells, $results );
				if ( $ok ) {
					bst_vehicle_migration_bust_caches_for_tour( $tid );
					++$tours_updated;
				}
			}
		}

		$results[] = sprintf(
			'Tours with vehicle_pricing: %d; nested vehicle rows seen: %d; tours saved successfully: %d; repeater rows targeted for CPT link update (counted before save; only persist when save succeeds): %d',
			$tours_with_pricing,
			$rows_seen,
			$tours_updated,
			$rows_touched
		);
		if ( $tours_with_pricing > 0 && 0 === $rows_seen ) {
			$results[] = 'No nested vehicle rows found under vehicle_pricing (unexpected). Check ACF field names/sync.';
		}

		// Booking vehicle ids: whenever legacy label text is non-empty, resolve find-or-create from that string
		// (canonical key + Vehicle CPT). Re-runs replace wrong non-zero ids so migration does not depend on a
		// separate remap pass for bookings that already had stale ids. Empty label + force_reset clears id.
		$table     = $wpdb->prefix . 'bst_tour_booking';
		$bookings  = $wpdb->get_results( "SELECT id, tour_id, vehicle1, vehicle2, vehicle1_id, vehicle2_id FROM {$table}", ARRAY_A );
		$b_updated = 0;

		foreach ( $bookings as $b ) {
			$bid     = (int) $b['id'];
			$tour_id = (int) $b['tour_id'];
			$u       = array();

			$v1 = isset( $b['vehicle1'] ) ? (string) $b['vehicle1'] : '';
			$v2 = isset( $b['vehicle2'] ) ? (string) $b['vehicle2'] : '';

			$old1 = isset( $b['vehicle1_id'] ) ? (int) $b['vehicle1_id'] : 0;
			$old2 = isset( $b['vehicle2_id'] ) ? (int) $b['vehicle2_id'] : 0;

			$fill1 = $force_reset || ( $v1 !== '' );
			if ( $fill1 ) {
				if ( $v1 !== '' ) {
					$base = function_exists( 'bst_vehicle_exact_text_key' ) ? bst_vehicle_exact_text_key( $v1 ) : trim( (string) $v1 );
					$vid  = bst_vehicle_migration_find_existing_id( $base, $norm_to_id, $vehicles_by_id );
					if ( $vid <= 0 && $base !== '' ) {
						$vid = bst_vehicle_migration_create_vehicle( $base, $tour_id > 0 ? $tour_id : 0, $norm_to_id, $vehicles_by_id );
					}
					$new1 = $vid > 0 ? $vid : 0;
					if ( $new1 !== $old1 ) {
						$u['vehicle1_id'] = $new1;
					}
				} elseif ( $force_reset && $old1 !== 0 ) {
					$u['vehicle1_id'] = 0;
				}
			}

			$fill2 = $force_reset || ( $v2 !== '' );
			if ( $fill2 ) {
				if ( $v2 !== '' ) {
					$base = function_exists( 'bst_vehicle_exact_text_key' ) ? bst_vehicle_exact_text_key( $v2 ) : trim( (string) $v2 );
					$vid  = bst_vehicle_migration_find_existing_id( $base, $norm_to_id, $vehicles_by_id );
					if ( $vid <= 0 && $base !== '' ) {
						$vid = bst_vehicle_migration_create_vehicle( $base, $tour_id > 0 ? $tour_id : 0, $norm_to_id, $vehicles_by_id );
					}
					$new2 = $vid > 0 ? $vid : 0;
					if ( $new2 !== $old2 ) {
						$u['vehicle2_id'] = $new2;
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

		$results[] = sprintf( 'Bookings updated with vehicle IDs (migration pass): %d', $b_updated );

		// Force reset: second pass matches every booking’s vehicle1/vehicle2 *text* to current Vehicle CPT ids (same logic as Tools → remap).
		// Catches stale non-zero ids and any edge case the loop above missed after CPT ids change.
		if ( $force_reset && ! $repair_repeater_links_from_text && function_exists( 'bst_remap_booking_vehicle_ids_from_legacy_text' ) ) {
			$results[] = 'Force reset: running booking text → Vehicle CPT alignment (full remap pass).';
			foreach ( bst_remap_booking_vehicle_ids_from_legacy_text() as $remap_line ) {
				$results[] = $remap_line;
			}
		}

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
