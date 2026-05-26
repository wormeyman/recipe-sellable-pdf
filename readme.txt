=== Recipe Sellable PDF ===
Contributors: wormeyman
Tags: wprm, wp-recipe-maker, pdf, recipe
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Generate sellable-quality PDFs of your WP Recipe Maker recipes, server-side, with one click.

== Description ==

Adds a "Sellable PDF" submenu under WP Recipe Maker that lists your recipes with a one-click Download button per row, plus a `wp recipe-pdf generate` WP-CLI command. Runs entirely server-side - no HTTP scraping, no rate limits, no IP whitelisting needed.

Brand colors and footer text default to your site name and home URL. To override on a per-site basis, define these constants in `wp-config.php` before WordPress loads the plugin:

	define( 'RSPDF_BRAND_NAME',   'All Ways Delicious' );
	define( 'RSPDF_BRAND_URL',    'allwaysdelicious.com' );
	define( 'RSPDF_BRAND_ACCENT', '#5C3317' );

== Installation ==

This plugin bundles Dompdf via Composer. The release zip already includes `vendor/`; if you're installing from a git clone, run `composer install` in the plugin directory before activating.

1. Upload the plugin folder to `/wp-content/plugins/`, or install the release zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Make sure WP Recipe Maker is also active.

== Usage ==

* **In wp-admin:** WP Recipe Maker → Sellable PDF → click "Download PDF" on the recipe you want.
* **From the command line:** `wp recipe-pdf generate <recipe_or_post_id> [--output=path.pdf]`

== Requirements ==

* WP Recipe Maker (free or premium), active.
* PHP 8.1 or later.
* The `mbstring` and `gd` PHP extensions (standard on all modern WordPress hosts).

== Changelog ==

= 0.1.0 =
* Initial release: row action, meta box, WP-CLI command.
