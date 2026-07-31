<?php
/**
 * Force Design settings onto the StoryPhone Home template.
 *
 * storyphone-pages renders the homepage. If that plugin is outdated on staging,
 * Catalog::get_nav_tree() ignores Design. This class overrides template_include
 * (priority 1000) and builds the navbar from the storyphone_design option
 * directly — so deploying Inventory Manager alone is enough for navbar Design.
 *
 * @package StoryPhone_Inventory_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Storefront Design bridge.
 */
class StoryPhone_IM_Storefront_Design {

	/**
	 * Hook registrations.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'override_home_template' ), 1000 );
		add_action( 'wp_footer', array( __CLASS__, 'admin_debug_badge' ), 5 );
	}

	/**
	 * Take over StoryPhone — Home so Design nav/sections always apply.
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	public static function override_home_template( $template ) {
		if ( ! is_page() ) {
			return $template;
		}

		$slug = (string) get_page_template_slug( get_queried_object_id() );
		if ( 'storyphone-home' !== $slug ) {
			return $template;
		}

		// Need pages plugin for parts/assets/render helpers.
		if ( ! class_exists( 'StoryPhone_Pages_Render' ) || ! class_exists( 'StoryPhone_Pages_Catalog' ) ) {
			return $template;
		}

		$path = STORYPHONE_IM_PLUGIN_DIR . 'templates/storefront-home.php';
		return file_exists( $path ) ? $path : $template;
	}

	/**
	 * Build header nav tree from Design (DB-fresh) or fall back to pages catalog.
	 *
	 * @param int $limit Max items.
	 * @return array<int, array{term: WP_Term, children: WP_Term[]}>
	 */
	public static function get_nav_tree( $limit = 9 ) {
		$limit  = max( 1, absint( $limit ) );
		$config = self::read_design_nav_fresh();

		if ( ! empty( $config['custom'] ) ) {
			$tree = array();
			foreach ( $config['ids'] as $term_id ) {
				if ( count( $tree ) >= $limit ) {
					break;
				}
				$term = get_term( (int) $term_id, 'product_cat' );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				$tree[] = array(
					'term'     => $term,
					'children' => self::get_child_categories( $term, 6 ),
				);
			}
			return $tree;
		}

		if ( class_exists( 'StoryPhone_Pages_Catalog' ) ) {
			return StoryPhone_Pages_Catalog::get_nav_tree( $limit );
		}

		return array();
	}

	/**
	 * Meta for the admin debug badge.
	 *
	 * @return array{mode:string,count:int,ids:int[],override:bool}
	 */
	public static function get_nav_debug_meta() {
		$config = self::read_design_nav_fresh();
		$custom = ! empty( $config['custom'] );
		$ids    = $custom ? array_slice( $config['ids'], 0, 9 ) : array();
		$tree   = self::get_nav_tree( 9 );
		$count  = count( $tree );
		$out_ids = array();
		foreach ( $tree as $row ) {
			if ( ! empty( $row['term'] ) && $row['term'] instanceof WP_Term ) {
				$out_ids[] = (int) $row['term']->term_id;
			}
		}

		return array(
			'mode'     => $custom ? 'custom' : 'auto',
			'count'    => $count,
			'ids'      => $out_ids,
			'override' => true,
			'saved'    => $ids,
		);
	}

