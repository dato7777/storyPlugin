<?php
/**
 * New Order (Azure) inventory sync for the Inventory Manager.
 *
 * Token stays server-side. Frontend only talks to our REST proxy.
 *
 * @package StoryPhone_Inventory_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * New Order API client, catalog cache, and REST handlers.
 */
class StoryPhone_IM_NewOrder {

	const OPTION_TOKEN   = 'storyphone_im_neworder_api_token';
	const OPTION_CATALOG = 'storyphone_im_neworder_catalog';
	const BASE_URL       = 'https://neworderapi.azurewebsites.net';
	const PAGE_SIZE      = 2000;

	/**
	 * Hook registrations.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register New Order REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		$ns = StoryPhone_IM_REST_Controller::NAMESPACE;

		register_rest_route(
			$ns,
			'/neworder/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_status' ),
				'permission_callback' => array( 'StoryPhone_IM_REST_Controller', 'check_permissions' ),
			)
		);

		register_rest_route(
			$ns,
			'/neworder/catalog',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_catalog' ),
				'permission_callback' => array( 'StoryPhone_IM_REST_Controller', 'check_permissions' ),
			)
		);

		register_rest_route(
			$ns,
			'/neworder/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'sync_stock' ),
				'permission_callback' => array( 'StoryPhone_IM_REST_Controller', 'check_permissions' ),
			)
		);

		register_rest_route(
			$ns,
			'/neworder/settings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'save_settings' ),
				'permission_callback' => array( 'StoryPhone_IM_REST_Controller', 'check_permissions' ),
			)
		);

		register_rest_route(
			$ns,
			'/neworder/export',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'export_to_disabled' ),
				'permission_callback' => array( 'StoryPhone_IM_REST_Controller', 'check_permissions' ),
			)
		);
	}

	/**
	 * Whether a bearer token is available (constant or option).
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::get_token();
	}

	/**
	 * Resolve API token. Prefer wp-config constant, then stored option.
	 *
	 * @return string
	 */
	public static function get_token() {
		if ( defined( 'STORYPHONE_NEWORDER_API_TOKEN' ) && STORYPHONE_NEWORDER_API_TOKEN ) {
			return self::normalize_token( (string) STORYPHONE_NEWORDER_API_TOKEN );
		}
		$stored = get_option( self::OPTION_TOKEN, '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}
		// Stored as b64:… so filters / sanitize cannot mangle the secret.
		if ( 0 === strpos( $stored, 'b64:' ) ) {
			$decoded = base64_decode( substr( $stored, 4 ), true );
			return self::normalize_token( false === $decoded ? '' : $decoded );
		}
		return self::normalize_token( $stored );
	}

	/**
	 * Strip control chars and optional "Bearer " prefix.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	private static function normalize_token( $token ) {
		$token = trim( (string) $token );
		$token = preg_replace( '/[\x00-\x1F\x7F]/', '', $token );
		if ( ! is_string( $token ) ) {
			return '';
		}
		if ( preg_match( '/^Bearer\s+/i', $token ) ) {
			$token = trim( preg_replace( '/^Bearer\s+/i', '', $token ) );
		}
		return $token;
	}

	/**
	 * Persist token to the options table (autoload on for reliable reads).
	 *
	 * @param string $token Normalized token.
	 * @return bool
	 */
	private static function store_token( $token ) {
		$token = self::normalize_token( $token );
		if ( '' === $token ) {
			delete_option( self::OPTION_TOKEN );
			return true;
		}
		$payload = 'b64:' . base64_encode( $token );
		// Autoload yes — staging/object-cache setups are flaky with autoload=no.
		$ok = update_option( self::OPTION_TOKEN, $payload, true );
		if ( false === $ok && get_option( self::OPTION_TOKEN ) === $payload ) {
			$ok = true; // unchanged value still counts as stored
		}
		return (bool) $ok && self::get_token() === $token;
	}

	/**
	 * GET /neworder/status
	 *
	 * @return WP_REST_Response
	 */
	public static function get_status() {
		$catalog = self::get_cached_catalog();
		return rest_ensure_response(
			array(
				'configured'     => self::is_configured(),
				'token_from'     => self::token_source(),
				'product_count'  => isset( $catalog['products'] ) ? count( $catalog['products'] ) : 0,
				'category_count' => isset( $catalog['categories'] ) ? count( $catalog['categories'] ) : 0,
				'synced_at'      => isset( $catalog['synced_at'] ) ? (string) $catalog['synced_at'] : '',
				'source'         => isset( $catalog['source'] ) ? (string) $catalog['source'] : '',
			)
		);
	}

	/**
	 * GET /neworder/catalog — last synced snapshot.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_catalog() {
		$catalog = self::get_cached_catalog();
		return rest_ensure_response(
			array(
				'configured' => self::is_configured(),
				'categories' => isset( $catalog['categories'] ) ? $catalog['categories'] : array(),
				'products'   => isset( $catalog['products'] ) ? $catalog['products'] : array(),
				'synced_at'  => isset( $catalog['synced_at'] ) ? (string) $catalog['synced_at'] : '',
				'product_count'  => isset( $catalog['products'] ) ? count( $catalog['products'] ) : 0,
				'category_count' => isset( $catalog['categories'] ) ? count( $catalog['categories'] ) : 0,
			)
		);
	}

	/**
	 * POST /neworder/sync — fetch in-stock products from New Order API.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function sync_stock() {
		$token = self::get_token();
		if ( '' === $token ) {
			return new WP_Error(
				'storyphone_im_neworder_no_token',
				__( 'New Order API token is not configured. Save a token on this page or define STORYPHONE_NEWORDER_API_TOKEN in wp-config.php.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		// Large catalog — allow more time for Azure.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$raw_rows = self::fetch_all_in_stock_products( $token );
		if ( is_wp_error( $raw_rows ) ) {
			return $raw_rows;
		}

		$products   = array();
		$categories = array();

		foreach ( $raw_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$normalized = self::normalize_product( $row );
			if ( ! $normalized ) {
				continue;
			}
			$products[] = $normalized;

			$cat_id = (string) $normalized['category_id'];
			if ( '' !== $cat_id && ! isset( $categories[ $cat_id ] ) ) {
				$categories[ $cat_id ] = array(
					'id'    => $cat_id,
					'name'  => $normalized['category_name'] ? $normalized['category_name'] : 'Uncategorized',
					'count' => 0,
				);
			}
			if ( '' !== $cat_id ) {
				++$categories[ $cat_id ]['count'];
			} else {
				if ( ! isset( $categories['__none__'] ) ) {
					$categories['__none__'] = array(
						'id'    => '__none__',
						'name'  => 'Uncategorized',
						'count' => 0,
					);
				}
				++$categories['__none__']['count'];
			}
		}

		$cat_list = array_values( $categories );
		usort(
			$cat_list,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		usort(
			$products,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		$catalog = array(
			'products'   => $products,
			'categories' => $cat_list,
			'synced_at'  => gmdate( 'c' ),
			'source'     => 'Products?stockMode=1',
			'raw_count'  => count( $raw_rows ),
		);

		update_option( self::OPTION_CATALOG, $catalog, false );

		return rest_ensure_response(
			array(
				'ok'             => true,
				'configured'     => true,
				'product_count'  => count( $products ),
				'category_count' => count( $cat_list ),
				'synced_at'      => $catalog['synced_at'],
				'categories'     => $cat_list,
				'products'       => $products,
			)
		);
	}

	/**
	 * POST /neworder/settings — save API token (never returned later in full).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_settings( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		if ( array_key_exists( 'token', $params ) ) {
			$raw = isset( $params['token'] ) ? (string) $params['token'] : '';
			// Do NOT use sanitize_text_field — it can mangle API tokens.
			$token = self::normalize_token( wp_unslash( $raw ) );

			if ( defined( 'STORYPHONE_NEWORDER_API_TOKEN' ) && STORYPHONE_NEWORDER_API_TOKEN ) {
				return rest_ensure_response(
					array(
						'ok'         => true,
						'configured' => true,
						'token_from' => 'constant',
						'message'    => __( 'Token is set in wp-config.php (STORYPHONE_NEWORDER_API_TOKEN). UI save is ignored.', 'storyphone-inventory-manager' ),
					)
				);
			}

			if ( '' === $token ) {
				delete_option( self::OPTION_TOKEN );
			} else {
				$stored = self::store_token( $token );
				if ( ! $stored ) {
					return new WP_Error(
						'storyphone_im_neworder_token_save_failed',
						__( 'Could not persist the New Order API token in the database. Check that the options table is writable.', 'storyphone-inventory-manager' ),
						array( 'status' => 500 )
					);
				}
			}
		}

		return rest_ensure_response(
			array(
				'ok'         => true,
				'configured' => self::is_configured(),
				'token_from' => self::token_source(),
			)
		);
	}

	/**
	 * POST /neworder/export — create Disabled WooCommerce products from cached New Order rows.
	 * SKU is set to the New Order product id.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function export_to_disabled( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$ids = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : array();
		$ids = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $id ) {
							return (string) $id;
						},
						$ids
					)
				)
			)
		);

		if ( empty( $ids ) ) {
			return new WP_Error(
				'storyphone_im_neworder_export_empty',
				__( 'Select at least one New Order product to export.', 'storyphone-inventory-manager' ),
				array( 'status' => 400 )
			);
		}

		$catalog = self::get_cached_catalog();
		$by_id   = array();
		$rows    = ( isset( $catalog['products'] ) && is_array( $catalog['products'] ) ) ? $catalog['products'] : array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['id'] ) ) {
				$by_id[ (string) $row['id'] ] = $row;
			}
		}

		$created = array();
		$updated = array();
		$errors  = array();

		foreach ( $ids as $no_id ) {
			if ( ! isset( $by_id[ $no_id ] ) ) {
				$errors[] = array(
					'id'      => $no_id,
					'message' => 'Not found in cached New Order catalog. Run Update Stock first.',
				);
				continue;
			}

			$result = self::upsert_disabled_product( $by_id[ $no_id ] );
			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'id'      => $no_id,
					'message' => $result->get_error_message(),
				);
				continue;
			}

			if ( ! empty( $result['updated'] ) ) {
				$updated[] = $result;
			} else {
				$created[] = $result;
			}
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'created' => $created,
				'updated' => $updated,
				'errors'  => $errors,
				'count'   => count( $created ) + count( $updated ),
			)
		);
	}

	/**
	 * Create or update a Disabled WC product from a normalized New Order row.
	 * SKU = New Order id.
	 *
	 * @param array $row Normalized product.
	 * @return array|WP_Error
	 */
	private static function upsert_disabled_product( $row ) {
		$no_id = isset( $row['id'] ) ? (string) $row['id'] : '';
		if ( '' === $no_id ) {
			return new WP_Error( 'storyphone_im_neworder_missing_id', 'Missing New Order id.' );
		}

		$name = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
		if ( '' === $name ) {
			$name = 'New Order #' . $no_id;
		}

		$sku         = wc_clean( $no_id );
		$existing_id = wc_get_product_id_by_sku( $sku );
		$is_update   = $existing_id > 0;

		if ( $is_update ) {
			$product = wc_get_product( $existing_id );
			if ( ! $product ) {
				return new WP_Error( 'storyphone_im_neworder_product_missing', 'Existing SKU product could not be loaded.' );
			}
		} else {
			$product = new WC_Product_Simple();
			$product->set_sku( $sku );
		}

		$product->set_name( $name );

		if ( isset( $row['description'] ) && '' !== (string) $row['description'] ) {
			$desc = wp_kses_post( (string) $row['description'] );
			$product->set_description( $desc );
			$product->set_short_description( $desc );
		}

		if ( isset( $row['price'] ) && '' !== $row['price'] && null !== $row['price'] ) {
			$price = wc_format_decimal( wc_clean( (string) $row['price'] ) );
			$product->set_regular_price( $price );
			$product->set_price( $price );
		}

		if ( isset( $row['stock'] ) && null !== $row['stock'] && '' !== $row['stock'] && is_numeric( $row['stock'] ) ) {
			$qty = absint( $row['stock'] );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $qty );
			$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
		} else {
			$product->set_manage_stock( false );
			$product->set_stock_status( 'outofstock' );
		}

		// Disabled = draft + hidden (top of Disabled list = newest IDs).
		$product->set_status( 'draft' );
		$product->set_catalog_visibility( 'hidden' );

		$product_id = $product->save();
		if ( ! $product_id ) {
			return new WP_Error( 'storyphone_im_neworder_save_failed', 'WooCommerce could not save the product.' );
		}

		if ( class_exists( 'StoryPhone_IM_Storefront_Visibility' ) ) {
			StoryPhone_IM_Storefront_Visibility::sync_hidden_visibility_terms( $product_id );
		}

		clean_post_cache( $product_id );
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}

