<?php
/**
 * Yoast → BST SEO migration tool.
 *
 * Adds a "SEO Migration" tab to the BST Tools admin page.
 * Reads Yoast post meta / term meta and copies it into the BST SEO override
 * fields (bst_seo_title, bst_seo_description) for tour, tour-type, and tour-type-code terms.
 *
 * Run once after Yoast data has been verified, then Yoast can be uninstalled.
 * The Yoast meta keys remain in the database but are no longer read by anything.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_bst_seo_migration_preview', 'bst_seo_migration_ajax_preview' );
add_action( 'wp_ajax_bst_seo_migration_run',     'bst_seo_migration_ajax_run' );

// ---- Tools page section (called from templates/tools-page.php) ----

function bst_seo_migration_tools_section() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<p>Reads your existing Yoast SEO data and copies it into the BST SEO override fields on each tour, tour-type, and tour-type-code term. <strong>Safe to run multiple times</strong> — only writes when the Yoast value is non-empty <em>and</em> the BST field is currently empty.</p>

	<p><strong>Step 1 — Preview</strong></p>
	<button type="button" id="bst-migration-preview" class="button button-secondary">Preview Migration</button>
	<span id="bst-migration-preview-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
	<div id="bst-migration-preview-results" style="margin-top:15px;"></div>

	<p style="margin-top:20px;"><strong>Step 2 — Run Migration</strong></p>
	<button type="button" id="bst-migration-run" class="button button-primary" disabled>Run Migration</button>
	<span id="bst-migration-run-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
	<div id="bst-migration-run-results" style="margin-top:15px;"></div>

	<script>
	(function($) {
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'bst_seo_migration' ) ); ?>;

		$('#bst-migration-preview').on('click', function() {
			var $btn = $(this);
			$btn.prop('disabled', true);
			$('#bst-migration-preview-spinner').show();
			$('#bst-migration-preview-results').html('');
			$.post(ajaxurl, { action: 'bst_seo_migration_preview', nonce: nonce }, function(resp) {
				$('#bst-migration-preview-spinner').hide();
				$btn.prop('disabled', false);
				if (resp.success) {
					$('#bst-migration-preview-results').html(resp.data.html);
					if (resp.data.count > 0) {
						$('#bst-migration-run').prop('disabled', false);
					}
				} else {
					$('#bst-migration-preview-results').html('<div class="notice notice-error"><p>' + resp.data + '</p></div>');
				}
			});
		});

		$('#bst-migration-run').on('click', function() {
			if (!confirm('Write BST SEO fields from Yoast data now?')) return;
			var $btn = $(this);
			$btn.prop('disabled', true);
			$('#bst-migration-run-spinner').show();
			$('#bst-migration-run-results').html('');
			$.post(ajaxurl, { action: 'bst_seo_migration_run', nonce: nonce }, function(resp) {
				$('#bst-migration-run-spinner').hide();
				if (resp.success) {
					$('#bst-migration-run-results').html(resp.data.html);
				} else {
					$('#bst-migration-run-results').html('<div class="notice notice-error"><p>' + resp.data + '</p></div>');
					$btn.prop('disabled', false);
				}
			});
		});
	}(jQuery));
	</script>
	<?php
}

// ---- AJAX handlers ----

function bst_seo_migration_ajax_preview() {
	check_ajax_referer( 'bst_seo_migration', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	$items = bst_seo_migration_collect();
	$html  = bst_seo_migration_render_table( $items, false );

	wp_send_json_success( array( 'html' => $html, 'count' => count( $items ) ) );
}

function bst_seo_migration_ajax_run() {
	check_ajax_referer( 'bst_seo_migration', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	$items   = bst_seo_migration_collect();
	$written = 0;
	$skipped = 0;

	foreach ( $items as $item ) {
		if ( $item['context'] === 'term' ) {
			$term = $item['object'];
			foreach ( $item['fields'] as $acf_key => $value ) {
				if ( $value !== '' && function_exists( 'update_field' ) ) {
					update_field( $acf_key, $value, $term );
					$written++;
				}
			}
		} elseif ( $item['context'] === 'option' ) {
			foreach ( $item['fields'] as $option_key => $value ) {
				if ( $value !== '' ) {
					update_option( $option_key, $value );
					$written++;
				}
			}
		} else {
			$post_id = $item['object'];
			foreach ( $item['fields'] as $acf_key => $value ) {
				if ( $value !== '' && function_exists( 'update_field' ) ) {
					update_field( $acf_key, $value, $post_id );
					$written++;
				}
			}
		}
	}

	$html  = '<div class="notice notice-success"><p>';
	$html .= sprintf( 'Migration complete. <strong>%d</strong> field(s) written across <strong>%d</strong> post(s)/term(s).', $written, count( $items ) );
	$html .= '</p></div>';
	$html .= bst_seo_migration_render_table( $items, true );

	wp_send_json_success( array( 'html' => $html ) );
}

// ---- Data collection ----

/**
 * Gather all Yoast data that has a non-empty value and whose BST target field is currently empty.
 *
 * @return array[]
 */
