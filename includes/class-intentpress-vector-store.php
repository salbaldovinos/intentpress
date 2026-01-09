<?php
/**
 * Vector store class.
 *
 * Handles storage and retrieval of embeddings in the database,
 * including similarity search operations.
 *
 * @package IntentPress
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IntentPress Vector Store class.
 *
 * @since 1.0.0
 */
class IntentPress_Vector_Store {

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'intentpress_embeddings';
	}

	/**
	 * Store an embedding for a post.
	 *
	 * @param int   $post_id      The post ID.
	 * @param array $embedding    The embedding vector.
	 * @param string $content_hash Hash of the content used to generate embedding.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function store_embedding( int $post_id, array $embedding, string $content_hash ): bool|WP_Error {
		global $wpdb;

		// Validate embedding.
		if ( empty( $embedding ) ) {
			return new WP_Error(
				'intentpress_empty_embedding',
				__( 'Cannot store empty embedding.', 'intentpress' )
			);
		}

		// Serialize embedding for storage.
		$embedding_json = wp_json_encode( $embedding );

		if ( false === $embedding_json ) {
			return new WP_Error(
				'intentpress_json_encode_error',
				__( 'Failed to encode embedding.', 'intentpress' )
			);
		}

		// Check if embedding already exists.
		$existing = $this->get_embedding( $post_id );

		if ( $existing ) {
			// Update existing embedding.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$this->table_name,
				array(
					'embedding'    => $embedding_json,
					'content_hash' => $content_hash,
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'post_id' => $post_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new embedding.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert(
				$this->table_name,
				array(
					'post_id'      => $post_id,
					'embedding'    => $embedding_json,
					'content_hash' => $content_hash,
					'created_at'   => current_time( 'mysql' ),
					'updated_at'   => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);
		}

		if ( false === $result ) {
			return new WP_Error(
				'intentpress_db_error',
				__( 'Failed to store embedding in database.', 'intentpress' )
			);
		}

		// Clear any cached data for this post.
		wp_cache_delete( 'embedding_' . $post_id, 'intentpress' );

		return true;
	}

	/**
	 * Get embedding for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return array|null Embedding data or null if not found.
	 */
	public function get_embedding( int $post_id ): ?array {
		global $wpdb;

		// Try cache first.
		$cached = wp_cache_get( 'embedding_' . $post_id, 'intentpress' );

		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE post_id = %d",
				$post_id
			),
			ARRAY_A
		);

		if ( null === $result ) {
			return null;
		}

		// Decode embedding.
		$result['embedding'] = json_decode( $result['embedding'], true );

		// Cache result.
		wp_cache_set( 'embedding_' . $post_id, $result, 'intentpress', 3600 );

		return $result;
	}

	/**
	 * Delete embedding for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_embedding( int $post_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->table_name,
			array( 'post_id' => $post_id ),
			array( '%d' )
		);

		// Clear cache.
		wp_cache_delete( 'embedding_' . $post_id, 'intentpress' );

		return false !== $result;
	}

	/**
	 * Find similar posts using cosine similarity.
	 *
	 * @param array $query_embedding The query embedding vector.
	 * @param int   $limit           Maximum number of results.
	 * @param float $threshold       Minimum similarity threshold (0-1).
	 * @param array $post_types      Post types to search in.
	 * @return array Array of results with post_id and similarity score.
	 */
	public function find_similar(
		array $query_embedding,
		int $limit = 10,
		float $threshold = 0.5,
		array $post_types = array( 'post', 'page' )
	): array {
		global $wpdb;

		// Get all embeddings from database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$embeddings = $wpdb->get_results(
			"SELECT post_id, embedding FROM {$this->table_name}",
			ARRAY_A
		);

		if ( empty( $embeddings ) ) {
			return array();
		}

		$results = array();

		foreach ( $embeddings as $row ) {
			$post_id   = (int) $row['post_id'];
			$embedding = json_decode( $row['embedding'], true );

			if ( ! is_array( $embedding ) ) {
				continue;
			}

			// Check if post exists and is of allowed type.
			$post = get_post( $post_id );

			if ( ! $post || ! in_array( $post->post_type, $post_types, true ) ) {
				continue;
			}

			// Check if post is published.
			if ( 'publish' !== $post->post_status ) {
				continue;
			}

			// Calculate cosine similarity.
			$similarity = $this->cosine_similarity( $query_embedding, $embedding );

			if ( $similarity >= $threshold ) {
				$results[] = array(
					'post_id'    => $post_id,
					'similarity' => $similarity,
				);
			}
		}

		// Sort by similarity descending.
		usort(
			$results,
			function ( $a, $b ) {
				return $b['similarity'] <=> $a['similarity'];
			}
		);

		// Limit results.
		return array_slice( $results, 0, $limit );
	}

	/**
	 * Calculate cosine similarity between two vectors.
	 *
	 * @param array $vector_a First vector.
	 * @param array $vector_b Second vector.
	 * @return float Similarity score between 0 and 1.
	 */
	private function cosine_similarity( array $vector_a, array $vector_b ): float {
		// Vectors must be same length.
		if ( count( $vector_a ) !== count( $vector_b ) ) {
			return 0.0;
		}

		$dot_product = 0.0;
		$magnitude_a = 0.0;
		$magnitude_b = 0.0;

		for ( $i = 0, $len = count( $vector_a ); $i < $len; $i++ ) {
			$dot_product += $vector_a[ $i ] * $vector_b[ $i ];
			$magnitude_a += $vector_a[ $i ] * $vector_a[ $i ];
			$magnitude_b += $vector_b[ $i ] * $vector_b[ $i ];
		}

		$magnitude_a = sqrt( $magnitude_a );
		$magnitude_b = sqrt( $magnitude_b );

		if ( 0.0 === $magnitude_a || 0.0 === $magnitude_b ) {
			return 0.0;
		}

		return $dot_product / ( $magnitude_a * $magnitude_b );
	}

	/**
	 * Get total count of stored embeddings.
	 *
	 * @return int Number of stored embeddings.
	 */
	public function get_count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

		return (int) $count;
	}

	/**
	 * Check if a post needs re-indexing based on content hash.
	 *
	 * @param int    $post_id      The post ID.
	 * @param string $content_hash Current content hash.
	 * @return bool True if re-indexing needed.
	 */
	public function needs_reindex( int $post_id, string $content_hash ): bool {
		$existing = $this->get_embedding( $post_id );

		if ( null === $existing ) {
			return true;
		}

		return $existing['content_hash'] !== $content_hash;
	}

	/**
	 * Get posts that need indexing.
	 *
	 * @param array $post_types Post types to check.
	 * @param int   $limit      Maximum number to return.
	 * @return array Array of post IDs.
	 */
	public function get_posts_needing_index( array $post_types = array( 'post', 'page' ), int $limit = 100 ): array {
		global $wpdb;

		$post_types_placeholder = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// Get published posts without embeddings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$this->table_name} e ON p.ID = e.post_id
				WHERE p.post_status = 'publish'
				AND p.post_type IN ({$post_types_placeholder})
				AND e.post_id IS NULL
				LIMIT %d",
				...array_merge( $post_types, array( $limit ) )
			)
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Clear all embeddings.
	 *
	 * @return bool True on success.
	 */
	public function clear_all(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

		// Clear cache group.
		wp_cache_flush_group( 'intentpress' );

		return false !== $result;
	}
}
