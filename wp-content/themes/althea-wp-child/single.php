<?php
/**
 * Single blog post template.
 *
 * @package Althea_WP_Child
 */

get_header();
?>

<div class="page-content">
    <div id="content" class="content">
        <?php while ( have_posts() ) : the_post(); ?>
            <?php
            $bst_banner   = function_exists( 'bst_get_single_post_banner_data' )
                ? bst_get_single_post_banner_data()
                : array(
                    'heading' => get_the_title(),
                    'image'   => function_exists( 'bst_get_blog_banner_image_url' ) ? bst_get_blog_banner_image_url() : '',
                );
            $banner_text  = $bst_banner['heading'];
            $banner_image = $bst_banner['image'];
            ?>
            <div class="translucent-overlay">
                <div class="top-banner-container">
                    <?php if ( $banner_image ) : ?>
                        <img class="top-banner" src="<?php echo esc_url( $banner_image ); ?>" alt="<?php echo esc_attr( $banner_text ); ?> - Article Banner" fetchpriority="high">
                    <?php endif; ?>
                    <h1 class="banner-text"><?php echo esc_html( $banner_text ); ?></h1>
                </div>
            </div>

            <div class="bst-breadcrumb-section">
                <div class="bst-breadcrumb-container has-share-buttons">
                    <?php
                    if ( function_exists( 'bst_render_blog_post_breadcrumbs' ) ) {
                        bst_render_blog_post_breadcrumbs();
                    }
                    ?>
                    <?php bst_render_share_buttons( array( 'context' => 'article' ) ); ?>
                </div>
            </div>

            <div class="translucent-overlay">
                <div class="page-content-wrapper bst-single-post-wrap">
                    <article id="post-<?php the_ID(); ?>" <?php post_class( 'bst-single-post' ); ?>>
                        <?php if ( get_the_date() ) : ?>
                            <p class="bst-single-post-date">
                                <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: formatted publish date */
                                            __( 'Published %s', 'althea-wp-child' ),
                                            get_the_date( 'F j, Y' )
                                        )
                                    );
                                    ?>
                                </time>
                            </p>
                            <hr class="bst-single-post-meta-divider">
                        <?php endif; ?>

                        <div class="bst-single-post-content entry-content">
                            <?php the_content(); ?>
                        </div>
                    </article>

                    <?php
                    if ( comments_open() || get_comments_number() ) {
                        echo '<div class="bst-blog-comments">';
                        comments_template();
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<?php
get_footer();
