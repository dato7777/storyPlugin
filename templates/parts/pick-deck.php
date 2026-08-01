<?php
/**
 * Part: rotating best-seller / deal-of-the-day card deck.
 *
 * Lives below the Stories rail so the hero's side stage can host the nav's
 * compact category cards instead.
 *
 * @package StoryPhone_Pages
 *
 * @var array<string, mixed> $args Expects 'product' and 'deal' => WC_Product|null.
 */

defined( 'ABSPATH' ) || exit;

$sp_product  = isset( $args['product'] ) ? $args['product'] : null;
$sp_deal     = isset( $args['deal'] ) ? $args['deal'] : null;
$sp_title    = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
$sp_subtitle = isset( $args['subtitle'] ) ? trim( (string) $args['subtitle'] ) : '';

if ( ! $sp_product instanceof WC_Product ) {
	return;
}

// Never show the same product on both faces — rotating a card onto itself
// reads as a glitch rather than as a second offer.
if ( $sp_deal instanceof WC_Product && $sp_deal->get_id() === $sp_product->get_id() ) {
	$sp_deal = null;
}

if ( $sp_deal instanceof WC_Product ) {
	$sp_deal_end = $sp_deal->get_date_on_sale_to();
	$sp_deadline = $sp_deal_end instanceof WC_DateTime
		? $sp_deal_end->getTimestamp()
		: current_datetime()->modify( 'tomorrow midnight' )->getTimestamp();
}

