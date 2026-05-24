<?php
/**
 * Structured data helpers for tour pages.
 *
 * Schema injected via wp_head on tour pages (Product, Event ×N, BreadcrumbList).
 * Organization schema injected on homepage.
 *
 * Both are output unconditionally — Yoast may or may not be generating these;
 * duplicate blocks are harmless and Google handles them gracefully.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', 'bst_wp_head_tour_schema', 5 );
add_action( 'wp_head', 'bst_wp_head_organization_schema', 5 );

/**
 * Output JSON-LD @graph on single tour pages.
 *
 * Always includes:
 *   - Product (with Offer)
 *   - Event (one node per upcoming date, up to 5)
 *   - BreadcrumbList
 *
 * AggregateRating added to Product when the filter bst_tour_aggregate_rating returns data.
 */
function bst_wp_head_tour_schema() {
	if ( ! is_singular( 'tour' ) ) {
		return;
	}

	$tour_id = get_queried_object_id();
	if ( ! $tour_id ) {
		return;
	}

	// ---- Core ACF fields ----
	$tour_title        = (string) get_the_title( $tour_id );
	$short_description = wp_strip_all_tags( (string) get_field( 'short_description', $tour_id ) );

	// Banner image: try detail_banner_image, fall back to image_1.
	// Normalise to a full absolute URL so schema validators never see a relative path.
	$bst_resolve_img = function( $raw ) {
		$raw = (string) $raw;
		if ( $raw === '' ) {
			return '';
		}
		if ( filter_var( $raw, FILTER_VALIDATE_URL ) ) {
			return $raw; // already absolute
		}
		if ( $raw[0] === '/' ) {
			return home_url( $raw ); // root-relative
		}
		return ''; // attachment ID or unrecognised — skip
	};
	$banner_image = $bst_resolve_img( get_field( 'detail_banner_image', $tour_id ) );
	if ( $banner_image === '' ) {
		$banner_image = $bst_resolve_img( get_field( 'image_1', $tour_id ) );
	}
	$starting_from     = (string) get_field( 'starting_from', $tour_id );
	$airport           = (string) get_field( 'airport', $tour_id );
	$currency          = (string) get_field( 'currency', $tour_id );
	if ( ! $currency ) {
		$currency = 'EUR';
	}
	$permalink = (string) get_permalink( $tour_id );

	// ---- Price: lowest per-person price across all packages (half of total = shared-double rate) ----
	// Tries packages in order; stops at the first non-zero value so we always
	// have a price if any package is configured on the tour.
	$price           = null;
	$package_pricing = get_field( 'package_pricing', $tour_id );
	if ( is_array( $package_pricing ) ) {
		$min_half = null;
		for ( $pkg_i = 1; $pkg_i <= 5; $pkg_i++ ) {
			$pkg_val = isset( $package_pricing[ 'package_' . $pkg_i ] ) ? floatval( $package_pricing[ 'package_' . $pkg_i ] ) : 0;
			if ( $pkg_val > 0 ) {
				$half = round( $pkg_val / 2 );
				if ( $min_half === null || $half < $min_half ) {
					$min_half = $half;
				}
			}
		}
		if ( $min_half !== null ) {
			$price = (string) $min_half;
		}
	}

	// ---- Tour type title (for breadcrumb) ----
	$tour_type_title = '';
	if ( function_exists( 'bst_get_tour_type_for_tour' ) ) {
		$tt              = bst_get_tour_type_for_tour( $tour_id );
		$tour_type_title = isset( $tt['title'] ) ? (string) $tt['title'] : '';
	}

	// ---- Upcoming tour dates (flat, sorted ASC, future only, capped at 5) ----
	$all_flat = array();
	if ( function_exists( 'bst_get_tour_dates_grouped_by_year' ) ) {
		foreach ( bst_get_tour_dates_grouped_by_year( $tour_id ) as $dates ) {
			$all_flat = array_merge( $all_flat, (array) $dates );
		}
	}
	usort( $all_flat, function ( $a, $b ) {
		return strcmp( (string) $a['start_date'], (string) $b['start_date'] );
	} );
	$today    = current_time( 'Y-m-d' );
	$upcoming = array();
	foreach ( $all_flat as $d ) {
		$ymd = function_exists( 'bst_tour_date_acf_date_meta_to_ymd' )
			? bst_tour_date_acf_date_meta_to_ymd( $d['start_date'] )
			: '';
		if ( $ymd !== '' && $ymd > $today ) {
			$upcoming[] = $d;
		}
	}
	$upcoming = array_slice( $upcoming, 0, 5 );

	// ---- Breadcrumb taxonomy term ----
	$bc_term_name = $tour_type_title;
	$bc_term_link = '';
	$bc_terms     = get_the_terms( $tour_id, 'tour-type-code' );
	if ( $bc_terms && ! is_wp_error( $bc_terms ) ) {
		$link = get_term_link( $bc_terms[0] );
		if ( ! is_wp_error( $link ) ) {
			$bc_term_link = (string) $link;
		}
		if ( ! $bc_term_name ) {
			$bc_term_name = $bc_terms[0]->name;
		}
	}

	// ---- Availability: InStock when any UPCOMING date has open slots ----
	$has_open_slot = false;
	foreach ( $upcoming as $d ) {
		if ( intval( $d['availability'] ) > 0 ) {
			$has_open_slot = true;
			break;
		}
	}
	$product_availability = $has_open_slot
		? 'https://schema.org/InStock'
		: 'https://schema.org/PreOrder'; // tours run each year; PreOrder signals future availability

	// ---- Build: Offer (base, shared by Product) ----
	$location_name = $starting_from . ( $airport ? ' (' . $airport . ')' : '' );

	$base_offer = array(
		'@type'         => 'Offer',
		'url'           => $permalink,
		'priceCurrency' => $currency,
		'availability'  => $product_availability,
		'seller'        => array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => get_bloginfo( 'name' ),
		),
	);
	if ( $price !== null ) {
		$base_offer['price']           = $price;
		$base_offer['priceValidUntil'] = wp_date( 'Y' ) . '-12-31';
	}

	// ---- Build: Product ----
	$product = array(
		'@type'       => 'Product',
		'@id'         => $permalink . '#product',
		'name'        => $tour_title,
		'description' => $short_description,
		'url'         => $permalink,
		'brand'       => array( '@type' => 'Brand', 'name' => get_bloginfo( 'name' ) ),
		'offers'      => $base_offer,
	);
	if ( $banner_image ) {
		$product['image'] = $banner_image;
	}

	// AggregateRating: no numeric review field exists in ACF today.
	// Supply data via this filter when a review system is added:
	//   add_filter( 'bst_tour_aggregate_rating', function( $r, $id ) {
	//       return array( 'ratingValue' => '4.8', 'reviewCount' => '12', 'bestRating' => '5' );
	//   }, 10, 2 );
	$agg = apply_filters( 'bst_tour_aggregate_rating', null, $tour_id );
	if ( is_array( $agg ) && ! empty( $agg['ratingValue'] ) ) {
		$product['aggregateRating'] = array_merge( array( '@type' => 'AggregateRating' ), $agg );
	}

	// ---- Build: Events (one node per upcoming date) ----
	$events = array();
	foreach ( $upcoming as $tour_date ) {
		$start_ymd = function_exists( 'bst_tour_date_acf_date_meta_to_ymd' )
			? bst_tour_date_acf_date_meta_to_ymd( $tour_date['start_date'] )
			: '';
		$end_ymd   = function_exists( 'bst_tour_date_acf_date_meta_to_ymd' )
			? bst_tour_date_acf_date_meta_to_ymd( $tour_date['end_date'] )
			: '';
		if ( ! $start_ymd ) {
			continue;
		}

		$avail = intval( $tour_date['availability'] );

		$event_offer = array(
			'@type'         => 'Offer',
			'url'           => $permalink,
			'priceCurrency' => $currency,
			'availability'  => $avail > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
		);
		if ( $price !== null ) {
			$event_offer['price'] = $price;
		}

		$event = array(
			'@type'               => 'Event',
			'@id'                 => $permalink . '#event-' . $start_ymd,
			'name'                => $tour_title,
			'description'         => $short_description,
			'startDate'           => $start_ymd,
			'eventStatus'         => $avail > 0
				? 'https://schema.org/EventScheduled'
				: 'https://schema.org/EventSoldOut',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'location'            => array(
				'@type' => 'Place',
				'name'  => $location_name,
			),
			'organizer'           => array(
				'@type' => 'Organization',
				'@id'   => home_url( '/#organization' ),
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
			'offers'              => $event_offer,
		);
		if ( $banner_image ) {
			$event['image'] = $banner_image;
		}
		if ( $end_ymd ) {
			$event['endDate'] = $end_ymd;
		}

		$events[] = $event;
	}

	// ---- Build: BreadcrumbList ----
	$bc_items = array(
		array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
		array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Our Tours', 'item' => (string) get_post_type_archive_link( 'tour-type' ) ),
	);
	$pos = 3;
	if ( $bc_term_link && $bc_term_name ) {
		$bc_items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => $bc_term_name, 'item' => $bc_term_link );
	}
	$bc_items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => $tour_title, 'item' => $permalink );

	$breadcrumbs = array(
		'@type'           => 'BreadcrumbList',
		'@id'             => $permalink . '#breadcrumb',
		'itemListElement' => $bc_items,
	);

	// ---- Assemble @graph ----
	$graph = array_merge( array( $product, $breadcrumbs ), $events );

	$schema = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	echo "\n" . '<script type="application/ld+json">' . "\n";
	echo wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	echo "\n" . '</script>' . "\n";
}

