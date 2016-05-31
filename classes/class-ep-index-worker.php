<?php
/**
 * Process indexing of posts
 *
 * @package ElasticPress
 *
 * @since   1.9
 *
 * @author  Chris Wiegman <chris.wiegman@10up.com>
 */

/**
 * Worker Process for Indexing posts
 *
 * Handles and dispatches the indexing of posts.
 */
class EP_Index_Worker {

	/**
	 * Holds the posts that will be bulk synced.
	 *
	 * @since 1.9
	 *
	 * @var array
	 */
	protected $posts;

	/**
	 * Initiate Index Worker
	 *
	 * Initiates the index worker process.
	 *
	 * @since 1.9
	 *
	 * @return EP_Index_Worker
	 */
	public function __construct() {

		$this->posts = array();

	}

	/**
	 * Create network alias
	 *
	 * Helper method for creating the network alias
	 *
	 * @since 1.9
	 *
	 * @return array|bool Array of indexes or false on error
	 */
	public function create_network_alias() {

		$sites   = ep_get_sites();
		$indexes = array();

		foreach ( $sites as $site ) {

			switch_to_blog( $site['blog_id'] );

			$indexes[] = ep_get_index_name();

			restore_current_blog();

		}

		return ep_create_network_alias( $indexes );

	}

	/**
	 * Index all posts
	 *
	 * Index all posts for a site or network wide.
	 *
	 * @since 1.9
	 *
	 * @return bool True on success or false
	 */
	public function index() {

		ep_check_host();

		$result = $this->_index_helper();

		if ( ! empty( $result['errors'] ) ) {
			return false;
		}

		return $result;

	}

	/**
	 * Helper method for indexing posts
	 *
	 * Handles the sync operation for individual posts.
	 *
	 * @since 1.9
	 *
	 * @return array Array of posts successfully synced as well as errors
	 */
	protected function _index_helper() {

		$posts_per_page = apply_filters( 'ep_index_posts_per_page', 350 );

		$offset_transient = get_transient( 'ep_index_offset' );
		$sync_transient   = get_transient( 'ep_index_synced' );

		$synced         = false === $sync_transient ? 0 : absint( $sync_transient );
		$errors         = array();
		$offset         = false === $offset_transient ? 0 : absint( $offset_transient );
		$complete       = false;
		$current_synced = 0;

		$args = apply_filters( 'ep_index_posts_args', array(
			'posts_per_page'      => $posts_per_page,
			'post_type'           => ep_get_indexable_post_types(),
			'post_status'         => ep_get_indexable_post_status(),
			'offset'              => $offset,
			'ignore_sticky_posts' => true,
			'orderby'             => 'ID',
			'order'               => 'DESC',
		) );

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {

			while ( $query->have_posts() ) {

				$query->the_post();

				$result = $this->queue_post( get_the_ID(), $query->post_count, $offset, false );

				if ( ! $result ) {

					$errors[] = get_the_ID();

				} else {

					$current_synced ++;
					$synced ++;

				}
			}

			$totals = get_transient( 'ep_post_count' );

			if ( $totals['total'] === $synced ) {
				$complete = true;
			}
		} else {

			$complete = true;

		}

		$offset += $posts_per_page;

		usleep( 500 ); // Delay to let $wpdb catch up.

		// Avoid running out of memory.
		$this->stop_the_insanity();

		set_transient( 'ep_index_offset', $offset, 600 );
		set_transient( 'ep_index_synced', $synced, 600 );

		if ( true === $complete ) {

			delete_transient( 'ep_index_offset' );

			/**
			 * Allow disabling of bulk error email.
			 *
			 * @since 1.9
			 *
			 * @param bool $to_send true to send bulk errors or false [Default: true]
			 */
			if ( true === apply_filters( 'ep_disable_index_bulk_errors', true ) ) {
				$this->send_bulk_errors();
			}
		}

		wp_reset_postdata();

		return array( 'synced' => $synced, 'current_synced' => $current_synced, 'errors' => $errors );

	}

