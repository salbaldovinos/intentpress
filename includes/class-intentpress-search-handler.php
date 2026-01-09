<?php
/**
 * Search handler class.
 *
 * Orchestrates semantic search flow including embedding generation,
 * similarity search, and fallback to WordPress default search.
 *
 * @package IntentPress
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IntentPress Search Handler class.
 *
 * @since 1.0.0
 */
class IntentPress_Search_Handler {

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
	 * Free tier search limit per month.
	 *
	 * @var int
	 */
	private const FREE_TIER_LIMIT = 1000;

	/**
	 * Free tier index limit.
	 *
	 * @var int
	 */
	private const FREE_TIER_INDEX_LIMIT = 500;

	/**
	 * Constructor.
	 *
	 * @param IntentPress_Embedding_Service $embedding_service Embedding service.
	 * @param IntentPress_Vector_Store      $vector_store      Vector store.
	 */
	public function __construct(
		IntentPress_Embedding_Service $embedding_service,
		IntentPress_Vector_Store $vector_store
	) {
		$this->embedding_service = $embedding_service;
		$this->vector_store      = $vector_store;
	}

	/**
	 * Initialize search handler.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook into WordPress search if semantic search is enabled.
		if ( $this->is_enabled() ) {
			add_filter( 'posts_search', array( $this, 'filter_posts_search' ), 10, 2 );
			add_filter( 'posts_pre_query', array( $this, 'handle_semantic_search' ), 10, 2 );
		}

		// Register cron handlers.
		add_action( 'intentpress_monthly_reset', array( $this, 'reset_monthly_counter' ) );
	}

	/**
	 * Check if semantic search is enabled.
	 *
	 * @return bool True if enabled.
	 */
	public function is_enabled(): bool {
		$api_key = $this->embedding_service->get_api_key();
		return ! empty( $api_key );
	}

