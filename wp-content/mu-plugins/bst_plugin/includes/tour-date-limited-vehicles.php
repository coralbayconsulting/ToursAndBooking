<?php
/**
 * Tour Date → Limited vehicles: validation, stats display, admin script.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking statuses that must never count toward limited-vehicle sold (defense in depth vs allow-list).
 *
 * @return string[]
 */
function bst_booking_statuses_never_count_limited_vehicle() {
	return array(
		'Cancelled',
		'Waiting List',
	);
}

/**
 * Booking statuses that count toward per-vehicle usage (aligned with slot sync + reserved holds).
 * Excludes {@see bst_booking_statuses_never_count_limited_vehicle()} even if ever duplicated on the allow list.
 *
 * @return string[]
 */
function bst_booking_statuses_for_limited_vehicle_usage() {
	$statuses = array(
		'Pending',
		'Processing',
		'Payment Failed',
		'Booked',
		'Finalized',
		'Completed',
		'Reserved',
	);
	$never = bst_booking_statuses_never_count_limited_vehicle();
	return array_values( array_diff( $statuses, $never ) );
}

/**
 * How many booking “slots” use this vehicle on this tour date (vehicle1_id + vehicle2_id, same id counts twice).
 * Reserved for future automatic Sold sync; admin Sold is manual for now.
 *
 * @param int $tour_date_id Tour-date post ID.
 * @param int $vehicle_id   Vehicle CPT ID.
 * @return int
 */
function bst_limited_vehicle_sold_count( $tour_date_id, $vehicle_id ) {
	$tour_date_id = (int) $tour_date_id;
	$vehicle_id   = (int) $vehicle_id;
	if ( $tour_date_id <= 0 || $vehicle_id <= 0 ) {
		return 0;
	}
	global $wpdb;
	$table    = $wpdb->prefix . 'bst_tour_booking';
	$statuses = bst_booking_statuses_for_limited_vehicle_usage();
	$excluded = bst_booking_statuses_never_count_limited_vehicle();
	if ( empty( $statuses ) ) {
		return 0;
	}
	$in_ph     = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	$not_in_ph = implode( ',', array_fill( 0, count( $excluded ), '%s' ) );
	$sql       = "SELECT COALESCE(SUM(
			(CASE WHEN vehicle1_id = %d THEN 1 ELSE 0 END) +
			(CASE WHEN vehicle2_id = %d THEN 1 ELSE 0 END)
		), 0) FROM {$table}
		WHERE tour_date_id = %d AND booking_status IN ({$in_ph}) AND booking_status NOT IN ({$not_in_ph})";
	$prepare_args = array_merge( array( $vehicle_id, $vehicle_id, $tour_date_id ), $statuses, $excluded );
	$sql          = $wpdb->prepare( $sql, $prepare_args );
	return (int) $wpdb->get_var( $sql );
}

/**
 * Remaining limited-vehicle slots from tour-date repeater (manual Sold field), or null if unlimited.
 *
 * @param int $tour_date_id Tour-date post ID.
 * @param int $vehicle_id   Vehicle CPT ID.
 * @return int|null        Zero or more when limited; null when not in the limited list.
 */