$sp_link     = get_permalink( $sp_product->get_id() );
$sp_link     = $sp_link ? $sp_link : home_url( '/' );
$sp_discount = StoryPhone_Pages_Render::get_discount_percent( $sp_product );
?>
<section class="sp-spotlight" id="sp-spotlight" aria-label="<?php esc_attr_e( 'מומלצים', 'storyphone-pages' ); ?>">
	<div class="sp-shell sp-spotlight__inner">
		<header class="sp-section__head" data-sp-reveal>
			<div>
				<h2 class="sp-section__title"><?php echo esc_html( $sp_title ? $sp_title : __( 'הבחירה של החנות', 'storyphone-pages' ) ); ?></h2>
				<p class="sp-section__subtitle"><?php echo esc_html( $sp_subtitle ? $sp_subtitle : __( 'הנמכר ביותר והדיל של היום — מתחלפים באותה כרטיסייה', 'storyphone-pages' ) ); ?></p>
			</div>
		</header>

		<div class="sp-spotlight__deck" data-sp-reveal>
			<div class="sp-pickDeck" data-sp-deck data-sp-tilt>

				<div class="sp-pickDeck__stack">

					<article class="sp-pick sp-pick--hot is-active" data-sp-deck-face>
						<div class="sp-pick__glow" aria-hidden="true"></div>

						<p class="sp-pick__kicker">
							<span class="sp-pick__flame" aria-hidden="true">&#128293;</span>
							<?php esc_html_e( 'הנמכר ביותר', 'storyphone-pages' ); ?>
						</p>

						<a class="sp-pick__media" href="<?php echo esc_url( $sp_link ); ?>">
							<?php
							echo wp_kses_post(
								$sp_product->get_image(
									'woocommerce_single',
									array(
										'class'    => 'sp-pick__img',
										'loading'  => 'lazy',
										'decoding' => 'async',
									)
								)
							);
							?>
							<?php if ( $sp_discount > 0 ) : ?>
								<span class="sp-badge sp-badge--sale">
									<?php
									printf(
										/* translators: %d: discount percentage. */
										esc_html__( '%d%%- הנחה', 'storyphone-pages' ),
										absint( $sp_discount )
									);
									?>
								</span>
							<?php endif; ?>
						</a>

						<h2 class="sp-pick__title">
							<a href="<?php echo esc_url( $sp_link ); ?>"><?php echo esc_html( $sp_product->get_name() ); ?></a>
						</h2>

						<div class="sp-pick__price"><?php echo wp_kses_post( $sp_product->get_price_html() ); ?></div>

						<?php if ( StoryPhone_Pages_Catalog::supports_quick_add( $sp_product ) ) : ?>
							<button
								type="button"
								class="sp-btn sp-btn--primary sp-btn--block"
								data-sp-add-to-cart
								data-product-id="<?php echo esc_attr( (string) $sp_product->get_id() ); ?>"
							>
								<span class="sp-btn__label"><?php esc_html_e( 'הוספה לסל', 'storyphone-pages' ); ?></span>
							</button>
						<?php else : ?>
							<a class="sp-btn sp-btn--primary sp-btn--block" href="<?php echo esc_url( $sp_link ); ?>">
								<?php esc_html_e( 'לצפייה במוצר', 'storyphone-pages' ); ?>
							</a>
						<?php endif; ?>
					</article>

					<?php if ( $sp_deal instanceof WC_Product ) : ?>
						<?php
						$sp_deal_link     = get_permalink( $sp_deal->get_id() );
						$sp_deal_link     = $sp_deal_link ? $sp_deal_link : home_url( '/' );
						$sp_deal_discount = StoryPhone_Pages_Render::get_discount_percent( $sp_deal );
						?>
						<article class="sp-pick sp-pick--deal" data-sp-deck-face>
							<div class="sp-pick__glow" aria-hidden="true"></div>

							<p class="sp-pick__kicker sp-pick__kicker--deal">
								<span class="sp-pick__bolt" aria-hidden="true">&#9889;</span>
								<?php esc_html_e( 'הדיל של היום', 'storyphone-pages' ); ?>
							</p>

							<a class="sp-pick__media" href="<?php echo esc_url( $sp_deal_link ); ?>">
								<?php
								echo wp_kses_post(
									$sp_deal->get_image(
										'woocommerce_single',
										array(
											'class'    => 'sp-pick__img',
											'loading'  => 'lazy',
											'decoding' => 'async',
										)
									)
								);
								?>
								<?php if ( $sp_deal_discount > 0 ) : ?>
									<span class="sp-badge sp-badge--sale">
										<?php
										printf(
											/* translators: %d: discount percentage. */
											esc_html__( '%d%%- הנחה', 'storyphone-pages' ),
											absint( $sp_deal_discount )
										);
										?>
									</span>
								<?php endif; ?>
							</a>

							<h2 class="sp-pick__title">
								<a href="<?php echo esc_url( $sp_deal_link ); ?>"><?php echo esc_html( $sp_deal->get_name() ); ?></a>
							</h2>

							<div class="sp-pick__price"><?php echo wp_kses_post( $sp_deal->get_price_html() ); ?></div>

							<div
								class="sp-pick__timer"
								data-sp-countdown="<?php echo esc_attr( gmdate( 'c', $sp_deadline ) ); ?>"
								aria-label="<?php esc_attr_e( 'הזמן שנותר למבצע', 'storyphone-pages' ); ?>"
							>
								<span class="sp-pick__timerIcon" aria-hidden="true"></span>
								<b data-sp-cd-h>--</b><i aria-hidden="true">:</i><b data-sp-cd-m>--</b><i aria-hidden="true">:</i><b data-sp-cd-s>--</b>
								<span class="sp-pick__timerNote"><?php esc_html_e( 'עד סוף המבצע', 'storyphone-pages' ); ?></span>
							</div>

							<?php if ( StoryPhone_Pages_Catalog::supports_quick_add( $sp_deal ) ) : ?>
								<button
									type="button"
									class="sp-btn sp-btn--primary sp-btn--block"
									data-sp-add-to-cart
									data-product-id="<?php echo esc_attr( (string) $sp_deal->get_id() ); ?>"
								>
									<span class="sp-btn__label"><?php esc_html_e( 'תפסו את המבצע', 'storyphone-pages' ); ?></span>
								</button>
							<?php else : ?>
								<a class="sp-btn sp-btn--primary sp-btn--block" href="<?php echo esc_url( $sp_deal_link ); ?>">
									<?php esc_html_e( 'לצפייה במבצע', 'storyphone-pages' ); ?>
								</a>
							<?php endif; ?>
						</article>
					<?php endif; ?>

				</div>

				<?php if ( $sp_deal instanceof WC_Product ) : ?>
					<div class="sp-pickDeck__pips">
						<button
							type="button"
							class="sp-pickDeck__pip is-on"
							data-sp-deck-pip
							data-index="0"
							aria-pressed="true"
						>
							<span class="sp-pickDeck__pipFill" aria-hidden="true"></span>
							<span class="sp-srOnly"><?php esc_html_e( 'הנמכר ביותר', 'storyphone-pages' ); ?></span>
						</button>
						<button
							type="button"
							class="sp-pickDeck__pip"
							data-sp-deck-pip
							data-index="1"
							aria-pressed="false"
						>
							<span class="sp-pickDeck__pipFill" aria-hidden="true"></span>
							<span class="sp-srOnly"><?php esc_html_e( 'הדיל של היום', 'storyphone-pages' ); ?></span>
						</button>
					</div>
				<?php endif; ?>

			</div>
		</div>
	</div>
</section>
