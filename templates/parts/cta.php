<?php
/**
 * Part: closing call to action.
 *
 * @package StoryPhone_Pages
 */

defined( 'ABSPATH' ) || exit;

$sp_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$sp_title    = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
$sp_text     = isset( $args['text'] ) ? trim( (string) $args['text'] ) : '';
$sp_label    = isset( $args['button_label'] ) ? trim( (string) $args['button_label'] ) : '';
$sp_url      = isset( $args['button_url'] ) ? trim( (string) $args['button_url'] ) : '';
if ( $sp_url ) {
	$sp_shop_url = $sp_url;
}
?>
<section class="sp-cta">
	<div class="sp-shell">
		<div class="sp-cta__panel" data-sp-reveal>
			<div class="sp-cta__copy">
				<h2 class="sp-cta__title"><?php echo esc_html( $sp_title ? $sp_title : __( 'לא בטוחים מה מתאים לכם?', 'storyphone-pages' ) ); ?></h2>
				<p class="sp-cta__text">
					<?php echo esc_html( $sp_text ? $sp_text : __( 'ספרו לנו מה אתם מחפשים ואיזה תקציב יש לכם, ונמצא לכם את המכשיר הנכון. בלי לחץ ובלי שטויות.', 'storyphone-pages' ) ); ?>
				</p>
			</div>
			<div class="sp-cta__actions">
				<a class="sp-btn sp-btn--primary sp-btn--lg" href="<?php echo esc_url( $sp_shop_url ); ?>">
					<?php echo esc_html( $sp_label ? $sp_label : __( 'להתחיל לבחור', 'storyphone-pages' ) ); ?>
				</a>
			</div>
		</div>
	</div>
</section>
