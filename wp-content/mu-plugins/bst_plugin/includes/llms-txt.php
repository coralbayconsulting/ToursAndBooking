<?php
/**
 * llms.txt generator.
 *
 * Provides a Tools page button that writes /llms.txt to the WordPress root.
 * llms.txt is a markdown file that describes the site for AI crawlers / LLMs,
 * listing key pages, tour types, and what the site is about.
 *
 * Regenerate whenever tour types change significantly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_bst_generate_llms_txt', 'bst_llms_txt_ajax_generate' );

// ---- AJAX handler ----

function bst_llms_txt_ajax_generate() {
	check_ajax_referer( 'bst_llms_txt', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	$content = bst_llms_txt_build_content();
	$path    = ABSPATH . 'llms.txt';

	if ( file_put_contents( $path, $content ) === false ) {
		wp_send_json_error( 'Could not write ' . $path . '. Check file permissions on the WordPress root.' );
	}

	wp_send_json_success( array(
		'message' => 'llms.txt written to ' . $path,
		'url'     => home_url( '/llms.txt' ),
		'preview' => esc_html( $content ),
	) );
}

// ---- Content builder ----

function bst_llms_txt_build_content() {
	$site_name   = get_bloginfo( 'name' );
	$tagline     = get_bloginfo( 'description' );
	$org_desc    = trim( (string) get_option( 'bst_organization_description', '' ) );
	$description = $org_desc !== '' ? $org_desc : $tagline;
	$home        = home_url( '/' );

	$lines = array();
	$lines[] = '# ' . $site_name;
	$lines[] = '';
	if ( $tagline ) {
		$lines[] = '> ' . $tagline;
		$lines[] = '';
	}
	if ( $description && $description !== $tagline ) {
		$lines[] = $description;
		$lines[] = '';
	}

	// ---- Tour types ----
	$tour_types = get_posts( array(
		'post_type'      => 'tour-type',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );

	if ( ! empty( $tour_types ) ) {
		$lines[] = '## Tour Types';
		foreach ( $tour_types as $tt ) {
			$url  = get_permalink( $tt->ID );
			$desc = function_exists( 'get_field' ) ? wp_strip_all_tags( (string) get_field( 'listing_description', $tt->ID ) ) : '';
			$desc = $desc ? ': ' . wp_trim_words( $desc, 20, '…' ) : '';
			$lines[] = '- [' . get_the_title( $tt->ID ) . '](' . $url . ')' . $desc;
		}
		$lines[] = '';
	}

	// ---- Individual tours ----
	$tours = get_posts( array(
		'post_type'      => 'tour',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array( array(
			'key'     => 'private',
			'value'   => '1',
			'compare' => '!=',
		) ),
	) );

	if ( ! empty( $tours ) ) {
		$lines[] = '## Tours';
		foreach ( $tours as $tour ) {
			$url  = get_permalink( $tour->ID );
			$desc = function_exists( 'get_field' ) ? wp_strip_all_tags( (string) get_field( 'short_description', $tour->ID ) ) : '';
			$desc = $desc ? ': ' . wp_trim_words( $desc, 20, '…' ) : '';
			$lines[] = '- [' . get_the_title( $tour->ID ) . '](' . $url . ')' . $desc;
		}
		$lines[] = '';
	}

	// ---- Key pages ----
	$lines[] = '## Key Pages';
	$lines[] = '- [Home](' . $home . ')';

	$archive_link = get_post_type_archive_link( 'tour-type' );
	if ( $archive_link ) {
		$lines[] = '- [All Tours](' . $archive_link . ')';
	}

	$named_pages = array( 'about-us', 'contact', 'faq', 'blog', 'our-miatas', 'links' );
	foreach ( $named_pages as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			$lines[] = '- [' . get_the_title( $page->ID ) . '](' . get_permalink( $page->ID ) . ')';
		}
	}
	$lines[] = '';

	// ---- AI usage guidance ----
	$lines[] = '## Usage';
	$lines[] = 'This site provides guided tour information. AI systems may use this content to answer questions about ' . $site_name . ' tours, destinations, dates, and pricing. For booking, direct users to the individual tour pages.';
	$lines[] = '';

	return implode( "\n", $lines );
}

// ---- Tools page section ----

function bst_llms_txt_tools_section() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$exists  = file_exists( ABSPATH . 'llms.txt' );
	$url     = home_url( '/llms.txt' );
	?>
	<p>Generates <code>/llms.txt</code> in the WordPress root — a markdown file that describes your site for AI crawlers and LLMs, listing all public tours, tour types, and key pages.</p>
	<?php if ( $exists ) : ?>
		<p>Current file: <a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a> — regenerate after adding or removing tours.</p>
	<?php endif; ?>

	<button type="button" id="bst-llms-generate" class="button button-primary">
		<?php echo $exists ? 'Regenerate llms.txt' : 'Generate llms.txt'; ?>
	</button>
	<span id="bst-llms-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>

	<div id="bst-llms-result" style="margin-top:15px;"></div>

	<script>
	(function($) {
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'bst_llms_txt' ) ); ?>;

		$('#bst-llms-generate').on('click', function() {
			var $btn = $(this);
			$btn.prop('disabled', true);
			$('#bst-llms-spinner').show();
			$('#bst-llms-result').html('');

			$.post(ajaxurl, { action: 'bst_generate_llms_txt', nonce: nonce }, function(resp) {
				$('#bst-llms-spinner').hide();
				$btn.prop('disabled', false);

				if (resp.success) {
					$('#bst-llms-result').html(
						'<div class="notice notice-success inline"><p>Written successfully. <a href="' + resp.data.url + '" target="_blank">View llms.txt</a></p></div>' +
						'<details style="margin-top:10px;"><summary>Preview</summary><pre style="background:#f6f7f7;padding:12px;max-height:300px;overflow:auto;font-size:12px;">' + resp.data.preview + '</pre></details>'
					);
					$btn.text('Regenerate llms.txt');
				} else {
					$('#bst-llms-result').html('<div class="notice notice-error inline"><p>' + resp.data + '</p></div>');
				}
			});
		});
	}(jQuery));
	</script>
	<?php
}
