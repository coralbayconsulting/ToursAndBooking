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
        wp_enqueue_style( 'chld_thm_cfg_parent', trailingslashit( get_template_directory_uri() ) . 'style.css', array( 'althea-wp-theme-extras' ), filemtime( get_template_directory() . '/style.css' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'chld_thm_cfg_parent_css', 10 );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'chld_thm_cfg_parent' ), filemtime( get_stylesheet_directory() . '/style.css' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

/*
 * ==========================================================================
 * BST iOS site background fix (May 2026)
 * ==========================================================================
 * iOS WebKit breaks Customizer body background (fixed attachment zoom/crop).
 * Fix: fixed <img> layer + optional tint; see IOS-BACKGROUND-FIX-ROLLBACK.md
 * in this theme folder for full rollback steps if Colibri/theme updates break it.
 *
 * Quick disable: make bst_should_use_ios_background_layer() return false.
 * ==========================================================================
 */

/**
 * Whether the current request is from an iPhone, iPad, or iPod.
 *
 * Server-side detection is required — Chrome DevTools mobile emulation keeps a
 * desktop user agent and pointer/hover media features, so CSS-only touch
 * queries do not run there even when the viewport is narrow.
 */
function bst_is_ios_request() {
	if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		return false;
	}
	return (bool) preg_match( '/iPhone|iPod|iPad/', wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
}

/**
 * Whether to use the iOS <img> background layer on this view.
 */
function bst_should_use_ios_background_layer() {
	if ( is_admin() || ! bst_is_ios_request() || ! get_background_image() ) {
		return false;
	}
	return true;
}

/**
 * Whether the iOS background layer includes the white translucence tint.
 *
 * The homepage shows the Customizer background without an overlay on desktop;
 * inner pages use .translucent-overlay. On iOS the tint is a real element on
 * the fixed bg layer (inner pages only).
 */
function bst_should_use_ios_background_tint() {
	return bst_should_use_ios_background_layer() && ! is_front_page();
}

/**
 * iOS WebKit breaks CSS body backgrounds (fixed attachment zoom/crop; pseudo-elements
 * often invisible). A real <img> with object-fit is the reliable workaround.
 */
function bst_ios_body_class( $classes ) {
	if ( bst_should_use_ios_background_layer() ) {
		$classes[] = 'bst-ios-bg';
	}
	if ( bst_should_use_ios_background_tint() ) {
		$classes[] = 'bst-ios-bg-tinted';
	}
	return $classes;
}
add_filter( 'body_class', 'bst_ios_body_class' );

function bst_render_ios_background_image() {
	static $rendered = false;
	if ( $rendered || ! bst_should_use_ios_background_layer() ) {
		return;
	}

	$rendered  = true;
	$image     = get_background_image();
	$pos_x     = get_theme_mod( 'background_position_x', 'center' );
	$pos_y     = get_theme_mod( 'background_position_y', 'bottom' );
	$position  = trim( $pos_x . ' ' . $pos_y );
	$tint      = bst_should_use_ios_background_tint()
		? '<span class="bst-ios-bg-tint" aria-hidden="true"></span>'
		: '';

	printf(
		'<div class="bst-ios-bg-layer" aria-hidden="true"><img src="%s" alt="" decoding="async" fetchpriority="low" style="object-position:%s">%s</div>',
		esc_url( $image ),
		esc_attr( $position ),
		$tint
	);
}
add_action( 'wp_body_open', 'bst_render_ios_background_image', 0 );
add_action( 'wp_footer', 'bst_render_ios_background_image', 0 );

/**
 * Android and other non-iOS touch devices: switch fixed → scroll only.
 * Does not remove the Customizer background image.
 */
function bst_non_ios_touch_background_fix() {
	if ( is_admin() || ! get_background_image() || bst_is_ios_request() ) {
		return;
	}

	$pos_x = get_theme_mod( 'background_position_x', 'center' );
	$pos_y = get_theme_mod( 'background_position_y', 'bottom' );

	$css = sprintf(
		'@media (hover: none) and (pointer: coarse) {
			body.custom-background {
				background-attachment: scroll !important;
				background-position: %1$s %2$s !important;
				background-size: cover !important;
				background-repeat: no-repeat !important;
			}
		}',
		esc_attr( $pos_x ),
		esc_attr( $pos_y )
	);

	wp_add_inline_style( 'chld_thm_cfg_child', $css );
}
add_action( 'wp_enqueue_scripts', 'bst_non_ios_touch_background_fix', 20 );

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
        wp_enqueue_script('single-tour-script', get_stylesheet_directory_uri() . '/single-tour.js', array('jquery'), filemtime( get_stylesheet_directory() . '/single-tour.js' ), true);
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
    return apply_filters( 'bst_tour_type_post_type_archive_display_title', 'Our Tours' );
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

