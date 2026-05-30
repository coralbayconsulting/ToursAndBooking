<?php
/**
 * Structured data for native blog pages.
 *
 * Outputs JSON-LD on:
 *   - Single blog post (BlogPosting + BreadcrumbList, publisher → /#organization)
 *   - Blog posts index (CollectionPage + category ItemList + BreadcrumbList)
 *   - Category archive (CollectionPage + post ItemList + BreadcrumbList)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', 'bst_wp_head_blog_schema', 5 );

/**
 * @return array<string, mixed>
 */
function bst_schema_organization_publisher() {
	return array(
		'@type' => 'Organization',
		'@id'   => home_url( '/#organization' ),
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);
}

/**
 * @param array<string, mixed> $schema
 */
function bst_schema_output_json_ld( $schema ) {
	echo "\n" . '<script type="application/ld+json">' . "\n";
	echo wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	echo "\n" . '</script>' . "\n";
}

/**
 * @param string $raw Image URL or root-relative path.
 * @return string
 */
function bst_schema_resolve_image_url( $raw ) {
	$raw = (string) $raw;
	if ( $raw === '' ) {
		return '';
	}
	if ( filter_var( $raw, FILTER_VALIDATE_URL ) ) {
		return $raw;
	}
	if ( $raw[0] === '/' ) {
		return home_url( $raw );
	}
	return '';
}

/**
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function bst_schema_blog_post_author( $post_id ) {
	$author_id = (int) get_post_field( 'post_author', $post_id );
	$author    = array(
		'@type' => 'Person',
		'name'  => get_the_author_meta( 'display_name', $author_id ),
	);

	$author_url = get_author_posts_url( $author_id );
	if ( $author_url ) {
		$author['url'] = $author_url;
	}

	return $author;
}

/**
 * @param int $post_id Post ID.
 * @return array<int, array<string, mixed>>
 */
function bst_schema_blog_post_breadcrumb_items( $post_id ) {
	$blog_url   = function_exists( 'bst_get_blog_index_url' ) ? bst_get_blog_index_url() : home_url( '/' );
	$blog_label = function_exists( 'bst_get_blog_index_label' ) ? bst_get_blog_index_label() : 'Blog';
	$post_title = get_the_title( $post_id );
	$permalink  = (string) get_permalink( $post_id );
	$category   = function_exists( 'bst_get_primary_category_for_post' )
		? bst_get_primary_category_for_post( $post_id )
		: null;

	$items = array(
		array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
		array( '@type' => 'ListItem', 'position' => 2, 'name' => $blog_label, 'item' => $blog_url ),
	);

	$pos = 3;
	if ( $category instanceof WP_Term ) {
		$cat_link = get_term_link( $category );
		if ( ! is_wp_error( $cat_link ) ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => $category->name,
				'item'     => (string) $cat_link,
			);
		}
	}

	$items[] = array(
		'@type'    => 'ListItem',
		'position' => $pos,
		'name'     => $post_title,
		'item'     => $permalink,
	);

	return $items;
}

/**
 * @param string $current_label Optional final crumb label (category name).
 * @param string $current_url   Optional final crumb URL.
 * @return array<int, array<string, mixed>>
 */
function bst_schema_blog_archive_breadcrumb_items( $current_label = '', $current_url = '' ) {
	$blog_url   = function_exists( 'bst_get_blog_index_url' ) ? bst_get_blog_index_url() : home_url( '/' );
	$blog_label = function_exists( 'bst_get_blog_index_label' ) ? bst_get_blog_index_label() : 'Blog';

	$items = array(
		array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
	);

	if ( $current_label === '' ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => 2,
			'name'     => $blog_label,
			'item'     => $blog_url,
		);
		return $items;
	}

	$items[] = array(
		'@type'    => 'ListItem',
		'position' => 2,
		'name'     => $blog_label,
		'item'     => $blog_url,
	);
	$items[] = array(
		'@type'    => 'ListItem',
		'position' => 3,
		'name'     => $current_label,
		'item'     => $current_url !== '' ? $current_url : $blog_url,
	);

	return $items;
}

/**
 * @param string                             $url
 * @param string                             $name
 * @param string                             $description
 * @param array<int, array<string, mixed>>   $list_items
 * @param array<int, array<string, mixed>>   $breadcrumb_items
 * @param string                             $image
 */
function bst_schema_output_blog_collection_page( $url, $name, $description, array $list_items, array $breadcrumb_items, $image = '' ) {
	$page = array(
		'@type'       => 'CollectionPage',
		'@id'         => $url . '#webpage',
		'name'        => $name,
		'url'         => $url,
		'description' => $description,
		'publisher'   => bst_schema_organization_publisher(),
		'isPartOf'    => array(
			'@type' => 'WebSite',
			'@id'   => home_url( '/#website' ),
		),
	);

	if ( $image !== '' ) {
		$page['image'] = $image;
	}

	if ( ! empty( $list_items ) ) {
		$page['mainEntity'] = array(
			'@type'           => 'ItemList',
			'itemListElement' => $list_items,
		);
	}

	$graph = array(
		$page,
		array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $url . '#breadcrumb',
			'itemListElement' => $breadcrumb_items,
		),
	);

	bst_schema_output_json_ld(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		)
	);
}

