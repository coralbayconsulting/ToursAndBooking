<?php
/**
 * Bootstrap: load Airwallex GF Payment Add-On when Gravity Forms is available.
 *
 * @package BST_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
	return;
}

GFForms::include_payment_addon_framework();

require_once __DIR__ . '/class-airwallex-api.php';
require_once __DIR__ . '/class-gf-field-airwallex.php';
require_once __DIR__ . '/class-gf-airwallex-addon.php';

if ( class_exists( 'GFAddOn' ) ) {
	GFAddOn::register( 'GFAirwallexAddOn' );
}
