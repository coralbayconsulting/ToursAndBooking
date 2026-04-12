<?php
/**
 * Vehicle normalization helpers (Vehicle CPT + booking integration).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** ACF Tour → Vehicle Pricing → Vehicles → vehicle_choice_archived (sync with acf-json/group_6781b21fb4ca6.json). */
if ( ! defined( 'BST_VEHICLE_VEHICLE_CHOICE_ARCHIVED_KEY' ) ) {
    define( 'BST_VEHICLE_VEHICLE_CHOICE_ARCHIVED_KEY', 'field_68a1b2c3d4e5' );
}

/**
 * Whether this pricing row is archived (hidden from new bookings, kept for historical data).
 *
 * @param array $vehicle_item One row from vehicle_pricing → vehicles.
 * @return bool
 */
function bst_vehicle_pricing_vehicle_choice_is_archived( array $vehicle_item ) {
    if ( ! empty( $vehicle_item['vehicle_choice_archived'] ) ) {
        return true;
    }
    $k = BST_VEHICLE_VEHICLE_CHOICE_ARCHIVED_KEY;
    return array_key_exists( $k, $vehicle_item ) && ! empty( $vehicle_item[ $k ] );
}

/**
 * Post status argument for get_posts/WP_Query when scanning Tour (or related) CPT rows in admin tooling.
 * Default `any` includes publish, draft, pending, private, future, and custom statuses — not trash.
 *
 * @param string $post_type Context for the `bst_post_statuses_for_admin_scan` filter.
 * @return string|array Passed to `post_status`.
 */
function bst_post_statuses_for_admin_scan( $post_type = 'tour' ) {
    return apply_filters( 'bst_post_statuses_for_admin_scan', 'any', $post_type );
}

/**
 * Remove `(...)` segments from a plain-text label (upgrade prices, class labels, etc.).
 * Repeats until stable so nested or multiple segments are removed.
 *
 * @param string $s Plain text (not HTML).
 * @return string
 */
function bst_vehicle_strip_parenthetical_segments( $s ) {
    $s = (string) $s;
    if ( '' === $s ) {
        return '';
    }
    do {
        $prev = $s;
        $s    = preg_replace( '/\s*\([^()]*\)/u', '', $s );
    } while ( $s !== $prev );

    return $s;
}

/**
 * Strip a vehicle label to its meaningful name: same rules as {@see bst_vehicle_exact_text_key()}.
 *
 * @param string $vehicle_text Raw stored vehicle text (may include "(+€450)", "(Class 2)", etc.).
 * @return string
 */
function bst_vehicle_base_name_from_text( $vehicle_text ) {
    return bst_vehicle_exact_text_key( $vehicle_text );
}

/**
 * Normalize vehicle label for deduplication (lowercase, collapse whitespace).
 *
 * @param string $text Raw text.
 * @return string
 */
function bst_vehicle_normalize_key( $text ) {
    $s = wp_strip_all_tags( (string) $text );
    $s = strtolower( trim( preg_replace( '/\s+/u', ' ', $s ) ) );
    return $s;
}

/**
 * Compact key without spaces (minor spacing differences).
 *
 * @param string $text Raw text.
 * @return string
 */
function bst_vehicle_compact_key( $text ) {
    return preg_replace( '/\s+/u', '', bst_vehicle_normalize_key( $text ) );
}

/**
 * Canonical vehicle label for matching Vehicle `post_title`: strip HTML, remove all parenthetical
 * segments (upgrade price, class, etc.), trim ends, collapse internal whitespace to a single space.
 * Case- and spelling-sensitive; no fuzzy keys.
 *
 * @param string $text Raw label from repeater, booking column, or Vehicle post_title.
 * @return string
 */
function bst_vehicle_exact_text_key( $text ) {
    $s = wp_strip_all_tags( (string) $text );
    $s = bst_vehicle_strip_parenthetical_segments( $s );
    $s = trim( preg_replace( '/\s+/u', ' ', $s ) );

    return $s;
}

