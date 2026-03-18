<?php
/**
 * Helpers for tour-date lookups.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get grouped tour dates by year for a single tour.
 *
 * Returns an array keyed by year, where each value is an array of
 * tour date rows shaped exactly like the existing single-tour.php logic:
 *
 * [
 *   '2026' => [
 *     [
 *       'id'                    => 123,
 *       'title'                 => 'Tour Date Title',
 *       'start_date'            => '20260301',   // YYYYMMDD for JS
 *       'end_date'              => '20260303',   // YYYYMMDD for JS
 *       'availability'          => 10,
 *       'date_extension_offered'=> '1',
 *     ],
 *     ...
 *   ],
 *   ...
 * ]
 *
 * @param int $tour_id Tour post ID.
 * @return array
 */
function bst_get_tour_dates_grouped_by_year($tour_id) {
    $tour_dates = array();

    if (empty($tour_id)) {
        return $tour_dates;
    }

    $args = array(
        'post_type'      => 'tour-date',
        'post_status'    => 'publish',  // Explicitly require published posts
        'meta_query'     => array(
            array(
                'key'     => 'tour',
                'value'   => $tour_id,
                'compare' => '=',
            ),
        ),
        'posts_per_page' => -1,         // Retrieve all posts
        'orderby'        => 'meta_value',
        'meta_key'       => 'start_date',
        'order'          => 'ASC',
    );

    $tour_date_query = new WP_Query($args);

    if ($tour_date_query->have_posts()) {
        while ($tour_date_query->have_posts()) {
            $tour_date_query->the_post();

            $start_date = get_post_meta(get_the_ID(), 'start_date', true);
            $end_date   = get_post_meta(get_the_ID(), 'end_date', true);

            if (empty($start_date) || empty($end_date)) {
                continue;
            }

            // Convert dates from YYYY-MM-DD to YYYYMMDD format for JavaScript
            $start_date_js = str_replace('-', '', $start_date);
            $end_date_js   = str_replace('-', '', $end_date);

            $year = date('Y', strtotime($start_date));
            if (!isset($tour_dates[$year])) {
                $tour_dates[$year] = array();
            }

            $tour_dates[$year][] = array(
                'id'                    => get_the_ID(),
                'title'                 => get_the_title(),
                'start_date'            => $start_date_js,
                'end_date'              => $end_date_js,
                'availability'          => get_post_meta(get_the_ID(), 'available_slots', true),
                'date_extension_offered'=> get_post_meta(get_the_ID(), 'extension_offered', true),
            );
        }

        wp_reset_postdata();
    }

    return $tour_dates;
}

