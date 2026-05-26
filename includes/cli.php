<?php
/**
 * WP-CLI command: wp recipe-pdf generate <id> [--output=<path>]
 */

defined( 'ABSPATH' ) or die();
if ( ! ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) ) {
	return;
}

WP_CLI::add_command( 'recipe-pdf generate', function ( $args, $assoc_args ) {
	if ( empty( $args[0] ) ) {
		WP_CLI::error( 'Usage: wp recipe-pdf generate <recipe_or_post_id> [--output=<path>]' );
	}
	$id = (int) $args[0];

	$pdf = rspdf_generate( $id );
	if ( is_wp_error( $pdf ) ) {
		WP_CLI::error( $pdf->get_error_message() );
	}

	$recipe = rspdf_resolve_recipe( $id );
	$slug   = is_wp_error( $recipe ) ? 'recipe' : rspdf_slug( (string) $recipe->name() );
	$output = $assoc_args['output'] ?? ( $slug . '.pdf' );

	if ( false === file_put_contents( $output, $pdf ) ) {
		WP_CLI::error( 'Failed to write ' . $output );
	}
	WP_CLI::success( 'Wrote ' . $output . ' (' . number_format( strlen( $pdf ) ) . ' bytes).' );
} );
