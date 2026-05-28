=== Recipe Sellable PDF ===
Contributors: wormeyman
Tags: wprm, wp-recipe-maker, pdf, recipe
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.1
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

== Per-site author name ==

The author shown on each PDF comes from WP Recipe Maker itself, not from this plugin. To set a single author name for every recipe on a site (e.g. "Robin @ All Ways Delicious"):

1. WP Recipe Maker → Settings → Recipe.
2. Set **Default recipe author display** to "Same name for all recipes".
3. Set **Same name for all recipes** to the value you want, e.g. `Robin @ All Ways Delicious`.

This plugin reads that setting via `WPRM_Recipe::author()` and falls back to the WordPress post author if the WPRM setting is blank.

== Requirements ==

* WP Recipe Maker (free or premium), active.
* PHP 8.1 or later.
* The `mbstring` and `gd` PHP extensions (standard on all modern WordPress hosts).

== Changelog ==

= 0.2.1 =
* Security: enforce object-level authorization on PDF downloads. The download handler now requires edit access to the specific recipe (current_user_can( 'edit_post', ... )), so a user who passes the page capability gate can no longer download recipes (including drafts/private) authored by someone else.

= 0.2.0 =
* Fix author rendering: now resolves "same"/"custom"/"post_author" settings to actual names via WPRM_Recipe::author() (previously printed the literal setting value).
* Sanitize summary as rich text (wp_kses_post) so embedded paragraph tags render as paragraphs instead of literal `<p>...</p>`.
* Strip HTML from ingredient group headers and ingredient text so embedded `<strong>` tags no longer appear as literal text.
* Document the per-site author setting in readme.txt and AGENTS.md.

= 0.1.0 =
* Initial release: WP Recipe Maker → Sellable PDF submenu, WP-CLI command.
