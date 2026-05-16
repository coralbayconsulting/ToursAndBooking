<?php get_header(); ?>

<?php
// Initialize tour type variables via helper for reuse
$tour_type_id    = null;
$tour_type_title = '';
$tour_type_slug  = '';

if (function_exists('bst_get_tour_type_for_tour')) {
    $tour_type_info  = bst_get_tour_type_for_tour(get_the_ID());
    $tour_type_id    = $tour_type_info['id'];
    $tour_type_title = $tour_type_info['title'];
    $tour_type_slug  = $tour_type_info['slug'];
}

// SEO Meta Tags for single tour pages (only when Yoast is not active — Yoast outputs these in wp_head).
if (is_singular('tour') && !defined('WPSEO_VERSION')) {
    $tour_title = get_the_title();
    $short_description = get_field('short_description');
    $banner_image = get_field('detail_banner_image');
    
    // Create meta description
    $meta_description = wp_strip_all_tags($short_description);
    if (strlen($meta_description) > 160) {
        $meta_description = substr($meta_description, 0, 157) . '...';
    }
    
    echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
    echo '<meta name="robots" content="index, follow">' . "\n";
    echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($tour_title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($banner_image) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:site_name" content="Blue Strada Tours">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($tour_title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($meta_description) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($banner_image) . '">' . "\n";
}
?>

<div class="page-content">
    <div id="content" class="content">
        <div data-colibri-id="2559-c1" class="style-1078 style-local-2559-c1 position-relative">
            <!--begin custom content-->
            <div class="translucent-overlay">
                <?php
                $tourid = get_the_ID();
                $tour_title = get_the_title();
                // Retrieve ACF fields
                $banner_image = get_field('detail_banner_image');
                $short_description = get_field('short_description');
                $starting_from = get_field('starting_from');
                $airport = get_field('airport');
                $ending_at = get_field('ending_at');
                $airport_2 = get_field('airport_2');
                $hospitality = get_field('hospitality');
                $roads = get_field('roads');
                $about = get_field('about');
                $schedule = get_field('schedule');
                $image_1 = get_field('image_1');
                $image_2 = get_field('image_2');
                $image_3 = get_field('image_3');
                $image_4 = get_field('image_4');
                $image_5 = get_field('image_5');
                $image_6 = get_field('image_6');
                $included = get_field('included');
                $not_included = get_field('not_included');
                
                // Get deposit settings for JavaScript - try both possible field name formats
                $deposit_type = get_field('deposit_type', get_the_ID());
                if (!$deposit_type) {
                    // Try button group format from screenshot
                    $deposit_type_buttons = get_field('deposit_type', get_the_ID());
                    if (is_array($deposit_type_buttons)) {
                        $deposit_type = $deposit_type_buttons[0];
                    }
                }
                
                $deposit_percent = get_field('deposit_percent', get_the_ID());
                $deposit_fixed_single = get_field('deposit_fixed_single', get_the_ID());
                $deposit_fixed_double = get_field('deposit_fixed_double', get_the_ID());
                
                // Get extension settings
                $extension_offered = get_field('extension_offered', get_the_ID());
                $extension_title = get_field('extension_title', get_the_ID());
                $extension_pricing = get_field('extension_pricing', get_the_ID());
                $extension_days = get_field('extension_driving_days', get_the_ID());
                $admin_vehicle_driving_days = get_field('admin_vehicle_driving_days', get_the_ID());
                
                // Get tour rating from ACF field and build medal markup via helper
                $tour_rating   = get_field('tour_rating', get_the_ID());
                $medal_display = '';
                if ($tour_rating && function_exists('bst_get_tour_rating_medal_markup')) {
                    $medal_display = bst_get_tour_rating_medal_markup($tour_rating);
                }

                // Get the vehicle_descriptor and package_image fields from the tour type
                $vehicle_descriptor = get_field('vehicle_descriptor', $tour_type_id);
                $package_image = get_field('package_image', $tour_type_id);
                
                // Get the list of dates for the tour from the tour-date CPT via helper
                $tour_dates = array();
                if (function_exists('bst_get_tour_dates_grouped_by_year')) {
                    $tour_dates = bst_get_tour_dates_grouped_by_year($tourid);
                }
                // Count the number of items in the $years array
                $years_count = count($tour_dates);
                
                // Output JSON-LD structured data for SEO via helper
                if (function_exists('bst_output_tour_json_ld')) {
                    bst_output_tour_json_ld(
                        $tourid,
                        $tour_type_id,
                        $tour_title,
                        $short_description,
                        $schedule,
                        $starting_from,
                        $tour_dates
                    );
                }
                ?>
                
                <div class="top-banner-container">
                    <img class="top-banner" src="<?php echo esc_url($banner_image); ?>" alt="<?php echo esc_attr($tour_title); ?> - Tour Banner">
                    <h1 class="banner-text"><?php the_title(); ?></h1>
                </div>

                <!-- Breadcrumbs Section -->
                <div class="bst-breadcrumb-section">
                    <div class="bst-breadcrumb-container has-share-buttons">
                        <ol class="bst-breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">
                            <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <a href="<?php echo home_url('/'); ?>" class="bst-breadcrumb-link" itemprop="item">
                                    <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="home" viewBox="0 0 1664 1896.0833" class="bst-breadcrumb-home-icon">
                                        <path d="M1408 992v480q0 26-19 45t-45 19H960v-384H704v384H320q-26 0-45-19t-19-45V992q0-1 .5-3t.5-3l575-474 575 474q1 2 1 6zm223-69l-62 74q-8 9-21 11h-3q-13 0-21-7L832 424l-692 577q-12 8-24 7-13-2-21-11l-62-74q-8-10-7-23.5T37 878l719-599q32-26 76-26t76 26l244 204V288q0-14 9-23t23-9h192q14 0 23 9t9 23v408l219 182q10 8 11 21.5t-7 23.5z" />
                                    </svg>
                                    <span itemprop="name">Home</span>
                                </a>
                                <meta itemprop="position" content="1" />
                            </li>
                            <li class="bst-breadcrumb-separator">/</li>
                            <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <a href="<?php echo get_post_type_archive_link('tour-type'); ?>" class="bst-breadcrumb-link" itemprop="item">
                                    <span itemprop="name">OUR TOURS</span>
                                </a>
                                <meta itemprop="position" content="2" />
                            </li>
                            <li class="bst-breadcrumb-separator">/</li>
                            <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <?php 
                                // Get the tour type title and link from taxonomy
                                $breadcrumb_tour_type_title = '';
                                $breadcrumb_tour_type_link = '';
                                
                                // Get taxonomy terms for breadcrumbs
                                $breadcrumb_taxonomy_terms = get_the_terms(get_the_ID(), 'tour-type-code'); // Replace with your taxonomy name
                                if ($breadcrumb_taxonomy_terms && !is_wp_error($breadcrumb_taxonomy_terms)) {
                                    $breadcrumb_taxonomy_term = $breadcrumb_taxonomy_terms[0];
                                    $breadcrumb_tour_type_link = get_term_link($breadcrumb_taxonomy_term);
                                    
                                    // Use the tour-type post title if we have it, otherwise use taxonomy term name
                                    if ($tour_type_id && $tour_type_title) {
                                        $breadcrumb_tour_type_title = $tour_type_title;
                                    } else {
                                        $breadcrumb_tour_type_title = $breadcrumb_taxonomy_term->name;
                                    }
                                }
                                ?>
                                <?php if ($breadcrumb_tour_type_link && !is_wp_error($breadcrumb_tour_type_link)): ?>
                                    <a href="<?php echo esc_url($breadcrumb_tour_type_link); ?>" class="bst-breadcrumb-link" itemprop="item">
                                        <span itemprop="name"><?php echo strtoupper($breadcrumb_tour_type_title); ?></span>
                                    </a>
                                <?php else: ?>
                                    <span class="bst-breadcrumb-text" itemprop="name"><?php echo strtoupper($breadcrumb_tour_type_title); ?></span>
                                <?php endif; ?>
                                <meta itemprop="position" content="3" />
                            </li>
                            <li class="bst-breadcrumb-separator">/</li>
                            <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <span class="bst-breadcrumb-text" itemprop="name"><?php echo strtoupper(get_the_title()); ?></span>
                                <meta itemprop="position" content="4" />
                            </li>
                        </ol>
                        
                        <!-- Share Buttons -->
                        <div class="bst-share-buttons">
                            <span class="bst-share-label">Share:</span>
                            <?php 
                            // Get clean title without HTML entities for sharing
                            $share_title = html_entity_decode(get_the_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $share_url = get_permalink();
                            // Use rawurlencode for mailto to preserve proper spacing
                            $email_subject = rawurlencode('Check out this tour: ' . $share_title);
                            $email_body = rawurlencode('I thought you might like this tour:' . "\n\n" . $share_title . "\n" . $share_url);
                            ?>
                            <a href="mailto:?subject=<?php echo $email_subject; ?>&body=<?php echo $email_body; ?>" class="bst-share-icon" title="Email to a friend">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                            </a>
                            <a href="https://wa.me/?text=<?php echo urlencode('Check out this tour: ' . $share_title . ' ' . $share_url); ?>" class="bst-share-icon" title="Share on WhatsApp" target="_blank" rel="noopener">
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

                <!-- Content wrapper to center columnsaround together -->
                <div class="page-content-wrapper">                    
                    <div class="two-column-container">
                    <!-- LEFT COLUMN -->
                    <div class="left-column">
                        <h2>What</h2>
                        <p><?php echo $short_description; ?></p>
                        
                        <?php 
                        // Check if tour rating is enabled in settings
                        $enable_tour_rating = get_option('bst_enable_tour_rating', false);
                        if ($enable_tour_rating && $tour_rating) : 
                        ?>
                        <div class="tour-rating-section">
                            <p class="tour-rating-display" style="display: flex; align-items: center;">
                                <span class="tour-rating-tooltip-wrapper" data-tooltip="<?php echo htmlspecialchars($tour_rating->description ? $tour_rating->description : $tour_rating->name, ENT_QUOTES, 'UTF-8'); ?>" style="display: flex; align-items: center;">
                                    <span class="tour-class-label">Tour Class:</span><?php echo $medal_display; ?>
                                </span>
                            </p>
                        </div>
                        <script>
                        // Ensure tooltip is initialized for the rating section
                        jQuery(document).ready(function($) {
                            // Direct initialization approach
                            function initRatingTooltip() {
                                const tooltipElement = $('.tour-rating-tooltip-wrapper[data-tooltip]');
                                if (tooltipElement.length > 0) {
                                    // Force re-initialization
                                    if (typeof window.initCustomTooltips === 'function') {
                                        window.initCustomTooltips();
                                    }
                                }
                            }
                            
                            // Try multiple times to ensure it works
                            setTimeout(initRatingTooltip, 100);
                            setTimeout(initRatingTooltip, 500);
                            setTimeout(initRatingTooltip, 1000);
                        });
                        </script>
                        <?php endif; ?>
                        
                        <div>
                            <?php
                            if (count($tour_dates) > 0) {
                                ksort($tour_dates, SORT_NATURAL);
                                foreach ($tour_dates as $year => $dates) {
                                    usort($dates, function($a, $b) {
                                        return strcmp((string) $a['start_date'], (string) $b['start_date']);
                                    });

                                    echo '<h2>' . $year . ' Tour Dates</h2>';
                                    echo '<div class="date-container">';
                                    echo '<ul class="date-list date-list--blue">';
                                    foreach ($dates as $tour_date) {
                                        $display_info = bst_get_tour_date_display_info(
                                            $tour_date['start_date'],
                                            $tour_date['end_date'],
                                            $tour_date['availability'],
                                            $extension_offered,
                                            $tour_date['date_extension_offered'],
                                            $extension_days
                                        );
                                        ?>
                                        <li>
                                            <span class="date-content"><?php echo $display_info['date_text']; ?></span>
                                            <?php if ($display_info['badge_text']) : ?>
                                                <span class="badge <?php echo $display_info['badge_class']; ?>"><?php echo $display_info['badge_text']; ?></span>
                                            <?php elseif ($display_info['status_badge_text']) : ?>
                                                <span class="badge <?php echo $display_info['status_badge_class']; ?>"><?php echo $display_info['status_badge_text']; ?></span>
                                            <?php endif; ?>
                                        </li>
                                        <?php
                                    }
                                    echo '</ul>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p>No tour dates available.</p>';
                            }
                            ?>
                        </div>

                        <h2>Starting From</h2>
                        <p><?php echo $starting_from.' - '.$airport; ?></p>

                        <?php if (!empty($ending_at)) : ?>
                        <h2>Ending At</h2>
                        <p><?php echo $ending_at.' - '.$airport_2; ?></p>
                        <?php endif; ?>

                        <h2>Hospitality</h2>
                        <p><?php echo $hospitality; ?></p>

                        <h2>Roads</h2>
                        <p><?php echo $roads; ?></p>

                        <h2>About the Tour</h2>
                        <?php echo $about; ?>

                        <?php
                        // Stable sort comparator: orders by `sort_order` ascending
                        // and uses original row index as a tiebreaker so ties keep
                        // the order they appear in the ACF repeater.
                        $bst_sort_by_order = function ( $a, $b ) {
                            $diff = $a['sort_order'] - $b['sort_order'];
                            return ( $diff !== 0 ) ? $diff : ( $a['_index'] - $b['_index'] );
                        };

                        // Documents section. Hidden entirely when the ACF repeater
                        // is empty or every row is missing a file.
                        $documents_rows  = function_exists( 'get_field' ) ? get_field( 'documents', $tourid ) : null;
                        $documents_valid = array();
                        if ( is_array( $documents_rows ) ) {
                            $doc_index = 0;
                            foreach ( $documents_rows as $doc_row ) {
                                if ( ! is_array( $doc_row ) ) {
                                    continue;
                                }
                                $doc_file = function_exists( 'bst_normalize_acf_file_field' )
                                    ? bst_normalize_acf_file_field( isset( $doc_row['file'] ) ? $doc_row['file'] : null )
                                    : null;
                                if ( $doc_file && ! empty( $doc_file['url'] ) ) {
                                    $doc_desc  = isset( $doc_row['description'] ) ? trim( (string) $doc_row['description'] ) : '';
                                    if ( $doc_desc !== '' ) {
                                        $doc_title = $doc_desc;
                                    } elseif ( ! empty( $doc_file['title'] ) ) {
                                        $doc_title = $doc_file['title'];
                                    } elseif ( ! empty( $doc_file['filename'] ) ) {
                                        $doc_title = $doc_file['filename'];
                                    } else {
                                        $doc_title = $doc_file['url'];
                                    }
                                    $documents_valid[] = array(
                                        'url'        => $doc_file['url'],
                                        'title'      => $doc_title,
                                        'sort_order' => ( isset( $doc_row['sort_order'] ) && $doc_row['sort_order'] !== '' ) ? (int) $doc_row['sort_order'] : 9999,
                                        '_index'     => $doc_index,
                                    );
                                }
                                $doc_index++;
                            }
                            if ( ! empty( $documents_valid ) ) {
                                usort( $documents_valid, $bst_sort_by_order );
                            }
                        }
                        if ( ! empty( $documents_valid ) ) : ?>
                            <h2>Documents</h2>
                            <ul class="documents-list">
                                <?php foreach ( $documents_valid as $doc ) : ?>
                                    <li class="documents-item">
                                        <a href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $doc['title'] ); ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php
                        // Related Links section. Hidden entirely when the ACF
                        // repeater is empty or every row is missing a URL.
                        $related_links_rows  = function_exists( 'get_field' ) ? get_field( 'related_links', $tourid ) : null;
                        $related_links_valid = array();
                        if ( is_array( $related_links_rows ) ) {
                            $rl_index = 0;
                            foreach ( $related_links_rows as $rl_row ) {
                                $rl_link = isset( $rl_row['link'] ) ? $rl_row['link'] : null;
                                if ( is_array( $rl_link ) && ! empty( $rl_link['url'] ) ) {
                                    $related_links_valid[] = array(
                                        'url'         => $rl_link['url'],
                                        'title'       => ! empty( $rl_link['title'] ) ? $rl_link['title'] : $rl_link['url'],
                                        'target'      => ! empty( $rl_link['target'] ) ? $rl_link['target'] : '',
                                        'description' => isset( $rl_row['description'] ) ? $rl_row['description'] : '',
                                        'sort_order'  => ( isset( $rl_row['sort_order'] ) && $rl_row['sort_order'] !== '' ) ? (int) $rl_row['sort_order'] : 9999,
                                        '_index'      => $rl_index,
                                    );
                                }
                                $rl_index++;
                            }
                            if ( ! empty( $related_links_valid ) ) {
                                usort( $related_links_valid, $bst_sort_by_order );
                            }
                        }
                        if ( ! empty( $related_links_valid ) ) : ?>
                            <h2>Related Links</h2>
                            <ul class="related-links-list">
                                <?php foreach ( $related_links_valid as $rl ) :
                                    $rl_target_attr = $rl['target'] ? ' target="' . esc_attr( $rl['target'] ) . '"' : '';
                                    $rl_rel_attr    = ( $rl['target'] === '_blank' ) ? ' rel="noopener noreferrer"' : '';
                                ?>
                                    <li class="related-links-item">
                                        <a href="<?php echo esc_url( $rl['url'] ); ?>"<?php echo $rl_target_attr . $rl_rel_attr; ?>><?php echo esc_html( $rl['title'] ); ?></a>
                                        <?php if ( ! empty( $rl['description'] ) ) : ?>
                                            <span class="related-links-description"> &mdash; <?php echo esc_html( $rl['description'] ); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (!empty($package_image)) : ?>
                        <h2>How Much It Costs</h2>
                        <p class="small-text">You can choose any of the following packages:</p>
                        <div class="image-container">
                            <img src="<?php echo $package_image; ?>" alt="<?php echo esc_attr($tour_title); ?> tour packages and pricing options for <?php echo esc_attr($tour_type_title); ?> tours">
                        </div>
                        <p class="small-text">(*) With another participant. Matching preferences will be requested while booking. In case of no match, you will be able to upgrade to a single room or receive a full refund.</p>
                        <?php endif; ?>

                        <?php
                        // Add pricing text: "Prices starting at" + half the cost of package 3 + "per person"
                        $package_pricing = get_field('package_pricing', get_the_ID());
                        if ($package_pricing && !empty($package_pricing['package_3'])) {
                            $package_3_price = floatval($package_pricing['package_3']);
                            $half_package_3_price = $package_3_price / 2;
                            
                            // Get tour currency, default to EUR
                            $tour_currency = get_field('currency', get_the_ID());
                            if (empty($tour_currency)) {
                                $tour_currency = 'EUR';
                            }
                            
                            // Round to nearest euro and format with thousands separator but no decimals
                            $rounded_price = round($half_package_3_price);
                            $symbol = ($tour_currency === 'USD') ? '$' : '€';
                            $formatted_price = $symbol . number_format($rounded_price, 0);
                            
                            echo '<p class="tour-pricing-info" style="margin: 5px 0; font-size: 14px; font-weight: 600; color: #2c5aa0; text-align: center;">Prices starting at ' . $formatted_price . ' per person</p>';
                        }
                        ?>

                        <div id="tourBookingForm" data-tour-id="<?php echo esc_attr($tourid); ?>" data-tour-title="<?php echo esc_attr($tour_title); ?>" data-tour-type="<?php echo esc_attr($tour_type_slug); ?>" data-vehicle-descriptor="<?php echo esc_attr($vehicle_descriptor); ?>">
                        <p class="booking-instruction">Please select the options below to get your personalized quote.</p>
                            <?php 
                            $help_currency = get_field('currency', get_the_ID());
                            if (empty($help_currency)) {
                                $help_currency = 'EUR';
                            }
                            $currency_name = ($help_currency === 'USD') ? 'US Dollars' : 'Euros';
                            ?>
                            <p class="small-text">Our tours are priced in <?php echo esc_html($currency_name); ?>. A currency converter with up-to-date rates is provided. We recommend using a credit card with no foreign transaction fees.</p>
                            <div class="booking-columns">
                                <div class="booking-left"> <!-- left column -->
                                    <label for="tourdatedropdown">Tour Date</label>
                                    <select id="tourdatedropdown" name="tour_date" class="tourdatedropdown" data-tour-id="<?php echo $tourid; ?>">
                                        <option value="">Select a Tour Date</option>
                                        <?php 
                                        // Create a flat array of all tour dates for sorting
                                        $all_dates = array();
                                        foreach ($tour_dates as $year => $dates) {
                                            foreach ($dates as $tour_date) {
                                                $all_dates[] = $tour_date;
                                            }
                                        }
                                        // Sort all dates by start_date
                                        usort($all_dates, function($a, $b) {
                                            return strcmp((string) $a['start_date'], (string) $b['start_date']);
                                        });
                                        
                                        $dropdown_options_added = 0;
                                        foreach ($all_dates as $tour_date) : ?>
                                            <?php
                                            $start_date = $tour_date['start_date'];
                                            $end_date = $tour_date['end_date'];
                                            $current_date = date('Y-m-d'); 
                                            
                                            // Normalize start_date to Y-m-d format for consistent comparison
                                            $normalized_start_date = date('Y-m-d', strtotime($start_date));
                                            $normalized_end_date = date('Y-m-d', strtotime($end_date));
                                            
                                            $available_slots = intval($tour_date['availability']);
                                            
                                            // Only include the tour date if today is earlier than the start date
                                            if ($current_date < $normalized_start_date) {
                                                $dropdown_options_added++;
                                                // Use standardized tour date title - extract date range from parentheses
                                                $tour_date_post = get_post($tour_date['id']);
                                                $date_text = $tour_date_post ? $tour_date_post->post_title : '';
                                                if ($tour_date_post && preg_match('/\(([^)]*\d{1,2}\s+\w+.*?\d{4})\)$/', $tour_date_post->post_title, $matches)) {
                                                    $date_text = $matches[1];
                                                } else {
                                                    // Fallback to manual formatting if title doesn't match expected pattern
                                                    $date_text = (date('M', strtotime($normalized_start_date)) == date('M', strtotime($normalized_end_date))) 
                                                        ? date('j', strtotime($normalized_start_date)) . '-' . date('j M Y', strtotime($normalized_end_date))
                                                        : date('j M', strtotime($normalized_start_date)) . ' - ' . date('j M Y', strtotime($normalized_end_date));
                                                }

                                                // Append "Sold Out" if all slots are taken
                                                $is_sold_out = ($available_slots <= 0);
                                                if ($is_sold_out) {
                                                    $date_text .= ' (Sold Out)';
                                                }
                                                ?>
                                                <option value="<?php echo $tour_date['id']; ?>" data-start-date="<?php echo $normalized_start_date; ?>" data-end-date="<?php echo $normalized_end_date; ?>" data-sold-out="<?php echo $is_sold_out ? 'true' : 'false'; ?>">
                                                    <?php echo $date_text; ?>
                                                </option>
                                            <?php } ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="tourpackagedropdown">Tour Package</label>
                                    <select id="tourpackagedropdown" name="tour_package" class="tourpackagedropdown" disabled>
                                        <option value="">Select a Package</option>
                                        <!-- Options will be populated dynamically -->
                                    </select>
                                    <div class="vehicle-dropdown-container" id="vehicleDropdown1Container">
                                        <label for="vehicledropdown1"><?php echo $vehicle_descriptor; ?> (**)</label>
                                        <select id="vehicledropdown1" name="vehicle1" class="vehicledropdown1">
                                            <option value="">Select a <?php echo $vehicle_descriptor; ?></option>
                                        </select>
                                    </div>
                                    <div class="vehicle-dropdown-container" id="vehicleDropdown2Container">
                                        <label for="vehicledropdown2"><?php echo $vehicle_descriptor; ?> 2 (**)</label>
                                        <select id="vehicledropdown2" name="vehicle2" class="vehicledropdown2">
                                            <option value="">Select a <?php echo $vehicle_descriptor; ?></option>
                                        </select>
                                    </div>
                                    <p id="bst-dual-vehicle-inventory-notice" class="small-text" style="display:none;color:#b32d2e;margin-top:8px;text-align:left;" role="alert" aria-live="polite"></p>
                                    <div id="vehicle-disclaimer" style="display: none; margin-top: 5px;">
                                        <p class="small-text" style="color: #666; font-style: italic; text-align: left;">(**) <?php echo $vehicle_descriptor; ?> choice is subject to availability</p>
                                    </div>
                                    <div id="extension-checkbox-container" style="display: none; margin-top: 10px;">
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="checkbox" id="extensionCheckbox" name="extension" value="1" style="margin-right: 8px;">
                                            <span id="extensionLabel"></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="booking-right"> <!-- right column -->
                                    <label for="tourpricecurrency">Show in Currency</label>
                                    <div class="currency-container">
                                        <select id="tourpricecurrency" name="currency" class="currencydropdown">
                                            <?php
                                            // Get the tour's currency
                                            $tour_currency = get_field('currency', get_the_ID());
                                            if (empty($tour_currency)) {
                                                $tour_currency = 'EUR'; // Default to EUR if not set
                                            }
                                            
                                            $exchange_rates = bst_get_exchange_rates_array($tour_currency);
                                            foreach ($exchange_rates as $rate) {
                                                // Set the tour's currency as selected by default
                                                $selected = ($rate['currency'] === $tour_currency) ? ' selected="selected"' : '';
                                                echo '<option value="' . esc_attr($rate['rate']) . '" data-flag="' . esc_attr($rate['flag']) . '" data-currency="' . esc_attr($rate['currency']) . '"' . $selected . '>' . esc_html($rate['currency']) . ' - ' . esc_html($rate['symbol']) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <span id="currencyFlag" class="currency-flag"></span>
                                    </div>
                                    <div class="price-container">
                                        <label for="tourprice">Tour Price</label>
                                        <input type="text" id="tourprice" name="tour_price" class="tourprice" readonly value="TBD">
                                        <input type="text" id="tourpriceconverted" name="tour_price_converted" class="tourprice-converted" readonly style="display: none;">
                                    </div>
            
                                    <button id="bookButton" class="book-button info-button" disabled>Book Tour!</button>
                                    <button id="waitingListButton" class="waiting-list-button info-button" disabled style="display: none; background-color: #f0ad4e; border-color: #eea236;">Add to Waiting List</button>
                                    <p id="bookButtonText" class="small-centered-italic">Book this tour with the selected options</p>
                                    <p id="waitingListButtonText" class="small-centered-italic" style="display: none;">This date or package is unavailable, but if you click here we will add you to the waiting list.</p>
                                </div>
                            </div>
                        </div>

                        <script>
                        // Show/hide vehicle disclaimer when vehicle dropdowns are visible
                        jQuery(document).ready(function($) {
                            function checkVehicleVisibility() {
                                const vehicle1Container = $('#vehicleDropdown1Container');
                                const vehicle2Container = $('#vehicleDropdown2Container');
                                const disclaimer = $('#vehicle-disclaimer');
                                
                                // Show disclaimer if either vehicle dropdown is visible
                                if (vehicle1Container.is(':visible') || vehicle2Container.is(':visible')) {
                                    disclaimer.show();
                                } else {
                                    disclaimer.hide();
                                }
                            }
                            
                            // Check visibility on page load
                            checkVehicleVisibility();
                            
                            // Monitor for changes in vehicle dropdown visibility
                            const observer = new MutationObserver(function() {
                                checkVehicleVisibility();
                            });
                            
                            // Observe changes to vehicle containers
                            if ($('#vehicleDropdown1Container').length) {
                                observer.observe($('#vehicleDropdown1Container')[0], { 
                                    attributes: true, 
                                    attributeFilter: ['style'] 
                                });
                            }
                            if ($('#vehicleDropdown2Container').length) {
                                observer.observe($('#vehicleDropdown2Container')[0], { 
                                    attributes: true, 
                                    attributeFilter: ['style'] 
                                });
                            }
                        });
                        </script>

                        <!-- Waiting List Confirmation Form (Hidden by default) -->
                        <div id="waitingListForm" style="display: none; background: #f9f9f9; border: 2px solid #f0ad4e; border-radius: 8px; padding: 20px; margin-top: 20px;">
                            <h3 style="color: #8a6d3b; margin-top: 0;">Join Waiting List</h3>
                            
                            <!-- Error Message Container -->
                            <div id="waitingListError" style="display: none; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 12px;">
                                <strong>Error:</strong> <span id="waitingListErrorText"></span>
                            </div>
                            
                            <!-- Customer Information Form -->
                            <div class="customer-info-form">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                    <div>
                                        <label for="waitingList_firstName" style="display: block; font-weight: bold; margin-bottom: 5px;">First Name</label>
                                        <input type="text" id="waitingList_firstName" name="first_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    </div>
                                    <div>
                                        <label for="waitingList_lastName" style="display: block; font-weight: bold; margin-bottom: 5px;">Last Name</label>
                                        <input type="text" id="waitingList_lastName" name="last_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                    <div>
                                        <label for="waitingList_email" style="display: block; font-weight: bold; margin-bottom: 5px;">Email</label>
                                        <input type="email" id="waitingList_email" name="email" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    </div>
                                    <div>
                                        <label for="waitingList_phone" style="display: block; font-weight: bold; margin-bottom: 5px;">Phone</label>
                                        <input type="tel" id="waitingList_phone" name="phone" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    </div>
                                </div>
                                <div style="margin-bottom: 20px;">
                                    <label for="waitingList_notes" style="display: block; font-weight: bold; margin-bottom: 5px;">Notes</label>
                                    <textarea id="waitingList_notes" name="notes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Any additional notes or special requests..."></textarea>
                                </div>
                                <div style="text-align: center;">
                                    <button type="button" id="cancelWaitingList" class="button" style="margin-right: 10px; background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Cancel</button>
                                    <button type="button" id="submitWaitingList" class="button" disabled style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Add to Waiting List</button>
                                </div>
                            </div>
                        </div>

                        <h2>Schedule</h2>
                        <p>
                            <strong>Note:</strong> The schedule and destinations may vary from tour date to tour date due to incremental tour enhancements or scheduling adjustments.<br/>
                        </p>
                        <p id="scheduleDateMessage" style="margin: 15px 0; font-style: italic; color: #666;">
                            Select a date above to see schedule based on tour dates.
                        </p>
                        <div class="date-container">
                            <div class="date-list date-list--orange" id="scheduleContent">
                                <?php 
                                // Add styling to extension header paragraph
                                $schedule_formatted = str_replace(
                                    '<p><strong>If you join',
                                    '<p style="text-align: left; margin: 20px 0 10px 0; font-size: 16px; line-height: 1.6;"><strong>If you join',
                                    $schedule
                                );
                                echo $schedule_formatted; 
                                ?>
                            </div>
                        </div>
                        <br/>
                        <ul class="checkmark-list">
                            <li>Morning departures are typically 9:00 or as advised</li>
                            <li>Afternoon arrivals normally leave time to relax before dinner</li>
                        </ul>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="right-column">
                        <div class="image-container">
                            <?php if ($image_1) : ?>
                                <img src="<?php echo $image_1; ?>" alt="<?php echo esc_attr($tour_title); ?> scenic view - <?php echo esc_attr($tour_type_title); ?> tour destination">
                            <?php endif; ?>
                            <?php if ($image_2) : ?>
                                <img src="<?php echo $image_2; ?>" alt="<?php echo esc_attr($tour_title); ?> travel photography - highlights from <?php echo esc_attr($tour_type_title); ?> tour">    
                            <?php endif; ?>
                            <?php if ($image_3) : ?>
                                <img src="<?php echo $image_3; ?>" alt="<?php echo esc_attr($tour_title); ?> destination gallery - <?php echo esc_attr($tour_type_title); ?> tour experience">
                            <?php endif; ?>
                            <?php if ($image_4) : ?>
                                <img src="<?php echo $image_4; ?>" alt="<?php echo esc_attr($tour_title); ?> destination gallery - <?php echo esc_attr($tour_type_title); ?> tour experience">
                            <?php endif; ?>
                            <?php if ($image_5) : ?>
                                <img src="<?php echo $image_5; ?>" alt="<?php echo esc_attr($tour_title); ?> destination gallery - <?php echo esc_attr($tour_type_title); ?> tour experience">
                            <?php endif; ?>
                            <?php if ($image_6) : ?>
                                <img src="<?php echo $image_6; ?>" alt="<?php echo esc_attr($tour_title); ?> destination gallery - <?php echo esc_attr($tour_type_title); ?> tour experience">
                            <?php endif; ?>
                        </div>
                        <h2>What is Included</h2>
                        <?php
                            // to apply the schedule style
                            $included = str_replace('<ul>', '<ul class="checkmark-list">', $included);
                            echo $included;
                        ?>

                        <h2>What You Provide</h2>
                        <?php
                            // to apply the schedule style
                            $not_included = str_replace('<ul>', '<ul class="checkmark-list">', $not_included);
                            echo $not_included;
                        ?>

                    </div>
                </div>
                </div> <!-- Close page-content-wrapper -->
                
                <!-- Waiting List Styles -->
                <style>
                .waiting-list-button {
                    background-color: #f0ad4e !important;
                    border-color: #eea236 !important;
                    color: white !important;
                }
                
                .waiting-list-button:hover {
                    background-color: #ec971f !important;
                    border-color: #d58512 !important;
                }
                
                .waiting-list-button:disabled {
                    background-color: #f7f7f7 !important;
                    border-color: #ccc !important;
                    color: #999 !important;
                }
                
                #waitingListForm {
                    margin-bottom: 10px;
                }
                
                #waitingListForm h3 {
                    font-size: 18px !important;
                }
                
                #waitingListForm h4 {
                    font-size: 14px !important;
                }
                
                #waitingListForm label {
                    font-size: 12px !important;
                    font-weight: bold;
                }
                
                #waitingListForm label[for="waitingList_firstName"]::after,
                #waitingListForm label[for="waitingList_lastName"]::after,
                #waitingListForm label[for="waitingList_email"]::after,
                #waitingListForm label[for="waitingList_phone"]::after {
                    content: " *";
                    color: #dc3545;
                    font-weight: bold;
                }
                
                #waitingListForm input:required:invalid {
                    border-color: #dc3545;
                }
                
                #waitingListForm input:required:valid {
                    border-color: #28a745;
                }
                
                @media (max-width: 768px) {
                    #waitingListForm .selected-tour-details > div {
                        grid-template-columns: 1fr !important;
                    }
                    
                    #waitingListForm .customer-info-form > div {
                        grid-template-columns: 1fr !important;
                    }
                }
                </style>
                
                <script>
                // Pass deposit settings to JavaScript
                window.tourDepositSettings = {
                    type: <?php echo json_encode($deposit_type, JSON_HEX_TAG); ?>,
                    percent: <?php echo json_encode($deposit_percent, JSON_HEX_TAG); ?>,
                    fixedSingle: <?php echo json_encode($deposit_fixed_single, JSON_HEX_TAG); ?>,
                    fixedDouble: <?php echo json_encode($deposit_fixed_double, JSON_HEX_TAG); ?>
                };

                // Pass bank wire discount to JavaScript
                window.bstBankWireDiscount = <?php echo json_encode(get_option('bst_bank_wire_discount', 2.5), JSON_HEX_TAG); ?>;

                // Pass extension settings to JavaScript
                window.tourExtensionSettings = {
                    offered: <?php echo json_encode($extension_offered, JSON_HEX_TAG); ?>,
                    title: <?php echo json_encode($extension_title, JSON_HEX_TAG); ?>,
                    pricing: <?php echo json_encode($extension_pricing, JSON_HEX_TAG); ?>,
                    extensionDays: <?php echo json_encode($extension_days, JSON_HEX_TAG); ?>,
                    adminVehicleDrivingDays: <?php echo json_encode($admin_vehicle_driving_days, JSON_HEX_TAG); ?>
                };

                // Canonical extension add-on (same PHP as admin booking edit; no booking row required)
                window.bstExtensionAddonAjax = {
                    url: <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
                    nonce: <?php echo json_encode( wp_create_nonce( 'bst_extension_addon' ) ); ?>,
                    tourId: <?php echo (int) $tourid; ?>
                };
                
                // Pass tour dates with extension info to JavaScript
                window.tourDatesData = <?php echo json_encode($all_dates, JSON_HEX_TAG); ?>;
                
                </script>
                
            <!--end custom content -->
        </div>
    </div>
</div>
<?php get_footer(); ?>