		if ( class_exists( 'StoryPhone_IM_Audit_Log' ) ) {
			StoryPhone_IM_Audit_Log::log(
				$is_update ? 'update' : 'create',
				$product_id,
				array(
					'source'     => 'neworder_export',
					'neworder_id'=> $no_id,
				)
			);
		}

		return array(
			'woo_id'      => (int) $product_id,
			'neworder_id' => $no_id,
			'sku'         => $sku,
			'name'        => $name,
			'updated'     => $is_update,
		);
	}

	/**
	 * Where the active token comes from.
	 *
	 * @return string constant|option|none
	 */
	private static function token_source() {
		if ( defined( 'STORYPHONE_NEWORDER_API_TOKEN' ) && STORYPHONE_NEWORDER_API_TOKEN ) {
			return 'constant';
		}
		$stored = get_option( self::OPTION_TOKEN, '' );
		if ( is_string( $stored ) && '' !== $stored ) {
			return 'option';
		}
		return 'none';
	}

	/**
	 * Load cached catalog array.
	 *
	 * @return array
	 */
	private static function get_cached_catalog() {
		$catalog = get_option( self::OPTION_CATALOG, array() );
		return is_array( $catalog ) ? $catalog : array();
	}

	/**
	 * Fetch all in-stock products. Prefer one large page; continue if full.
	 *
	 * @param string $token Bearer token.
	 * @return array|WP_Error
	 */
	private static function fetch_all_in_stock_products( $token ) {
		$page_size = self::PAGE_SIZE;
		$page_num  = 1;
		$all       = array();
		$max_pages = 10;

		while ( $page_num <= $max_pages ) {
			$batch = self::api_get(
				$token,
				'/api/Products',
				array(
					'page_size'    => $page_size,
					'page_num'     => $page_num,
					'serialsOnly'  => 'false',
					'stockMode'    => 1,
					'showNotUsed'  => 'false',
				)
			);

			if ( is_wp_error( $batch ) ) {
				return $batch;
			}

			$rows = self::as_list( $batch );
			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$all[] = $row;
			}

			if ( count( $rows ) < $page_size ) {
				break;
			}

			++$page_num;
		}

		return $all;
	}

	/**
	 * GET against New Order Azure API.
	 *
	 * @param string $token  Bearer token.
	 * @param string $path   Path starting with /api.
	 * @param array  $params Query params.
	 * @return array|WP_Error
	 */
	private static function api_get( $token, $path, $params = array() ) {
		$base = defined( 'STORYPHONE_NEWORDER_API_BASE' ) && STORYPHONE_NEWORDER_API_BASE
			? rtrim( (string) STORYPHONE_NEWORDER_API_BASE, '/' )
			: self::BASE_URL;

		$url = $base . $path;
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'storyphone_im_neworder_http',
				sprintf(
					/* translators: %s: error message */
					__( 'New Order API network error: %s', 'storyphone-inventory-manager' ),
					$response->get_error_message()
				),
				array( 'status' => 502 )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			$snippet = substr( wp_strip_all_tags( $body ), 0, 280 );
			return new WP_Error(
				'storyphone_im_neworder_http',
				sprintf(
					/* translators: 1: HTTP status, 2: body snippet */
					__( 'New Order API HTTP %1$d: %2$s', 'storyphone-inventory-manager' ),
					$code,
					$snippet ? $snippet : 'empty body'
				),
				array( 'status' => 502 )
			);
		}

		if ( '' === trim( $body ) ) {
			return array();
		}

		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'storyphone_im_neworder_json',
				__( 'New Order API returned invalid JSON.', 'storyphone-inventory-manager' ),
				array( 'status' => 502 )
			);
		}

		return $data;
	}

	/**
	 * Unwrap list payloads (same keys as wolt-net-income client).
	 *
	 * @param mixed $payload Response body.
	 * @return array
	 */
	private static function as_list( $payload ) {
		if ( null === $payload ) {
			return array();
		}
		if ( is_array( $payload ) && self::is_list_array( $payload ) ) {
			return $payload;
		}
		if ( is_array( $payload ) ) {
			foreach ( array( 'items', 'data', 'results', 'products', 'categories' ) as $key ) {
				if ( isset( $payload[ $key ] ) && is_array( $payload[ $key ] ) ) {
					return $payload[ $key ];
				}
			}
			return array( $payload );
		}
		return array();
	}

	/**
	 * PHP 8.1 array_is_list polyfill-ish check.
	 *
	 * @param array $arr Array.
	 * @return bool
	 */
	private static function is_list_array( $arr ) {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $arr );
		}
		$i = 0;
		foreach ( $arr as $k => $_ ) {
			if ( $k !== $i ) {
				return false;
			}
			++$i;
		}
		return true;
	}

	/**
	 * Normalize a New Order product row for the UI.
	 *
	 * @param array $row Raw API row.
	 * @return array|null
	 */
	private static function normalize_product( $row ) {
		$id = isset( $row['id'] ) ? (string) $row['id'] : '';
		if ( '' === $id ) {
			return null;
		}

		$category    = ( isset( $row['category'] ) && is_array( $row['category'] ) ) ? $row['category'] : array();
		$supplier    = ( isset( $row['supplier'] ) && is_array( $row['supplier'] ) ) ? $row['supplier'] : array();
		$cat_id      = isset( $category['id'] ) ? (string) $category['id'] : '';
		$cat_name    = isset( $category['name'] ) ? (string) $category['name'] : '';
		$extra_codes = array();
		if ( ! empty( $row['additionalBarcodes'] ) && is_array( $row['additionalBarcodes'] ) ) {
			foreach ( $row['additionalBarcodes'] as $code ) {
				$code = trim( (string) $code );
				if ( '' !== $code ) {
					$extra_codes[] = $code;
				}
			}
		} elseif ( ! empty( $row['additionalBarcodes'] ) && is_string( $row['additionalBarcodes'] ) ) {
			$extra_codes[] = trim( $row['additionalBarcodes'] );
		}

		$stock = isset( $row['currentStock'] ) ? $row['currentStock'] : null;
		if ( null !== $stock && '' !== $stock && is_numeric( $stock ) ) {
			$stock = 0 + $stock;
		} else {
			$stock = null;
		}

		return array(
			'id'                   => $id,
			'name'                 => isset( $row['name'] ) ? (string) $row['name'] : ( 'Product #' . $id ),
			'sku'                  => isset( $row['barcode'] ) ? (string) $row['barcode'] : '',
			'barcode'              => isset( $row['barcode'] ) ? (string) $row['barcode'] : '',
			'additional_barcodes'  => $extra_codes,
			'price'                => isset( $row['price'] ) ? $row['price'] : null,
			'cost'                 => isset( $row['cost'] ) ? $row['cost'] : null,
			'cost_no_tax'          => isset( $row['costNoTax'] ) ? $row['costNoTax'] : null,
			'stock'                => $stock,
			'is_serial'            => ! empty( $row['isSerial'] ),
			'is_tax_free'          => ! empty( $row['isTaxFree'] ),
			'is_stock'             => array_key_exists( 'isStock', $row ) ? (bool) $row['isStock'] : true,
			'is_active'            => array_key_exists( 'isActive', $row ) ? (bool) $row['isActive'] : true,
			'description'          => isset( $row['description'] ) ? (string) $row['description'] : '',
			'category_id'          => $cat_id,
			'category_name'        => $cat_name,
			'supplier_id'          => isset( $supplier['id'] ) ? (string) $supplier['id'] : '',
			'supplier_name'        => isset( $supplier['name'] ) ? (string) $supplier['name'] : '',
			'raw'                  => $row,
		);
	}
}