function bst_seo_migration_collect() {
	$items = array();

	// ---- tour posts ----
	$tours = get_posts( array(
		'post_type'      => 'tour',
		'post_status'    => 'any',
		'posts_per_page' => -1,
	) );
	foreach ( $tours as $post ) {
		$row = bst_seo_migration_post_row( $post->ID );
		if ( $row ) {
			$items[] = $row;
		}
	}

	// ---- tour-type posts ----
	$tour_types = get_posts( array(
		'post_type'      => 'tour-type',
		'post_status'    => 'any',
		'posts_per_page' => -1,
	) );
	foreach ( $tour_types as $post ) {
		$row = bst_seo_migration_post_row( $post->ID );
		if ( $row ) {
			$items[] = $row;
		}
	}

	// ---- tour-type-code taxonomy terms ----
	$terms = get_terms( array(
		'taxonomy'   => 'tour-type-code',
		'hide_empty' => false,
	) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$row = bst_seo_migration_term_row( $term );
			if ( $row ) {
				$items[] = $row;
			}
		}
	}

	// ---- organization social URLs (site-wide Yoast option) ----
	$social_row = bst_seo_migration_social_row();
	if ( $social_row ) {
		$items[] = $social_row;
	}

	// ---- tour-type post type archive SEO (site-wide Yoast option) ----
	$archive_row = bst_seo_migration_archive_row();
	if ( $archive_row ) {
		$items[] = $archive_row;
	}

	return $items;
}

function bst_seo_migration_post_row( $post_id ) {
	$map = array(
		'bst_seo_title'       => '_yoast_wpseo_title',
		'bst_seo_description' => '_yoast_wpseo_metadesc',
	);

	$fields = array();
	foreach ( $map as $bst_key => $yoast_key ) {
		$yoast_val = trim( (string) get_post_meta( $post_id, $yoast_key, true ) );
		if ( $yoast_val === '' ) {
			continue;
		}
		// Only migrate if the BST field is currently empty.
		$bst_val = function_exists( 'get_field' ) ? trim( (string) get_field( $bst_key, $post_id ) ) : '';
		if ( $bst_val !== '' ) {
			continue;
		}
		$fields[ $bst_key ] = $yoast_val;
	}

	if ( empty( $fields ) ) {
		return null;
	}

	return array(
		'context' => 'post',
		'object'  => $post_id,
		'label'   => get_the_title( $post_id ) . ' (ID ' . $post_id . ')',
		'type'    => get_post_type( $post_id ),
		'fields'  => $fields,
	);
}

function bst_seo_migration_term_row( $term ) {
	// Yoast stores term SEO in term meta with these keys:
	$map = array(
		'bst_seo_title'       => 'wpseo_title',
		'bst_seo_description' => 'wpseo_desc',
	);

	$fields = array();
	foreach ( $map as $bst_key => $yoast_key ) {
		$yoast_val = trim( (string) get_term_meta( $term->term_id, $yoast_key, true ) );
		if ( $yoast_val === '' ) {
			continue;
		}
		$bst_val = function_exists( 'get_field' ) ? trim( (string) get_field( $bst_key, $term ) ) : '';
		if ( $bst_val !== '' ) {
			continue;
		}
		$fields[ $bst_key ] = $yoast_val;
	}

	if ( empty( $fields ) ) {
		return null;
	}

	return array(
		'context' => 'term',
		'object'  => $term,
		'label'   => $term->name . ' (term ID ' . $term->term_id . ')',
		'type'    => $term->taxonomy,
		'fields'  => $fields,
	);
}

