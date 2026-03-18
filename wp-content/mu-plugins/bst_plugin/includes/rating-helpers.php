<?php
/**
 * Helpers for tour rating taxonomy (Platinum / Gold / Silver / Bronze) and filters.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get tour-rating terms that are actually used by tours within a given tour-type term,
 * sorted by medal hierarchy (Platinum, Gold, Silver, Bronze, then others).
 *
 * @param WP_Term $tour_type_term The current tour-type-code taxonomy term.
 * @return WP_Term[] Array of tour-rating terms in display order. Empty array when none.
 */
function bst_get_tour_type_rating_terms($tour_type_term) {
    if (!($tour_type_term instanceof WP_Term)) {
        return array();
    }

    $taxonomy = $tour_type_term->taxonomy;
    $term_id  = $tour_type_term->term_id;

    // Find all tours in this tour type.
    $tours_query = new WP_Query(array(
        'post_type'      => 'tour',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => array(
            array(
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $term_id,
            ),
        ),
    ));

    if (!$tours_query->have_posts()) {
        wp_reset_postdata();
        return array();
    }

    $used_terms = array();

    foreach ($tours_query->posts as $tour_id) {
        $tour_ratings = wp_get_post_terms($tour_id, 'tour-rating');
        if (empty($tour_ratings) || is_wp_error($tour_ratings)) {
            continue;
        }
        foreach ($tour_ratings as $rating_term) {
            if (!isset($used_terms[$rating_term->term_id])) {
                $used_terms[$rating_term->term_id] = $rating_term;
            }
        }
    }

    wp_reset_postdata();

    if (empty($used_terms)) {
        return array();
    }

    $rating_terms = array_values($used_terms);

    // Sort by medal hierarchy (Platinum=4, Gold=3, Silver=2, Bronze=1, Others=0), descending.
    usort($rating_terms, function($a, $b) {
        $get_medal_value = function($name) {
            $name_lower = strtolower($name);
            if (strpos($name_lower, 'platinum') !== false) return 4;
            if (strpos($name_lower, 'gold') !== false)     return 3;
            if (strpos($name_lower, 'silver') !== false)   return 2;
            if (strpos($name_lower, 'bronze') !== false)   return 1;
            return 0;
        };

        $medal_a = $get_medal_value($a->name);
        $medal_b = $get_medal_value($b->name);

        return $medal_b - $medal_a;
    });

    return $rating_terms;
}

/**
 * Get HTML markup for a tour rating "medal" display.
 *
 * Produces the same inline markup previously used on single-tour pages:
 * a colored circle plus the rating name, or just the name for non-medal ratings.
 *
 * @param WP_Term|object $tour_rating Term object (or similar) with a ->name property.
 * @return string HTML markup for display, or empty string when no rating provided.
 */
function bst_get_tour_rating_medal_markup($tour_rating) {
    if (empty($tour_rating) || !is_object($tour_rating) || empty($tour_rating->name)) {
        return '';
    }

    $name       = $tour_rating->name;
    $name_lower = strtolower($name);

    if (strpos($name_lower, 'platinum') !== false) {
        return '<span class="tour-medal-inline tour-medal-platinum"></span><span class="tour-medal-text">' . esc_html($name) . '</span>';
    }

    if (strpos($name_lower, 'gold') !== false) {
        return '<span class="tour-medal-inline tour-medal-gold"></span><span class="tour-medal-text">' . esc_html($name) . '</span>';
    }

    if (strpos($name_lower, 'silver') !== false) {
        return '<span class="tour-medal-inline tour-medal-silver"></span><span class="tour-medal-text">' . esc_html($name) . '</span>';
    }

    if (strpos($name_lower, 'bronze') !== false) {
        return '<span class="tour-medal-inline tour-medal-bronze"></span><span class="tour-medal-text">' . esc_html($name) . '</span>';
    }

    // Fallback for other ratings without medal types.
    return '<span class="tour-medal-text">' . esc_html($name) . '</span>';
}