	/**
	 * Floating badge for shop managers so Design wiring is obvious on preview.
	 *
	 * @return void
	 */
	public static function admin_debug_badge() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! is_page() ) {
			return;
		}
		if ( 'storyphone-home' !== (string) get_page_template_slug( get_queried_object_id() ) ) {
			return;
		}

		$meta  = self::get_nav_debug_meta();
		$label = sprintf(
			'StoryPhone Design nav: %s · %d items (IM override)',
			$meta['mode'],
			(int) $meta['count']
		);
		echo '<div id="storyphone-im-nav-debug" style="position:fixed;z-index:999999;left:12px;bottom:12px;padding:10px 14px;border-radius:12px;background:#0f172a;color:#f8fafc;font:650 12px/1.35 system-ui,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.35);max-width:min(420px,calc(100vw - 24px));">';
		echo esc_html( $label );
		echo '<div style="opacity:.75;margin-top:4px;font-weight:500;">';
		echo esc_html( 'ids: ' . ( $meta['ids'] ? implode( ',', $meta['ids'] ) : '—' ) );
		echo '</div></div>';
	}

	/**
	 * Fresh Design nav config from DB.
	 *
	 * @return array{custom:bool,ids:int[]}
	 */
	private static function read_design_nav_fresh() {
		$data = StoryPhone_IM_Design::read_option_fresh();
		if ( ! is_array( $data ) || empty( $data ) ) {
			return array(
				'custom' => false,
				'ids'    => array(),
			);
		}

		$home = isset( $data['pages']['home'] ) && is_array( $data['pages']['home'] ) ? $data['pages']['home'] : array();
		$ids  = array();
		if ( isset( $home['nav_category_ids'] ) && is_array( $home['nav_category_ids'] ) ) {
			$ids = array_values( array_filter( array_map( 'absint', $home['nav_category_ids'] ) ) );
		}

		return array(
			'custom' => ! empty( $home['nav_custom'] ) || ! empty( $ids ),
			'ids'    => $ids,
		);
	}

	/**
	 * Resolve homepage catalog + Design section_content for templates.
	 *
	 * Empty Design picks keep the automatic catalog queries.
	 * Product/category ID helpers fall back locally so an outdated
	 * storyphone-pages plugin cannot fatal the homepage.
	 *
	 * @return array<string, mixed>
	 */
	public static function resolve_home_data() {
		$stories   = StoryPhone_Pages_Stories::build( 10, 6 );
		$hot       = StoryPhone_Pages_Catalog::get_hot_products( 6 );
		$showcase  = StoryPhone_Pages_Catalog::get_showcase_products( 8 );
		$families  = StoryPhone_Pages_Catalog::get_categories( 8, true );
		$deal      = StoryPhone_Pages_Catalog::get_deal_product();
		$nav       = self::get_nav_tree( 9 );
		$pick      = ! empty( $hot ) ? $hot[0] : ( ! empty( $showcase ) ? $showcase[0] : null );
		$chips     = StoryPhone_Pages_Catalog::get_categories( 5 );
		$content   = class_exists( 'StoryPhone_IM_Design' )
			? StoryPhone_IM_Design::get_all_section_content()
			: array();

		$hero = isset( $content['hero'] ) && is_array( $content['hero'] ) ? $content['hero'] : array();
		if ( ! empty( $hero['chip_category_ids'] ) ) {
			$custom_chips = self::resolve_categories_by_ids( $hero['chip_category_ids'], 8 );
			if ( ! empty( $custom_chips ) ) {
				$chips = $custom_chips;
			}
		}

		$story_c = isset( $content['story-rail'] ) && is_array( $content['story-rail'] ) ? $content['story-rail'] : array();
		if ( ! empty( $story_c['category_ids'] ) ) {
			$custom_stories = self::resolve_stories_from_category_ids( $story_c['category_ids'], 6 );
			if ( ! empty( $custom_stories ) ) {
				$stories = $custom_stories;
			}
		}

		$pick_c = isset( $content['pick-deck'] ) && is_array( $content['pick-deck'] ) ? $content['pick-deck'] : array();
		if ( ! empty( $pick_c['product_id'] ) ) {
			$custom_pick = self::resolve_product_by_id( $pick_c['product_id'] );
			if ( $custom_pick ) {
				$pick = $custom_pick;
			}
		}

		$reach_c = isset( $content['quick-reach'] ) && is_array( $content['quick-reach'] ) ? $content['quick-reach'] : array();
		if ( ! empty( $reach_c['category_ids'] ) ) {
			$custom_families = self::resolve_categories_by_ids( $reach_c['category_ids'], 12 );
			if ( ! empty( $custom_families ) ) {
				$families = $custom_families;
			}
		}

		$heat_c = isset( $content['heat-board'] ) && is_array( $content['heat-board'] ) ? $content['heat-board'] : array();
		if ( ! empty( $heat_c['product_ids'] ) ) {
			$custom_hot = self::resolve_products_by_ids( $heat_c['product_ids'], 12 );
			if ( ! empty( $custom_hot ) ) {
				$hot = $custom_hot;
			}
		}

		$show_c = isset( $content['showcase'] ) && is_array( $content['showcase'] ) ? $content['showcase'] : array();
		if ( ! empty( $show_c['product_ids'] ) ) {
			$custom_show = self::resolve_products_by_ids( $show_c['product_ids'], 12 );
			if ( ! empty( $custom_show ) ) {
				$showcase = $custom_show;
			}
		}

		$deal_c = isset( $content['deal'] ) && is_array( $content['deal'] ) ? $content['deal'] : array();
		if ( ! empty( $deal_c['product_id'] ) ) {
			$custom_deal = self::resolve_product_by_id( $deal_c['product_id'] );
			if ( $custom_deal ) {
				$deal = $custom_deal;
			}
		}

		$sections = array(
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
				$sections = $configured;
			}
		}

		return array(
			'nav'             => $nav,
			'stories'         => $stories,
			'hot'             => $hot,
			'showcase'        => $showcase,
			'families'        => $families,
			'deal'            => $deal,
			'pick'            => $pick,
			'chips'           => $chips,
			'sections'        => $sections,
			'section_content' => $content,
			'nav_meta'        => self::get_nav_debug_meta(),
		);
	}

	/**
	 * Visible WC product by ID (Catalog helper or local fallback).
	 *
	 * @param int $id Product ID.
	 * @return WC_Product|null
	 */
	private static function resolve_product_by_id( $id ) {
		$id = absint( $id );
		if ( ! $id || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		if ( method_exists( 'StoryPhone_Pages_Catalog', 'get_product_by_id' ) ) {
			$product = StoryPhone_Pages_Catalog::get_product_by_id( $id );
			return $product instanceof WC_Product ? $product : null;
		}

		$product = wc_get_product( $id );
		if ( $product instanceof WC_Product && $product->is_visible() ) {
			return $product;
		}

		return null;
	}

	/**
	 * Visible products by IDs (Catalog helper or local fallback).
	 *
	 * @param int[] $ids   Product IDs.
	 * @param int   $limit Max.
	 * @return WC_Product[]
	 */
	private static function resolve_products_by_ids( array $ids, $limit = 12 ) {
		if ( method_exists( 'StoryPhone_Pages_Catalog', 'get_products_by_ids' ) ) {
			return StoryPhone_Pages_Catalog::get_products_by_ids( $ids, $limit );
		}

		$limit = max( 1, absint( $limit ) );
		$out   = array();
		foreach ( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) as $id ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$product = self::resolve_product_by_id( $id );
			if ( $product ) {
				$out[] = $product;
			}
		}
		return $out;
	}

	/**
	 * Categories by IDs (Catalog helper or local fallback).
	 *
	 * @param int[] $ids   Term IDs.
	 * @param int   $limit Max.
	 * @return WP_Term[]
	 */
	private static function resolve_categories_by_ids( array $ids, $limit = 12 ) {
		if ( method_exists( 'StoryPhone_Pages_Catalog', 'get_categories_by_ids' ) ) {
			return StoryPhone_Pages_Catalog::get_categories_by_ids( $ids, $limit );
		}

		$limit = max( 1, absint( $limit ) );
		$out   = array();
		foreach ( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) as $term_id ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$term = get_term( (int) $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$out[] = $term;
			}
		}
		return $out;
	}

	/**
	 * Stories from category IDs (Stories helper or local fallback).
	 *
	 * @param int[] $category_ids Term IDs.
	 * @param int   $per_story    Slides per story.
	 * @return array<int, array<string, mixed>>
	 */
	private static function resolve_stories_from_category_ids( array $category_ids, $per_story = 6 ) {
		if ( method_exists( 'StoryPhone_Pages_Stories', 'build_from_category_ids' ) ) {
			return StoryPhone_Pages_Stories::build_from_category_ids( $category_ids, $per_story );
		}

		// Outdated pages plugin: keep auto stories rather than fatal.
		return array();
	}

	/**
	 * Child categories for a parent term.
	 *
	 * @param WP_Term $parent Parent.
	 * @param int     $limit  Max children.
	 * @return WP_Term[]
	 */
	private static function get_child_categories( $parent, $limit = 6 ) {
		if ( class_exists( 'StoryPhone_Pages_Catalog' ) && method_exists( 'StoryPhone_Pages_Catalog', 'get_child_categories' ) ) {
			return StoryPhone_Pages_Catalog::get_child_categories( $parent, $limit );
		}

		if ( ! $parent instanceof WP_Term || ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => (int) $parent->term_id,
				'hide_empty' => true,
				'number'     => max( 1, absint( $limit ) ),
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		return ( is_wp_error( $terms ) || ! is_array( $terms ) ) ? array() : $terms;
	}
}
