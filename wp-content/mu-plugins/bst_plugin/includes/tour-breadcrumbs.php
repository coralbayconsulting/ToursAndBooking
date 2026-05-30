<?php
/**
 * Shared breadcrumb helpers for tour archives and taxonomy pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the breadcrumb list for tour archives/taxonomies.
 *
 * Structure:
 * Home / OUR TOURS [/ CURRENT LABEL]
 *
 * When $current_label is empty, only "Home / OUR TOURS" is rendered.
 *
 * @param string $current_label Optional label for the final crumb (e.g., tour-type name).
 */
function bst_render_tour_archive_breadcrumbs($current_label = '') {
    ?>
    <ol class="bst-breadcrumb-list">
        <li class="bst-breadcrumb-item">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="bst-breadcrumb-link">
                <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="home" viewBox="0 0 1664 1896.0833" class="bst-breadcrumb-home-icon">
                    <path d="M1408 992v480q0 26-19 45t-45 19H960v-384H704v384H320q-26 0-45-19t-19-45V992q0-1 .5-3t.5-3l575-474 575 474q1 2 1 6zm223-69l-62 74q-8 9-21 11h-3q-13 0-21-7L832 424l-692 577q-12 8-24 7-13-2-21-11l-62-74q-8-10-7-23.5T37 878l719-599q32-26 76-26t76 26l244 204V288q0-14 9-23t23-9h192q14 0 23 9t9 23v408l219 182q10 8 11 21.5t-7 23.5z" />
                </svg>
            </a>
        </li>
        <li class="bst-breadcrumb-separator">/</li>
        <li class="bst-breadcrumb-item">
            <a href="<?php echo esc_url(get_post_type_archive_link('tour-type')); ?>" class="bst-breadcrumb-link">OUR TOURS</a>
        </li>
        <?php if (!empty($current_label)) : ?>
            <li class="bst-breadcrumb-separator">/</li>
            <li class="bst-breadcrumb-item">
                <span class="bst-breadcrumb-text"><?php echo strtoupper(esc_html($current_label)); ?></span>
            </li>
        <?php endif; ?>
    </ol>
    <?php
}

/**
 * URL for the main blog posts index.
 *
 * @return string
 */
function bst_get_blog_index_url() {
    $posts_page_id = (int) get_option( 'page_for_posts' );
    if ( $posts_page_id ) {
        return get_permalink( $posts_page_id );
    }

    $archive_link = get_post_type_archive_link( 'post' );
    return $archive_link ? $archive_link : home_url( '/' );
}

/**
 * Label for the blog index breadcrumb.
 *
 * @return string
 */
function bst_get_blog_index_label() {
    $posts_page_id = (int) get_option( 'page_for_posts' );
    if ( $posts_page_id ) {
        return get_the_title( $posts_page_id );
    }

    return 'Blog';
}

/**
 * Primary category for a blog post (Yoast primary when available).
 *
 * @param int|null $post_id Post ID.
 * @return WP_Term|null
 */
function bst_get_primary_category_for_post( $post_id = null ) {
    $post_id = $post_id ? (int) $post_id : get_the_ID();
    if ( ! $post_id ) {
        return null;
    }

    if ( class_exists( 'WPSEO_Primary_Term' ) ) {
        $primary = new WPSEO_Primary_Term( 'category', $post_id );
        $term_id = $primary->get_primary_term();
        if ( $term_id && ! is_wp_error( $term_id ) ) {
            $term = get_term( (int) $term_id, 'category' );
            if ( $term && ! is_wp_error( $term ) ) {
                return $term;
            }
        }
    }

    $categories = get_the_category( $post_id );
    if ( empty( $categories ) ) {
        return null;
    }

    return $categories[0];
}

/**
 * Render breadcrumbs for a single blog post.
 *
 * Structure: Home / Blog [/ Category] / Post title
 */
