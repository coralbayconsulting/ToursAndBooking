<?php
/**
 * ACF field group: SEO Overrides.
 *
 * Registers bst_seo_title and bst_seo_description on:
 *   - tour post type
 *   - tour-type post type
 *
 * The tour-type-code taxonomy archive (/tours/{slug}/) reads SEO data from the
 * linked tour-type post — no separate taxonomy term fields needed.
 *
 * All fields are optional — seo-head.php falls back to content fields when empty.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', 'bst_register_seo_field_group' );

function bst_register_seo_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key'      => 'group_bst_seo_overrides',
		'title'    => 'SEO',
		'fields'   => array(
			array(
				'key'               => 'field_bst_seo_title',
				'label'             => 'SEO Title',
				'name'              => 'bst_seo_title',
				'type'              => 'text',
				'instructions'      => 'Overrides the browser tab title and search engine title. Leave empty to use the default (post title + site name). Aim for 50–60 characters.',
				'required'          => 0,
				'maxlength'         => 120,
				'placeholder'       => '',
				'prepend'           => '',
				'append'            => '',
			),
			array(
				'key'               => 'field_bst_seo_description',
				'label'             => 'Meta Description',
				'name'              => 'bst_seo_description',
				'type'              => 'textarea',
				'instructions'      => 'Overrides the meta description shown in search results and social previews. Leave empty to use the short description / listing description. Aim for 120–155 characters.',
				'required'          => 0,
				'maxlength'         => 320,
				'rows'              => 3,
				'new_lines'         => '',
				'placeholder'       => '',
			),

		),
		'location' => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'tour',
				),
			),
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'tour-type',
				),
			),
		),
		'menu_order'            => 100,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
		'description'           => 'Optional SEO overrides. All fields fall back to content fields when left empty.',
	) );
}
