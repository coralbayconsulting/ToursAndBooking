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
        wp_enqueue_script('single-tour-script', get_stylesheet_directory_uri() . '/single-tour.js', array('jquery'), '1.1.22', true);
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

/**
 * True when viewing a frontend tour-type-code term archive (/tours/{slug}/).
 */
function bst_is_queried_tour_type_code_term_archive() {
    if (!function_exists('get_queried_object')) {
        return false;
    }
    $obj = get_queried_object();
    return $obj instanceof WP_Term && isset($obj->taxonomy) && $obj->taxonomy === 'tour-type-code';
}

/**
 * Tour-type CPT archive (/tour-types/) matches archive-tour-type.php banner H1.
 */
function bst_is_tour_type_post_type_archive() {
    return is_post_type_archive('tour-type');
}

/**
 * Banner/main heading shown on tour-type CPT archive only.
 *
 * Optional override: BST Settings → "Our Tours archive page title" (`bst_ptarchive_tour_type_page_title`).
 * Filter: bst_tour_type_post_type_archive_display_title
 */
function bst_get_tour_type_post_type_archive_display_title() {
    $default = 'Our Tours';
    $from_opt  = trim((string) get_option('bst_ptarchive_tour_type_page_title', ''));
    if ($from_opt !== '') {
        $default = $from_opt;
    }
    return apply_filters('bst_tour_type_post_type_archive_display_title', $default);
}

/**
 * Normalize an ACF file field value to url/title/filename (array return, ID, or URL).
 *
 * @param mixed $file ACF file sub-field value.
 * @return array{url:string,title:string,filename:string}|null
 */
function bst_normalize_acf_file_field( $file ) {
    if ( empty( $file ) ) {
        return null;
    }

    if ( is_array( $file ) ) {
        if ( ! empty( $file['url'] ) ) {
            return array(
                'url'      => $file['url'],
                'title'    => ! empty( $file['title'] ) ? (string) $file['title'] : '',
                'filename' => ! empty( $file['filename'] ) ? (string) $file['filename'] : '',
            );
        }
        if ( ! empty( $file['ID'] ) ) {
            $file = (int) $file['ID'];
        }
    }

    if ( is_numeric( $file ) ) {
        $attachment_id = (int) $file;
        $url             = wp_get_attachment_url( $attachment_id );
        if ( ! $url ) {
            return null;
        }
        $attached = get_attached_file( $attachment_id );

        return array(
            'url'      => $url,
            'title'    => get_the_title( $attachment_id ),
            'filename' => $attached ? basename( $attached ) : '',
        );
    }

    if ( is_string( $file ) && filter_var( $file, FILTER_VALIDATE_URL ) ) {
        $path = wp_parse_url( $file, PHP_URL_PATH );

        return array(
            'url'      => $file,
            'title'    => '',
            'filename' => $path ? basename( $path ) : '',
        );
    }

    return null;
}

/**
 * Browser/tab title string: heading + optional page + site (same layout as WP core defaults).
 *
 * @param string $heading Main title segment (banner H1 text).
 *
 * @return string
 */
function bst_build_document_title_for_heading($heading) {
    global $page, $paged;

    $sep   = apply_filters('document_title_separator', '-');
    $parts = array((string) $heading);
    $page  = (int) $page;
    $paged = (int) $paged;
    if (($paged >= 2 || $page >= 2) && !is_404()) {
        /* translators: %s: Page number */
        $parts[] = sprintf(__('Page %s'), max($paged, $page));
    }
    $parts[] = get_bloginfo('name', 'display');
    $parts   = array_filter(array_map('trim', $parts));

    return implode(' ' . trim($sep) . ' ', $parts);
}

/**
 * @return string
 */
function bst_build_tour_type_code_document_title() {
    return bst_build_document_title_for_heading(bst_get_queried_tour_type_code_heading());
}

/**
 * Single tour, tour-type-code taxonomy, or tour-type post type archive.
 */
function bst_is_managed_title_context() {
    return bst_is_queried_tour_type_code_term_archive()
        || bst_is_tour_type_post_type_archive()
        || is_singular('tour');
}

/**
 * Whether Yoast has a custom SEO title for this URL (post or term SEO fields only; not CPT archives).
 */
