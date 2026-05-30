<?php
/**
 * BST XML Sitemap.
 *
 * Registers /bst-sitemap.xml as an index pointing to four sub-sitemaps:
 *   /bst-sitemap.xml?type=tours          — all published tour posts
 *   /bst-sitemap.xml?type=tour-types     — all published tour-type posts
 *   /bst-sitemap.xml?type=taxonomy       — all tour-type-code taxonomy terms
 *   /bst-sitemap.xml?type=pages          — all published pages
 *   /bst-sitemap.xml?type=blog           — blog index, categories, and posts
 *
 * When Yoast is active its own sitemap takes precedence; this sitemap is still
 * accessible at /bst-sitemap.xml but Yoast's /sitemap_index.xml is what search
 * engines follow. After uninstalling Yoast, submit /bst-sitemap.xml to Search
 * Console and add it to robots.txt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init',            'bst_sitemap_add_rewrite_rules' );
add_filter( 'query_vars',      'bst_sitemap_add_query_vars' );
add_action( 'template_redirect', 'bst_sitemap_handle_request' );

function bst_sitemap_add_rewrite_rules() {
	add_rewrite_rule( '^bst-sitemap\.xml$', 'index.php?bst_sitemap=1', 'top' );
}

function bst_sitemap_add_query_vars( $vars ) {
	$vars[] = 'bst_sitemap';
	return $vars;
}

function bst_sitemap_handle_request() {
	if ( ! get_query_var( 'bst_sitemap' ) ) {
		return;
	}

	$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';

	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex, follow', true );

	if ( $type === '' ) {
		bst_sitemap_output_index();
	} else {
		bst_sitemap_output_sub( $type );
	}
	exit;
}

// ---- Index ----

function bst_sitemap_output_index() {
	$types = array( 'tours', 'tour-types', 'taxonomy', 'pages', 'blog' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( $types as $type ) {
		$url = home_url( '/bst-sitemap.xml?type=' . $type );
		echo "\t<sitemap>\n";
		echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
		echo "\t\t<lastmod>" . esc_html( gmdate( 'Y-m-d' ) ) . "</lastmod>\n";
		echo "\t</sitemap>\n";
	}
	echo '</sitemapindex>';
}

// ---- Sub-sitemaps ----

function bst_sitemap_output_sub( $type ) {
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
	echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

	switch ( $type ) {
		case 'tours':
			bst_sitemap_urls_for_post_type( 'tour', 'weekly', '0.9', 'detail_banner_image' );
			break;
		case 'tour-types':
			bst_sitemap_urls_for_post_type( 'tour-type', 'weekly', '0.8', 'banner_image' );
			break;
		case 'taxonomy':
			bst_sitemap_urls_for_taxonomy( 'tour-type-code' );
			break;
		case 'pages':
			bst_sitemap_urls_for_post_type( 'page', 'monthly', '0.5', '' );
			break;
		case 'blog':
			bst_sitemap_urls_for_blog();
			break;
	}

	echo '</urlset>';
}

function bst_sitemap_emit_url( $url, $changefreq, $priority, $image = '', $image_title = '', $lastmod = '' ) {
	if ( ! $url ) {
		return;
	}

	echo "\t<url>\n";
	echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
	if ( $lastmod !== '' ) {
		echo "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
	}
	echo "\t\t<changefreq>" . esc_html( $changefreq ) . "</changefreq>\n";
	echo "\t\t<priority>" . esc_html( $priority ) . "</priority>\n";
	if ( $image ) {
		echo "\t\t<image:image>\n";
		echo "\t\t\t<image:loc>" . esc_url( $image ) . "</image:loc>\n";
		if ( $image_title !== '' ) {
			echo "\t\t\t<image:title>" . esc_html( $image_title ) . "</image:title>\n";
		}
		echo "\t\t</image:image>\n";
	}
	echo "\t</url>\n";
}

function bst_sitemap_urls_for_blog() {
	if ( function_exists( 'bst_get_blog_index_url' ) ) {
		$index_url   = bst_get_blog_index_url();
		$index_title = function_exists( 'bst_get_blog_index_label' ) ? bst_get_blog_index_label() : 'Blog';
		$index_image = function_exists( 'bst_get_blog_banner_image_url' ) ? bst_get_blog_banner_image_url() : '';
		bst_sitemap_emit_url( $index_url, 'weekly', '0.6', $index_image, $index_title );
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => true,
		)
	);

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}

			$image = '';
			if ( function_exists( 'bst_get_category_banner_image_url' ) ) {
				$image = bst_get_category_banner_image_url( $term );
			}

			bst_sitemap_emit_url( (string) $link, 'weekly', '0.65', $image, $term->name );
		}
	}

	bst_sitemap_urls_for_post_type( 'post', 'monthly', '0.7', '' );
}

function bst_sitemap_urls_for_post_type( $post_type, $changefreq, $priority, $image_field ) {
	$posts = get_posts( array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	) );

	foreach ( $posts as $post ) {
		// Skip private tours from the sitemap.
		if ( $post_type === 'tour' && function_exists( 'get_field' ) ) {
			if ( get_field( 'private', $post->ID ) ) {
				continue;
			}
		}

		$url      = get_permalink( $post->ID );
		$lastmod  = gmdate( 'Y-m-d', strtotime( $post->post_modified_gmt ) );
		$image    = '';
		if ( $image_field && function_exists( 'get_field' ) ) {
			$image = (string) get_field( $image_field, $post->ID );
		}
		if ( ! $image && has_post_thumbnail( $post->ID ) ) {
			$src   = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'large' );
			$image = $src ? $src[0] : '';
		}

		echo "\t<url>\n";
		echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
		echo "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
		echo "\t\t<changefreq>" . esc_html( $changefreq ) . "</changefreq>\n";
		echo "\t\t<priority>" . esc_html( $priority ) . "</priority>\n";
		if ( $image ) {
			echo "\t\t<image:image>\n";
			echo "\t\t\t<image:loc>" . esc_url( $image ) . "</image:loc>\n";
			echo "\t\t\t<image:title>" . esc_html( get_the_title( $post->ID ) ) . "</image:title>\n";
			echo "\t\t</image:image>\n";
		}
		echo "\t</url>\n";
	}
}

function bst_sitemap_urls_for_taxonomy( $taxonomy ) {
	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => true,
	) );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}

	foreach ( $terms as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}

		// Try to get the banner image from the linked tour-type post.
		$image = '';
		if ( function_exists( 'get_field' ) ) {
			$image = (string) get_field( 'bst_seo_social_image', $term );
		}

		echo "\t<url>\n";
		echo "\t\t<loc>" . esc_url( $link ) . "</loc>\n";
		echo "\t\t<changefreq>weekly</changefreq>\n";
		echo "\t\t<priority>0.8</priority>\n";
		if ( $image ) {
			echo "\t\t<image:image>\n";
			echo "\t\t\t<image:loc>" . esc_url( $image ) . "</image:loc>\n";
			echo "\t\t\t<image:title>" . esc_html( $term->name ) . "</image:title>\n";
			echo "\t\t</image:image>\n";
		}
		echo "\t</url>\n";
	}
}

/**
 * Add sitemap URL to robots.txt when Yoast is not active.
 */
add_filter( 'robots_txt', 'bst_sitemap_robots_txt', 10, 2 );

function bst_sitemap_robots_txt( $output, $public ) {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return $output;
	}
	if ( $public === '1' || $public === 1 ) {
		$output .= "\nSitemap: " . esc_url( home_url( '/bst-sitemap.xml' ) ) . "\n";
	}
	return $output;
}
