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
	 * Read storyphone_design from the DB (bypass object cache).
	 *
	 * Staging hosts often leave get_option() stale after update_option(), so the
	 * storefront already used a direct read. The Design admin must do the same
	 * or saved navbar toggles look "all off" after refresh.
	 *
	 * @return array
	 */
	public static function read_option_fresh() {
		global $wpdb;

		wp_cache_delete( self::OPTION_KEY, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::OPTION_KEY
			)
		);

		if ( null === $row || false === $row ) {
			return array();
		}

		$data = maybe_unserialize( $row );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Read stored design settings with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$raw = self::read_option_fresh();
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

		$section_content = self::merge_section_content(
			isset( $home['section_content'] ) && is_array( $home['section_content'] ) ? $home['section_content'] : array()
		);

		return array(
			'pages' => array(
				'home' => array(
					'nav_category_ids' => $nav_ids,
					'nav_custom'       => $nav_custom,
					'sections'         => $sections,
					'section_content'  => $section_content,
				),
			),
		);
	}

	/**
	 * Default editable content bags per homepage section.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function default_section_content() {
		return array(
			'hero'           => array(
				'custom'            => false,
				'chip_category_ids' => array(),
				'title'             => '',
				'subtitle'          => '',
			),
			'story-rail'     => array(
				'custom'       => false,
				'category_ids' => array(),
				'title'        => '',
				'subtitle'     => '',
			),
			'pick-deck'      => array(
				'custom'     => false,
				'product_id' => 0,
				'title'      => '',
				'subtitle'   => '',
			),
			'quick-reach'    => array(
				'custom'       => false,
				'category_ids' => array(),
				'title'        => '',
				'subtitle'     => '',
			),
			'heat-board'     => array(
				'custom'      => false,
				'product_ids' => array(),
				'title'       => '',
				'subtitle'    => '',
			),
			'cinema-banner'  => array(
				'custom' => false,
				'items'  => array(),
			),
			'showcase'       => array(
				'custom'      => false,
				'product_ids' => array(),
				'title'       => '',
				'subtitle'    => '',
			),
			'deal'           => array(
				'custom'     => false,
				'product_id' => 0,
			),
			'trust'          => array(
				'custom' => false,
				'title'  => '',
				'items'  => array(),
			),
			'editor-content' => array(
				'note' => '',
			),
			'cta'            => array(
				'custom'       => false,
				'title'        => '',
				'text'         => '',
				'button_label' => '',
				'button_url'   => '',
			),
		);
	}

	/**
	 * Merge saved section content with defaults.
	 *
	 * @param array $saved Saved map.
	 * @return array<string, array<string, mixed>>
	 */
	private static function merge_section_content( $saved ) {
		$defaults = self::default_section_content();
		$out      = array();
		foreach ( $defaults as $id => $def ) {
			$row = isset( $saved[ $id ] ) && is_array( $saved[ $id ] ) ? $saved[ $id ] : array();
			$out[ $id ] = self::sanitize_one_section_content( $id, array_merge( $def, $row ) );
		}
		return $out;
	}

	/**
	 * Sanitize one section content bag.
	 *
	 * @param string $id   Section id.
	 * @param array  $row  Raw row.
	 * @return array<string, mixed>
	 */
	private static function sanitize_one_section_content( $id, $row ) {
		$id  = sanitize_key( $id );
		$row = is_array( $row ) ? $row : array();

		$ids = static function ( $key, $max = 24 ) use ( $row ) {
			if ( empty( $row[ $key ] ) || ! is_array( $row[ $key ] ) ) {
				return array();
			}
			$clean = array_values( array_unique( array_filter( array_map( 'absint', $row[ $key ] ) ) ) );
			return array_slice( $clean, 0, $max );
		};

		$custom_flag = static function () use ( $row ) {
			return ! empty( $row['custom'] );
		};

		switch ( $id ) {
			case 'hero':
				$chip_ids = $ids( 'chip_category_ids', 8 );
				return array(
					// Legacy: non-empty ID list counts as custom even without the flag.
					'custom'            => $custom_flag() || ! empty( $chip_ids ),
					'chip_category_ids' => $chip_ids,
					'title'             => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
					'subtitle'          => isset( $row['subtitle'] ) ? sanitize_text_field( $row['subtitle'] ) : '',
				);
			case 'story-rail':
			case 'quick-reach':
				$cat_ids = $ids( 'category_ids', 12 );
				return array(
					'custom'       => $custom_flag() || ! empty( $cat_ids ),
					'category_ids' => $cat_ids,
					'title'        => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
					'subtitle'     => isset( $row['subtitle'] ) ? sanitize_text_field( $row['subtitle'] ) : '',
				);
			case 'pick-deck':
				$pid = isset( $row['product_id'] ) ? absint( $row['product_id'] ) : 0;
				return array(
					'custom'     => $custom_flag() || $pid > 0,
					'product_id' => $pid,
					'title'      => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
					'subtitle'   => isset( $row['subtitle'] ) ? sanitize_text_field( $row['subtitle'] ) : '',
				);
			case 'heat-board':
			case 'showcase':
				$pids = $ids( 'product_ids', 12 );
				return array(
					'custom'      => $custom_flag() || ! empty( $pids ),
					'product_ids' => $pids,
					'title'       => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
					'subtitle'    => isset( $row['subtitle'] ) ? sanitize_text_field( $row['subtitle'] ) : '',
				);
			case 'cinema-banner':
				return self::sanitize_cinema_banner_content( $row, $custom_flag() );
			case 'deal':
				$pid = isset( $row['product_id'] ) ? absint( $row['product_id'] ) : 0;
				return array(
					'custom'     => $custom_flag() || $pid > 0,
					'product_id' => $pid,
				);
			case 'trust':
				$items = array();
				if ( ! empty( $row['items'] ) && is_array( $row['items'] ) ) {
					foreach ( array_slice( $row['items'], 0, 8 ) as $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}
						$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
						$text  = isset( $item['text'] ) ? sanitize_text_field( $item['text'] ) : '';
						if ( '' === $title && '' === $text ) {
							continue;
						}
						$items[] = array(
							'title' => $title,
							'text'  => $text,
						);
					}
				}
				return array(
					'custom' => $custom_flag() || ! empty( $items ),
					'title'  => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
					'items'  => $items,
				);
			case 'cta':
				$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
				$text  = isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '';
				$label = isset( $row['button_label'] ) ? sanitize_text_field( $row['button_label'] ) : '';
				$url   = isset( $row['button_url'] ) ? esc_url_raw( $row['button_url'] ) : '';
				return array(
					'custom'       => $custom_flag() || '' !== $title || '' !== $text || '' !== $label || '' !== $url,
					'title'        => $title,
					'text'         => $text,
					'button_label' => $label,
					'button_url'   => $url,
				);
			case 'editor-content':
			default:
				return array(
					'note' => '',
				);
		}
	}

	/**
	 * Cinema orbit items: image / video attachments or products, optional caption.
	 * Migrates legacy product_ids lists into product items.
	 *
	 * @param array $row         Raw content bag.
	 * @param bool  $custom_flag Explicit custom flag from request.
	 * @return array<string, mixed>
	 */
	private static function sanitize_cinema_banner_content( $row, $custom_flag ) {
		$row   = is_array( $row ) ? $row : array();
		$items = array();

		if ( ! empty( $row['items'] ) && is_array( $row['items'] ) ) {
			foreach ( array_slice( $row['items'], 0, 8 ) as $index => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : '';
				if ( ! in_array( $type, array( 'image', 'video', 'product' ), true ) ) {
					continue;
				}
				$attachment_id = isset( $item['attachment_id'] ) ? absint( $item['attachment_id'] ) : 0;
				$product_id    = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
				$url           = isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '';
				$label         = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
				$text          = isset( $item['text'] ) ? sanitize_text_field( $item['text'] ) : '';
				$key           = isset( $item['key'] ) ? sanitize_key( $item['key'] ) : '';

				if ( 'product' === $type && $product_id < 1 ) {
					continue;
				}
				if ( ( 'image' === $type || 'video' === $type ) && $attachment_id < 1 && '' === $url ) {
					continue;
				}

				if ( '' === $key ) {
					$key = 'c' . (string) ( $index + 1 ) . 'x' . (string) ( $attachment_id + $product_id );
				}

				$items[] = array(
					'key'           => $key,
					'type'          => $type,
					'attachment_id' => $attachment_id,
					'product_id'    => $product_id,
					'url'           => $url,
					'label'         => $label,
					'text'          => $text,
				);
			}
		}

		// Legacy: product_ids-only cinema picks → product items.
		if ( empty( $items ) && ! empty( $row['product_ids'] ) && is_array( $row['product_ids'] ) ) {
			foreach ( array_slice( array_values( array_filter( array_map( 'absint', $row['product_ids'] ) ) ), 0, 8 ) as $i => $pid ) {
				$items[] = array(
					'key'           => 'legacy' . (string) $pid,
					'type'          => 'product',
					'attachment_id' => 0,
					'product_id'    => $pid,
					'url'           => '',
					'label'         => '',
					'text'          => '',
				);
			}
		}

		return array(
			'custom' => $custom_flag || ! empty( $items ),
			'items'  => $items,
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
			$by_id[ (int) $row['id'] ] = $row;
		}

		// Only mark items enabled when Design was actually saved with those IDs.
		// Do not fake "first 9 on" — that made the UI lie when nothing was stored.
		if ( $nav_custom && ! empty( $selected ) ) {
			foreach ( $selected as $id ) {
				$id = (int) $id;
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
						'description' => __( 'Homepage stack from top to bottom. Toggle visibility, reorder, and choose what appears inside each section.', 'storyphone-inventory-manager' ),
						'type'        => 'sections',
						'items'       => $home['sections'],
					),
				),
				'section_content'  => $home['section_content'],
				'section_preview'  => self::build_section_previews(),
			)
		);
	}

	/**
	 * What each homepage section currently shows (for Design admin UI).
	 *
	 * Uses the same resolver as the storefront when available, so "Automatic"
	 * sections list the real live items — not only saved custom IDs.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function build_section_previews() {
		$defaults = self::default_section_content();
		$preview  = array();

		foreach ( array_keys( $defaults ) as $id ) {
			$preview[ $id ] = array(
				'source'   => 'auto',
				'title'    => '',
				'subtitle' => '',
				'items'    => array(),
				'note'     => '',
			);
		}

		if ( ! class_exists( 'StoryPhone_IM_Storefront_Design' ) || ! class_exists( 'StoryPhone_Pages_Catalog' ) ) {
			$content = self::get_all_section_content();
			foreach ( $content as $id => $bag ) {
				$preview[ $id ] = self::preview_from_content_bag( $id, $bag );
			}
			return $preview;
		}

		try {
			$data    = StoryPhone_IM_Storefront_Design::resolve_home_data();
			$content = isset( $data['section_content'] ) && is_array( $data['section_content'] ) ? $data['section_content'] : array();

			$preview['hero'] = array(
				'source'   => self::preview_source( $content, 'hero', 'chip_category_ids' ),
				'title'    => isset( $content['hero']['title'] ) ? (string) $content['hero']['title'] : '',
				'subtitle' => isset( $content['hero']['subtitle'] ) ? (string) $content['hero']['subtitle'] : '',
				'items'    => self::terms_to_preview_items( isset( $data['chips'] ) ? $data['chips'] : array() ),
				'note'     => __( 'Popular chips under search', 'storyphone-inventory-manager' ),
			);

			$preview['story-rail'] = array(
				'source'   => self::preview_source( $content, 'story-rail', 'category_ids' ),
				'title'    => isset( $content['story-rail']['title'] ) ? (string) $content['story-rail']['title'] : '',
				'subtitle' => isset( $content['story-rail']['subtitle'] ) ? (string) $content['story-rail']['subtitle'] : '',
				'items'    => self::stories_to_preview_items( isset( $data['stories'] ) ? $data['stories'] : array() ),
				'note'     => __( 'Story bubbles', 'storyphone-inventory-manager' ),
			);

			$pick_items = self::products_to_preview_items(
				! empty( $data['pick'] ) ? array( $data['pick'] ) : array()
			);
			if ( empty( $pick_items ) && class_exists( 'StoryPhone_Pages_Catalog' ) ) {
				// Fallback so Design UI always shows the live automatic pick.
				$fallback_hot = StoryPhone_Pages_Catalog::get_hot_products( 1 );
				$pick_items   = self::products_to_preview_items( $fallback_hot );
			}
			$preview['pick-deck'] = array(
				'source'   => ! empty( $content['pick-deck']['custom'] ) || ! empty( $content['pick-deck']['product_id'] ) ? 'custom' : 'auto',
				'title'    => isset( $content['pick-deck']['title'] ) ? (string) $content['pick-deck']['title'] : '',
				'subtitle' => isset( $content['pick-deck']['subtitle'] ) ? (string) $content['pick-deck']['subtitle'] : '',
				'items'    => $pick_items,
				'note'     => __( 'Featured pick card', 'storyphone-inventory-manager' ),
			);

			$preview['quick-reach'] = array(
				'source'   => self::preview_source( $content, 'quick-reach', 'category_ids' ),
				'title'    => isset( $content['quick-reach']['title'] ) ? (string) $content['quick-reach']['title'] : '',
				'subtitle' => isset( $content['quick-reach']['subtitle'] ) ? (string) $content['quick-reach']['subtitle'] : '',
				'items'    => self::terms_to_preview_items( isset( $data['families'] ) ? $data['families'] : array() ),
				'note'     => __( 'Quick-reach category tiles', 'storyphone-inventory-manager' ),
			);

			$preview['heat-board'] = array(
				'source'   => self::preview_source( $content, 'heat-board', 'product_ids' ),
				'title'    => isset( $content['heat-board']['title'] ) ? (string) $content['heat-board']['title'] : '',
				'subtitle' => isset( $content['heat-board']['subtitle'] ) ? (string) $content['heat-board']['subtitle'] : '',
				'items'    => self::products_to_preview_items( isset( $data['hot'] ) ? $data['hot'] : array() ),
				'note'     => __( 'Heat board products', 'storyphone-inventory-manager' ),
			);

			$preview['showcase'] = array(
				'source'   => self::preview_source( $content, 'showcase', 'product_ids' ),
				'title'    => isset( $content['showcase']['title'] ) ? (string) $content['showcase']['title'] : '',
				'subtitle' => isset( $content['showcase']['subtitle'] ) ? (string) $content['showcase']['subtitle'] : '',
				'items'    => self::products_to_preview_items( isset( $data['showcase'] ) ? $data['showcase'] : array() ),
				'note'     => __( 'Showcase grid', 'storyphone-inventory-manager' ),
			);

			$preview['cinema-banner'] = self::build_cinema_banner_preview(
				isset( $content['cinema-banner'] ) && is_array( $content['cinema-banner'] ) ? $content['cinema-banner'] : array()
			);

			$deal_items = self::products_to_preview_items(
				! empty( $data['deal'] ) ? array( $data['deal'] ) : array()
			);
			if ( empty( $deal_items ) && empty( $content['deal']['custom'] ) && class_exists( 'StoryPhone_Pages_Catalog' ) ) {
				$fallback_deal = StoryPhone_Pages_Catalog::get_deal_product();
				$deal_items    = self::products_to_preview_items(
					$fallback_deal ? array( $fallback_deal ) : array()
				);
			}
			$preview['deal'] = array(
				'source'   => ! empty( $content['deal']['custom'] ) || ! empty( $content['deal']['product_id'] ) ? 'custom' : 'auto',
				'title'    => '',
				'subtitle' => '',
				'items'    => $deal_items,
				'note'     => __( 'Deal of the day', 'storyphone-inventory-manager' ),
			);

			$trust = isset( $content['trust'] ) ? $content['trust'] : array();
			$trust_items = array();
			if ( ! empty( $trust['items'] ) && is_array( $trust['items'] ) ) {
				foreach ( $trust['items'] as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$t = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
					$x = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';
					if ( '' === $t && '' === $x ) {
						continue;
					}
					$trust_items[] = array(
						'id'   => 0,
						'name' => $t ? $t : $x,
						'type' => 'text',
					);
				}
			}
			$preview['trust'] = array(
				'source'   => ! empty( $trust_items ) ? 'custom' : 'auto',
				'title'    => isset( $trust['title'] ) ? (string) $trust['title'] : '',
				'subtitle' => '',
				'items'    => $trust_items,
				'note'     => ! empty( $trust_items )
					? __( 'Custom trust items', 'storyphone-inventory-manager' )
					: __( 'Default trust marquee', 'storyphone-inventory-manager' ),
			);

			$cta = isset( $content['cta'] ) ? $content['cta'] : array();
			$cta_custom = ! empty( $cta['title'] ) || ! empty( $cta['text'] ) || ! empty( $cta['button_label'] ) || ! empty( $cta['button_url'] );
			$cta_items  = array();
			if ( ! empty( $cta['title'] ) ) {
				$cta_items[] = array( 'id' => 0, 'name' => (string) $cta['title'], 'type' => 'text' );
			} elseif ( ! $cta_custom ) {
				$cta_items[] = array( 'id' => 0, 'name' => __( 'Default CTA copy', 'storyphone-inventory-manager' ), 'type' => 'text' );
			}
			if ( ! empty( $cta['button_label'] ) ) {
				$cta_items[] = array( 'id' => 0, 'name' => (string) $cta['button_label'], 'type' => 'text' );
			}
			$preview['cta'] = array(
				'source'   => $cta_custom ? 'custom' : 'auto',
				'title'    => isset( $cta['title'] ) ? (string) $cta['title'] : '',
				'subtitle' => isset( $cta['text'] ) ? (string) $cta['text'] : '',
				'items'    => $cta_items,
				'note'     => __( 'Closing call to action', 'storyphone-inventory-manager' ),
			);

			$preview['editor-content'] = array(
				'source'   => 'auto',
				'title'    => '',
				'subtitle' => '',
				'items'    => array(),
				'note'     => __( 'WordPress page body (edit in WP editor)', 'storyphone-inventory-manager' ),
			);
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			$content = self::get_all_section_content();
			foreach ( $content as $id => $bag ) {
				$preview[ $id ] = self::preview_from_content_bag( $id, $bag );
			}
		}

		return $preview;
	}

	/**
	 * Preview payload for cinema orbit (custom items or automatic product search).
	 *
	 * @param array $bag Cinema content bag.
	 * @return array<string, mixed>
	 */
	private static function build_cinema_banner_preview( $bag ) {
		$bag   = is_array( $bag ) ? $bag : array();
		$items = array();

		if ( ! empty( $bag['items'] ) && is_array( $bag['items'] ) ) {
			foreach ( $bag['items'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$type  = isset( $row['type'] ) ? (string) $row['type'] : '';
				$text  = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';
				$label = isset( $row['label'] ) ? (string) $row['label'] : '';
				$url   = isset( $row['url'] ) ? (string) $row['url'] : '';
				$name  = $label;
				$image = $url;

				if ( 'product' === $type && ! empty( $row['product_id'] ) && function_exists( 'wc_get_product' ) ) {
					$product = wc_get_product( absint( $row['product_id'] ) );
					if ( $product instanceof WC_Product ) {
						$name = $product->get_name();
						if ( class_exists( 'StoryPhone_Pages_Catalog' ) ) {
							$image = StoryPhone_Pages_Catalog::get_product_image_url( $product, 'woocommerce_thumbnail' );
						}
					}
				} elseif ( ( 'image' === $type || 'video' === $type ) && ! empty( $row['attachment_id'] ) ) {
					$att_id = absint( $row['attachment_id'] );
					if ( ! $name ) {
						$name = (string) get_the_title( $att_id );
					}
					if ( 'image' === $type ) {
						$resolved = wp_get_attachment_image_url( $att_id, 'medium' );
						if ( $resolved ) {
							$image = $resolved;
						}
					}
				}

				if ( ! $name ) {
					$name = 'video' === $type ? __( 'Video', 'storyphone-inventory-manager' ) : __( 'Image', 'storyphone-inventory-manager' );
				}

				$items[] = array(
					'id'    => isset( $row['product_id'] ) ? (int) $row['product_id'] : (int) ( $row['attachment_id'] ?? 0 ),
					'name'  => $name,
					'type'  => $type ? $type : 'image',
					'image' => $image,
					'url'   => $url,
					'text'  => $text,
				);
			}
		}

		if ( empty( $items ) && class_exists( 'StoryPhone_Pages_Catalog' ) ) {
			$cinema_searches = array(
				array( 'iPhone 17', 'iPhone 16 Pro', 'iPhone' ),
				array( 'Galaxy S25', 'Galaxy S24', 'Samsung Galaxy' ),
				array( 'MacBook Pro', 'MacBook Air', 'MacBook' ),
				array( 'Sony', 'Canon', 'Camera', 'מצלמה' ),
				array( 'PlayStation', 'Xbox', 'Controller', 'גיימינג' ),
				array( 'Apple Watch', 'Watch', 'שעון' ),
				array( 'AirPods', 'Galaxy Buds', 'Earbuds' ),
				array( 'iPhone 16', 'iPhone 15', 'iPhone' ),
			);
			foreach ( $cinema_searches as $terms ) {
				$found = StoryPhone_Pages_Catalog::find_product_by_search( $terms );
				if ( $found ) {
					$items[] = array(
						'id'    => (int) $found->get_id(),
						'name'  => $found->get_name(),
						'type'  => 'product',
						'image' => StoryPhone_Pages_Catalog::get_product_image_url( $found, 'woocommerce_thumbnail' ),
						'url'   => '',
						'text'  => '',
					);
				}
			}
		}

		$is_custom = ! empty( $bag['custom'] ) || ( ! empty( $bag['items'] ) && is_array( $bag['items'] ) );

		return array(
			'source'   => $is_custom ? 'custom' : 'auto',
			'title'    => '',
			'subtitle' => '',
			'items'    => $items,
			'note'     => __( 'Cinema orbit: images, videos, or products (max 8)', 'storyphone-inventory-manager' ),
		);
	}

	/**
	 * custom vs auto for a list field.
	 *
	 * @param array  $content Full section_content.
	 * @param string $section Section id.
	 * @param string $key     List key.
	 * @return string
	 */
	private static function preview_source( $content, $section, $key ) {
		$bag = isset( $content[ $section ] ) && is_array( $content[ $section ] ) ? $content[ $section ] : array();
		if ( ! empty( $bag['custom'] ) ) {
			return 'custom';
		}
		if ( empty( $bag[ $key ] ) ) {
			return 'auto';
		}
		return is_array( $bag[ $key ] ) ? ( empty( $bag[ $key ] ) ? 'auto' : 'custom' ) : 'custom';
	}

	/**
	 * @param WP_Term[] $terms Terms.
	 * @return array<int, array{id:int,name:string,type:string}>
	 */
	private static function terms_to_preview_items( $terms ) {
		$out = array();
		foreach ( (array) $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$out[] = array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
					'type' => 'category',
				);
			}
		}
		return $out;
	}

	/**
	 * @param WC_Product[] $products Products.
	 * @return array<int, array{id:int,name:string,type:string}>
	 */
	private static function products_to_preview_items( $products ) {
		$out = array();
		foreach ( (array) $products as $product ) {
			if ( $product instanceof WC_Product ) {
				$out[] = array(
					'id'   => (int) $product->get_id(),
					'name' => $product->get_name(),
					'type' => 'product',
				);
			}
		}
		return $out;
	}

	/**
	 * @param array $stories Story payloads.
	 * @return array<int, array{id:int,name:string,type:string}>
	 */
	private static function stories_to_preview_items( $stories ) {
		$out = array();
		foreach ( (array) $stories as $story ) {
			if ( ! is_array( $story ) || empty( $story['name'] ) ) {
				continue;
			}
			$id = 0;
			if ( ! empty( $story['id'] ) && preg_match( '/(\d+)/', (string) $story['id'], $m ) ) {
				$id = (int) $m[1];
			}
			$out[] = array(
				'id'   => $id,
				'name' => (string) $story['name'],
				'type' => 'category',
			);
		}
		return $out;
	}

	/**
	 * Fallback preview from saved IDs only (no storefront resolver).
	 *
	 * @param string $id  Section id.
	 * @param array  $bag Content bag.
	 * @return array<string, mixed>
	 */
	private static function preview_from_content_bag( $id, $bag ) {
		$bag   = is_array( $bag ) ? $bag : array();
		$items = array();
		$source = 'auto';

		$cat_keys = array( 'chip_category_ids', 'category_ids' );
		foreach ( $cat_keys as $key ) {
			if ( empty( $bag[ $key ] ) || ! is_array( $bag[ $key ] ) ) {
				continue;
			}
			$source = 'custom';
			foreach ( $bag[ $key ] as $term_id ) {
				$term = get_term( absint( $term_id ), 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$items[] = array(
						'id'   => (int) $term->term_id,
						'name' => $term->name,
						'type' => 'category',
					);
				}
			}
		}

		if ( ! empty( $bag['product_ids'] ) && is_array( $bag['product_ids'] ) ) {
			$source = 'custom';
			foreach ( $bag['product_ids'] as $pid ) {
				if ( ! function_exists( 'wc_get_product' ) ) {
					break;
				}
				$product = wc_get_product( absint( $pid ) );
				if ( $product instanceof WC_Product ) {
					$items[] = array(
						'id'   => (int) $product->get_id(),
						'name' => $product->get_name(),
						'type' => 'product',
					);
				}
			}
		}

		if ( ! empty( $bag['product_id'] ) && function_exists( 'wc_get_product' ) ) {
			$source  = 'custom';
			$product = wc_get_product( absint( $bag['product_id'] ) );
			if ( $product instanceof WC_Product ) {
				$items[] = array(
					'id'   => (int) $product->get_id(),
					'name' => $product->get_name(),
					'type' => 'product',
				);
			}
		}

		return array(
			'source'   => $source,
			'title'    => isset( $bag['title'] ) ? (string) $bag['title'] : '',
			'subtitle' => isset( $bag['subtitle'] ) ? (string) $bag['subtitle'] : '',
			'items'    => $items,
			'note'     => 'custom' === $source
				? __( 'Your saved selection', 'storyphone-inventory-manager' )
				: __( 'Automatic (save custom picks to override)', 'storyphone-inventory-manager' ),
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

		$previous = self::get_settings()['pages']['home'];

		if ( isset( $params['nav_category_ids'] ) && is_array( $params['nav_category_ids'] ) ) {
			$nav_ids    = array_values( array_unique( array_filter( array_map( 'absint', $params['nav_category_ids'] ) ) ) );
			$nav_custom = true;
		} else {
			// Keep existing navbar when the client only updates sections / content.
			$nav_ids    = isset( $previous['nav_category_ids'] ) ? $previous['nav_category_ids'] : array();
			$nav_custom = ! empty( $previous['nav_custom'] ) || ! empty( $nav_ids );
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

		if ( isset( $params['section_content'] ) && is_array( $params['section_content'] ) ) {
			$section_content = self::merge_section_content( $params['section_content'] );
		} else {
			// Keep previously saved content when client omits the field.
			$section_content = $previous['section_content'];
		}

		if ( empty( $sections_in ) ) {
			$sections = $previous['sections'];
		}

		// Persist explicitly — nav_custom means storefront must not use auto top-9.
		$payload = array(
			'pages' => array(
				'home' => array(
					'nav_category_ids' => $nav_ids,
					'nav_custom'       => $nav_custom,
					'sections'         => $sections,
					'section_content'  => $section_content,
				),
			),
		);

		update_option( self::OPTION_KEY, $payload, false );

		// Object cache can keep a stale get_option() even when WP_CACHE is false.
		wp_cache_delete( self::OPTION_KEY, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		// Verify write landed in DB (helps catch staging/object-cache mismatches).
		$verify = self::read_option_fresh();
		$verify_home = ( is_array( $verify ) && isset( $verify['pages']['home'] ) && is_array( $verify['pages']['home'] ) )
			? $verify['pages']['home']
			: array();
		$verify_ok   = is_array( $verify )
			&& (
				! empty( $verify_home['nav_custom'] )
				|| ! empty( $verify_home['nav_category_ids'] )
				|| ! empty( $verify_home['section_content'] )
			);
		if ( ! $verify_ok ) {
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

	/**
	 * Content bag for one homepage section (merged defaults).
	 *
	 * @param string $section_id Section slug.
	 * @return array<string, mixed>
	 */
	public static function get_section_content( $section_id ) {
		$section_id = sanitize_key( $section_id );
		$settings   = self::get_settings();
		$map        = $settings['pages']['home']['section_content'];
		if ( isset( $map[ $section_id ] ) && is_array( $map[ $section_id ] ) ) {
			return $map[ $section_id ];
		}
		$defaults = self::default_section_content();
		return isset( $defaults[ $section_id ] ) ? $defaults[ $section_id ] : array();
	}

	/**
	 * Full section_content map.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all_section_content() {
		$settings = self::get_settings();
		return $settings['pages']['home']['section_content'];
	}
}
