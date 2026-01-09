<?php
/**
 * REST API class.
 *
 * Registers and handles REST API endpoints for the plugin.
 *
 * @package IntentPress
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IntentPress REST API class.
 *
 * @since 1.0.0
 */
class IntentPress_REST_API {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'intentpress/v1';

	/**
	 * Search handler instance.
	 *
	 * @var IntentPress_Search_Handler
	 */
	private IntentPress_Search_Handler $search_handler;

	/**
	 * Embedding service instance.
	 *
	 * @var IntentPress_Embedding_Service
	 */
	private IntentPress_Embedding_Service $embedding_service;

	/**
	 * Vector store instance.
	 *
	 * @var IntentPress_Vector_Store
	 */
	private IntentPress_Vector_Store $vector_store;

	/**
	 * Constructor.
	 *
	 * @param IntentPress_Search_Handler    $search_handler    Search handler.
	 * @param IntentPress_Embedding_Service $embedding_service Embedding service.
	 * @param IntentPress_Vector_Store      $vector_store      Vector store.
	 */
	public function __construct(
		IntentPress_Search_Handler $search_handler,
		IntentPress_Embedding_Service $embedding_service,
		IntentPress_Vector_Store $vector_store
	) {
		$this->search_handler    = $search_handler;
		$this->embedding_service = $embedding_service;
		$this->vector_store      = $vector_store;
	}

