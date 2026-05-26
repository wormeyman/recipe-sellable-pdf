<?php
/**
 * PDF template. Variables come from $rspdf (set by rspdf_render_html()).
 *
 * @var array $rspdf
 */
defined( 'ABSPATH' ) or die();

$rspdf_accent = $rspdf['brand_accent'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( $rspdf['title'] ); ?></title>
<style>
	@page { margin: 0.6in 0.75in 0.75in 0.75in; }
	body {
		font-family: Helvetica, Arial, sans-serif;
		color: #1A1A1A;
		font-size: 10pt;
		line-height: 1.5;
	}
	.header { width: 100%; margin-bottom: 12pt; }
	.header td { vertical-align: top; }
	.header .image { width: 1.9in; padding-right: 0.15in; }
	.header .image img { width: 1.9in; height: 1.9in; object-fit: cover; }
	.title { font-size: 22pt; font-weight: bold; margin: 0 0 4pt 0; }
	.summary { color: #555; font-size: 9.5pt; line-height: 1.4; }
	.rule { border-top: 1pt solid #D4B896; height: 1pt; margin: 8pt 0; }
	.meta { width: 100%; margin-bottom: 4pt; }
	.meta td { padding: 2pt 6pt 2pt 0; vertical-align: top; }
	.meta .label { font-weight: bold; width: 1.25in; }
	.meta .value { color: #555; }
	h2.section {
		color: <?php echo esc_html( $rspdf_accent ); ?>;
		font-size: 13pt;
		margin: 14pt 0 6pt 0;
	}
	.group-name { font-weight: bold; margin: 6pt 0 2pt 0; }
	ul.ingredients { padding-left: 14pt; margin: 0 0 4pt 0; }
	ul.ingredients li { margin-bottom: 2pt; }
	ol.steps { padding-left: 18pt; margin: 0 0 4pt 0; }
	ol.steps li {
		margin-bottom: 6pt;
		padding-left: 4pt;
	}
	ol.steps li::marker {
		color: <?php echo esc_html( $rspdf_accent ); ?>;
		font-weight: bold;
	}
	.notes { color: #555; font-size: 9.5pt; }
	.footer {
		position: fixed;
		bottom: -0.3in;
		left: 0;
		right: 0;
		text-align: center;
		font-size: 8pt;
		color: #555;
	}
</style>
</head>
<body>

<?php if ( $rspdf['image_uri'] ) : ?>
	<table class="header">
		<tr>
			<td class="image">
				<img src="<?php echo esc_attr( $rspdf['image_uri'] ); ?>" alt="">
			</td>
			<td>
				<h1 class="title"><?php echo esc_html( $rspdf['title'] ); ?></h1>
				<?php if ( $rspdf['summary'] ) : ?>
					<?php // Summary is pre-sanitized via wp_kses_post() in rspdf_build_view(). ?>
					<div class="summary"><?php echo $rspdf['summary']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>
			</td>
		</tr>
	</table>
<?php else : ?>
	<h1 class="title"><?php echo esc_html( $rspdf['title'] ); ?></h1>
	<?php if ( $rspdf['summary'] ) : ?>
		<?php // Summary is pre-sanitized via wp_kses_post() in rspdf_build_view(). ?>
		<div class="summary"><?php echo $rspdf['summary']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php endif; ?>
<?php endif; ?>

<div class="rule"></div>

<?php
$rspdf_meta_rows = array_filter(
	[
		[ 'Prep Time',  $rspdf['prep_time'] ],
		[ 'Cook Time',  $rspdf['cook_time'] ],
		[ 'Total Time', $rspdf['total_time'] ],
		[ 'Servings',   $rspdf['servings'] ],
		[ 'Course',     $rspdf['course'] ],
		[ 'Cuisine',    $rspdf['cuisine'] ],
		[ 'Author',     $rspdf['author'] ],
	],
	static fn( $row ) => trim( (string) $row[1] ) !== ''
);
?>
<?php if ( $rspdf_meta_rows ) : ?>
	<table class="meta">
		<?php foreach ( $rspdf_meta_rows as [ $rspdf_label, $rspdf_value ] ) : ?>
			<tr>
				<td class="label"><?php echo esc_html( $rspdf_label ); ?></td>
				<td class="value"><?php echo esc_html( $rspdf_value ); ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
	<div class="rule"></div>
<?php endif; ?>

<?php if ( $rspdf['ingredients'] ) : ?>
	<h2 class="section">Ingredients</h2>
	<?php foreach ( $rspdf['ingredients'] as $rspdf_group ) : ?>
		<?php if ( $rspdf_group['name'] !== '' ) : ?>
			<div class="group-name"><?php echo esc_html( $rspdf_group['name'] ); ?></div>
		<?php endif; ?>
		<?php if ( $rspdf_group['items'] ) : ?>
			<ul class="ingredients">
				<?php foreach ( $rspdf_group['items'] as $rspdf_item ) : ?>
					<?php if ( $rspdf_item !== '' ) : ?>
						<li><?php echo esc_html( $rspdf_item ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( $rspdf['instructions'] ) : ?>
	<h2 class="section">Instructions</h2>
	<?php foreach ( $rspdf['instructions'] as $rspdf_group ) : ?>
		<?php if ( $rspdf_group['name'] !== '' ) : ?>
			<div class="group-name"><?php echo esc_html( $rspdf_group['name'] ); ?></div>
		<?php endif; ?>
		<?php if ( $rspdf_group['steps'] ) : ?>
			<ol class="steps">
				<?php foreach ( $rspdf_group['steps'] as $rspdf_step ) : ?>
					<?php if ( $rspdf_step !== '' ) : ?>
						<li><?php echo wp_kses_post( $rspdf_step ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( trim( (string) $rspdf['notes'] ) !== '' ) : ?>
	<div class="rule"></div>
	<h2 class="section">Notes</h2>
	<div class="notes"><?php echo wp_kses_post( $rspdf['notes'] ); ?></div>
<?php endif; ?>

<div class="footer">
	<?php echo esc_html( $rspdf['brand_name'] ); ?> · <?php echo esc_html( $rspdf['brand_url'] ); ?>
</div>

</body>
</html>
