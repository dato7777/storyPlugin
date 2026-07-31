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
		global $wpdb;

		wp_cache_delete( StoryPhone_IM_Design::OPTION_KEY, 'options' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				StoryPhone_IM_Design::OPTION_KEY
			)
		);

		if ( null === $row || false === $row ) {
			return array(
				'custom' => false,
				'ids'    => array(),
			);
		}

		$data = maybe_unserialize( $row );
		if ( ! is_array( $data ) ) {
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