/**
 * Map a tour-type-code term slug + label to vehicle_type (car | motorcycle).
 *
 * Tours store Type Code via ACF taxonomy field `type_code` (taxonomy `tour-type-code`, object).
 * The `tour-type` CPT uses the same term so type pages can archive CPT rows while sharing one code.
 *
 * @param string $slug Term slug (authoritative for codes like driving, motorcycle).
 * @param string $name Term name (fallback if slug is ambiguous).
 * @return string 'car'|'motorcycle'
 */
function bst_vehicle_type_from_tour_type_code_term( $slug, $name ) {
    $slug_l = strtolower( (string) $slug );
    $name_l = strtolower( (string) $name );

    if ( '' !== $slug_l ) {
        if ( 'motorcycle' === $slug_l
            || false !== strpos( $slug_l, 'motorcycle' )
            || false !== strpos( $slug_l, 'motor-cycle' ) ) {
            return 'motorcycle';
        }
        // Explicit driving-style codes stay car (Miata / roadster tours use this pattern in UI).
        if ( 'driving' === $slug_l ) {
            return 'car';
        }
    }

    if ( false !== strpos( $name_l, 'motorcycle' ) || false !== strpos( $name_l, 'motor cycle' ) ) {
        return 'motorcycle';
    }

    return 'car';
}

/**
 * Vehicle ACF type from the tour's Type Code (taxonomy `tour-type-code` on the tour).
 *
 * @param int $tour_id Tour post ID.
 * @return string 'car'|'motorcycle'
 */
function bst_vehicle_type_for_tour_id( $tour_id ) {
    $tour_id = (int) $tour_id;
    if ( $tour_id <= 0 || ! function_exists( 'get_field' ) ) {
        return 'car';
    }

    $raw = get_field( 'type_code', $tour_id );
    $slug = '';
    $name = '';

    if ( $raw instanceof WP_Term ) {
        $slug = (string) $raw->slug;
        $name = (string) $raw->name;
    } elseif ( is_numeric( $raw ) ) {
        $t = get_term( (int) $raw );
        if ( $t && ! is_wp_error( $t ) ) {
            $slug = (string) $t->slug;
            $name = (string) $t->name;
        }
    } elseif ( is_array( $raw ) ) {
        if ( ! empty( $raw['term_id'] ) ) {
            $t = get_term( (int) $raw['term_id'] );
            if ( $t && ! is_wp_error( $t ) ) {
                $slug = (string) $t->slug;
                $name = (string) $t->name;
            }
        } else {
            $slug = isset( $raw['slug'] ) ? (string) $raw['slug'] : '';
            $name = isset( $raw['name'] ) ? (string) $raw['name'] : '';
        }
    } elseif ( is_object( $raw ) && isset( $raw->slug ) ) {
        $slug = (string) $raw->slug;
        $name = isset( $raw->name ) ? (string) $raw->name : '';
    }

    return bst_vehicle_type_from_tour_type_code_term( $slug, $name );
}

/**
 * Default ACF transmission for a new vehicle: motorcycles → manual, except “DCT” in title → automatic; cars from title keywords.
 *
 * @param string $post_title   Vehicle CPT title.
 * @param string $vehicle_type 'car' or 'motorcycle'.
 * @return string 'manual'|'automatic'|'na'
 */
function bst_vehicle_default_transmission( $post_title, $vehicle_type ) {
    $vehicle_type = (string) $vehicle_type;
    if ( 'motorcycle' === $vehicle_type ) {
        $t = strtolower( (string) $post_title );
        if ( false !== strpos( $t, 'dct' ) ) {
            return 'automatic';
        }
        return 'manual';
    }
    $t = strtolower( (string) $post_title );
    if ( false !== strpos( $t, 'automatic' ) || false !== strpos( $t, ' auto' ) || preg_match( '/\bauto\b/', $t ) ) {
        return 'automatic';
    }
    if ( false !== strpos( $t, 'manual' ) ) {
        return 'manual';
    }
    return 'na';
}

