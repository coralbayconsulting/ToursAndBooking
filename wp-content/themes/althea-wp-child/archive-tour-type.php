<?php get_header(); ?>

<?php
// =============================================================================
// BST Filter Pre-Pass (archive)
// -----------------------------------------------------------------------------
// Walks every tour-type post in the main query and resolves its associated
// tours-by-year data. We use this to (a) populate the "Filter by Year"
// dropdown rendered in the breadcrumb/mobile filter blocks below and (b) avoid
// calling bst_get_tours_by_year_for_tour_type() a second time inside the main
// rendering loop.
// =============================================================================

$bst_selected_year = isset( $_GET['tour_year'] ) && $_GET['tour_year'] !== ''
    ? (int) sanitize_text_field( wp_unslash( $_GET['tour_year'] ) )
    : 0;

$bst_archive_data    = array(); // [tour-type post_id => $tours_by_year]
$bst_available_years = array();

if ( have_posts() ) {
    global $wp_query;
    if ( $wp_query && ! empty( $wp_query->posts ) ) {
        foreach ( $wp_query->posts as $bst_pp_post ) {
            $bst_pp_type_code = get_field( 'type_code', $bst_pp_post->ID );
            $bst_pp_tby       = ( $bst_pp_type_code && function_exists( 'bst_get_tours_by_year_for_tour_type' ) )
                ? bst_get_tours_by_year_for_tour_type( $bst_pp_type_code->term_id )
                : array();
            if ( ! empty( $bst_pp_tby ) ) {
                $bst_archive_data[ $bst_pp_post->ID ] = $bst_pp_tby;
                foreach ( array_keys( $bst_pp_tby ) as $bst_y ) {
                    $bst_y_int = (int) $bst_y;
                    if ( $bst_y_int > 0 && ! in_array( $bst_y_int, $bst_available_years, true ) ) {
                        $bst_available_years[] = $bst_y_int;
                    }
                }
            }
        }
    }
}

sort( $bst_available_years );
$bst_available_years = array_values( array_unique( $bst_available_years ) );

if ( $bst_selected_year && ! in_array( $bst_selected_year, $bst_available_years, true ) ) {
    $bst_selected_year = 0;
}

$bst_show_years  = ! empty( $bst_available_years );
$bst_show_filter = $bst_show_years; // no rating filter on this archive
?>