// Note: tour-rating taxonomy is now registered in the plugin alongside tour-type-code

/**
 * Render email / WhatsApp / copy-link share buttons.
 *
 * @param array $args {
 *     @type string $context     tour|article|page|tours-list|blog-list
 *     @type string $url         Share URL (defaults to current post permalink).
 *     @type string $title       Single-item title for tour/article/page contexts.
 *     @type string $email_label List label for tours-list context.
 * }
 */
function bst_render_share_buttons( $args = array() ) {
    $args = wp_parse_args(
        $args,
        array(
            'context'     => 'article',
            'url'         => '',
            'title'       => '',
            'email_label' => '',
        )
    );

    $context = $args['context'];
    $url     = $args['url'];
    $title   = $args['title'];

    if ( $url === '' && in_array( $context, array( 'tour', 'article', 'page' ), true ) ) {
        $url = get_permalink();
    }

    if ( $context === 'tours-list' && $url === '' ) {
        $url = function_exists( 'bst_get_tour_archive_share_metadata' )
            ? bst_get_tour_archive_share_metadata()['url']
            : get_post_type_archive_link( 'tour-type' );
    }

    if ( $context === 'blog-list' && $url === '' && function_exists( 'bst_get_blog_index_url' ) ) {
        $url = bst_get_blog_index_url();
    }

    if ( $title === '' && in_array( $context, array( 'tour', 'article', 'page' ), true ) ) {
        $title = html_entity_decode( get_the_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }

    if ( $context === 'tours-list' && $title === '' ) {
        $title = $args['email_label'] !== '' ? $args['email_label'] : "Blue Strada's tours";
    }

    if ( $context === 'blog-list' && $title === '' ) {
        $title = $args['email_label'] !== '' ? $args['email_label'] : ( function_exists( 'bst_get_blog_index_label' ) ? bst_get_blog_index_label() : 'Blog' );
    }

    switch ( $context ) {
        case 'tour':
            $subject_text   = 'Check out this tour: ' . $title;
            $body_intro     = 'I thought you might like this tour:';
            $whatsapp_text  = 'Check out this tour: ' . $title . ' ' . $url;
            $email_aria     = 'Email this tour to a friend';
            $whatsapp_aria  = 'Share this tour on WhatsApp';
            break;

        case 'page':
            $subject_text   = 'Check out this page: ' . $title;
            $body_intro     = 'I thought you might like this page:';
            $whatsapp_text  = 'Check out this page: ' . $title . ' ' . $url;
            $email_aria     = 'Email this page to a friend';
            $whatsapp_aria  = 'Share this page on WhatsApp';
            break;

        case 'tours-list':
            $label = $args['email_label'] !== '' ? $args['email_label'] : "Blue Strada's tours";
            $subject_text   = 'Check out ' . $label;
            $body_intro     = 'I thought you might like ' . $label . ':';
            $whatsapp_text  = 'Check out ' . $label . ': ' . $url;
            $email_aria     = 'Email these tours to a friend';
            $whatsapp_aria  = 'Share these tours on WhatsApp';
            break;

        case 'blog-list':
            $label = $args['email_label'] !== '' ? $args['email_label'] : 'these articles';
            $subject_text   = 'Check out ' . $label;
            $body_intro     = 'I thought you might like ' . $label . ':';
            $whatsapp_text  = 'Check out ' . $label . ': ' . $url;
            $email_aria     = 'Email these articles to a friend';
            $whatsapp_aria  = 'Share these articles on WhatsApp';
            break;

        case 'article':
        default:
            $subject_text   = 'Check out this article: ' . $title;
            $body_intro     = 'I thought you might like this article:';
            $whatsapp_text  = 'Check out this article: ' . $title . ' ' . $url;
            $email_aria     = 'Email this article to a friend';
            $whatsapp_aria  = 'Share this article on WhatsApp';
            break;
    }

    $bst_share_email_subject = rawurlencode( $subject_text );
    $bst_share_email_body    = rawurlencode( $body_intro . "\n\n" . ( in_array( $context, array( 'tour', 'article', 'page' ), true ) ? $title . "\n" : '' ) . $url );
    $bst_share_whatsapp_text = $whatsapp_text;
    $bst_share_email_aria    = $email_aria;
    $bst_share_whatsapp_aria = $whatsapp_aria;

    $object_id = isset( $args['object_id'] ) ? absint( $args['object_id'] ) : 0;
    if ( ! $object_id && in_array( $context, array( 'tour', 'article', 'page' ), true ) ) {
        $object_id = get_the_ID();
    }

    $bst_share_track_context   = $context;
    $bst_share_track_url       = $url;
    $bst_share_track_title     = $title;
    $bst_share_track_object_id = $object_id;

    include get_stylesheet_directory() . '/partials/bst-share-buttons.php';
}

// Enqueue custom tooltip JavaScript for star ratings
function enqueue_custom_tooltip_script() {
    if (is_tax('tour-type-code') || is_post_type_archive('tour-type') || is_singular('tour') || is_singular('post') || is_home() || is_category() || is_tag()) {
        if (is_tax('tour-type-code') || is_post_type_archive('tour-type') || is_singular('tour')) {
            wp_enqueue_script('custom-tooltip', get_stylesheet_directory_uri() . '/custom-tooltip.js', array('jquery'), '1.0.1', true);
            wp_enqueue_script('rating-help', get_stylesheet_directory_uri() . '/js/rating-help.js', array('jquery'), '1.0.1', true);
        }
        wp_enqueue_script('bst-tour-share', get_stylesheet_directory_uri() . '/js/bst-tour-share.js', array(), '1.1.0', true);
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
 * Mailchimp signup checkbox on blog comment forms (MC4WP wp-comment-form integration).
 * Uses the same audience as the homepage MC4WP form (form ID 9746 on production).
 */
function bst_get_mc4wp_reference_form_id() {
	return (int) apply_filters( 'bst_mc4wp_reference_form_id', 9746 );
}

function bst_get_mc4wp_reference_form_list_ids() {
	static $list_ids  = null;
	static $resolved = false;

	if ( $resolved ) {
		return $list_ids;
	}

	$list_ids = array();

	if ( function_exists( 'mc4wp_get_form' ) ) {
		try {
			$form = mc4wp_get_form( bst_get_mc4wp_reference_form_id() );
			if ( $form && method_exists( $form, 'get_lists' ) ) {
				$list_ids = array_values( array_filter( (array) $form->get_lists() ) );
			}
		} catch ( Exception $e ) {
			// Reference form missing on this environment.
		}
	}

	if ( empty( $list_ids ) ) {
		$integration_options = get_option( 'mc4wp_integrations', array() );
		if ( ! empty( $integration_options['wp-comment-form']['lists'] ) ) {
			$list_ids = array_values( array_filter( (array) $integration_options['wp-comment-form']['lists'] ) );
		}
	}

	$list_ids = (array) apply_filters( 'bst_mc4wp_comment_form_list_ids', $list_ids );

	$resolved = true;

	return $list_ids;
}

function bst_mc4wp_enable_comment_form_integration( $options ) {
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	if ( ! isset( $options['wp-comment-form'] ) || ! is_array( $options['wp-comment-form'] ) ) {
		$options['wp-comment-form'] = array();
	}

	$options['wp-comment-form']['enabled'] = 0;
	$options['wp-comment-form']['label']   = __( 'Subscribe to our Mailing List', 'althea-wp-child' );

	if ( empty( $options['wp-comment-form']['lists'] ) ) {
		$list_ids = bst_get_mc4wp_reference_form_list_ids();
		if ( ! empty( $list_ids ) ) {
			$options['wp-comment-form']['lists'] = $list_ids;
		}
	}

	return $options;
}
add_filter( 'mc4wp_integration_options', 'bst_mc4wp_enable_comment_form_integration' );

function bst_mc4wp_comment_form_show_checkbox( $show_checkbox, $integration_slug ) {
	// Theme renders the checkbox directly; keep MC4WP from duplicating it.
	if ( $integration_slug === 'wp-comment-form' ) {
		return false;
	}

	return $show_checkbox;
}
add_filter( 'mc4wp_integration_show_checkbox', 'bst_mc4wp_comment_form_show_checkbox', 10, 2 );

/**
 * Default Mailchimp interest merge field for comment signups (matches homepage form).
 */
function bst_mc4wp_default_interest_value() {
	return apply_filters( 'bst_mc4wp_default_interest', 'All Tours' );
}

function bst_mc4wp_comment_form_subscriber_data( $data, $comment_id ) {
	if ( empty( $data['MMERGE8'] ) ) {
		$data['MMERGE8'] = bst_mc4wp_default_interest_value();
	}

	return $data;
}
add_filter( 'mc4wp_integration_wp-comment-form_data', 'bst_mc4wp_comment_form_subscriber_data', 10, 2 );

/**
 * Build Mailchimp subscriber data from a posted comment.
 *
 * @param int $comment_id Comment ID.
 * @return array
 */
function bst_build_comment_mailchimp_subscriber_data( $comment_id ) {
	$comment = get_comment( $comment_id );
	if ( ! $comment || ! is_email( $comment->comment_author_email ) ) {
		return array();
	}

	$data = array(
		'EMAIL'    => $comment->comment_author_email,
		'NAME'     => $comment->comment_author,
		'MMERGE8'  => bst_mc4wp_default_interest_value(),
		'OPTIN_IP' => $comment->comment_author_IP,
	);

	if ( function_exists( 'mc4wp_add_name_data' ) ) {
		$data = mc4wp_add_name_data( $data );
	}

	// Homepage list requires LNAME; comment form only collects a single name field.
	if ( empty( $data['LNAME'] ) && ! empty( $data['FNAME'] ) ) {
		$data['LNAME'] = '.';
	}

	return apply_filters( 'mc4wp_integration_wp-comment-form_data', $data, $comment_id );
}

/**
 * Log comment Mailchimp issues to the PHP error log.
 *
 * @param string $message Log message.
 */
function bst_log_comment_mailchimp( $message ) {
	$should_log = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
		|| ( function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) );

	if ( ! $should_log ) {
		return;
	}

	error_log( 'BST Mailchimp comment: ' . $message );
}

/**
 * Whether the comment Mailchimp checkbox should appear on the current view.
 *
 * @return bool
 */
function bst_should_show_comment_mailchimp_checkbox() {
	return is_singular( 'post' ) && function_exists( 'mc4wp_get_api_v3' );
}

/**
 * Whether the visitor opted in on the comment form.
 *
 * @return bool
 */
function bst_comment_mailchimp_opted_in() {
	return ! empty( $_POST['_mc4wp_subscribe_wp-comment-form'] )
		&& (int) wp_unslash( $_POST['_mc4wp_subscribe_wp-comment-form'] ) === 1;
}

/**
 * Render the optional Mailchimp signup checkbox above the comment submit button.
 *
 * @param string $submit_button Submit button HTML.
 * @return string
 */
function bst_comment_form_add_mailchimp_checkbox( $submit_button ) {
	if ( ! bst_should_show_comment_mailchimp_checkbox() ) {
		return $submit_button;
	}

	$label = __( 'Subscribe to our Mailing List', 'althea-wp-child' );

	$checkbox = sprintf(
		'<p class="comment-form-mailchimp-consent">'
		. '<input id="wp-comment-mailchimp-consent" name="_mc4wp_subscribe_wp-comment-form" type="checkbox" value="1" />'
		. '<label for="wp-comment-mailchimp-consent">%s</label>'
		. '</p>',
		esc_html( $label )
	);

	return $checkbox . $submit_button;
}
add_filter( 'comment_form_submit_field', 'bst_comment_form_add_mailchimp_checkbox', 89 );

/**
 * Subscribe a commenter to the site's Mailchimp audience.
 *
 * @param int $comment_id Comment ID.
 * @return bool
 */
function bst_subscribe_comment_to_mailchimp( $comment_id ) {
	$list_ids = bst_get_mc4wp_reference_form_list_ids();
	if ( empty( $list_ids ) ) {
		bst_log_comment_mailchimp( 'No Mailchimp list IDs found for comment signups.' );
		return false;
	}

	$data = bst_build_comment_mailchimp_subscriber_data( $comment_id );
	if ( empty( $data['EMAIL'] ) ) {
		bst_log_comment_mailchimp( 'Missing email for comment ' . $comment_id . '.' );
		return false;
	}

	if ( ! function_exists( 'mc4wp_get_api_v3' ) || ! class_exists( 'MC4WP_List_Data_Mapper' ) ) {
		bst_log_comment_mailchimp( 'MC4WP Mailchimp API is not available.' );
		return false;
	}

	$email          = $data['EMAIL'];
	$api            = mc4wp_get_api_v3();
	$double_optin   = true;
	$stored_options = get_option( 'mc4wp_integrations', array() );

	if ( isset( $stored_options['wp-comment-form']['double_optin'] ) ) {
		$double_optin = (bool) $stored_options['wp-comment-form']['double_optin'];
	}

	$target_status = $double_optin ? 'pending' : 'subscribed';

	foreach ( $list_ids as $list_id ) {
		$existing = null;

		try {
			$existing = $api->get_list_member( $list_id, $email );
		} catch ( MC4WP_API_Resource_Not_Found_Exception $e ) {
			$existing = null;
		} catch ( MC4WP_API_Exception $e ) {
			bst_log_comment_mailchimp( 'Mailchimp lookup failed for ' . $email . ': ' . $e->getMessage() );
			return false;
		}

		if ( $existing && 'subscribed' === $existing->status ) {
			return true;
		}

		if ( $existing ) {
			// Record already exists as pending/unsubscribed/etc. Do not PATCH merge fields:
			// this audience has a required ADDRESS field and partial updates fail validation.
			try {
				if ( 'pending' === $existing->status && 'pending' === $target_status ) {
					$api->update_list_member(
						$list_id,
						$email,
						array(
							'status' => 'unsubscribed',
						)
					);
				}

				$update_args = array(
					'status'        => $target_status,
					'email_address' => $email,
					'email_type'    => mc4wp_get_email_type(),
				);

				if ( ! empty( $data['OPTIN_IP'] ) ) {
					$update_args['ip_signup'] = $data['OPTIN_IP'];
				}

				$api->update_list_member( $list_id, $email, $update_args );
				bst_log_comment_mailchimp(
					sprintf(
						'Re-subscribed existing Mailchimp contact %1$s as %2$s.',
						$email,
						$target_status
					)
				);

				return true;
			} catch ( MC4WP_API_Exception $e ) {
				bst_log_comment_mailchimp( 'Mailchimp re-subscribe failed for ' . $email . ': ' . $e->getMessage() );
				return false;
			}
		}

		$mapper     = new MC4WP_List_Data_Mapper( $data, array( $list_id ) );
		$map        = $mapper->map();
		$subscriber = $map[ $list_id ] ?? null;

		if ( ! $subscriber instanceof MC4WP_MailChimp_Subscriber ) {
			continue;
		}

		$subscriber->status     = $target_status;
		$subscriber->email_type = mc4wp_get_email_type();

		if ( ! empty( $data['OPTIN_IP'] ) ) {
			$subscriber->ip_signup = $data['OPTIN_IP'];
		}

		$subscriber = apply_filters( 'mc4wp_subscriber_data', $subscriber );
		$subscriber = apply_filters( 'mc4wp_integration_subscriber_data', $subscriber );
		$subscriber = apply_filters( 'mc4wp_integration_wp-comment-form_subscriber_data', $subscriber, $comment_id );

		if ( ! $subscriber instanceof MC4WP_MailChimp_Subscriber ) {
			continue;
		}

		try {
			$api->add_new_list_member( $list_id, $subscriber->to_array() );
			bst_log_comment_mailchimp(
				sprintf(
					'Added new Mailchimp contact %1$s as %2$s.',
					$email,
					$target_status
				)
			);

			return true;
		} catch ( MC4WP_API_Exception $e ) {
			bst_log_comment_mailchimp( 'Mailchimp add failed for ' . $email . ': ' . $e->getMessage() );
			return false;
		}
	}

	bst_log_comment_mailchimp( 'Mailchimp subscribe produced no list mappings for comment ' . $comment_id . '.' );

	return false;
}

/**
 * Subscribe commenters to Mailchimp when they opt in on blog posts.
 *
 * @param int    $comment_id       Comment ID.
 * @param string $comment_approved Comment approval status.
 */
function bst_comment_post_mailchimp_subscribe( $comment_id, $comment_approved ) {
	if ( ! bst_comment_mailchimp_opted_in() || 'spam' === $comment_approved ) {
		return;
	}

	$comment = get_comment( $comment_id );
	if ( ! $comment ) {
		return;
	}

	$post = get_post( (int) $comment->comment_post_ID );
	if ( ! $post || 'post' !== $post->post_type ) {
		return;
	}

	if ( ! function_exists( 'mc4wp_get_api_key' ) || ! mc4wp_get_api_key() ) {
		bst_log_comment_mailchimp( 'Mailchimp API key is missing. Add it under MC4WP → Mailchimp in wp-admin.' );
		return;
	}

	bst_subscribe_comment_to_mailchimp( $comment_id );
}
add_action( 'comment_post', 'bst_comment_post_mailchimp_subscribe', 40, 2 );
