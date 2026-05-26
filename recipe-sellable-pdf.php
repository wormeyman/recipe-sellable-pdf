<?php
/*
Plugin Name: Recipe Sellable PDF
Plugin URI: https://github.com/wormeyman/recipe-sellable-pdf
Description: One-click sellable-quality PDFs of WP Recipe Maker recipes, generated server-side. Adds a "Sellable PDF" submenu under WP Recipe Maker and a `wp recipe-pdf generate` WP-CLI command.
Version: 0.2.0
Author: Eric Johnson
Author URI: https://ericjohnson.guru/
Requires PHP: 8.1
License: MIT
License URI: https://opensource.org/licenses/MIT
*/

defined( 'ABSPATH' ) or die();

define( 'RSPDF_VERSION', '0.2.0' );
define( 'RSPDF_PATH', plugin_dir_path( __FILE__ ) );
define( 'RSPDF_URL', plugin_dir_url( __FILE__ ) );

// Brand defaults. Override per-site by editing these constants before zipping,
// or define them in wp-config.php to keep the plugin file untouched.
if ( ! defined( 'RSPDF_BRAND_NAME' ) ) {
	define( 'RSPDF_BRAND_NAME', get_bloginfo( 'name' ) );
}
if ( ! defined( 'RSPDF_BRAND_URL' ) ) {
	define( 'RSPDF_BRAND_URL', wp_parse_url( home_url(), PHP_URL_HOST ) );
}
if ( ! defined( 'RSPDF_BRAND_ACCENT' ) ) {
	define( 'RSPDF_BRAND_ACCENT', '#5C3317' );
}

// Load Composer dependencies (Dompdf). The plugin won't function without them.
$rspdf_autoload = RSPDF_PATH . 'vendor/autoload.php';
if ( file_exists( $rspdf_autoload ) ) {
	require_once $rspdf_autoload;
} else {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>Recipe Sellable PDF:</strong> Composer dependencies are missing. Run <code>composer install</code> in the plugin directory before activating.</p></div>';
	} );
	return;
}

// Hard requirement: WP Recipe Maker must be active.
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WPRM_Recipe_Manager' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Recipe Sellable PDF:</strong> Requires WP Recipe Maker to be active.</p></div>';
		} );
		return;
	}

	require_once RSPDF_PATH . 'includes/renderer.php';
	require_once RSPDF_PATH . 'includes/admin.php';

	if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
		require_once RSPDF_PATH . 'includes/cli.php';
	}
} );
