<?php
/**
 * StoryPhone — Home (Design-aware override from Inventory Manager).
 *
 * Uses storyphone-pages render/parts/assets, but builds the navbar from
 * Inventory Manager Design settings so an outdated pages Catalog cannot
 * force the automatic top-9 list.
 *
 * @package StoryPhone_Inventory_Manager
 */

defined( 'ABSPATH' ) || exit;

$sp_stories  = StoryPhone_Pages_Stories::build( 10, 6 );
$sp_hot      = StoryPhone_Pages_Catalog::get_hot_products( 6 );
$sp_showcase = StoryPhone_Pages_Catalog::get_showcase_products( 8 );
$sp_families = StoryPhone_Pages_Catalog::get_categories( 8, true );
$sp_deal     = StoryPhone_Pages_Catalog::get_deal_product();
$sp_nav      = StoryPhone_IM_Storefront_Design::get_nav_tree( 9 );
$sp_pick     = ! empty( $sp_hot ) ? $sp_hot[0] : ( ! empty( $sp_showcase ) ? $sp_showcase[0] : null );
$sp_nav_meta = StoryPhone_IM_Storefront_Design::get_nav_debug_meta();

$sp_sections = array(
	'hero',
	'story-rail',
	'pick-deck',
	'quick-reach',
	'heat-board',
	'showcase',
	'deal',
	'trust',
	'editor-content',
	'cta',
);

if ( class_exists( 'StoryPhone_IM_Design' ) ) {
	$configured = StoryPhone_IM_Design::get_enabled_home_sections();
	if ( ! empty( $configured ) ) {
		$sp_sections = $configured;
	}
}

/**
 * Render one homepage section by id.
 *
 * @param string $sp_section_id Section slug.
 * @return void
 */
$sp_render_section = static function ( $sp_section_id ) use ( $sp_nav, $sp_stories, $sp_pick, $sp_deal, $sp_families, $sp_hot, $sp_showcase ) {
	switch ( $sp_section_id ) {
		case 'hero':
			StoryPhone_Pages_Render::part( 'hero', array( 'nav' => $sp_nav ) );
			break;
		case 'story-rail':
			StoryPhone_Pages_Render::part( 'story-rail', array( 'stories' => $sp_stories ) );
			break;
		case 'pick-deck':
			StoryPhone_Pages_Render::part(
				'pick-deck',
				array(
					'product' => $sp_pick,
					'deal'    => $sp_deal,
				)
			);
			break;
		case 'quick-reach':
			StoryPhone_Pages_Render::part( 'quick-reach', array( 'categories' => $sp_families ) );
			break;
		case 'heat-board':
			StoryPhone_Pages_Render::part( 'heat-board', array( 'products' => $sp_hot ) );
			break;
		case 'showcase':
			StoryPhone_Pages_Render::part(
				'showcase',
				array(
					'products' => $sp_showcase,
					'title'    => __( 'נבחרת הבית', 'storyphone-pages' ),
					'subtitle' => __( 'המכשירים והאביזרים שאנחנו עומדים מאחוריהם', 'storyphone-pages' ),
				)
			);
			break;
		case 'deal':
			StoryPhone_Pages_Render::part( 'deal', array( 'product' => $sp_deal ) );
			break;
		case 'trust':
			StoryPhone_Pages_Render::part( 'trust' );
			break;
		case 'editor-content':
			StoryPhone_Pages_Render::part( 'editor-content' );
			break;
		case 'cta':
			StoryPhone_Pages_Render::part( 'cta' );
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
