<?php
/**
 * IntentPress Search Integration
 *
 * Hooks into WordPress search to replace default keyword search
 * with semantic search, similar to how Relevanssi works.
 *
 * @package IntentPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search Integration class.
 *
 * Replaces WordPress default search with IntentPress semantic search.
 */
class IntentPress_Search_Integration {

	/**
	 * Search handler instance.
	 *
	 * @var IntentPress_Search_Handler
	 */
	private IntentPress_Search_Handler $search_handler;

	/**
	 * Whether search replacement is enabled.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Store search results for template use.
	 *
	 * @var array|null
	 */
	private ?array $search_results = null;

	/**
	 * Store search metadata.
	 *
	 * @var array|null
	 */
	private ?array $search_meta = null;

	/**
	 * Constructor.
	 *
	 * @param IntentPress_Search_Handler $search_handler Search handler instance.
	 */
	public function __construct( IntentPress_Search_Handler $search_handler ) {
		$this->search_handler = $search_handler;
		$this->enabled        = (bool) get_option( 'intentpress_replace_search', true );
	}

	/**
	 * Initialize hooks.
	 */
	public function init(): void {
		if ( $this->enabled ) {
			// Hook into the main query to intercept searches.
			add_action( 'pre_get_posts', array( $this, 'intercept_search' ), 10, 1 );

			// Filter posts_pre_query to return our results.
			add_filter( 'posts_pre_query', array( $this, 'filter_search_results' ), 10, 2 );

			// Add relevance data to posts.
			add_filter( 'the_posts', array( $this, 'add_relevance_data' ), 10, 2 );
		}

		// Register shortcodes (always available).
		add_shortcode( 'intentpress_search', array( $this, 'search_form_shortcode' ) );
		add_shortcode( 'intentpress_results', array( $this, 'search_results_shortcode' ) );

		// Register widget.
		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		// Add search metadata to body class.
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Intercept search queries.
	 *
	 * @param WP_Query $query The query object.
	 */
	public function intercept_search( WP_Query $query ): void {
		// Only intercept main frontend search queries.
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		$search_query = get_search_query();

		if ( empty( $search_query ) ) {
			return;
		}

		// Get pagination parameters.
		$paged    = $query->get( 'paged' ) ? absint( $query->get( 'paged' ) ) : 1;
		$per_page = $query->get( 'posts_per_page' ) ? absint( $query->get( 'posts_per_page' ) ) : get_option( 'posts_per_page', 10 );

		// Perform semantic search.
		$results = $this->search_handler->search(
			$search_query,
			array(
				'page'     => $paged,
				'per_page' => $per_page,
			)
		);

		if ( is_wp_error( $results ) ) {
			// On error, let WordPress handle the search normally.
			return;
		}

		// Store results for later use.
		$this->search_results = $results['results'];
		$this->search_meta    = $results['meta'];

		// Get post IDs from results.
		$post_ids = wp_list_pluck( $results['results'], 'id' );

		if ( empty( $post_ids ) ) {
			// No results - set to return nothing.
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		// Modify query to fetch these specific posts in order.
		$query->set( 'post__in', $post_ids );
		$query->set( 'orderby', 'post__in' );
		$query->set( 's', '' ); // Clear search to prevent default search behavior.

		// Set pagination.
		$query->set( 'posts_per_page', $per_page );

		// Store total for pagination.
		$query->set( 'intentpress_total', $results['total'] );
		$query->set( 'intentpress_query', $search_query );
	}

	/**
	 * Filter search results if needed.
	 *
	 * @param array|null $posts Array of posts or null.
	 * @param WP_Query   $query The query object.
	 * @return array|null
	 */
	public function filter_search_results( ?array $posts, WP_Query $query ): ?array {
		// We handle this in pre_get_posts, so just return as-is.
		return $posts;
	}

	/**
	 * Add relevance data to posts.
	 *
	 * @param array    $posts Array of post objects.
	 * @param WP_Query $query The query object.
	 * @return array
	 */
	public function add_relevance_data( array $posts, WP_Query $query ): array {
		if ( null === $this->search_results || ! $query->is_main_query() ) {
			return $posts;
		}

		// Create a map of post IDs to similarity scores.
		$similarity_map = array();
		foreach ( $this->search_results as $result ) {
			$similarity_map[ $result['id'] ] = $result['similarity'] ?? 0;
		}

		// Add similarity score to each post.
		foreach ( $posts as $post ) {
			if ( isset( $similarity_map[ $post->ID ] ) ) {
				$post->intentpress_similarity = $similarity_map[ $post->ID ];
			}
		}

		return $posts;
	}

	/**
	 * Add body class for semantic search.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_class( array $classes ): array {
		if ( is_search() && null !== $this->search_meta ) {
			$classes[] = 'intentpress-search';

			if ( ! empty( $this->search_meta['search_type'] ) ) {
				$classes[] = 'intentpress-search-' . sanitize_html_class( $this->search_meta['search_type'] );
			}

			if ( ! empty( $this->search_meta['fallback_used'] ) ) {
				$classes[] = 'intentpress-fallback';
			}
		}

		return $classes;
	}

	/**
	 * Search form shortcode.
	 *
	 * Usage: [intentpress_search placeholder="Search..." button_text="Search"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function search_form_shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'placeholder'  => __( 'Search...', 'intentpress' ),
				'button_text'  => __( 'Search', 'intentpress' ),
				'class'        => '',
				'show_button'  => 'true',
				'autofocus'    => 'false',
			),
			$atts,
			'intentpress_search'
		);

		$form_class   = 'intentpress-search-form';
		$custom_class = sanitize_html_class( $atts['class'] );

		if ( $custom_class ) {
			$form_class .= ' ' . $custom_class;
		}

		$current_query = get_search_query();
		$show_button   = filter_var( $atts['show_button'], FILTER_VALIDATE_BOOLEAN );
		$autofocus     = filter_var( $atts['autofocus'], FILTER_VALIDATE_BOOLEAN );

		ob_start();
		?>
		<form role="search" method="get" class="<?php echo esc_attr( $form_class ); ?>" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="intentpress-search-label screen-reader-text" for="intentpress-search-input">
				<?php esc_html_e( 'Search for:', 'intentpress' ); ?>
			</label>
			<input
				type="search"
				id="intentpress-search-input"
				class="intentpress-search-input"
				placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
				value="<?php echo esc_attr( $current_query ); ?>"
				name="s"
				<?php echo $autofocus ? 'autofocus' : ''; ?>
			/>
			<?php if ( $show_button ) : ?>
				<button type="submit" class="intentpress-search-button">
					<?php echo esc_html( $atts['button_text'] ); ?>
				</button>
			<?php endif; ?>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Search results shortcode.
	 *
	 * Usage: [intentpress_results per_page="10" show_excerpt="true" show_relevance="true"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function search_results_shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'per_page'       => 10,
				'show_excerpt'   => 'true',
				'show_relevance' => 'false',
				'show_meta'      => 'true',
				'excerpt_length' => 55,
				'class'          => '',
				'no_results'     => __( 'No results found.', 'intentpress' ),
			),
			$atts,
			'intentpress_results'
		);

		$search_query = get_search_query();

		if ( empty( $search_query ) ) {
			return '';
		}

		$per_page       = absint( $atts['per_page'] );
		$show_excerpt   = filter_var( $atts['show_excerpt'], FILTER_VALIDATE_BOOLEAN );
		$show_relevance = filter_var( $atts['show_relevance'], FILTER_VALIDATE_BOOLEAN );
		$show_meta      = filter_var( $atts['show_meta'], FILTER_VALIDATE_BOOLEAN );
		$paged          = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;

		// Perform search.
		$results = $this->search_handler->search(
			$search_query,
			array(
				'page'     => $paged,
				'per_page' => $per_page,
			)
		);

		if ( is_wp_error( $results ) ) {
			return '<p class="intentpress-error">' . esc_html__( 'Search temporarily unavailable.', 'intentpress' ) . '</p>';
		}

		$wrapper_class = 'intentpress-results';
		$custom_class  = sanitize_html_class( $atts['class'] );

		if ( $custom_class ) {
			$wrapper_class .= ' ' . $custom_class;
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php if ( $show_meta ) : ?>
				<div class="intentpress-results-meta">
					<p class="intentpress-results-count">
						<?php
						printf(
							/* translators: 1: number of results, 2: search query */
							esc_html( _n( '%1$d result for "%2$s"', '%1$d results for "%2$s"', $results['total'], 'intentpress' ) ),
							absint( $results['total'] ),
							esc_html( $search_query )
						);
						?>
					</p>
					<?php if ( ! empty( $results['meta']['search_type'] ) && 'semantic' === $results['meta']['search_type'] ) : ?>
						<span class="intentpress-semantic-badge">
							<?php esc_html_e( 'Semantic Search', 'intentpress' ); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( empty( $results['results'] ) ) : ?>
				<p class="intentpress-no-results"><?php echo esc_html( $atts['no_results'] ); ?></p>
			<?php else : ?>
				<ul class="intentpress-results-list">
					<?php foreach ( $results['results'] as $result ) : ?>
						<li class="intentpress-result-item">
							<article class="intentpress-result">
								<h3 class="intentpress-result-title">
									<a href="<?php echo esc_url( $result['url'] ); ?>">
										<?php echo esc_html( $result['title'] ); ?>
									</a>
								</h3>

								<?php if ( $show_excerpt && ! empty( $result['excerpt'] ) ) : ?>
									<p class="intentpress-result-excerpt">
										<?php echo wp_kses_post( $result['excerpt'] ); ?>
									</p>
								<?php endif; ?>

								<div class="intentpress-result-footer">
									<span class="intentpress-result-type">
										<?php echo esc_html( get_post_type_object( $result['post_type'] )->labels->singular_name ?? $result['post_type'] ); ?>
									</span>

									<?php if ( $show_relevance && isset( $result['similarity'] ) ) : ?>
										<span class="intentpress-result-relevance">
											<?php
											printf(
												/* translators: %s: relevance percentage */
												esc_html__( '%s%% relevant', 'intentpress' ),
												number_format( $result['similarity'] * 100, 0 )
											);
											?>
										</span>
									<?php endif; ?>
								</div>
							</article>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php $this->render_pagination( $results['total'], $per_page, $paged ); ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render pagination.
	 *
	 * @param int $total    Total number of results.
	 * @param int $per_page Results per page.
	 * @param int $current  Current page.
	 */
	private function render_pagination( int $total, int $per_page, int $current ): void {
		$total_pages = ceil( $total / $per_page );

		if ( $total_pages <= 1 ) {
			return;
		}

		$pagination = paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'current'   => $current,
				'total'     => $total_pages,
				'prev_text' => __( '&laquo; Previous', 'intentpress' ),
				'next_text' => __( 'Next &raquo;', 'intentpress' ),
				'type'      => 'array',
			)
		);

