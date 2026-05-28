<?php
/**
 * PDF renderer: WPRM recipe → Dompdf-rendered PDF binary.
 */

defined( 'ABSPATH' ) or die();

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Resolve a recipe object from either a wprm_recipe ID or a regular post ID.
 *
 * Returns a WPRM_Recipe on success, or WP_Error on failure.
 */
function rspdf_resolve_recipe( int $id ) {
	if ( $id <= 0 ) {
		return new WP_Error( 'rspdf_bad_id', 'Invalid ID.' );
	}

	$post = get_post( $id );
	if ( ! $post ) {
		return new WP_Error( 'rspdf_no_post', 'No post found for that ID.' );
	}

	$recipe_id = $id;
	if ( 'wprm_recipe' !== $post->post_type ) {
		$ids = WPRM_Recipe_Manager::get_recipe_ids_from_post( $id );
		if ( empty( $ids ) ) {
			return new WP_Error( 'rspdf_no_recipe', 'No WPRM recipe found in that post.' );
		}
		$recipe_id = (int) $ids[0];
	}

	$recipe = WPRM_Recipe_Manager::get_recipe( $recipe_id );
	if ( ! $recipe ) {
		return new WP_Error( 'rspdf_load_failed', 'Could not load recipe.' );
	}

	return $recipe;
}

/**
 * Read a local attachment image, resize to a max edge, and return a data: URI.
 * Falls back to false on any error.
 */
function rspdf_image_data_uri( int $attachment_id, int $max_edge = 800 ) {
	if ( $attachment_id <= 0 ) {
		return false;
	}
	$path = get_attached_file( $attachment_id );
	if ( ! $path || ! file_exists( $path ) ) {
		return false;
	}

	$editor = wp_get_image_editor( $path );
	if ( is_wp_error( $editor ) ) {
		// Fallback: just embed the original bytes.
		$bytes = @file_get_contents( $path );
		if ( false === $bytes ) {
			return false;
		}
		$mime = wp_get_image_mime( $path ) ?: 'image/jpeg';
		return 'data:' . $mime . ';base64,' . base64_encode( $bytes );
	}

	$size = $editor->get_size();
	$w    = isset( $size['width'] ) ? (int) $size['width'] : 0;
	$h    = isset( $size['height'] ) ? (int) $size['height'] : 0;
	if ( $w > $max_edge || $h > $max_edge ) {
		$editor->resize( $max_edge, $max_edge, false );
	}

	$tmp = wp_tempnam( 'rspdf-img' );
	$saved = $editor->save( $tmp, 'image/jpeg' );
	if ( is_wp_error( $saved ) ) {
		wp_delete_file( $tmp );
		return false;
	}
	$bytes = @file_get_contents( $saved['path'] );
	wp_delete_file( $saved['path'] );
	if ( false === $bytes ) {
		return false;
	}
	return 'data:image/jpeg;base64,' . base64_encode( $bytes );
}

/**
 * Format a WPRM time integer + unit into a display string.
 */
function rspdf_format_time( $value, string $unit ): string {
	$value = (int) $value;
	if ( $value <= 0 ) {
		return '';
	}
	return $value . ' ' . trim( $unit );
}

/**
 * Build a flat list of "Label: value+unit" nutrition strings from a recipe.
 *
 * Reuses WPRM's own field config (WPRM_Nutrition::get_fields()) so labels,
 * units, and ordering match WPRM's recipe card. Skips empty/zero values.
 * Returns plain-text strings; the template renders them with esc_html().
 */
function rspdf_build_nutrition( $recipe ): array {
	if ( ! method_exists( $recipe, 'nutrition' ) || ! class_exists( 'WPRM_Nutrition' ) ) {
		return [];
	}

	$values = (array) $recipe->nutrition();
	if ( ! $values ) {
		return [];
	}

	$out = [];
	foreach ( WPRM_Nutrition::get_fields() as $key => $field ) {
		if ( ! isset( $values[ $key ] ) || ! is_numeric( $values[ $key ] ) ) {
			continue;
		}
		$value = (float) $values[ $key ];
		if ( $value <= 0 ) {
			continue;
		}
		$label  = trim( wp_strip_all_tags( (string) ( $field['label'] ?? $key ) ) );
		$unit   = trim( wp_strip_all_tags( (string) ( $field['unit'] ?? '' ) ) );
		$number = rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
		$out[]  = $label . ': ' . $number . $unit;
	}

	return $out;
}

/**
 * Build the template data array from a WPRM_Recipe.
 */
