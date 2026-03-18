<?php
/**
 * Structured data helpers for tour pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Output JSON-LD structured data for a single tour.
 *
 * Mirrors the existing inline JSON-LD used on single-tour pages so that
 * themes can call this helper instead of duplicating the logic.
 *
 * @param int    $tour_id          The tour post ID.
 * @param int    $tour_type_id     The related tour-type post ID (if any).
 * @param string $tour_title       The tour title.
 * @param string $short_description Short description (HTML allowed).
 * @param string $schedule         Schedule/itinerary text (HTML allowed).
 * @param string $starting_from    Starting location text.
 * @param array  $tour_dates       Grouped tour-date data as built in single-tour.php.
 */
function bst_output_tour_json_ld($tour_id, $tour_type_id, $tour_title, $short_description, $schedule, $starting_from, array $tour_dates) {
    if (!$tour_id) {
        return;
    }

    $tour_type_title = $tour_type_id ? get_the_title($tour_type_id) : '';
    $next_start_date = null;

    // Find next upcoming start date from $tour_dates (same logic as template).
    if (!empty($tour_dates)) {
        $all_dates_flat = array();
        foreach ($tour_dates as $year => $dates) {
            $all_dates_flat = array_merge($all_dates_flat, $dates);
        }

        usort($all_dates_flat, function ($a, $b) {
            return strtotime($a['start_date']) - strtotime($b['start_date']);
        });

        $current_date = date('Y-m-d');
        foreach ($all_dates_flat as $date) {
            if (date('Y-m-d', strtotime($date['start_date'])) > $current_date) {
                $next_start_date = $date['start_date'];
                break;
            }
        }
    }

    $banner_image = get_field('detail_banner_image', $tour_id);
    $permalink    = get_permalink($tour_id);

    // Currency: same behavior as original template (fallback to EUR).
    $schema_currency = get_field('currency', $tour_id);
    if (empty($schema_currency)) {
        $schema_currency = 'EUR';
    }

    ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "TouristTrip",
        "name": "<?php echo esc_js($tour_title); ?>",
        "description": "<?php echo esc_js(wp_strip_all_tags($short_description)); ?>",
        "image": "<?php echo esc_url($banner_image); ?>",
        "url": "<?php echo esc_url($permalink); ?>",
        "provider": {
            "@type": "Organization",
            "name": "Blue Strada Tours",
            "url": "<?php echo esc_url(home_url()); ?>"
        },
        "touristType": "<?php echo esc_js($tour_type_title); ?>",
        "itinerary": {
            "@type": "ItemList",
            "description": "<?php echo esc_js(wp_strip_all_tags($schedule)); ?>"
        }<?php if ($next_start_date) : ?>,
        "startDate": "<?php echo esc_js(date('Y-m-d', strtotime($next_start_date))); ?>"<?php endif; ?>,
        "location": {
            "@type": "Place",
            "name": "<?php echo esc_js($starting_from); ?>",
            "address": "<?php echo esc_js($starting_from); ?>"
        },
        "offers": {
            "@type": "Offer",
            "availability": "https://schema.org/InStock",
            "url": "<?php echo esc_url($permalink); ?>",
            "priceCurrency": "<?php echo esc_js($schema_currency); ?>"
        }
    }
    </script>
    <?php
}

