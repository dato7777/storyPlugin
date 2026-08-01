<?php
/**
 * Part: the Heat Board — best sellers, ranked.
 *
 * Ranking comes from WooCommerce's own `total_sales`. The bars show each
 * product's popularity *relative* to the top seller, so the page never
 * publishes the client's actual sales numbers to competitors.
 *
 * @package StoryPhone_Pages
 *
 * @var array<string, mixed> $args Expects 'products' => WC_Product[].
 */

defined( 'ABSPATH' ) || exit;

$sp_products = isset( $args['products'] ) && is_array( $args['products'] ) ? $args['products'] : array();
$sp_title    = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
$sp_subtitle = isset( $args['subtitle'] ) ? trim( (string) $args['subtitle'] ) : '';
if ( empty( $sp_products ) ) {
	return;
}

$sp_heat = StoryPhone_Pages_Catalog::get_heat_map( $sp_products );
?>
<section class="sp-heat" id="sp-heat">
	<div class="sp-aurora sp-aurora--soft" aria-hidden="true">
		<span class="sp-aurora__blob sp-aurora__blob--2"></span>
	</div>

	<div class="sp-shell">

		<header class="sp-section__head" data-sp-reveal>
			<div>
				<h2 class="sp-section__title">
					<span class="sp-heat__flame" aria-hidden="true">&#128293;</span>
					<?php echo esc_html( $sp_title ? $sp_title : __( 'הכי חם עכשיו', 'storyphone-pages' ) ); ?>
				</h2>
				<p class="sp-section__subtitle"><?php echo esc_html( $sp_subtitle ? $sp_subtitle : __( 'מה שהלקוחות שלנו קונים הכי הרבה — מתעדכן לפי מכירות אמיתיות', 'storyphone-pages' ) ); ?></p>
			</div>
			<p class="sp-heat__live" aria-hidden="true">
				<span class="sp-heat__pulse"></span>
				<?php esc_html_e( 'לפי נתוני מכירות', 'storyphone-pages' ); ?>
			</p>
		</header>

		<ol class="sp-heat__list">
			<?php foreach ( $sp_products as $sp_rank => $sp_product ) : ?>
				<?php
				$sp_link    = get_permalink( $sp_product->get_id() );
				$sp_link    = $sp_link ? $sp_link : home_url( '/' );
				$sp_id      = $sp_product->get_id();
				$sp_value   = isset( $sp_heat[ $sp_id ] ) ? (int) $sp_heat[ $sp_id ] : 0;
				$sp_instock = $sp_product->is_in_stock();
				?>
				<li class="sp-heat__row" data-sp-reveal>
					<span class="sp-heat__rank" aria-hidden="true">
						<?php echo esc_html( str_pad( (string) ( $sp_rank + 1 ), 2, '0', STR_PAD_LEFT ) ); ?>
					</span>

					<a class="sp-heat__media" href="<?php echo esc_url( $sp_link ); ?>" tabindex="-1" aria-hidden="true">
						<?php
						echo wp_kses_post(
							$sp_product->get_image(
								'woocommerce_thumbnail',
								array(
									'class'    => 'sp-heat__img',
									'loading'  => 'lazy',
									'decoding' => 'async',
								)
							)
						);
						?>
					</a>

					<div class="sp-heat__body">
						<h3 class="sp-heat__name">
							<a href="<?php echo esc_url( $sp_link ); ?>"><?php echo esc_html( $sp_product->get_name() ); ?></a>
						</h3>

						<div class="sp-heat__bar" role="presentation">
							<span class="sp-heat__fill" style="--sp-heat: <?php echo esc_attr( (string) $sp_value ); ?>%"></span>
						</div>
					</div>

					<div class="sp-heat__side">
						<div class="sp-heat__price"><?php echo wp_kses_post( $sp_product->get_price_html() ); ?></div>

						<?php if ( StoryPhone_Pages_Catalog::supports_quick_add( $sp_product ) ) : ?>
							<button
								type="button"
								class="sp-btn sp-btn--add sp-btn--sm"
								data-sp-add-to-cart
								data-product-id="<?php echo esc_attr( (string) $sp_id ); ?>"
							>
								<span class="sp-btn__label"><?php esc_html_e( 'הוספה', 'storyphone-pages' ); ?></span>
							</button>
						<?php else : ?>
							<a class="sp-btn sp-btn--ghost sp-btn--sm" href="<?php echo esc_url( $sp_link ); ?>">
								<?php echo esc_html( $sp_instock ? __( 'לפרטים', 'storyphone-pages' ) : __( 'אזל', 'storyphone-pages' ) ); ?>
							</a>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>

	</div>
</section>