function bst_render_blog_post_breadcrumbs() {
    $blog_url   = bst_get_blog_index_url();
    $blog_label = bst_get_blog_index_label();
    $category   = bst_get_primary_category_for_post();
    $post_title = get_the_title();
    ?>
    <ol class="bst-breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">
        <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="bst-breadcrumb-link" itemprop="item">
                <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1664 1896.0833" class="bst-breadcrumb-home-icon" aria-hidden="true">
                    <path d="M1408 992v480q0 26-19 45t-45 19H960v-384H704v384H320q-26 0-45-19t-19-45V992q0-1 .5-3t.5-3l575-474 575 474q1 2 1 6zm223-69l-62 74q-8 9-21 11h-3q-13 0-21-7L832 424l-692 577q-12 8-24 7-13-2-21-11l-62-74q-8-10-7-23.5T37 878l719-599q32-26 76-26t76 26l244 204V288q0-14 9-23t23-9h192q14 0 23 9t9 23v408l219 182q10 8 11 21.5t-7 23.5z" />
                </svg>
                <span itemprop="name">Home</span>
            </a>
            <meta itemprop="position" content="1" />
        </li>
        <li class="bst-breadcrumb-separator">/</li>
        <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <a href="<?php echo esc_url( $blog_url ); ?>" class="bst-breadcrumb-link" itemprop="item">
                <span itemprop="name"><?php echo esc_html( strtoupper( $blog_label ) ); ?></span>
            </a>
            <meta itemprop="position" content="2" />
        </li>
        <?php if ( $category ) : ?>
            <li class="bst-breadcrumb-separator">/</li>
            <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>" class="bst-breadcrumb-link" itemprop="item">
                    <span itemprop="name"><?php echo esc_html( strtoupper( $category->name ) ); ?></span>
                </a>
                <meta itemprop="position" content="3" />
            </li>
            <li class="bst-breadcrumb-separator">/</li>
            <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <span class="bst-breadcrumb-text" itemprop="name"><?php echo esc_html( strtoupper( $post_title ) ); ?></span>
                <meta itemprop="position" content="4" />
            </li>
        <?php else : ?>
            <li class="bst-breadcrumb-separator">/</li>
            <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <span class="bst-breadcrumb-text" itemprop="name"><?php echo esc_html( strtoupper( $post_title ) ); ?></span>
                <meta itemprop="position" content="3" />
            </li>
        <?php endif; ?>
    </ol>
    <?php
}

/**
 * Render breadcrumbs for the blog index or a category archive.
 *
 * Structure: Home / Blog [/ Category]
 *
 * @param string $current_label Optional final crumb (e.g. category name). When empty, Blog is the current page.
 */
function bst_render_blog_archive_breadcrumbs( $current_label = '' ) {
    $blog_url   = bst_get_blog_index_url();
    $blog_label = bst_get_blog_index_label();
    ?>
    <ol class="bst-breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">
        <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="bst-breadcrumb-link" itemprop="item">
                <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1664 1896.0833" class="bst-breadcrumb-home-icon" aria-hidden="true">
                    <path d="M1408 992v480q0 26-19 45t-45 19H960v-384H704v384H320q-26 0-45-19t-19-45V992q0-1 .5-3t.5-3l575-474 575 474q1 2 1 6zm223-69l-62 74q-8 9-21 11h-3q-13 0-21-7L832 424l-692 577q-12 8-24 7-13-2-21-11l-62-74q-8-10-7-23.5T37 878l719-599q32-26 76-26t76 26l244 204V288q0-14 9-23t23-9h192q14 0 23 9t9 23v408l219 182q10 8 11 21.5t-7 23.5z" />
                </svg>
                <span itemprop="name">Home</span>
            </a>
            <meta itemprop="position" content="1" />
        </li>
        <li class="bst-breadcrumb-separator">/</li>
        <?php if ( $current_label === '' ) : ?>
            <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <span class="bst-breadcrumb-text" itemprop="name"><?php echo esc_html( strtoupper( $blog_label ) ); ?></span>
                <meta itemprop="position" content="2" />
            </li>
        <?php else : ?>
            <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a href="<?php echo esc_url( $blog_url ); ?>" class="bst-breadcrumb-link" itemprop="item">
                    <span itemprop="name"><?php echo esc_html( strtoupper( $blog_label ) ); ?></span>
                </a>
                <meta itemprop="position" content="2" />
            </li>
            <li class="bst-breadcrumb-separator">/</li>
            <li class="bst-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <span class="bst-breadcrumb-text" itemprop="name"><?php echo esc_html( strtoupper( $current_label ) ); ?></span>
                <meta itemprop="position" content="3" />
            </li>
        <?php endif; ?>
    </ol>
    <?php
}