<div class="page-content">
    <div id="content" class="content">

        <!-- Altered Content Start -->
        <div class="translucent-overlay">

            <!-- TOP BANNER -->
            <div class="top-banner-container">
                <?php $bst_archive_title = bst_get_tour_type_post_type_archive_display_title(); ?>
                <img class="top-banner" src="<?php echo esc_url(get_option('bst_banner_image')); ?>" alt="<?php echo esc_attr($bst_archive_title); ?> - Banner Image" fetchpriority="high">
                <h1 class="banner-text"><?php echo esc_html($bst_archive_title); ?></h1>
            </div>
        </div>
        
        <!-- Breadcrumbs Section -->
        <div class="bst-breadcrumb-section">
            <div class="bst-breadcrumb-container has-share-buttons">
                <?php if (function_exists('bst_render_tour_archive_breadcrumbs')) {
                    bst_render_tour_archive_breadcrumbs('');
                } ?>

                <!-- DESKTOP FILTERS - inline with breadcrumbs -->
                <?php if ( $bst_show_filter ) : ?>
                <div class="breadcrumb-filters">
                    <form method="GET" class="breadcrumb-filter-form">
                        <label class="breadcrumb-filter-label" style="text-transform: uppercase; font-weight: 600; color: #666; display: inline-block; font-size: 14px; letter-spacing: 1.0px; margin-right: 0;">Filter by Year</label>
                        <select name="tour_year" id="tour_year_desktop" class="breadcrumb-filter-select" aria-label="Filter by year">
                            <option value="">All Years</option>
                            <?php foreach ( $bst_available_years as $bst_y ) : ?>
                                <option value="<?php echo esc_attr( $bst_y ); ?>" <?php selected( $bst_selected_year, $bst_y ); ?>>
                                    <?php echo esc_html( $bst_y ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="breadcrumb-filter-btn">Filter</button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Share Buttons -->
                <?php
                $share_meta = function_exists( 'bst_get_tour_archive_share_metadata' )
                    ? bst_get_tour_archive_share_metadata()
                    : array(
                        'url'         => get_post_type_archive_link( 'tour-type' ),
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
        <?php if ( $bst_show_filter ) : ?>
        <div class="mobile-filters" style="display: none; padding: 15px 20px; background: #f8f8f8; border-bottom: 1px solid #ddd;">
            <style>
                @media (max-width: 768px) {
                    .mobile-filters {
                        display: block !important;
                    }
                    .bst-breadcrumb-section {
                        display: none !important;
                    }
                }
                @media (min-width: 769px) {
                    .mobile-filters {
                        display: none !important;
                    }
                }
            </style>
            <form method="GET" class="mobile-filter-form">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: 1.0px;">Filter by Year</label>
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
                <div style="text-align: center;">
                    <button type="submit" class="breadcrumb-filter-btn" style="display: inline-flex; padding: 10px 28px;">Filter</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="translucent-overlay">
            
            <!-- TOUR LISTINGS -->

            <div class="h-section-grid-container h-section-boxed-container"> <!--original div-->
                <div data-colibri-id="4640-c7" class="h-row-container gutters-row-lg-1 gutters-row-md-1 gutters-row-0 gutters-row-v-lg-1 gutters-row-v-md-1 gutters-row-v-1 style-1802 style-local-4640-c7 position-relative"><!--original div-->
                    <div class="h-row justify-content-lg-center justify-content-md-center justify-content-center align-items-lg-stretch align-items-md-stretch align-items-stretch gutters-col-lg-1 gutters-col-md-1 gutters-col-0 gutters-col-v-lg-1 gutters-col-v-md-1 gutters-col-v-1"><!--original div-->

                        
                        <?php
                        
                        while (have_posts()) : the_post();
                            $title = get_the_title();
                            $id = get_the_ID();
                            $description = get_field('listing_description');
                            $image = get_field('listing_image');
                            $type_code_field = get_field('type_code', $id);
                            $taxonomy_url = '';
                            if ($type_code_field) {
                                $term_id = $type_code_field->term_id;
                                $term = get_term($term_id, 'tour-type-code');
                                if ($term && !is_wp_error($term)) {
                                    $taxonomy_url = get_term_link($term);
                                }
                            } else {
                                error_log('No type_code ACF field found for post ID: ' . $id);
                            }

                            // Pull dates from the pre-pass cache so we don't
                            // re-query tours/tour-dates inside the loop.
                            $tours_by_year = isset( $bst_archive_data[ $id ] )
                                ? $bst_archive_data[ $id ]
                                : array();

                            // Apply year filter: if a year is selected, restrict
                            // to that year and skip cards with no dates in it.
                            if ( $bst_selected_year ) {
                                $tours_by_year = isset( $tours_by_year[ $bst_selected_year ] )
                                    ? array( $bst_selected_year => $tours_by_year[ $bst_selected_year ] )
                                    : array();
                            }

                            if ( empty( $tours_by_year ) ) {
                                continue;
                            }
                            ?>
                            <div class="tour-type-box">
                                <h2>
                                    <?php echo esc_html($title); ?>
                                </h2>
                                <div class="listing-image-container">
                                    <a href="<?php echo esc_url($taxonomy_url); ?>">
                                        <?php echo wp_get_attachment_image( $image, 'tour-listing', false, [ 'alt' => esc_attr( $title ) . ' - Tour Category', 'loading' => 'lazy', 'sizes' => '(max-width: 600px) calc(100vw - 40px), 300px' ] ); ?>
                                    </a>
                                </div>
                                <p><?php echo wp_kses_post($description); ?></p>
                                <div class="date-container">
                                    <?php
                                    foreach ($tours_by_year as $year => $tours) {
                                        echo '<div class="date-list">';
                                        // Drop the year from the header when a
                                        // year filter is active since the year
                                        // is implied.
                                        $bst_date_header = $bst_selected_year
                                            ? 'Tour Dates'
                                            : $year . ' Tour Dates';
                                        echo '<h3>' . esc_html( $bst_date_header ) . '</h3>';
                                        echo '<ul class="date-list date-list--smallblue">';
                                        foreach ($tours as $tour) {
                                            // Strip a trailing "(YYYY)" from the
                                            // individual tour title when the
                                            // page is filtered to that year, so
                                            // the year isn't repeated next to
                                            // every row.
                                            $tour_label = $tour['title'];
                                            if ( $bst_selected_year ) {
                                                $tour_label = preg_replace(
                                                    '/\s*\(' . preg_quote( (string) $bst_selected_year, '/' ) . '\)\s*$/',
                                                    '',
                                                    $tour_label
                                                );
                                            }
                                            echo '<li>';
                                            echo '<span class="date-content">' . esc_html($tour['date_text']) . ': <a href="' . esc_url($tour['permalink']) . '">' . esc_html($tour_label) . '</a></span>';
                                            if (!empty($tour['badge_class'])) {
                                                echo '<span class="badge ' . esc_attr($tour['badge_class']) . '">' . esc_html($tour['badge_text']) . '</span>';
                                            } elseif (!empty($tour['status_badge_text'])) {
                                                echo '<span class="badge ' . esc_attr($tour['status_badge_class']) . '">' . esc_html($tour['status_badge_text']) . '</span>';
                                            }
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                                <a href="<?php echo esc_url($taxonomy_url); ?>" class="info-button">INFO</a>
                            </div>
                        <?php
                    endwhile;
                        ?>
                        </div>
                    </div>
                </div>
                <br/>
            </div>
            <!-- Altered Content End -->

        </div>
    </div>


<?php get_footer(); ?>