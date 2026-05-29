<?php
/**
 * Share buttons partial (email, WhatsApp, copy link).
 *
 * Expects share content variables from bst_render_share_buttons().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bst_share_track_context   = $bst_share_track_context ?? 'unknown';
$bst_share_track_url       = $bst_share_track_url ?? '';
$bst_share_track_title     = $bst_share_track_title ?? '';
$bst_share_track_object_id = isset( $bst_share_track_object_id ) ? (int) $bst_share_track_object_id : 0;
?>
<div
    class="bst-share-buttons"
    data-share-context="<?php echo esc_attr( $bst_share_track_context ); ?>"
    data-share-url="<?php echo esc_url( $bst_share_track_url ); ?>"
    data-share-title="<?php echo esc_attr( $bst_share_track_title ); ?>"
    data-share-object-id="<?php echo esc_attr( (string) $bst_share_track_object_id ); ?>"
>
    <span class="bst-share-label"><?php esc_html_e( 'Share:', 'althea-wp-child' ); ?></span>
    <a href="<?php echo esc_url( 'mailto:?subject=' . $bst_share_email_subject . '&body=' . $bst_share_email_body ); ?>" class="bst-share-icon bst-share-action" data-share-method="email" title="<?php esc_attr_e( 'Email to a friend', 'althea-wp-child' ); ?>" aria-label="<?php echo esc_attr( $bst_share_email_aria ); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
        </svg>
    </a>
    <a href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $bst_share_whatsapp_text ) ); ?>" class="bst-share-icon bst-share-action" data-share-method="whatsapp" title="<?php esc_attr_e( 'Share on WhatsApp', 'althea-wp-child' ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $bst_share_whatsapp_aria ); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
        </svg>
    </a>
    <button type="button" class="bst-share-icon bst-share-copy bst-share-action" data-share-method="copy" title="<?php esc_attr_e( 'Copy link to clipboard', 'althea-wp-child' ); ?>" aria-label="<?php esc_attr_e( 'Copy link to clipboard', 'althea-wp-child' ); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
        </svg>
    </button>
</div>
