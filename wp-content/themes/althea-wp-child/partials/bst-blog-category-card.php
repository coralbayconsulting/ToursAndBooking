<?php
/**
 * Blog category tile for the main blog index.
 *
 * @package Althea_WP_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$category = get_query_var( 'bst_blog_category' );
if ( ! $category instanceof WP_Term ) {
    return;
}

$permalink = get_category_link( $category );
if ( is_wp_error( $permalink ) ) {
    return;
}

$title       = $category->name;
$description = term_description( $category );
$image       = function_exists( 'bst_get_category_banner_image_url' )
    ? bst_get_category_banner_image_url( $category )
    : '';
?>
<div class="tour-box bst-blog-box bst-blog-category-box" id="category-<?php echo esc_attr( (string) $category->term_id ); ?>">
    <h2><?php echo esc_html( $title ); ?></h2>
    <?php if ( $image ) : ?>
        <div class="listing-image-container">
            <a href="<?php echo esc_url( $permalink ); ?>">
                <img
                    src="<?php echo esc_url( $image ); ?>"
                    alt="<?php echo esc_attr( $title ); ?>"
                    class="attachment-tour-listing size-tour-listing"
                    loading="lazy"
                    sizes="(max-width: 600px) calc(100vw - 40px), 300px"
                />
            </a>
        </div>
    <?php endif; ?>
    <p><?php echo wp_kses_post( $description ); ?></p>
    <?php
    $post_count = (int) $category->count;
    ?>
    <p class="tour-pricing-info" style="margin: -8px 0 10px; font-size: 14px; font-weight: 600; color: #2c5aa0; text-align: center;">
        <?php
        echo esc_html(
            sprintf(
                _n(
                    'There is %d post in this category',
                    'There are %d posts in this category',
                    $post_count,
                    'althea-wp-child'
                ),
                $post_count
            )
        );
        ?>
    </p>
    <a href="<?php echo esc_url( $permalink ); ?>" class="info-button"><?php esc_html_e( 'View Posts', 'althea-wp-child' ); ?></a>
</div>
