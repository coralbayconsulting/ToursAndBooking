<?php
/**
 * Vehicle normalization helpers (Vehicle CPT + booking integration).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
 * Lookup or create a Vehicle CPT post by exact title.
 *
 * @param string $vehicle_name Base vehicle name (no price suffix).
 * @return int Vehicle post ID (0 if invalid/failure).
 */
function bst_get_or_create_vehicle_id_by_name( $vehicle_name ) {
    $name = trim( sanitize_text_field( (string) $vehicle_name ) );
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
        $unset = get_field( 'vehicle_active', $id );
        if ( $unset === null || $unset === '' ) {
            update_field( 'vehicle_active', 1, $id );
        }
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