function bst_limited_vehicle_slots_remaining( $tour_date_id, $vehicle_id ) {
	$tour_date_id = (int) $tour_date_id;
	$vehicle_id   = (int) $vehicle_id;
	if ( $tour_date_id <= 0 || $vehicle_id <= 0 ) {
		return null;
	}
	$rows = get_field( 'limited_vehicles', $tour_date_id, false );
	if ( ! is_array( $rows ) ) {
		return null;
	}
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$vid = bst_lv_coerce_vehicle_post_id( bst_lv_repeater_sub_value( $row, 'limited_vehicle', 'field_696e8b1a0a002' ) );
		if ( $vid !== $vehicle_id ) {
			continue;
		}
		$max  = (int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_max', 'field_696e8b1a0a003' );
		$sold = (int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_sold', 'field_696e8b1a0a004' );
		if ( $max <= 0 ) {
			return null;
		}
		return max( 0, $max - $sold );
	}
	return null;
}

/**
 * Extract limited_vehicles repeater row key from ACF subfield prefix (acf[field_XXX][rowKey][field_YYY]).
 *
 * @param string $prefix Subfield input prefix.
 * @return string|null
 */
function bst_limited_vehicles_row_key_from_prefix( $prefix ) {
	$prefix = (string) $prefix;
	if ( preg_match( '/\[field_696e8b1a0a001\]\[([^\]]+)\]/', $prefix, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '/\[limited_vehicles\]\[([^\]]+)\]/', $prefix, $m ) ) {
		return $m[1];
	}
	return null;
}

/**
 * @param mixed  $row     Repeater row array.
 * @param string $name    Subfield name.
 * @param string $acf_key Subfield key (field_…).
 * @return mixed|null
 */
function bst_lv_repeater_sub_value( $row, $name, $acf_key ) {
	if ( ! is_array( $row ) ) {
		return null;
	}
	if ( array_key_exists( $name, $row ) ) {
		return $row[ $name ];
	}
	if ( $acf_key && array_key_exists( $acf_key, $row ) ) {
		return $row[ $acf_key ];
	}
	return null;
}

/**
 * @param mixed $v Raw vehicle subfield value.
 * @return int
 */
function bst_lv_coerce_vehicle_post_id( $v ) {
	if ( empty( $v ) && 0 !== $v && '0' !== $v ) {
		return 0;
	}
	if ( is_numeric( $v ) ) {
		return (int) $v;
	}
	if ( is_object( $v ) && isset( $v->ID ) ) {
		return (int) $v->ID;
	}
	if ( is_array( $v ) ) {
		if ( isset( $v['ID'] ) ) {
			return (int) $v['ID'];
		}
		if ( isset( $v['id'] ) ) {
			return (int) $v['id'];
		}
	}
	return 0;
}

/**
 * Plain text for admin notices: strip tags and decode entities (e.g. &#038; → &).
 *
 * @param string $text Raw, HTML, or entity-encoded text.
 * @return string
 */
function bst_plain_text_for_notice( $text ) {
	$text = wp_strip_all_tags( (string) $text, true );
	if ( function_exists( 'html_entity_decode' ) ) {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Resolve repeater row for a subfield (prefix, single-row shortcut, or ACF loop index).
 *
 * @param array $field ACF field array.
 * @return array|null
 */
function bst_limited_vehicles_resolve_row_for_subfield( $field ) {
	global $post;
	if ( ! $post || 'tour-date' !== $post->post_type || ! $post->ID ) {
		return null;
	}
	$rows = get_field( 'limited_vehicles', $post->ID, false );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return null;
	}

	$prefix  = isset( $field['prefix'] ) ? (string) $field['prefix'] : '';
	$row_key = bst_limited_vehicles_row_key_from_prefix( $prefix );
	if ( null !== $row_key && isset( $rows[ $row_key ] ) ) {
		return $rows[ $row_key ];
	}

	if ( 1 === count( $rows ) ) {
		return reset( $rows );
	}

	if ( function_exists( 'acf_get_loop' ) ) {
		$state = acf_get_loop( 'active' );
		if ( $state ) {
			$parent_key = '';
			$i          = -1;
			if ( is_array( $state ) ) {
				$parent_key = isset( $state['field']['key'] ) ? (string) $state['field']['key'] : '';
				$i          = isset( $state['i'] ) ? (int) $state['i'] : -1;
			} elseif ( is_object( $state ) ) {
				$fld = isset( $state->field ) ? $state->field : null;
				if ( is_array( $fld ) && isset( $fld['key'] ) ) {
					$parent_key = (string) $fld['key'];
				} elseif ( is_object( $fld ) && isset( $fld->key ) ) {
					$parent_key = (string) $fld->key;
				}
				$i = isset( $state->i ) ? (int) $state->i : -1;
			}
			if ( 'field_696e8b1a0a001' === $parent_key && $i >= 0 ) {
				$ordered = array_values( $rows );
				if ( isset( $ordered[ $i ] ) ) {
					return $ordered[ $i ];
				}
			}
		}
	}

	return null;
}

/**
 * Vehicle post IDs already assigned to other limited-vehicle rows (saved meta). Used to narrow the picker on new rows.
 *
 * @param int         $tour_date_id   Tour-date post ID.
 * @param string|null $except_row_key Repeater row layout key to skip (current row), or null to skip none.
 * @return int[]
 */
function bst_limited_vehicle_ids_assigned_other_rows( $tour_date_id, $except_row_key = null ) {
	$tour_date_id = (int) $tour_date_id;
	if ( $tour_date_id <= 0 ) {
		return array();
	}
	$rows = get_field( 'limited_vehicles', $tour_date_id, false );
	if ( ! is_array( $rows ) ) {
		return array();
	}
	$ids = array();
	foreach ( $rows as $rk => $row ) {
		if ( null !== $except_row_key && (string) $rk === (string) $except_row_key ) {
			continue;
		}
		$vid = bst_lv_coerce_vehicle_post_id( bst_lv_repeater_sub_value( $row, 'limited_vehicle', 'field_696e8b1a0a002' ) );
		if ( $vid > 0 ) {
			$ids[] = $vid;
		}
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Disable vehicle picker once this row is stored with a vehicle so usage stats stay tied to the same CPT.
 *
 * @param array $field ACF field.
 * @return array
 */
function bst_prepare_limited_vehicle_lock_if_persisted( $field ) {
	global $post;
	if ( ! $post || 'tour-date' !== $post->post_type || ! $post->ID ) {
		return $field;
	}
	$prefix  = isset( $field['prefix'] ) ? (string) $field['prefix'] : '';
	$row_key = bst_limited_vehicles_row_key_from_prefix( $prefix );
	if ( null === $row_key || 'acfcloneindex' === $row_key ) {
		return $field;
	}
	$rows = get_field( 'limited_vehicles', $post->ID, false );
	if ( ! is_array( $rows ) || ! isset( $rows[ $row_key ] ) ) {
		return $field;
	}
	$saved_vid = bst_lv_coerce_vehicle_post_id( bst_lv_repeater_sub_value( $rows[ $row_key ], 'limited_vehicle', 'field_696e8b1a0a002' ) );
	if ( $saved_vid <= 0 ) {
		return $field;
	}
	// Do not set disabled/readonly on the field object — ACF may omit the hidden input from POST and clear the value.
	$field['wrapper']['class'] = isset( $field['wrapper']['class'] ) ? $field['wrapper']['class'] : '';
	$field['wrapper']['class'] .= ' bst-lv-vehicle-locked';
	$field['instructions']     = __( 'Locked after save so Sold/Avail stay aligned with bookings. Remove this row and add another to use a different vehicle.', 'bst-plugin' );
	return $field;
}

/**
 * Block changing or clearing vehicle on a row that already had one saved.
 *
 * @param bool|string $valid Valid or error message.
 * @param mixed       $value Submitted vehicle ID.
 * @param array       $field ACF field.
 * @return bool|string
 */
function bst_validate_limited_vehicle_immutable_when_persisted( $valid, $value, $field, $_acf_input = '' ) {
	if ( true !== $valid ) {
		return $valid;
	}
	global $post;
	if ( ! $post || 'tour-date' !== $post->post_type || ! $post->ID ) {
		return $valid;
	}
	$prefix  = isset( $field['prefix'] ) ? (string) $field['prefix'] : '';
	$row_key = bst_limited_vehicles_row_key_from_prefix( $prefix );
	if ( null === $row_key || 'acfcloneindex' === $row_key ) {
		return $valid;
	}
	$rows = get_field( 'limited_vehicles', $post->ID, false );
	if ( ! is_array( $rows ) || ! isset( $rows[ $row_key ] ) ) {
		return $valid;
	}
	$old_vid = bst_lv_coerce_vehicle_post_id( bst_lv_repeater_sub_value( $rows[ $row_key ], 'limited_vehicle', 'field_696e8b1a0a002' ) );
	$new_vid = (int) $value;
	if ( $old_vid > 0 && $new_vid !== $old_vid ) {
		return __( 'This row’s vehicle cannot be changed after it is saved. Delete the row and add a new one if you need a different vehicle.', 'bst-plugin' );
	}
	return $valid;
}

/**
 * @param array $field ACF field array.
 * @return array|null Row data or null.
 */
function bst_limited_vehicles_row_from_field_prefix( $field ) {
	return bst_limited_vehicles_resolve_row_for_subfield( $field );
}

/**
 * @param array $field ACF field.
 * @return string HTML message body.
 */
function bst_limited_vehicle_format_avail_message( $field ) {
	$row = bst_limited_vehicles_resolve_row_for_subfield( $field );
	if ( ! $row ) {
		return '—';
	}
	$max = (int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_max', 'field_696e8b1a0a003' );
	if ( $max <= 0 ) {
		return '—';
	}
	$sold  = (int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_sold', 'field_696e8b1a0a004' );
	$avail = max( 0, $max - $sold );
	return '<span class="bst-lv-avail-num">' . esc_html( (string) $avail ) . '</span>';
}

/**
 * @param array $field ACF field array.
 */
function bst_prepare_limited_vehicle_avail_display_field( $field ) {
	if ( isset( $field['key'] ) && 'field_696e8b1a0a005' === $field['key'] ) {
		$field['message'] = bst_limited_vehicle_format_avail_message( $field );
	}
	return $field;
}

/**
 * @param bool|string $valid   Valid or error string.
 * @param mixed       $value   Repeater value.
 * @param array       $field   Field.
 * @param string      $input   Input name (unused).
 * @return bool|string
 */
function bst_validate_limited_vehicles_no_duplicate_vehicle( $valid, $value, $field, $input ) {
	if ( true !== $valid ) {
		return $valid;
	}
	if ( empty( $value ) || ! is_array( $value ) ) {
		return $valid;
	}
	$seen = array();
	foreach ( $value as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$vid = 0;
		if ( isset( $row['limited_vehicle'] ) ) {
			$vid = (int) $row['limited_vehicle'];
		} elseif ( isset( $row['field_696e8b1a0a002'] ) ) {
			$vid = (int) $row['field_696e8b1a0a002'];
		}
		if ( $vid <= 0 ) {
			continue;
		}
		if ( isset( $seen[ $vid ] ) ) {
			return __( 'Each vehicle can only appear once in Limited vehicles. Remove the duplicate row or choose a different vehicle.', 'bst-plugin' );
		}
		$seen[ $vid ] = true;
	}
	return $valid;
}

/**
 * Vehicles on the tour that are “Limited by default”, with suggested max = BST Vehicle Count (min 1).
 *
 * @param int $tour_id Tour post ID.
 * @return array[] Each item: vehicle_id (int), max (int), title (string) for admin Select2.
 */
function bst_tour_limited_by_default_vehicle_rows_for_date( $tour_id ) {
	$tour_id = (int) $tour_id;
	if ( $tour_id <= 0 ) {
		return array();
	}
	$ids = function_exists( 'bst_tour_pricing_vehicle_ids_resolved' )
		? bst_tour_pricing_vehicle_ids_resolved( $tour_id )
		: bst_tour_linked_vehicle_ids( $tour_id );
	$out = array();
	foreach ( $ids as $vid ) {
		if ( ! function_exists( 'bst_vehicle_usually_limited' ) || ! bst_vehicle_usually_limited( $vid ) ) {
			continue;
		}
		$count = function_exists( 'get_field' ) ? get_field( 'bst_vehicle_count', $vid ) : null;
		$max   = (int) $count;
		if ( $max < 1 ) {
			$max = 1;
		}
		$title = function_exists( 'bst_vehicle_display_title' ) ? bst_vehicle_display_title( $vid ) : get_the_title( $vid );
		if ( '' === trim( (string) $title ) ) {
			$title = '#' . (string) $vid;
		}
		$out[] = array(
			'vehicle_id' => $vid,
			'max'        => $max,
			'title'      => $title,
		);
	}
	return $out;
}

/**
 * Collapse repeater rows so each vehicle ID appears at most once (fixes duplicate-row data that fails ACF validation).
 *
 * @param array $rows Raw limited_vehicles repeater from get_field( …, false ).
 * @return array
 */
function bst_limited_vehicles_repeater_dedupe_by_vehicle_id( array $rows ) {
	$keep_keys = array();
	$merged    = array();
	foreach ( $rows as $rk => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$vid = bst_lv_coerce_vehicle_post_id( bst_lv_repeater_sub_value( $row, 'limited_vehicle', 'field_696e8b1a0a002' ) );
		if ( $vid <= 0 ) {
			$merged[ $rk ] = $row;
			continue;
		}
		if ( ! isset( $keep_keys[ $vid ] ) ) {
			$keep_keys[ $vid ] = $rk;
			$merged[ $rk ]     = $row;
			continue;
		}
		$keeper = $keep_keys[ $vid ];
		$base   = isset( $merged[ $keeper ] ) && is_array( $merged[ $keeper ] ) ? $merged[ $keeper ] : array();
		$mx     = max(
			(int) bst_lv_repeater_sub_value( $base, 'limited_vehicle_max', 'field_696e8b1a0a003' ),
			(int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_max', 'field_696e8b1a0a003' )
		);
		$sd     = max(
			(int) bst_lv_repeater_sub_value( $base, 'limited_vehicle_sold', 'field_696e8b1a0a004' ),
			(int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_sold', 'field_696e8b1a0a004' )
		);
		$merged[ $keeper ] = array_merge( $base, $row, array(
			'limited_vehicle'      => $vid,
			'field_696e8b1a0a002'  => $vid,
			'limited_vehicle_max'  => $mx,
			'field_696e8b1a0a003'  => $mx,
			'limited_vehicle_sold' => $sd,
			'field_696e8b1a0a004'  => $sd,
		) );
	}
	return $merged;
}

/**
 * Build a clean repeater value ACF accepts: numeric rows only, storable subfields by field key (drops empty placeholder rows).
 *
 * @param array $rows Raw limited_vehicles from get_field( …, false ) or merged arrays.
 * @return array<int, array<string, int>>
 */
function bst_limited_vehicles_sanitize_rows_for_save( array $rows ) {
	$out = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$vid = bst_lv_coerce_vehicle_post_id( bst_lv_repeater_sub_value( $row, 'limited_vehicle', 'field_696e8b1a0a002' ) );
		if ( $vid <= 0 ) {
			continue;
		}
		$max  = (int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_max', 'field_696e8b1a0a003' );
		$sold = (int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_sold', 'field_696e8b1a0a004' );
		if ( $max < 1 ) {
			$max = 1;
		}
		if ( $sold < 0 ) {
			$sold = 0;
		}
		$out[] = array(
			'field_696e8b1a0a002' => $vid,
			'field_696e8b1a0a003' => $max,
			'field_696e8b1a0a004' => $sold,
		);
	}
	return $out;
}

/**
 * ACF update_field() / acf_update_value() may return null on success; treat only false as failure.
 *
 * @param mixed $result Value returned by ACF.
 * @return bool
 */
function bst_acf_save_returned_ok( $result ) {
	return false !== $result;
}

/**
 * Same data as bst_limited_vehicles_sanitize_rows_for_save() but with subfield names (some ACF paths expect this).
 *
 * @param array<int, array<string, int>> $normalized Keyed subfields.
 * @return array<int, array<string, int>>
 */
function bst_limited_vehicles_normalized_to_named_rows( array $normalized ) {
	$named = array();
	foreach ( $normalized as $r ) {
		$named[] = array(
			'limited_vehicle'      => (int) $r['field_696e8b1a0a002'],
			'limited_vehicle_max'  => (int) $r['field_696e8b1a0a003'],
			'limited_vehicle_sold' => (int) $r['field_696e8b1a0a004'],
		);
	}
	return $named;
}

/**
 * Replace repeater by clearing then add_row() each line (fallback when update_field bulk save fails).
 *
 * @param int   $post_id    Tour-date post ID.
 * @param array $normalized From bst_limited_vehicles_sanitize_rows_for_save().
 * @return bool
 */
function bst_limited_vehicles_save_repeater_via_add_row( $post_id, array $normalized ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || ! function_exists( 'update_field' ) || ! function_exists( 'add_row' ) || ! function_exists( 'get_field' ) ) {
		return false;
	}

	$backup = get_field( 'limited_vehicles', $post_id, false );
	if ( ! is_array( $backup ) ) {
		$backup = array();
	}

	update_field( 'field_696e8b1a0a001', array(), $post_id );
	update_field( 'limited_vehicles', array(), $post_id );

	foreach ( $normalized as $r ) {
		$row = array(
			'field_696e8b1a0a002' => (int) $r['field_696e8b1a0a002'],
			'field_696e8b1a0a003' => (int) $r['field_696e8b1a0a003'],
			'field_696e8b1a0a004' => (int) $r['field_696e8b1a0a004'],
		);
		$ar  = add_row( 'field_696e8b1a0a001', $row, $post_id );
		if ( false === $ar ) {
			$ar = add_row(
				'field_696e8b1a0a001',
				array(
					'limited_vehicle'      => $row['field_696e8b1a0a002'],
					'limited_vehicle_max'  => $row['field_696e8b1a0a003'],
					'limited_vehicle_sold' => $row['field_696e8b1a0a004'],
				),
				$post_id
			);
		}
		if ( false === $ar ) {
			if ( ! empty( $backup ) ) {
				update_field( 'field_696e8b1a0a001', $backup, $post_id );
			}
			return false;
		}
	}

	return true;
}

/**
 * Save limited_vehicles repeater from PHP without UI validation blocking updates.
 *
 * @param int   $post_id Tour-date post ID.
 * @param array $rows    Repeater value (same shape as get_field false).
 * @return bool
 */
function bst_limited_vehicles_update_field_programmatic( $post_id, array $rows ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return false;
	}

	$normalized = bst_limited_vehicles_sanitize_rows_for_save( $rows );

	remove_filter( 'acf/validate_value/key=field_696e8b1a0a001', 'bst_validate_limited_vehicles_no_duplicate_vehicle', 10 );
	remove_filter( 'acf/validate_value/key=field_696e8b1a0a002', 'bst_validate_limited_vehicle_immutable_when_persisted', 10 );

	$ok = false;
	try {
		if ( function_exists( 'acf_get_field_groups' ) ) {
			acf_get_field_groups( array( 'post_id' => $post_id ) );
		}

		if ( empty( $normalized ) ) {
			if ( function_exists( 'update_field' ) ) {
				$r = update_field( 'field_696e8b1a0a001', array(), $post_id );
				if ( ! bst_acf_save_returned_ok( $r ) ) {
					$r = update_field( 'limited_vehicles', array(), $post_id );
				}
				$ok = bst_acf_save_returned_ok( $r );
			}
		} else {
			$named = bst_limited_vehicles_normalized_to_named_rows( $normalized );

			if ( function_exists( 'update_field' ) ) {
				$r = update_field( 'field_696e8b1a0a001', $normalized, $post_id );
				$ok = bst_acf_save_returned_ok( $r );
				if ( ! $ok ) {
					$r = update_field( 'field_696e8b1a0a001', $normalized, 'post_' . $post_id );
					$ok = bst_acf_save_returned_ok( $r );
				}
				if ( ! $ok ) {
					$r = update_field( 'limited_vehicles', $normalized, $post_id );
					$ok = bst_acf_save_returned_ok( $r );
				}
				if ( ! $ok ) {
					$r = update_field( 'field_696e8b1a0a001', $named, $post_id );
					$ok = bst_acf_save_returned_ok( $r );
				}
				if ( ! $ok ) {
					$r = update_field( 'limited_vehicles', $named, $post_id );
					$ok = bst_acf_save_returned_ok( $r );
				}
			}

			if ( ! $ok && function_exists( 'acf_get_field' ) && function_exists( 'acf_update_value' ) ) {
				$field = acf_get_field( 'field_696e8b1a0a001' );
				if ( is_array( $field ) ) {
					$r = acf_update_value( $normalized, $post_id, $field );
					$ok = bst_acf_save_returned_ok( $r );
					if ( ! $ok ) {
						$r = acf_update_value( $named, $post_id, $field );
						$ok = bst_acf_save_returned_ok( $r );
					}
				}
			}

			if ( ! $ok && 'tour-date' === get_post_type( $post_id ) ) {
				$ok = bst_limited_vehicles_save_repeater_via_add_row( $post_id, $normalized );
			}
		}
	} finally {
		add_filter( 'acf/validate_value/key=field_696e8b1a0a001', 'bst_validate_limited_vehicles_no_duplicate_vehicle', 10, 4 );
		add_filter( 'acf/validate_value/key=field_696e8b1a0a002', 'bst_validate_limited_vehicle_immutable_when_persisted', 10, 4 );
	}

	return $ok;
}

/**
 * Add or update limited-vehicle rows from tour template: vehicle + max only (sold unchanged on existing rows; new rows get sold 0).
 *
 * @param int   $tour_date_id  Tour-date post ID.
 * @param array $template_rows From bst_tour_limited_by_default_vehicle_rows_for_date().
 * @return array{added: int, updated: int}|WP_Error
 */
function bst_apply_limited_vehicle_rows_create_only( $tour_date_id, array $template_rows ) {
	$tour_date_id = (int) $tour_date_id;
	if ( $tour_date_id <= 0 || empty( $template_rows ) || ! function_exists( 'get_field' ) ) {
		return array(
			'added'   => 0,
			'updated' => 0,
		);
	}
	if ( 'tour-date' !== get_post_type( $tour_date_id ) ) {
		return new WP_Error( 'bad_post', sprintf( 'Post %d is not a tour-date.', $tour_date_id ) );
	}

	$rows = get_field( 'limited_vehicles', $tour_date_id, false );
	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	$by_vid = array();
	foreach ( $rows as $rk => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$vid = bst_lv_coerce_vehicle_post_id( bst_lv_repeater_sub_value( $row, 'limited_vehicle', 'field_696e8b1a0a002' ) );
		if ( $vid > 0 ) {
			$by_vid[ $vid ] = $rk;
		}
	}

	$added   = 0;
	$updated = 0;

	foreach ( $template_rows as $item ) {
		$vid = isset( $item['vehicle_id'] ) ? (int) $item['vehicle_id'] : 0;
		if ( $vid <= 0 ) {
			continue;
		}
		$max = isset( $item['max'] ) ? (int) $item['max'] : 1;
		if ( $max < 1 ) {
			$max = 1;
		}

		$patch = array(
			'limited_vehicle'     => $vid,
			'field_696e8b1a0a002' => $vid,
			'limited_vehicle_max' => $max,
			'field_696e8b1a0a003' => $max,
		);

		if ( isset( $by_vid[ $vid ] ) ) {
			$rk          = $by_vid[ $vid ];
			$rows[ $rk ] = array_merge( is_array( $rows[ $rk ] ) ? $rows[ $rk ] : array(), $patch );
			++$updated;
		} else {
			$patch['limited_vehicle_sold'] = 0;
			$patch['field_696e8b1a0a004']  = 0;
			$rows[]                        = $patch;
			++$added;
		}
	}

	$rows = bst_limited_vehicles_repeater_dedupe_by_vehicle_id( $rows );

	$ok = bst_limited_vehicles_update_field_programmatic( $tour_date_id, $rows );
	if ( ! $ok ) {
		return new WP_Error(
			'acf_update',
			sprintf(
				/* translators: %d: tour-date post ID */
				__( 'Could not save limited_vehicles for tour-date %d.', 'bst-plugin' ),
				$tour_date_id
			)
		);
	}

	return array(
		'added'   => $added,
		'updated' => $updated,
	);
}

/**
 * Recalculate limited_vehicle_sold from bookings for every row on one tour-date; count rows whose sold value changed.
 *
 * @param int $tour_date_id Tour-date post ID.
 * @return array{rows_updated: int, oversold: string[]}|WP_Error
 */
function bst_sync_limited_vehicle_sold_for_tour_date( $tour_date_id ) {
	$tour_date_id = (int) $tour_date_id;
	if ( $tour_date_id <= 0 || ! function_exists( 'get_field' ) ) {
		return array(
			'rows_updated' => 0,
			'oversold'     => array(),
		);
	}
	if ( 'tour-date' !== get_post_type( $tour_date_id ) ) {
		return new WP_Error( 'bad_post', sprintf( 'Post %d is not a tour-date.', $tour_date_id ) );
	}

	$rows = get_field( 'limited_vehicles', $tour_date_id, false );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return array(
			'rows_updated' => 0,
			'oversold'     => array(),
		);
	}

	$rows_updated = 0;
	$oversold     = array();
	$dirty        = false;
	$td_title     = bst_plain_text_for_notice( get_post_field( 'post_title', $tour_date_id ) );

	foreach ( $rows as $rk => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$vid = bst_lv_coerce_vehicle_post_id( bst_lv_repeater_sub_value( $row, 'limited_vehicle', 'field_696e8b1a0a002' ) );
		if ( $vid <= 0 ) {
			continue;
		}

		$max      = (int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_max', 'field_696e8b1a0a003' );
		$old_sold = (int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_sold', 'field_696e8b1a0a004' );
		$new_sold = bst_limited_vehicle_sold_count( $tour_date_id, $vid );

		if ( (int) $old_sold !== (int) $new_sold ) {
			++$rows_updated;
			$dirty = true;
		}

		$rows[ $rk ]['limited_vehicle_sold'] = $new_sold;
		$rows[ $rk ]['field_696e8b1a0a004']  = $new_sold;

		if ( $max > 0 && $new_sold > $max ) {
			$vtitle = function_exists( 'bst_vehicle_display_title' ) ? bst_vehicle_display_title( $vid ) : get_post_field( 'post_title', $vid );
			$vtitle = bst_plain_text_for_notice( $vtitle );
			$oversold[] = sprintf(
				/* translators: 1: vehicle label, 2: tour date title, 3: post ID, 4: sold, 5: max */
				__( '%1$s — tour date “%2$s” (ID %3$d): sold %4$d, max %5$d', 'bst-plugin' ),
				$vtitle,
				$td_title,
				$tour_date_id,
				$new_sold,
				$max
			);
		}
	}

	if ( $dirty ) {
		$ok = bst_limited_vehicles_update_field_programmatic( $tour_date_id, $rows );
		if ( ! $ok ) {
			return new WP_Error(
				'acf_update',
				sprintf(
					/* translators: %d: tour-date post ID */
					__( 'Could not save limited_vehicles for tour-date %d.', 'bst-plugin' ),
					$tour_date_id
				)
			);
		}
	}

	return array(
		'rows_updated' => $rows_updated,
		'oversold'     => $oversold,
	);
}

/**
 * Batch: create limited-vehicle rows (vehicle + max) on child tour-dates from tours’ Limited by default pricing vehicles.
 *
 * @return array{lines: string[], error_count: int}
 */
function bst_migrate_limited_vehicles_create_only_batch() {
	$lines = array();

	$tours = get_posts(
		array(
			'post_type'              => 'tour',
			'post_status'            => function_exists( 'bst_post_statuses_for_admin_scan' ) ? bst_post_statuses_for_admin_scan( 'tour' ) : 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'update_post_meta_cache' => false,
		)
	);

	$templates = array();
	foreach ( $tours as $tid ) {
		$tid = (int) $tid;
		$tpl = bst_tour_limited_by_default_vehicle_rows_for_date( $tid );
		if ( ! empty( $tpl ) ) {
			$templates[ $tid ] = $tpl;
		}
	}

	if ( empty( $templates ) ) {
		$lines[] = __( 'No tours have vehicles on pricing marked Limited by default. Nothing to do.', 'bst-plugin' );
		return array(
			'lines'       => $lines,
			'error_count' => 0,
		);
	}

	$date_ids = get_posts(
		array(
			'post_type'              => 'tour-date',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'update_post_meta_cache' => false,
		)
	);

	$stats = array(
		'tours'   => count( $templates ),
		'dates'   => 0,
		'added'   => 0,
		'updated' => 0,
		'errors'  => 0,
	);

	foreach ( $date_ids as $td_id ) {
		$td_id  = (int) $td_id;
		$parent = bst_tour_id_for_tour_date( $td_id );
		if ( $parent <= 0 || ! isset( $templates[ $parent ] ) ) {
			continue;
		}

		$res = bst_apply_limited_vehicle_rows_create_only( $td_id, $templates[ $parent ] );
		if ( is_wp_error( $res ) ) {
			++$stats['errors'];
			if ( $stats['errors'] <= 25 ) {
				$lines[] = $res->get_error_message();
			}
			continue;
		}

		++$stats['dates'];
		$stats['added']   += $res['added'];
		$stats['updated'] += $res['updated'];
	}

	/* translators: 1: tour count, 2: tour-date count, 3: rows added, 4: rows updated (max only) */
	$lines[] = sprintf(
		__( 'Tours with Limited by default vehicles on pricing: %1$d. Tour dates processed: %2$d. Rows added: %3$d, rows updated (max only): %4$d. Run “Recalculate sold from bookings” separately to set Sold.', 'bst-plugin' ),
		$stats['tours'],
		$stats['dates'],
		$stats['added'],
		$stats['updated']
	);

	if ( $stats['errors'] > 0 ) {
		$lines[] = sprintf(
			/* translators: %d: error count */
			__( 'Failed tour-dates: %d (see messages above).', 'bst-plugin' ),
			$stats['errors']
		);
	}

	return array(
		'lines'       => $lines,
		'error_count' => (int) $stats['errors'],
	);
}

/**
 * Batch: set Sold on every limited-vehicle row from bookings; collect oversold (sold &gt; max).
 *
 * @return array{lines: string[], error_count: int, rows_updated: int, oversold: string[]}
 */
function bst_migrate_limited_vehicles_sync_sold_batch() {
	$lines        = array();
	$rows_updated = 0;
	$oversold     = array();
	$errors       = 0;

	$date_ids = get_posts(
		array(
			'post_type'              => 'tour-date',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'update_post_meta_cache' => false,
		)
	);

	foreach ( $date_ids as $td_id ) {
		$td_id = (int) $td_id;
		$res   = bst_sync_limited_vehicle_sold_for_tour_date( $td_id );
		if ( is_wp_error( $res ) ) {
			++$errors;
			if ( $errors <= 25 ) {
				$lines[] = $res->get_error_message();
			}
			continue;
		}
		$rows_updated += (int) $res['rows_updated'];
		if ( ! empty( $res['oversold'] ) && is_array( $res['oversold'] ) ) {
			$oversold = array_merge( $oversold, $res['oversold'] );
		}
	}

	if ( $errors > 0 ) {
		$lines[] = sprintf(
			/* translators: %d: error count */
			__( 'Failed tour-dates: %d (see messages above).', 'bst-plugin' ),
			$errors
		);
	}

	return array(
		'lines'        => $lines,
		'error_count'  => $errors,
		'rows_updated' => $rows_updated,
		'oversold'     => $oversold,
	);
}

/**
 * Booking IDs on a tour date that selected this vehicle (vehicle1_id or vehicle2_id).
 * Uses the same booking-status rules as {@see bst_limited_vehicle_sold_count()}.
 *
 * @param int $tour_date_id Tour-date post ID.
 * @param int $vehicle_id   Vehicle CPT ID.
 * @return int[]
 */
function bst_limited_vehicle_booking_ids_using_vehicle( $tour_date_id, $vehicle_id ) {
	$tour_date_id = (int) $tour_date_id;
	$vehicle_id   = (int) $vehicle_id;
	if ( $tour_date_id <= 0 || $vehicle_id <= 0 ) {
		return array();
	}
	global $wpdb;
	$table    = $wpdb->prefix . 'bst_tour_booking';
	$statuses = bst_booking_statuses_for_limited_vehicle_usage();
	$excluded = bst_booking_statuses_never_count_limited_vehicle();
	if ( empty( $statuses ) ) {
		return array();
	}
	$in_ph     = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	$not_in_ph = implode( ',', array_fill( 0, count( $excluded ), '%s' ) );
	$sql       = "SELECT id FROM {$table}
		WHERE tour_date_id = %d
		AND (vehicle1_id = %d OR vehicle2_id = %d)
		AND booking_status IN ({$in_ph})
		AND booking_status NOT IN ({$not_in_ph})
		ORDER BY id ASC";
	$prepare_args = array_merge( array( $tour_date_id, $vehicle_id, $vehicle_id ), $statuses, $excluded );
	$sql          = $wpdb->prepare( $sql, $prepare_args );
	$ids          = $wpdb->get_col( $sql );
	return array_map( 'intval', is_array( $ids ) ? $ids : array() );
}

/**
 * Limited-vehicle rows where calculated sold (from bookings) exceeds max — for admin dashboard.
 *
 * @return array<int, array{ tour_date_id: int, vehicle_id: int, max: int, sold: int, vehicle_title: string, tour_date_title: string, booking_ids: int[] }>
 */
function bst_limited_vehicle_dashboard_oversold_rows() {
	static $cached = null;
	if ( is_array( $cached ) ) {
		return $cached;
	}
	if ( ! function_exists( 'get_field' ) ) {
		$cached = array();
		return $cached;
	}
	$out = array();
	$tour_dates = get_posts(
		array(
			'post_type'              => 'tour-date',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'update_post_meta_cache' => false,
		)
	);
	foreach ( $tour_dates as $td_id ) {
		$td_id = (int) $td_id;
		$rows  = get_field( 'limited_vehicles', $td_id, false );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			continue;
		}
		$td_title = bst_plain_text_for_notice( get_post_field( 'post_title', $td_id ) );
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$vid = bst_lv_coerce_vehicle_post_id( bst_lv_repeater_sub_value( $row, 'limited_vehicle', 'field_696e8b1a0a002' ) );
			if ( $vid <= 0 ) {
				continue;
			}
			$max  = (int) bst_lv_repeater_sub_value( $row, 'limited_vehicle_max', 'field_696e8b1a0a003' );
			$sold = bst_limited_vehicle_sold_count( $td_id, $vid );
			if ( $max > 0 && $sold > $max ) {
				$vtitle = function_exists( 'bst_vehicle_display_title' ) ? bst_vehicle_display_title( $vid ) : get_post_field( 'post_title', $vid );
				$vtitle = bst_plain_text_for_notice( $vtitle );
				$out[]  = array(
					'tour_date_id'    => $td_id,
					'vehicle_id'      => $vid,
					'max'             => $max,
					'sold'            => $sold,
					'vehicle_title'   => $vtitle,
					'tour_date_title' => $td_title,
					'booking_ids'     => bst_limited_vehicle_booking_ids_using_vehicle( $td_id, $vid ),
				);
			}
		}
	}
	$cached = $out;
	return $cached;
}

/**
 * AJAX: list limited-by-default tour vehicles for filling the tour-date repeater (admin).
 */
function bst_ajax_limited_vehicles_from_tour() {
	check_ajax_referer( 'bst_lv_from_tour', 'nonce' );
	$tour_id = isset( $_POST['tour_id'] ) ? (int) $_POST['tour_id'] : 0;
	if ( $tour_id <= 0 || ! current_user_can( 'edit_post', $tour_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid tour or permission denied.', 'bst-plugin' ) ) );
	}
	if ( 'tour' !== get_post_type( $tour_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Not a tour.', 'bst-plugin' ) ) );
	}
	$items = bst_tour_limited_by_default_vehicle_rows_for_date( $tour_id );
	wp_send_json_success( array( 'items' => $items ) );
}

add_action( 'wp_ajax_bst_lv_limited_from_tour', 'bst_ajax_limited_vehicles_from_tour' );

/**
 * Enqueue script for live Avail. display on tour-date edit.
 *
 * @param string $hook Current admin hook.
 */
function bst_enqueue_tour_date_limited_vehicle_admin( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'tour-date' !== $screen->post_type ) {
		return;
	}
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	$js_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/js/tour-date-limited-vehicles.js';
	if ( ! file_exists( $js_path ) ) {
		return;
	}
	wp_enqueue_script(
		'bst-tour-date-limited-vehicles',
		content_url( 'mu-plugins/bst_plugin/js/tour-date-limited-vehicles.js' ),
		array( 'jquery', 'acf-input' ),
		filemtime( $js_path ),
		true
	);
	wp_localize_script(
		'bst-tour-date-limited-vehicles',
		'bstLimitedVehicles',
		array(
			'postId'  => $post_id,
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bst_lv_from_tour' ),
			'i18n'    => array(
				'addFromTour'     => __( 'Add limited vehicles from tour', 'bst-plugin' ),
				'addFromTourNote' => __( 'Before using this button, mark each relevant vehicle as “Limited by default” on its Vehicle edit screen (and ensure it is linked on the parent tour’s Vehicle Pricing). Only those vehicles are added.', 'bst-plugin' ),
				'selectTour'      => __( 'Select a parent Tour first.', 'bst-plugin' ),
				'noMatches'       => __( 'No vehicles on this tour are marked Limited by default.', 'bst-plugin' ),
				'allPresent'      => __( 'Those vehicles are already listed in Limited vehicles.', 'bst-plugin' ),
				'added'           => __( 'Added %d limited vehicle row(s).', 'bst-plugin' ),
				'requestError'    => __( 'Could not load tour vehicles. Try again.', 'bst-plugin' ),
			),
		)
	);
}

add_filter( 'acf/prepare_field/key=field_696e8b1a0a002', 'bst_prepare_limited_vehicle_lock_if_persisted' );
add_filter( 'acf/prepare_field/key=field_696e8b1a0a005', 'bst_prepare_limited_vehicle_avail_display_field' );
add_filter( 'acf/validate_value/key=field_696e8b1a0a002', 'bst_validate_limited_vehicle_immutable_when_persisted', 10, 4 );
add_filter( 'acf/validate_value/key=field_696e8b1a0a001', 'bst_validate_limited_vehicles_no_duplicate_vehicle', 10, 4 );
add_action( 'admin_enqueue_scripts', 'bst_enqueue_tour_date_limited_vehicle_admin', 25 );
