<?php get_header(); ?>

<div class="page-content">
    <div id="content" class="content">

        <!-- Altered Content Start -->
        <div class="translucent-overlay">

            <!-- TOP BANNER -->
            <div class="top-banner-container">
                <img class="top-banner" src="<?php echo esc_url(get_option('bst_banner_image')); ?>" alt="Our Tours - Banner Image">
                <h1 class="banner-text">Our Tours</h1>
            </div>
        </div>
        
        <!-- Breadcrumbs Section -->
        <div class="bst-breadcrumb-section">
            <div class="bst-breadcrumb-container has-share-buttons">
                <?php if (function_exists('bst_render_tour_archive_breadcrumbs')) {
                    bst_render_tour_archive_breadcrumbs('');
                } ?>
                
                <!-- Share Buttons -->
                <div class="bst-share-buttons">
                    <span class="bst-share-label">Share:</span>
                    <?php 
                    // Get page info for sharing via helper
                    $share_meta    = function_exists('bst_get_tour_archive_share_metadata')
                        ? bst_get_tour_archive_share_metadata()
                        : array('url' => get_post_type_archive_link('tour-type'), 'email_label' => "Blue Strada's tours");
                    $share_url     = $share_meta['url'];
                    $email_label   = $share_meta['email_label'];
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
                                    
                                    // Check if this tour type has any public tours
                                    $public_tours_query = new WP_Query(array(
                                        'post_type' => 'tour',
                                        'posts_per_page' => 1,
                                        'fields' => 'ids',
                                        'tax_query' => array(
                                            array(
                                                'taxonomy' => 'tour-type-code',
                                                'field'    => 'term_id',
                                                'terms'    => $term_id,
                                            )
                                        ),
                                        'meta_query' => array(
                                            array(
                                                'key' => 'private',
                                                'value' => 1,
                                                'compare' => '!=',
                                                'type' => 'NUMERIC'
                                            )
                                        )
                                    ));
                                    
                                    // Skip this tour type if it has no public tours
                                    if (!$public_tours_query->have_posts()) {
                                        wp_reset_postdata();
                                        continue;
                                    }
                                    wp_reset_postdata();
                                }
                            } else {
                                error_log('No type_code ACF field found for post ID: ' . $id);
                            }

                            ?>
                            <div class="tour-type-box">
                                <h2>
                                    <?php echo $title; ?>
                                </h2>
                                <div class="listing-image-container">
                                    <a href="<?php echo esc_url($taxonomy_url); ?>">
                                        <img src="<?php echo $image; ?>" alt="<?php echo $title; ?> - Tour Category">
                                    </a>
                                </div>
                                <p><?php echo $description; ?></p>
                                <div class="date-container">
                                    <?php
                                    $tours_by_year = bst_get_tours_by_year_for_tour_type($type_code_field->term_id);

                                    if (!empty($tours_by_year)) {
                                        foreach ($tours_by_year as $year => $tours) {
                                            echo '<div class="date-list">';
                                            echo '<h3>' . esc_html($year) . ' Tour Dates</h3>';
                                            echo '<ul class="date-list date-list--smallblue">';
                                            foreach ($tours as $tour) {
                                                echo '<li>';
                                                echo '<span class="date-content">' . esc_html($tour['date_text']) . ': <a href="' . esc_url($tour['permalink']) . '">' . esc_html($tour['title']) . '</a></span>';
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
                                    } else {
                                        echo '<p>No tours found for the specified tour type.</p>';
                                    }
                                    ?>
                                </div>
                                <br/>
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

<script>
jQuery(document).ready(function($) {
    // Auto-refresh to keep availability data current - using configurable interval
    var autoRefreshMinutes = <?php echo intval(get_option('bst_auto_refresh_interval', 15)); ?>;
    var AUTO_REFRESH_TIME = autoRefreshMinutes * 60 * 1000; // Convert minutes to milliseconds
    
    setTimeout(function() {
        location.reload();
    }, AUTO_REFRESH_TIME);
});

// Share functionality
function bstCopyTourLink(event) {
    const url = window.location.href;
    const btn = event.currentTarget;
    
    // Try modern clipboard API first (requires HTTPS)
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            bstShowCopySuccess(btn);
        }).catch(err => {
            // Fallback to older method
            bstCopyFallback(url, btn);
        });
    } else {
        // Use fallback for older browsers or non-HTTPS
        bstCopyFallback(url, btn);
    }
}

function bstCopyFallback(text, btn) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        bstShowCopySuccess(btn);
    } catch (err) {
        console.error('Failed to copy:', err);
        alert('Failed to copy link. Please copy manually: ' + text);
    }
    
    document.body.removeChild(textArea);
}

function bstShowCopySuccess(btn) {
    btn.classList.add('copied');
    btn.setAttribute('title', 'Copied!');
    
    setTimeout(() => {
        btn.classList.remove('copied');
        btn.setAttribute('title', 'Copy link to clipboard');
    }, 2000);
}
</script>

<?php get_footer(); ?>