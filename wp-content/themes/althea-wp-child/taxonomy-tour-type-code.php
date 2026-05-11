<?php get_header(); ?>

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
                <img class="top-banner" src="<?php echo esc_url($banner_image); ?>" alt="<?php echo esc_attr($banner_text); ?> - Tour Category Banner">
                <h1 class="banner-text"><?php echo esc_html($banner_text); ?></h1>
            </div>
        </div>
        
        <!-- Breadcrumbs Section -->
        <div class="bst-breadcrumb-section">
            <div class="bst-breadcrumb-container has-share-buttons">
                <?php if (function_exists('bst_render_tour_archive_breadcrumbs')) {
                    bst_render_tour_archive_breadcrumbs($banner_text);
                } ?>
                
                <!-- DESKTOP FILTERS - inline with breadcrumbs -->
                <?php 
                // Check if tour rating is enabled in settings
                $enable_tour_rating = get_option('bst_enable_tour_rating', false);
                $current_term       = get_queried_object();
                $rating_terms       = array();

                if ($enable_tour_rating && function_exists('bst_get_tour_type_rating_terms')) {
                    $rating_terms = bst_get_tour_type_rating_terms($current_term);
                }

                if ($enable_tour_rating && !empty($rating_terms)) :
                ?>
                <div class="breadcrumb-filters">
                    <form method="GET" class="breadcrumb-filter-form">
                        <?php
                        // Get current filter values
                        $selected_rating = isset($_GET['tour_rating']) ? sanitize_text_field($_GET['tour_rating']) : '';
                        
                        if (!empty($rating_terms) && !is_wp_error($rating_terms)) :
                        ?>
                            <label for="tour_rating_desktop" class="breadcrumb-filter-label" style="text-transform: uppercase; font-weight: 600; color: #666; display: inline-block; font-size: 14px; letter-spacing: 1.0px; margin-right: 0;">Tour Class Filter</label>
                            <select name="tour_rating" id="tour_rating_desktop" class="breadcrumb-filter-select" onchange="this.form.submit()">
                                <option value="">All Classes</option>
                                <?php foreach ($rating_terms as $term) : ?>
                                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($selected_rating, $term->slug); ?>>
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="rating-help-btn" id="rating-help-btn" title="Learn about our tour classification system">ℹ</button>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endif; // Close enable_tour_rating && !empty(rating_terms) check ?>
                
                <!-- Share Buttons -->
                <div class="bst-share-buttons">
                    <span class="bst-share-label">Share:</span>
                    <?php 
                    // Get taxonomy term for sharing via helper
                    $term       = get_queried_object();
                    $share_meta = function_exists('bst_get_tour_type_term_share_metadata')
                        ? bst_get_tour_type_term_share_metadata($term)
                        : array('url' => get_term_link($term), 'email_label' => "Blue Strada's tours");
                    $share_url   = $share_meta['url'];
                    $email_label = $share_meta['email_label'];
                    // Use rawurlencode for mailto to preserve proper spacing
                    $email_subject = rawurlencode('Check out ' . $email_label);
                    $email_body    = rawurlencode('I thought you might like ' . $email_label . ':' . "\n\n" . $share_url);
                    ?>
                    <a href="mailto:?subject=<?php echo $email_subject; ?>&body=<?php echo $email_body; ?>" class="bst-share-icon" title="Email to a friend">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </a>
                    <a href="https://wa.me/?text=<?php echo urlencode('Check out ' . $email_label . ': ' . $share_url); ?>" class="bst-share-icon" title="Share on WhatsApp" target="_blank" rel="noopener">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                    </a>
                    <button onclick="bstCopyTourLink(event)" class="bst-share-icon bst-share-copy" title="Copy link to clipboard">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- MOBILE FILTERS - separate section for mobile -->
        <?php 
        // Reuse the same rating terms from above for mobile filters.
        if ($enable_tour_rating && !empty($rating_terms)) :
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
                <?php
                // Get current filter values
                $selected_rating_mobile = isset($_GET['tour_rating']) ? sanitize_text_field($_GET['tour_rating']) : '';
                
                if (!empty($rating_terms) && !is_wp_error($rating_terms)) :
                ?>
                    <label for="tour_rating_mobile" style="display: block; margin-bottom: 8px; font-weight: 600; color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: 1.0px;">Tour Class Filter</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <select name="tour_rating" id="tour_rating_mobile" style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px;" onchange="this.form.submit()">
                            <option value="">All Classes</option>
                            <?php foreach ($rating_terms as $term) : ?>
                                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($selected_rating_mobile, $term->slug); ?>>
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="rating-help-btn" id="rating-help-btn-mobile" title="Learn about our rating system" style="background: none; color: #666; border: none; width: 32px; height: 32px; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">ℹ</button>
                    </div>
                <?php endif; ?>
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
        </div>

        <div class="translucent-overlay">
            
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
                            
                            // Sort by listing_sort_order
                            usort($tours, function($a, $b) {
                                return intval($a['listing_sort_order']) - intval($b['listing_sort_order']);
                            });
                            
                            foreach ($tours as $tour) :
                                $tour_post = get_post($tour['ID']);
                                $title = str_replace(array("Motorcycle ", "Miata ", "Jeep "), "", get_the_title($tour_post));
                                $description = get_field('listing_description', $tour_post->ID);
                                $image = get_field('listing_image', $tour_post->ID);
                                $new = get_field('new', $tour_post->ID);
                                $title_modifier = get_field('title_modifier', $tour_post->ID);
                                
                                // Get tour rating from ACF field
                                $tour_rating = get_field('tour_rating', $tour_post->ID);
                                ?>
                                <div class="tour-box">
                                    <h2><?php echo $title; ?></h2>
                                    <div class="listing-image-container">
                                        <a href="<?php echo get_permalink($tour_post->ID); ?>">
                                            <?php if ($new) : ?>
                                                <img class="new-icon" border="0" src="<?php echo get_stylesheet_directory_uri(); ?>/images/new-icon-corner.jpg" alt="New Icon">
                                            <?php endif; ?>
                                            <?php if ($enable_tour_rating && $tour_rating) : ?>
                                                <div class="medal-rating-overlay" data-tooltip="<?php echo htmlspecialchars($tour_rating->description ? $tour_rating->description : $tour_rating->name, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <span style="color: white; font-size: 12px; font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.8); white-space: nowrap;"><?php echo esc_html($tour_rating->name); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <img src="<?php echo $image; ?>" alt="<?php echo $title; ?>">
                                            <?php if ($title_modifier) : ?>
                                            <div class="overlay-text">
                                                    <span class="badge green"><?php echo $title_modifier; ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <p><?php echo $description; ?></p>
                                    
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
                                        
                                        echo '<p class="tour-pricing-info" style="margin: 5px 0; font-size: 14px; font-weight: 600; color: #2c5aa0; text-align: center;">Prices starting at ' . $formatted_price . ' per person</p>';
                                    }
                                    ?>
                                    <div class="date-container">
                                        <?php
                                        $tourid = $tour['ID'];
                                        $tour_dates = array();
                                        $args = array(
                                            'post_type' => 'tour-date',
                                            'post_status' => 'publish',  // Only get published tour dates
                                            'meta_query' => array(
                                                array(
                                                    'key' => 'tour',
                                                    'value' => $tourid,
                                                    'compare' => '='
                                                )
                                            ),
                                            'posts_per_page' => -1,
                                            'orderby' => 'meta_value',
                                            'meta_key' => 'start_date',
                                            'order' => 'ASC'
                                        );
                                        $tour_date_query = new WP_Query($args);
                                        if ($tour_date_query->have_posts()) {
                                            while ($tour_date_query->have_posts()) {
                                                $tour_date_query->the_post();
                                                $start_date_raw = get_post_meta(get_the_ID(), 'start_date', true);
                                                $end_date_raw   = get_post_meta(get_the_ID(), 'end_date', true);
                                                if ( function_exists( 'bst_tour_date_show_on_public_schedule' )
                                                    && ! bst_tour_date_show_on_public_schedule( $start_date_raw, (int) $tourid ) ) {
                                                    continue;
                                                }
                                                $year = function_exists( 'bst_tour_date_acf_date_meta_to_ymd' )
                                                    ? (int) substr( (string) bst_tour_date_acf_date_meta_to_ymd( $start_date_raw ), 0, 4 )
                                                    : (int) date( 'Y', strtotime( $start_date_raw ) );
                                                if ( $year <= 0 ) {
                                                    continue;
                                                }
                                                $start_date_for_row = function_exists( 'bst_tour_date_acf_date_meta_to_ymd' )
                                                    ? bst_tour_date_acf_date_meta_to_ymd( $start_date_raw )
                                                    : $start_date_raw;
                                                $end_date_for_row = function_exists( 'bst_tour_date_acf_date_meta_to_ymd' )
                                                    ? bst_tour_date_acf_date_meta_to_ymd( $end_date_raw )
                                                    : $end_date_raw;
                                                if ( $start_date_for_row === '' || $end_date_for_row === '' ) {
                                                    continue;
                                                }
                                                if (!isset($tour_dates[$year])) {
                                                    $tour_dates[$year] = array();
                                                }
                                                $tour_dates[$year][] = array(
                                                    'id' => get_the_ID(),
                                                    'title' => get_the_title(),
                                                    'start_date' => $start_date_for_row,
                                                    'end_date' => $end_date_for_row,
                                                    'availability' => get_post_meta(get_the_ID(), 'available_slots', true)
                                                );
                                            }
                                            wp_reset_postdata();
                                        }
                                        // Sort tour dates by start_date within each year
                                        if (!empty($tour_dates)) {
                                            foreach ($tour_dates as $year => &$tours_in_year) {
                                                usort($tours_in_year, function($a, $b) {
                                                    return strtotime($a['start_date']) - strtotime($b['start_date']);
                                                });
                                            }
                                            unset($tours_in_year); // Break the reference
                                        }
                                        if (!empty($tour_dates)) {
                                            foreach ($tour_dates as $year => $tours_in_year) {
                                                echo '<div class="date-list">';
                                                echo '<h3>' . $year . ' Tour Dates</h3>';
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
                                    <br/>
                                    <a href="<?php echo get_permalink($tour_post->ID); ?>" class="info-button">INFO</a>
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