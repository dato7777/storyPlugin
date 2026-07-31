<?php
/**
 * StoryPhone — Home (Design-aware override from Inventory Manager).
 *
 * Uses storyphone-pages render/parts/assets, but builds the navbar and
 * section content from Inventory Manager Design settings.
 *
 * @package StoryPhone_Inventory_Manager
 */

defined( 'ABSPATH' ) || exit;

$sp_home    = StoryPhone_IM_Storefront_Design::resolve_home_data();
$sp_nav     = $sp_home['nav'];
$sp_stories = $sp_home['stories'];
$sp_hot     = $sp_home['hot'];
$sp_showcase = $sp_home['showcase'];
$sp_families = $sp_home['families'];
$sp_deal    = $sp_home['deal'];
$sp_pick    = $sp_home['pick'];
$sp_chips   = $sp_home['chips'];
$sp_sections = $sp_home['sections'];
$sp_content = $sp_home['section_content'];
$sp_nav_meta = $sp_home['nav_meta'];

/**
 * Content bag for a section id.
 *
 * @param string $id Section slug.
 * @return array<string, mixed>
 */
$sp_sc = static function ( $id ) use ( $sp_content ) {
	return ( isset( $sp_content[ $id ] ) && is_array( $sp_content[ $id ] ) ) ? $sp_content[ $id ] : array();
};

/**
 * Render one homepage section by id.
 *
 * @param string $sp_section_id Section slug.
 * @return void
 */
$sp_render_section = static function ( $sp_section_id ) use ( $sp_nav, $sp_stories, $sp_pick, $sp_deal, $sp_families, $sp_hot, $sp_showcase, $sp_chips, $sp_sc ) {
	$c = $sp_sc( $sp_section_id );
	switch ( $sp_section_id ) {
		case 'hero':
			StoryPhone_Pages_Render::part(
				'hero',
				array(
					'nav'      => $sp_nav,
					'chips'    => $sp_chips,
					'title'    => isset( $c['title'] ) ? $c['title'] : '',
					'subtitle' => isset( $c['subtitle'] ) ? $c['subtitle'] : '',
				)
			);
			break;
		case 'story-rail':
			StoryPhone_Pages_Render::part(
				'story-rail',
				array(
					'stories'  => $sp_stories,
					'title'    => isset( $c['title'] ) ? $c['title'] : '',
					'subtitle' => isset( $c['subtitle'] ) ? $c['subtitle'] : '',
				)
			);
			break;
		case 'pick-deck':
			StoryPhone_Pages_Render::part(
				'pick-deck',
				array(
					'product'  => $sp_pick,
					'deal'     => $sp_deal,
					'title'    => isset( $c['title'] ) ? $c['title'] : '',
					'subtitle' => isset( $c['subtitle'] ) ? $c['subtitle'] : '',
				)
			);
			break;
		case 'quick-reach':
			StoryPhone_Pages_Render::part(
				'quick-reach',
				array(
					'categories' => $sp_families,
					'title'      => isset( $c['title'] ) ? $c['title'] : '',
					'subtitle'   => isset( $c['subtitle'] ) ? $c['subtitle'] : '',
				)
			);
			break;
		case 'heat-board':
			StoryPhone_Pages_Render::part(
				'heat-board',
				array(
					'products' => $sp_hot,
					'title'    => isset( $c['title'] ) ? $c['title'] : '',
					'subtitle' => isset( $c['subtitle'] ) ? $c['subtitle'] : '',
				)
			);
			break;
		case 'showcase':
			StoryPhone_Pages_Render::part(
				'showcase',
				array(
					'products' => $sp_showcase,
					'title'    => ! empty( $c['title'] ) ? $c['title'] : __( 'נבחרת הבית', 'storyphone-pages' ),
					'subtitle' => ! empty( $c['subtitle'] ) ? $c['subtitle'] : __( 'המכשירים והאביזרים שאנחנו עומדים מאחוריהם', 'storyphone-pages' ),
				)
			);
			break;
		case 'deal':
			StoryPhone_Pages_Render::part( 'deal', array( 'product' => $sp_deal ) );
			break;
		case 'trust':
			StoryPhone_Pages_Render::part(
				'trust',
				array(
					'title' => isset( $c['title'] ) ? $c['title'] : '',
					'items' => isset( $c['items'] ) && is_array( $c['items'] ) ? $c['items'] : array(),
				)
			);
			break;
		case 'editor-content':
			StoryPhone_Pages_Render::part( 'editor-content' );
			break;
		case 'cta':
			StoryPhone_Pages_Render::part(
				'cta',
				array(
					'title'        => isset( $c['title'] ) ? $c['title'] : '',
					'text'         => isset( $c['text'] ) ? $c['text'] : '',
					'button_label' => isset( $c['button_label'] ) ? $c['button_label'] : '',
					'button_url'   => isset( $c['button_url'] ) ? $c['button_url'] : '',
				)
			);
			break;
	}
};

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#07091a">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'sp-page sp-page--home' ); ?>>
<?php wp_body_open(); ?>

<!-- sp-nav mode=<?php echo esc_html( $sp_nav_meta['mode'] ); ?> count=<?php echo esc_html( (string) (int) $sp_nav_meta['count'] ); ?> ids=<?php echo esc_html( implode( ',', $sp_nav_meta['ids'] ) ); ?> via=im-override -->

<a class="sp-skip" href="#sp-main"><?php esc_html_e( 'דלג לתוכן הראשי', 'storyphone-pages' ); ?></a>

<?php StoryPhone_Pages_Render::part( 'site-header', array( 'nav' => $sp_nav ) ); ?>

<main id="sp-main" class="sp-main">

	<?php foreach ( $sp_sections as $sp_section_id ) : ?>
		<?php $sp_render_section( $sp_section_id ); ?>
	<?php endforeach; ?>

</main>

<?php StoryPhone_Pages_Render::part( 'site-footer' ); ?>

<?php StoryPhone_Pages_Render::part( 'story-viewer' ); ?>
<?php StoryPhone_Pages_Render::part( 'command-palette' ); ?>
<?php StoryPhone_Pages_Render::part( 'cart-drawer' ); ?>

<?php StoryPhone_Pages_Stories::print_json( $sp_stories ); ?>

<?php wp_footer(); ?>
</body>
</html>
