<?php
/**
 * Design studio REST + settings for storefront pages.
 *
 * Option key: storyphone_design
 *
 * @package StoryPhone_Inventory_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Design configuration API.
 */
class StoryPhone_IM_Design {

	const OPTION_KEY = 'storyphone_design';

	/**
	 * Default homepage section stack (top → bottom).
	 *
	 * @return array<int, array{id:string,label:string,enabled:bool}>
	 */
	public static function default_home_sections() {
		return array(
			array(
				'id'      => 'hero',
				'label'   => 'Hero',
				'enabled' => true,
			),
			array(
				'id'      => 'story-rail',
				'label'   => 'Stories rail',
				'enabled' => true,
			),
			array(
				'id'      => 'pick-deck',
				'label'   => 'Pick deck',
				'enabled' => true,
			),
			array(
				'id'      => 'quick-reach',
				'label'   => 'Quick reach categories',
				'enabled' => true,
			),
			array(
				'id'      => 'heat-board',
				'label'   => 'Heat board (hot products)',
				'enabled' => true,
			),
			array(
				'id'      => 'showcase',
				'label'   => 'Showcase grid',
				'enabled' => true,
			),
			array(
				'id'      => 'deal',
				'label'   => 'Deal highlight',
				'enabled' => true,
			),
			array(
				'id'      => 'trust',
				'label'   => 'Trust strip',
				'enabled' => true,
			),
			array(
				'id'      => 'editor-content',
				'label'   => 'Page editor content',
				'enabled' => true,
			),
			array(
				'id'      => 'cta',
				'label'   => 'Call to action',
				'enabled' => true,
			),
		);
	}

