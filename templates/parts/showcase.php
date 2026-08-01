<?php
/**
 * Part: product showcase grid.
 *
 * Products are rendered server-side so the page has real content (and real
 * Hebrew text for SEO) in its first paint, with no loading spinner.
 *
 * @package StoryPhone_Pages
 *
 * @var array<string, mixed> $args Expects 'products', 'title', 'subtitle'.
 */

defined( 'ABSPATH' ) || exit;

$sp_products = isset( $args['products'] ) && is_array( $args['products'] ) ? $args['products'] : array();
if ( empty( $sp_products ) ) {
	return;
}

$sp_title    = isset( $args['title'] ) ? $args['title'] : '';
$sp_subtitle = isset( $args['subtitle'] ) ? $args['subtitle'] : '';
$sp_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>
<section class="sp-section" id="sp-showcase">
	<div class="sp-shell">

		<header class="sp-section__head" data-sp-reveal>
			<div>
				<?php if ( $sp_title ) : ?>
					<h2 class="sp-section__title"><?php echo esc_html( $sp_title ); ?></h2>
				<?php endif; ?>
				<?php if ( $sp_subtitle ) : ?>
					<p class="sp-section__subtitle"><?php echo esc_html( $sp_subtitle ); ?></p>
				<?php endif; ?>
			</div>
			<a class="sp-textlink" href="<?php echo esc_url( $sp_shop_url ); ?>">
				<?php esc_html_e( 'לכל המוצרים', 'storyphone-pages' ); ?>
				<span aria-hidden="true">&#8592;</span>
			</a>
		</header>

		<div class="sp-grid sp-grid--products">
			<?php
			foreach ( $sp_products as $sp_product ) {
				StoryPhone_Pages_Render::product_card( $sp_product );
			}
			?>
		</div>

	</div>
</section>
