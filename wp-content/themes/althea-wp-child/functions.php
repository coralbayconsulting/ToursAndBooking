<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

if ( !function_exists( 'chld_thm_cfg_parent_css' ) ):
    function chld_thm_cfg_parent_css() {
        wp_enqueue_style( 'chld_thm_cfg_parent', trailingslashit( get_template_directory_uri() ) . 'style.css', array( 'althea-wp-theme-extras' ), '1.0.14' );
    }
endif;
add_action( 'wp_enqueue_scripts', 'chld_thm_cfg_parent_css', 10 );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'chld_thm_cfg_parent' ), '1.0.14' );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION

function enqueue_font_awesome() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), '6.0.13');
}
add_action('wp_enqueue_scripts', 'enqueue_font_awesome');

// Add Permissions-Policy header to allow synchronous AJAX calls
function add_permissions_policy_header() {
    header("Permissions-Policy: sync-xhr=(self)");
}
add_action('send_headers', 'add_permissions_policy_header');

// Enqueue and localize the script
function enqueue_single_tour_script() {
    if (is_singular('tour')) {
        wp_enqueue_script('single-tour-script', get_stylesheet_directory_uri() . '/single-tour.js', array('jquery'), '1.1.17', true);
        wp_localize_script('single-tour-script', 'ajax_object', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'auto_refresh_interval' => get_option('bst_auto_refresh_interval', 15)
        ));

        // Localize the script with session data
        $session_referrer = isset($_SESSION['referrer']) ? $_SESSION['referrer'] : '';
        // Filter out gravityapi.com referrers (form preview/testing)
        if (!empty($session_referrer) && stripos($session_referrer, 'gravityapi.com') !== false) {
            $session_referrer = '';
        }
        wp_localize_script('single-tour-script', 'sessionData', array(
            'source' => isset($_SESSION['source']) ? $_SESSION['source'] : '',
            'referrer' => $session_referrer
        ));

        // Get global settings for packages
        $package_settings = get_package_settings();

        // Get tour currency for multi-currency support
        $tour_currency = get_field('currency');
        if (!$tour_currency) {
            $tour_currency = 'EUR'; // Default to EUR if not set
        }

        // Localize the script with package settings
        wp_localize_script('single-tour-script', 'packageSettings', $package_settings);
        
        // Get exchange rates for this tour's base currency
        $exchange_rates = bst_get_exchange_rates_array($tour_currency);
        $rates_data = array();
        foreach ($exchange_rates as $rate) {
            $rates_data[$rate['currency']] = array(
                'rate' => $rate['rate'],
                'symbol' => $rate['symbol']
            );
        }
        
        // Localize the script with tour currency data and exchange rates
        wp_localize_script('single-tour-script', 'tourCurrency', array(
            'currency' => $tour_currency,
            'symbol' => $tour_currency === 'USD' ? '$' : '€',
            'rates' => $rates_data
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_single_tour_script');

// Add SEO meta descriptions for tour pages
function add_tour_seo_meta() {
    if (is_singular('tour')) {
        $tour_id = get_the_ID();
        $tour_title = get_the_title();
        $short_description = get_field('short_description', $tour_id);
        $starting_from = get_field('starting_from', $tour_id);
        
        // Create meta description from tour data
        $meta_description = $short_description;
        if ($starting_from) {
            $meta_description .= ' Starting from ' . $starting_from . '.';
        }
        $meta_description .= ' Book your ' . $tour_title . ' adventure today!';
        
        // Limit to 160 characters for optimal SEO
        $meta_description = wp_trim_words($meta_description, 25, '...');
        
        echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
        
        // Add Open Graph tags for social sharing
        echo '<meta property="og:title" content="' . esc_attr($tour_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        
        // Add banner image as og:image if available
        $banner_image = get_field('detail_banner_image', $tour_id);
        if ($banner_image) {
            echo '<meta property="og:image" content="' . esc_url($banner_image) . '">' . "\n";
        }
    } elseif (is_tax('tour-type-code')) {
        $term = get_queried_object();
        $meta_description = 'Explore our ' . $term->name . ' tours and adventures. Book your perfect travel experience today!';
        echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($term->name . ' Tours') . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
    } elseif (is_post_type_archive('tour-type')) {
        $meta_description = 'Discover our collection of guided tours and travel adventures. From cultural experiences to outdoor activities, find your perfect tour today!';
        echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
        echo '<meta property="og:title" content="Our Tours - Blue Strada Tours">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
    }
}
add_action('wp_head', 'add_tour_seo_meta');

// Note: tour-rating taxonomy is now registered in the plugin alongside tour-type-code

// Enqueue custom tooltip JavaScript for star ratings
function enqueue_custom_tooltip_script() {
    if (is_tax('tour-type-code') || is_post_type_archive('tour-type') || is_singular('tour')) {
        wp_enqueue_script('custom-tooltip', get_stylesheet_directory_uri() . '/custom-tooltip.js', array('jquery'), '1.0.1', true);
        wp_enqueue_script('rating-help', get_stylesheet_directory_uri() . '/js/rating-help.js', array('jquery'), '1.0.1', true);
    }
}
add_action('wp_enqueue_scripts', 'enqueue_custom_tooltip_script');


