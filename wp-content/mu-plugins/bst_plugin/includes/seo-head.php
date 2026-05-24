<?php
/**
 * BST SEO head output — replaces Yoast for all tour-related page types.
 *
 * Outputs <title>, meta description, canonical, Open Graph, and Twitter Card.
 * Silently deactivates when Yoast SEO is active so there is no double output
 * during the transition period. Once Yoast is uninstalled this takes over.
 *
 * Reading priority for every field:
 *   BST SEO override field (bst_seo_*) → content field default → WP core fallback
 *
 * Covered page types:
 *   - Single tour (CPT: tour)
 *   - Single tour-type (CPT: tour-type)          [individual tour-type post]
 *   - Tour-type-code taxonomy archive             [/tours/{slug}/]
 *   - Tour-type CPT archive                       [/tour-types/]
 *   - All other pages / posts (basic fallback)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', 'bst_seo_head_output', 1 );

// Use wp_robots filter so our directives replace WordPress core's default
// max-image-preview:large output rather than duplicating it.
add_filter( 'wp_robots', 'bst_seo_robots_directives' );

function bst_seo_robots_directives( $robots ) {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return $robots;
	}
	$is_private_tour = is_singular( 'tour' ) && function_exists( 'get_field' ) && get_field( 'private', get_queried_object_id() );
	if ( $is_private_tour ) {
		return array( 'noindex' => true );
	}
	return array(
		'index'             => true,
		'follow'            => true,
		'max-image-preview' => 'large',
		'max-snippet'       => '-1',
		'max-video-preview' => '-1',
	);
}

function bst_seo_head_output() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return;
	}

	$data = bst_seo_resolve_head_data();
	if ( empty( $data ) ) {
		return;
	}

	// ---- <title> (only when theme does not support title-tag) ----
	// WordPress core outputs <title> via wp_head when add_theme_support('title-tag') is set.
	// We filter pre_get_document_title / document_title instead (see below), so no echo here.

	// ---- Meta description ----
	if ( ! empty( $data['description'] ) ) {
		echo '<meta name="description" content="' . esc_attr( $data['description'] ) . '">' . "\n";
	}

	// ---- Canonical ----
	if ( ! empty( $data['canonical'] ) ) {
		echo '<link rel="canonical" href="' . esc_url( $data['canonical'] ) . '">' . "\n";
	}

	// ---- Open Graph ----
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";

	if ( ! empty( $data['title'] ) ) {
		echo '<meta property="og:title" content="' . esc_attr( $data['title'] ) . '">' . "\n";
	}
	if ( ! empty( $data['description'] ) ) {
		echo '<meta property="og:description" content="' . esc_attr( $data['description'] ) . '">' . "\n";
	}
	if ( ! empty( $data['canonical'] ) ) {
		echo '<meta property="og:url" content="' . esc_url( $data['canonical'] ) . '">' . "\n";
	}

	$og_type = isset( $data['og_type'] ) ? $data['og_type'] : 'website';
	echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";

	if ( ! empty( $data['image'] ) ) {
		$og_image = $data['image'][0] === '/' ? home_url( $data['image'] ) : $data['image'];
		echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
		echo '<meta property="og:image:alt" content="' . esc_attr( $data['title'] ?? '' ) . '">' . "\n";
	}

	// ---- Twitter Card ----
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	if ( ! empty( $data['title'] ) ) {
		echo '<meta name="twitter:title" content="' . esc_attr( $data['title'] ) . '">' . "\n";
	}
	if ( ! empty( $data['description'] ) ) {
		echo '<meta name="twitter:description" content="' . esc_attr( $data['description'] ) . '">' . "\n";
	}
	if ( ! empty( $data['image'] ) ) {
		echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '">' . "\n";
	}
}

// ---- Title tag filters (replaces Yoast title output) ----

add_filter( 'pre_get_document_title', 'bst_seo_document_title', 100002 );
add_filter( 'document_title',         'bst_seo_document_title', 100002 );

function bst_seo_document_title( $title ) {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return $title;
	}
	$data = bst_seo_resolve_head_data();
	if ( ! empty( $data['title'] ) ) {
		return $data['title'];
	}
	return $title;
}

// ---- Data resolver ----

/**
 * Build the SEO data array for the current page.
 *
 * @return array{title:string, description:string, canonical:string, image:string, og_type:string}
 */
