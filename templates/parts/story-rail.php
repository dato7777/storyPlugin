<?php
/**
 * Part: the Stories rail.
 *
 * Replaces the usual wall of dropdown menus. Each category is a tappable
 * bubble that opens an immersive, auto-advancing product story. Links are real
 * category URLs, so the rail still works (and is crawlable) without JavaScript.
 *
 * @package StoryPhone_Pages
 *
 * @var array<string, mixed> $args Expects 'stories' => array.
 */

defined( 'ABSPATH' ) || exit;

$sp_stories  = isset( $args['stories'] ) && is_array( $args['stories'] ) ? $args['stories'] : array();
$sp_title    = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
$sp_subtitle = isset( $args['subtitle'] ) ? trim( (string) $args['subtitle'] ) : '';
if ( empty( $sp_stories ) ) {
	return;
}
?>
<section class="sp-stories" id="sp-stories" aria-labelledby="sp-stories-title">
	<div class="sp-shell">

		<header class="sp-stories__head" data-sp-reveal>
			<div>
				<h2 class="sp-section__title" id="sp-stories-title">
					<?php echo esc_html( $sp_title ? $sp_title : __( 'הסטוריז של החנות', 'storyphone-pages' ) ); ?>
				</h2>
				<p class="sp-section__subtitle">
					<?php echo esc_html( $sp_subtitle ? $sp_subtitle : __( 'הקטגוריות שלנו, בלי תפריטים. לחצו על עיגול וצפו במה שיש בפנים.', 'storyphone-pages' ) ); ?>
				</p>
			</div>
			<p class="sp-stories__hint" aria-hidden="true">
				<?php esc_html_e( 'החליקו לצדדים', 'storyphone-pages' ); ?>
			</p>
		</header>

		<div class="sp-rail" data-sp-rail>
			<ul class="sp-rail__track" data-sp-rail-track>
				<?php foreach ( $sp_stories as $sp_index => $sp_story ) : ?>
					<li class="sp-rail__item">
						<a
							class="sp-bubble"
							href="<?php echo esc_url( $sp_story['url'] ); ?>"
							data-sp-story-open
							data-story-index="<?php echo esc_attr( (string) $sp_index ); ?>"
							data-story-id="<?php echo esc_attr( $sp_story['id'] ); ?>"
						>
							<span class="sp-bubble__ring">
								<span class="sp-bubble__inner">
									<?php if ( ! empty( $sp_story['cover'] ) ) : ?>
										<img
											class="sp-bubble__img"
											src="<?php echo esc_url( $sp_story['cover'] ); ?>"
											alt=""
											loading="lazy"
											decoding="async"
										>
									<?php else : ?>
										<span class="sp-bubble__fallback" aria-hidden="true">
											<?php echo esc_html( mb_substr( $sp_story['name'], 0, 1 ) ); ?>
										</span>
									<?php endif; ?>
								</span>
								<span class="sp-bubble__play" aria-hidden="true"></span>
							</span>
							<span class="sp-bubble__name"><?php echo esc_html( $sp_story['name'] ); ?></span>
							<span class="sp-bubble__count">
								<?php
								printf(
									/* translators: %s: number of products. */
									esc_html__( '%s פריטים', 'storyphone-pages' ),
									esc_html( number_format_i18n( (int) $sp_story['count'] ) )
								);
								?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<button
				type="button"
				class="sp-rail__nav sp-rail__nav--prev"
				data-sp-rail-prev
				aria-label="<?php esc_attr_e( 'הקודם', 'storyphone-pages' ); ?>"
			>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9.3 12 15 6.3 13.6 4.9 6.5 12l7.1 7.1L15 17.7 9.3 12Z"/></svg>
			</button>
			<button
				type="button"
				class="sp-rail__nav sp-rail__nav--next"
				data-sp-rail-next
				aria-label="<?php esc_attr_e( 'הבא', 'storyphone-pages' ); ?>"
			>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.7 12 9 17.7l1.4 1.4 7.1-7.1-7.1-7.1L9 6.3 14.7 12Z"/></svg>
			</button>
		</div>

	</div>
</section>