	/**
	 * Perform semantic search.
	 *
	 * @param string $query      Search query.
	 * @param array  $args       Optional search arguments.
	 * @return array Search results with meta information.
	 */
	public function search( string $query, array $args = array() ): array {
		$start_time = microtime( true );

		// Default arguments.
		$defaults = array(
			'per_page'   => (int) get_option( 'intentpress_per_page', 10 ),
			'threshold'  => (float) get_option( 'intentpress_similarity_threshold', 0.5 ),
			'post_types' => get_option( 'intentpress_indexed_post_types', array( 'post', 'page' ) ),
			'page'       => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		// Initialize result structure.
		$result = array(
			'success' => true,
			'data'    => array(
				'results'   => array(),
				'total'     => 0,
				'page'      => $args['page'],
				'per_page'  => $args['per_page'],
				'query'     => $query,
			),
			'meta'    => array(
				'fallback_used'  => false,
				'execution_time' => 0,
				'search_type'    => 'semantic',
			),
		);

		// Check free tier limits.
		if ( $this->has_reached_search_limit() ) {
			// Fallback to WordPress search.
			return $this->fallback_search( $query, $args, $result, 'limit_reached' );
		}

		// Generate embedding for query.
		$query_embedding = $this->embedding_service->generate_embedding( $query );

		if ( is_wp_error( $query_embedding ) ) {
			// Log error and fallback.
			$this->log_error( 'Embedding failed: ' . $query_embedding->get_error_message() );
			return $this->fallback_search( $query, $args, $result, 'embedding_error' );
		}

		// Search vector store.
		$max_results = (int) get_option( 'intentpress_max_results', 100 );
		$similar     = $this->vector_store->find_similar(
			$query_embedding,
			$max_results,
			$args['threshold'],
			$args['post_types']
		);

		if ( empty( $similar ) ) {
			// No semantic results, try fallback.
			$fallback_enabled = get_option( 'intentpress_fallback_enabled', true );

			if ( $fallback_enabled ) {
				return $this->fallback_search( $query, $args, $result, 'no_results' );
			}

			// Return empty results.
			$result['data']['total']        = 0;
			$result['meta']['execution_time'] = microtime( true ) - $start_time;

			$this->record_search( $query, 0, $result['meta']['execution_time'], false );

			return $result;
		}

		// Paginate results.
		$offset        = ( $args['page'] - 1 ) * $args['per_page'];
		$paged_results = array_slice( $similar, $offset, $args['per_page'] );

		// Build result data.
		$results = array();

		foreach ( $paged_results as $item ) {
			$post = get_post( $item['post_id'] );

			if ( ! $post ) {
				continue;
			}

			$results[] = $this->format_result( $post, $item['similarity'] );
		}

		$result['data']['results'] = $results;
		$result['data']['total']   = count( $similar );
		$result['meta']['execution_time'] = microtime( true ) - $start_time;

		// Increment search counter.
		$this->increment_search_counter();

		// Record analytics.
		$this->record_search( $query, count( $similar ), $result['meta']['execution_time'], false );

		/**
		 * Filter semantic search results.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $result Search result array.
		 * @param string $query  Search query.
		 * @param array  $args   Search arguments.
		 */
		return apply_filters( 'intentpress_search_results', $result, $query, $args );
	}

	/**
	 * Fallback to WordPress default search.
	 *
	 * @param string $query  Search query.
	 * @param array  $args   Search arguments.
	 * @param array  $result Base result structure.
	 * @param string $reason Reason for fallback.
	 * @return array Search results.
	 */
	private function fallback_search( string $query, array $args, array $result, string $reason ): array {
		$start_time = microtime( true );

		// Use WordPress search.
		$wp_query_args = array(
			's'              => $query,
			'post_type'      => $args['post_types'],
			'post_status'    => 'publish',
			'posts_per_page' => $args['per_page'],
			'paged'          => $args['page'],
			'orderby'        => 'relevance',
		);

		$wp_query = new WP_Query( $wp_query_args );

		$results = array();

		foreach ( $wp_query->posts as $post ) {
			$results[] = $this->format_result( $post );
		}

		$result['data']['results']           = $results;
		$result['data']['total']             = $wp_query->found_posts;
		$result['meta']['fallback_used']     = true;
		$result['meta']['fallback_reason']   = $reason;
		$result['meta']['search_type']       = 'wordpress';
		$result['meta']['execution_time']    = microtime( true ) - $start_time;

		// Record analytics.
		$this->record_search( $query, $wp_query->found_posts, $result['meta']['execution_time'], true );

		return $result;
	}

	/**
	 * Format a post as a search result.
	 *
	 * @param WP_Post    $post       The post object.
	 * @param float|null $similarity Optional similarity score.
	 * @return array Formatted result.
	 */
	private function format_result( WP_Post $post, ?float $similarity = null ): array {
		$result = array(
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'excerpt'    => get_the_excerpt( $post ),
			'url'        => get_permalink( $post ),
			'post_type'  => $post->post_type,
			'date'       => get_the_date( 'c', $post ),
			'author'     => array(
				'id'   => (int) $post->post_author,
				'name' => get_the_author_meta( 'display_name', $post->post_author ),
			),
		);

		if ( null !== $similarity ) {
			$result['similarity'] = round( $similarity, 4 );
		}

		// Add thumbnail if available.
		if ( has_post_thumbnail( $post ) ) {
			$result['thumbnail'] = get_the_post_thumbnail_url( $post, 'thumbnail' );
		}

		/**
		 * Filter individual search result.
		 *
		 * @since 1.0.0
		 *
		 * @param array   $result     Formatted result.
		 * @param WP_Post $post       Post object.
		 * @param float   $similarity Similarity score.
		 */
		return apply_filters( 'intentpress_search_result_item', $result, $post, $similarity );
	}

	/**
	 * Filter posts_search to bypass for semantic searches.
	 *
	 * @param string   $search Search SQL.
	 * @param WP_Query $query  WP_Query instance.
	 * @return string Modified search SQL.
	 */
	public function filter_posts_search( string $search, WP_Query $query ): string {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $search;
		}

		// If we're handling this as semantic search, clear the search.
		if ( $query->get( 'intentpress_semantic' ) ) {
			return '';
		}

		return $search;
	}

	/**
	 * Handle semantic search for main query.
	 *
	 * @param array|null $posts Posts array or null.
	 * @param WP_Query   $query WP_Query instance.
	 * @return array|null Modified posts or null.
	 */
	public function handle_semantic_search( ?array $posts, WP_Query $query ): ?array {
		// Only handle main search queries on frontend.
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return $posts;
		}

