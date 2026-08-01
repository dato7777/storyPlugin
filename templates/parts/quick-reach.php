<?php
/**
 * Part: quick reach tiles.
 *
 * Two taps to anything: the rail covers browsing, these tiles cover the
 * "I already know what I want" shopper.
 *
 * @package StoryPhone_Pages
 *
 * @var array<string, mixed> $args Expects 'categories' => WP_Term[].
 */

defined( 'ABSPATH' ) || exit;

$sp_categories = isset( $args['categories'] ) && is_array( $args['categories'] ) ? $args['categories'] : array();
$sp_title      = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
$sp_subtitle   = isset( $args['subtitle'] ) ? trim( (string) $args['subtitle'] ) : '';
if ( empty( $sp_categories ) ) {
	return;
}

$sp_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>
<section class="sp-section sp-reach" id="sp-reach">
	<div class="sp-shell">

		<header class="sp-section__head" data-sp-reveal>
			<div>
				<h2 class="sp-section__title"><?php echo esc_html( $sp_title ? $sp_title : __( 'מגיעים ישר לעניין', 'storyphone-pages' ) ); ?></h2>
				<p class="sp-section__subtitle"><?php echo esc_html( $sp_subtitle ? $sp_subtitle : __( 'יודעים מה אתם רוצים? קפצו ישירות לקטגוריה', 'storyphone-pages' ) ); ?></p>
			</div>
			<a class="sp-textlink" href="<?php echo esc_url( $sp_shop_url ); ?>">
				<?php esc_html_e( 'כל הקטגוריות', 'storyphone-pages' ); ?>
				<span aria-hidden="true">&#8592;</span>
			</a>
		</header>

		<div class="sp-reach__grid">
			<?php foreach ( $sp_categories as $sp_i => $sp_term ) : ?>
				<?php
				$sp_link = get_term_link( $sp_term );
				if ( is_wp_error( $sp_link ) ) {
					continue;
				}
				$sp_image = StoryPhone_Pages_Catalog::get_category_cover( $sp_term );
				?>
				<a
					class="sp-tile"
					href="<?php echo esc_url( $sp_link ); ?>"
					data-sp-reveal
					style="--sp-tile-hue: <?php echo esc_attr( (string) ( ( $sp_i * 47 ) % 360 ) ); ?>"
				>
					<span class="sp-tile__glow" aria-hidden="true"></span>

					<span class="sp-tile__media<?php echo $sp_image ? '' : ' sp-tile__media--empty'; ?>">
						<?php if ( $sp_image ) : ?>
							<img class="sp-tile__img" src="<?php echo esc_url( $sp_image ); ?>" alt="" loading="lazy" decoding="async">
						<?php else : ?>
							<span class="sp-tile__mono" aria-hidden="true"><?php echo esc_html( mb_substr( $sp_term->name, 0, 1 ) ); ?></span>
						<?php endif; ?>
					</span>

					<span class="sp-tile__body">
						<span class="sp-tile__name"><?php echo esc_html( $sp_term->name ); ?></span>
						<span class="sp-tile__count">
							<?php
							printf(
								/* translators: %s: number of products. */
								esc_html__( '%s פריטים', 'storyphone-pages' ),
								esc_html( number_format_i18n( (int) $sp_term->count ) )
							);
							?>
						</span>
					</span>

					<span class="sp-tile__arrow" aria-hidden="true">&#8592;</span>
				</a>
			<?php endforeach; ?>
		</div>

	</div>
</section>