function bst_yoast_has_custom_seo_title_for_current_page() {
    if (!defined('WPSEO_VERSION')) {
        return false;
    }
    if (is_singular('tour')) {
        $v = get_post_meta(get_queried_object_id(), '_yoast_wpseo_title', true);
        return is_string($v) && trim($v) !== '';
    }
    if (bst_is_queried_tour_type_code_term_archive()) {
        $term = get_queried_object();
        if (!($term instanceof WP_Term)) {
            return false;
        }
        foreach (array('wpseo_title', '_wpseo_title') as $key) {
            $v = get_term_meta($term->term_id, $key, true);
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
        }
        return false;
    }
    return false;
}

/**
 * Short programmatic tab/SERP title when Yoast has no custom SEO title for this page.
 *
 * @return string
 */
function bst_get_programmatic_document_title_for_current_page() {
    if (bst_is_queried_tour_type_code_term_archive()) {
        return bst_build_tour_type_code_document_title();
    }
    if (bst_is_tour_type_post_type_archive()) {
        return bst_build_document_title_for_heading(bst_get_tour_type_post_type_archive_display_title());
    }
    if (is_singular('tour')) {
        $tid     = get_queried_object_id();
        $heading = apply_filters('bst_singular_tour_document_title_heading', get_the_title($tid), $tid);

        return bst_build_document_title_for_heading((string) $heading);
    }
    return '';
}

/**
 * WordPress core title tag when Yoast is not active (or unmanaged pages).
 *
 * @param string $title Previous value.
 *
 * @return string
 */
function bst_apply_pre_get_document_title_programmatic($title) {
    if (!bst_is_managed_title_context()) {
        return $title;
    }
    if (defined('WPSEO_VERSION')) {
        return $title;
    }
    if (bst_yoast_has_custom_seo_title_for_current_page()) {
        return $title;
    }

    return bst_get_programmatic_document_title_for_current_page();
}

/**
 * Yoast: use editor SEO title when set; otherwise keep short programmatic titles.
 *
 * @param string $title Assembled by Yoast (variables resolved).
 *
 * @return string
 */
function bst_apply_wpseo_title_hybrid($title) {
    if (!bst_is_managed_title_context()) {
        return $title;
    }
    // CPT archive: no reliable Yoast "per URL" SEO title; always use BST heading (+ site name). Optional heading in BST Settings.
    if (bst_is_tour_type_post_type_archive()) {
        return bst_get_programmatic_document_title_for_current_page();
    }
    if (bst_yoast_has_custom_seo_title_for_current_page()) {
        return $title;
    }

    return bst_get_programmatic_document_title_for_current_page();
}

/**
 * Yoast: optional meta description for /tour-types/ from BST Settings (Yoast has no archive metabox for this URL).
 *
 * @param string $desc Yoast-assembled description.
 *
 * @return string
 */
function bst_apply_wpseo_metadesc_tour_type_archive($desc) {
    if (!bst_is_tour_type_post_type_archive()) {
        return $desc;
    }
    $custom = trim((string) get_option('bst_ptarchive_tour_type_meta_description', ''));
    if ($custom !== '') {
        return $custom;
    }
    return $desc;
}

/**
 * Banner heading + image for the current tour-type-code taxonomy (single tour-type CPT lookup).
 *
 * @return array{heading: string, image: string}
 */
function bst_get_queried_tour_type_code_banner_data() {
    $default_image = '/wp-content/uploads/default-banner.jpg';
    $term          = get_queried_object();
    if (!($term instanceof WP_Term) || $term->taxonomy !== 'tour-type-code' || !isset($term->name)) {
        return array(
            'heading' => 'No Tour Type Provided',
            'image'   => $default_image,
        );
    }
    $heading = $term->name;
    $image   = $default_image;
    if (isset($term->term_id)) {
        $tour_type_query = new WP_Query(array(
            'post_type'      => 'tour-type',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => 'type_code',
                    'value'   => $term->term_id,
                    'compare' => '=',
                ),
            ),
        ));
        if ($tour_type_query->have_posts()) {
            $tour_type_query->the_post();
            $heading     = get_the_title();
            $acf_banner = get_field('banner_image');
            if ($acf_banner) {
                $image = $acf_banner;
            }
            wp_reset_postdata();
        }
    }
    return array(
        'heading' => $heading,
        'image'   => $image,
    );
}

/**
 * Display title for taxonomy-tour-type-code (browser tab matches banner H1).
 */