	/**
	 * Queues up a post for bulk indexing
	 *
	 * Adds individual posts to a queue for later processing.
	 *
	 * @since 1.9
	 *
	 * @param int  $post_id          The post ID to add.
	 * @param int  $bulk_trigger     The maximum number of posts to hold in the queue before triggering a bulk-update operation.
	 * @param int  $offset           The current offset to keep track of.
	 * @param bool $show_bulk_errors True to show bulk errors or false.
	 *
	 * @return bool|int true if successfully synced, false if not or 2 if post was killed before sync
	 */
	public function queue_post( $post_id, $bulk_trigger, $offset = 0, $show_bulk_errors = false ) {

		static $post_count = 0;
		static $killed_post_count = 0;

		$killed_post = false;

		$post_args = ep_prepare_post( $post_id );

		// Mimic EP_Sync_Manager::sync_post( $post_id ), otherwise posts can slip
		// through the kill filter... that would be bad!
		if ( apply_filters( 'ep_post_sync_kill', false, $post_args, $post_id ) ) {

			$killed_post_count ++;
			$killed_post = true; // Save status for return.

		} else { // Post wasn't killed so process it.

			// Put the post into the queue.
			$this->posts[ $post_id ][] = '{ "index": { "_id": "' . absint( $post_id ) . '" } }';

			if ( function_exists( 'wp_json_encode' ) ) {

				$this->posts[ $post_id ][] = addcslashes( wp_json_encode( $post_args ), "\n" );

			} else {

				$this->posts[ $post_id ][] = addcslashes( json_encode( $post_args ), "\n" );

			}

			// Augment the counter.
			++ $post_count;

		}

		// If we have hit the trigger, initiate the bulk request.
		if ( ( $post_count + $killed_post_count ) === absint( $bulk_trigger ) ) {

			// Don't waste time if we've killed all the posts.
			if ( ! empty( $this->posts ) ) {
				$this->bulk_index( $offset, $show_bulk_errors );
			}

			// Reset the post count.
			$post_count        = 0;
			$killed_post_count = 0;

			// Reset the posts.
			$this->posts = array();

		}

		if ( true === $killed_post ) {
			return 2;
		}

		return true;

	}

	/**
	 * Perform the bulk index operation
	 *
	 * Sends multiple posts to the ES server at once.
	 *
	 * @since 1.9
	 *
	 * @param int  $offset           The current offset to keep track of.
	 * @param bool $show_bulk_errors true to show individual post error messages for bulk errors.
	 *
	 * @return bool|WP_Error true on success or WP_Error on failure
	 */
	protected function bulk_index( $offset = 0, $show_bulk_errors = false ) {

		// Monitor how many times we attempt to add this particular bulk request.
		static $attempts = 0;

		// Augment the attempts.
		++ $attempts;

		$failed_transient = get_transient( 'ep_index_failed_posts' );

		$failed_posts  = is_array( $failed_transient ) ? $failed_transient : array();
		$failed_blocks = array();

		// Make sure we actually have something to index.
		if ( empty( $this->posts ) ) {

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::error( 'There are no posts to index.' );
			}

			return 0;
		}

		$flatten = array();

		foreach ( $this->posts as $post ) {

			$flatten[] = $post[0];
			$flatten[] = $post[1];

		}

		// Make sure to add a new line at the end or the request will fail.
		$body = rtrim( implode( "\n", $flatten ) ) . "\n";

		// Show the content length in bytes if in debug.
		if ( defined( 'WP_CLI' ) && WP_CLI && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			WP_CLI::log( 'Request string length: ' . size_format( mb_strlen( $body, '8bit' ), 2 ) );
		}

		// Decode the response.
		$response = ep_bulk_index_posts( $body );

		if ( is_wp_error( $response ) ) {

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::error( implode( "\n", $response->get_error_messages() ) );
			}

			$failed_blocks   = is_array( get_transient( 'ep_index_failed_blocks' ) ) ? get_transient( 'ep_index_failed_blocks' ) : array();
			$failed_blocks[] = $offset;