function bst_seo_migration_social_row() {
	// Only migrate if bst_organization_social_urls is currently empty.
	$existing = trim( (string) get_option( 'bst_organization_social_urls', '' ) );
	if ( $existing !== '' ) {
		return null;
	}

	$yoast_social = get_option( 'wpseo_social', array() );
	if ( empty( $yoast_social ) || ! is_array( $yoast_social ) ) {
		return null;
	}

	$url_keys = array( 'facebook_site', 'instagram_url', 'linkedin_url', 'youtube_url', 'pinterest_url', 'myspace_url', 'wikipedia_url' );
	$urls     = array();

	foreach ( $url_keys as $key ) {
		if ( ! empty( $yoast_social[ $key ] ) ) {
			$url = trim( $yoast_social[ $key ] );
			if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$urls[] = $url;
			}
		}
	}

	// Twitter/X is stored as a handle (without URL prefix).
	if ( ! empty( $yoast_social['twitter_site'] ) ) {
		$handle = ltrim( trim( $yoast_social['twitter_site'] ), '@' );
		if ( $handle !== '' ) {
			$urls[] = 'https://x.com/' . $handle;
		}
	}

	if ( empty( $urls ) ) {
		return null;
	}

	return array(
		'context' => 'option',
		'object'  => 'bst_organization_social_urls',
		'label'   => 'Organization Social URLs (site-wide)',
		'type'    => 'option',
		'fields'  => array(
			'bst_organization_social_urls' => implode( "\n", $urls ),
		),
	);
}

function bst_seo_migration_archive_row() {
	$yoast_titles = get_option( 'wpseo_titles', array() );
	if ( empty( $yoast_titles ) || ! is_array( $yoast_titles ) ) {
		return null;
	}

	// Map: BST option key => Yoast wpseo_titles sub-key
	$map = array(
		'bst_ptarchive_tour_type_meta_description' => 'metadesc-ptarchive-tour-type',
		'bst_ptarchive_tour_type_page_title'        => 'title-ptarchive-tour-type',
	);

	$fields = array();
	foreach ( $map as $bst_key => $yoast_key ) {
		$yoast_val = isset( $yoast_titles[ $yoast_key ] ) ? trim( (string) $yoast_titles[ $yoast_key ] ) : '';
		if ( $yoast_val === '' ) {
			continue;
		}
		// Yoast title templates contain %% variables — skip if not a plain string.
		if ( strpos( $yoast_val, '%%' ) !== false ) {
			continue;
		}
		// Only migrate if the BST option is currently empty.
		if ( trim( (string) get_option( $bst_key, '' ) ) !== '' ) {
			continue;
		}
		$fields[ $bst_key ] = $yoast_val;
	}

	if ( empty( $fields ) ) {
		return null;
	}

	return array(
		'context' => 'option',
		'object'  => 'archive',
		'label'   => 'Our Tours archive (/tour-types/)',
		'type'    => 'option',
		'fields'  => $fields,
	);
}

// ---- HTML rendering ----

function bst_seo_migration_render_table( $items, $is_done ) {
	if ( empty( $items ) ) {
		return '<div class="notice notice-info"><p>No Yoast data found to migrate — either it has already been migrated or no Yoast SEO fields have been filled in.</p></div>';
	}

	$label_map = array(
		'bst_seo_title'                             => 'SEO Title',
		'bst_seo_description'                       => 'Meta Description',
		'bst_organization_social_urls'              => 'Social Profile URLs',
		'bst_ptarchive_tour_type_page_title'        => 'Archive Page Title',
		'bst_ptarchive_tour_type_meta_description'  => 'Archive Meta Description',
	);

	$html  = '<table class="widefat striped" style="margin-top:15px;">';
	$html .= '<thead><tr>';
	$html .= '<th>Type</th><th>Post / Term</th><th>Field</th><th>Value to ' . ( $is_done ? 'Written' : 'Copy' ) . '</th>';
	$html .= '</tr></thead><tbody>';

	foreach ( $items as $item ) {
		$first = true;
		foreach ( $item['fields'] as $acf_key => $value ) {
			$label    = isset( $label_map[ $acf_key ] ) ? $label_map[ $acf_key ] : $acf_key;
			$display  = mb_strlen( $value ) > 100 ? mb_substr( $value, 0, 97 ) . '…' : $value;
			$html    .= '<tr>';
			if ( $first ) {
				$rowspan  = count( $item['fields'] );
				$html    .= '<td rowspan="' . $rowspan . '">' . esc_html( $item['type'] ) . '</td>';
				$html    .= '<td rowspan="' . $rowspan . '">' . esc_html( $item['label'] ) . '</td>';
				$first    = false;
			}
			$html .= '<td>' . esc_html( $label ) . '</td>';
			$html .= '<td><code style="word-break:break-all;">' . esc_html( $display ) . '</code></td>';
			$html .= '</tr>';
		}
	}

	$html .= '</tbody></table>';
	$html .= '<p style="margin-top:10px;color:#666;">' . count( $items ) . ' item(s) with Yoast data to migrate.</p>';

	return $html;
}