		if ( $pagination ) {
			echo '<nav class="intentpress-pagination" aria-label="' . esc_attr__( 'Search results pagination', 'intentpress' ) . '">';
			echo '<ul class="intentpress-pagination-list">';
			foreach ( $pagination as $link ) {
				echo '<li class="intentpress-pagination-item">' . wp_kses_post( $link ) . '</li>';
			}
			echo '</ul>';
			echo '</nav>';
		}
	}

	/**
	 * Register search widget.
	 */
	public function register_widget(): void {
		register_widget( 'IntentPress_Search_Widget' );
	}

	/**
	 * Get search metadata.
	 *
	 * @return array|null
	 */
	public function get_search_meta(): ?array {
		return $this->search_meta;
	}

	/**
	 * Check if semantic search was used.
	 *
	 * @return bool
	 */
	public function is_semantic_search(): bool {
		return null !== $this->search_meta && 'semantic' === ( $this->search_meta['search_type'] ?? '' );
	}

	/**
	 * Check if fallback was used.
	 *
	 * @return bool
	 */
	public function is_fallback_search(): bool {
		return null !== $this->search_meta && ! empty( $this->search_meta['fallback_used'] );
	}
}

/**
 * IntentPress Search Widget.
 */
class IntentPress_Search_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'intentpress_search',
			__( 'IntentPress Search', 'intentpress' ),
			array(
				'description' => __( 'AI-powered semantic search form.', 'intentpress' ),
				'classname'   => 'widget-intentpress-search',
			)
		);
	}

	/**
	 * Output widget content.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ): void {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		echo wp_kses_post( $args['before_widget'] );

		if ( $title ) {
			echo wp_kses_post( $args['before_title'] . $title . $args['after_title'] );
		}

		$placeholder = ! empty( $instance['placeholder'] ) ? $instance['placeholder'] : __( 'Search...', 'intentpress' );
		$button_text = ! empty( $instance['button_text'] ) ? $instance['button_text'] : __( 'Search', 'intentpress' );

		echo do_shortcode(
			sprintf(
				'[intentpress_search placeholder="%s" button_text="%s"]',
				esc_attr( $placeholder ),
				esc_attr( $button_text )
			)
		);

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Output widget settings form.
	 *
	 * @param array $instance Widget instance.
	 * @return void
	 */
	public function form( $instance ): void {
		$title       = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$placeholder = ! empty( $instance['placeholder'] ) ? $instance['placeholder'] : __( 'Search...', 'intentpress' );
		$button_text = ! empty( $instance['button_text'] ) ? $instance['button_text'] : __( 'Search', 'intentpress' );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'intentpress' ); ?>
			</label>
			<input
				type="text"
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>"
			/>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'placeholder' ) ); ?>">
				<?php esc_html_e( 'Placeholder:', 'intentpress' ); ?>
			</label>
			<input
				type="text"
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'placeholder' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'placeholder' ) ); ?>"
				value="<?php echo esc_attr( $placeholder ); ?>"
			/>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'button_text' ) ); ?>">
				<?php esc_html_e( 'Button Text:', 'intentpress' ); ?>
			</label>
			<input
				type="text"
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'button_text' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'button_text' ) ); ?>"
				value="<?php echo esc_attr( $button_text ); ?>"
			/>
		</p>
		<?php
	}

	/**
	 * Save widget settings.
	 *
	 * @param array $new_instance New instance.
	 * @param array $old_instance Old instance.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ): array {
		$instance                = array();
		$instance['title']       = sanitize_text_field( $new_instance['title'] ?? '' );
		$instance['placeholder'] = sanitize_text_field( $new_instance['placeholder'] ?? '' );
		$instance['button_text'] = sanitize_text_field( $new_instance['button_text'] ?? '' );
		return $instance;
	}
}

/**
 * Template tag: Check if current search uses IntentPress.
 *
 * @return bool
 */
function intentpress_is_semantic_search(): bool {
	global $intentpress_search_integration;
	return $intentpress_search_integration instanceof IntentPress_Search_Integration
		&& $intentpress_search_integration->is_semantic_search();
}

/**
 * Template tag: Get search relevance score for current post.
 *
 * @param WP_Post|int|null $post Post object or ID.
 * @return float|null
 */
function intentpress_get_relevance( $post = null ): ?float {
	$post = get_post( $post );

	if ( ! $post || ! isset( $post->intentpress_similarity ) ) {
		return null;
	}

	return (float) $post->intentpress_similarity;
}

/**
 * Template tag: Display search relevance as percentage.
 *
 * @param WP_Post|int|null $post Post object or ID.
 */
function intentpress_the_relevance( $post = null ): void {
	$relevance = intentpress_get_relevance( $post );

	if ( null !== $relevance ) {
		printf(
			'<span class="intentpress-relevance">%s%%</span>',
			esc_html( number_format( $relevance * 100, 0 ) )
		);
	}
}