		// Check if already processed.
		if ( $query->get( 'intentpress_processed' ) ) {
			return $posts;
		}

		$search_query = $query->get( 's' );

		if ( empty( $search_query ) ) {
			return $posts;
		}

		// Perform semantic search.
		$args = array(
			'per_page'   => $query->get( 'posts_per_page' ) ?: 10,
			'page'       => max( 1, $query->get( 'paged' ) ?: 1 ),
			'post_types' => $query->get( 'post_type' ) ?: get_option( 'intentpress_indexed_post_types', array( 'post', 'page' ) ),
		);

		$result = $this->search( $search_query, $args );

		// If fallback was used, let WordPress handle it.
		if ( $result['meta']['fallback_used'] ) {
			return $posts;
		}

		// Mark as processed.
		$query->set( 'intentpress_processed', true );
		$query->set( 'intentpress_semantic', true );

		// Extract post IDs from results.
		$post_ids = wp_list_pluck( $result['data']['results'], 'id' );

		if ( empty( $post_ids ) ) {
			$query->found_posts   = 0;
			$query->max_num_pages = 0;
			return array();
		}

		// Get posts in order.
		$semantic_posts = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$semantic_posts[] = $post;
			}
		}

		// Set query vars.
		$query->found_posts   = $result['data']['total'];
		$query->max_num_pages = ceil( $result['data']['total'] / $args['per_page'] );

		return $semantic_posts;
	}

	/**
	 * Check if monthly search limit has been reached.
	 *
	 * @return bool True if limit reached.
	 */
	public function has_reached_search_limit(): bool {
		$monthly_searches = (int) get_option( 'intentpress_monthly_searches', 0 );
		return $monthly_searches >= self::FREE_TIER_LIMIT;
	}

	/**
	 * Check if index limit has been reached.
	 *
	 * @return bool True if limit reached.
	 */
	public function has_reached_index_limit(): bool {
		$indexed_count = $this->vector_store->get_count();
		return $indexed_count >= self::FREE_TIER_INDEX_LIMIT;
	}

	/**
	 * Increment the monthly search counter.
	 *
	 * @return void
	 */
	private function increment_search_counter(): void {
		$current = (int) get_option( 'intentpress_monthly_searches', 0 );
		update_option( 'intentpress_monthly_searches', $current + 1 );
	}

	/**
	 * Reset monthly search counter.
	 *
	 * @return void
	 */
	public function reset_monthly_counter(): void {
		update_option( 'intentpress_monthly_searches', 0 );
		update_option( 'intentpress_search_counter_reset', current_time( 'mysql' ) );
	}

	/**
	 * Get search usage statistics.
	 *
	 * @return array Usage stats.
	 */
	public function get_usage_stats(): array {
		return array(
			'monthly_searches'      => (int) get_option( 'intentpress_monthly_searches', 0 ),
			'monthly_search_limit'  => self::FREE_TIER_LIMIT,
			'indexed_posts'         => $this->vector_store->get_count(),
			'index_limit'           => self::FREE_TIER_INDEX_LIMIT,
			'last_reset'            => get_option( 'intentpress_search_counter_reset', '' ),
		);
	}

	/**
	 * Record search analytics.
	 *
	 * @param string $query          Search query.
	 * @param int    $result_count   Number of results.
	 * @param float  $execution_time Execution time in seconds.
	 * @param bool   $fallback_used  Whether fallback was used.
	 * @return void
	 */
	private function record_search( string $query, int $result_count, float $execution_time, bool $fallback_used ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'intentpress_analytics';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table_name,
			array(
				'query_text'     => substr( $query, 0, 500 ),
				'query_hash'     => md5( strtolower( trim( $query ) ) ),
				'result_count'   => $result_count,
				'execution_time' => $execution_time,
				'fallback_used'  => $fallback_used ? 1 : 0,
				'user_id'        => get_current_user_id() ?: null,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%f', '%d', '%d', '%s' )
		);
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private function log_error( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'IntentPress Search: ' . $message );
		}
	}
}