/**
 * Set transmission ACF when empty (new vehicle setup).
 *
 * @param int    $vehicle_post_id Vehicle post ID.
 * @param string $post_title      Title used for car keyword detection.
 * @param int    $tour_id         Tour ID to infer type if vehicle_type not set yet.
 */
function bst_vehicle_set_default_transmission_if_empty( $vehicle_post_id, $post_title, $tour_id = 0 ) {
    $vehicle_post_id = (int) $vehicle_post_id;
    if ( $vehicle_post_id <= 0 || ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
        return;
    }
    $current = get_field( 'transmission', $vehicle_post_id );
    if ( $current !== null && $current !== '' && false !== $current ) {
        return;
    }
    $vtype = get_field( 'vehicle_type', $vehicle_post_id );
    if ( ( $vtype === null || $vtype === '' || false === $vtype ) && $tour_id > 0 ) {
        $vtype = bst_vehicle_type_for_tour_id( $tour_id );
    }
    if ( $vtype === null || $vtype === '' ) {
        $vtype = 'car';
    }
    update_field( 'transmission', bst_vehicle_default_transmission( $post_title, (string) $vtype ), $vehicle_post_id );
}

/**
 * Lookup or create a Vehicle CPT post by exact title.
 *
 * @param string   $vehicle_name Base vehicle name (may include price suffix; stripped).
 * @param int|null $tour_id      Optional tour ID to set vehicle_type on create.
 * @return int Vehicle post ID (0 if invalid/failure).
 */
function bst_get_or_create_vehicle_id_by_name( $vehicle_name, $tour_id = null ) {
    $raw = trim( (string) $vehicle_name );
    $name = trim( sanitize_text_field( bst_vehicle_base_name_from_text( $raw ) ) );
    if ( '' === $name ) {
        return 0;
    }

    $existing = get_page_by_title( $name, OBJECT, 'vehicle' );
    if ( $existing && ! empty( $existing->ID ) ) {
        return (int) $existing->ID;
    }

    $id = wp_insert_post(
        array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => $name,
        ),
        true
    );

    if ( is_wp_error( $id ) ) {
        error_log( 'BST Vehicle: failed to create vehicle "' . $name . '": ' . $id->get_error_message() );
        return 0;
    }

    $id = (int) $id;
    if ( $id && function_exists( 'update_field' ) ) {
        $tid = null === $tour_id ? 0 : (int) $tour_id;
        if ( $tid > 0 ) {
            $vtype = get_field( 'vehicle_type', $id );
            if ( $vtype === null || $vtype === '' || false === $vtype ) {
                update_field( 'vehicle_type', bst_vehicle_type_for_tour_id( $tid ), $id );
            }
        }
        $unset = get_field( 'vehicle_active', $id );
        if ( $unset === null || $unset === '' ) {
            update_field( 'vehicle_active', 1, $id );
        }
        bst_vehicle_set_default_transmission_if_empty( $id, $name, $tid );
    }

    return $id;
}

/**
 * Whether a vehicle CPT may appear in booking dropdowns (ACF: vehicle_active).
 * Missing field or ACF absent: treat as available.
 *
 * @param int $vehicle_id Vehicle CPT ID.
 * @return bool
 */
function bst_vehicle_is_available_for_booking( $vehicle_id ) {
    $vehicle_id = (int) $vehicle_id;
    if ( $vehicle_id <= 0 ) {
        return true;
    }

    $post = get_post( $vehicle_id );
    if ( ! $post || 'vehicle' !== $post->post_type ) {
        return true;
    }

    if ( ! function_exists( 'get_field' ) ) {
        return true;
    }

    $active = get_field( 'vehicle_active', $vehicle_id );
    if ( $active === null || $active === '' ) {
        return true;
    }

    return (bool) $active;
}

/**
 * Vehicle CPT flag: “Limited by default” (hint only; per-date limits live on tour-date).
 *
 * @param int $vehicle_id Vehicle CPT ID.
 * @return bool
 */
