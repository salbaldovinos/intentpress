<?php
/**
 * Plugin activator class.
 *
 * Handles plugin activation and deactivation tasks including
 * database table creation and default options setup.
 *
 * @package IntentPress
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IntentPress Activator class.
 *
 * @since 1.0.0
 */
class IntentPress_Activator {

	/**
	 * Database version for schema migrations.
	 *
	 * @var string
	 */
	private const DB_VERSION = '1.0.0';

	/**
	 * Option name for database version.
	 *
	 * @var string
	 */
	private const DB_VERSION_OPTION = 'intentpress_db_version';

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public function activate(): void {
		$this->create_tables();
		$this->set_default_options();
		$this->schedule_events();

		// Store current database version.
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		// Flush rewrite rules for any custom endpoints.
		flush_rewrite_rules();
	}

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		$this->unschedule_events();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create database tables.
	 *
	 * @return void
	 */
	private function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'intentpress_embeddings';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			embedding longtext NOT NULL,
			model_version varchar(50) NOT NULL DEFAULT 'text-embedding-3-small',
			content_hash varchar(32) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY post_id (post_id),
			KEY model_version (model_version),
			KEY content_hash (content_hash)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Create search analytics table.
		$analytics_table = $wpdb->prefix . 'intentpress_analytics';

		$analytics_sql = "CREATE TABLE {$analytics_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			query_text varchar(500) NOT NULL,
			query_hash varchar(32) NOT NULL,
			result_count int(11) NOT NULL DEFAULT 0,
			execution_time float NOT NULL DEFAULT 0,
			fallback_used tinyint(1) NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY query_hash (query_hash),
			KEY created_at (created_at),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $analytics_sql );
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private function set_default_options(): void {
		// Only set defaults if options don't exist.
		$defaults = array(
			'intentpress_api_key'             => '',
			'intentpress_indexed_post_types'  => array( 'post', 'page' ),
			'intentpress_per_page'            => 10,
			'intentpress_similarity_threshold' => 0.5,
			'intentpress_monthly_searches'    => 0,
			'intentpress_search_counter_reset' => '',
			'intentpress_fallback_enabled'    => true,
			'intentpress_cache_ttl'           => 3600,
			'intentpress_max_results'         => 100,
			'intentpress_onboarding_complete' => false,
		);

		foreach ( $defaults as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $default_value, '', 'no' );
			}
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	private function schedule_events(): void {
		// Schedule monthly search counter reset.
		if ( ! wp_next_scheduled( 'intentpress_monthly_reset' ) ) {
			// Schedule for the first day of next month at midnight.
			$next_month = strtotime( 'first day of next month midnight' );
			wp_schedule_event( $next_month, 'monthly', 'intentpress_monthly_reset' );
		}

		// Schedule daily cleanup of old analytics data.
		if ( ! wp_next_scheduled( 'intentpress_cleanup_analytics' ) ) {
			wp_schedule_event( time(), 'daily', 'intentpress_cleanup_analytics' );
		}
	}

	/**
	 * Unschedule cron events.
	 *
	 * @return void
	 */
	private function unschedule_events(): void {
		wp_clear_scheduled_hook( 'intentpress_monthly_reset' );
		wp_clear_scheduled_hook( 'intentpress_cleanup_analytics' );
	}

	/**
	 * Get the database version.
	 *
	 * @return string Database version.
	 */
	public static function get_db_version(): string {
		return get_option( self::DB_VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Check if database needs upgrade.
	 *
	 * @return bool True if upgrade needed.
	 */
	public static function needs_upgrade(): bool {
		return version_compare( self::get_db_version(), self::DB_VERSION, '<' );
	}
}