function bst_seo_resolve_head_data() {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}

	$site_name = get_bloginfo( 'name' );
	$sep       = ' - ';

	if ( is_singular( 'tour' ) ) {
		$cache = bst_seo_data_for_tour( get_queried_object_id(), $site_name, $sep );
	} elseif ( is_singular( 'tour-type' ) ) {
		$cache = bst_seo_data_for_tour_type_post( get_queried_object_id(), $site_name, $sep );
	} elseif ( function_exists( 'bst_is_queried_tour_type_code_term_archive' ) && bst_is_queried_tour_type_code_term_archive() ) {
		$cache = bst_seo_data_for_tour_type_code_term( get_queried_object(), $site_name, $sep );
	} elseif ( function_exists( 'bst_is_tour_type_post_type_archive' ) && bst_is_tour_type_post_type_archive() ) {
		$cache = bst_seo_data_for_tour_type_archive( $site_name, $sep );
	} else {
		$cache = bst_seo_data_fallback( $site_name, $sep );
	}

	return $cache;
}

// ---- Per-type resolvers ----

function bst_seo_data_for_tour( $post_id, $site_name, $sep ) {
	$post_title = get_the_title( $post_id );
	$seo_title  = bst_seo_clean_field( get_field( 'bst_seo_title', $post_id ) );
	$seo_desc   = bst_seo_clean_field( get_field( 'bst_seo_description', $post_id ) );

	$title = $seo_title !== ''
		? $seo_title
		: $post_title . $sep . $site_name;

	$desc_raw = $seo_desc !== ''
		? $seo_desc
		: wp_strip_all_tags( (string) get_field( 'short_description', $post_id ) );
	$description = bst_seo_trim_description( $desc_raw );

	return array(
		'title'       => $title,
		'description' => $description,
		'canonical'   => (string) get_permalink( $post_id ),
		'image'       => (string) get_field( 'detail_banner_image', $post_id ),
		'og_type'     => 'product',
	);
}

function bst_seo_data_for_tour_type_post( $post_id, $site_name, $sep ) {
	$post_title = get_the_title( $post_id );
	$seo_title  = bst_seo_clean_field( get_field( 'bst_seo_title', $post_id ) );
	$seo_desc   = bst_seo_clean_field( get_field( 'bst_seo_description', $post_id ) );

	$title = $seo_title !== ''
		? $seo_title
		: $post_title . $sep . $site_name;

	$desc_raw = $seo_desc !== ''
		? $seo_desc
		: wp_strip_all_tags( (string) get_field( 'listing_description', $post_id ) );
	$description = bst_seo_trim_description( $desc_raw );

	return array(
		'title'       => $title,
		'description' => $description,
		'canonical'   => (string) get_permalink( $post_id ),
		'image'       => (string) get_field( 'banner_image', $post_id ),
		'og_type'     => 'website',
	);
}

function bst_seo_get_linked_tour_type_post_id( $term_id ) {
	$posts = get_posts( array(
		'post_type'      => 'tour-type',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array( array(
			'key'   => 'type_code',
			'value' => $term_id,
		) ),
	) );
	return ! empty( $posts ) ? (int) $posts[0] : 0;
}