function bst_vehicle_usually_limited( $vehicle_id ) {
    $vehicle_id = (int) $vehicle_id;
    if ( $vehicle_id <= 0 || ! function_exists( 'get_field' ) ) {
        return false;
    }
    return (bool) get_field( 'vehicle_usually_limited', $vehicle_id );
}

/**
 * Whether a vehicle’s vehicle_type matches the tour’s expected type (empty type on vehicle = allow).
 *
 * @param int    $vehicle_id Vehicle CPT ID.
 * @param string $vtype      Expected `car` or `motorcycle`.
 * @return bool
 */
function bst_vehicle_matches_tour_vehicle_type( $vehicle_id, $vtype ) {
    $vehicle_id = (int) $vehicle_id;
    if ( $vehicle_id <= 0 ) {
        return false;
    }
    if ( ! function_exists( 'get_field' ) ) {
        return true;
    }
    $vt = get_field( 'vehicle_type', $vehicle_id );
    if ( null === $vt || false === $vt || '' === $vt ) {
        return true;
    }
    return (string) $vt === (string) $vtype;
}

/**
 * Published vehicle IDs of a given type that are “available for assignment” (vehicle_active).
 *
 * @param string $vtype `car` or `motorcycle`.
 * @return int[]
 */
function bst_vehicle_ids_assignable_for_type( $vtype ) {
    $vtype = (string) $vtype;
    if ( '' === $vtype ) {
        $vtype = 'car';
    }
    $active_clause = array(
        'relation' => 'OR',
        array(
            'key'     => 'vehicle_active',
            'compare' => 'NOT EXISTS',
        ),
        array(
            'key'     => 'vehicle_active',
            'value'   => '1',
            'compare' => '=',
        ),
        array(
            'key'     => 'vehicle_active',
            'value'   => 1,
            'compare' => '=',
        ),
    );
    $q             = new WP_Query(
        array(
            'post_type'              => 'vehicle',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => array(
                'relation' => 'AND',
                array(
                    'key'     => 'vehicle_type',
                    'value'   => $vtype,
                    'compare' => '=',
                ),
                $active_clause,
            ),
        )
    );
    return array_map( 'intval', $q->posts ? $q->posts : array() );
}

/**
 * Vehicle IDs allowed in Tour → Vehicle pricing picker: assignable for tour type, plus any already linked on this tour (retired but still on pricing).
 *
 * @param int $tour_id Tour post ID.
 * @return int[]
 */
function bst_vehicle_ids_for_tour_pricing_picker( $tour_id ) {
    $tour_id = (int) $tour_id;
    if ( $tour_id <= 0 ) {
        return array();
    }
    $vtype      = bst_vehicle_type_for_tour_id( $tour_id );
    $vtype      = ( 'motorcycle' === $vtype ) ? 'motorcycle' : 'car';
    $assignable = bst_vehicle_ids_assignable_for_type( $vtype );
    $linked     = bst_tour_linked_vehicle_ids( $tour_id );
    $extra      = array();
    foreach ( $linked as $vid ) {
        if ( in_array( (int) $vid, $assignable, true ) ) {
            continue;
        }
        if ( bst_vehicle_matches_tour_vehicle_type( (int) $vid, $vtype ) ) {
            $extra[] = (int) $vid;
        }
    }
    return array_values( array_unique( array_merge( $assignable, $extra ) ) );
}

/**
 * Vehicle IDs saved on Limited vehicles repeater for a tour date (any assignment state).
 *
 * @param int $tour_date_id Tour-date post ID.
 * @return int[]
 */
