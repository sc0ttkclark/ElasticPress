<?php
 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
WP_CLI::add_command( 'elasticpress', 'ElasticPress_CLI_Command' );

/**
 * CLI Commands for ElasticPress
 *
 */
class ElasticPress_CLI_Command extends WP_CLI_Command {

	/**
	 * Holds the indexing engine for use in various operations.
	 *
	 * @since 2.0
	 *
	 * @var EP_Index_Worker
	 */
	private $index_worker;

	/**
	 * ElasticPress_CLI_Command constructor.
	 *
	 * Ensures the index worker is available.
	 */
	public function __construct() {

		// Load the Indexer files.
		if ( ! class_exists( 'EP_Index_Worker' ) ) {
			require( dirname( dirname( __FILE__ ) ) . '/classes/class-ep-index-worker.php' );
		}

		$this->index_worker = new EP_Index_Worker();

	}

	/**
	 * Add the document mapping
	 *
	 * @synopsis [--network-wide]
	 * @subcommand put-mapping
	 * @since      0.9
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function put_mapping( $args, $assoc_args ) {
		$this->_connect_check();

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ){
				$assoc_args['network-wide'] = 0;
			}
			$sites = ep_get_sites( $assoc_args['network-wide'] );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				WP_CLI::line( sprintf( esc_html__( 'Adding mapping for site %d...', 'elasticpress' ), (int) $site['blog_id'] ) );

				// Deletes index first
				ep_delete_index();

				$result = ep_put_mapping();

				if ( $result ) {
					WP_CLI::success( esc_html__( 'Mapping sent', 'elasticpress' ) );
				} else {
					WP_CLI::error( esc_html__( 'Mapping failed', 'elasticpress' ) );
				}

				restore_current_blog();
			}
		} else {
			WP_CLI::line( esc_html__( 'Adding mapping...', 'elasticpress' ) );

			// Deletes index first
			$this->delete_index( $args, $assoc_args );

			$result = ep_put_mapping();

			if ( $result ) {
				WP_CLI::success( esc_html__( 'Mapping sent', 'elasticpress' ) );
			} else {
				WP_CLI::error( esc_html__( 'Mapping failed', 'elasticpress' ) );
			}
		}
	}

	/**
	 * Delete the current index. !!Warning!! This removes your elasticsearch index for the entire site.
	 *
	 * @todo       replace this function with one that updates all rows with a --force option
	 * @synopsis [--network-wide]
	 * @subcommand delete-index
	 * @since      0.9
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function delete_index( $args, $assoc_args ) {
		$this->_connect_check();

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ){
				$assoc_args['network-wide'] = 0;
			}
			$sites = ep_get_sites( $assoc_args['network-wide'] );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				WP_CLI::line( sprintf( esc_html__( 'Deleting index for site %d...', 'elasticpress' ), (int) $site['blog_id'] ) );

				$result = ep_delete_index();

				if ( $result ) {
					WP_CLI::success( esc_html__( 'Index deleted', 'elasticpress' ) );
				} else {
					WP_CLI::error( esc_html__( 'Delete index failed', 'elasticpress' ) );
				}

				restore_current_blog();
			}
		} else {
			WP_CLI::line( esc_html__( 'Deleting index...', 'elasticpress' ) );

			$result = ep_delete_index();

			if ( $result ) {
				WP_CLI::success( esc_html__( 'Index deleted', 'elasticpress' ) );
			} else {
				WP_CLI::error( esc_html__( 'Index delete failed', 'elasticpress' ) );
			}
		}
	}

	/**
	 * Map network alias to every index in the network
	 *
	 * @param array $args
	 *
	 * @subcommand recreate-network-alias
	 * @since      0.9
	 *
	 * @param array $assoc_args
	 */
	public function recreate_network_alias( $args, $assoc_args ) {
		$this->_connect_check();

		WP_CLI::line( esc_html__( 'Recreating network alias...', 'elasticpress' ) );

		ep_delete_network_alias();

		$create_result = $this->index_worker->create_network_alias();

		if ( $create_result ) {
			WP_CLI::success( esc_html__( 'Done!', 'elasticpress' ) );
		} else {
			WP_CLI::error( esc_html__( 'An error occurred', 'elasticpress' ) );
		}
	}

