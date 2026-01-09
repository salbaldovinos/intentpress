<?php
/**
 * Embedding service class.
 *
 * Handles communication with OpenAI API for generating
 * text embeddings.
 *
 * @package IntentPress
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IntentPress Embedding Service class.
 *
 * @since 1.0.0
 */
class IntentPress_Embedding_Service {

	/**
	 * OpenAI API endpoint.
	 *
	 * @var string
	 */
	private const API_ENDPOINT = 'https://api.openai.com/v1/embeddings';

	/**
	 * Default embedding model.
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'text-embedding-3-small';

	/**
	 * Maximum text length for embedding.
	 *
	 * @var int
	 */
	private const MAX_TEXT_LENGTH = 8000;

	/**
	 * API request timeout in seconds.
	 *
	 * @var int
	 */
	private const REQUEST_TIMEOUT = 30;

	/**
	 * Generate embedding for text.
	 *
	 * @param string $text  The text to embed.
	 * @param string $model Optional model to use.
	 * @return array|WP_Error Embedding vector or error.
	 */
	public function generate_embedding( string $text, string $model = self::DEFAULT_MODEL ): array|WP_Error {
		// Validate API key.
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'intentpress_no_api_key',
				__( 'OpenAI API key not configured.', 'intentpress' )
			);
		}

		// Sanitize and truncate text.
		$text = $this->prepare_text( $text );

		if ( empty( $text ) ) {
			return new WP_Error(
				'intentpress_empty_text',
				__( 'Cannot generate embedding for empty text.', 'intentpress' )
			);
		}

		// Check cache first.
		$cache_key = 'embed_' . md5( $text . $model );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Make API request.
		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => self::REQUEST_TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'input' => $text,
						'model' => $model,
					)
				),
			)
		);

		// Check for WP error.
		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API request failed: ' . $response->get_error_message() );
			return $response;
		}

		// Check HTTP status.
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			$error = $this->parse_api_error( $response, $status_code );
			$this->log_error( 'API error (' . $status_code . '): ' . $error->get_error_message() );
			return $error;
		}

		// Parse response.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'intentpress_json_error',
				__( 'Failed to parse API response.', 'intentpress' )
			);
		}

		// Extract embedding.
		if ( ! isset( $data['data'][0]['embedding'] ) ) {
			return new WP_Error(
				'intentpress_invalid_response',
				__( 'Invalid response structure from API.', 'intentpress' )
			);
		}

		$embedding = $data['data'][0]['embedding'];

		// Cache for 1 hour.
		$cache_ttl = (int) get_option( 'intentpress_cache_ttl', 3600 );
		set_transient( $cache_key, $embedding, $cache_ttl );

		return $embedding;
	}

	/**
	 * Generate embedding for a post.
	 *
	 * @param int|WP_Post $post Post ID or object.
	 * @return array|WP_Error Embedding vector or error.
	 */
	public function generate_embedding_for_post( int|WP_Post $post ): array|WP_Error {
		$post = get_post( $post );

		if ( ! $post ) {
			return new WP_Error(
				'intentpress_invalid_post',
				__( 'Post not found.', 'intentpress' )
			);
		}

		// Build text content for embedding.
		$content = $this->build_post_content( $post );

		return $this->generate_embedding( $content );
	}

	/**
	 * Build content string for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return string Combined content for embedding.
	 */
	public function build_post_content( WP_Post $post ): string {
		$parts = array();

		// Add title (weighted higher by prepending).
		$parts[] = $post->post_title;

		// Add excerpt if available.
		if ( ! empty( $post->post_excerpt ) ) {
			$parts[] = $post->post_excerpt;
		}

		// Add main content.
		$content = wp_strip_all_tags( $post->post_content );
		$content = preg_replace( '/\s+/', ' ', $content );
		$parts[] = trim( $content );

		/**
		 * Filter the content parts before combining.
		 *
		 * @since 1.0.0
		 *
		 * @param array   $parts Array of content parts.
		 * @param WP_Post $post  The post object.
		 */
		$parts = apply_filters( 'intentpress_embedding_content_parts', $parts, $post );

		return implode( ' ', array_filter( $parts ) );
	}

	/**
	 * Generate content hash for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return string MD5 hash of content.
	 */
	public function generate_content_hash( WP_Post $post ): string {
		$content = $this->build_post_content( $post );
		return md5( $content );
	}

	/**
	 * Validate API key format and connectivity.
	 *
	 * @param string $api_key API key to validate.
	 * @return bool|WP_Error True if valid, WP_Error if not.
	 */
	public function validate_api_key( string $api_key ): bool|WP_Error {
		// Check format (sk-... or sk-proj-...).
		// OpenAI keys can be: sk-xxx, sk-proj-xxx, and may contain underscores and hyphens.
		if ( ! preg_match( '/^sk-[a-zA-Z0-9_-]{20,}$/', $api_key ) ) {
			return new WP_Error(
				'intentpress_invalid_key_format',
				__( 'Invalid API key format. Keys should start with "sk-".', 'intentpress' )
			);
		}

		// Test with a simple embedding request.
		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'input' => 'test',
						'model' => self::DEFAULT_MODEL,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'intentpress_connection_error',
				__( 'Unable to connect to OpenAI API.', 'intentpress' )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $status_code ) {
			return new WP_Error(
				'intentpress_invalid_api_key',
				__( 'Invalid API key. Please check your OpenAI API key.', 'intentpress' )
			);
		}

		if ( 200 !== $status_code ) {
			return $this->parse_api_error( $response, $status_code );
		}

		return true;
	}

	/**
	 * Get the stored API key.
	 *
	 * @return string Decrypted API key.
	 */
	public function get_api_key(): string {
		$encrypted_key = get_option( 'intentpress_api_key', '' );

		if ( empty( $encrypted_key ) ) {
			return '';
		}

		// Decrypt key.
		return $this->decrypt_api_key( $encrypted_key );
	}

	/**
	 * Store the API key securely.
	 *
	 * @param string $api_key The API key to store.
	 * @return bool True on success.
	 */
	public function store_api_key( string $api_key ): bool {
		if ( empty( $api_key ) ) {
			delete_option( 'intentpress_api_key' );
			return true;
		}

		$encrypted = $this->encrypt_api_key( $api_key );
		return update_option( 'intentpress_api_key', $encrypted );
	}

	/**
	 * Prepare text for embedding.
	 *
	 * @param string $text Raw text.
	 * @return string Prepared text.
	 */
	private function prepare_text( string $text ): string {
		// Remove HTML tags.
		$text = wp_strip_all_tags( $text );

		// Normalize whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );

		// Trim.
		$text = trim( $text );

		// Truncate if needed.
		if ( strlen( $text ) > self::MAX_TEXT_LENGTH ) {
			$text = substr( $text, 0, self::MAX_TEXT_LENGTH );
			// Try to end at a word boundary.
			$text = preg_replace( '/\s+\S*$/', '', $text );
		}

		return $text;
	}

	/**
	 * Parse API error response.
	 *
	 * @param array $response    API response.
	 * @param int   $status_code HTTP status code.
	 * @return WP_Error Error object.
	 */
	private function parse_api_error( array $response, int $status_code ): WP_Error {
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$message = match ( $status_code ) {
			401     => __( 'Invalid API key.', 'intentpress' ),
			429     => __( 'Rate limit exceeded. Please try again later.', 'intentpress' ),
			500     => __( 'OpenAI service error. Please try again later.', 'intentpress' ),
			default => $data['error']['message'] ?? __( 'Unknown API error.', 'intentpress' ),
		};

		return new WP_Error(
			'intentpress_api_error_' . $status_code,
			$message,
			array( 'status' => $status_code )
		);
	}

	/**
	 * Encrypt API key for storage.
	 *
	 * @param string $api_key Plain text API key.
	 * @return string Encrypted key.
	 */
	private function encrypt_api_key( string $api_key ): string {
		// Use WordPress salts for encryption.
		$key  = wp_salt( 'auth' );
		$iv   = substr( wp_salt( 'secure_auth' ), 0, 16 );
		$data = openssl_encrypt( $api_key, 'AES-256-CBC', $key, 0, $iv );

		return base64_encode( $data );
	}

	/**
	 * Decrypt stored API key.
	 *
	 * @param string $encrypted_key Base64 encoded encrypted key.
	 * @return string Decrypted API key.
	 */
	private function decrypt_api_key( string $encrypted_key ): string {
		$key       = wp_salt( 'auth' );
		$iv        = substr( wp_salt( 'secure_auth' ), 0, 16 );
		$encrypted = base64_decode( $encrypted_key );

		$decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );

		return false === $decrypted ? '' : $decrypted;
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
			error_log( 'IntentPress: ' . $message );
		}
	}

	/**
	 * Get the embedding model name.
	 *
	 * @return string Model name.
	 */
	public function get_model(): string {
		return self::DEFAULT_MODEL;
	}

	/**
	 * Get embedding dimensions for the current model.
	 *
	 * @return int Number of dimensions.
	 */
	public function get_dimensions(): int {
		// text-embedding-3-small has 1536 dimensions.
		return 1536;
	}
}
