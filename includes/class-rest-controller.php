<?php
/**
 * REST API controller for StoryPhone Inventory Manager.
 *
 * Routes under /wp-json/storyphone/v1/
 *
 * @package StoryPhone_Inventory_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles StoryPhone inventory REST routes.
 */
class StoryPhone_IM_REST_Controller {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'storyphone/v1';

	/**
	 * Hook registrations.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_products' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
					'args'                => array(
						'page'     => array(
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'default'           => 24,
							'sanitize_callback' => 'absint',
						),
						'search'   => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'category' => array(
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'status'   => array(
							'default'           => 'publish',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'collection' => array(
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_key',
						),
						'stock_status' => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_product' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'bulk_products' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_stats' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_product' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'update_product' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'trash_product' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)/image',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'upload_product_image' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_product_image' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'image_id' => array(
							'required'          => false,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'upload_media' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_categories' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_category' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/categories/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_category' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_category' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/categories/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'bulk_categories' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
			)
		);
	}

	/**
	 * Permission check: manage_woocommerce + valid REST nonce (via cookie auth).
	 *
	 * WordPress REST API automatically verifies the X-WP-Nonce header for cookie
	 * authentication when permission_callback runs under a logged-in user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public static function check_permissions( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'storyphone_im_forbidden',
				__( 'You do not have permission to manage inventory.', 'storyphone-inventory-manager' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			// Also accept _wpnonce query/body param for tooling.
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'storyphone_im_invalid_nonce',
				__( 'Invalid or missing REST nonce.', 'storyphone-inventory-manager' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /products — list products with pagination and filters.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_products( $request ) {
		$page         = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page     = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$search       = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$category     = absint( $request->get_param( 'category' ) );
		$status       = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$collection   = sanitize_key( (string) $request->get_param( 'collection' ) );
		$stock_status = sanitize_key( (string) $request->get_param( 'stock_status' ) );

		$allowed_collections = array( 'all', 'categories', 'instock', 'outofstock', 'disabled' );
		if ( ! in_array( $collection, $allowed_collections, true ) ) {
			$collection = 'all';
		}

		$allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'any' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'publish';
		}

		// Collection presets override status / stock filters.
		if ( 'disabled' === $collection ) {
			// Disabled only (any stock level).
			return self::list_disabled_products( $page, $per_page, $search, $category );
		}

		if ( 'instock' === $collection ) {
			// Enabled + in stock only (never Disabled / out of stock).
			return self::list_stock_enabled_products( $page, $per_page, $search, $category, 'instock' );
		}

		if ( 'outofstock' === $collection ) {
			// Enabled + out of stock only (never Disabled / drafts / hidden).
			return self::list_stock_enabled_products( $page, $per_page, $search, $category, 'outofstock' );
		}

		// All items / categories: include Enabled and Disabled (drafts, hidden, etc.).
		if ( 'all' === $collection || 'categories' === $collection ) {
			return self::list_all_inventory_products( $page, $per_page, $search, $category );
		}

		$query_args = array(
			'status'   => $status,
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'return'   => 'objects',
		);

		if ( '' !== $stock_status ) {
			$allowed_stock = array( 'instock', 'outofstock', 'onbackorder' );
			if ( in_array( $stock_status, $allowed_stock, true ) ) {
				$query_args['stock_status'] = $stock_status;
			}
		}

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		if ( $category > 0 ) {
			$term = get_term( $category, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$query_args['category'] = array( $term->slug );
			}
		}

		$results = new WC_Product_Query( $query_args );
		$query   = $results->get_products();

		$products = array();
		if ( ! empty( $query->products ) ) {
			foreach ( $query->products as $product ) {
				$products[] = self::format_product_summary( $product );
			}
		}

		return rest_ensure_response(
			array(
				'products'   => $products,
				'total'      => isset( $query->total ) ? (int) $query->total : count( $products ),
				'pages'      => isset( $query->max_num_pages ) ? (int) $query->max_num_pages : 1,
				'page'       => $page,
				'per_page'   => $per_page,
				'collection' => $collection,
			)
		);
	}

	/**
	 * Inventory statuses included in "All items" (everything except trash).
	 *
	 * @return string[]
	 */
	private static function inventory_statuses() {
		return array( 'publish', 'draft', 'private', 'pending' );
	}

	/**
	 * Whether a product is Disabled in the inventory UI (off storefront).
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	private static function is_inventory_disabled( $product ) {
		if ( ! $product ) {
			return true;
		}
		if ( 'publish' !== $product->get_status() ) {
			return true;
		}
		return 'hidden' === $product->get_catalog_visibility();
	}

	/**
	 * List All items: Enabled + Disabled (any stock).
	 * Uses WP_Query so draft/Disabled products are included in search (WC search often skips them).
	 *
	 * @param int    $page     Page.
	 * @param int    $per_page Per page.
	 * @param string $search   Search.
	 * @param int    $category Category term ID.
	 * @return WP_REST_Response
	 */
	private static function list_all_inventory_products( $page, $per_page, $search, $category ) {
		$args = array(
			'post_type'              => 'product',
			'post_status'            => self::inventory_statuses(),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( $category > 0 ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => array( $category ),
				),
			);
		}

		$query    = new WP_Query( $args );
		$products = array();

