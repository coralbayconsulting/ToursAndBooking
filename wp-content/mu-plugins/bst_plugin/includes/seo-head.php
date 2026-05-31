<?php
/**
 * BST SEO head output for tour, blog, and related page types.
 *
 * Outputs <title>, meta description, canonical, Open Graph, and Twitter Card.
 *
 * Reading priority for every field:
 *   BST SEO override field (bst_seo_*) → content field default → WP core fallback
 *
 * Covered page types:
 *   - Single tour (CPT: tour)
 *   - Single tour-type (CPT: tour-type)          [individual tour-type post]
 *   - Tour-type-code taxonomy archive             [/tours/{slug}/]
 *   - Tour-type CPT archive                       [/tour-types/]
 *   - Single blog post (post)                     [article traffic]
 *   - Blog category archive (category)            [title, description, banner image]
 *   - Blog posts index (Settings → Posts page)    [blog banner + page title]
 *   - Other singular pages / posts (basic fallback)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Priority 0 fires before WordPress core's _wp_render_title_tag at priority 1,
// giving us the chance to output <title> first and remove the core action.
add_action( 'wp_head', 'bst_seo_head_output', 0 );

// Use wp_robots filter so our directives replace WordPress core's default
// max-image-preview:large output rather than duplicating it.
add_filter( 'wp_robots', 'bst_seo_robots_directives' );

// Append BST-specific disallow rules to the dynamically generated robots.txt.
add_filter( 'robots_txt', 'bst_robots_txt_rules' );

function bst_seo_robots_directives( $robots ) {
	// Internal CPTs — never index these.
	if ( is_singular( array( 'tour-date', 'vehicle', 'source-code', 'email-template' ) ) ) {
		return array( 'noindex' => true, 'follow' => false );
	}

	// Colibri page builder taxonomy archives.
	if ( is_tax( 'element_category' ) ) {
		return array( 'noindex' => true, 'follow' => false );
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

function bst_robots_txt_rules( $output ) {
	$rules = "\n# BST: Block internal and non-public paths\n"
		. "Disallow: /wp-content/themes/\n"
		. "Disallow: /wp-content/plugins/\n"
		. "Disallow: /tour-date/\n"
		. "Disallow: /vehicle/\n"
		. "Disallow: /source-code/\n"
		. "Disallow: /email-template/\n"
		. "Disallow: /element_category/\n"
		. "Disallow: /booking/\n"
		. "Disallow: /bookinginvoice/\n"
		. "Disallow: /wp-json/\n"
		. "Disallow: /*?post_type=\n"
		. "Disallow: /*?s=\n";

	return $output . $rules;
}

function bst_seo_head_output() {
	$data = bst_seo_resolve_head_data();
	if ( empty( $data ) ) {
		return;
	}

	// ---- <title> ----
	// Output directly and remove WordPress core's _wp_render_title_tag (priority 1) so the
	// theme's document_title filters cannot override our custom SEO title.
	if ( ! empty( $data['title'] ) ) {
		remove_action( 'wp_head', '_wp_render_title_tag', 1 );
		echo '<title>' . esc_html( $data['title'] ) . '</title>' . "\n";
	}

	// ---- Meta description ----
	if ( ! empty( $data['description'] ) ) {
		echo '<meta name="description" content="' . esc_attr( $data['description'] ) . '">' . "\n";
	}

	// ---- Canonical ----
	// Remove WordPress core's rel_canonical (priority 10) so there is only one canonical tag.
	remove_action( 'wp_head', 'rel_canonical' );
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

	if ( $og_type === 'article' && is_singular( 'post' ) ) {
		$post_id = get_queried_object_id();
		if ( $post_id ) {
			echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c', $post_id ) ) . '">' . "\n";
			echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c', $post_id ) ) . '">' . "\n";
			if ( function_exists( 'bst_get_primary_category_for_post' ) ) {
				$category = bst_get_primary_category_for_post( $post_id );
				if ( $category instanceof WP_Term ) {
					echo '<meta property="article:section" content="' . esc_attr( $category->name ) . '">' . "\n";
				}
			}
		}
	}

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
	} elseif ( is_singular( 'post' ) ) {
		$cache = bst_seo_data_for_blog_post( get_queried_object_id(), $site_name, $sep );
	} elseif ( is_category() ) {
		$cache = bst_seo_data_for_blog_category( get_queried_object(), $site_name, $sep );
	} elseif ( is_home() && ! is_front_page() ) {
		$cache = bst_seo_data_for_blog_index( $site_name, $sep );
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

/**
 * SEO for a single blog post — primary traffic target for the blog.
 *
 * @param int    $post_id   Post ID.
 * @param string $site_name Site name.
 * @param string $sep       Title separator.
 * @return array{title:string, description:string, canonical:string, image:string, og_type:string}
 */
