<?php
/**
 * Admin surface: a "Sellable PDF" submenu under WP Recipe Maker that lists
 * recipes with per-row Download buttons, plus an admin-post.php download
 * handler. (WPRM disables the standard wprm_recipe list/edit screens, so we
 * cannot rely on post_row_actions or add_meta_boxes.)
 */

defined( 'ABSPATH' ) or die();

/**
 * Capability gate. Mirrors WPRM's "manage" feature when available, falls back
 * to edit_posts so the page works on free WPRM too.
 */
function rspdf_capability(): string {
	if ( class_exists( 'WPRM_Settings' ) ) {
		$cap = WPRM_Settings::get( 'features_manage_access' );
		if ( is_string( $cap ) && $cap !== '' ) {
			return $cap;
		}
	}
	return 'edit_posts';
}

/**
 * URL that triggers the download for a given recipe ID.
 */
function rspdf_download_url( int $id ): string {
	return wp_nonce_url(
		add_query_arg(
			[
				'action' => 'rspdf_download',
				'id'     => $id,
			],
			admin_url( 'admin-post.php' )
		),
		'rspdf_download_' . $id
	);
}

/**
 * Register the submenu under WP Recipe Maker's top-level menu.
 */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'wprecipemaker',
		__( 'Sellable PDF', 'recipe-sellable-pdf' ),
		__( 'Sellable PDF', 'recipe-sellable-pdf' ),
		rspdf_capability(),
		'rspdf',
		'rspdf_render_list_page'
	);
}, 50 );

/**
 * Render the recipe-list admin page.
 */
function rspdf_render_list_page(): void {
	if ( ! current_user_can( rspdf_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'recipe-sellable-pdf' ) );
	}

	// Save the per-site author name. Handled here (not via options.php) so the
	// save uses rspdf_capability() rather than options.php's manage_options
	// default. check_admin_referer() is the nonce check.
	$saved = false;
	if ( isset( $_POST['rspdf_save_settings'] ) ) {
		check_admin_referer( 'rspdf_save_settings' );
		$author_name = isset( $_POST['rspdf_author_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rspdf_author_name'] ) ) : '';
		update_option( 'rspdf_author_name', $author_name );
		$saved = true;
	}

	$per_page = 20;
	$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$query = new WP_Query(
		[
			'post_type'      => 'wprm_recipe',
			'post_status'    => [ 'publish', 'draft', 'private' ],
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => $search,
		]
	);

	$base_url = admin_url( 'admin.php?page=rspdf' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Sellable PDF', 'recipe-sellable-pdf' ); ?></h1>
		<p><?php esc_html_e( 'Generate a styled, sellable-quality PDF of any recipe. Click Download on the row you want.', 'recipe-sellable-pdf' ); ?></p>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Author name saved.', 'recipe-sellable-pdf' ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'PDF author name', 'recipe-sellable-pdf' ); ?></h2>
		<form method="post" style="margin: 0 0 12px 0;">
			<?php wp_nonce_field( 'rspdf_save_settings' ); ?>
			<input type="hidden" name="rspdf_save_settings" value="1">
			<input type="text" class="regular-text" name="rspdf_author_name" id="rspdf_author_name"
				value="<?php echo esc_attr( (string) get_option( 'rspdf_author_name', '' ) ); ?>"
				placeholder="<?php esc_attr_e( 'e.g. Your Name or Brand', 'recipe-sellable-pdf' ); ?>">
			<?php submit_button( __( 'Save author name', 'recipe-sellable-pdf' ), 'secondary', 'submit', false ); ?>
			<p class="description"><?php esc_html_e( 'Shown as the Author on every PDF from this site. Leave blank to use the recipe\'s own author (WP Recipe Maker setting, then the WordPress post author).', 'recipe-sellable-pdf' ); ?></p>
		</form>

		<hr>

		<form method="get" style="margin: 12px 0;">
			<input type="hidden" name="page" value="rspdf">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search recipes', 'recipe-sellable-pdf' ); ?>">
			<?php submit_button( __( 'Search', 'recipe-sellable-pdf' ), '', '', false ); ?>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Recipe', 'recipe-sellable-pdf' ); ?></th>
					<th scope="col" style="width: 8em;"><?php esc_html_e( 'Status', 'recipe-sellable-pdf' ); ?></th>
					<th scope="col" style="width: 10em;"><?php esc_html_e( 'Modified', 'recipe-sellable-pdf' ); ?></th>
					<th scope="col" style="width: 14em;"></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $query->have_posts() ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No recipes found.', 'recipe-sellable-pdf' ); ?></td></tr>
				<?php else : ?>
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
						<?php $recipe_id = (int) get_the_ID(); ?>
						<tr>
							<td>
								<strong><?php echo esc_html( get_the_title() ?: __( '(untitled)', 'recipe-sellable-pdf' ) ); ?></strong>
							</td>
							<td><?php echo esc_html( get_post_status() ); ?></td>
							<td><?php echo esc_html( get_the_modified_date( 'Y-m-d' ) ); ?></td>
							<td>
								<a class="button button-primary" href="<?php echo esc_url( rspdf_download_url( $recipe_id ) ); ?>">
									<?php esc_html_e( 'Download PDF', 'recipe-sellable-pdf' ); ?>
								</a>
							</td>
						</tr>
					<?php endwhile; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
		$total_pages = (int) $query->max_num_pages;
		if ( $total_pages > 1 ) {
			$links = paginate_links(
				[
					'base'      => add_query_arg( 'paged', '%#%', $base_url . ( $search !== '' ? '&s=' . rawurlencode( $search ) : '' ) ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'prev_text' => __( '« Prev', 'recipe-sellable-pdf' ),
					'next_text' => __( 'Next »', 'recipe-sellable-pdf' ),
				]
			);
			if ( $links ) {
				echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
			}
		}
		wp_reset_postdata();
		?>
	</div>
	<?php
}

/**
 * Download handler. Validates nonce + capability, streams PDF to browser.
 */
add_action( 'admin_post_rspdf_download', function () {
	// $id is sanitized via (int) cast; we need its value to construct the
	// nonce action below, so the nonce check necessarily follows this read.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( $id <= 0 ) {
		wp_die( esc_html__( 'Missing recipe ID.', 'recipe-sellable-pdf' ), '', [ 'response' => 400 ] );
	}

	if ( ! current_user_can( rspdf_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'recipe-sellable-pdf' ), '', [ 'response' => 403 ] );
	}

	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'rspdf_download_' . $id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'recipe-sellable-pdf' ), '', [ 'response' => 403 ] );
	}

	$recipe = rspdf_resolve_recipe( $id );
	if ( is_wp_error( $recipe ) ) {
		wp_die( esc_html( $recipe->get_error_message() ), '', [ 'response' => 404 ] );
	}

	// Object-level authorization. The capability gate above is a blanket check;
	// it does not stop a user from passing an arbitrary ID. Require edit access
	// to the specific recipe so lower-privileged users cannot download recipes
	// (including drafts/private) authored by someone else.
	if ( ! current_user_can( 'edit_post', $recipe->id() ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'recipe-sellable-pdf' ), '', [ 'response' => 403 ] );
	}

	$pdf = rspdf_generate( $id );
	if ( is_wp_error( $pdf ) ) {
		wp_die( esc_html( $pdf->get_error_message() ), '', [ 'response' => 500 ] );
	}

	$title = (string) $recipe->name();

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . rspdf_slug( $title ) . '.pdf"' );
	header( 'Content-Length: ' . strlen( $pdf ) );
	// Binary PDF output; escaping would corrupt it. Headers are set above.
	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
} );