function rspdf_build_view( $recipe ): array {
	$image_id  = (int) $recipe->image_id();
	$image_uri = $image_id ? rspdf_image_data_uri( $image_id ) : false;

	// Sanitization contract for the template:
	//   - "Plain-text" fields: pre-stripped via wp_strip_all_tags(); template
	//     uses esc_html() to render.
	//   - "Rich-text" fields (summary, notes, instruction step text):
	//     pre-sanitized via wp_kses_post(); template echoes the HTML directly.
	// This keeps the template logic simple and matches what Robin's real
	// recipe data actually contains (mixed plain + HTML from years of edits).

	$strip = static function ( $v ): string {
		return trim( wp_strip_all_tags( (string) $v ) );
	};

	$ingredient_groups = [];
	foreach ( (array) $recipe->ingredients() as $group ) {
		$ingredient_groups[] = [
			'name'  => $strip( $group['name'] ?? '' ),
			'items' => array_map(
				static function ( $ing ) use ( $strip ) {
					$amount = $strip( $ing['amount'] ?? '' );
					$unit   = $strip( $ing['unit'] ?? '' );
					$name   = $strip( $ing['name'] ?? '' );
					$notes  = $strip( $ing['notes'] ?? '' );
					$parts  = array_filter( [ $amount, $unit, $name ] );
					$text   = implode( ' ', $parts );
					if ( $notes !== '' ) {
						$text .= ' (' . $notes . ')';
					}
					return $text;
				},
				(array) ( $group['ingredients'] ?? [] )
			),
		];
	}

	$instruction_groups = [];
	foreach ( (array) $recipe->instructions() as $group ) {
		$instruction_groups[] = [
			'name'  => $strip( $group['name'] ?? '' ),
			'steps' => array_map(
				static function ( $step ) {
					return trim( wp_kses_post( (string) ( $step['text'] ?? '' ) ) );
				},
				(array) ( $group['instructions'] ?? [] )
			),
		];
	}

	// Author, in priority order:
	//   1. The plugin's own "rspdf_author_name" setting (Sellable PDF admin
	//      page). This is the reliable cross-site option: WPRM's "Same name
	//      for all recipes" setting is not exposed on every site/tier.
	//   2. WPRM's resolved author(). (author_display() returns the SETTING
	//      string "same"/"post_author"/etc., NOT a name - author() resolves it.)
	//   3. The WordPress post author, so this field never reads blank.
	$author = $strip( (string) get_option( 'rspdf_author_name', '' ) );
	if ( $author === '' ) {
		$author = $strip( $recipe->author() );
	}
	if ( $author === '' ) {
		$author = $strip( $recipe->post_author_name() );
	}

	return [
		'title'        => $strip( $recipe->name() ),
		'summary'      => trim( wp_kses_post( (string) $recipe->summary() ) ),
		'author'       => $author,
		'course'       => implode( ', ', array_map( $strip, (array) $recipe->tags( 'course', true ) ) ),
		'cuisine'      => implode( ', ', array_map( $strip, (array) $recipe->tags( 'cuisine', true ) ) ),
		'prep_time'    => rspdf_format_time( $recipe->prep_time(), 'minutes' ),
		'cook_time'    => rspdf_format_time( $recipe->cook_time(), 'minutes' ),
		'total_time'   => rspdf_format_time( $recipe->total_time(), 'minutes' ),
		'servings'     => trim( (string) $recipe->servings() . ' ' . $strip( $recipe->servings_unit() ) ),
		'notes'        => trim( wp_kses_post( (string) $recipe->notes() ) ),
		'image_uri'    => $image_uri,
		'ingredients'  => $ingredient_groups,
		'instructions' => $instruction_groups,
		'nutrition'    => rspdf_build_nutrition( $recipe ),
		'brand_name'   => (string) RSPDF_BRAND_NAME,
		'brand_url'    => (string) RSPDF_BRAND_URL,
		'brand_accent' => (string) RSPDF_BRAND_ACCENT,
	];
}

/**
 * Render the HTML template for a given view.
 */
function rspdf_render_html( array $view ): string {
	ob_start();
	$rspdf = $view;
	include RSPDF_PATH . 'templates/recipe.php';
	return (string) ob_get_clean();
}

/**
 * Generate a PDF binary string from a recipe ID (or post ID).
 *
 * @return string|WP_Error PDF bytes on success, WP_Error on failure.
 */
function rspdf_generate( int $id ) {
	$recipe = rspdf_resolve_recipe( $id );
	if ( is_wp_error( $recipe ) ) {
		return $recipe;
	}

	$view = rspdf_build_view( $recipe );
	$html = rspdf_render_html( $view );

	$options = new Options();
	$options->set( 'isRemoteEnabled', false );
	$options->set( 'isHtml5ParserEnabled', true );
	$options->set( 'defaultFont', 'Helvetica' );

	$dompdf = new Dompdf( $options );
	$dompdf->loadHtml( $html, 'UTF-8' );
	$dompdf->setPaper( 'letter', 'portrait' );
	$dompdf->render();

	return (string) $dompdf->output();
}

/**
 * Filename-safe slug from a recipe title.
 */
function rspdf_slug( string $title ): string {
	$slug = sanitize_title( $title );
	return $slug !== '' ? $slug : 'recipe';
}