function bst_seo_data_for_blog_post( $post_id, $site_name, $sep ) {
	$post_id    = (int) $post_id;
	$post_title = get_the_title( $post_id );

	$title = $post_title . $sep . $site_name;

	if ( has_excerpt( $post_id ) ) {
		$desc_raw = wp_strip_all_tags( get_the_excerpt( $post_id ) );
	} else {
		$desc_raw = wp_strip_all_tags(
			wp_trim_words( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ), 30, '...' )
		);
	}
	$description = bst_seo_trim_description( $desc_raw );

	$image = '';
	if ( has_post_thumbnail( $post_id ) ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
		$image = $src ? $src[0] : '';
	}

	return array(
		'title'       => $title,
		'description' => $description,
		'canonical'   => (string) get_permalink( $post_id ),
		'image'       => bst_seo_normalize_image_url( $image ),
		'og_type'     => 'article',
	);
}

/**
 * SEO for a native blog category archive (mirrors tour-type-code term archives).
 *
 * @param WP_Term|null $term      Category term.
 * @param string       $site_name Site name.
 * @param string       $sep       Title separator.
 * @return array{title:string, description:string, canonical:string, image:string, og_type:string}
 */
function bst_seo_data_for_blog_category( $term, $site_name, $sep ) {
	if ( ! ( $term instanceof WP_Term ) || $term->taxonomy !== 'category' ) {
		return array();
	}

	$heading = $term->name;
	$title   = $heading . $sep . $site_name;

	if ( $term->description ) {
		$desc_raw = wp_strip_all_tags( $term->description );
	} else {
		$default_desc = trim( (string) get_option( 'bst_ptarchive_blog_category_meta_description', '' ) );
		if ( $default_desc !== '' ) {
			$desc_raw = bst_seo_clean_field( $default_desc, array( 'category_name' => $heading ) );
		} else {
			$desc_raw = 'Read ' . $heading . ' articles and stories from ' . $site_name . '.';
		}
	}
	$description = bst_seo_trim_description( $desc_raw );

	$image = '';
	if ( function_exists( 'bst_get_category_banner_image_url' ) ) {
		$image = bst_get_category_banner_image_url( $term );
	}

	$link = get_term_link( $term );

	return array(
		'title'       => $title,
		'description' => $description,
		'canonical'   => ! is_wp_error( $link ) ? (string) $link : '',
		'image'       => bst_seo_normalize_image_url( $image ),
		'og_type'     => 'website',
	);
}

/**
 * SEO for the blog posts index (Settings → Reading → Posts page).
 *
 * @param string $site_name Site name.
 * @param string $sep       Title separator.
 * @return array{title:string, description:string, canonical:string, image:string, og_type:string}
 */
function bst_seo_data_for_blog_index( $site_name, $sep ) {
	$posts_page_id = (int) get_option( 'page_for_posts' );
	$default_label = $posts_page_id
		? get_the_title( $posts_page_id )
		: ( function_exists( 'bst_get_blog_index_label' ) ? bst_get_blog_index_label() : 'Blog' );
	$seo_title_opt = trim( (string) get_option( 'bst_ptarchive_blog_page_title', '' ) );
	$heading       = $seo_title_opt !== '' ? bst_seo_clean_field( $seo_title_opt ) : $default_label;
	$seo_desc      = trim( (string) get_option( 'bst_ptarchive_blog_meta_description', '' ) );

	$title = $heading . $sep . $site_name;

	if ( $seo_desc !== '' ) {
		$desc_raw = bst_seo_clean_field( $seo_desc );
	} elseif ( $posts_page_id && has_excerpt( $posts_page_id ) ) {
		$desc_raw = wp_strip_all_tags( get_the_excerpt( $posts_page_id ) );
	} else {
		$desc_raw = 'Explore articles about our tours, travel tips, and motoring adventures from ' . $site_name . '.';
	}
	$description = bst_seo_trim_description( $desc_raw );

	$image = function_exists( 'bst_get_blog_banner_image_url' ) ? bst_get_blog_banner_image_url() : '';
	$canonical = $posts_page_id
		? (string) get_permalink( $posts_page_id )
		: (string) get_post_type_archive_link( 'post' );

	return array(
		'title'       => $title,
		'description' => $description,
		'canonical'   => $canonical,
		'image'       => bst_seo_normalize_image_url( $image ),
		'og_type'     => 'website',
	);
}

