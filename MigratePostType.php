<?php
/**
 * CLI Command to migrate posts from one post type to another.
 * Optionally set a taxonomy term to assign to each migrated post.
 */

namespace TyB\CLI;

/**
 * CLI command to migrate post types
 */
class MigratePostType extends \WP_CLI_Command {

	/**
	 * The post type to migrate from
	 *
	 * @var string
	 */
	protected $from = '';

	/**
	 * The post type to migrate to
	 *
	 * @var string
	 */
	protected $to = '';

	/**
	 * How many items to process per run
	 * Default is 150 posts.
	 *
	 * @var int
	 */
	protected $per_page = 150;

	/**
	 * The query offset for where to start in the results.
	 *
	 * @var int
	 */
	protected $offset = 0;

	/**
	 * Assign posts to specific term in a taxonomy
	 *
	 * @var string
	 */
	protected $taxonomy = '';

	/**
	 * Term to assign posts to
	 *
	 * @var string
	 */
	protected $term = '';

	/**
	 * Whether to run script in dry-run mode or not.
	 * Default is true.
	 *
	 * @var boolean
	 */
	protected $dry_run = true;

	/**
	 * Setup needed variables
	 *
	 * @param array $args       The CLI arguments.
	 * @param array $assoc_args The associative array of arguments.
	 */
	public function __construct( $args, $assoc_args ) {

		// Determine the post type to migrate from
		if ( isset( $assoc_args['from'] ) ) {
			$this->from = sanitize_text_field( $assoc_args['from'] );
		}

		// Determine the post type to migrate to
		if ( isset( $assoc_args['to'] ) ) {
			$this->to = sanitize_text_field( $assoc_args['to'] );
		}

		// Determine the term and taxonomy to assign the posts to
		if ( isset( $assoc_args['taxonomy'] ) ) {
			$this->taxonomy = sanitize_text_field( $assoc_args['taxonomy'] );
		}

		if ( isset( $assoc_args['term'] ) ) {
			$this->term = sanitize_text_field( $assoc_args['term'] );
		}

		// Determine how many items to process this run
		if ( isset( $assoc_args['per-page'] ) ) {
			$this->per_page = (int) $assoc_args['per-page'];
		}

		// Determine our offset
		if ( isset( $assoc_args['offset'] ) ) {
			$this->offset = (int) $assoc_args['offset'];
		}

		// Dry run is always true by default. Have to explicitly pass false to run
		if ( isset( $assoc_args['dry-run'] ) && 'false' === $assoc_args['dry-run'] ) {
			$this->dry_run = false;
		}

		$this->count  = 0;
		$this->errors = 0;
	}

	/**
	 * Start migration process
	 */
	public function run() {
		Handler::start_timer();

		// Get all posts of requested type
		$query_args = [
			'post_type'      => $this->from,
			'posts_per_page' => $this->per_page,
			'offset'         => $this->offset,
			'post_status'    => 'any',
		];

		$query = new \WP_Query( $query_args );

		if ( $query->have_posts() ) {
			// Setup our progress bar
			$progress = \WP_CLI\Utils\make_progress_bar( 'Migrating...', count( $query->posts ) );

			// Loop through each response
			foreach ( $query->posts as $post ) {
				$post->post_type = $this->to;

				// Migrate the post to the new post type
				$this->create( $post );

				// Tick the progress bar
				$progress->tick();

				// Free memory every 50 posts
				if ( 0 === $this->count % 50 ) {
					\WP_CLI::log( 'Sleeping...' );
					sleep( 1 );
					$this->stop_the_insanity();
				}
			}

			// Close out our progress bar and log the success message
			$progress->finish();
			\WP_CLI::success( sprintf( 'Successfully Migrated %d posts', absint( $this->count ) ) );
			\WP_CLI::log( \WP_CLI::colorize( '%YTotal time elapsed: %N' . Handler::stop_timer() ) );

			// Output errors if there were any
			if ( 0 < $this->errors ) {
				\WP_CLI::warning( sprintf( '%d failed to migrate', absint( $this->errors ) ) );
			}

			return false;
		}

		\WP_CLI::warning( sprintf( 'No posts of post type "%s" found', $this->from ) );

		return false;
	}

	/**
	 * Create our item
	 *
	 * @param  object $post_data Post data.
	 * @return int|boolean
	 */
	protected function create( $post_data = [] ) {

		if ( $post_data->post_type === $this->to && is_a( $post_data, '\WP_Post' ) ) {

			// No dry run? Update the post
			if ( false === $this->dry_run ) {
				$post_id = wp_update_post( $post_data, true );

				// Create terms if arguments are set
				if ( ! is_wp_error( $post_id ) && $this->term && $this->taxonomy ) {
					$this->set_terms( $post_id );
				}
			} else {
				// For dry run output
				$post_id = $post_data->ID;
			}
		}

		if ( is_wp_error( $post_id ) ) {
			\WP_CLI::warning(
				sprintf(
					'Error inserting item "%s": %s',
					esc_html( $post_data->post_title ),
					esc_html( $post_id->get_error_message() )
				)
			);

			// Increment the error count
			$this->errors++;
			return;
		} else {
			// Increment the success count
			$this->count++;
		}

		return $post_id;
	}

	/**
	 * Set post taxonomy terms based on arguments
	 *
	 * @param int $post_id Post ID.
	 */
	protected function set_terms( $post_id ) {
		if ( $this->dry_run ) {
			return;
		}

		$term_id     = 0;
		$term_exists = null;

		// Check if the requested term exists
		$term_exists = term_exists( $this->term, $this->taxonomy, 0 );

		if ( null === $term_exists ) {
			// If term doesnt exist, create it.
			$term = wp_insert_term(
				$this->term,
				$this->taxonomy
			);

			// Error inserting term? Log an error.
			if ( is_wp_error( $term ) ) {
				\WP_CLI::warning(
					sprintf(
						'Error inserting term: %s',
						esc_html( $term->get_error_message() )
					)
				);
			} else {
				// Else extract the term ID.
				$term_id = is_array( $term ) && isset( $term['term_id'] ) ? absint( $term['term_id'] ) : 0;
			}
		} else {
			// Term exists, lets get the ID.
			$category = get_term_by( 'name', $this->term, $this->taxonomy );
			$term_id  = $category->term_id;
		}

		// Assign the post to the term
		if ( $term_id && ! is_wp_error( $term_id ) && 0 !== $term_id ) {
			wp_set_object_terms( $post_id, $term_id, $this->taxonomy );
		}
	}

	/**
	 * Clear caches + free up memory
	 *
	 * @return void
	 */
	protected function stop_the_insanity() {
		/**
		 * WP Globals
		 *
		 * @var \WP_Object_Cache $wp_object_cache
		 * @var \wpdb $wpdb
		 */
		global $wpdb, $wp_object_cache;
		$wpdb->queries = [];

		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = [];
			$wp_object_cache->stats          = [];
			$wp_object_cache->memcache_debug = [];
			$wp_object_cache->cache          = [];

			// Helps define caching standards for import process
			if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset(); // important
			}
		}
	}
}
