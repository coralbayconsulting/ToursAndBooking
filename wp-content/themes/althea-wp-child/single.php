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
        <div data-colibri-id="2559-c1" class="style-1078 style-local-2559-c1 position-relative">
            <div class="translucent-overlay">
                <?php while ( have_posts() ) : the_post(); ?>
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

                    <div class="page-content-wrapper bst-single-post-wrap">
                        <article id="post-<?php the_ID(); ?>" <?php post_class( 'bst-single-post' ); ?>>
                            <header class="bst-single-post-header">
                                <h1 class="bst-single-post-title entry-title"><?php the_title(); ?></h1>
                                <?php if ( get_the_date() ) : ?>
                                    <p class="bst-single-post-date">
                                        <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
                                            <?php echo esc_html( get_the_date() ); ?>
                                        </time>
                                    </p>
                                <?php endif; ?>
                            </header>

                            <div class="bst-single-post-content entry-content">
                                <?php the_content(); ?>
                            </div>
                        </article>

                        <?php
                        if ( comments_open() || get_comments_number() ) {
                            comments_template();
                        }
                        ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