			return $response;

		}

		// If we did have errors, try to add the documents again.
		if ( isset( $response['errors'] ) && true === $response['errors'] ) {

			if ( $attempts < 5 ) {

				foreach ( $response['items'] as $item ) {

					if ( empty( $item['index']['error'] ) ) {
						unset( $this->posts[ $item['index']['_id'] ] );
					}
				}

				$this->bulk_index( $offset, $show_bulk_errors );

			} else {

				foreach ( $response['items'] as $item ) {

					if ( ! empty( $item['index']['_id'] ) ) {

						$failed_blocks[] = $offset;
						$failed_posts[]  = $item['index']['_id'];

					}
				}

				$attempts = 0;
			}
		} else {

			// There were no errors, all the posts were added.
			$attempts = 0;

		}

		if ( empty( $failed_posts ) ) {

			delete_transient( 'ep_index_failed_posts' );

		} else {

			set_transient( 'ep_index_failed_posts', $failed_posts, 600 );

		}

		if ( empty( $failed_blocks ) ) {

			delete_transient( 'ep_index_failed_blocks' );

		} else {

			set_transient( 'ep_index_failed_blocks', $failed_blocks, 600 );

		}

		return true;

	}

	/**
	 * Send any bulk indexing errors
	 *
	 * Emails bulk errors regarding any posts that failed to index.
	 *
	 * @since 1.9
	 *
	 * @return void
	 */
	public function send_bulk_errors() {

		$failed_posts = get_transient( 'ep_index_failed_posts' );

		if ( false !== $failed_posts && is_array( $failed_posts ) ) {

			$error_text = esc_html__( 'The following posts failed to index:' . PHP_EOL . PHP_EOL, 'elasticpress' );

			foreach ( $failed_posts as $failed ) {

				$failed_post = get_post( $failed );

				if ( $failed_post ) {
					$error_text .= "- {$failed}: " . $failed_post->post_title . PHP_EOL;
				}
			}

			/**
			 * Filter the email text used to send the bulk error email
			 *
			 * @since 1.9
			 *
			 * @param string $error_text The message body of the bulk error email.
			 */
			$error_text = apply_filters( 'ep_bulk_errors_email_text', $error_text );

			/**
			 * Filter the email subject used to send the bulk error email
			 *
			 * @since 1.9
			 *
			 * @param string $email_subject The subject of the bulk error email.
			 */
			$email_subject = apply_filters( 'ep_bulk_errors_email_subject', wp_specialchars_decode( get_option( 'blogname' ) ) . esc_html__( ': ElasticPress Index Errors', 'elasticpress' ) );

			/**
			 * Filter the email recipient who should receive the bulk indexing errors
			 *
			 * @since 1.9
			 *
			 * @param string $email_to The email address to which the bulk errors should be sent.
			 */
			$email_to = apply_filters( 'wp_bulk_errors_email_to', get_option( 'admin_email' ) );

			if ( defined( 'WP_CLI' ) && WP_CLI ) {

				WP_CLI::log( $error_text );

			} else {

				wp_mail( $email_to, $email_subject, $error_text );

			}

			// Clear failed posts after sending emails.
			delete_transient( 'ep_index_failed_posts' );

		}
	}

	/**
	 * Resets some values to reduce memory footprint.
	 */
	public function stop_the_insanity() {

		global $wpdb, $wp_object_cache, $wp_actions, $wp_filter;

		$wpdb->queries = array();

		if ( is_object( $wp_object_cache ) ) {

			$wp_object_cache->group_ops      = array();
			$wp_object_cache->stats          = array();
			$wp_object_cache->memcache_debug = array();

			// Make sure this is a public property, before trying to clear it.
			try {

				$cache_property = new ReflectionProperty( $wp_object_cache, 'cache' );

				if ( $cache_property->isPublic() ) {
					$wp_object_cache->cache = array();
				}

				unset( $cache_property );

			} catch ( ReflectionException $e ) {
			}

			/*
			 * In the case where we're not using an external object cache, we need to call flush on the default
			 * WordPress object cache class to clear the values from the cache property
			 */
			if ( ! wp_using_ext_object_cache() ) {
				wp_cache_flush();
			}

			if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
				call_user_func( array( $wp_object_cache, '__remoteset' ) ); // important.
			}
		}

		// Prevent wp_actions from growing out of control.
		$wp_actions = array();

		// WP_Query class adds filter get_term_metadata using its own instance
		// what prevents WP_Query class from being destructed by PHP gc.
		// It's high memory consuming as WP_Query instance holds all query results inside itself
		// and in theory $wp_filter will not stop growing until Out Of Memory exception occurs.
		if ( isset( $wp_filter['get_term_metadata'][10] ) ) {

			foreach ( $wp_filter['get_term_metadata'][10] as $hook => $content ) {

				if ( preg_match( '#^[0-9a-f]{32}lazyload_term_meta$#', $hook ) ) {
					unset( $wp_filter['get_term_metadata'][10][ $hook ] );
				}
			}
		}
	}
}
