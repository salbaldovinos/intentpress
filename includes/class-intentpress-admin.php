<?php
/**
 * Admin class.
 *
 * Handles admin page registration, asset enqueuing, and admin-specific
 * functionality.
 *
 * @package IntentPress
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IntentPress Admin class.
 *
 * @since 1.0.0
 */
class IntentPress_Admin {

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private string $page_hook = '';

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
	 * Initialize admin functionality.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
		add_filter( 'plugin_action_links_' . INTENTPRESS_PLUGIN_BASENAME, array( $this, 'add_plugin_links' ) );

		// Handle post save to update embeddings.
		add_action( 'save_post', array( $this, 'handle_post_save' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'handle_post_delete' ) );
	}

	/**
	 * Add admin menu page.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		$this->page_hook = add_options_page(
			__( 'IntentPress Settings', 'intentpress' ),
			__( 'IntentPress', 'intentpress' ),
			'manage_options',
			'intentpress',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		// Security check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'intentpress' ) );
		}

		?>
		<div class="wrap">
			<div id="intentpress-admin-root"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only load on our admin page.
		if ( $hook_suffix !== $this->page_hook ) {
			return;
		}

		$asset_file = INTENTPRESS_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$assets = require $asset_file;

		// Enqueue script.
		wp_enqueue_script(
			'intentpress-admin',
			INTENTPRESS_PLUGIN_URL . 'build/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		// Enqueue styles.
		wp_enqueue_style(
			'intentpress-admin',
			INTENTPRESS_PLUGIN_URL . 'build/index.css',
			array( 'wp-components' ),
			$assets['version']
		);

		// Localize script with data.
		wp_localize_script(
			'intentpress-admin',
			'intentpressAdmin',
			$this->get_localized_data()
		);

		// Set script translations.
		wp_set_script_translations(
			'intentpress-admin',
			'intentpress',
			INTENTPRESS_PLUGIN_DIR . 'languages'
		);
	}

	/**
	 * Get localized data for admin script.
	 *
	 * @return array Localized data.
	 */
	private function get_localized_data(): array {
		$api_key = $this->embedding_service->get_api_key();

		return array(
			'apiUrl'       => rest_url( 'intentpress/v1' ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'version'      => INTENTPRESS_VERSION,
			'isConfigured' => ! empty( $api_key ),
			'postTypes'    => $this->get_available_post_types(),
			'limits'       => array(
				'freeSearches' => 1000,
				'freeIndex'    => 500,
			),
		);
	}

	/**
	 * Get available post types for indexing.
	 *
	 * @return array Post types.
	 */
	private function get_available_post_types(): array {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		$available = array();

		foreach ( $post_types as $post_type ) {
			// Skip attachments.
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$available[] = array(
				'value' => $post_type->name,
				'label' => $post_type->label,
			);
		}

		return $available;
	}

	/**
	 * Display admin notices.
	 *
	 * @return void
	 */
	public function display_admin_notices(): void {
		// Only show on our page or dashboard.
		$screen = get_current_screen();

		if ( ! $screen || ( 'settings_page_intentpress' !== $screen->id && 'dashboard' !== $screen->id ) ) {
			return;
		}

		// Check if API key is configured.
		$api_key = $this->embedding_service->get_api_key();

		if ( empty( $api_key ) && 'settings_page_intentpress' !== $screen->id ) {
			?>
			<div class="notice notice-warning">
				<p>
					<?php
					printf(
						/* translators: %s: settings page link */
						esc_html__( 'IntentPress requires an OpenAI API key to work. %s to configure.', 'intentpress' ),
						'<a href="' . esc_url( admin_url( 'options-general.php?page=intentpress' ) ) . '">' .
						esc_html__( 'Go to settings', 'intentpress' ) . '</a>'
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing links.
	 * @return array Modified links.
	 */
	public function add_plugin_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=intentpress' ) ),
			esc_html__( 'Settings', 'intentpress' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Handle post save to update embeddings.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public function handle_post_save( int $post_id, WP_Post $post, bool $update ): void {
		// Skip autosaves and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check if post type should be indexed.
		$indexed_types = get_option( 'intentpress_indexed_post_types', array( 'post', 'page' ) );

		if ( ! in_array( $post->post_type, $indexed_types, true ) ) {
			return;
		}

		// Only index published posts.
		if ( 'publish' !== $post->post_status ) {
			// If post was unpublished, remove embedding.
			$this->vector_store->delete_embedding( $post_id );
			return;
		}

		// Check if content has changed.
		$content_hash = $this->embedding_service->generate_content_hash( $post );

		if ( ! $this->vector_store->needs_reindex( $post_id, $content_hash ) ) {
			return;
		}

		// Schedule embedding generation (don't block post save).
		wp_schedule_single_event(
			time(),
			'intentpress_generate_embedding',
			array( $post_id )
		);

		// Register the action if not already done.
		if ( ! has_action( 'intentpress_generate_embedding' ) ) {
			add_action( 'intentpress_generate_embedding', array( $this, 'generate_post_embedding' ) );
		}
	}

	/**
	 * Generate embedding for a post (async).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function generate_post_embedding( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		// Generate embedding.
		$embedding = $this->embedding_service->generate_embedding_for_post( $post );

		if ( is_wp_error( $embedding ) ) {
			// Log error but don't break.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'IntentPress: Failed to generate embedding for post ' . $post_id . ': ' . $embedding->get_error_message() );
			}
			return;
		}

		// Store embedding.
		$content_hash = $this->embedding_service->generate_content_hash( $post );
		$this->vector_store->store_embedding( $post_id, $embedding, $content_hash );
	}

	/**
	 * Handle post deletion.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_post_delete( int $post_id ): void {
		$this->vector_store->delete_embedding( $post_id );
	}
}