	/**
	 * Read stored design settings with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$pages = isset( $raw['pages'] ) && is_array( $raw['pages'] ) ? $raw['pages'] : array();
		$home  = isset( $pages['home'] ) && is_array( $pages['home'] ) ? $pages['home'] : array();

		$nav_ids = array();
		if ( isset( $home['nav_category_ids'] ) && is_array( $home['nav_category_ids'] ) ) {
			$nav_ids = array_values( array_filter( array_map( 'absint', $home['nav_category_ids'] ) ) );
		}

		// Once Design has been saved, never silently fall back to auto nav.
		// Also treat a non-empty ID list as custom (older saves before nav_custom).
		$nav_custom = ! empty( $home['nav_custom'] ) || ! empty( $nav_ids );

		$sections = self::merge_sections(
			isset( $home['sections'] ) && is_array( $home['sections'] ) ? $home['sections'] : array()
		);

		return array(
			'pages' => array(
				'home' => array(
					'nav_category_ids' => $nav_ids,
					'nav_custom'       => $nav_custom,
					'sections'         => $sections,
				),
			),
		);
	}

	/**
	 * Merge saved section flags with the canonical ordered list.
	 *
	 * @param array $saved Saved sections.
	 * @return array
	 */
	private static function merge_sections( $saved ) {
		$by_id = array();
		foreach ( $saved as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			$by_id[ sanitize_key( $row['id'] ) ] = ! empty( $row['enabled'] );
		}

		// Preserve custom order if provided; append any missing defaults.
		$ordered = array();
		$seen    = array();
		foreach ( $saved as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			$id = sanitize_key( $row['id'] );
			foreach ( self::default_home_sections() as $def ) {
				if ( $def['id'] === $id ) {
					$ordered[] = array(
						'id'      => $id,
						'label'   => $def['label'],
						'enabled' => array_key_exists( $id, $by_id ) ? (bool) $by_id[ $id ] : true,
					);
					$seen[ $id ] = true;
					break;
				}
			}
		}

		foreach ( self::default_home_sections() as $def ) {
			if ( isset( $seen[ $def['id'] ] ) ) {
				continue;
			}
			$ordered[] = array(
				'id'      => $def['id'],
				'label'   => $def['label'],
				'enabled' => array_key_exists( $def['id'], $by_id ) ? (bool) $by_id[ $def['id'] ] : true,
			);
		}

		return $ordered;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			StoryPhone_IM_REST_Controller::NAMESPACE,
			'/design/pages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_pages' ),
				'permission_callback' => array( 'StoryPhone_IM_REST_Controller', 'check_permissions' ),
			)
		);

		register_rest_route(
			StoryPhone_IM_REST_Controller::NAMESPACE,
			'/design/page/(?P<key>[a-z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_page_design' ),
					'permission_callback' => array( 'StoryPhone_IM_REST_Controller', 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_page_design' ),
					'permission_callback' => array( 'StoryPhone_IM_REST_Controller', 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * List editable storefront pages.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_pages() {
		$front_id = (int) get_option( 'page_on_front' );
		$shop_id  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;

		$pages = array(
			array(
				'key'         => 'home',
				'title'       => __( 'Homepage', 'storyphone-inventory-manager' ),
				'description' => __( 'Navbar items and homepage sections from top to bottom.', 'storyphone-inventory-manager' ),
				'wp_page_id'  => $front_id,
				'url'         => home_url( '/' ),
				'badge'       => __( 'Live storefront', 'storyphone-inventory-manager' ),
			),
		);

		if ( $shop_id > 0 ) {
			$shop = get_post( $shop_id );
			if ( $shop ) {
				$pages[] = array(
					'key'         => 'shop',
					'title'       => $shop->post_title ? $shop->post_title : __( 'Shop', 'storyphone-inventory-manager' ),
					'description' => __( 'WooCommerce shop archive (coming next — structure preview).', 'storyphone-inventory-manager' ),
					'wp_page_id'  => $shop_id,
					'url'         => get_permalink( $shop_id ),
					'badge'       => __( 'Shop', 'storyphone-inventory-manager' ),
					'readonly'    => true,
				);
			}
		}

		$wp_pages = get_pages(
			array(
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
				'post_status' => 'publish',
			)
		);

		foreach ( $wp_pages as $page ) {
			if ( (int) $page->ID === $front_id || (int) $page->ID === $shop_id ) {
				continue;
			}
			$pages[] = array(
				'key'         => 'page-' . (int) $page->ID,
				'title'       => $page->post_title,
				'description' => __( 'WordPress page — open in WP editor for full content.', 'storyphone-inventory-manager' ),
				'wp_page_id'  => (int) $page->ID,
				'url'         => get_permalink( $page->ID ),
				'edit_url'    => get_edit_post_link( $page->ID, 'raw' ),
				'badge'       => __( 'Page', 'storyphone-inventory-manager' ),
				'readonly'    => true,
			);
		}

		return rest_ensure_response( array( 'pages' => $pages ) );
	}

	/**
	 * GET design config for a page key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_page_design( $request ) {
		$key = sanitize_key( $request['key'] );
		if ( 'home' !== $key ) {
			return new WP_Error(
				'storyphone_im_design_readonly',
				__( 'This page is not editable in Design yet. Use the WordPress editor link.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$settings = self::get_settings();
		$home     = $settings['pages']['home'];

		$nav_candidates = self::get_nav_candidates();
		$selected       = $home['nav_category_ids'];
		$nav_custom     = ! empty( $home['nav_custom'] );

		$nav_items = array();
		$by_id     = array();
		foreach ( $nav_candidates as $row ) {
			$by_id[ $row['id'] ] = $row;
		}

		// Only mark items enabled when Design was actually saved with those IDs.
		// Do not fake "first 9 on" — that made the UI lie when nothing was stored.
		if ( $nav_custom && ! empty( $selected ) ) {
			foreach ( $selected as $id ) {
				if ( isset( $by_id[ $id ] ) ) {
					$nav_items[] = array_merge( $by_id[ $id ], array( 'enabled' => true ) );
					unset( $by_id[ $id ] );
				}
			}
			foreach ( $by_id as $row ) {
				$nav_items[] = array_merge( $row, array( 'enabled' => false ) );
			}
		} else {
			foreach ( $nav_candidates as $row ) {
				$nav_items[] = array_merge( $row, array( 'enabled' => false ) );
			}
		}

		return rest_ensure_response(
			array(
				'key'        => 'home',
				'title'      => __( 'Homepage', 'storyphone-inventory-manager' ),
				'nav_custom' => $nav_custom,
				'nav_limit'  => 9,
				'blocks'     => array(
					array(
						'id'          => 'navbar',
						'title'       => __( 'Navbar', 'storyphone-inventory-manager' ),
						'description' => __( 'Toggle top-level categories on/off and reorder. Max 9 appear in the site header. Save required.', 'storyphone-inventory-manager' ),
						'type'        => 'nav_categories',
						'items'       => $nav_items,
					),
					array(
						'id'          => 'sections',
						'title'       => __( 'Page sections', 'storyphone-inventory-manager' ),
						'description' => __( 'Homepage stack from top to bottom. Toggle visibility or reorder.', 'storyphone-inventory-manager' ),
						'type'        => 'sections',
						'items'       => $home['sections'],
					),
				),
			)
		);
	}

	/**
	 * Top-level product categories available for the navbar.
	 *
	 * @return array<int, array{id:int,name:string,count:int}>
	 */
	private static function get_nav_candidates() {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'parent'     => 0,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$default_cat = (int) get_option( 'default_product_cat', 0 );
		$out         = array();
		foreach ( $terms as $term ) {
			if ( $default_cat && (int) $term->term_id === $default_cat ) {
				continue;
			}
			$out[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'count' => (int) $term->count,
			);
		}
		return $out;
	}

	/**
	 * Save homepage design.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_page_design( $request ) {
		$key = sanitize_key( $request['key'] );
		if ( 'home' !== $key ) {
			return new WP_Error(
				'storyphone_im_design_readonly',
				__( 'This page cannot be saved from Design yet.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$nav_ids = array();
		if ( isset( $params['nav_category_ids'] ) && is_array( $params['nav_category_ids'] ) ) {
			$nav_ids = array_values( array_unique( array_filter( array_map( 'absint', $params['nav_category_ids'] ) ) ) );
		}

		$sections_in = isset( $params['sections'] ) && is_array( $params['sections'] ) ? $params['sections'] : array();
		$sections    = self::merge_sections( $sections_in );

		// If client sent an explicit order, rebuild from that order.
		if ( ! empty( $sections_in ) ) {
			$ordered = array();
			$seen    = array();
			foreach ( $sections_in as $row ) {
				if ( ! is_array( $row ) || empty( $row['id'] ) ) {
					continue;
				}
				$id = sanitize_key( $row['id'] );
				foreach ( self::default_home_sections() as $def ) {
					if ( $def['id'] === $id && empty( $seen[ $id ] ) ) {
						$ordered[]   = array(
							'id'      => $id,
							'label'   => $def['label'],
							'enabled' => ! array_key_exists( 'enabled', $row ) || ! empty( $row['enabled'] ),
						);
						$seen[ $id ] = true;
						break;
					}
				}
			}
			foreach ( self::default_home_sections() as $def ) {
				if ( empty( $seen[ $def['id'] ] ) ) {
					$ordered[] = $def;
				}
			}
			$sections = $ordered;
		}

		// Persist explicitly — nav_custom means storefront must not use auto top-9.
		$payload = array(
			'pages' => array(
				'home' => array(
					'nav_category_ids' => $nav_ids,
					'nav_custom'       => true,
					'sections'         => $sections,
				),
			),
		);

		update_option( self::OPTION_KEY, $payload, false );

		// Object cache can keep a stale get_option() even when WP_CACHE is false.
		wp_cache_delete( self::OPTION_KEY, 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		// Verify write landed in DB (helps catch staging/object-cache mismatches).
		$verify = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $verify ) || empty( $verify['pages']['home']['nav_custom'] ) ) {
			// Force direct write if update_option was a no-op / cache lie.
			global $wpdb;
			$encoded = maybe_serialize( $payload );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists  = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					self::OPTION_KEY
				)
			);
			if ( $exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->options,
					array( 'option_value' => $encoded ),
					array( 'option_name' => self::OPTION_KEY ),
					array( '%s' ),
					array( '%s' )
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert(
					$wpdb->options,
					array(
						'option_name'  => self::OPTION_KEY,
						'option_value' => $encoded,
						'autoload'     => 'no',
					),
					array( '%s', '%s', '%s' )
				);
			}
			wp_cache_delete( self::OPTION_KEY, 'options' );
		}

		if ( class_exists( 'StoryPhone_IM_Audit_Log' ) ) {
			StoryPhone_IM_Audit_Log::log( 'update', 0, array( 'design' => 'home', 'nav_count' => count( $nav_ids ) ) );
		}

		// Bust common page caches so navbar/sections refresh.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'wp_cache_flush' ) && apply_filters( 'storyphone_im_flush_object_cache_on_design_save', false ) ) {
			wp_cache_flush();
		}
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Ignore.
			}
		}

		$response = self::get_page_design( $request );
		if ( $response instanceof WP_REST_Response ) {
			$data                 = $response->get_data();
			$data['saved_nav_ids'] = $nav_ids;
			$data['saved_count']   = count( $nav_ids );
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Public helper for storyphone-pages: filtered nav parent term IDs (ordered).
	 *
	 * @return int[]
	 */
	public static function get_home_nav_category_ids() {
		$settings = self::get_settings();
		$ids      = $settings['pages']['home']['nav_category_ids'];
		return is_array( $ids ) ? $ids : array();
	}

	/**
	 * Whether Design has taken ownership of the homepage navbar.
	 * When true, storyphone-pages must not fall back to automatic top-9.
	 *
	 * @return bool
	 */
	public static function is_home_nav_custom() {
		$settings = self::get_settings();
		return ! empty( $settings['pages']['home']['nav_custom'] );
	}

	/**
	 * Public helper: whether a homepage section should render.
	 *
	 * @param string $section_id Section slug.
	 * @return bool
	 */
	public static function is_home_section_enabled( $section_id ) {
		$section_id = sanitize_key( $section_id );
		$settings   = self::get_settings();
		foreach ( $settings['pages']['home']['sections'] as $row ) {
			if ( $row['id'] === $section_id ) {
				return ! empty( $row['enabled'] );
			}
		}
		return true;
	}

	/**
	 * Ordered enabled homepage section IDs.
	 *
	 * @return string[]
	 */
	public static function get_enabled_home_sections() {
		$settings = self::get_settings();
		$out      = array();
		foreach ( $settings['pages']['home']['sections'] as $row ) {
			if ( ! empty( $row['enabled'] ) ) {
				$out[] = $row['id'];
			}
		}
		return $out;
	}
}