function bst_seo_data_for_tour_type_code_term( $term, $site_name, $sep ) {
	if ( ! ( $term instanceof WP_Term ) ) {
		return array();
	}

	// SEO fields come from the linked tour-type post — that is the canonical place
	// to enter title/description for the taxonomy archive.
	$seo_title = '';
	$seo_desc  = '';
	if ( function_exists( 'get_field' ) ) {
		$linked_post_id = bst_seo_get_linked_tour_type_post_id( $term->term_id );
		if ( $linked_post_id ) {
			$seo_title = bst_seo_clean_field( get_field( 'bst_seo_title', $linked_post_id ) );
			$seo_desc  = bst_seo_clean_field( get_field( 'bst_seo_description', $linked_post_id ) );
		}
	}

	// Banner heading + image from the linked tour-type post.
	$banner_data = function_exists( 'bst_get_queried_tour_type_code_banner_data' )
		? bst_get_queried_tour_type_code_banner_data()
		: array( 'heading' => $term->name, 'image' => '' );

	$heading = $banner_data['heading'];

	$title = $seo_title !== ''
		? $seo_title
		: $heading . $sep . $site_name;

	$desc_raw = $seo_desc !== ''
		? $seo_desc
		: ( $term->description ? $term->description : 'Explore our ' . $heading . ' tours and adventures.' );
	$description = bst_seo_trim_description( wp_strip_all_tags( $desc_raw ) );

	$image = $banner_data['image'];
	if ( $image && $image[0] === '/' ) {
		$image = home_url( $image );
	}

	$link = get_term_link( $term );

	return array(
		'title'       => $title,
		'description' => $description,
		'canonical'   => ! is_wp_error( $link ) ? (string) $link : '',
		'image'       => $image,
		'og_type'     => 'website',
	);
}

function bst_seo_data_for_tour_type_archive( $site_name, $sep ) {
	$seo_title_opt = trim( (string) get_option( 'bst_ptarchive_tour_type_page_title', '' ) );
	$heading       = $seo_title_opt !== '' ? $seo_title_opt : 'Our Tours';
	$seo_desc      = trim( (string) get_option( 'bst_ptarchive_tour_type_meta_description', '' ) );

	$title = $heading . $sep . $site_name;

	$description = bst_seo_trim_description(
		$seo_desc !== ''
			? $seo_desc
			: 'Discover our guided ' . $heading . '. Scenic roads, expert guides, and unforgettable experiences across Europe.'
	);

	// Banner image from BST Settings.
	$image = (string) get_option( 'bst_banner_image', '' );

	return array(
		'title'       => $title,
		'description' => $description,
		'canonical'   => (string) get_post_type_archive_link( 'tour-type' ),
		'image'       => $image,
		'og_type'     => 'website',
	);
}

function bst_seo_data_fallback( $site_name, $sep ) {
	// Front page may also satisfy is_singular() when set to a static page — check it first.
	if ( is_front_page() || is_home() ) {
		$front_id = (int) get_option( 'page_on_front' );
		$desc_raw = ( $front_id && has_excerpt( $front_id ) )
			? wp_strip_all_tags( get_the_excerpt( $front_id ) )
			: get_bloginfo( 'description' );
		$image = '';
		if ( $front_id && has_post_thumbnail( $front_id ) ) {
			$src   = wp_get_attachment_image_src( get_post_thumbnail_id( $front_id ), 'large' );
			$image = $src ? $src[0] : '';
		}
		return array(
			'title'       => $site_name . $sep . get_bloginfo( 'description' ),
			'description' => bst_seo_trim_description( $desc_raw ),
			'canonical'   => home_url( '/' ),
			'image'       => $image,
			'og_type'     => 'website',
		);
	}

	if ( is_singular() ) {
		$post_id    = get_queried_object_id();
		$title      = get_the_title( $post_id ) . $sep . $site_name;
		$excerpt    = has_excerpt( $post_id )
			? wp_strip_all_tags( get_the_excerpt( $post_id ) )
			: wp_strip_all_tags( wp_trim_words( strip_shortcodes( get_post_field( 'post_content', $post_id ) ), 30, '...' ) );
		$description = bst_seo_trim_description( $excerpt );
		$image      = '';
		if ( has_post_thumbnail( $post_id ) ) {
			$src   = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
			$image = $src ? $src[0] : '';
		}
		$canonical = (string) get_permalink( $post_id );
	} else {
		return array();
	}

	return array(
		'title'       => $title,
		'description' => $description,
		'canonical'   => $canonical,
		'image'       => $image,
		'og_type'     => 'website',
	);
}

// ---- Helpers ----