function bst_limited_vehicle_ids_on_tour_date( $tour_date_id ) {
    $tour_date_id = (int) $tour_date_id;
    if ( $tour_date_id <= 0 || ! function_exists( 'get_field' ) ) {
        return array();
    }
    $rows = get_field( 'limited_vehicles', $tour_date_id, false );
    if ( ! is_array( $rows ) ) {
        return array();
    }
    $ids = array();
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) || empty( $row['limited_vehicle'] ) ) {
            continue;
        }
        $v = $row['limited_vehicle'];
        if ( is_object( $v ) && isset( $v->ID ) ) {
            $ids[] = (int) $v->ID;
        } elseif ( is_array( $v ) && isset( $v['ID'] ) ) {
            $ids[] = (int) $v['ID'];
        } else {
            $ids[] = (int) $v;
        }
    }
    return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
}

/**
 * Tour Date → Limited vehicles picker: assignable & on tour pricing, plus vehicles already saved on this date (e.g. retired but still on row).
 *
 * @param int $tour_date_id Tour-date post ID.
 * @param int $tour_id      Parent tour post ID.
 * @return int[]
 */
function bst_vehicle_ids_for_tour_date_limited_picker( $tour_date_id, $tour_id ) {
    $tour_date_id = (int) $tour_date_id;
    $tour_id      = (int) $tour_id;
    if ( $tour_id <= 0 ) {
        return array();
    }
    $vtype      = bst_vehicle_type_for_tour_id( $tour_id );
    $vtype      = ( 'motorcycle' === $vtype ) ? 'motorcycle' : 'car';
    $linked     = bst_tour_linked_vehicle_ids( $tour_id );
    $assignable = bst_vehicle_ids_assignable_for_type( $vtype );
    $on_tour    = array_values( array_intersect( $assignable, $linked ) );

    $saved  = bst_limited_vehicle_ids_on_tour_date( $tour_date_id );
    $legacy = array();
    foreach ( $saved as $vid ) {
        if ( in_array( $vid, $on_tour, true ) ) {
            continue;
        }
        if ( bst_vehicle_matches_tour_vehicle_type( $vid, $vtype ) ) {
            $legacy[] = $vid;
        }
    }
    return array_values( array_unique( array_merge( $on_tour, $legacy ) ) );
}

/**
 * Public/admin label for a vehicle: ACF listing_description when set, else post title.
 *
 * @param int $vehicle_id Vehicle CPT ID.
 * @return string
 */
function bst_vehicle_display_title( $vehicle_id ) {
    $vehicle_id = (int) $vehicle_id;
    if ( $vehicle_id <= 0 ) {
        return '';
    }

    $post = get_post( $vehicle_id );
    if ( ! $post || 'vehicle' !== $post->post_type ) {
        return '';
    }

    if ( function_exists( 'get_field' ) ) {
        $desc = get_field( 'listing_description', $vehicle_id );
        if ( is_string( $desc ) && '' !== trim( $desc ) ) {
            return trim( $desc );
        }
    }

    return $post->post_title;
}

/**
 * Preferred display name for a booking vehicle selection.
 * Uses vehicle ID when present; falls back to legacy stored text.
 *
 * @param object $booking Booking row.
 * @param int    $slot    1 or 2.
 * @return string
 */
function bst_booking_vehicle_display_text( $booking, $slot = 1 ) {
    $slot = (int) $slot;
    $id_field   = ( 2 === $slot ) ? 'vehicle2_id' : 'vehicle1_id';
    $text_field = ( 2 === $slot ) ? 'vehicle2' : 'vehicle1';

    $vid = isset( $booking->{$id_field} ) ? (int) $booking->{$id_field} : 0;
    if ( $vid > 0 ) {
        $p = get_post( $vid );
        if ( $p && 'vehicle' === $p->post_type ) {
            $label = bst_vehicle_display_title( $vid );
            return '' !== $label ? $label : $p->post_title;
        }
    }
    return isset( $booking->{$text_field} ) ? (string) $booking->{$text_field} : '';
}

/**
 * Distinct Vehicle CPT IDs linked from a tour's vehicle_pricing repeater (vehicle_id subfields).
 *
 * @param int $tour_id Tour post ID.
 * @return int[]
 */
