<?php
/**
 * ACF / Secure Custom Fields — Local JSON (Git-friendly field groups)
 *
 * Field group JSON is saved under wp-content/mu-plugins/bst_plugin/acf-json/
 * Commit those .json files to Git; deploys ship definitions without manual export/import.
 *
 * After deploy: WP Admin → Field Groups — use "Sync available" when JSON is newer than DB.
 *
 * @package BST_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register save/load paths for ACF and SCF (SCF uses the same acf/* hooks).
 */
function bst_register_acf_json_paths() {
	if ( ! defined( 'BST_PLUGIN_DIR' ) ) {
		return;
	}

	$json_dir = BST_PLUGIN_DIR . 'acf-json';

	// Ensure directory exists when saving from admin (best-effort).
	if ( is_admin() && ! is_dir( $json_dir ) ) {
		wp_mkdir_p( $json_dir );
	}

	add_filter(
		'acf/settings/save_json',
		function () use ( $json_dir ) {
			return $json_dir;
		}
	);

	add_filter(
		'acf/settings/load_json',
		function ( $paths ) use ( $json_dir ) {
			// Load from plugin repo (and keep any other paths themes/plugins register).
			$paths[] = $json_dir;
			return $paths;
		}
	);
}

// After other plugins (ACF/SCF) load; filters are read when ACF needs them.
add_action( 'plugins_loaded', 'bst_register_acf_json_paths', 20 );