/**
 * Resolve BST and Yoast template variables in an SEO field value.
 *
 * Supported variables:
 *   {site_name}    or  %%sitename%%  → site name
 *   {sep}          or  %%sep%%       → ' - '
 *
 * Any remaining unknown %%var%% tokens are stripped.
 */
function bst_seo_resolve_template_vars( $value ) {
	$value = (string) $value;
	if ( $value === '' ) {
		return '';
	}
	$site_name = get_bloginfo( 'name' );
	$sep       = ' - ';

	// BST-style variables (case-sensitive, curly-brace syntax).
	$value = str_replace(
		array( '{site_name}', '{sep}' ),
		array( $site_name,    $sep    ),
		$value
	);

	// Yoast-style variables (case-insensitive, %% syntax) — known subset only.
	$value = str_ireplace(
		array( '%%sitename%%', '%%sep%%', '%%page%%', '%%pagenumber%%', '%%pagetotal%%' ),
		array( $site_name,     $sep,      '',          '',                ''              ),
		$value
	);

	// Strip any remaining unknown %%var%% tokens.
	$value = preg_replace( '/%%[^%]*%%/', '', $value );

	return trim( preg_replace( '/\s+/', ' ', $value ) );
}

function bst_seo_clean_field( $value ) {
	return bst_seo_resolve_template_vars( $value );
}

function bst_seo_trim_description( $text, $max = 155 ) {
	$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
	if ( mb_strlen( $text ) > $max ) {
		$text = mb_substr( $text, 0, $max - 1 ) . '…';
	}
	return $text;
}

// ---- SEO field cleanup (converts Yoast %% vars to BST {vars} in the DB) ----

/**
 * Convert Yoast template variables to BST equivalents for storage.
 * Known vars become {site_name} / {sep}; unknown %%var%% tokens are stripped.
 */
function bst_seo_normalize_field_for_storage( $value ) {
	$value = (string) $value;
	if ( $value === '' ) {
		return '';
	}

	// Convert known Yoast vars to BST equivalents.
	$value = str_ireplace(
		array( '%%sitename%%', '%%sep%%' ),
		array( '{site_name}',  '{sep}'   ),
		$value
	);

	// Strip page-number vars (meaningless in SEO title/description).
	$value = str_ireplace(
		array( '%%page%%', '%%pagenumber%%', '%%pagetotal%%' ),
		'',
		$value
	);

	// Strip any remaining unknown %%var%% tokens.
	$value = preg_replace( '/%%[^%]*%%/', '', $value );

	return trim( preg_replace( '/\s+/', ' ', $value ) );
}

add_action( 'wp_ajax_bst_seo_cleanup_template_vars', 'bst_seo_cleanup_template_vars_handler' );

function bst_seo_cleanup_template_vars_handler() {
	check_ajax_referer( 'bst_seo_cleanup', '_wpnonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$updated = 0;
	$fields  = array( 'bst_seo_title', 'bst_seo_description' );

	// Posts.
	$posts = get_posts( array(
		'post_type'      => array( 'tour', 'tour-type', 'page', 'post' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	foreach ( $posts as $post_id ) {
		foreach ( $fields as $field ) {
			$raw = get_post_meta( $post_id, $field, true );
			if ( ! $raw ) {
				continue;
			}
			$clean = bst_seo_normalize_field_for_storage( $raw );
			if ( $clean !== $raw ) {
				update_post_meta( $post_id, $field, $clean );
				$updated++;
			}
		}
	}

	// Taxonomy terms.
	$terms = get_terms( array( 'taxonomy' => 'tour-type-code', 'hide_empty' => false ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			foreach ( $fields as $field ) {
				$raw = get_term_meta( $term->term_id, $field, true );
				if ( ! $raw ) {
					continue;
				}
				$clean = bst_seo_normalize_field_for_storage( $raw );
				if ( $clean !== $raw ) {
					update_term_meta( $term->term_id, $field, $clean );
					$updated++;
				}
			}
		}
	}

	wp_send_json_success( "Done. {$updated} field(s) updated." );
}