function bst_tour_linked_vehicle_ids( $tour_id ) {
    $tour_id = (int) $tour_id;
    if ( $tour_id <= 0 || ! function_exists( 'get_field' ) ) {
        return array();
    }
    $ids     = array();
    // Raw (false): same structure as postmeta / migration; formatted (true) can omit or duplicate nested field_* rows.
    $pricing = get_field( 'vehicle_pricing', $tour_id, false );
    if ( empty( $pricing ) || ! is_array( $pricing ) ) {
        return array();
    }
    foreach ( $pricing as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $nested = array();
        if ( function_exists( 'bst_vehicle_migration_get_nested_vehicle_rows' ) ) {
            $nested = bst_vehicle_migration_get_nested_vehicle_rows( $row );
        } elseif ( ! empty( $row['vehicles'] ) && is_array( $row['vehicles'] ) ) {
            $nested = $row['vehicles'];
        }
        foreach ( $nested as $vrow ) {
            if ( ! is_array( $vrow ) ) {
                continue;
            }
            $linked_id = 0;
            if ( function_exists( 'bst_vehicle_migration_row_linked_post_id' ) ) {
                $linked_id = bst_vehicle_migration_row_linked_post_id( $vrow );
            } elseif ( isset( $vrow['vehicle_id'] ) ) {
                $x = $vrow['vehicle_id'];
                $linked_id = is_array( $x ) && isset( $x['ID'] ) ? (int) $x['ID'] : ( is_object( $x ) && isset( $x->ID ) ? (int) $x->ID : (int) $x );
            }
            if ( $linked_id > 0 ) {
                $ids[] = $linked_id;
            }
        }
    }
    $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
    sort( $ids, SORT_NUMERIC );
    return $ids;
}

/**
 * Cached maps for resolving tour repeater label text → Vehicle CPT (same rules as migration / “On Tours”).
 *
 * @return array{norm_to_id: array<string,int>, vehicles_by_id: array<int,string>} norm_to_id keys are {@see bst_vehicle_exact_text_key()} of post_title.
 */
function bst_vehicle_label_resolution_maps() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }
    $norm_to_id     = array();
    $vehicles_by_id = array();
    $posts          = get_posts(
        array(
            'post_type'              => 'vehicle',
            'post_status'            => 'any',
            'posts_per_page'         => -1,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'update_post_meta_cache' => false,
            'no_found_rows'          => true,
        )
    );
    foreach ( $posts as $vid ) {
        $vid   = (int) $vid;
        $title = get_the_title( $vid );
        $vehicles_by_id[ $vid ] = $title;
        $norm_to_id[ bst_vehicle_exact_text_key( $title ) ] = $vid;
    }
    $cache = array(
        'norm_to_id'     => $norm_to_id,
        'vehicles_by_id' => $vehicles_by_id,
    );
    return $cache;
}

/**
 * Resolve vehicle label text to Vehicle CPT id (read-only; exact string match vs migration).
 *
 * @param string $label_text     Same string as repeater `vehicle` field (after {@see bst_vehicle_exact_text_key()}).
 * @param array  $norm_to_id     Map exact text key → vehicle id (see {@see bst_vehicle_label_resolution_maps()}).
 * @param array  $vehicles_by_id Map vehicle id → title.
 * @return int 0 if none.
 */
function bst_vehicle_resolve_base_name_to_vehicle_id( $label_text, array $norm_to_id, array $vehicles_by_id ) {
    $key = bst_vehicle_exact_text_key( $label_text );
    if ( '' === $key ) {
        return 0;
    }
    if ( isset( $norm_to_id[ $key ] ) ) {
        return (int) $norm_to_id[ $key ];
    }
    foreach ( $vehicles_by_id as $vid => $title ) {
        if ( $key === bst_vehicle_exact_text_key( $title ) ) {
            return (int) $vid;
        }
    }
    return 0;
}

