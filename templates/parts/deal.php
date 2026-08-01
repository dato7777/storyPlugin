<?php
/**
 * Part: deal spotlight with a live countdown.
 *
 * Picks the deepest genuine discount currently in the catalog. The countdown
 * runs to WooCommerce's own sale end date when one is set, otherwise to
 * midnight in the site's timezone.
 *
 * @package StoryPhone_Pages
 *
 * @var array<string, mixed> $args Expects 'product' => WC_Product|null.
 */

defined( 'ABSPATH' ) || exit;

$sp_product = isset( $args['product'] ) ? $args['product'] : null;
if ( ! $sp_product instanceof WC_Product ) {
	return;
}

$sp_link     = get_permalink( $sp_product->get_id() );
$sp_link     = $sp_link ? $sp_link : home_url( '/' );
$sp_discount = StoryPhone_Pages_Render::get_discount_percent( $sp_product );

$sp_sale_end = $sp_product->get_date_on_sale_to();
if ( $sp_sale_end instanceof WC_DateTime ) {
	$sp_deadline = $sp_sale_end->getTimestamp();
} else {
	$sp_deadline = current_datetime()->modify( 'tomorrow midnight' )->getTimestamp();
}
?>
<section class="sp-deal" id="sp-deal">
	<div class="sp-shell">
		<div class="sp-deal__panel" data-sp-reveal>
			<div class="sp-aurora sp-aurora--soft" aria-hidden="true">
				<span class="sp-aurora__blob sp-aurora__blob--3"></span>
			</div>

			<div class="sp-deal__copy">
				<p class="sp-deal__kicker">
					<span class="sp-deal__bolt" aria-hidden="true">&#9889;</span>
					<?php esc_html_e( 'הדיל של היום', 'storyphone-pages' ); ?>
				</p>

				<h2 class="sp-deal__title">
					<a href="<?php echo esc_url( $sp_link ); ?>"><?php echo esc_html( $sp_product->get_name() ); ?></a>
				</h2>

				<div class="sp-deal__price"><?php echo wp_kses_post( $sp_product->get_price_html() ); ?></div>

				<div
					class="sp-countdown"
					data-sp-countdown="<?php echo esc_attr( gmdate( 'c', $sp_deadline ) ); ?>"
					aria-label="<?php esc_attr_e( 'הזמן שנותר למבצע', 'storyphone-pages' ); ?>"
				>
					<span class="sp-countdown__cell"><b data-sp-cd-h>--</b><i><?php esc_html_e( 'שעות', 'storyphone-pages' ); ?></i></span>
					<span class="sp-countdown__sep" aria-hidden="true">:</span>
					<span class="sp-countdown__cell"><b data-sp-cd-m>--</b><i><?php esc_html_e( 'דקות', 'storyphone-pages' ); ?></i></span>
					<span class="sp-countdown__sep" aria-hidden="true">:</span>
					<span class="sp-countdown__cell"><b data-sp-cd-s>--</b><i><?php esc_html_e( 'שניות', 'storyphone-pages' ); ?></i></span>
				</div>

				<div class="sp-deal__actions">
					<?php if ( StoryPhone_Pages_Catalog::supports_quick_add( $sp_product ) ) : ?>
						<button
							type="button"
							class="sp-btn sp-btn--primary sp-btn--lg"
							data-sp-add-to-cart
							data-product-id="<?php echo esc_attr( (string) $sp_product->get_id() ); ?>"
						>
							<span class="sp-btn__label"><?php esc_html_e( 'תפסו את המבצע', 'storyphone-pages' ); ?></span>
						</button>
					<?php endif; ?>
					<a class="sp-btn sp-btn--quiet sp-btn--lg" href="<?php echo esc_url( $sp_link ); ?>">
						<?php esc_html_e( 'לפרטים המלאים', 'storyphone-pages' ); ?>
					</a>
				</div>
			</div>

			<a class="sp-deal__media" href="<?php echo esc_url( $sp_link ); ?>" data-sp-tilt>
				<?php
				echo wp_kses_post(
					$sp_product->get_image(
						'woocommerce_single',
						array(
							'class'    => 'sp-deal__img',
							'loading'  => 'lazy',
							'decoding' => 'async',
						)
					)
				);
				?>
				<?php if ( $sp_discount > 0 ) : ?>
					<span class="sp-deal__save">
						<b><?php echo esc_html( (string) absint( $sp_discount ) ); ?>%</b>
						<i><?php esc_html_e( 'הנחה', 'storyphone-pages' ); ?></i>
					</span>
				<?php endif; ?>
			</a>
		</div>
	</div>
</section>
