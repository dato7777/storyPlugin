<?php
/**
 * Admin menu page and asset enqueue for StoryPhone Inventory Manager.
 *
 * @package StoryPhone_Inventory_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the admin page and enqueues the React build only on that screen.
 */
class StoryPhone_IM_Admin_Page {

	/**
	 * Screen id for the plugin admin page.
	 *
	 * @var string
	 */
	const SCREEN_ID = 'toplevel_page_storyphone-inventory';

	/**
	 * Hook registrations.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'current_screen', array( __CLASS__, 'silence_unrelated_notices' ) );
		add_action( 'in_admin_header', array( __CLASS__, 'strip_admin_notices' ), 1000 );
		add_action( 'admin_head', array( __CLASS__, 'print_boot_css' ), 1 );
		add_action( 'admin_footer', array( __CLASS__, 'print_boot_js' ), 1 );
	}

	/**
	 * Whether the current admin screen is this plugin's page.
	 *
	 * @param WP_Screen|null $screen Optional screen.
	 * @return bool
	 */
	private static function is_our_screen( $screen = null ) {
		if ( ! $screen && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}

		return $screen && isset( $screen->id ) && self::SCREEN_ID === $screen->id;
	}

	/**
	 * Drop standard admin notice hooks on our screen so other plugins
	 * do not bury the inventory UI under banners.
	 *
	 * @param WP_Screen $screen Current screen.
	 * @return void
	 */
	public static function silence_unrelated_notices( $screen ) {
		if ( ! self::is_our_screen( $screen ) ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	/**
	 * Late pass: clear notice actions again (some plugins re-add them).
	 *
	 * @return void
	 */
	public static function strip_admin_notices() {
		if ( ! self::is_our_screen() ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	/**
	 * Critical CSS printed early so the mount stays visible even if a
	 * third-party stylesheet later tries to hide wrappers.
	 *
	 * @return void
	 */
	public static function print_boot_css() {
		if ( ! self::is_our_screen() ) {
			return;
		}

		echo '<style id="storyphone-im-boot-css">'
			. 'body.toplevel_page_storyphone-inventory #wpbody,'
			. 'body.toplevel_page_storyphone-inventory #wpbody-content,'
			. 'body.toplevel_page_storyphone-inventory .storyphone-im-wrap,'
			. 'body.toplevel_page_storyphone-inventory #storyphone-inventory-root{'
			. 'display:block!important;visibility:visible!important;opacity:1!important;'
			. 'height:auto!important;max-height:none!important;clip:auto!important;'
			. '}'
			. '</style>';
	}

	/**
	 * Safe notice cleanup + unhide ancestors of the React mount.
	 * Never hide a node that contains #storyphone-inventory-root.
	 *
	 * @return void
	 */
	public static function print_boot_js() {
		if ( ! self::is_our_screen() ) {
			return;
		}

		echo '<script id="storyphone-im-boot-js">'
			. '(function(){'
			. 'function unhidePath(root){'
			. 'var n=root;'
			. 'while(n&&n!==document.documentElement){'
			. 'try{'
			. 'var cs=window.getComputedStyle(n);'
			. 'if(cs.display==="none"){n.style.setProperty("display","block","important");}'
			. 'if(cs.visibility==="hidden"){n.style.setProperty("visibility","visible","important");}'
			. 'if(parseFloat(cs.opacity)===0){n.style.setProperty("opacity","1","important");}'
			. 'if(cs.height==="0px"&&n.id!=="wpadminbar"){'
			. 'n.style.setProperty("height","auto","important");'
			. 'n.style.setProperty("max-height","none","important");'
			. '}'
			. '}catch(e){}'
			. 'if(n.id==="wpwrap"){break;}'
			. 'n=n.parentElement;'
			. '}'
			. '}'
			. 'function stripNotices(){'
			. 'var root=document.getElementById("storyphone-inventory-root");'
			. 'if(!root){return;}'
			. 'unhidePath(root);'
			. 'var body=document.getElementById("wpbody-content");'
			. 'if(!body){return;}'
			. 'Array.prototype.slice.call(body.children).forEach(function(el){'
			. 'if(el.contains(root)){return;}'
			. 'var c=el.classList;'
			. 'if(c.contains("notice")||c.contains("update-nag")||c.contains("updated")||'
			. 'c.contains("error")||c.contains("woocommerce-message")||'
			. 'c.contains("woocommerce-error")||c.contains("woocommerce-info")){'
			. 'el.style.setProperty("display","none","important");'
			. '}'
			. '});'
			. '}'
			. 'stripNotices();'
			. 'document.addEventListener("DOMContentLoaded",stripNotices);'
			. 'setTimeout(stripNotices,0);'
			. 'setTimeout(stripNotices,400);'
			. 'setTimeout(stripNotices,1500);'
			. 'if(window.MutationObserver){'
			. 'var body=document.getElementById("wpbody-content");'
			. 'if(body){'
			. 'new MutationObserver(function(){stripNotices();}).observe(body,{childList:true,subtree:false});'
			. '}'
			. '}'
			. '})();'
			. '</script>';
	}

	/**
	 * Register top-level admin menu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'StoryPhone Inventory', 'storyphone-inventory-manager' ),
			__( 'StoryPhone Inventory', 'storyphone-inventory-manager' ),
			'manage_woocommerce',
			'storyphone-inventory',
			array( __CLASS__, 'render_page' ),
			'dashicons-store',
			56
		);
	}

	/**
	 * Render the admin page mount point for React.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'storyphone-inventory-manager' ) );
		}

		// Keep mount classes unique and free of notice/error/updated — those
		// class names are often targeted by hide-banner CSS from this or other plugins.
		echo '<div class="wrap storyphone-im-wrap" id="storyphone-im-app" data-storyphone-im="1">';
		echo '<div id="storyphone-inventory-root" data-storyphone-im-root="1">';
		echo '<p class="storyphone-im-placeholder">' . esc_html__( 'Loading StoryPhone Inventory Manager…', 'storyphone-inventory-manager' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Enqueue built React assets only on this plugin's admin screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		$is_our_page = false;
		if ( $screen && isset( $screen->id ) && self::SCREEN_ID === $screen->id ) {
			$is_our_page = true;
		} elseif ( 'toplevel_page_storyphone-inventory' === $hook_suffix ) {
			$is_our_page = true;
		}

		if ( ! $is_our_page ) {
			return;
		}

		$js_path  = STORYPHONE_IM_PLUGIN_DIR . 'build/main.js';
		$css_path = STORYPHONE_IM_PLUGIN_DIR . 'build/main.css';

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'storyphone-im-app',
				STORYPHONE_IM_PLUGIN_URL . 'build/main.css',
				array(),
				(string) filemtime( $css_path )
			);
		}

		if ( file_exists( $js_path ) ) {
			wp_enqueue_script(
				'storyphone-im-app',
				STORYPHONE_IM_PLUGIN_URL . 'build/main.js',
				array(),
				(string) filemtime( $js_path ),
				true
			);

			$pages_ready = class_exists( 'StoryPhone_Pages_Render' ) && class_exists( 'StoryPhone_Pages_Catalog' );

			wp_localize_script(
				'storyphone-im-app',
				'storyphoneSettings',
				array(
					'root'               => esc_url_raw( rest_url( 'storyphone/v1/' ) ),
					'nonce'              => wp_create_nonce( 'wp_rest' ),
					'siteUrl'            => esc_url_raw( home_url( '/' ) ),
					'canManage'          => current_user_can( 'manage_woocommerce' ),
					'pluginUrl'          => esc_url_raw( STORYPHONE_IM_PLUGIN_URL ),
					'currencySymbol'     => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '₪',
					'currencyPosition'   => get_option( 'woocommerce_currency_pos', 'left' ),
					'storefrontReady'    => $pages_ready,
					'storefrontOverride' => true,
					'navLimit'           => 9,
				)
			);
		}
	}
}