		foreach ( (array) $query->posts as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$products[] = self::format_product_summary( $product );
			}
		}

		$total = (int) $query->found_posts;
		$pages = max( 1, (int) $query->max_num_pages );

		return rest_ensure_response(
			array(
				'products'   => $products,
				'total'      => $total,
				'pages'      => $pages,
				'page'       => $page,
				'per_page'   => $per_page,
				'collection' => 'all',
			)
		);
	}

	/**
	 * List Enabled products for a stock status (instock or outofstock). Excludes Disabled.
	 *
	 * @param int    $page         Page.
	 * @param int    $per_page     Per page.
	 * @param string $search       Search.
	 * @param int    $category     Category term ID.
	 * @param string $stock_status Stock status key.
	 * @return WP_REST_Response
	 */
	private static function list_stock_enabled_products( $page, $per_page, $search, $category, $stock_status ) {
		$allowed_stock = array( 'instock', 'outofstock' );
		if ( ! in_array( $stock_status, $allowed_stock, true ) ) {
			$stock_status = 'instock';
		}

		$base = array(
			'status'       => 'publish',
			'stock_status' => $stock_status,
			'limit'        => -1,
			'return'       => 'ids',
			'orderby'      => 'date',
			'order'        => 'DESC',
		);

		if ( '' !== $search ) {
			$base['s'] = $search;
		}

		if ( $category > 0 ) {
			$term = get_term( $category, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$base['category'] = array( $term->slug );
			}
		}

		$query = new WC_Product_Query( $base );
		$ids   = $query->get_products();
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}

		$enabled_ids = array();
		foreach ( $ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || self::is_inventory_disabled( $product ) ) {
				continue;
			}
			if ( $stock_status !== $product->get_stock_status() ) {
				continue;
			}
			$enabled_ids[] = (int) $product_id;
		}

		$total = count( $enabled_ids );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$page  = min( max( 1, $page ), $pages );
		$slice = array_slice( $enabled_ids, ( $page - 1 ) * $per_page, $per_page );

		$products = array();
		foreach ( $slice as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$products[] = self::format_product_summary( $product );
			}
		}

		return rest_ensure_response(
			array(
				'products'   => $products,
				'total'      => $total,
				'pages'      => $pages,
				'page'       => $page,
				'per_page'   => $per_page,
				'collection' => $stock_status,
			)
		);
	}

	/**
	 * Count Enabled products for a stock status (excludes Disabled).
	 *
	 * @param string $stock_status Stock status key.
	 * @return int
	 */
	private static function count_stock_enabled_products( $stock_status ) {
		$query = new WC_Product_Query(
			array(
				'status'       => 'publish',
				'stock_status' => $stock_status,
				'limit'        => -1,
				'return'       => 'ids',
			)
		);
		$ids = $query->get_products();
		if ( ! is_array( $ids ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product && ! self::is_inventory_disabled( $product ) && $stock_status === $product->get_stock_status() ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * List disabled products: draft/private OR catalog visibility hidden.
	 *
	 * @param int    $page     Page.
	 * @param int    $per_page Per page.
	 * @param string $search   Search.
	 * @param int    $category Category term ID.
	 * @return WP_REST_Response
	 */
	private static function list_disabled_products( $page, $per_page, $search, $category ) {
		$ids = self::get_disabled_product_ids( $search, $category );
		rsort( $ids, SORT_NUMERIC );

		$total = count( $ids );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$page  = min( $page, $pages );
		$slice = array_slice( $ids, ( $page - 1 ) * $per_page, $per_page );

		$products = array();
		foreach ( $slice as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$products[] = self::format_product_summary( $product );
			}
		}

		return rest_ensure_response(
			array(
				'products'   => $products,
				'total'      => $total,
				'pages'      => $pages,
				'page'       => $page,
				'per_page'   => $per_page,
				'collection' => 'disabled',
			)
		);
	}

	/**
	 * Collect product IDs considered disabled in the inventory UI.
	 *
	 * @param string $search   Search string.
	 * @param int    $category Category term ID.
	 * @return int[]
	 */
	private static function get_disabled_product_ids( $search = '', $category = 0 ) {
		$base = array(
			'limit'  => -1,
			'return' => 'ids',
			'orderby'=> 'ID',
			'order'  => 'DESC',
		);

		if ( '' !== $search ) {
			$base['s'] = $search;
		}

		if ( $category > 0 ) {
			$term = get_term( $category, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$base['category'] = array( $term->slug );
			}
		}

		$hidden_query = new WC_Product_Query(
			array_merge(
				$base,
				array(
					'status'     => 'publish',
					'visibility' => 'hidden',
				)
			)
		);
		$hidden_ids = $hidden_query->get_products();
		if ( ! is_array( $hidden_ids ) ) {
			$hidden_ids = array();
		}

		$draft_query = new WC_Product_Query(
			array_merge(
				$base,
				array(
					'status' => array( 'draft', 'private', 'pending' ),
				)
			)
		);
		$draft_ids = $draft_query->get_products();
		if ( ! is_array( $draft_ids ) ) {
			$draft_ids = array();
		}

		$ids = array_unique( array_map( 'absint', array_merge( $hidden_ids, $draft_ids ) ) );
		return array_values( array_filter( $ids ) );
	}

	/**
	 * GET /stats — collection counts for sidebar.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_stats( $request ) {
		// All items = Enabled + Disabled (not trash).
		$all_query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => self::inventory_statuses(),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$all = (int) $all_query->found_posts;

		$instock    = self::count_stock_enabled_products( 'instock' );
		$outofstock = self::count_stock_enabled_products( 'outofstock' );
		$disabled   = count( self::get_disabled_product_ids() );

		return rest_ensure_response(
			array(
				'all'        => $all,
				'instock'    => $instock,
				'outofstock' => $outofstock,
				'disabled'   => $disabled,
			)
		);
	}

	/**
	 * Count products for a WC_Product_Query arg set.
	 *
	 * @param array $args Query args.
	 * @return int
	 */
	private static function count_products( $args ) {
		$query_args = array_merge(
			$args,
			array(
				'limit'    => 1,
				'page'     => 1,
				'paginate' => true,
				'return'   => 'ids',
			)
		);
		$results = new WC_Product_Query( $query_args );
		$query   = $results->get_products();
		return isset( $query->total ) ? (int) $query->total : 0;
	}

	/**
	 * POST /products/bulk — trash or update many products.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function bulk_products( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$ids = isset( $params['ids'] ) && is_array( $params['ids'] )
			? array_values( array_filter( array_map( 'absint', $params['ids'] ) ) )
			: array();
		$action = isset( $params['action'] ) ? sanitize_key( $params['action'] ) : '';

		$allowed = array( 'trash', 'mark_outofstock', 'mark_instock', 'disable', 'enable' );
		if ( empty( $ids ) || ! in_array( $action, $allowed, true ) ) {
			return new WP_Error(
				'storyphone_im_bulk_invalid',
				__( 'Provide product ids and a valid bulk action.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( count( $ids ) > 50 ) {
			return new WP_Error(
				'storyphone_im_bulk_too_many',
				__( 'You can update at most 50 products at once.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$done = 0;
		foreach ( $ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			if ( 'trash' === $action ) {
				if ( wp_trash_post( $product_id ) ) {
					++$done;
					StoryPhone_IM_Audit_Log::log( 'trash', $product_id, array( 'bulk' => '1' ) );
				}
				continue;
			}

			if ( 'mark_outofstock' === $action ) {
				// Stay listed when Enabled; WooCommerce blocks purchase when OOS.
				$product->set_stock_status( 'outofstock' );
				$product->save();
				self::bust_product_caches( $product_id );
			} elseif ( 'mark_instock' === $action ) {
				$product->set_stock_status( 'instock' );
				$product->save();
				self::bust_product_caches( $product_id );
			} elseif ( 'disable' === $action ) {
				self::apply_product_disabled( $product );
				self::persist_product_visibility( $product );
			} elseif ( 'enable' === $action ) {
				self::apply_product_enabled( $product );
				self::persist_product_visibility( $product );
			}

			++$done;
			StoryPhone_IM_Audit_Log::log( 'update', $product_id, array( 'bulk_action' => $action ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'updated' => $done,
				'action'  => $action,
			)
		);
	}

	/**
	 * GET /products/{id} — full product detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_product( $request ) {
		$product = self::get_wc_product( absint( $request['id'] ) );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		return rest_ensure_response( self::format_product_detail( $product ) );
	}

	/**
	 * POST /products/{id} — update product fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_product( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$product = self::get_wc_product( absint( $request['id'] ) );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$changed = array();

		if ( isset( $params['name'] ) ) {
			$name = sanitize_text_field( $params['name'] );
			$product->set_name( $name );
			$changed['name'] = $name;
		}

		if ( isset( $params['description'] ) ) {
			$desc = wp_kses_post( $params['description'] );
			$product->set_description( $desc );
			$changed['description'] = 'updated';

			// Many themes show the short description on the product page.
			// Keep them in sync unless short_description is sent explicitly.
			if ( ! array_key_exists( 'short_description', $params ) ) {
				$product->set_short_description( $desc );
				$changed['short_description'] = 'synced';
			}
		}

		if ( array_key_exists( 'short_description', $params ) ) {
			$product->set_short_description( wp_kses_post( $params['short_description'] ) );
			$changed['short_description'] = 'updated';
		}

		if ( isset( $params['sku'] ) ) {
			$sku = wc_clean( $params['sku'] );
			if ( '' !== $sku ) {
				$existing_id = wc_get_product_id_by_sku( $sku );
				if ( $existing_id && (int) $existing_id !== (int) $product->get_id() ) {
					return new WP_Error(
						'storyphone_im_sku_exists',
						__( 'Another product already uses this SKU.', 'storyphone-inventory-manager' ),
						array( 'status' => 400 )
					);
				}
			}
			$product->set_sku( $sku );
			$changed['sku'] = $sku;
		}

		if ( isset( $params['price'] ) ) {
			$price = wc_format_decimal( wc_clean( $params['price'] ) );
			$product->set_regular_price( $price );
			// Keep sale price logic intact: if no sale price, regular becomes active price.
			if ( '' === $product->get_sale_price( 'edit' ) ) {
				$product->set_price( $price );
			} else {
				$product->set_price( $product->get_sale_price( 'edit' ) );
			}
			$changed['price'] = $price;
		}

		if ( array_key_exists( 'stock_quantity', $params ) ) {
			$qty = $params['stock_quantity'];
			if ( null === $qty || '' === $qty ) {
				$product->set_manage_stock( false );
				$product->set_stock_quantity( null );
				$changed['stock_quantity'] = '';
			} else {
				$qty = absint( $qty );
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $qty );
				$changed['stock_quantity'] = (string) $qty;
			}
		}

		if ( isset( $params['stock_status'] ) ) {
			$status  = sanitize_text_field( $params['stock_status'] );
			$allowed = array( 'instock', 'outofstock', 'onbackorder' );
			if ( in_array( $status, $allowed, true ) ) {
				$product->set_stock_status( $status );
				$changed['stock_status'] = $status;
			}
		}

		if ( isset( $params['status'] ) ) {
			$post_status     = sanitize_key( $params['status'] );
			$allowed_status  = array( 'publish', 'draft', 'private', 'pending' );
			if ( in_array( $post_status, $allowed_status, true ) ) {
				$product->set_status( $post_status );
				$changed['status'] = $post_status;
			}
		}

		if ( isset( $params['catalog_visibility'] ) ) {
			$visibility = sanitize_key( $params['catalog_visibility'] );
			$allowed_vis = array( 'visible', 'catalog', 'search', 'hidden' );
			if ( in_array( $visibility, $allowed_vis, true ) ) {
				$product->set_catalog_visibility( $visibility );
				$changed['catalog_visibility'] = $visibility;
			}
		}

		// Convenience flag: Enabled = shop + search; Disabled = draft + hidden (not on site).
		if ( array_key_exists( 'enabled', $params ) ) {
			$enabled = filter_var( $params['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if ( null === $enabled ) {
				$enabled = (bool) $params['enabled'];
			}
			if ( $enabled ) {
				self::apply_product_enabled( $product );
				$changed['enabled']             = '1';
				$changed['status']              = 'publish';
				$changed['catalog_visibility']  = 'visible';
			} else {
				self::apply_product_disabled( $product );
				$changed['enabled']             = '0';
				$changed['status']              = 'draft';
				$changed['catalog_visibility']  = 'hidden';
			}
		}

		if ( isset( $params['category_ids'] ) && is_array( $params['category_ids'] ) ) {
			$cat_ids = array_map( 'absint', $params['category_ids'] );
			$cat_ids = array_filter( $cat_ids );
			$product->set_category_ids( $cat_ids );
			$changed['category_ids'] = implode( ',', $cat_ids );
		}

		// Set which image is the storefront default (featured image).
		if ( array_key_exists( 'image_id', $params ) ) {
			$featured_id = absint( $params['image_id'] );
			$result      = self::assign_product_featured_image( $product, $featured_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$product                   = $result;
			$changed['image_id']       = (string) (int) $product->get_image_id();
			$changed['featured_sync']  = '1';
		} else {
			$product->save();
			self::bust_product_caches( $product->get_id() );
		}

		// Force exclude-from-search/catalog terms when Disabled (draft or hidden).
		if ( isset( $changed['enabled'] ) && '0' === $changed['enabled'] ) {
			StoryPhone_IM_Storefront_Visibility::sync_hidden_visibility_terms( $product->get_id() );
			self::bust_product_caches( $product->get_id() );
		}

		StoryPhone_IM_Audit_Log::log( 'update', $product->get_id(), $changed );

		// Reload so response matches what the storefront will read.
		$product = wc_get_product( $product->get_id() );

		return rest_ensure_response(
			array(
				'success' => true,
				'product' => self::format_product_detail( $product ),
			)
		);
	}

	/**
	 * POST /products — create a new product.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_product( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$name  = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		$price = isset( $params['price'] ) ? wc_format_decimal( wc_clean( $params['price'] ) ) : '';
		$sku   = isset( $params['sku'] ) ? wc_clean( $params['sku'] ) : '';

		if ( '' === $name ) {
			return new WP_Error(
				'storyphone_im_missing_name',
				__( 'Product name is required.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $price && '0' !== (string) $price ) {
			return new WP_Error(
				'storyphone_im_missing_price',
				__( 'Product price is required.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $sku ) {
			return new WP_Error(
				'storyphone_im_missing_sku',
				__( 'Product SKU is required.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$existing_id = wc_get_product_id_by_sku( $sku );
		if ( $existing_id ) {
			return new WP_Error(
				'storyphone_im_sku_exists',
				__( 'Another product already uses this SKU.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$stock_status = 'instock';
		if ( isset( $params['stock_status'] ) ) {
			$status  = sanitize_text_field( $params['stock_status'] );
			$allowed = array( 'instock', 'outofstock', 'onbackorder' );
			if ( in_array( $status, $allowed, true ) ) {
				$stock_status = $status;
			}
		}

		// Default to unlimited stock (∞) unless a limited quantity is provided.
		$has_limited_qty = array_key_exists( 'stock_quantity', $params )
			&& null !== $params['stock_quantity']
			&& '' !== $params['stock_quantity'];
		$stock_qty       = $has_limited_qty ? absint( $params['stock_quantity'] ) : null;

		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_sku( $sku );
		$product->set_regular_price( $price );
		$product->set_price( $price );
		if ( $has_limited_qty ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $stock_qty );
		} else {
			$product->set_manage_stock( false );
			$product->set_stock_quantity( null );
		}
		$product->set_stock_status( $stock_status );

		if ( ! empty( $params['description'] ) ) {
			$desc = wp_kses_post( $params['description'] );
			$product->set_description( $desc );
			if ( ! array_key_exists( 'short_description', $params ) ) {
				$product->set_short_description( $desc );
			}
		}

		if ( array_key_exists( 'short_description', $params ) ) {
			$product->set_short_description( wp_kses_post( $params['short_description'] ) );
		}

		// Enabled = publish + visible; Disabled = draft + hidden.
		if ( array_key_exists( 'enabled', $params ) ) {
			$enabled = filter_var( $params['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if ( null === $enabled ) {
				$enabled = (bool) $params['enabled'];
			}
			if ( $enabled ) {
				self::apply_product_enabled( $product );
			} else {
				self::apply_product_disabled( $product );
			}
		}

		$cat_ids = array();
		if ( isset( $params['category_ids'] ) && is_array( $params['category_ids'] ) ) {
			$cat_ids = array_map( 'absint', $params['category_ids'] );
			$cat_ids = array_filter( $cat_ids );
			$product->set_category_ids( $cat_ids );
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			return new WP_Error(
				'storyphone_im_create_failed',
				__( 'Failed to create product.', 'storyphone-inventory-manager' ),
				array( 'status' => 500 )
			);
		}

		if ( array_key_exists( 'enabled', $params ) ) {
			$enabled = filter_var( $params['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if ( null === $enabled ) {
				$enabled = (bool) $params['enabled'];
			}
			if ( ! $enabled ) {
				StoryPhone_IM_Storefront_Visibility::sync_hidden_visibility_terms( $product_id );
			}
		}

		$product = wc_get_product( $product_id );

		StoryPhone_IM_Audit_Log::log(
			'create',
			$product_id,
			array(
				'name'           => $name,
				'sku'            => $sku,
				'price'          => $price,
				'stock_quantity' => $has_limited_qty ? (string) $stock_qty : 'unlimited',
				'stock_status'   => $stock_status,
				'category_ids'   => implode( ',', $cat_ids ),
			)
		);

		$response = rest_ensure_response(
			array(
				'success' => true,
				'product' => self::format_product_detail( $product ),
			)
		);
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * POST /products/{id}/image — upload/replace featured image.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload_product_image( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$product = self::get_wc_product( absint( $request['id'] ) );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		$files = $request->get_file_params();
		if ( empty( $files['image'] ) || empty( $files['image']['tmp_name'] ) ) {
			return new WP_Error(
				'storyphone_im_missing_image',
				__( 'No image file provided. Use multipart field name "image".', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$file = $files['image'];

		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		);

		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
		if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
			return new WP_Error(
				'storyphone_im_invalid_mime',
				__( 'Invalid image type. Allowed: JPEG, PNG, GIF, WebP.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => $allowed_mimes,
		);

		$upload = wp_handle_upload( $file, $overrides );
		if ( isset( $upload['error'] ) ) {
			return new WP_Error(
				'storyphone_im_upload_error',
				sanitize_text_field( $upload['error'] ),
				array( 'status' => 500 )
			);
		}

		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $upload['file'], $product->get_id() );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			return new WP_Error(
				'storyphone_im_attachment_failed',
				__( 'Failed to create media attachment.', 'storyphone-inventory-manager' ),
				array( 'status' => 500 )
			);
		}

		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		$as_featured = true;
		if ( null !== $request->get_param( 'as_featured' ) ) {
			$as_featured = filter_var( $request->get_param( 'as_featured' ), FILTER_VALIDATE_BOOLEAN );
		}

		$current_featured = (int) $product->get_image_id();
		$gallery          = array_map( 'absint', $product->get_gallery_image_ids() );

		if ( $as_featured || $current_featured < 1 ) {
			if ( $current_featured > 0 && $current_featured !== (int) $attach_id ) {
				$gallery[] = $current_featured;
			}
			$gallery = array_values(
				array_filter(
					array_unique( $gallery ),
					static function ( $gid ) use ( $attach_id ) {
						return (int) $gid > 0 && (int) $gid !== (int) $attach_id;
					}
				)
			);
			$product->set_image_id( (int) $attach_id );
			$product->set_gallery_image_ids( $gallery );
			$product->save();
			$product = self::persist_product_image_meta( $product );
		} else {
			$gallery[] = (int) $attach_id;
			$gallery   = array_values(
				array_filter(
					array_unique( $gallery ),
					static function ( $gid ) use ( $current_featured ) {
						return (int) $gid > 0 && (int) $gid !== $current_featured;
					}
				)
			);
			$product->set_gallery_image_ids( $gallery );
			$product->save();
			$product = self::persist_product_image_meta( $product );
		}

		StoryPhone_IM_Audit_Log::log(
			'upload_image',
			$product->get_id(),
			array(
				'image_id' => (string) $attach_id,
			)
		);

		$image_url = wp_get_attachment_image_url( $attach_id, 'medium' );
		if ( ! $image_url ) {
			$image_url = wp_get_attachment_url( $attach_id );
		}

		$product = wc_get_product( $product->get_id() );

		return rest_ensure_response(
			array(
				'success'   => true,
				'image_id'  => (int) $attach_id,
				'image_url' => esc_url_raw( $image_url ? $image_url : '' ),
				'product'   => self::format_product_detail( $product ),
			)
		);
	}

	/**
	 * DELETE /products/{id}/image — remove a product image (featured or gallery).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_product_image( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$product = self::get_wc_product( absint( $request['id'] ) );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		$image_id = absint( $request->get_param( 'image_id' ) );
		if ( $image_id < 1 ) {
			$image_id = (int) $product->get_image_id();
		}
		if ( $image_id < 1 ) {
			return new WP_Error(
				'storyphone_im_no_image',
				__( 'No image to delete.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$featured = (int) $product->get_image_id();
		$gallery  = array_map( 'absint', $product->get_gallery_image_ids() );
		$all_ids  = array_unique( array_filter( array_merge( array( $featured ), $gallery ) ) );

		if ( ! in_array( $image_id, $all_ids, true ) ) {
			return new WP_Error(
				'storyphone_im_image_not_on_product',
				__( 'That image is not attached to this product.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$gallery = array_values(
			array_filter(
				$gallery,
				static function ( $gid ) use ( $image_id ) {
					return (int) $gid !== (int) $image_id;
				}
			)
		);

		if ( $featured === $image_id ) {
			$new_featured = ! empty( $gallery ) ? (int) $gallery[0] : 0;
			if ( $new_featured > 0 ) {
				array_shift( $gallery );
			}
			$product->set_image_id( $new_featured );
			$product->set_gallery_image_ids( $gallery );
			$product->save();
			$product = self::persist_product_image_meta( $product );
		} else {
			$product->set_gallery_image_ids( $gallery );
			$product->save();
			$product = self::persist_product_image_meta( $product );
		}

		// Remove from media library as well (soft-delete to trash).
		wp_delete_attachment( $image_id, false );

		StoryPhone_IM_Audit_Log::log(
			'update',
			$product->get_id(),
			array(
				'deleted_image_id' => (string) $image_id,
			)
		);

		$product = wc_get_product( $product->get_id() );

		return rest_ensure_response(
			array(
				'success' => true,
				'product' => self::format_product_detail( $product ),
			)
		);
	}

	/**
	 * DELETE /products/{id} — move product to trash (not permanent delete).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function trash_product( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$product_id = absint( $request['id'] );
		$product    = self::get_wc_product( $product_id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		$result = wp_trash_post( $product_id );
		if ( ! $result ) {
			return new WP_Error(
				'storyphone_im_trash_failed',
				__( 'Failed to move product to trash.', 'storyphone-inventory-manager' ),
				array( 'status' => 500 )
			);
		}

		StoryPhone_IM_Audit_Log::log(
			'trash',
			$product_id,
			array(
				'name' => $product->get_name(),
				'sku'  => $product->get_sku(),
			)
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'product_id' => $product_id,
				'status'     => 'trash',
				'message'    => __( 'Product moved to trash.', 'storyphone-inventory-manager' ),
			)
		);
	}

	/**
	 * POST /categories — create a product category (or subcategory).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	/**
	 * POST /media — upload an image for use inside product descriptions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload_media( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$files = $request->get_file_params();
		if ( empty( $files['image'] ) || empty( $files['image']['tmp_name'] ) ) {
			return new WP_Error(
				'storyphone_im_missing_image',
				__( 'No image file provided. Use multipart field name "image".', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$file = $files['image'];

		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		);

		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
		if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
			return new WP_Error(
				'storyphone_im_invalid_mime',
				__( 'Invalid image type. Allowed: JPEG, PNG, GIF, WebP.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $allowed_mimes,
			)
		);
		if ( isset( $upload['error'] ) ) {
			return new WP_Error(
				'storyphone_im_upload_error',
				sanitize_text_field( $upload['error'] ),
				array( 'status' => 500 )
			);
		}

		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $upload['file'] );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			return new WP_Error(
				'storyphone_im_attachment_failed',
				__( 'Failed to create media attachment.', 'storyphone-inventory-manager' ),
				array( 'status' => 500 )
			);
		}

		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		$image_url = wp_get_attachment_image_url( $attach_id, 'large' );
		if ( ! $image_url ) {
			$image_url = wp_get_attachment_url( $attach_id );
		}

		StoryPhone_IM_Audit_Log::log(
			'upload_image',
			0,
			array(
				'media_id' => (string) $attach_id,
				'context'  => 'description',
			)
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'id'       => (int) $attach_id,
				'url'      => esc_url_raw( $image_url ? $image_url : '' ),
			)
		);
	}

	/**
	 * Format a product_cat term for the inventory UI.
	 *
	 * @param WP_Term $term Term.
	 * @return array
	 */
	private static function format_category( $term ) {
		$thumb_id  = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
		$icon_size = absint( get_term_meta( $term->term_id, 'storyphone_icon_size', true ) );
		if ( $icon_size < 1 ) {
			$icon_size = 64;
		}

		$thumb_url = '';
		if ( $thumb_id > 0 ) {
			$thumb_url = wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
			if ( ! $thumb_url ) {
				$thumb_url = wp_get_attachment_url( $thumb_id );
			}
		}

		return array(
			'id'            => (int) $term->term_id,
			'name'          => $term->name,
			'slug'          => $term->slug,
			'parent'        => (int) $term->parent,
			'count'         => (int) $term->count,
			'thumbnail_id'  => $thumb_id,
			'thumbnail_url' => $thumb_url ? esc_url_raw( $thumb_url ) : '',
			'icon_size'     => $icon_size,
		);
	}

	/**
	 * Whether $maybe_descendant is the same as or under $ancestor_id.
	 *
	 * @param int $ancestor_id       Ancestor term ID.
	 * @param int $maybe_descendant  Candidate term ID.
	 * @return bool
	 */
	private static function category_is_ancestor_of( $ancestor_id, $maybe_descendant ) {
		$ancestor_id      = absint( $ancestor_id );
		$maybe_descendant = absint( $maybe_descendant );
		if ( $ancestor_id < 1 || $maybe_descendant < 1 ) {
			return false;
		}
		if ( $ancestor_id === $maybe_descendant ) {
			return true;
		}

		$current = $maybe_descendant;
		$seen    = array();
		while ( $current > 0 && empty( $seen[ $current ] ) ) {
			$seen[ $current ] = true;
			$term             = get_term( $current, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				break;
			}
			$parent = (int) $term->parent;
			if ( $parent === $ancestor_id ) {
				return true;
			}
			$current = $parent;
		}
		return false;
	}

	/**
	 * Validate a parent assignment for a category.
	 *
	 * @param int $term_id Category ID (0 when creating).
	 * @param int $parent  Proposed parent ID.
	 * @return true|WP_Error
	 */
	private static function validate_category_parent( $term_id, $parent ) {
		$term_id = absint( $term_id );
		$parent  = absint( $parent );

		if ( 0 === $parent ) {
			return true;
		}

		$parent_term = get_term( $parent, 'product_cat' );
		if ( ! $parent_term || is_wp_error( $parent_term ) ) {
			return new WP_Error(
				'storyphone_im_category_parent',
				__( 'Parent category not found.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( $term_id > 0 && $parent === $term_id ) {
			return new WP_Error(
				'storyphone_im_category_parent',
				__( 'A category cannot be its own parent.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		// Parent cannot be a descendant of this category (would create a cycle).
		if ( $term_id > 0 && self::category_is_ancestor_of( $term_id, $parent ) ) {
			return new WP_Error(
				'storyphone_im_category_parent_cycle',
				__( 'Cannot set a child category as the parent.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Save category icon (WC thumbnail) and recommended icon size.
	 *
	 * @param int   $term_id Term ID.
	 * @param array $params  Request params.
	 * @return void
	 */
	private static function save_category_icon_meta( $term_id, $params ) {
		$term_id = absint( $term_id );
		if ( $term_id < 1 || ! is_array( $params ) ) {
			return;
		}

		if ( array_key_exists( 'thumbnail_id', $params ) ) {
			$thumb_id = absint( $params['thumbnail_id'] );
			if ( $thumb_id > 0 ) {
				update_term_meta( $term_id, 'thumbnail_id', $thumb_id );
			} else {
				delete_term_meta( $term_id, 'thumbnail_id' );
			}
		}

		if ( array_key_exists( 'icon_size', $params ) ) {
			$size     = absint( $params['icon_size'] );
			$allowed  = array( 32, 48, 64, 96, 128, 256 );
			if ( in_array( $size, $allowed, true ) ) {
				update_term_meta( $term_id, 'storyphone_icon_size', $size );
			}
		}
	}

	/**
	 * POST /categories — create a product category (or subcategory).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_category( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error(
				'storyphone_im_category_name',
				__( 'Category name is required.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$parent   = isset( $params['parent'] ) ? absint( $params['parent'] ) : 0;
		$parent_ok = self::validate_category_parent( 0, $parent );
		if ( is_wp_error( $parent_ok ) ) {
			return $parent_ok;
		}

		$result = wp_insert_term(
			$name,
			'product_cat',
			array(
				'parent' => $parent,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term_id = (int) $result['term_id'];
		self::save_category_icon_meta( $term_id, $params );
		$term = get_term( $term_id, 'product_cat' );

		StoryPhone_IM_Audit_Log::log(
			'create',
			0,
			array(
				'category_id' => (string) $term_id,
				'name'        => $name,
				'parent'      => (string) $parent,
			)
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'category' => self::format_category( $term ),
			)
		);
	}

	/**
	 * GET /categories — list WooCommerce product categories.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_categories( $request ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$categories = array();
		foreach ( $terms as $term ) {
			$categories[] = self::format_category( $term );
		}

		return rest_ensure_response( array( 'categories' => $categories ) );
	}

	/**
	 * POST/PUT /categories/{id} — rename, reparent, or update icon for a category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_category( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$term_id = absint( $request['id'] );
		$term    = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error(
				'storyphone_im_category_not_found',
				__( 'Category not found.', 'storyphone-inventory-manager' ),
				array( 'status' => 404 )
			);
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$args    = array();
		$changed = array();

		if ( isset( $params['name'] ) ) {
			$name = sanitize_text_field( $params['name'] );
			if ( '' === $name ) {
				return new WP_Error(
					'storyphone_im_category_name',
					__( 'Category name is required.', 'storyphone-inventory-manager' ),
					array( 'status' => 400 )
				);
			}
			$args['name'] = $name;
			$changed[]    = 'name';
		}

		if ( array_key_exists( 'parent', $params ) ) {
			$parent    = absint( $params['parent'] );
			$parent_ok = self::validate_category_parent( $term_id, $parent );
			if ( is_wp_error( $parent_ok ) ) {
				return $parent_ok;
			}
			$args['parent'] = $parent;
			$changed[]      = 'parent';
		}

		$meta_keys = array( 'thumbnail_id', 'icon_size' );
		$has_meta  = false;
		foreach ( $meta_keys as $key ) {
			if ( array_key_exists( $key, $params ) ) {
				$has_meta  = true;
				$changed[] = $key;
			}
		}

		if ( empty( $args ) && ! $has_meta ) {
			return new WP_Error(
				'storyphone_im_category_empty',
				__( 'Nothing to update.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $args ) ) {
			$result = wp_update_term( $term_id, 'product_cat', $args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( $has_meta ) {
			self::save_category_icon_meta( $term_id, $params );
		}

		$updated = get_term( $term_id, 'product_cat' );
		StoryPhone_IM_Audit_Log::log(
			'update',
			0,
			array(
				'category_id' => (string) $term_id,
				'fields'      => implode( ',', $changed ),
			)
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'category' => self::format_category( $updated ),
			)
		);
	}

	/**
	 * POST /categories/bulk — bulk set parent or delete categories.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function bulk_categories( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$action = isset( $params['action'] ) ? sanitize_key( $params['action'] ) : '';
		$ids    = isset( $params['ids'] ) && is_array( $params['ids'] )
			? array_values( array_filter( array_map( 'absint', $params['ids'] ) ) )
			: array();

		$allowed = array( 'set_parent', 'delete' );
		if ( ! in_array( $action, $allowed, true ) || empty( $ids ) ) {
			return new WP_Error(
				'storyphone_im_bulk_category_invalid',
				__( 'Provide category ids and action set_parent or delete.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( count( $ids ) > 50 ) {
			return new WP_Error(
				'storyphone_im_bulk_too_many',
				__( 'You can update at most 50 categories at once.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( 'delete' === $action ) {
			return self::bulk_delete_categories( $ids );
		}

		$parent  = isset( $params['parent'] ) ? absint( $params['parent'] ) : 0;
		$updated = 0;
		$skipped = 0;

		foreach ( $ids as $term_id ) {
			if ( $parent > 0 && $parent === $term_id ) {
				++$skipped;
				continue;
			}

			$term = get_term( $term_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				++$skipped;
				continue;
			}

			$parent_ok = self::validate_category_parent( $term_id, $parent );
			if ( is_wp_error( $parent_ok ) ) {
				++$skipped;
				continue;
			}

			$result = wp_update_term( $term_id, 'product_cat', array( 'parent' => $parent ) );
			if ( is_wp_error( $result ) ) {
				++$skipped;
				continue;
			}

			++$updated;
			StoryPhone_IM_Audit_Log::log(
				'update',
				0,
				array(
					'category_id' => (string) $term_id,
					'bulk'        => 'set_parent',
					'parent'      => (string) $parent,
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'updated' => $updated,
				'skipped' => $skipped,
				'action'  => 'set_parent',
				'parent'  => $parent,
			)
		);
	}

	/**
	 * Bulk-delete product categories (skips terms that still have children).
	 *
	 * @param int[] $ids Term IDs.
	 * @return WP_REST_Response
	 */
	private static function bulk_delete_categories( array $ids ) {
		$deleted = 0;
		$skipped = 0;

		// Delete deepest terms first so parents can succeed in the same batch.
		usort(
			$ids,
			static function ( $a, $b ) {
				$ta = get_term( (int) $a, 'product_cat' );
				$tb = get_term( (int) $b, 'product_cat' );
				$da = ( $ta && ! is_wp_error( $ta ) ) ? count( get_ancestors( (int) $a, 'product_cat' ) ) : 0;
				$db = ( $tb && ! is_wp_error( $tb ) ) ? count( get_ancestors( (int) $b, 'product_cat' ) ) : 0;
				return $db - $da;
			}
		);

		foreach ( $ids as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				++$skipped;
				continue;
			}

			$children = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => $term_id,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				++$skipped;
				continue;
			}

			$result = wp_delete_term( $term_id, 'product_cat' );
			if ( is_wp_error( $result ) || ! $result ) {
				++$skipped;
				continue;
			}

			++$deleted;
			StoryPhone_IM_Audit_Log::log(
				'trash',
				0,
				array(
					'category_id'   => (string) $term_id,
					'category_name' => $term->name,
					'bulk'          => 'delete',
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'deleted' => $deleted,
				'skipped' => $skipped,
				'action'  => 'delete',
			)
		);
	}

	/**
	 * DELETE /categories/{id} — delete a product category term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_category( $request ) {
		$rate = StoryPhone_IM_Audit_Log::check_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$term_id = absint( $request['id'] );
		$term    = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error(
				'storyphone_im_category_not_found',
				__( 'Category not found.', 'storyphone-inventory-manager' ),
				array( 'status' => 404 )
			);
		}

		$children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $term_id,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
			return new WP_Error(
				'storyphone_im_category_has_children',
				__( 'Delete or move child categories first.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$result = wp_delete_term( $term_id, 'product_cat' );
		if ( is_wp_error( $result ) || ! $result ) {
			return new WP_Error(
				'storyphone_im_category_delete_failed',
				__( 'Could not delete category.', 'storyphone-inventory-manager' ),
				array( 'status' => 500 )
			);
		}

		StoryPhone_IM_Audit_Log::log(
			'trash',
			0,
			array(
				'category_id'   => (string) $term_id,
				'category_name' => $term->name,
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'deleted' => $term_id,
			)
		);
	}

	/**
	 * Assign featured image and keep gallery/meta in sync for shop + single product.
	 *
	 * @param WC_Product $product     Product.
	 * @param int        $featured_id Attachment ID (0 to clear).
	 * @return WC_Product|WP_Error
	 */
	private static function assign_product_featured_image( $product, $featured_id ) {
		$featured_id = absint( $featured_id );
		$current_id  = (int) $product->get_image_id();
		$gallery     = array_values( array_filter( array_map( 'absint', $product->get_gallery_image_ids() ) ) );

		if ( $featured_id > 0 ) {
			$known = array_unique( array_filter( array_merge( array( $current_id ), $gallery ) ) );
			if ( ! in_array( $featured_id, $known, true ) && ! wp_attachment_is_image( $featured_id ) ) {
				return new WP_Error(
					'storyphone_im_invalid_image',
					__( 'Image not found for this product.', 'storyphone-inventory-manager' ),
					array( 'status' => 400 )
				);
			}

			if ( $current_id > 0 && $current_id !== $featured_id && ! in_array( $current_id, $gallery, true ) ) {
				$gallery[] = $current_id;
			}

			$gallery = array_values(
				array_filter(
					array_unique( $gallery ),
					static function ( $gid ) use ( $featured_id ) {
						return (int) $gid !== (int) $featured_id;
					}
				)
			);

			$product->set_image_id( $featured_id );
			$product->set_gallery_image_ids( $gallery );
		} else {
			if ( $current_id > 0 && ! in_array( $current_id, $gallery, true ) ) {
				$gallery[] = $current_id;
			}
			$product->set_image_id( 0 );
			$product->set_gallery_image_ids( array_values( array_unique( array_filter( $gallery ) ) ) );
		}

		$product->save();
		return self::persist_product_image_meta( $product );
	}

	/**
	 * Force WordPress/Woo meta + attachment order so single product pages
	 * (not only catalog thumbnails) pick up the featured image.
	 *
	 * @param WC_Product $product Product after CRUD save.
	 * @return WC_Product
	 */
	private static function persist_product_image_meta( $product ) {
		$product_id  = $product->get_id();
		$featured_id = (int) $product->get_image_id();
		$gallery     = array_values(
			array_filter(
				array_map( 'absint', $product->get_gallery_image_ids() ),
				static function ( $gid ) use ( $featured_id ) {
					return $gid > 0 && $gid !== $featured_id;
				}
			)
		);

		// Canonical Woo/WP meta used by most themes & builders.
		if ( $featured_id > 0 ) {
			set_post_thumbnail( $product_id, $featured_id );
			update_post_meta( $product_id, '_thumbnail_id', $featured_id );
			wp_update_post(
				array(
					'ID'         => $featured_id,
					'post_parent'=> $product_id,
					'menu_order' => 0,
				)
			);
		} else {
			delete_post_thumbnail( $product_id );
			delete_post_meta( $product_id, '_thumbnail_id' );
		}

		update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery ) );

		// Keep gallery attachment order predictable for themes that sort by menu_order.
		$order = 1;
		foreach ( $gallery as $gid ) {
			wp_update_post(
				array(
					'ID'          => $gid,
					'post_parent' => $product_id,
					'menu_order'  => $order,
				)
			);
			++$order;
		}

		// Re-apply via CRUD on a fresh object so in-memory state matches meta.
		$fresh = wc_get_product( $product_id );
		if ( $fresh ) {
			$fresh->set_image_id( $featured_id > 0 ? $featured_id : '' );
			$fresh->set_gallery_image_ids( $gallery );
			$fresh->save();
			$product = $fresh;
		}

		self::bust_product_caches( $product_id );

		$reloaded = wc_get_product( $product_id );
		return $reloaded ? $reloaded : $product;
	}

	/**
	 * Disable a product: draft + catalog hidden (not on site / search / cart).
	 *
	 * @param WC_Product $product Product.
	 * @return void
	 */
	private static function apply_product_disabled( $product ) {
		if ( 'trash' === $product->get_status() ) {
			return;
		}
		$product->set_status( 'draft' );
		$product->set_catalog_visibility( 'hidden' );
	}

	/**
	 * Enable a product: published + fully visible in shop and search.
	 *
	 * @param WC_Product $product Product.
	 * @return void
	 */
	private static function apply_product_enabled( $product ) {
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
	}

	/**
	 * Persist disable/enable and force visibility terms + cache bust.
	 *
	 * @param WC_Product $product Product.
	 * @return void
	 */
	private static function persist_product_visibility( $product ) {
		$product_id = $product->get_id();
		$product->save();
		if ( 'hidden' === $product->get_catalog_visibility() || 'publish' !== $product->get_status() ) {
			StoryPhone_IM_Storefront_Visibility::sync_hidden_visibility_terms( $product_id );
		}
		self::bust_product_caches( $product_id );
	}

	/**
	 * Clear product/page caches so catalog + single product refresh.
	 *
	 * @param int $product_id Product ID.
	 */
	private static function bust_product_caches( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return;
		}

		clean_post_cache( $product_id );
		wc_delete_product_transients( $product_id );
		wp_cache_delete( 'product-' . $product_id, 'products' );

		if ( class_exists( 'WC_Cache_Helper' ) ) {
			if ( method_exists( 'WC_Cache_Helper', 'invalidate_cache_group' ) ) {
				WC_Cache_Helper::invalidate_cache_group( 'products' );
			}
		}

		// Elementor / common page caches on Woo storefronts.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Ignore cache clear failures.
			}
		}
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $product_id );
		}
		if ( function_exists( 'lite_speed_purge_url' ) ) {
			lite_speed_purge_url( get_permalink( $product_id ) );
		}
		do_action( 'litespeed_purge_post', $product_id );
		do_action( 'storyphone_im_product_cache_cleared', $product_id );
	}

	/**
	 * Load a WC product or return WP_Error.
	 *
	 * @param int $product_id Product ID.
	 * @return WC_Product|WP_Error
	 */
	private static function get_wc_product( $product_id ) {
		if ( $product_id < 1 ) {
			return new WP_Error(
				'storyphone_im_invalid_id',
				__( 'Invalid product ID.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error(
				'storyphone_im_not_found',
				__( 'Product not found.', 'storyphone-inventory-manager' ),
				array( 'status' => 404 )
			);
		}

		return $product;
	}

	/**
	 * Format product for list/grid views.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private static function format_product_summary( $product ) {
		$image_id  = $product->get_image_id();
		$image_url = '';
		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
			if ( ! $image_url ) {
				$image_url = wp_get_attachment_url( $image_id );
			}
		}

		$categories = array();
		$term_ids   = $product->get_category_ids();
		if ( ! empty( $term_ids ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'include'    => $term_ids,
					'hide_empty' => false,
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$categories[] = array(
						'id'   => (int) $term->term_id,
						'name' => $term->name,
					);
				}
			}
		}

		$visibility = $product->get_catalog_visibility();
		$post_status = $product->get_status();
		$is_disabled = ( 'hidden' === $visibility ) || ! in_array( $post_status, array( 'publish' ), true );

		return array(
			'id'                 => $product->get_id(),
			'name'               => $product->get_name(),
			'sku'                => $product->get_sku(),
			'price'              => $product->get_regular_price(),
			'stock_qty'          => $product->get_stock_quantity(),
			'manage_stock'       => (bool) $product->get_manage_stock(),
			'stock_status'       => $product->get_stock_status(),
			'status'             => $post_status,
			'catalog_visibility' => $visibility,
			'enabled'            => ! $is_disabled,
			'image'              => $image_url ? esc_url_raw( $image_url ) : '',
			'categories'         => $categories,
		);
	}

	/**
	 * Format full product detail for edit panel.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private static function format_product_detail( $product ) {
		$summary = self::format_product_summary( $product );

		$image_id  = (int) $product->get_image_id();
		$image_url = '';
		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'medium' );
			if ( ! $image_url ) {
				$image_url = wp_get_attachment_url( $image_id );
			}
		}

		$images   = array();
		$gallery  = array_map( 'absint', $product->get_gallery_image_ids() );
		$all_ids  = array();
		if ( $image_id > 0 ) {
			$all_ids[] = $image_id;
		}
		foreach ( $gallery as $gid ) {
			if ( $gid > 0 && ! in_array( $gid, $all_ids, true ) ) {
				$all_ids[] = $gid;
			}
		}

		foreach ( $all_ids as $aid ) {
			$url = wp_get_attachment_image_url( $aid, 'medium' );
			if ( ! $url ) {
				$url = wp_get_attachment_url( $aid );
			}
			$images[] = array(
				'id'          => (int) $aid,
				'url'         => $url ? esc_url_raw( $url ) : '',
				'is_featured' => (int) $aid === $image_id,
			);
		}

		$description       = $product->get_description();
		$short_description = $product->get_short_description();

		return array_merge(
			$summary,
			array(
				'description'       => $description,
				'short_description' => $short_description,
				// Prefer full description; fall back so the editor is never blank if only short exists.
				'edit_description'  => $description ? $description : $short_description,
				'manage_stock'      => $product->get_manage_stock(),
				'image_id'          => $image_id,
				'image'             => $image_url ? esc_url_raw( $image_url ) : $summary['image'],
				'images'            => $images,
				'category_ids'      => array_map( 'absint', $product->get_category_ids() ),
				'status'            => $product->get_status(),
				'type'              => $product->get_type(),
			)
		);
	}
}
