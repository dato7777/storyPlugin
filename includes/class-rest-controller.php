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
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'upload_product_image' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_categories' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
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

		$allowed_collections = array( 'all', 'outofstock', 'disabled' );
		if ( ! in_array( $collection, $allowed_collections, true ) ) {
			$collection = 'all';
		}

		$allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'any' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'publish';
		}

		// Collection presets override status / stock filters.
		if ( 'outofstock' === $collection ) {
			$status       = 'publish';
			$stock_status = 'outofstock';
		} elseif ( 'disabled' === $collection ) {
			$status       = array( 'draft', 'private' );
			$stock_status = '';
		} elseif ( 'all' === $collection ) {
			$status = 'publish';
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

		$response = rest_ensure_response(
			array(
				'products'   => $products,
				'total'      => isset( $query->total ) ? (int) $query->total : count( $products ),
				'pages'      => isset( $query->max_num_pages ) ? (int) $query->max_num_pages : 1,
				'page'       => $page,
				'per_page'   => $per_page,
				'collection' => $collection,
			)
		);

		return $response;
	}

	/**
	 * GET /stats — collection counts for sidebar.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_stats( $request ) {
		$all = self::count_products(
			array(
				'status' => 'publish',
			)
		);
		$outofstock = self::count_products(
			array(
				'status'       => 'publish',
				'stock_status' => 'outofstock',
			)
		);
		$disabled = self::count_products(
			array(
				'status' => array( 'draft', 'private' ),
			)
		);

		return rest_ensure_response(
			array(
				'all'        => $all,
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
				$product->set_stock_status( 'outofstock' );
			} elseif ( 'mark_instock' === $action ) {
				$product->set_stock_status( 'instock' );
			} elseif ( 'disable' === $action ) {
				$product->set_status( 'draft' );
			} elseif ( 'enable' === $action ) {
				$product->set_status( 'publish' );
			}

			$product->save();
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
			$product->set_description( wp_kses_post( $params['description'] ) );
			$changed['description'] = 'updated';
		}

		if ( isset( $params['short_description'] ) ) {
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

		if ( isset( $params['stock_quantity'] ) ) {
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

		if ( isset( $params['category_ids'] ) && is_array( $params['category_ids'] ) ) {
			$cat_ids = array_map( 'absint', $params['category_ids'] );
			$cat_ids = array_filter( $cat_ids );
			$product->set_category_ids( $cat_ids );
			$changed['category_ids'] = implode( ',', $cat_ids );
		}

		$product->save();

		StoryPhone_IM_Audit_Log::log( 'update', $product->get_id(), $changed );

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

		$stock_qty = isset( $params['stock_quantity'] ) ? absint( $params['stock_quantity'] ) : 0;

		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_sku( $sku );
		$product->set_regular_price( $price );
		$product->set_price( $price );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $stock_qty );
		$product->set_stock_status( $stock_status );

		if ( ! empty( $params['description'] ) ) {
			$product->set_description( wp_kses_post( $params['description'] ) );
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

		$product = wc_get_product( $product_id );

		StoryPhone_IM_Audit_Log::log(
			'create',
			$product_id,
			array(
				'name'           => $name,
				'sku'            => $sku,
				'price'          => $price,
				'stock_quantity' => (string) $stock_qty,
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

		$product->set_image_id( $attach_id );
		$product->save();

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

		return rest_ensure_response(
			array(
				'success'    => true,
				'image_id'   => (int) $attach_id,
				'image_url'  => esc_url_raw( $image_url ? $image_url : '' ),
				'product'    => self::format_product_detail( $product ),
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
			$categories[] = array(
				'id'     => (int) $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'parent' => (int) $term->parent,
				'count'  => (int) $term->count,
			);
		}

		return rest_ensure_response( array( 'categories' => $categories ) );
	}

	/**
	 * POST/PUT /categories/{id} — rename or reparent a product category.
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

		$args = array();
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
		}

		if ( array_key_exists( 'parent', $params ) ) {
			$parent = absint( $params['parent'] );
			if ( $parent === $term_id ) {
				return new WP_Error(
					'storyphone_im_category_parent',
					__( 'A category cannot be its own parent.', 'storyphone-inventory-manager' ),
					array( 'status' => 400 )
				);
			}
			$args['parent'] = $parent;
		}

		if ( empty( $args ) ) {
			return new WP_Error(
				'storyphone_im_category_empty',
				__( 'Nothing to update.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$result = wp_update_term( $term_id, 'product_cat', $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$updated = get_term( $term_id, 'product_cat' );
		StoryPhone_IM_Audit_Log::log(
			'update',
			0,
			array(
				'category_id' => (string) $term_id,
				'fields'      => implode( ',', array_keys( $args ) ),
			)
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'category' => array(
					'id'     => (int) $updated->term_id,
					'name'   => $updated->name,
					'slug'   => $updated->slug,
					'parent' => (int) $updated->parent,
					'count'  => (int) $updated->count,
				),
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

		return array(
			'id'           => $product->get_id(),
			'name'         => $product->get_name(),
			'sku'          => $product->get_sku(),
			'price'        => $product->get_regular_price(),
			'stock_qty'    => $product->get_stock_quantity(),
			'stock_status' => $product->get_stock_status(),
			'status'       => $product->get_status(),
			'image'        => $image_url ? esc_url_raw( $image_url ) : '',
			'categories'   => $categories,
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

		$image_id  = $product->get_image_id();
		$image_url = '';
		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'medium' );
			if ( ! $image_url ) {
				$image_url = wp_get_attachment_url( $image_id );
			}
		}

		return array_merge(
			$summary,
			array(
				'description'       => $product->get_description(),
				'short_description' => $product->get_short_description(),
				'manage_stock'      => $product->get_manage_stock(),
				'image_id'          => $image_id ? (int) $image_id : 0,
				'image'             => $image_url ? esc_url_raw( $image_url ) : $summary['image'],
				'category_ids'      => array_map( 'absint', $product->get_category_ids() ),
				'status'            => $product->get_status(),
				'type'              => $product->get_type(),
			)
		);
	}
}