	/**
	 * Index all posts for a site or network wide
	 *
	 * @synopsis [--setup] [--network-wide] [--posts-per-page] [--nobulk] [--offset] [--show-bulk-errors] [--post-type] [--keep-active]
	 *
	 * @param array $args
	 *
	 * @since 0.1.2
	 *
	 * @param array $assoc_args
	 */
	public function index( $args, $assoc_args ) {
		$this->_connect_check();

		if ( ! empty( $assoc_args['posts-per-page'] ) ) {
			$assoc_args['posts-per-page'] = absint( $assoc_args['posts-per-page'] );
		} else {
			$assoc_args['posts-per-page'] = 350;
		}

		if ( ! empty( $assoc_args['offset'] ) ) {
			$assoc_args['offset'] = absint( $assoc_args['offset'] );
		} else {
			$assoc_args['offset'] = 0;
		}

		if ( empty( $assoc_args['post-type'] ) ) {
			$assoc_args['post-type'] = null;
		}

		$total_indexed = 0;

		/**
		 * Prior to the index command invoking
		 * Useful for deregistering filters/actions that occur during a query request
		 *
		 * @since 1.4.1
		 */
		do_action( 'ep_wp_cli_pre_index', $args, $assoc_args );

		// Deactivate ElasticPress if setup is set to true.
		if (
			! isset( $assoc_args['keep-active'] ) ||
			false === $assoc_args['keep-active'] ||
			( isset( $assoc_args['setup'] ) && true === $assoc_args['setup'] )
		) {
			// Deactivate our search integration
			$this->deactivate();
		}

		timer_start();

		// Run setup if flag was passed
		if ( isset( $assoc_args['setup'] ) && true === $assoc_args['setup'] ) {

			// Right now setup is just the put_mapping command, as this also deletes the index(s) first
			$this->put_mapping( $args, $assoc_args );
		}

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ){
				$assoc_args['network-wide'] = 0;
			}

			WP_CLI::log( esc_html__( 'Indexing posts network-wide...', 'elasticpress' ) );

			$sites = ep_get_sites( $assoc_args['network-wide'] );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				$result = $this->index_worker->_index_helper( $assoc_args );

				$total_indexed += $result['synced'];

				WP_CLI::log( sprintf( esc_html__( 'Number of posts indexed on site %d: %d', 'elasticpress' ), $site['blog_id'], $result['synced'] ) );

				if ( ! empty( $result['errors'] ) ) {
					WP_CLI::error( sprintf( esc_html__( 'Number of post index errors on site %d: %d', 'elasticpress' ), $site['blog_id'], count( $result['errors'] ) ) );
				}

