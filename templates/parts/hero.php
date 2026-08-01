<?php
/**
 * Part: cinematic hero.
 *
 * Search is the only search control on the page. The side stage hosts compact
 * child-category cards revealed by hovering a parent item in the header —
 * parents themselves are not links.
 *
 * @package StoryPhone_Pages
 *
 * @var array<string, mixed> $args Expects 'nav' => nav tree from Catalog::get_nav_tree().
 */

defined( 'ABSPATH' ) || exit;

$sp_nav      = isset( $args['nav'] ) && is_array( $args['nav'] ) ? $args['nav'] : array();
$sp_chips    = isset( $args['chips'] ) && is_array( $args['chips'] ) ? $args['chips'] : StoryPhone_Pages_Catalog::get_categories( 5 );
$sp_title    = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
$sp_subtitle = isset( $args['subtitle'] ) ? trim( (string) $args['subtitle'] ) : '';
?>
<section class="sp-hero">
	<div class="sp-aurora" aria-hidden="true">
		<span class="sp-aurora__blob sp-aurora__blob--1"></span>
		<span class="sp-aurora__blob sp-aurora__blob--2"></span>
		<span class="sp-aurora__blob sp-aurora__blob--3"></span>
	</div>
	<div class="sp-hero__grid-lines" aria-hidden="true"></div>
	<div class="sp-noise" aria-hidden="true"></div>

	<div class="sp-shell sp-hero__inner">

		<div class="sp-hero__copy">
			<p class="sp-eyebrow" data-sp-reveal>
				<span class="sp-eyebrow__item">
					<span class="sp-eyebrow__dot" aria-hidden="true"></span>
					<?php esc_html_e( 'יבוא רשמי', 'storyphone-pages' ); ?>
				</span>
				<span class="sp-eyebrow__item">
					<span class="sp-eyebrow__dot" aria-hidden="true"></span>
					<?php esc_html_e( 'אחריות מלאה', 'storyphone-pages' ); ?>
				</span>
				<span class="sp-eyebrow__item">
					<span class="sp-eyebrow__dot" aria-hidden="true"></span>
					<?php esc_html_e( 'משלוח מהיר לכל הארץ', 'storyphone-pages' ); ?>
				</span>
			</p>

			<?php if ( $sp_title ) : ?>
				<h1 class="sp-hero__title" data-sp-reveal>
					<span class="sp-hero__line sp-hero__line--accent"><?php echo esc_html( $sp_title ); ?></span>
				</h1>
			<?php else : ?>
				<h1 class="sp-hero__title" data-sp-reveal>
					<span class="sp-hero__line"><?php esc_html_e( 'לכל מכשיר', 'storyphone-pages' ); ?></span>
					<span class="sp-hero__line sp-hero__line--accent"><?php esc_html_e( 'יש סיפור.', 'storyphone-pages' ); ?></span>
					<span class="sp-hero__line sp-hero__line--sub"><?php esc_html_e( 'בואו נמצא את שלכם.', 'storyphone-pages' ); ?></span>
				</h1>
			<?php endif; ?>

			<p class="sp-hero__lede" data-sp-reveal>
				<?php
				if ( $sp_subtitle ) {
					echo esc_html( $sp_subtitle );
				} else {
					esc_html_e( 'אלפי מכשירים ואביזרים מקוריים. תגידו מה אתם מחפשים — ואנחנו נביא אתכם לזה בשלוש שניות.', 'storyphone-pages' );
				}
				?>
			</p>

			<button type="button" class="sp-heroSearch" data-sp-search-open data-sp-reveal>
				<svg class="sp-heroSearch__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M10.5 3a7.5 7.5 0 1 0 4.55 13.46l4.24 4.25 1.42-1.42-4.25-4.24A7.5 7.5 0 0 0 10.5 3Zm0 2a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11Z"/>
				</svg>
				<span class="sp-heroSearch__text">
					<span class="sp-heroSearch__static"><?php esc_html_e( 'חיפוש', 'storyphone-pages' ); ?></span>
					<span class="sp-heroSearch__typer" data-sp-typer aria-hidden="true"></span>
				</span>
				<span class="sp-heroSearch__go"><?php esc_html_e( 'התחילו', 'storyphone-pages' ); ?></span>
			</button>

			<?php if ( ! empty( $sp_chips ) ) : ?>
				<div class="sp-hero__chips" data-sp-reveal>
					<span class="sp-hero__chipsLabel"><?php esc_html_e( 'פופולרי:', 'storyphone-pages' ); ?></span>
					<?php foreach ( $sp_chips as $sp_chip ) : ?>
						<?php
						$sp_chip_link = get_term_link( $sp_chip );
						if ( is_wp_error( $sp_chip_link ) ) {
							continue;
						}
						?>
						<a class="sp-chip" href="<?php echo esc_url( $sp_chip_link ); ?>">
							<?php echo esc_html( $sp_chip->name ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<aside
			class="sp-hero__stage"
			data-sp-nav-stage
			data-sp-reveal
			aria-live="polite"
			aria-label="<?php esc_attr_e( 'קטגוריות משנה', 'storyphone-pages' ); ?>"
		>
			<div class="sp-navStage is-idle is-active" data-sp-nav-idle>
				<div class="sp-navStage__idle">
					<span class="sp-navStage__idleMark" aria-hidden="true"></span>
					<p class="sp-navStage__idleTitle"><?php esc_html_e( 'גלו קטגוריה', 'storyphone-pages' ); ?></p>
					<p class="sp-navStage__idleHint"><?php esc_html_e( 'רחפו מעל פריט בתפריט — תת-הקטגוריות יופיעו כאן', 'storyphone-pages' ); ?></p>
				</div>
			</div>

			<?php foreach ( $sp_nav as $sp_entry ) : ?>
				<?php
				if ( empty( $sp_entry['term'] ) || ! $sp_entry['term'] instanceof WP_Term ) {
					continue;
				}
				$sp_parent   = $sp_entry['term'];
				$sp_children = isset( $sp_entry['children'] ) && is_array( $sp_entry['children'] ) ? $sp_entry['children'] : array();
				$sp_panel_id = 'cat-' . (int) $sp_parent->term_id;
				?>
				<div class="sp-navStage" data-sp-nav-panel="<?php echo esc_attr( $sp_panel_id ); ?>" hidden>
					<p class="sp-navStage__label"><?php echo esc_html( $sp_parent->name ); ?></p>
					<div class="sp-navCards">
						<?php if ( ! empty( $sp_children ) ) : ?>
							<?php foreach ( $sp_children as $sp_child ) : ?>
								<?php
								if ( ! $sp_child instanceof WP_Term ) {
									continue;
								}
								$sp_child_link = get_term_link( $sp_child );
								if ( is_wp_error( $sp_child_link ) ) {
									continue;
								}
								$sp_cover = StoryPhone_Pages_Catalog::get_category_cover( $sp_child, 'woocommerce_thumbnail' );
								$sp_mono  = function_exists( 'mb_substr' )
									? mb_substr( $sp_child->name, 0, 1 )
									: substr( $sp_child->name, 0, 1 );
								?>
								<a class="sp-navCard" href="<?php echo esc_url( $sp_child_link ); ?>" data-sp-tilt>
									<span class="sp-navCard__glow" aria-hidden="true"></span>
									<span class="sp-navCard__shine" aria-hidden="true"></span>
									<span class="sp-navCard__media<?php echo '' === $sp_cover ? ' sp-navCard__media--empty' : ''; ?>">
										<?php if ( '' !== $sp_cover ) : ?>
											<img class="sp-navCard__img" src="<?php echo esc_url( $sp_cover ); ?>" alt="" loading="lazy" decoding="async">
										<?php else : ?>
											<span class="sp-navCard__mono" aria-hidden="true"><?php echo esc_html( $sp_mono ); ?></span>
										<?php endif; ?>
									</span>
									<span class="sp-navCard__body">
										<span class="sp-navCard__name"><?php echo esc_html( $sp_child->name ); ?></span>
									</span>
								</a>
							<?php endforeach; ?>
						<?php else : ?>
							<?php
							// Leaf parents still need a useful panel: show a few
							// products from the category in the same compact card skin.
							$sp_leaf_products = StoryPhone_Pages_Catalog::get_category_products( $sp_parent, 4 );
							foreach ( $sp_leaf_products as $sp_leaf ) :
								$sp_leaf_link = get_permalink( $sp_leaf->get_id() );
								$sp_leaf_link = $sp_leaf_link ? $sp_leaf_link : home_url( '/' );
								$sp_leaf_img  = StoryPhone_Pages_Catalog::get_product_image_url( $sp_leaf, 'woocommerce_thumbnail' );
								?>
								<a class="sp-navCard" href="<?php echo esc_url( $sp_leaf_link ); ?>" data-sp-tilt>
									<span class="sp-navCard__glow" aria-hidden="true"></span>
									<span class="sp-navCard__shine" aria-hidden="true"></span>
									<span class="sp-navCard__media">
										<?php if ( '' !== $sp_leaf_img ) : ?>
											<img class="sp-navCard__img" src="<?php echo esc_url( $sp_leaf_img ); ?>" alt="" loading="lazy" decoding="async">
										<?php endif; ?>
									</span>
									<span class="sp-navCard__body">
										<span class="sp-navCard__name"><?php echo esc_html( $sp_leaf->get_name() ); ?></span>
									</span>
								</a>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</aside>

	</div>

	<a class="sp-hero__scroll" href="#sp-stories" aria-label="<?php esc_attr_e( 'גלילה לסטוריז', 'storyphone-pages' ); ?>">
		<span class="sp-hero__scrollDot" aria-hidden="true"></span>
	</a>
</section>
