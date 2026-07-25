<?php
/**
 * Write-action audit log and soft rate limiting.
 *
 * @package StoryPhone_Inventory_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Persists who changed what, when; soft-limits write bursts per user.
 */
class StoryPhone_IM_Audit_Log {

	/**
	 * Max write requests per user per window.
	 *
	 * @var int
	 */
	const RATE_LIMIT_MAX = 60;

	/**
	 * Rate-limit window in seconds.
	 *
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 60;

	/**
	 * DB schema version option key.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'storyphone_im_audit_db_version';

	/**
	 * Current schema version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0';

	/**
	 * Ensure table exists (activation + version bump).
	 *
	 * @return void
	 */
	public static function maybe_install() {
		$installed = get_option( self::DB_VERSION_OPTION, '' );
		if ( self::DB_VERSION === $installed ) {
			return;
		}
		self::install();
	}

	/**
	 * Create or upgrade the audit log table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(32) NOT NULL DEFAULT '',
			summary text NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY product_id (product_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Prefixed table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'storyphone_im_audit';
	}

	/**
	 * Soft rate limit for write endpoints. Returns WP_Error when exceeded.
	 *
	 * @return true|WP_Error
	 */
	public static function check_rate_limit() {
		$user_id = get_current_user_id();
		$key     = 'storyphone_im_rl_' . $user_id;
		$count   = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_MAX ) {
			return new WP_Error(
				'storyphone_im_rate_limited',
				__( 'Too many inventory changes. Please wait a minute and try again.', 'storyphone-inventory-manager' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );

		return true;
	}

	/**
	 * Append an audit row for a write action.
	 *
	 * @param string $action     Action slug: create|update|upload_image|trash.
	 * @param int    $product_id Product ID (0 if unknown).
	 * @param array  $summary    Sanitized key => value change summary.
	 * @return void
	 */
	public static function log( $action, $product_id = 0, $summary = array() ) {
		global $wpdb;

		$action = sanitize_key( (string) $action );
		if ( '' === $action ) {
			return;
		}

		$safe_summary = array();
		if ( is_array( $summary ) ) {
			foreach ( $summary as $key => $value ) {
				$safe_key = sanitize_key( (string) $key );
				if ( '' === $safe_key ) {
					continue;
				}
				if ( is_scalar( $value ) || null === $value ) {
					$safe_summary[ $safe_key ] = sanitize_text_field( (string) $value );
				} elseif ( is_array( $value ) ) {
					$safe_summary[ $safe_key ] = array_map( 'sanitize_text_field', array_map( 'strval', $value ) );
				}
			}
		}

		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			self::table_name(),
			array(
				'user_id'    => (int) get_current_user_id(),
				'product_id' => absint( $product_id ),
				'action'     => $action,
				'summary'    => wp_json_encode( $safe_summary ),
				'ip'         => $ip,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}
}