/**
 * Vehicle CPT IDs from a tour’s vehicle_pricing: linked vehicle_id rows plus label-only rows resolved to CPTs.
 * Vehicles admin “On Tours” uses linked CPT ids only (see bst_vehicle_usage_map); this helper still resolves
 * label text for pickers / limits until the text subfield is removed.
 *
 * @param int $tour_id Tour post ID.
 * @return int[]
 */
function bst_tour_pricing_vehicle_ids_resolved( $tour_id ) {
    $tour_id = (int) $tour_id;
    if ( $tour_id <= 0 || ! function_exists( 'get_field' ) ) {
        return array();
    }
    $pricing = get_field( 'vehicle_pricing', $tour_id, false );
    if ( empty( $pricing ) || ! is_array( $pricing ) ) {
        return array();
    }

    $maps = bst_vehicle_label_resolution_maps();

    $ids = array();
    foreach ( $pricing as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $nested = array();
        if ( function_exists( 'bst_vehicle_migration_get_nested_vehicle_rows' ) ) {
            $nested = bst_vehicle_migration_get_nested_vehicle_rows( $row );
        } elseif ( ! empty( $row['vehicles'] ) && is_array( $row['vehicles'] ) ) {
            $nested = $row['vehicles'];
        }
        foreach ( $nested as $vrow ) {
            if ( ! is_array( $vrow ) ) {
                continue;
            }
            $linked = 0;
            if ( function_exists( 'bst_vehicle_migration_row_linked_post_id' ) ) {
                $linked = bst_vehicle_migration_row_linked_post_id( $vrow );
            } elseif ( isset( $vrow['vehicle_id'] ) ) {
                $x = $vrow['vehicle_id'];
                $linked = is_array( $x ) && isset( $x['ID'] ) ? (int) $x['ID'] : (int) $x;
            } elseif ( isset( $vrow['field_67f9e40b1c001'] ) ) {
                $x = $vrow['field_67f9e40b1c001'];
                $linked = is_array( $x ) && isset( $x['ID'] ) ? (int) $x['ID'] : (int) $x;
            }
            if ( $linked > 0 ) {
                $p = get_post( $linked );
                if ( $p && 'vehicle' === $p->post_type ) {
                    $ids[] = $linked;
                }
                continue;
            }
            if ( function_exists( 'bst_vehicle_migration_row_vehicle_text' ) ) {
                $raw = bst_vehicle_migration_row_vehicle_text( $vrow );
            } else {
                $raw = isset( $vrow['vehicle'] ) ? (string) $vrow['vehicle'] : '';
            }
            $base = bst_vehicle_exact_text_key( $raw );
            if ( '' !== $base ) {
                $resolved = bst_vehicle_resolve_base_name_to_vehicle_id( $base, $maps['norm_to_id'], $maps['vehicles_by_id'] );
                if ( $resolved > 0 ) {
                    $ids[] = $resolved;
                }
            }
        }
    }

    $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
    sort( $ids, SORT_NUMERIC );
    return $ids;
}

/**
 * Parent tour post ID from a tour-date post (ACF field `tour`).
 *
 * @param int|string $tour_date_post_id Numeric ID or ACF-style string.
 * @return int
 */
function bst_tour_id_for_tour_date( $tour_date_post_id ) {
    if ( ! function_exists( 'get_field' ) ) {
        return 0;
    }
    $pid = 0;
    if ( is_numeric( $tour_date_post_id ) ) {
        $pid = (int) $tour_date_post_id;
    } elseif ( is_string( $tour_date_post_id ) && function_exists( 'acf_get_valid_post_id' ) ) {
        $pid = (int) acf_get_valid_post_id( $tour_date_post_id );
    }
    if ( $pid <= 0 ) {
        return 0;
    }
    $raw = get_field( 'tour', $pid );
    if ( $raw instanceof WP_Post ) {
        return (int) $raw->ID;
    }
    if ( is_array( $raw ) && ! empty( $raw['ID'] ) ) {
        return (int) $raw['ID'];
    }
    if ( is_numeric( $raw ) ) {
        return (int) $raw;
    }
    return 0;
}