function bst_wp_head_blog_schema() {
	if ( is_singular( 'post' ) ) {
		bst_wp_head_blog_post_schema();
		return;
	}

	if ( is_home() && ! is_front_page() ) {
		bst_wp_head_blog_index_schema();
		return;
	}

	if ( is_category() ) {
		bst_wp_head_blog_category_schema();
	}
}

function bst_wp_head_blog_post_schema() {
	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	$permalink = (string) get_permalink( $post_id );
	$seo_data  = function_exists( 'bst_seo_resolve_head_data' ) ? bst_seo_resolve_head_data() : array();
	$headline  = get_the_title( $post_id );
	$description = ! empty( $seo_data['description'] ) ? $seo_data['description'] : '';

	$article = array(
		'@type'            => 'BlogPosting',
		'@id'              => $permalink . '#article',
		'headline'         => $headline,
		'url'              => $permalink,
		'datePublished'    => get_the_date( 'c', $post_id ),
		'dateModified'     => get_the_modified_date( 'c', $post_id ),
		'author'           => bst_schema_blog_post_author( $post_id ),
		'publisher'        => bst_schema_organization_publisher(),
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => $permalink,
		),
		'isPartOf'         => array(
			'@type' => 'WebSite',
			'@id'   => home_url( '/#website' ),
		),
	);

	if ( $description !== '' ) {
		$article['description'] = $description;
	}

	$image = '';
	if ( has_post_thumbnail( $post_id ) ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
		$image = $src ? bst_schema_resolve_image_url( $src[0] ) : '';
	}
	if ( $image !== '' ) {
		$article['image'] = array(
			'@type' => 'ImageObject',
			'url'   => $image,
		);
	}

	$category = function_exists( 'bst_get_primary_category_for_post' )
		? bst_get_primary_category_for_post( $post_id )
		: null;
	if ( $category instanceof WP_Term ) {
		$article['articleSection'] = $category->name;
	}

	$word_count = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
	if ( $word_count > 0 ) {
		$article['wordCount'] = $word_count;
	}

	bst_schema_output_json_ld(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				$article,
				array(
					'@type'           => 'BreadcrumbList',
					'@id'             => $permalink . '#breadcrumb',
					'itemListElement' => bst_schema_blog_post_breadcrumb_items( $post_id ),
				),
			),
		)
	);
}

function bst_wp_head_blog_index_schema() {
	$url = function_exists( 'bst_get_blog_index_url' ) ? bst_get_blog_index_url() : home_url( '/' );

	$seo_data    = function_exists( 'bst_seo_resolve_head_data' ) ? bst_seo_resolve_head_data() : array();
	$page_name   = ! empty( $seo_data['title'] ) ? $seo_data['title'] : get_bloginfo( 'name' );
	$description = ! empty( $seo_data['description'] ) ? $seo_data['description'] : '';
	$image       = ! empty( $seo_data['image'] ) ? bst_schema_resolve_image_url( $seo_data['image'] ) : '';

	$categories = function_exists( 'bst_get_blog_index_categories' ) ? bst_get_blog_index_categories() : array();
	$items      = array();
	$pos        = 1;

	foreach ( $categories as $category ) {
		if ( ! ( $category instanceof WP_Term ) ) {
			continue;
		}
		$link = get_term_link( $category );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => $category->name,
			'url'      => (string) $link,
		);
	}

	bst_schema_output_blog_collection_page(
		$url,
		$page_name,
		$description,
		$items,
		bst_schema_blog_archive_breadcrumb_items(),
		$image
	);
}

function bst_wp_head_blog_category_schema() {
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) || $term->taxonomy !== 'category' ) {
		return;
	}

	$link = get_term_link( $term );
	if ( is_wp_error( $link ) ) {
		return;
	}
	$url = (string) $link;

	$seo_data    = function_exists( 'bst_seo_resolve_head_data' ) ? bst_seo_resolve_head_data() : array();
	$page_name   = ! empty( $seo_data['title'] ) ? $seo_data['title'] : $term->name . ' - ' . get_bloginfo( 'name' );
	$description = ! empty( $seo_data['description'] ) ? $seo_data['description'] : '';
	$image       = ! empty( $seo_data['image'] ) ? bst_schema_resolve_image_url( $seo_data['image'] ) : '';

	$posts = get_posts(
		array(
			'cat'                    => $term->term_id,
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$items = array();
	$pos   = 1;
	foreach ( $posts as $post ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => get_the_title( $post->ID ),
			'url'      => (string) get_permalink( $post->ID ),
		);
	}

	bst_schema_output_blog_collection_page(
		$url,
		$page_name,
		$description,
		$items,
		bst_schema_blog_archive_breadcrumb_items( $term->name, $url ),
		$image
	);
}