/**
 * Output Organization JSON-LD on the homepage.
 *
 * Output unconditionally — Yoast may or may not be generating this correctly.
 * To add social profile URLs: add_filter( 'bst_organization_same_as', function( $urls ) { ... } );
 */
function bst_wp_head_organization_schema() {
	if ( ! is_front_page() ) {
		return;
	}

	$logo_url = '';
	$logo_id  = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$src = wp_get_attachment_image_src( $logo_id, 'full' );
		if ( $src ) {
			$logo_url = $src[0];
		}
	}
	if ( ! $logo_url ) {
		$icon_id = (int) get_option( 'site_icon' );
		if ( $icon_id ) {
			$src = wp_get_attachment_image_src( $icon_id, 'full' );
			if ( $src ) {
				$logo_url = $src[0];
			}
		}
	}

	$org_description = trim( (string) get_option( 'bst_organization_description', '' ) );
	if ( $org_description === '' ) {
		$org_description = get_bloginfo( 'description' );
	}

	$org = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'@id'      => home_url( '/#organization' ),
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
	);
	if ( $org_description ) {
		$org['description'] = $org_description;
	}
	$org['areaServed'] = 'Europe';
	if ( $logo_url ) {
		$org['logo'] = array(
			'@type'      => 'ImageObject',
			'url'        => $logo_url,
			'contentUrl' => $logo_url,
		);
	}

	// Social URLs from BST Settings → SEO & Schema → Social Profile URLs.
	$social_raw = trim( (string) get_option( 'bst_organization_social_urls', '' ) );
	$same_as    = array();
	if ( $social_raw !== '' ) {
		foreach ( preg_split( '/\r\n|\r|\n/', $social_raw ) as $line ) {
			$url = trim( $line );
			if ( $url !== '' && filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$same_as[] = $url;
			}
		}
	}
	// Allow code-level additions via filter.
	$same_as = apply_filters( 'bst_organization_same_as', $same_as );
	if ( ! empty( $same_as ) ) {
		$org['sameAs'] = array_values( array_unique( $same_as ) );
	}

	$website = array(
		'@type'           => 'WebSite',
		'@id'             => home_url( '/#website' ),
		'url'             => home_url( '/' ),
		'name'            => get_bloginfo( 'name' ),
		'description'     => get_bloginfo( 'description' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		),
	);

	unset( $org['@context'] );

	$graph = array(
		'@context' => 'https://schema.org',
		'@graph'   => array( $org, $website ),
	);

	echo "\n" . '<script type="application/ld+json">' . "\n";
	echo wp_json_encode( $graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	echo "\n" . '</script>' . "\n";
}