function bst_get_queried_tour_type_code_heading() {
    return bst_get_queried_tour_type_code_banner_data()['heading'];
}

/**
 * Final document_title filter when Yoast is off (pre_get may already have short-circuited).
 *
 * @param string $title Full title from core.
 *
 * @return string
 */
function bst_apply_document_title_programmatic($title) {
    if (defined('WPSEO_VERSION')) {
        return $title;
    }
    if (!bst_is_managed_title_context()) {
        return $title;
    }
    if (bst_yoast_has_custom_seo_title_for_current_page()) {
        return $title;
    }

    return bst_get_programmatic_document_title_for_current_page();
}

// Without Yoast: core <title> only. With Yoast: use bst_apply_wpseo_title_hybrid so custom SEO titles in Yoast win.
add_filter('pre_get_document_title', 'bst_apply_pre_get_document_title_programmatic', 100001);
add_filter('document_title', 'bst_apply_document_title_programmatic', 100001);

add_filter('wpseo_title', 'bst_apply_wpseo_title_hybrid', 999);
add_filter('wpseo_opengraph_title', 'bst_apply_wpseo_title_hybrid', 999);
add_filter('wpseo_twitter_title', 'bst_apply_wpseo_title_hybrid', 999);
add_filter('wpseo_metadesc', 'bst_apply_wpseo_metadesc_tour_type_archive', 999);

/**
 * Ensure Organization schema logo URLs are absolute (Yoast sometimes emits root-relative paths).
 *
 * @param array $piece Organization graph piece.
 * @param mixed $context Yoast meta context (unused).
 * @return array
 */
function bst_yoast_schema_organization_absolutize_logo_urls($piece, $context = null) {
    unset($context);
    if (!is_array($piece) || empty($piece['logo']) || !is_array($piece['logo'])) {
        return $piece;
    }
    foreach (array('url', 'contentUrl') as $key) {
        if (empty($piece['logo'][$key]) || !is_string($piece['logo'][$key])) {
            continue;
        }
        $url = $piece['logo'][$key];
        if ($url !== '' && $url[0] === '/') {
            $piece['logo'][$key] = home_url($url);
        }
    }
    return $piece;
}
add_filter('wpseo_schema_organization', 'bst_yoast_schema_organization_absolutize_logo_urls', 11, 2);

/**
 * Normalize ACF / mixed values to plain text for Yoast content analysis (single tour).
 *
 * @param mixed $value Raw field value.
 *
 * @return string
 */
function bst_yoast_analysis_flatten_text($value) {
    if ($value === null || $value === false || $value === '') {
        return '';
    }
    if ($value instanceof WP_Term) {
        $parts = array_filter(array($value->name, isset($value->description) ? $value->description : ''));
        $value   = implode(' ', $parts);
    } elseif (is_object($value)) {
        if (isset($value->post_title)) {
            $value = (string) $value->post_title;
        } elseif (isset($value->name)) {
            $value = (string) $value->name;
        } else {
            return '';
        }
    } elseif (is_array($value)) {
        $pieces = array();
        foreach ($value as $item) {
            $t = bst_yoast_analysis_flatten_text($item);
            if ($t !== '') {
                $pieces[] = $t;
            }
        }
        return implode(' ', $pieces);
    }
    $text = wp_strip_all_tags((string) $value, true);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim($text);
}

/**
 * Yoast may embed analysis text inside HTML &lt;script&gt; tags. Literal "&lt;/script&gt;" in ACF/WYSIWYG
 * (even as plain text) terminates the tag early → "Unexpected end of input" and a broken SEO metabox.
 *
 * @param string $text Analysis-bound string.
 *
 * @return string
 */
function bst_yoast_sanitize_analysis_payload($text) {
    $text = is_string($text) ? $text : '';
    $text = preg_replace('/<\/script\b[^>]*>/iu', ' ', $text);
    $text = preg_replace('/<script\b[^>]*>/iu', ' ', $text);
    $text = wp_check_invalid_utf8($text, true);
    if (strlen($text) > 500000) {
        $text = substr($text, 0, 500000) . ' …';
    }
    return $text;
}

/**
 * Merge Yoast analysis base content with extra plain text.
 *
 * @param string $content Original post_content passed to Yoast.
 * @param string $extra     Additional text (ACF-derived).
 *
 * @return string
 */
