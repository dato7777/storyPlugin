<?php
/**
 * Part: trust marquee.
 *
 * The list is duplicated once so the CSS animation can loop seamlessly; the
 * clone is hidden from assistive tech.
 *
 * @package StoryPhone_Pages
 */

defined( 'ABSPATH' ) || exit;

$sp_default_icon = 'M12 2 4 5.2v6.3c0 4.5 3.2 8.6 8 10.5 4.8-1.9 8-6 8-10.5V5.2L12 2Zm3.9 7.6-4.6 4.6a1 1 0 0 1-1.4 0L8 12.3l1.4-1.4 1.2 1.2 3.9-3.9 1.4 1.4Z';
$sp_items        = array(
	array(
		'title' => __( 'משלוח מהיר', 'storyphone-pages' ),
		'text'  => __( 'עד הבית, לכל הארץ', 'storyphone-pages' ),
		'icon'  => 'M3 7h11v8H3V7Zm11 3h3.2l2.8 3v2h-6v-5ZM6.5 20a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Zm10 0a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Z',
	),
	array(
		'title' => __( 'אחריות רשמית', 'storyphone-pages' ),
		'text'  => __( 'על כל המוצרים בחנות', 'storyphone-pages' ),
		'icon'  => $sp_default_icon,
	),
	array(
		'title' => __( 'החזרה תוך 14 יום', 'storyphone-pages' ),
		'text'  => __( 'בלי שאלות מיותרות', 'storyphone-pages' ),
		'icon'  => 'M12 4V1L7 5l5 4V6a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8Z',
	),
	array(
		'title' => __( 'תשלום מאובטח', 'storyphone-pages' ),
		'text'  => __( 'בכל כרטיסי האשראי', 'storyphone-pages' ),
		'icon'  => 'M4 6h16a1 1 0 0 1 1 1v2H3V7a1 1 0 0 1 1-1Zm-1 5h18v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6Zm3 3v2h5v-2H6Z',
	),
	array(
		'title' => __( 'יבוא רשמי', 'storyphone-pages' ),
		'text'  => __( 'בלי הפתעות, בלי אפור', 'storyphone-pages' ),
		'icon'  => 'M12 2 3 6v6c0 5 3.8 9.4 9 10 5.2-.6 9-5 9-10V6l-9-4Zm0 5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Zm0 11c-2 0-3.8-1-4.9-2.5.1-1.6 3.3-2.5 4.9-2.5s4.8.9 4.9 2.5A5.9 5.9 0 0 1 12 18Z',
	),
	array(
		'title' => __( 'תמיכה אנושית', 'storyphone-pages' ),
		'text'  => __( 'מדברים איתכם, לא בוט', 'storyphone-pages' ),
		'icon'  => 'M12 3a9 9 0 0 0-9 9v4.5A2.5 2.5 0 0 0 5.5 19H7a1 1 0 0 0 1-1v-5a1 1 0 0 0-1-1H5a7 7 0 0 1 14 0h-2a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h1.2a2.5 2.5 0 0 1-2.2 1.3H13v1.7h3a4.2 4.2 0 0 0 4-3.1A2.5 2.5 0 0 0 21 16.5V12a9 9 0 0 0-9-9Z',
	),
);

$sp_custom_items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
if ( ! empty( $sp_custom_items ) ) {
	$sp_items = array();
	foreach ( $sp_custom_items as $sp_row ) {
		if ( ! is_array( $sp_row ) ) {
			continue;
		}
		$sp_t = isset( $sp_row['title'] ) ? trim( (string) $sp_row['title'] ) : '';
		$sp_x = isset( $sp_row['text'] ) ? trim( (string) $sp_row['text'] ) : '';
		if ( '' === $sp_t && '' === $sp_x ) {
			continue;
		}
		$sp_items[] = array(
			'title' => $sp_t,
			'text'  => $sp_x,
			'icon'  => ! empty( $sp_row['icon'] ) ? (string) $sp_row['icon'] : $sp_default_icon,
		);
	}
	if ( empty( $sp_items ) ) {
		return;
	}
}

/**
 * Render one marquee run.
 *
 * @param array $items  Items.
 * @param bool  $hidden Whether this run is the duplicated, inert copy.
 * @return void
 */
$sp_render_run = static function ( array $items, $hidden = false ) {
	printf(
		'<ul class="sp-marquee__run"%s>',
		$hidden ? ' aria-hidden="true"' : ''
	);

	foreach ( $items as $item ) {
		?>
		<li class="sp-marquee__item">
			<span class="sp-marquee__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" focusable="false"><path d="<?php echo esc_attr( $item['icon'] ); ?>"/></svg>
			</span>
			<span class="sp-marquee__body">
				<strong class="sp-marquee__title"><?php echo esc_html( $item['title'] ); ?></strong>
				<span class="sp-marquee__text"><?php echo esc_html( $item['text'] ); ?></span>
			</span>
		</li>
		<?php
	}

	echo '</ul>';
};
?>
<section class="sp-marquee" aria-label="<?php esc_attr_e( 'למה לקנות אצלנו', 'storyphone-pages' ); ?>">
	<div class="sp-marquee__viewport">
		<div class="sp-marquee__track">
			<?php
			$sp_render_run( $sp_items );
			$sp_render_run( $sp_items, true );
			?>
		</div>
	</div>
</section>