				restore_current_blog();
			}

			WP_CLI::log( esc_html__( 'Recreating network alias...', 'elasticpress' ) );

			$this->index_worker->create_network_alias();

			WP_CLI::log( sprintf( esc_html__( 'Total number of posts indexed: %d', 'elasticpress' ), $total_indexed ) );

		} else {

			WP_CLI::log( esc_html__( 'Indexing posts...', 'elasticpress' ) );

			$result = $this->index_worker->_index_helper( $assoc_args );

			WP_CLI::log( sprintf( esc_html__( 'Number of posts indexed on site %d: %d', 'elasticpress' ), get_current_blog_id(), $result['synced'] ) );

			if ( ! empty( $result['errors'] ) ) {
				WP_CLI::error( sprintf( esc_html__( 'Number of post index errors on site %d: %d', 'elasticpress' ), get_current_blog_id(), count( $result['errors'] ) ) );
			}
		}

		WP_CLI::log( WP_CLI::colorize( '%Y' . esc_html__( 'Total time elapsed: ', 'elasticpress' ) . '%N' . timer_stop() ) );

		// Reactivate our search integration
		$this->activate();

		WP_CLI::success( esc_html__( 'Done!', 'elasticpress' ) );
	}

	/**
	 * Ping the Elasticsearch server and retrieve a status.
	 *
	 * @since 0.9.1
	 */
	public function status() {
		$this->_connect_check();

		$request_args = array( 'headers' => ep_format_request_headers() );

		$request = wp_remote_get( trailingslashit( ep_get_host( true ) ) . '_recovery/?pretty', $request_args );

		if ( is_wp_error( $request ) ) {
			WP_CLI::error( implode( "\n", $request->get_error_messages() ) );
		}

		$body = wp_remote_retrieve_body( $request );
		WP_CLI::line( '' );
		WP_CLI::line( '====== Status ======' );
		WP_CLI::line( print_r( $body, true ) );
		WP_CLI::line( '====== End Status ======' );
	}

	/**
	 * Get stats on the current index.
	 *
	 * @since 0.9.2
	 */
	public function stats() {
		$this->_connect_check();

		$request_args = array( 'headers' => ep_format_request_headers() );

		$request = wp_remote_get( trailingslashit( ep_get_host( true ) ) . '_stats/', $request_args );
		if ( is_wp_error( $request ) ) {
			WP_CLI::error( implode( "\n", $request->get_error_messages() ) );
		}
		$body  = json_decode( wp_remote_retrieve_body( $request ), true );
		$sites = ( is_multisite() ) ? ep_get_sites() : array( 'blog_id' => get_current_blog_id() );

		foreach ( $sites as $site ) {
			$current_index = ep_get_index_name( $site['blog_id'] );

			if (isset( $body['indices'][$current_index] ) ) {
				WP_CLI::log( '====== Stats for: ' . $current_index . " ======" );
				WP_CLI::log( 'Documents:  ' . $body['indices'][$current_index]['total']['docs']['count'] );
				WP_CLI::log( 'Index Size: ' . size_format($body['indices'][$current_index]['total']['store']['size_in_bytes'], 2 ) );
				WP_CLI::log( '====== End Stats ======' );
			} else {
				WP_CLI::warning( $current_index . ' is not currently indexed.' );
			}
		}
	}

	/**
	 * Activate ElasticPress
	 *
	 * @since 0.9.3
	 */
	public function activate() {
		$this->_connect_check();

		$status = ep_is_activated();

		if ( $status ) {
			WP_CLI::warning( esc_html__( 'ElasticPress is already activated.', 'elasticpress' ) );
		} else {
			WP_CLI::log( esc_html__( 'ElasticPress is currently deactivated, activating...', 'elasticpress' ) );

			$result = ep_activate();

			if ( $result ) {
				WP_CLI::success( esc_html__( 'ElasticPress was activated!', 'elasticpress' ) );
			} else {
				WP_CLI::warning( esc_html__( 'ElasticPress was unable to be activated.', 'elasticpress' ) );
			}
		}
	}

	/**
	 * Deactivate ElasticPress
	 *
	 * @since 0.9.3
	 */
	public function deactivate() {
		$this->_connect_check();

		$status = ep_is_activated();

		if ( ! $status ) {
			WP_CLI::warning( esc_html__( 'ElasticPress is already deactivated.', 'elasticpress' ) );
		} else {
			WP_CLI::log( esc_html__( 'ElasticPress is currently activated, deactivating...', 'elasticpress' ) );

			$result = ep_deactivate();

			if ( $result ) {
				WP_CLI::success( esc_html__( 'ElasticPress was deactivated!', 'elasticpress' ) );
			} else {
				WP_CLI::warning( esc_html__( 'ElasticPress was unable to be deactivated.', 'elasticpress' ) );
			}
		}
	}

	/**
	 * Return current status of ElasticPress
	 *
	 * @subcommand is-active
	 *
	 * @since 0.9.3
	 */
	public function is_activated() {
		$this->_connect_check();

		$active = ep_is_activated();

		if ( $active ) {
			WP_CLI::log( esc_html__( 'ElasticPress is currently activated.', 'elasticpress' ) );
		} else {
			WP_CLI::log( esc_html__( 'ElasticPress is currently deactivated.', 'elasticpress' ) );
		}
	}

	/**
	 * Provide better error messaging for common connection errors
	 *
	 * @since 0.9.3
	 */
	private function _connect_check() {
		if ( ! defined( 'EP_HOST' ) ) {
			WP_CLI::error( esc_html__( 'EP_HOST is not defined! Check wp-config.php', 'elasticpress' ) );
		}

		if ( false === ep_elasticsearch_alive() ) {
			WP_CLI::error( esc_html__( 'Unable to reach Elasticsearch Server! Check that service is running.', 'elasticpress' ) );
		}
	}
}
