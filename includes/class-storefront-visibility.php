<?php
/**
 * Enforce Disabled products off the storefront and out of search.
 *
 * Disabled = draft / private / pending, or catalog visibility "hidden".
 * Out of stock products stay listed when Enabled, but WooCommerce blocks purchase.
 *
 * @package StoryPhone_Inventory_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Storefront visibility enforcement.
 */
class StoryPhone_IM_Storefront_Visibility {

	/**
	 * Hook storefront filters.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_product_is_visible', array( __CLASS__, 'filter_is_visible' ), 99, 2 );
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'filter_is_purchasable' ), 99, 2 );
		add_filter( 'woocommerce_variation_is_visible', array( __CLASS__, 'filter_variation_is_visible' ), 99, 4 );
		add_action( 'template_redirect', array( __CLASS__, 'block_disabled_single_product' ), 0 );
		add_action( 'pre_get_posts', array( __CLASS__, 'exclude_disabled_from_search' ), 99 );
		add_filter( 'the_posts', array( __CLASS__, 'strip_disabled_from_posts' ), 99, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 99, 2 );
	}

	/**
	 * Whether a product must stay off the public storefront.
	 *
	 * @param WC_Product|int $product Product or ID.
	 * @return bool
	 */
	public static function is_storefront_disabled( $product ) {
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product );
		}
		if ( ! $product ) {
			return true;
		}

		if ( 'publish' !== $product->get_status() ) {
			return true;
		}

		return 'hidden' === $product->get_catalog_visibility();
	}

	/**
	 * Requests that must never expose Disabled products.
	 *
	 * @return bool
	 */
	private static function is_public_storefront_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		// Classic wp-admin screens only (admin-ajax can be frontend search).
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		return true;
	}

	/**
	 * Force Disabled products out of catalogs / loops.
	 *
	 * @param bool $visible    Current visibility.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public static function filter_is_visible( $visible, $product_id ) {
		if ( self::is_storefront_disabled( $product_id ) ) {
			return false;
		}
		return (bool) $visible;
	}

	/**
	 * Variations of a Disabled parent stay hidden.
	 *
	 * @param bool $visible      Current.
	 * @param int  $variation_id Variation ID.
	 * @param int  $parent_id    Parent ID.
	 * @return bool
	 */
	public static function filter_variation_is_visible( $visible, $variation_id, $parent_id, $variation = null ) {
		unset( $variation );
		if ( self::is_storefront_disabled( $parent_id ) || self::is_storefront_disabled( $variation_id ) ) {
			return false;
		}
		return (bool) $visible;
	}

	/**
	 * Disabled products must not be purchasable.
	 *
	 * @param bool       $purchasable Current value.
	 * @param WC_Product $product     Product.
	 * @return bool
	 */
	public static function filter_is_purchasable( $purchasable, $product ) {
		if ( self::is_storefront_disabled( $product ) ) {
			return false;
		}
		return (bool) $purchasable;
	}

	/**
	 * Block add-to-cart for Disabled products.
	 *
	 * @param bool $passed     Validation state.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public static function validate_add_to_cart( $passed, $product_id ) {
		if ( self::is_storefront_disabled( $product_id ) ) {
			return false;
		}
		return (bool) $passed;
	}

	/**
	 * Treat Disabled product URLs as non-existent (404) for everyone on the storefront.
	 * Admins manage these only in wp-admin / Inventory Manager — not via the live product page.
	 *
	 * @return void
	 */
	public static function block_disabled_single_product() {
		if ( ! self::is_public_storefront_request() ) {
			return;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product || ! self::is_storefront_disabled( $product ) ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		include get_query_template( '404' );
		exit;
	}

	/**
	 * Exclude Disabled / hidden products from frontend search (all queries, not only main).
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public static function exclude_disabled_from_search( $query ) {
		if ( ! self::is_public_storefront_request() || ! $query instanceof WP_Query || ! $query->is_search() ) {
			return;
		}

		// Logged-in editors otherwise see drafts in frontend search.
		$query->set( 'post_status', 'publish' );

		if ( ! taxonomy_exists( 'product_visibility' ) ) {
			return;
		}

		$tax_query = $query->get( 'tax_query' );
		$tax_query = is_array( $tax_query ) ? $tax_query : array();

		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => array( 'exclude-from-search' ),
			'operator' => 'NOT IN',
		);

		if ( empty( $tax_query['relation'] ) ) {
			$tax_query['relation'] = 'AND';
		}

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Last line of defense: remove Disabled products from any frontend result set
	 * (theme AJAX search, secondary queries, widgets, etc.).
	 *
	 * @param WP_Post[] $posts Posts.
	 * @param WP_Query  $query Query.
	 * @return WP_Post[]
	 */
	public static function strip_disabled_from_posts( $posts, $query ) {
		if ( ! self::is_public_storefront_request() || empty( $posts ) || ! is_array( $posts ) ) {
			return $posts;
		}

		$filtered = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post && 'product' === $post->post_type ) {
				if ( self::is_storefront_disabled( $post->ID ) ) {
					continue;
				}
			}
			$filtered[] = $post;
		}

		return $filtered;
	}

	/**
	 * After save: ensure visibility taxonomy matches "hidden".
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function sync_hidden_visibility_terms( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id < 1 || ! taxonomy_exists( 'product_visibility' ) ) {
			return;
		}

		$terms = array( 'exclude-from-catalog', 'exclude-from-search' );
		wp_set_object_terms( $product_id, $terms, 'product_visibility', false );
	}
}
