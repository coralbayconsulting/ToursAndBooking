<?php
/**
 * Helpers for share link metadata on tour pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get share metadata for the tour archive page.
 *
 * Returns:
 * [
 *   'url'          => string,
 *   'email_label'  => string, // human text for subject/body
 * ]
 *
 * Matches the existing archive-tour-type wording.
 *
 * @return array
 */
function bst_get_tour_archive_share_metadata() {
    $share_url    = get_post_type_archive_link('tour-type');
    $email_label  = "Blue Strada's tours";

    return array(
        'url'         => $share_url,
        'email_label' => $email_label,
    );
}

/**
 * Get share metadata for a tour-type taxonomy term context.
 *
 * Returns:
 * [
 *   'url'          => string,
 *   'email_label'  => string, // e.g. "Blue Strada's driving tours"
 * ]
 *
 * Matches the existing taxonomy-tour-type-code wording.
 *
 * @param WP_Term $term
 * @return array
 */
function bst_get_tour_type_term_share_metadata($term) {
    $share_url = '';
    $label     = "Blue Strada's tours";

    if ($term && !is_wp_error($term)) {
        $share_url = get_term_link($term);

        // Extract tour type from term name (e.g., "Driving Tours" -> "driving").
        $tour_type_name = strtolower(trim(preg_replace('/\s+tours?$/i', '', $term->name)));
        if (!empty($tour_type_name)) {
            $label = "Blue Strada's {$tour_type_name} tours";
        }
    }

    if (empty($share_url)) {
        $share_url = get_post_type_archive_link('tour-type');
    }

    return array(
        'url'         => $share_url,
        'email_label' => $label,
    );
}