add_action( 'wp_head', 'bst_wp_head_collection_schema', 5 );

/**
 * Output CollectionPage + ItemList JSON-LD on tour-type archive and taxonomy term archives.
 */
function bst_wp_head_collection_schema() {
	$site_name = get_bloginfo( 'name' );

	// ---- tour-type post type archive (/tour-types/) ----
	if ( function_exists( 'bst_is_tour_type_post_type_archive' ) && bst_is_tour_type_post_type_archive() ) {
		$heading  = function_exists( 'bst_get_tour_type_post_type_archive_display_title' )
			? bst_get_tour_type_post_type_archive_display_title()
			: 'Our Tours';
		$url      = (string) get_post_type_archive_link( 'tour-type' );

		$tour_types = get_posts( array(
			'post_type'      => 'tour-type',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$items = array();
		$pos   = 1;
		foreach ( $tour_types as $tt ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => get_the_title( $tt->ID ),
				'url'      => (string) get_permalink( $tt->ID ),
			);
		}

		if ( empty( $items ) ) {
			return;
		}

		// Use the same resolved SEO title as <title> and og:title.
		$seo_data  = function_exists( 'bst_seo_resolve_head_data' ) ? bst_seo_resolve_head_data() : array();
		$page_name = ! empty( $seo_data['title'] ) ? $seo_data['title'] : $heading . ' - ' . $site_name;

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'CollectionPage',
			'name'       => $page_name,
			'url'        => $url,
			'mainEntity' => array(
				'@type'           => 'ItemList',
				'itemListElement' => $items,
			),
		);

		echo "\n" . '<script type="application/ld+json">' . "\n";
		echo wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		echo "\n" . '</script>' . "\n";
		return;
	}

	// ---- tour-type-code taxonomy term archive ----
	if ( function_exists( 'bst_is_queried_tour_type_code_term_archive' ) && bst_is_queried_tour_type_code_term_archive() ) {
		$term = get_queried_object();
		if ( ! ( $term instanceof WP_Term ) ) {
			return;
		}

		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return;
		}

		$banner_data = function_exists( 'bst_get_queried_tour_type_code_banner_data' )
			? bst_get_queried_tour_type_code_banner_data()
			: array( 'heading' => $term->name );
		$heading = $banner_data['heading'];

		$tours = get_posts( array(
			'post_type'      => 'tour',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => array( array(
				'taxonomy' => 'tour-type-code',
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			) ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$items = array();
		$pos   = 1;
		foreach ( $tours as $tour ) {
			if ( function_exists( 'get_field' ) && get_field( 'private', $tour->ID ) ) {
				continue;
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => get_the_title( $tour->ID ),
				'url'      => (string) get_permalink( $tour->ID ),
			);
		}

		if ( empty( $items ) ) {
			return;
		}

		// Use the same resolved SEO title as <title> and og:title.
		$seo_data  = function_exists( 'bst_seo_resolve_head_data' ) ? bst_seo_resolve_head_data() : array();
		$page_name = ! empty( $seo_data['title'] ) ? $seo_data['title'] : $heading . ' - ' . $site_name;

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'CollectionPage',
			'name'       => $page_name,
			'url'        => (string) $link,
			'mainEntity' => array(
				'@type'           => 'ItemList',
				'itemListElement' => $items,
			),
		);

		echo "\n" . '<script type="application/ld+json">' . "\n";
		echo wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		echo "\n" . '</script>' . "\n";
	}
}

/**
 * Backward-compatible stub — schema now output by bst_wp_head_tour_schema() via wp_head.
 * Kept so the function_exists() check in single-tour.php does not break.
 */
function bst_output_tour_json_ld( $tour_id, $tour_type_id, $tour_title, $short_description, $schedule, $starting_from, array $tour_dates ) {
	// No-op: replaced by bst_wp_head_tour_schema() hooked to wp_head at priority 5.
}
