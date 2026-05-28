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
  ingredient text, time/serving labels, nutrition strings) are pre-stripped
  with `wp_strip_all_tags()`. The template uses `esc_html()` to render.
- **Rich-text fields** (`summary`, `notes`, instruction step text) are
  pre-sanitized with `wp_kses_post()`. The template echoes them directly,
  with a `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`
  comment explaining why escaping would break the HTML.

If you add a new field, decide which category it belongs to and route it
through the same machinery. Never let raw WPRM strings reach the template.

## Author resolution

The renderer resolves the author in priority order (see `rspdf_build_view()`):

1. **This plugin's `rspdf_author_name` option** — set on the Sellable PDF admin
   page ("PDF author name" field). This is the reliable cross-site control.
2. **`$recipe->author()`** — WPRM's resolved author string. Note
   `author_display()` returns a SETTING value (`'same'`, `'custom'`,
   `'post_author'`, `'default'`), NOT a name; `author()` resolves it.
3. **`$recipe->post_author_name()`** — the WordPress post author, so the field
   never reads blank.

Why the plugin setting exists: WPRM's own "Same name for all recipes" option
(WP Recipe Maker → Settings → Recipe) is **not exposed on every site/tier** -
it's present on AWD but not on EAW/ATN. The plugin setting sidesteps that so the
author can be set uniformly on any site without depending on WPRM's UI.

## Nutrition

`rspdf_build_nutrition()` reads `$recipe->nutrition()` (nutrient => float) and
formats each value using WPRM's own field config via
`WPRM_Nutrition::get_fields()` (labels, units, ordering). Output is a flat array
of plain-text "Label: value+unit" strings; the template joins them with " | "
and renders with `esc_html()`. Empty/zero values are skipped. Guarded with
`method_exists`/`class_exists` so it no-ops if WPRM nutrition is unavailable.

## File map

- `recipe-sellable-pdf.php` — plugin header, brand constants, bootstrap.
- `includes/admin.php` — `WP Recipe Maker → Sellable PDF` submenu (recipe list +
  the `rspdf_author_name` settings-save form) and the
  `admin-post.php?action=rspdf_download` download handler. The handler does a
  nonce check, the page-level capability check (`rspdf_capability()`), AND an
  object-level `current_user_can( 'edit_post', $recipe->id() )` check.
- `includes/cli.php` — `wp recipe-pdf generate <id> [--output=path]`.
- `includes/renderer.php` — `rspdf_resolve_recipe()`, `rspdf_build_view()`,
  `rspdf_build_nutrition()`, `rspdf_image_data_uri()`, `rspdf_render_html()`,
  `rspdf_generate()`, and small helpers (`rspdf_format_time()`, `rspdf_slug()`).
  No hooks. Note `rspdf_build_view()` is not side-effect-free: it reads the
  `rspdf_author_name` option and `rspdf_image_data_uri()` touches the filesystem.
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

4. **Object-level authorization on download.** The download handler must keep its
   `current_user_can( 'edit_post', $recipe->id() )` check (added in v0.2.1). The
   page-level capability gate alone is not enough - it does not stop a user from
   passing an arbitrary `id`, so without this check a lower-privileged user could
   download recipes (including drafts/private) authored by someone else. Don't
   remove it as "redundant."

5. **Binary PDF output.** The download handler echoes raw PDF bytes after
   setting `Content-Type: application/pdf`. PCP flags this as
   `WordPress.Security.EscapeOutput.OutputNotEscaped` - the existing
   `phpcs:ignore` line is correct; escaping would corrupt the binary.

## Local dev with Local (Local by Flywheel)

The current dev site is **https://eawplugindev.local/**, run by the **Local**
app. Site path:
`/Users/ericjohnson/Local Sites/eawplugindev/app/public/`. It has WP Recipe
Maker (free + premium) and `plugin-check` installed.

Unlike the old Studio/Playground setup, Local runs a real local server and
**honors symlinks**, so this repo is symlinked straight into the site - no
rsync needed, edits are live:

```fish
ln -s /Users/ericjohnson/GitHub/recipe-sellable-pdf \
  "/Users/ericjohnson/Local Sites/eawplugindev/app/public/wp-content/plugins/recipe-sellable-pdf"
```

(The previous WordPress Studio site at `localhost:8883` is retired; it ran
Playground/WebAssembly, which dereferenced symlinks and required an rsync to
push changes.)

## Running tests / verification

- **PHP syntax:** `for f in *.php includes/*.php templates/*.php; php -l $f; end`
- **End-to-end via WP-CLI:** use Local's "Open site shell" (it wires up PHP +
  the site DB socket), then:
  `wp recipe-pdf generate <recipe_id> --output=/tmp/test.pdf`
- **Admin smoke test:** load
  `https://eawplugindev.local/wp-admin/admin.php?page=rspdf`, confirm the recipe
  list renders, the "PDF author name" setting saves, and "Download PDF" works.
- **Plugin Check:** the `plugin-check` plugin is installed on the Local site.
  Run via wp-admin (Tools → Plugin Check) or
  `wp plugin check recipe-sellable-pdf` from the site shell.

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
- Settings UI for brand overrides. The Sellable PDF admin page has one setting
  so far (`rspdf_author_name`); brand values still come from constants in
  `wp-config.php`: `RSPDF_BRAND_NAME`, `RSPDF_BRAND_URL`, `RSPDF_BRAND_ACCENT`.

## Style

- Procedural PHP, not class-heavy. Match the existing files.
- WordPress coding style (tabs, Yoda-free, PSR-12-ish spacing).
- Use hyphens, not em dashes, in user-visible strings and docs.
