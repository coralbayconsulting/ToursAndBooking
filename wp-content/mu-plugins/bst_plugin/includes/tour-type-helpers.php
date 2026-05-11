<?php
/**
 * Helpers for tour type archives and listings.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get grouped tour dates by year for a given tour-type-code term.
 *
 * Returns an array keyed by year, where each value is an array of
 * tour date display rows:
 * [
 *   '2026' => [
 *     [
 *       'title' => 'Tour name',
 *       'permalink' => 'https://...',
 *       'start_date' => '2026-03-01',
 *       'end_date' => '2026-03-03',
 *       'date_text' => '1–3 Mar 2026',
 *       'badge_class' => 'badge--available',
 *       'badge_text' => 'Available',
 *       'status_badge_class' => 'badge--waitlist',
 *       'status_badge_text' => 'Waitlist',
 *     ],
 *     ...
 *   ],
 *   ...
 * ]
 *
 * If there are no matching tour dates, an empty array is returned.
 *
 * @param int $tour_type_term_id Term ID for the tour-type-code taxonomy.
 * @return array
 */
function bst_get_tours_by_year_for_tour_type($tour_type_term_id) {
    if (empty($tour_type_term_id)) {
        return array();
    }

    // Get all tour posts for this tour type (excluding private tours).
    $tour_args = array(
        'post_type'      => 'tour',
        'posts_per_page' => -1,
        'tax_query'      => array(
            array(
                'taxonomy' => 'tour-type-code',
                'field'    => 'term_id',
                'terms'    => $tour_type_term_id,
            ),
        ),
        'meta_query'     => array(
            array(
                'key'     => 'private',
                'value'   => 1,
                'compare' => '!=',
                'type'    => 'NUMERIC',
            ),
        ),
        'fields'         => 'ids',
    );

    $tour_ids = get_posts($tour_args);

    if (empty($tour_ids)) {
        return array();
    }

    // Collect tour-date IDs for all matching tours in a single query.
    $tour_date_ids = get_posts(array(
        'post_type'      => 'tour-date',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => 'tour',
                'value'   => $tour_ids,
                'compare' => 'IN',
            ),
        ),
        'fields'         => 'ids',
    ));

    if (empty($tour_date_ids)) {
        return array();
    }

    // Now query all tour-date posts we collected, ordered by start_date.
    $tour_date_args = array(
        'post_type'      => 'tour-date',
        'posts_per_page' => -1,
        'post__in'       => $tour_date_ids,
        'orderby'        => 'meta_value',
        'meta_key'       => 'start_date',
        'order'          => 'ASC',
    );

    $tour_date_query = new WP_Query($tour_date_args);
    $tours_by_year   = array();

    if ($tour_date_query->have_posts()) {
        while ($tour_date_query->have_posts()) {
            $tour_date_query->the_post();

            $tour       = get_field('tour');
            $tour_name  = $tour ? str_replace(array('Motorcycle ', 'Miata ', 'Jeep '), '', get_the_title($tour)) : '';
            $tour_link  = $tour ? get_permalink($tour) : '';
            $tour_pid   = 0;
            if ( $tour ) {
                if ( is_object( $tour ) && isset( $tour->ID ) ) {
                    $tour_pid = (int) $tour->ID;
                } elseif ( is_numeric( $tour ) ) {
                    $tour_pid = (int) $tour;
                }
            }

            $start_raw = get_field('start_date');
            $end_raw   = get_field('end_date');
            if (empty($start_raw) || empty($end_raw)) {
                continue;
            }

            if ( function_exists( 'bst_tour_date_show_on_public_schedule' )
                && ! bst_tour_date_show_on_public_schedule( $start_raw, $tour_pid ) ) {
                continue;
            }

            $start_date = bst_tour_date_acf_date_meta_to_ymd( $start_raw );
            $end_date   = bst_tour_date_acf_date_meta_to_ymd( $end_raw );
            if ( $start_date === '' || $end_date === '' ) {
                continue;
            }

            $availability = intval(get_field('available_slots'));

            $display_info = bst_get_tour_date_display_info(
                $start_date,
                $end_date,
                $availability
            );

            $year = date('Y', strtotime($start_date));

            if (!isset($tours_by_year[$year])) {
                $tours_by_year[$year] = array();
            }

            $tours_by_year[$year][] = array(
                'title'              => $tour_name,
                'permalink'          => $tour_link,
                'start_date'         => $start_date,
                'end_date'           => $end_date,
                'date_text'          => $display_info['date_text'],
                'badge_class'        => $display_info['badge_class'],
                'badge_text'         => $display_info['badge_text'],
                'status_badge_class' => $display_info['status_badge_class'],
                'status_badge_text'  => $display_info['status_badge_text'],
            );
        }

        wp_reset_postdata();
    }

    // Sort tours by start_date within each year.
    if (!empty($tours_by_year)) {
        foreach ($tours_by_year as $year => &$tours) {
            usort($tours, function ($a, $b) {
                return strtotime($a['start_date']) - strtotime($b['start_date']);
            });
        }
        unset($tours);
    }

    return $tours_by_year;
}

/**
 * Resolve tour-type information for a given tour post based on the tour-type-code taxonomy.
 *
 * Looks at the first associated tour-type-code term and finds the corresponding
 * tour-type post. Returns an array with:
 *
 * [
 *   'id'    => (int|null),
 *   'title' => (string),
 *   'slug'  => (string),
 * ]
 *
 * If no matching tour-type is found, id will be null and title/slug empty.
 *
 * @param int $tour_id Tour post ID.
 * @return array
 */
function bst_get_tour_type_for_tour($tour_id) {
    $result = array(
        'id'    => null,
        'title' => '',
        'slug'  => '',
    );

    if (empty($tour_id)) {
        return $result;
    }

    $taxonomy_terms = get_the_terms($tour_id, 'tour-type-code');

    if (!$taxonomy_terms || is_wp_error($taxonomy_terms)) {
        return $result;
    }

    $taxonomy_term        = $taxonomy_terms[0];
    $result['slug']       = $taxonomy_term->slug;
    $tour_type_posts      = get_posts(array(
        'post_type'      => 'tour-type',
        'tax_query'      => array(
            array(
                'taxonomy' => 'tour-type-code',
                'field'    => 'term_id',
                'terms'    => $taxonomy_term->term_id,
            ),
        ),
        'posts_per_page' => 1,
    ));

    if (!empty($tour_type_posts)) {
        $result['id']    = $tour_type_posts[0]->ID;
        $result['title'] = $tour_type_posts[0]->post_title;
    }

    return $result;
}