function bst_wpseo_merge_pre_analysis_content($content, $extra) {
    $extra = is_string($extra) ? trim($extra) : '';
    if ($extra === '') {
        return bst_yoast_sanitize_analysis_payload(is_string($content) ? $content : '');
    }
    $base = is_string($content) ? trim(wp_strip_all_tags($content)) : '';
    if ($base === '') {
        return bst_yoast_sanitize_analysis_payload($extra);
    }

    return bst_yoast_sanitize_analysis_payload($content . "\n\n" . $extra);
}

/**
 * Text for Yoast when editing a tour-type CPT row (archive-tour-type.php cards: title, listing blurb, linked tours/dates).
 *
 * @param int $post_id tour-type post ID.
 *
 * @return string
 */
function bst_build_yoast_pre_analysis_tour_type_cpt_content($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('get_field')) {
        return '';
    }

    $sections = array();
    $title     = get_the_title($post_id);
    if ($title !== '') {
        $sections[] = $title;
    }

    $listing = bst_yoast_analysis_flatten_text(get_field('listing_description', $post_id));
    if ($listing !== '') {
        $sections[] = $listing;
    }

    $type_code = get_field('type_code', $post_id);
    $term_id   = 0;
    if ($type_code instanceof WP_Term) {
        $term_id = (int) $type_code->term_id;
        $td      = bst_yoast_analysis_flatten_text($type_code->name . ' ' . (isset($type_code->description) ? $type_code->description : ''));
        if ($td !== '') {
            $sections[] = $td;
        }
    }

    if ($term_id && function_exists('bst_get_tours_by_year_for_tour_type')) {
        $by_year = bst_get_tours_by_year_for_tour_type($term_id);
        if (!empty($by_year)) {
            ksort($by_year, SORT_NATURAL);
            $lines = array();
            $cap   = 0;
            foreach ($by_year as $year => $tours) {
                foreach ($tours as $t) {
                    if ($cap++ >= 100) {
                        break 2;
                    }
                    $piece = trim(
                        (string) $year . ' '
                        . (isset($t['date_text']) ? $t['date_text'] : '') . ' '
                        . (isset($t['title']) ? $t['title'] : '')
                    );
                    if ($piece !== '') {
                        $lines[] = $piece;
                    }
                }
            }
            if (!empty($lines)) {
                $sections[] = 'Tour dates ' . implode('. ', $lines);
            }
        }
    }

    $blob = implode("\n\n", array_filter($sections));
    $blob = apply_filters('bst_yoast_pre_analysis_tour_type_cpt_text', $blob, $post_id);

    return is_string($blob) ? trim($blob) : '';
}

/**
 * Text sent to Yoast for tour posts: mirrors main copy blocks from single-tour.php (ACF).
 * Tour dates list is dynamic/JS-heavy; add via filter bst_yoast_pre_analysis_tour_append if needed.
 *
 * @param int $post_id Tour post ID.
 *
 * @return string
 */
function bst_build_yoast_pre_analysis_tour_content($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('get_field')) {
        return '';
    }

    $sections = array();

    $title = get_the_title($post_id);
    if ($title !== '') {
        $sections[] = $title;
    }

    $what = bst_yoast_analysis_flatten_text(get_field('short_description', $post_id));
    if ($what !== '') {
        $sections[] = 'What ' . $what;
    }

    $starting = bst_yoast_analysis_flatten_text(get_field('starting_from', $post_id));
    $airport    = bst_yoast_analysis_flatten_text(get_field('airport', $post_id));
    if ($starting !== '' || $airport !== '') {
        $sections[] = 'Starting From ' . trim($starting . ' ' . $airport);
    }

    $ending = bst_yoast_analysis_flatten_text(get_field('ending_at', $post_id));
    if ($ending !== '') {
        $airport2 = bst_yoast_analysis_flatten_text(get_field('airport_2', $post_id));
        $sections[] = 'Ending At ' . trim($ending . ' ' . $airport2);
    }

    $blocks = array(
        'Hospitality'      => 'hospitality',
        'Roads'            => 'roads',
        'About the Tour'   => 'about',
        'Schedule'         => 'schedule',
        'What is Included' => 'included',
        'What You Provide' => 'not_included',
    );
    foreach ($blocks as $label => $field_key) {
        $txt = bst_yoast_analysis_flatten_text(get_field($field_key, $post_id));
        if ($txt !== '') {
            $sections[] = $label . ' ' . $txt;
        }
    }

    $ext = bst_yoast_analysis_flatten_text(get_field('extension_title', $post_id));
    if ($ext !== '') {
        $sections[] = 'Extension ' . $ext;
    }

    if (get_option('bst_enable_tour_rating', false)) {
        $rating = get_field('tour_rating', $post_id);
        $rtext  = bst_yoast_analysis_flatten_text($rating);
        if ($rtext !== '') {
            $sections[] = 'Tour Class ' . $rtext;
        }
    }

    $blob = implode("\n\n", array_filter($sections));
    $blob = apply_filters('bst_yoast_pre_analysis_tour_text', $blob, $post_id);

    return is_string($blob) ? trim($blob) : '';
}