	/**
	 * Initialize REST API.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Search endpoint (public).
		register_rest_route(
			self::NAMESPACE,
			'/search',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_search' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'args'                => $this->get_search_args(),
			)
		);

		// Test search endpoint (admin only).
		register_rest_route(
			self::NAMESPACE,
			'/search/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_test_search' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_search_args(),
			)
		);

		// Settings endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_settings_args(),
				),
			)
		);

		// Index endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/index',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'trigger_indexing' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'post_ids' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'integer' ),
						'default' => array(),
					),
					'batch_size' => array(
						'type'    => 'integer',
						'default' => 10,
						'minimum' => 1,
						'maximum' => 50,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/index/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_index_status' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/index/clear',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'clear_index' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Health check endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health_status' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Validate API key endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/validate-key',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_api_key' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'api_key' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Analytics endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/analytics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_analytics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'period' => array(
						'type'    => 'string',
						'default' => '7d',
						'enum'    => array( '24h', '7d', '30d', '90d' ),
					),
				),
			)
		);

		// Onboarding status endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/onboarding',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_onboarding_status' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_onboarding_status' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'complete' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Handle search request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_search( WP_REST_Request $request ): WP_REST_Response {
		$query = $request->get_param( 'query' );
		$args  = array(
			'per_page'   => $request->get_param( 'per_page' ),
			'page'       => $request->get_param( 'page' ),
			'threshold'  => $request->get_param( 'threshold' ),
			'post_types' => $request->get_param( 'post_types' ),
		);

		// Filter out null values.
		$args = array_filter( $args, fn( $v ) => null !== $v );

		$result = $this->search_handler->search( $query, $args );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle test search request (includes debug info).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_test_search( WP_REST_Request $request ): WP_REST_Response {
		$query = $request->get_param( 'query' );
		$args  = array(
			'per_page'   => $request->get_param( 'per_page' ),
			'page'       => $request->get_param( 'page' ),
			'threshold'  => $request->get_param( 'threshold' ),
			'post_types' => $request->get_param( 'post_types' ),
		);

		$args = array_filter( $args, fn( $v ) => null !== $v );

		$result = $this->search_handler->search( $query, $args );

		// Add debug information.
		$result['debug'] = array(
			'embedding_model' => $this->embedding_service->get_model(),
			'indexed_count'   => $this->vector_store->get_count(),
			'usage_stats'     => $this->search_handler->get_usage_stats(),
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Get plugin settings.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_settings(): WP_REST_Response {
		$api_key = $this->embedding_service->get_api_key();

		$settings = array(
			'api_key_configured'   => ! empty( $api_key ),
			'api_key_masked'       => $api_key ? $this->mask_api_key( $api_key ) : '',
			'indexed_post_types'   => get_option( 'intentpress_indexed_post_types', array( 'post', 'page' ) ),
			'per_page'             => (int) get_option( 'intentpress_per_page', 10 ),
			'similarity_threshold' => (float) get_option( 'intentpress_similarity_threshold', 0.5 ),
			'fallback_enabled'     => (bool) get_option( 'intentpress_fallback_enabled', true ),
			'replace_search'       => (bool) get_option( 'intentpress_replace_search', true ),
			'cache_ttl'            => (int) get_option( 'intentpress_cache_ttl', 3600 ),
			'max_results'          => (int) get_option( 'intentpress_max_results', 100 ),
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $settings,
			),
			200
		);
	}

	/**
	 * Update plugin settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$updated = array();

		// Handle API key.
		if ( $request->has_param( 'api_key' ) ) {
			$api_key = $request->get_param( 'api_key' );

			if ( ! empty( $api_key ) ) {
				// Validate before saving.
				$validation = $this->embedding_service->validate_api_key( $api_key );

				if ( is_wp_error( $validation ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'error'   => $validation->get_error_message(),
							'code'    => $validation->get_error_code(),
						),
						400
					);
				}
			}

			$this->embedding_service->store_api_key( $api_key );
			$updated['api_key'] = true;
		}

		// Update other settings.
		$settings_map = array(
			'indexed_post_types'   => 'intentpress_indexed_post_types',
			'per_page'             => 'intentpress_per_page',
			'similarity_threshold' => 'intentpress_similarity_threshold',
			'fallback_enabled'     => 'intentpress_fallback_enabled',
			'replace_search'       => 'intentpress_replace_search',
			'cache_ttl'            => 'intentpress_cache_ttl',
			'max_results'          => 'intentpress_max_results',
		);

		foreach ( $settings_map as $param => $option ) {
			if ( $request->has_param( $param ) ) {
				$value = $request->get_param( $param );
				update_option( $option, $value );
				$updated[ $param ] = true;
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Settings updated successfully.', 'intentpress' ),
				'updated' => $updated,
			),
			200
		);
	}

	/**
	 * Trigger indexing of posts.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function trigger_indexing( WP_REST_Request $request ): WP_REST_Response {
		$post_ids   = $request->get_param( 'post_ids' );
		$batch_size = $request->get_param( 'batch_size' );

		// Check if index limit reached.
		if ( $this->search_handler->has_reached_index_limit() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Index limit reached. Upgrade to index more posts.', 'intentpress' ),
					'code'    => 'index_limit_reached',
				),
				403
			);
		}

		// If no specific posts, get posts needing indexing.
		if ( empty( $post_ids ) ) {
			$post_types = get_option( 'intentpress_indexed_post_types', array( 'post', 'page' ) );
			$post_ids   = $this->vector_store->get_posts_needing_index( $post_types, $batch_size );
		}

		if ( empty( $post_ids ) ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'No posts need indexing.', 'intentpress' ),
					'indexed' => 0,
				),
				200
			);
		}

		$indexed = 0;
		$errors  = array();

		foreach ( $post_ids as $post_id ) {
			// Check limit again.
			if ( $this->search_handler->has_reached_index_limit() ) {
				break;
			}

			$post = get_post( $post_id );

			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}

			// Generate embedding.
			$embedding = $this->embedding_service->generate_embedding_for_post( $post );

			if ( is_wp_error( $embedding ) ) {
				$errors[] = array(
					'post_id' => $post_id,
					'error'   => $embedding->get_error_message(),
				);
				continue;
			}

			// Store embedding.
			$content_hash = $this->embedding_service->generate_content_hash( $post );
			$stored       = $this->vector_store->store_embedding( $post_id, $embedding, $content_hash );

			if ( is_wp_error( $stored ) ) {
				$errors[] = array(
					'post_id' => $post_id,
					'error'   => $stored->get_error_message(),
				);
				continue;
			}

			++$indexed;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'indexed' => $indexed,
				'errors'  => $errors,
				'message' => sprintf(
					/* translators: %d: number of posts indexed */
					__( 'Successfully indexed %d posts.', 'intentpress' ),
					$indexed
				),
			),
			200
		);
	}

	/**
	 * Get indexing status.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_index_status(): WP_REST_Response {
		$post_types   = get_option( 'intentpress_indexed_post_types', array( 'post', 'page' ) );
		$needs_index  = $this->vector_store->get_posts_needing_index( $post_types, 1000 );
		$indexed      = $this->vector_store->get_count();

		// Get total indexable posts.
		$total_query = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$total = $total_query->found_posts;

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'indexed'       => $indexed,
					'total'         => $total,
					'needs_indexing' => count( $needs_index ),
					'percentage'    => $total > 0 ? round( ( $indexed / $total ) * 100, 1 ) : 0,
					'limit'         => 500,
					'limit_reached' => $this->search_handler->has_reached_index_limit(),
				),
			),
			200
		);
	}

	/**
	 * Clear all indexed data.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function clear_index(): WP_REST_Response {
		$this->vector_store->clear_all();

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Index cleared successfully.', 'intentpress' ),
			),
			200
		);
	}

	/**
	 * Get system health status.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_health_status(): WP_REST_Response {
		$api_key = $this->embedding_service->get_api_key();
		$checks  = array();

		// API key check.
		$checks['api_key'] = array(
			'status'  => ! empty( $api_key ) ? 'ok' : 'error',
			'message' => ! empty( $api_key )
				? __( 'API key configured', 'intentpress' )
				: __( 'API key not configured', 'intentpress' ),
		);

		// Database check.
		global $wpdb;
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->prefix . 'intentpress_embeddings'
			)
		);

		$checks['database'] = array(
			'status'  => $table_exists ? 'ok' : 'error',
			'message' => $table_exists
				? __( 'Database tables exist', 'intentpress' )
				: __( 'Database tables missing', 'intentpress' ),
		);

		// Index check.
		$indexed = $this->vector_store->get_count();
		$checks['index'] = array(
			'status'  => $indexed > 0 ? 'ok' : 'warning',
			'message' => $indexed > 0
				/* translators: %d: number of indexed posts */
				? sprintf( __( '%d posts indexed', 'intentpress' ), $indexed )
				: __( 'No posts indexed yet', 'intentpress' ),
		);

		// Usage limits.
		$usage = $this->search_handler->get_usage_stats();
		$checks['search_limit'] = array(
			'status'  => $usage['monthly_searches'] < $usage['monthly_search_limit'] ? 'ok' : 'warning',
			/* translators: %1$d: searches used, %2$d: search limit */
			'message' => sprintf(
				__( '%1$d / %2$d searches used this month', 'intentpress' ),
				$usage['monthly_searches'],
				$usage['monthly_search_limit']
			),
		);

		// Overall status.
		$has_error   = false;
		$has_warning = false;

		foreach ( $checks as $check ) {
			if ( 'error' === $check['status'] ) {
				$has_error = true;
			}
			if ( 'warning' === $check['status'] ) {
				$has_warning = true;
			}
		}

		$overall = 'ok';
		if ( $has_warning ) {
			$overall = 'warning';
		}
		if ( $has_error ) {
			$overall = 'error';
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'status' => $overall,
					'checks' => $checks,
				),
			),
			200
		);
	}

	/**
	 * Validate API key.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function validate_api_key( WP_REST_Request $request ): WP_REST_Response {
		$api_key    = $request->get_param( 'api_key' );
		$validation = $this->embedding_service->validate_api_key( $api_key );

		if ( is_wp_error( $validation ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'valid'   => false,
					'error'   => $validation->get_error_message(),
					'code'    => $validation->get_error_code(),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'valid'   => true,
				'message' => __( 'API key is valid.', 'intentpress' ),
			),
			200
		);
	}

	/**
	 * Get analytics data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_analytics( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$period = $request->get_param( 'period' );
		$days   = match ( $period ) {
			'24h' => 1,
			'7d'  => 7,
			'30d' => 30,
			'90d' => 90,
			default => 7,
		};

		$table_name = $wpdb->prefix . 'intentpress_analytics';

		// Get search stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_searches,
					AVG(execution_time) as avg_execution_time,
					AVG(result_count) as avg_results,
					SUM(CASE WHEN fallback_used = 1 THEN 1 ELSE 0 END) as fallback_count
				FROM {$table_name}
				WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			),
			ARRAY_A
		);

		// Get top queries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$top_queries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query_text, COUNT(*) as count
				FROM {$table_name}
				WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				GROUP BY query_hash
				ORDER BY count DESC
				LIMIT 10",
				$days
			),
			ARRAY_A
		);

		// Get daily breakdown.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as date, COUNT(*) as searches
				FROM {$table_name}
				WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$days
			),
			ARRAY_A
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'period'      => $period,
					'summary'     => array(
						'total_searches'     => (int) $stats['total_searches'],
						'avg_execution_time' => round( (float) $stats['avg_execution_time'], 3 ),
						'avg_results'        => round( (float) $stats['avg_results'], 1 ),
						'fallback_rate'      => $stats['total_searches'] > 0
							? round( ( $stats['fallback_count'] / $stats['total_searches'] ) * 100, 1 )
							: 0,
					),
					'top_queries' => $top_queries,
					'daily'       => $daily,
				),
			),
			200
		);
	}

	/**
	 * Get onboarding status.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_onboarding_status(): WP_REST_Response {
		$api_key  = $this->embedding_service->get_api_key();
		$indexed  = $this->vector_store->get_count();
		$complete = get_option( 'intentpress_onboarding_complete', false );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'complete'        => (bool) $complete,
					'steps'           => array(
						'api_key_configured' => ! empty( $api_key ),
						'posts_indexed'      => $indexed > 0,
					),
					'indexed_count'   => $indexed,
				),
			),
			200
		);
	}

	/**
	 * Update onboarding status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_onboarding_status( WP_REST_Request $request ): WP_REST_Response {
		$complete = $request->get_param( 'complete' );
		update_option( 'intentpress_onboarding_complete', $complete );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Onboarding status updated.', 'intentpress' ),
			),
			200
		);
	}

	/**
	 * Check if user can read (public access).
	 *
	 * @return bool True if allowed.
	 */
	public function check_read_permission(): bool {
		return current_user_can( 'read' );
	}

	/**
	 * Check if user has admin permissions.
	 *
	 * @return bool True if allowed.
	 */
	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get search endpoint arguments.
	 *
	 * @return array Argument definitions.
	 */
	private function get_search_args(): array {
		return array(
			'query'      => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return ! empty( trim( $param ) );
				},
			),
			'per_page'   => array(
				'type'    => 'integer',
				'default' => 10,
				'minimum' => 1,
				'maximum' => 100,
			),
			'page'       => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'threshold'  => array(
				'type'    => 'number',
				'default' => null,
				'minimum' => 0,
				'maximum' => 1,
			),
			'post_types' => array(
				'type'              => 'array',
				'items'             => array( 'type' => 'string' ),
				'default'           => null,
				'sanitize_callback' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return null;
					}
					return array_map( 'sanitize_key', $value );
				},
			),
		);
	}

	/**
	 * Get settings endpoint arguments.
	 *
	 * @return array Argument definitions.
	 */
	private function get_settings_args(): array {
		return array(
			'api_key'              => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'indexed_post_types'   => array(
				'type'              => 'array',
				'items'             => array( 'type' => 'string' ),
				'sanitize_callback' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return array();
					}
					return array_map( 'sanitize_key', $value );
				},
			),
			'per_page'             => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
			),
			'similarity_threshold' => array(
				'type'    => 'number',
				'minimum' => 0,
				'maximum' => 1,
			),
			'fallback_enabled'     => array(
				'type' => 'boolean',
			),
			'replace_search'       => array(
				'type' => 'boolean',
			),
			'cache_ttl'            => array(
				'type'    => 'integer',
				'minimum' => 0,
				'maximum' => 86400,
			),
			'max_results'          => array(
				'type'    => 'integer',
				'minimum' => 10,
				'maximum' => 500,
			),
		);
	}

	/**
	 * Mask API key for display.
	 *
	 * @param string $api_key Full API key.
	 * @return string Masked key.
	 */
	private function mask_api_key( string $api_key ): string {
		if ( strlen( $api_key ) < 8 ) {
			return str_repeat( '*', strlen( $api_key ) );
		}

		return substr( $api_key, 0, 4 ) . str_repeat( '*', strlen( $api_key ) - 8 ) . substr( $api_key, -4 );
	}
}
