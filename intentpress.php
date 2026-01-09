<?php
/**
 * IntentPress - AI-Powered Semantic Search for WordPress
 *
 * @package     IntentPress
 * @author      IntentPress
 * @copyright   2024 IntentPress
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       IntentPress
 * Plugin URI:        https://intentpress.com
 * Description:       Replace WordPress's keyword-based search with AI-powered semantic search using OpenAI embeddings.
 * Version:           0.2.2
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            IntentPress
 * Author URI:        https://intentpress.com
 * Text Domain:       intentpress
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
define( 'INTENTPRESS_VERSION', '0.2.2' );

/**
 * Plugin file path.
 *
 * @var string
 */
define( 'INTENTPRESS_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path.
 *
 * @var string
 */
define( 'INTENTPRESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 *
 * @var string
 */
define( 'INTENTPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 *
 * @var string
 */
define( 'INTENTPRESS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main IntentPress class.
 *
 * @since 1.0.0
 */
final class IntentPress {

	/**
	 * Plugin instance.
	 *
	 * @var IntentPress|null
	 */
	private static ?IntentPress $instance = null;

	/**
	 * Activator instance.
	 *
	 * @var IntentPress_Activator|null
	 */
	private ?IntentPress_Activator $activator = null;

	/**
	 * REST API instance.
	 *
	 * @var IntentPress_REST_API|null
	 */
	private ?IntentPress_REST_API $rest_api = null;

	/**
	 * Search handler instance.
	 *
	 * @var IntentPress_Search_Handler|null
	 */
	private ?IntentPress_Search_Handler $search_handler = null;

	/**
	 * Embedding service instance.
	 *
	 * @var IntentPress_Embedding_Service|null
	 */
	private ?IntentPress_Embedding_Service $embedding_service = null;

	/**
	 * Vector store instance.
	 *
	 * @var IntentPress_Vector_Store|null
	 */
	private ?IntentPress_Vector_Store $vector_store = null;

	/**
	 * Search integration instance.
	 *
	 * @var IntentPress_Search_Integration|null
	 */
	private ?IntentPress_Search_Integration $search_integration = null;

	/**
	 * Get plugin instance.
	 *
	 * @return IntentPress Plugin instance.
	 */
	public static function get_instance(): IntentPress {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required dependencies.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		// Core classes.
		require_once INTENTPRESS_PLUGIN_DIR . 'includes/class-intentpress-activator.php';
		require_once INTENTPRESS_PLUGIN_DIR . 'includes/class-intentpress-vector-store.php';
		require_once INTENTPRESS_PLUGIN_DIR . 'includes/class-intentpress-embedding-service.php';
		require_once INTENTPRESS_PLUGIN_DIR . 'includes/class-intentpress-search-handler.php';
		require_once INTENTPRESS_PLUGIN_DIR . 'includes/class-intentpress-search-integration.php';
		require_once INTENTPRESS_PLUGIN_DIR . 'includes/class-intentpress-rest-api.php';
		require_once INTENTPRESS_PLUGIN_DIR . 'includes/class-intentpress-admin.php';
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Activation/deactivation hooks.
		register_activation_hook( INTENTPRESS_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( INTENTPRESS_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Initialize plugin on plugins_loaded.
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public function activate(): void {
		$this->get_activator()->activate();
	}

	/**
	 * Plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		$this->get_activator()->deactivate();
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// Load text domain for translations.
		load_plugin_textdomain(
			'intentpress',
			false,
			dirname( INTENTPRESS_PLUGIN_BASENAME ) . '/languages'
		);

		// Initialize components.
		$this->init_components();

		/**
		 * Fires after IntentPress is fully initialized.
		 *
		 * @since 1.0.0
		 */
		do_action( 'intentpress_init' );
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	private function init_components(): void {
		// Initialize REST API.
		$this->get_rest_api()->init();

		// Initialize search handler.
		$this->get_search_handler()->init();

		// Initialize search integration (hooks into WordPress search).
		$this->get_search_integration()->init();

		// Enqueue frontend styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );

		// Initialize admin if in admin context.
		if ( is_admin() ) {
			$admin = new IntentPress_Admin(
				$this->get_embedding_service(),
				$this->get_vector_store()
			);
			$admin->init();
		}
	}

	/**
	 * Enqueue frontend styles for shortcodes and widgets.
	 *
	 * @return void
	 */
	public function enqueue_frontend_styles(): void {
		wp_enqueue_style(
			'intentpress-frontend',
			INTENTPRESS_PLUGIN_URL . 'assets/css/intentpress-frontend.css',
			array(),
			INTENTPRESS_VERSION
		);
	}

	/**
	 * Get activator instance.
	 *
	 * @return IntentPress_Activator Activator instance.
	 */
	public function get_activator(): IntentPress_Activator {
		if ( null === $this->activator ) {
			$this->activator = new IntentPress_Activator();
		}

		return $this->activator;
	}

	/**
	 * Get REST API instance.
	 *
	 * @return IntentPress_REST_API REST API instance.
	 */
	public function get_rest_api(): IntentPress_REST_API {
		if ( null === $this->rest_api ) {
			$this->rest_api = new IntentPress_REST_API(
				$this->get_search_handler(),
				$this->get_embedding_service(),
				$this->get_vector_store()
			);
		}

		return $this->rest_api;
	}

	/**
	 * Get search handler instance.
	 *
	 * @return IntentPress_Search_Handler Search handler instance.
	 */
	public function get_search_handler(): IntentPress_Search_Handler {
		if ( null === $this->search_handler ) {
			$this->search_handler = new IntentPress_Search_Handler(
				$this->get_embedding_service(),
				$this->get_vector_store()
			);
		}

		return $this->search_handler;
	}

	/**
	 * Get embedding service instance.
	 *
	 * @return IntentPress_Embedding_Service Embedding service instance.
	 */
	public function get_embedding_service(): IntentPress_Embedding_Service {
		if ( null === $this->embedding_service ) {
			$this->embedding_service = new IntentPress_Embedding_Service();
		}

		return $this->embedding_service;
	}

	/**
	 * Get vector store instance.
	 *
	 * @return IntentPress_Vector_Store Vector store instance.
	 */
	public function get_vector_store(): IntentPress_Vector_Store {
		if ( null === $this->vector_store ) {
			$this->vector_store = new IntentPress_Vector_Store();
		}

		return $this->vector_store;
	}

	/**
	 * Get search integration instance.
	 *
	 * @return IntentPress_Search_Integration Search integration instance.
	 */
	public function get_search_integration(): IntentPress_Search_Integration {
		global $intentpress_search_integration;

		if ( null === $this->search_integration ) {
			$this->search_integration = new IntentPress_Search_Integration(
				$this->get_search_handler()
			);
			// Set global for template tags.
			$intentpress_search_integration = $this->search_integration;
		}

		return $this->search_integration;
	}
}

/**
 * Get the IntentPress plugin instance.
 *
 * @since 1.0.0
 *
 * @return IntentPress Plugin instance.
 */
function intentpress(): IntentPress {
	return IntentPress::get_instance();
}

// Initialize the plugin.
intentpress();
