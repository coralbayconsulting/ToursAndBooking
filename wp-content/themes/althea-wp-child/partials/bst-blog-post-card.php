<?php
/**
 * Blog post tile for archive loops (matches tour-box layout).
 *
 * @package Althea_WP_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$permalink = get_the_permalink();
$title     = get_the_title();
?>
<div <?php post_class( 'tour-box bst-blog-box' ); ?> id="post-<?php the_ID(); ?>">
    <h2><?php echo esc_html( $title ); ?></h2>
    <?php if ( has_post_thumbnail() ) : ?>
        <div class="listing-image-container">
            <a href="<?php echo esc_url( $permalink ); ?>">
                <?php
                the_post_thumbnail(
                    'tour-listing',
                    array(
                        'alt'     => esc_attr( $title ),
                        'loading' => 'lazy',
                        'sizes'   => '(max-width: 600px) calc(100vw - 40px), 300px',
                    )
                );
                ?>
            </a>
        </div>
    <?php endif; ?>
    <p><?php echo wp_kses_post( get_the_excerpt() ); ?></p>
    <a href="<?php echo esc_url( $permalink ); ?>" class="info-button"><?php esc_html_e( 'READ MORE', 'althea-wp-child' ); ?></a>
</div>
