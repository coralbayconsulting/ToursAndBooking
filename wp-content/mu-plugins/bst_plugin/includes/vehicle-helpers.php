<?php
/**
 * Vehicle normalization helpers (Vehicle CPT + booking integration).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
 * Strip a vehicle dropdown label down to its base name (remove trailing " (+€...)" etc).
 *
 * @param string $vehicle_text Raw stored vehicle text (often includes "(+€450)").
 * @return string
 */
function bst_vehicle_base_name_from_text( $vehicle_text ) {
    $v = trim( (string) $vehicle_text );
    if ( '' === $v ) {
        return '';
    }
    // Split at first " (" to match existing code behavior.
    $parts = preg_split( '/\s*\(/', $v, 2 );
    return trim( (string) ( $parts[0] ?? $v ) );
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
 * Default ACF transmission for a new vehicle: motorcycles → automatic; cars from title keywords.
 *
 * @param string $post_title   Vehicle CPT title.
 * @param string $vehicle_type 'car' or 'motorcycle'.
 * @return string 'manual'|'automatic'|'na'
 */
function bst_vehicle_default_transmission( $post_title, $vehicle_type ) {
    $vehicle_type = (string) $vehicle_type;
    if ( 'motorcycle' === $vehicle_type ) {
        return 'automatic';
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

