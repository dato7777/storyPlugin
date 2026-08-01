<?php
/**
 * Plugin Name:       StoryPhone Inventory Manager
 * Plugin URI:        https://storyphone.co.il
 * Description:       Simplified WooCommerce product inventory dashboard for StoryPhone shop admins.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            StoryPhone
 * Author URI:        https://storyphone.co.il
 * Text Domain:       storyphone-inventory-manager
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 *
 * @package StoryPhone_Inventory_Manager
 */

defined( 'ABSPATH' ) || exit;

define( 'STORYPHONE_IM_VERSION', '1.0.0' );
define( 'STORYPHONE_IM_PLUGIN_FILE', __FILE__ );
define( 'STORYPHONE_IM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STORYPHONE_IM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Display an admin notice when WooCommerce is missing.
 *
 * @return void
 */
function storyphone_im_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__(
		'StoryPhone Inventory Manager requires WooCommerce to be installed and active. The plugin has been deactivated.',
		'storyphone-inventory-manager'
	);
	echo '</p></div>';
}

/**
 * Check WooCommerce dependency on plugins_loaded.
 *
 * @return void
 */
function storyphone_im_check_woocommerce() {
	if ( class_exists( 'WooCommerce' ) ) {
		storyphone_im_bootstrap();
		return;
	}

	add_action( 'admin_notices', 'storyphone_im_woocommerce_missing_notice' );

	// Deactivate ourselves gracefully if WooCommerce is not present.
	if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( is_plugin_active( plugin_basename( STORYPHONE_IM_PLUGIN_FILE ) ) ) {
			deactivate_plugins( plugin_basename( STORYPHONE_IM_PLUGIN_FILE ) );
			if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				unset( $_GET['activate'] );
			}
		}
	}
}
add_action( 'plugins_loaded', 'storyphone_im_check_woocommerce' );

/**
 * Bootstrap plugin classes once WooCommerce is confirmed active.
 *
 * @return void
 */
function storyphone_im_bootstrap() {
	require_once STORYPHONE_IM_PLUGIN_DIR . 'includes/class-admin-page.php';
	require_once STORYPHONE_IM_PLUGIN_DIR . 'includes/class-rest-controller.php';
	require_once STORYPHONE_IM_PLUGIN_DIR . 'includes/class-neworder.php';
	require_once STORYPHONE_IM_PLUGIN_DIR . 'includes/class-audit-log.php';
	require_once STORYPHONE_IM_PLUGIN_DIR . 'includes/class-storefront-visibility.php';
	require_once STORYPHONE_IM_PLUGIN_DIR . 'includes/class-design.php';
	require_once STORYPHONE_IM_PLUGIN_DIR . 'includes/class-storefront-design.php';

	StoryPhone_IM_Audit_Log::maybe_install();
	StoryPhone_IM_Admin_Page::init();
	StoryPhone_IM_REST_Controller::init();
	StoryPhone_IM_NewOrder::init();
	StoryPhone_IM_Storefront_Visibility::init();
	StoryPhone_IM_Storefront_Design::init();
	add_action( 'rest_api_init', array( 'StoryPhone_IM_Design', 'register_routes' ) );
}

/**
 * Activation hook: verify WooCommerce is available; otherwise bail with a notice.
 *
 * @return void
 */
function storyphone_im_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		// WooCommerce may not be loaded yet during activation; check for the plugin file.
		$wc_active = false;
		if ( function_exists( 'is_plugin_active' ) ) {
			$wc_active = is_plugin_active( 'woocommerce/woocommerce.php' );
		} else {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			$wc_active = is_plugin_active( 'woocommerce/woocommerce.php' );
		}

		if ( ! $wc_active ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				esc_html__(
					'StoryPhone Inventory Manager requires WooCommerce to be installed and active before activation.',
					'storyphone-inventory-manager'
				),
				esc_html__( 'Plugin Activation Error', 'storyphone-inventory-manager' ),
				array( 'back_link' => true )
			);
		}
	}

	require_once STORYPHONE_IM_PLUGIN_DIR . 'includes/class-audit-log.php';
	StoryPhone_IM_Audit_Log::install();
}
register_activation_hook( __FILE__, 'storyphone_im_activate' );

/**
 * Declare compatibility with WooCommerce feature flags (HPOS, etc.).
 *
 * @return void
 */
function storyphone_im_declare_wc_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			STORYPHONE_IM_PLUGIN_FILE,
			true
		);
	}
}
add_action( 'before_woocommerce_init', 'storyphone_im_declare_wc_compatibility' );