/**
 * Yoast SEO: feed ACF-driven copy into the analyzer when post_content is empty (tour + tour-type CPT).
 * Yoast Premium’s block editor UI does not use this for the sidebar scores in all versions; avoid legacy
 * YoastSEO.app.registerModification scripts here—they can blank the React metabox.
 *
 * @param string       $content       Existing post_content.
 * @param WP_Post|null $post          Post being analyzed.
 * @param mixed        $unused_fields Optional third arg from Yoast (legacy custom fields list).
 *
 * @return string
 */
function bst_wpseo_pre_analysis_post_content_bst($content, $post, $unused_fields = null) {
    unset($unused_fields);
    if (!defined('WPSEO_VERSION') || !$post instanceof WP_Post) {
        return $content;
    }

    if ($post->post_type === 'tour') {
        $extra = bst_build_yoast_pre_analysis_tour_content((int) $post->ID);
        return bst_wpseo_merge_pre_analysis_content($content, $extra);
    }

    if ($post->post_type === 'tour-type') {
        $extra = bst_build_yoast_pre_analysis_tour_type_cpt_content((int) $post->ID);
        return bst_wpseo_merge_pre_analysis_content($content, $extra);
    }

    return $content;
}

add_filter('wpseo_pre_analysis_post_content', 'bst_wpseo_pre_analysis_post_content_bst', 10, 3);

// Add SEO meta descriptions for tour pages
function add_tour_seo_meta() {
    // seo-head.php (BST plugin) handles this when Yoast is not active.
    if (defined('WPSEO_VERSION') || function_exists('bst_seo_head_output')) {
        return;
    }
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
    } elseif (bst_is_queried_tour_type_code_term_archive()) {
        $term             = get_queried_object();
        $taxonomy_heading = bst_get_queried_tour_type_code_heading();
        $meta_description = isset($term->name)
            ? 'Explore our ' . $term->name . ' tours and adventures. Book your perfect travel experience today!'
            : 'Explore our guided tours and adventures. Book your perfect travel experience today!';
        echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($taxonomy_heading) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
    } elseif (bst_is_tour_type_post_type_archive()) {
        $archive_title = bst_build_document_title_for_heading(bst_get_tour_type_post_type_archive_display_title());
        $meta_custom   = trim((string) get_option('bst_ptarchive_tour_type_meta_description', ''));
        $meta_description = $meta_custom !== ''
            ? $meta_custom
            : 'Discover our collection of guided tours and travel adventures. From cultural experiences to outdoor activities, find your perfect tour today!';
        echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($archive_title) . '">' . "\n";
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
        wp_enqueue_script('bst-tour-share', get_stylesheet_directory_uri() . '/js/bst-tour-share.js', array(), '1.0.0', true);
    }
    if (is_tax('tour-type-code') || is_post_type_archive('tour-type')) {
        wp_enqueue_script('bst-auto-refresh', get_stylesheet_directory_uri() . '/js/bst-auto-refresh.js', array('jquery'), '1.0.0', true);
        wp_localize_script('bst-auto-refresh', 'bstAutoRefresh', array(
            'interval' => intval(get_option('bst_auto_refresh_interval', 15)),
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_custom_tooltip_script');

/**
 * Override Yoast og:type from 'article' to 'product' on single tour pages.
 */
function bst_yoast_opengraph_type_for_tours( $type, $presentation = null ) {
    unset( $presentation );
    if ( is_singular( 'tour' ) ) {
        return 'product';
    }
    return $type;
}
add_filter( 'wpseo_opengraph_type', 'bst_yoast_opengraph_type_for_tours', 10, 2 );

