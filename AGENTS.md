# Agent Instructions — Recipe Sellable PDF

This file briefs AI coding agents working in this repo. Read it before editing.

## What this plugin is

A small WordPress plugin that generates styled, sellable-quality PDFs from
WP Recipe Maker (WPRM) recipes - server-side, no HTTP scraping. It was built
because the original Python scraper kept tripping rate limits on the site
owner's residential IP; running inside WordPress sidesteps that entirely.

Owner uses it to produce Etsy-ready PDFs of her own recipes on three
WPRM-powered sites.

## How it works

- Recipe data is pulled via `WPRM_Recipe_Manager::get_recipe( $id )` and the
  `WPRM_Recipe` object's public methods (`name()`, `summary()`, `ingredients()`,
  `instructions()`, `prep_time()`, `tags('course', true)`, etc.).
- HTML is rendered from `templates/recipe.php` with the recipe data exposed as
  `$rspdf` (an array, not the WPRM object). All other template locals are
  prefixed `$rspdf_` to satisfy Plugin Check.
- PDF is rendered via **Dompdf 3.1.5**, vendored in `vendor/`.
- Recipe image is loaded from local uploads (no HTTP), resized via
  `wp_get_image_editor()`, and embedded as a `data:` URI.

## Sanitization contract

`rspdf_build_view()` in `includes/renderer.php` is responsible for ALL
sanitization. The template just renders. Two categories:

- **Plain-text fields** (title, author, course, cuisine, group names,
  ingredient text, time/serving labels) are pre-stripped with
  `wp_strip_all_tags()`. The template uses `esc_html()` to render.
- **Rich-text fields** (`summary`, `notes`, instruction step text) are
  pre-sanitized with `wp_kses_post()`. The template echoes them directly,
  with a `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`
  comment explaining why escaping would break the HTML.

If you add a new field, decide which category it belongs to and route it
through the same machinery. Never let raw WPRM strings reach the template.

## Author resolution

`WPRM_Recipe::author_display()` returns a SETTING value (`'same'`, `'custom'`,
`'post_author'`, `'default'`) - NOT a name. Always call `$recipe->author()`,
which resolves the setting to a real string. The renderer falls back to
`$recipe->post_author_name()` if WPRM gives an empty string.

Per-site author customization lives in WPRM, not in this plugin:
WP Recipe Maker → Settings → Recipe → "Same name for all recipes".

## File map

- `recipe-sellable-pdf.php` — plugin header, brand constants, bootstrap.
- `includes/admin.php` — `WP Recipe Maker → Sellable PDF` submenu + the
  `admin-post.php?action=rspdf_download` download handler (nonce + capability
  checks).
- `includes/cli.php` — `wp recipe-pdf generate <id> [--output=path]`.
- `includes/renderer.php` — `rspdf_resolve_recipe()`, `rspdf_build_view()`,
  `rspdf_generate()`. Pure functions; no hooks.
- `templates/recipe.php` — Dompdf HTML/CSS template.

## Constraints to respect

1. **WPRM hides the standard `wprm_recipe` post type UI.** The `post_row_actions`
   filter and `add_meta_boxes` hook do NOT fire for these posts. WPRM's own
   "Manage" page is React-based with no PHP filter for adding row actions.
   That's why this plugin has its own custom admin submenu instead. Don't try
   to reintroduce row actions or meta boxes - they won't render.

2. **Plugin Check (PCP) rules.** This plugin must keep passing Plugin Check
   on every change:
   - All `$_GET`/`$_POST` reads need either a nonce check or a `phpcs:ignore
     WordPress.Security.NonceVerification.Recommended` with a comment
     explaining why the order is forced.
   - Use `wp_delete_file()` not `unlink()`.
   - Prefix every plugin-scope function and variable with `rspdf_` or `RSPDF_`.
   - `readme.txt` "Tested up to" must match the current released WordPress
     version (currently 7.0).
   - No hidden files in the distributed zip (`.gitignore` is excluded from the
     release zip - see "Building a release" below).

3. **Capability gate.** Use `rspdf_capability()` in `includes/admin.php`, which
   prefers `WPRM_Settings::get('features_manage_access')` and falls back to
   `edit_posts`. Don't hardcode `manage_options`.

4. **Binary PDF output.** The download handler echoes raw PDF bytes after
   setting `Content-Type: application/pdf`. PCP flags this as
   `WordPress.Security.EscapeOutput.OutputNotEscaped` - the existing
   `phpcs:ignore` line is correct; escaping would corrupt the binary.

## Local dev with WordPress Studio

There is a Studio site set up at `localhost:8883` (site name `RobinPlugin`,
path `/Users/ericjohnson/Studio/robinplugin/`). It runs Playground
(WebAssembly), which **does NOT honor symlinks** - Studio dereferences them
at mount time and copies contents into the virtual filesystem.

To push changes from this repo into the Studio site:

```fish
rsync -av --delete \
  --exclude='.git' --exclude='.gitignore' \
  /Users/ericjohnson/GitHub/recipe-sellable-pdf/ \
  /Users/ericjohnson/Studio/robinplugin/wp-content/plugins/recipe-sellable-pdf/
```

Do **not** add `--delete-excluded` - it'll wipe the destination's `vendor/`.

## Running tests / verification

- **PHP syntax:** `for f in *.php includes/*.php templates/*.php; php -l $f; end`
- **End-to-end via WP-CLI** (in the Studio site):
  `wp recipe-pdf generate <recipe_id> --output=/tmp/test.pdf`
- **Admin smoke test:** load `http://localhost:8883/wp-admin/admin.php?page=rspdf`,
  confirm the recipe list renders and "Download PDF" buttons work.
- **Plugin Check:** the `plugin-check` plugin is already installed on the
  Studio site. Run via wp-admin.

## Building a release zip

```fish
cd /Users/ericjohnson/GitHub
zip -r recipe-sellable-pdf.zip recipe-sellable-pdf \
  -x 'recipe-sellable-pdf/.git/*' 'recipe-sellable-pdf/.gitignore'
```

`vendor/` is committed to this repo on purpose so the zip is install-ready.

## What's NOT here yet

- Plugin Update Checker (PUC v5). Adding it would let installed sites
  auto-update from GitHub releases. Pattern to follow: see
  `github.com/wormeyman/Eric-Johnson-Guru-WP-Plugin` for the vendored PUC and
  `.github/workflows/release.yml` setup.
- Settings UI for brand overrides. For now, override via constants in
  `wp-config.php`: `RSPDF_BRAND_NAME`, `RSPDF_BRAND_URL`, `RSPDF_BRAND_ACCENT`.
- Featured-image rendering has not been exercised against a real recipe yet -
  the seed recipe in the Studio site has no image.

## Style

- Procedural PHP, not class-heavy. Match the existing files.
- WordPress coding style (tabs, Yoda-free, PSR-12-ish spacing).
- Use hyphens, not em dashes, in user-visible strings and docs.