/**
 * Default blog banner URL (Settings → Blog Banner, then site fallback image).
 *
 * @return string
 */
function bst_get_blog_banner_image_url() {
    $default = '/wp-content/uploads/default-banner.jpg';
    $image   = (string) get_option( 'bst_blog_banner_image', '' );

    return $image !== '' ? $image : $default;
}

/**
 * Banner heading + image for the blog posts index.
 *
 * @return array{heading: string, image: string}
 */
function bst_get_blog_index_banner_data() {
    return array(
        'heading' => bst_get_blog_index_label(),
        'image'   => bst_get_blog_banner_image_url(),
    );
}

/**
 * Banner image URL for a blog category (ACF banner, then site blog banner default).
 *
 * @param WP_Term|null $term Category term.
 * @return string
 */
function bst_get_category_banner_image_url( $term ) {
    $image = bst_get_blog_banner_image_url();

    if ( ! $term instanceof WP_Term || $term->taxonomy !== 'category' ) {
        return $image;
    }

    if ( function_exists( 'get_field' ) ) {
        $acf_banner = get_field( 'banner_image', $term );
        if ( ! $acf_banner ) {
            $acf_banner = get_field( 'banner_image', 'category_' . (int) $term->term_id );
        }
        if ( is_array( $acf_banner ) && ! empty( $acf_banner['url'] ) ) {
            $acf_banner = $acf_banner['url'];
        }
        if ( is_string( $acf_banner ) && $acf_banner !== '' ) {
            $image = $acf_banner;
        }
    }

    return $image;
}

/**
 * Banner heading + image for the current blog category archive.
 *
 * @return array{heading: string, image: string}
 */
function bst_get_queried_category_banner_data() {
    $term = get_queried_object();

    if ( ! ( $term instanceof WP_Term ) || $term->taxonomy !== 'category' ) {
        return array(
            'heading' => '',
            'image'   => bst_get_blog_banner_image_url(),
        );
    }

    return array(
        'heading' => $term->name,
        'image'   => bst_get_category_banner_image_url( $term ),
    );
}

/**
 * Banner heading + image for a single blog post (featured image, then site blog banner default).
 *
 * @param int|null $post_id Post ID.
 * @return array{heading: string, image: string}
 */
function bst_get_single_post_banner_data( $post_id = null ) {
    $post_id = $post_id ? (int) $post_id : get_the_ID();
    $heading = $post_id ? get_the_title( $post_id ) : '';
    $image   = '';

    if ( $post_id && has_post_thumbnail( $post_id ) ) {
        $thumb = get_the_post_thumbnail_url( $post_id, 'full' );
        if ( $thumb ) {
            $image = $thumb;
        }
    }

    if ( $image === '' ) {
        $image = bst_get_blog_banner_image_url();
    }

    return array(
        'heading' => $heading,
        'image'   => $image,
    );
}

/**
 * Categories to show on the main blog index.
 *
 * @return WP_Term[]
 */
function bst_get_blog_index_categories() {
    $exclude = array_filter( array( (int) get_option( 'default_category' ) ) );

    $categories = get_categories(
        array(
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'exclude'    => $exclude,
            'orderby'    => 'name',
            'order'      => 'ASC',
        )
    );

    if ( ! is_array( $categories ) ) {
        return array();
    }

    $categories = array_filter(
        $categories,
        static function ( $category ) {
            return function_exists( 'bst_get_category_published_post_count' )
                ? bst_get_category_published_post_count( $category ) > 0
                : (int) $category->count > 0;
        }
    );

    return array_values( $categories );
}

/**
 * Published post count for a category, matching the category archive query (includes child categories).
 *
 * @param WP_Term|int $term Category term or term ID.
 * @return int
 */
function bst_get_category_published_post_count( $term ) {
    $term_id = $term instanceof WP_Term ? (int) $term->term_id : (int) $term;
    if ( $term_id <= 0 ) {
        return 0;
    }

    $query = new WP_Query(
        array(
            'cat'                    => $term_id,
            'post_type'              => 'post',
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'ignore_sticky_posts'    => true,
        )
    );

    $count = (int) $query->found_posts;
    wp_reset_postdata();

    return $count;
}

