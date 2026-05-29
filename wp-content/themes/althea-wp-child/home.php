<?php
/**
 * Blog posts index (Settings → Reading → Posts page).
 *
 * Lists category tiles linking to native category archives.
 *
 * @package Althea_WP_Child
 */

get_header();

$share_url      = function_exists( 'bst_get_blog_index_url' ) ? bst_get_blog_index_url() : home_url( '/' );
$share_label    = function_exists( 'bst_get_blog_index_label' ) ? bst_get_blog_index_label() : 'Blog';
$bst_banner     = function_exists( 'bst_get_blog_index_banner_data' )
    ? bst_get_blog_index_banner_data()
    : array(
        'heading' => $share_label,
        'image'   => '',
    );
$banner_text    = $bst_banner['heading'];
$banner_image   = $bst_banner['image'];
$blog_categories = function_exists( 'bst_get_blog_index_categories' )
    ? bst_get_blog_index_categories()
    : array();
?>

<div class="page-content">
    <div id="content" class="content">
        <div class="translucent-overlay">
            <div class="top-banner-container">
                <?php if ( $banner_image ) : ?>
                    <img class="top-banner" src="<?php echo esc_url( $banner_image ); ?>" alt="<?php echo esc_attr( $banner_text ); ?> - Blog Banner" fetchpriority="high">
                <?php endif; ?>
                <h1 class="banner-text"><?php echo esc_html( $banner_text ); ?></h1>
            </div>
        </div>

        <div class="bst-breadcrumb-section">
            <div class="bst-breadcrumb-container has-share-buttons">
                <?php
                if ( function_exists( 'bst_render_blog_archive_breadcrumbs' ) ) {
                    bst_render_blog_archive_breadcrumbs();
                }
                ?>
                <?php
                bst_render_share_buttons(
                    array(
                        'context'     => 'blog-list',
                        'url'         => $share_url,
                        'email_label' => $share_label,
                        'object_id'   => (int) get_option( 'page_for_posts' ),
                    )
                );
                ?>
            </div>
        </div>

        <div class="translucent-overlay">
            <?php if ( ! empty( $blog_categories ) ) : ?>
                <div class="h-section-grid-container h-section-boxed-container">
                    <div data-colibri-id="4640-c7" class="h-row-container gutters-row-lg-1 gutters-row-md-1 gutters-row-0 gutters-row-v-lg-1 gutters-row-v-md-1 gutters-row-v-1 style-1802 style-local-4640-c7 position-relative">
                        <div class="h-row justify-content-lg-center justify-content-md-center justify-content-center align-items-lg-stretch align-items-md-stretch align-items-stretch gutters-col-lg-1 gutters-col-md-1 gutters-col-0 gutters-col-v-lg-1 gutters-col-v-md-1 gutters-col-v-1">
                            <?php
                            foreach ( $blog_categories as $category ) {
                                set_query_var( 'bst_blog_category', $category );
                                get_template_part( 'partials/bst-blog-category-card' );
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <div class="page-content-wrapper">
                    <div class="bst-single-post bst-blog-empty">
                        <p><?php esc_html_e( 'No blog categories found.', 'althea-wp-child' ); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
get_footer();