function bst_seo_data_fallback( $site_name, $sep ) {
	// Static front page only — blog index is handled by bst_seo_data_for_blog_index().
	if ( is_front_page() ) {
		$front_id = (int) get_option( 'page_on_front' );

		$canonical = home_url( '/' );
		$desc_raw  = ( $front_id && has_excerpt( $front_id ) )
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
			'canonical'   => $canonical,
			'image'       => bst_seo_normalize_image_url( $image ),
			'og_type'     => 'website',
		);
	}

	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		if ( get_post_type( $post_id ) === 'post' ) {
			return array();
		}

		$title       = get_the_title( $post_id ) . $sep . $site_name;
		$excerpt     = has_excerpt( $post_id )
			? wp_strip_all_tags( get_the_excerpt( $post_id ) )
			: wp_strip_all_tags( wp_trim_words( strip_shortcodes( get_post_field( 'post_content', $post_id ) ), 30, '...' ) );
		$description = bst_seo_trim_description( $excerpt );
		$image       = '';
		if ( has_post_thumbnail( $post_id ) ) {
			$src   = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
			$image = $src ? $src[0] : '';
		}
		$canonical = (string) get_permalink( $post_id );

		return array(
			'title'       => $title,
			'description' => $description,
			'canonical'   => $canonical,
			'image'       => bst_seo_normalize_image_url( $image ),
			'og_type'     => 'website',
		);
	}

	return array();
}

// ---- Helpers ----

/**
 * Resolve BST template variables and legacy %% syntax in an SEO field value.
 *
 * Supported variables:
 *   {site_name}      or  %%sitename%%  → site name
 *   {sep}            or  %%sep%%       → ' - '
 *   {category_name}  or  {title}       → category name (when provided in $context)
 *
 * Any remaining unknown %%var%% tokens are stripped.
 *
 * @param string               $value   Field value.
 * @param array<string,string> $context Optional template context.
 */
function bst_seo_resolve_template_vars( $value, $context = array() ) {
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

	if ( ! empty( $context['category_name'] ) ) {
		$category_name = (string) $context['category_name'];
		$value         = str_replace(
			array( '{category_name}', '{title}' ),
			array( $category_name, $category_name ),
			$value
		);
	}

	// Legacy %% syntax (case-insensitive) — known subset only.
	$value = str_ireplace(
		array( '%%sitename%%', '%%sep%%', '%%page%%', '%%pagenumber%%', '%%pagetotal%%' ),
		array( $site_name,     $sep,      '',          '',                ''              ),
		$value
	);

	// Strip any remaining unknown %%var%% tokens.
	$value = preg_replace( '/%%[^%]*%%/', '', $value );

	return trim( preg_replace( '/\s+/', ' ', $value ) );
}

function bst_seo_clean_field( $value, $context = array() ) {
	return bst_seo_resolve_template_vars( $value, $context );
}

/**
 * @param string $image Absolute or root-relative image URL.
 * @return string
 */
function bst_seo_normalize_image_url( $image ) {
	$image = (string) $image;
	if ( $image === '' ) {
		return '';
	}
	if ( $image[0] === '/' ) {
		return home_url( $image );
	}
	return $image;
}

function bst_seo_trim_description( $text, $max = 155 ) {
	$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
	if ( mb_strlen( $text ) > $max ) {
		$text = mb_substr( $text, 0, $max - 1 ) . '…';
	}
	return $text;
}

// ---- SEO field cleanup (converts legacy %% vars to BST {vars} in the DB) ----

/**
 * Convert legacy %% template variables to BST equivalents for storage.
 * Known vars become {site_name} / {sep}; unknown %%var%% tokens are stripped.
 */
function bst_seo_normalize_field_for_storage( $value ) {
	$value = (string) $value;
	if ( $value === '' ) {
		return '';
	}

	// Convert known legacy vars to BST equivalents.
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
