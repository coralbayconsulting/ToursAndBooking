<?php get_header(); ?>

<?php
// =============================================================================
// BST Filter Pre-Pass
// -----------------------------------------------------------------------------
// Compute the set of tour-dates (grouped by year) for every tour in the current
// taxonomy term, and the list of distinct years that have at least one
// publicly-visible tour-date. This data is used to (a) populate the "Filter by
// year" dropdown rendered in the breadcrumb/mobile filter blocks below and
// (b) avoid re-querying tour-date posts inside the main rendering loop.
//
// Notes:
//  - The pre-pass intentionally ignores the rating filter so that the year
//    dropdown is stable as the rating selection changes.
//  - Visibility is governed by bst_tour_date_show_on_public_schedule(), so past
//    or hidden dates are excluded just like the existing rendering logic.
// =============================================================================

$bst_selected_year = isset( $_GET['tour_year'] ) && $_GET['tour_year'] !== ''
    ? (int) sanitize_text_field( wp_unslash( $_GET['tour_year'] ) )
    : 0;

$bst_term_obj         = get_queried_object();
$bst_tour_dates_by_id = array();
$bst_available_years  = array();

if ( $bst_term_obj && isset( $bst_term_obj->taxonomy, $bst_term_obj->term_id ) ) {
    $bst_pre_pass_query = new WP_Query( array(
        'post_type'      => 'tour',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => array(
            array(
                'taxonomy' => $bst_term_obj->taxonomy,
                'field'    => 'term_id',
                'terms'    => $bst_term_obj->term_id,
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
    ) );

    if ( $bst_pre_pass_query->have_posts() ) {
        foreach ( $bst_pre_pass_query->posts as $bst_pre_tour_id ) {
            $bst_dates       = array();
            $bst_dates_query = new WP_Query( array(
                'post_type'      => 'tour-date',
                'post_status'    => 'publish',
                'meta_query'     => array(
                    array(
                        'key'     => 'tour',
                        'value'   => $bst_pre_tour_id,
                        'compare' => '=',
                    ),
                ),
                'posts_per_page' => -1,
                'orderby'        => 'meta_value',
                'meta_key'       => 'start_date',
                'order'          => 'ASC',
            ) );

            if ( $bst_dates_query->have_posts() ) {
                while ( $bst_dates_query->have_posts() ) {
                    $bst_dates_query->the_post();
                    $bst_start_raw = get_post_meta( get_the_ID(), 'start_date', true );
                    $bst_end_raw   = get_post_meta( get_the_ID(), 'end_date', true );
                    if ( function_exists( 'bst_tour_date_show_on_public_schedule' )
                        && ! bst_tour_date_show_on_public_schedule( $bst_start_raw, (int) $bst_pre_tour_id ) ) {
                        continue;
                    }
                    $bst_year = function_exists( 'bst_tour_date_acf_date_meta_to_ymd' )
                        ? (int) substr( (string) bst_tour_date_acf_date_meta_to_ymd( $bst_start_raw ), 0, 4 )
                        : (int) date( 'Y', strtotime( $bst_start_raw ) );
                    if ( $bst_year <= 0 ) {
                        continue;
                    }
                    $bst_start_ymd = function_exists( 'bst_tour_date_acf_date_meta_to_ymd' )
                        ? bst_tour_date_acf_date_meta_to_ymd( $bst_start_raw )
                        : $bst_start_raw;
                    $bst_end_ymd = function_exists( 'bst_tour_date_acf_date_meta_to_ymd' )
                        ? bst_tour_date_acf_date_meta_to_ymd( $bst_end_raw )
                        : $bst_end_raw;
                    if ( $bst_start_ymd === '' || $bst_end_ymd === '' ) {
                        continue;
                    }
                    if ( ! isset( $bst_dates[ $bst_year ] ) ) {
                        $bst_dates[ $bst_year ] = array();
                    }
                    $bst_dates[ $bst_year ][] = array(
                        'id'           => get_the_ID(),
                        'title'        => get_the_title(),
                        'start_date'   => $bst_start_ymd,
                        'end_date'     => $bst_end_ymd,
                        'availability' => get_post_meta( get_the_ID(), 'available_slots', true ),
                    );
                    if ( ! in_array( $bst_year, $bst_available_years, true ) ) {
                        $bst_available_years[] = $bst_year;
                    }
                }
                wp_reset_postdata();
            }

            if ( ! empty( $bst_dates ) ) {
                foreach ( $bst_dates as $bst_y => &$bst_year_dates ) {
                    usort( $bst_year_dates, function ( $a, $b ) {
                        return strtotime( $a['start_date'] ) - strtotime( $b['start_date'] );
                    } );
                }
                unset( $bst_year_dates );
                ksort( $bst_dates );
            }

            $bst_tour_dates_by_id[ $bst_pre_tour_id ] = $bst_dates;
        }
        wp_reset_postdata();
    }
}

sort( $bst_available_years );
$bst_available_years = array_values( array_unique( $bst_available_years ) );

// Drop a stale selection if the year is no longer available (e.g. all dates in
// that year were unpublished or the user is viewing a different term).
if ( $bst_selected_year && ! in_array( $bst_selected_year, $bst_available_years, true ) ) {
    $bst_selected_year = 0;
}
?>

<div class="page-content">
    <div id="content" class="content">
        <!-- Altered Content Start -->
        <div class="translucent-overlay">
            <?php
                $bst_banner   = bst_get_queried_tour_type_code_banner_data();
                $banner_text  = $bst_banner['heading'];
                $banner_image = $bst_banner['image'];
            ?>

            <!-- TOP BANNER -->
            <div class="top-banner-container">
                <img class="top-banner" src="<?php echo esc_url($banner_image); ?>" alt="<?php echo esc_attr($banner_text); ?> - Tour Category Banner" fetchpriority="high">
                <h1 class="banner-text"><?php echo esc_html($banner_text); ?></h1>
            </div>
        
        <!-- Breadcrumbs Section -->
        <div class="bst-breadcrumb-section">
            <div class="bst-breadcrumb-container has-share-buttons">
                <?php if (function_exists('bst_render_tour_archive_breadcrumbs')) {
                    bst_render_tour_archive_breadcrumbs($banner_text);
                } ?>
                
                <!-- DESKTOP FILTERS - inline with breadcrumbs -->
                <?php
                // Check if tour rating is enabled in settings (kept gated so the
                // rating dropdown only appears when the option is on; the year
                // dropdown is always available when there are public dates).
                $enable_tour_rating = get_option('bst_enable_tour_rating', false);
                $current_term       = get_queried_object();
                $rating_terms       = array();

                if ($enable_tour_rating && function_exists('bst_get_tour_type_rating_terms')) {
                    $rating_terms = bst_get_tour_type_rating_terms($current_term);
                }

                $selected_rating  = isset($_GET['tour_rating']) ? sanitize_text_field( wp_unslash( $_GET['tour_rating'] ) ) : '';
                $bst_show_rating  = $enable_tour_rating && ! empty( $rating_terms ) && ! is_wp_error( $rating_terms );
                $bst_show_years   = ! empty( $bst_available_years );
                $bst_show_filter  = $bst_show_years || $bst_show_rating;
                $bst_filter_label = ( $bst_show_years && ! $bst_show_rating ) ? 'Filter by Year' : 'Filter';

                if ( $bst_show_filter ) :
                ?>
                <div class="breadcrumb-filters">
                    <form method="GET" class="breadcrumb-filter-form">
                        <label class="breadcrumb-filter-label" style="text-transform: uppercase; font-weight: 600; color: #666; display: inline-block; font-size: 14px; letter-spacing: 1.0px; margin-right: 0;"><?php echo esc_html( $bst_filter_label ); ?></label>

                        <?php if ( $bst_show_years ) : ?>
                            <select name="tour_year" id="tour_year_desktop" class="breadcrumb-filter-select" aria-label="Filter by year">
                                <option value="">All Years</option>
                                <?php foreach ( $bst_available_years as $bst_y ) : ?>
                                    <option value="<?php echo esc_attr( $bst_y ); ?>" <?php selected( $bst_selected_year, $bst_y ); ?>>
                                        <?php echo esc_html( $bst_y ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if ( $bst_show_rating ) : ?>
                            <select name="tour_rating" id="tour_rating_desktop" class="breadcrumb-filter-select" aria-label="Filter by tour class">
                                <option value="">All Classes</option>
                                <?php foreach ( $rating_terms as $term ) : ?>
                                    <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_rating, $term->slug ); ?>>
                                        <?php echo esc_html( $term->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="rating-help-btn" id="rating-help-btn" title="Learn about our tour classification system">ℹ</button>
                        <?php endif; ?>

                        <button type="submit" class="breadcrumb-filter-btn">Filter</button>
                    </form>
                </div>
                <?php endif; // Close $bst_show_filter check ?>
                
                <!-- Share Buttons -->
                <?php
                $term       = get_queried_object();
                $share_meta = function_exists( 'bst_get_tour_type_term_share_metadata' )
                    ? bst_get_tour_type_term_share_metadata( $term )
                    : array(
                        'url'         => get_term_link( $term ),
                        'email_label' => "Blue Strada's tours",
                    );
                bst_render_share_buttons(
                    array(
                        'context'     => 'tours-list',
                        'url'         => $share_meta['url'],
                        'email_label' => $share_meta['email_label'],
                    )
                );
                ?>
            </div>
        </div>

        <!-- MOBILE FILTERS - separate section for mobile -->
        <?php
        // Reuse the same flags from the desktop block above; the mobile block
        // mirrors that UI so both dropdowns submit together via a Filter button.
        if ( $bst_show_filter ) :
        ?>
        <div class="mobile-filters" style="display: none; padding: 15px 20px; background: #f8f8f8; border-bottom: 1px solid #ddd;">
            <style>
                /* Show mobile filters only on mobile devices */
                @media (max-width: 768px) {
                    .mobile-filters {
                        display: block !important;
                    }
                    .bst-breadcrumb-section {
                        display: none !important;
                    }
                }
                /* Hide mobile filters on desktop */
                @media (min-width: 769px) {
                    .mobile-filters {
                        display: none !important;
                    }
                }
            </style>
            <form method="GET" class="mobile-filter-form">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: 1.0px;"><?php echo esc_html( $bst_filter_label ); ?></label>

                <?php if ( $bst_show_years ) : ?>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <select name="tour_year" id="tour_year_mobile" style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px;" aria-label="Filter by year">
                            <option value="">All Years</option>
                            <?php foreach ( $bst_available_years as $bst_y ) : ?>
                                <option value="<?php echo esc_attr( $bst_y ); ?>" <?php selected( $bst_selected_year, $bst_y ); ?>>
                                    <?php echo esc_html( $bst_y ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ( $bst_show_rating ) : ?>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <select name="tour_rating" id="tour_rating_mobile" style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px;" aria-label="Filter by tour class">
                            <option value="">All Classes</option>
                            <?php foreach ( $rating_terms as $term ) : ?>
                                <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_rating, $term->slug ); ?>>
                                    <?php echo esc_html( $term->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="rating-help-btn" id="rating-help-btn-mobile" title="Learn about our rating system" style="background: none; color: #666; border: none; width: 32px; height: 32px; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">ℹ</button>
                    </div>
                <?php endif; ?>

                <div style="text-align: center;">
                    <button type="submit" class="breadcrumb-filter-btn" style="display: inline-flex; padding: 10px 28px;">Filter</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <script>
        jQuery(document).ready(function($) {
            // Only initialize rating help functionality if elements exist
            function initializeRatingHelp() {
                // Check if any rating help button exists (desktop or mobile context)
                if ($('#rating-help-btn').length === 0 && $('#rating-help-btn-mobile').length === 0) {
                    return false; // No rating help button found
                }
                
                // Create modal HTML dynamically and append to body
                function createRatingModal() {
                    const modalHTML = `
                        <div id="rating-panel-backdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 2147483647;">
                            <div id="rating-info-panel" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 2147483647; background: #fff; border: none; border-radius: 8px; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.4); max-width: 500px; width: 90%; max-height: 70vh; overflow-y: auto;">
                                <div style="background: #007cba; color: white; padding: 15px 20px; position: relative;">
                                    <h4 style="margin: 0; font-size: 18px; color: white;">Tour Classification System</h4>
                                    <button type="button" id="close-rating-panel" style="position: absolute; top: 10px; right: 15px; background: none; border: none; font-size: 24px; color: white; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
                                </div>
                                <div style="padding: 20px;">
                                    <style>
                                        #rating-info-panel ul { 
                                            margin-left: 20px; 
                                            padding-left: 0; 
                                            margin-top: 10px;
                                            text-align: left;
                                        }
                                        #rating-info-panel li { 
                                            margin-bottom: 5px; 
                                            text-align: left;
                                        }
                                        #rating-info-panel div {
                                            text-align: left;
                                        }
                                        /* Mobile layout for stars and title */
                                        @media (max-width: 768px) {
                                            #rating-info-panel .rating-entry {
                                                display: block !important;
                                                margin-bottom: 15px !important;
                                            }
                                            #rating-info-panel .rating-stars {
                                                display: inline !important;
                                                margin-right: 8px !important;
                                                min-width: auto !important;
                                                font-size: 18px !important;
                                            }
                                            #rating-info-panel .rating-content {
                                                display: inline !important;
                                                width: auto !important;
                                                vertical-align: top !important;
                                            }
                                            #rating-info-panel .rating-title {
                                                display: inline !important;
                                                margin-bottom: 5px !important;
                                            }
                                            #rating-info-panel .rating-description {
                                                display: block !important;
                                                margin-top: 5px !important;
                                            }
                                        }
                                    </style>
                                    <?php 
                                    // Get only rating terms that are actually used in tours for this tour type
                                    $used_all_rating_terms = array();
                                    
                                    // Query tours in this category to find which ratings are actually used
                                    $all_tours_query = new WP_Query(array(
                                        'post_type' => 'tour',
                                        'posts_per_page' => -1,
                                        'fields' => 'ids',
                                        'tax_query' => array(
                                            array(
                                                'taxonomy' => get_queried_object()->taxonomy,
                                                'field'    => 'term_id',
                                                'terms'    => get_queried_object()->term_id,
                                            ),
                                        ),
                                    ));
                                    
                                    if ($all_tours_query->have_posts()) {
                                        foreach ($all_tours_query->posts as $tour_id) {
                                            $tour_ratings = wp_get_post_terms($tour_id, 'tour-rating');
                                            if (!empty($tour_ratings) && !is_wp_error($tour_ratings)) {
                                                foreach ($tour_ratings as $rating_term) {
                                                    if (!isset($used_all_rating_terms[$rating_term->term_id])) {
                                                        $used_all_rating_terms[$rating_term->term_id] = $rating_term;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    wp_reset_postdata();
                                    
                                    // Convert to array and sort by medal hierarchy (Platinum, Gold, Silver, Bronze)
                                    $all_rating_terms = array_values($used_all_rating_terms);
                                    if (!empty($all_rating_terms)) {
                                        usort($all_rating_terms, function($a, $b) {
                                            // Define medal hierarchy (Platinum=4, Gold=3, Silver=2, Bronze=1, Others=0)
                                            $get_medal_value = function($name) {
                                                $name_lower = strtolower($name);
                                                if (strpos($name_lower, 'platinum') !== false) return 4;
                                                if (strpos($name_lower, 'gold') !== false) return 3;
                                                if (strpos($name_lower, 'silver') !== false) return 2;
                                                if (strpos($name_lower, 'bronze') !== false) return 1;
                                                return 0;
                                            };
                                            
                                            $medal_a = $get_medal_value($a->name);
                                            $medal_b = $get_medal_value($b->name);
                                            return $medal_b - $medal_a; // Descending order (Platinum first)
                                        });
                                        
                                        foreach ($all_rating_terms as $term) : 
                                    ?>
                                    <div class="rating-entry" style="margin-bottom: 15px;">
                                        <div class="rating-title-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                            <strong class="rating-title" style="color: #333; font-size: 16px;"><?php echo esc_html($term->name); ?></strong>
                                        </div>
                                        <?php if (!empty($term->description)) : ?>
                                            <div class="rating-description" style="color: #666; line-height: 1.5; font-size: 14px; text-align: left; margin-left: 0;">
                                                <?php echo wp_kses_post($term->description); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                        endforeach;
                                    } 
                                    ?>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Remove any existing modal first
                    $('#rating-panel-backdrop').remove();
                    
                    // Append to body
                    $('body').append(modalHTML);
                    
                    // Bind events with error handling
                    $('#close-rating-panel, #rating-panel-backdrop').off('click').on('click', function(e) {
                        if (e.target === this) {
                            hideRatingPanel();
                        }
                    });
                }
                
                function showRatingPanel() {
                    try {
                        createRatingModal();
                        $('#rating-panel-backdrop').fadeIn(300);
                        $('body').css('overflow', 'hidden');
                    } catch (error) {
                        console.log('Error showing rating panel:', error);
                    }
                }
                
                function hideRatingPanel() {
                    try {
                        $('#rating-panel-backdrop').fadeOut(200, function() {
                            $(this).remove();
                        });
                        $('body').css('overflow', '');
                    } catch (error) {
                        console.log('Error hiding rating panel:', error);
                    }
                }
                
                // Bind click event to rating help button with error handling
                $(document).off('click', '#rating-help-btn, #rating-help-btn-mobile').on('click', '#rating-help-btn, #rating-help-btn-mobile', function(e) {
                    e.preventDefault();
                    showRatingPanel();
                });
                
                // Close on ESC key
                $(document).off('keydown.rating-panel').on('keydown.rating-panel', function(e) {
                    if (e.key === 'Escape' && $('#rating-panel-backdrop').length) {
                        hideRatingPanel();
                    }
                });
                
                return true; // Successfully initialized
            }
            
            // Initialize with error handling
            try {
                initializeRatingHelp();
            } catch (error) {
                console.log('Rating help initialization failed:', error);
            }
        });
        </script>

            <!-- TOUR LISTINGS -->
            <div class="h-section-grid-container h-section-boxed-container">
                <div data-colibri-id="4640-c7" class="h-row-container gutters-row-lg-1 gutters-row-md-1 gutters-row-0 gutters-row-v-lg-1 gutters-row-v-md-1 gutters-row-v-1 style-1802 style-local-4640-c7 position-relative">
                    <div class="h-row justify-content-lg-center justify-content-md-center justify-content-center align-items-lg-stretch align-items-md-stretch align-items-stretch gutters-col-lg-1 gutters-col-md-1 gutters-col-0 gutters-col-v-lg-1 gutters-col-v-md-1 gutters-col-v-1">
                        <?php
                        // Build the query with filters
                        $query_args = array(
                            'post_type' => 'tour',
                            'posts_per_page' => -1,
                            'tax_query' => array(
                                'relation' => 'AND',
                                array(
                                    'taxonomy' => get_queried_object()->taxonomy,
                                    'field'    => 'term_id',
                                    'terms'    => get_queried_object()->term_id,
                                ),
                            ),
                            'meta_query' => array(
                                array(
                                    'key' => 'private',
                                    'value' => 1,
                                    'compare' => '!=',
                                    'type' => 'NUMERIC'
                                )
                            ),
                        );
                        
                        // Add rating filter if selected
                        $selected_rating = isset($_GET['tour_rating']) ? sanitize_text_field($_GET['tour_rating']) : '';
                        if (!empty($selected_rating)) {
                            // Add taxonomy query for tour-rating
                            $query_args['tax_query'][] = array(
                                'taxonomy' => 'tour-rating',
                                'field'    => 'slug',
                                'terms'    => $selected_rating,
                            );
                        }
                        
                        $custom_query = new WP_Query($query_args);
                        
                        if ($custom_query->have_posts()) :
                            $tours = array();
                            while ($custom_query->have_posts()) : $custom_query->the_post();
                                $tours[] = array(
                                    'ID' => get_the_ID(),
                                    'title' => get_the_title(),
                                    'listing_sort_order' => get_field('listing_sort_order') ?: 9999
                                );
                            endwhile;
                            wp_reset_postdata();

                            // Apply the year filter: drop any tour that has no
                            // publicly-visible tour-date in the selected year.
                            if ( $bst_selected_year ) {
                                $tours = array_values( array_filter( $tours, function ( $t ) use ( $bst_tour_dates_by_id, $bst_selected_year ) {
                                    return ! empty( $bst_tour_dates_by_id[ $t['ID'] ][ $bst_selected_year ] );
                                } ) );
                            }

                            // Sort by listing_sort_order
                            usort($tours, function($a, $b) {
                                return intval($a['listing_sort_order']) - intval($b['listing_sort_order']);
                            });

                            foreach ($tours as $tour) :
                                $tour_post = get_post($tour['ID']);
                                $title = str_replace(array("Motorcycle ", "Miata ", "Jeep "), "", get_the_title($tour_post));
                                // When filtering to a single year, drop a
                                // trailing "(YYYY)" from the title since the
                                // year is implied by the active filter.
                                if ( $bst_selected_year ) {
                                    $title = preg_replace(
                                        '/\s*\(' . preg_quote( (string) $bst_selected_year, '/' ) . '\)\s*$/',
                                        '',
                                        $title
                                    );
                                }
                                $description = get_field('listing_description', $tour_post->ID);
                                $image = get_field('listing_image', $tour_post->ID);
                                $new = get_field('new', $tour_post->ID);
                                $title_modifier = get_field('title_modifier', $tour_post->ID);
                                
                                // Get tour rating from ACF field
                                $tour_rating = get_field('tour_rating', $tour_post->ID);
                                ?>
                                <div class="tour-box">
                                    <h2><?php echo esc_html($title); ?></h2>
                                    <div class="listing-image-container">
                                        <a href="<?php echo esc_url(get_permalink($tour_post->ID)); ?>">
                                            <?php if ($new) : ?>
                                                <img class="new-icon" border="0" src="<?php echo get_stylesheet_directory_uri(); ?>/images/new-icon-corner.webp" alt="New Icon">
                                            <?php endif; ?>
                                            <?php if ($enable_tour_rating && $tour_rating) : ?>
                                                <div class="medal-rating-overlay" data-tooltip="<?php echo esc_attr($tour_rating->description ? $tour_rating->description : $tour_rating->name); ?>">
                                                    <span style="color: white; font-size: 12px; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.8); white-space: nowrap;"><?php echo esc_html($tour_rating->name); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php echo wp_get_attachment_image( $image, 'tour-listing', false, [ 'alt' => esc_attr( $title ), 'loading' => 'lazy', 'sizes' => '(max-width: 600px) calc(100vw - 40px), 300px' ] ); ?>
                                            <?php if ($title_modifier) : ?>
                                            <div class="overlay-text">
                                                    <span class="badge green"><?php echo esc_html($title_modifier); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <p><?php echo wp_kses_post($description); ?></p>
                                    
                                    <?php
                                    // Add pricing text: "Prices starting at" + half the cost of package 3 + "per person"
                                    $package_pricing = get_field('package_pricing', $tour_post->ID);
                                    if ($package_pricing && !empty($package_pricing['package_3'])) {
                                        $package_3_price = floatval($package_pricing['package_3']);
                                        $half_package_3_price = $package_3_price / 2;
                                        
                                        // Get tour currency, default to EUR
                                        $tour_currency = get_field('currency', $tour_post->ID);
                                        if (empty($tour_currency)) {
                                            $tour_currency = 'EUR';
                                        }
                                        
                                        // Round to nearest euro and format the currency
                                        $rounded_price = round($half_package_3_price);
                                        
                                        // Format with thousands separator but no decimals
                                        $symbol = ($tour_currency === 'USD') ? '$' : '€';
                                        $formatted_price = $symbol . number_format($rounded_price, 0);
                                        
                                        echo '<p class="tour-pricing-info" style="margin: -8px 0 10px; font-size: 14px; font-weight: 600; color: #2c5aa0; text-align: center;">Prices starting at ' . $formatted_price . ' per person</p>';
                                    }
                                    ?>
                                    <div class="date-container">
                                        <?php
                                        $tourid = $tour['ID'];

                                        // Use the dates we already gathered in
                                        // the pre-pass at the top of the file.
                                        $tour_dates = isset( $bst_tour_dates_by_id[ $tourid ] )
                                            ? $bst_tour_dates_by_id[ $tourid ]
                                            : array();

                                        // When a year filter is active, only
                                        // render that year's date list.
                                        if ( $bst_selected_year ) {
                                            $tour_dates = isset( $tour_dates[ $bst_selected_year ] )
                                                ? array( $bst_selected_year => $tour_dates[ $bst_selected_year ] )
                                                : array();
                                        }

                                        if (!empty($tour_dates)) {
                                            foreach ($tour_dates as $year => $tours_in_year) {
                                                echo '<div class="date-list">';
                                                // Drop the year from the header
                                                // when a year filter is active
                                                // since the year is implied.
                                                $bst_date_header = $bst_selected_year
                                                    ? 'Tour Dates'
                                                    : $year . ' Tour Dates';
                                                echo '<h3>' . esc_html( $bst_date_header ) . '</h3>';
                                                echo '<ul class="date-list date-list--smallblue">';
                                                foreach ($tours_in_year as $tour_date) {
                                                    $current_date = date('Y-m-d');
                                                    $start_date = date('Y-m-d', strtotime($tour_date['start_date']));
                                                    $end_date = date('Y-m-d', strtotime($tour_date['end_date']));
                                                    $availability = intval($tour_date['availability']); // Using available_slots field
                                                    
                                                    // Extract just the date portion from tour-date title (remove tour name)
                                                    $full_title = $tour_date['title'];
                                                    // Extract date portion from title (usually in parentheses at the end)
                                                    if (preg_match('/\(([^)]+)\)$/', $full_title, $matches)) {
                                                        $date_text = $matches[1]; // Just the date part
                                                    } else {
                                                        // Fallback to calculated date if pattern doesn't match
                                                        $date_text = date('j-n M Y', strtotime($start_date));
                                                        if ($start_date !== $end_date) {
                                                            $date_text .= ' to ' . date('j M Y', strtotime($end_date));
                                                        }
                                                    }
                                                    
                                                    // Get display information using centralized function for badges only
                                                    $display_info = bst_get_tour_date_display_info(
                                                        $start_date,
                                                        $end_date,
                                                        $availability
                                                    );
                                                    
                                                    $badge_class = $display_info['badge_class'];
                                                    $badge_text = $display_info['badge_text'];
                                                    echo '<li>';
                                                    echo '<span class="date-content">' . esc_html($date_text) . '</span>';
                                                    if ($badge_class) {
                                                        echo '<span class="badge '.$badge_class.'">'. $badge_text.'</span>';
                                                    } elseif ($display_info['status_badge_text']) {
                                                        echo '<span class="badge '.$display_info['status_badge_class'].'">'. $display_info['status_badge_text'].'</span>';
                                                    }
                                                    echo '</li>';
                                                }
                                                echo '</ul>';
                                                echo '</div>';
                                            }
                                        } else {
                                            echo '<p>No tour dates available.</p>';
                                        }
                                        ?>
                                    </div>
                                    <a href="<?php echo esc_url(get_permalink($tour_post->ID)); ?>" class="info-button">INFO</a>
                                </div>
                                <?php
                            endforeach;
                        else :
                            echo '<p>No tours found.</p>';
                        endif;
                        ?>
                    </div>
                </div>
                <br/>
            </div>
            <!-- Altered Content End -->
        </div> <!-- Close translucent-overlay -->
    </div>
</div>


<?php get_footer(); ?>