<?php
/**
 * Category archive template.
 *
 * @package Althea_WP_Child
 */

get_header();

$category    = get_queried_object();
$cat_name    = $category instanceof WP_Term ? $category->name : single_cat_title( '', false );
$share_url   = $category instanceof WP_Term ? get_term_link( $category ) : '';
$share_label = $cat_name ? sprintf( '%s articles', $cat_name ) : 'these articles';

if ( is_wp_error( $share_url ) ) {
    $share_url = '';
}

$bst_banner   = function_exists( 'bst_get_queried_category_banner_data' )
    ? bst_get_queried_category_banner_data()
    : array(
        'heading' => $cat_name,
        'image'   => function_exists( 'bst_get_blog_banner_image_url' ) ? bst_get_blog_banner_image_url() : '',
    );
$banner_text  = $bst_banner['heading'] !== '' ? $bst_banner['heading'] : $cat_name;
$banner_image = $bst_banner['image'];
?>

<div class="page-content">
    <div id="content" class="content">
        <div class="translucent-overlay">
            <div class="top-banner-container">
                <?php if ( $banner_image ) : ?>
                    <img class="top-banner" src="<?php echo esc_url( $banner_image ); ?>" alt="<?php echo esc_attr( $banner_text ); ?> - Category Banner" fetchpriority="high">
                <?php endif; ?>
                <h1 class="banner-text"><?php echo esc_html( $banner_text ); ?></h1>
            </div>
        </div>

        <div class="bst-breadcrumb-section">
            <div class="bst-breadcrumb-container has-share-buttons">
                <?php
                if ( function_exists( 'bst_render_blog_archive_breadcrumbs' ) ) {
                    bst_render_blog_archive_breadcrumbs( $cat_name );
                }
                ?>
                <?php
                bst_render_share_buttons(
                    array(
                        'context'     => 'blog-list',
                        'url'         => $share_url,
                        'email_label' => $share_label,
                        'object_id'   => $category instanceof WP_Term ? (int) $category->term_id : 0,
                    )
                );
                ?>
            </div>
        </div>

        <div class="translucent-overlay">
            <?php if ( have_posts() ) : ?>
                <div class="h-section-grid-container h-section-boxed-container">
                    <div data-colibri-id="4640-c7" class="h-row-container gutters-row-lg-1 gutters-row-md-1 gutters-row-0 gutters-row-v-lg-1 gutters-row-v-md-1 gutters-row-v-1 style-1802 style-local-4640-c7 position-relative">
                        <div class="h-row justify-content-lg-center justify-content-md-center justify-content-center align-items-lg-stretch align-items-md-stretch align-items-stretch gutters-col-lg-1 gutters-col-md-1 gutters-col-0 gutters-col-v-lg-1 gutters-col-v-md-1 gutters-col-v-1">
                            <?php
                            while ( have_posts() ) :
                                the_post();
                                get_template_part( 'partials/bst-blog-post-card' );
                            endwhile;
                            ?>
                        </div>
                    </div>
                </div>

                <nav class="bst-blog-pagination page-content-wrapper" aria-label="<?php esc_attr_e( 'Category posts', 'althea-wp-child' ); ?>">
                    <?php
                    the_posts_pagination(
                        array(
                            'mid_size'  => 2,
                            'prev_text' => '&larr; ' . __( 'Previous', 'althea-wp-child' ),
                            'next_text' => __( 'Next', 'althea-wp-child' ) . ' &rarr;',
                        )
                    );
                    ?>
                </nav>
            <?php else : ?>
                <div class="page-content-wrapper">
                    <div class="bst-single-post bst-blog-empty">
                        <p><?php esc_html_e( 'No posts found in this category.', 'althea-wp-child' ); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
get_footer();
