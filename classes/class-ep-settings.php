<?php
/**
 * Create an ElasticPress settings page.
 *
 * @package elasticpress
 * @since   1.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ElasticPress Settings Page
 *
 * Sets up the settings page to handle ElasticPress configuration.
 */
class EP_Settings {

	/**
	 * WordPress options page
	 *
	 * @since 1.9
	 * @var object
	 */
	var $options_page;

	/**
	 * Register WordPress hooks
	 *
	 * Loads initial actions.
	 *
	 * @since 1.9
	 * @return EP_Settings
	 */
	public function __construct() {

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) { // Must be network admin in multisite.
			add_action( 'network_admin_menu', array( $this, 'action_admin_menu' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		}

		add_action( 'wp_ajax_toggle_module', array( $this, 'ajax_toggle_module' ) );

		// Add assets
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'wp_ajax_ep_launch_index', array( $this, 'action_wp_ajax_ep_launch_index' ) );
		add_action( 'wp_ajax_ep_pause_index', array( $this, 'action_wp_ajax_ep_pause_index' ) );
		add_action( 'wp_ajax_ep_restart_index', array( $this, 'action_wp_ajax_ep_restart_index' ) );
	}

	/**
	 * Executes the index
	 *
	 * When the indexer is called VIA Ajax this function starts the index or resumes from the previous position.
	 *
	 * @param bool $keep_active Whether Elasticsearch integration should not be deactivated, index not deleted and mappings not set.
	 * @since 2.1
	 * @return array|WP_Error
	 */
	protected function _run_index( $keep_active = false ) {
		$post_count    = array( 'total' => 0 );
		$post_types    = ep_get_indexable_post_types();
		$post_statuses = ep_get_indexable_post_status();

		foreach ( $post_types as $type ) {
			$type_count          = wp_count_posts( $type );
			$post_count[ $type ] = 0;
			foreach ( $post_statuses as $status ) {
				$count = absint( $type_count->$status );
				$post_count['total'] += $count;
				$post_count[ $type ] += $count;
			}
		}

		set_transient( 'ep_post_count', $post_count, 600 );

		if ( ! $keep_active && ( false === get_transient( 'ep_index_offset' ) ) ) {
			// Deactivate our search integration.
			ep_deactivate();
			$mapping_success = ep_process_site_mappings();
			do_action( 'ep_put_mapping' );
			if ( true !== $mapping_success ) {
				if ( false === $mapping_success ) {
					wp_send_json_error( esc_html__( 'Mappings could not be completed. If the error persists contact your system administrator', 'elasticpress' ) );
				}
				wp_send_json_success( $mapping_success );
				exit();
			}
		}

		$indexer       = new EP_Index_Worker();
		$index_success = $indexer->index();

		if ( ! $index_success ) {
			return new WP_Error( esc_html__( 'Indexing could not be completed. If the error persists contact your system administrator', 'elasticpress' ) );
		}

		$total = get_transient( 'ep_post_count' );
		if ( false === get_transient( 'ep_index_offset' ) ) {
			$data = array(
				'ep_sync_complete'  => true,
				'ep_posts_synced'   => ( false === get_transient( 'ep_index_synced' ) ? 0 : absint( get_transient( 'ep_index_synced' ) ) ),
				'ep_posts_total'    => absint( $total['total'] ),
				'ep_current_synced' => $index_success['current_synced'],
			);
		} else {
			$data = array(
				'ep_sync_complete'  => false,
				'ep_posts_synced'   => ( false === get_transient( 'ep_index_synced' ) ? 0 : absint( get_transient( 'ep_index_synced' ) ) ),
				'ep_posts_total'    => absint( $total['total'] ),
				'ep_current_synced' => $index_success['current_synced'],
			);
		}
		
		return $data;
	}

	/**
	 * Process manual indexing
	 *
	 * Processes the action when the manual indexing button is clicked.
	 *
	 * @since 2.1
	 */
	public function action_wp_ajax_ep_launch_index() {
		// Verify nonce and make sure this is run by an admin.
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'ep_manual_index' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Security error!', 'elasticpress' ) );
		}

		$network     = false;
		$site        = false;
		$sites       = false;
		$indexes     = false;
		$keep_active = isset( $_POST['keep_active'] ) ? 'true' === $_POST['keep_active'] : false;

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$network = true;
		}

		if ( true === $network ) {
			delete_site_option( 'ep_index_paused' );
			update_site_option( 'ep_index_keep_active', $keep_active );
			$last_run = get_site_transient( 'ep_sites_to_index' );

			if ( false === $last_run ) {
				$sites   = ep_get_sites();
				$success = array();
				$indexes = array();
			} else {
				$sites   = ( isset( $last_run['sites'] ) ) ? $last_run['sites'] : ep_get_sites();
				$success = ( isset( $last_run['success'] ) ) ? $last_run['success'] : array();
				$indexes = ( isset( $last_run['indexes'] ) ) ? $last_run['indexes'] : array();
			}

			$site_info = array_pop( $sites );
			$site      = absint( $site_info['blog_id'] );
		} else {
			delete_option( 'ep_index_paused' );
			update_option( 'ep_index_keep_active', $keep_active, false );
		}

		if ( false !== $site ) {
			switch_to_blog( $site );
		}

		$result = $this->_run_index( $keep_active );

		if ( false !== $site ) {
			$indexes[] = ep_get_index_name();
			if ( is_array( $result ) && isset( $result['ep_sync_complete'] ) && true === $result['ep_sync_complete'] ) {
				delete_transient( 'ep_index_synced' );
				delete_transient( 'ep_post_count' );
				delete_site_option( 'ep_index_keep_active' );
			}
			restore_current_blog();
		} else {
			if ( is_array( $result ) && isset( $result['ep_sync_complete'] ) && true === $result['ep_sync_complete'] ) {
				delete_transient( 'ep_index_synced' );
				delete_transient( 'ep_post_count' );
				delete_option( 'ep_index_keep_active' );
			}
		}

		if ( is_array( $result ) && isset( $result['ep_sync_complete'] ) ) {
			if ( true === $result['ep_sync_complete'] ) {
				if ( $network ) {
					$success[] = $site;
					$last_run = array(
						'sites'   => $sites,
						'success' => $success,
						'indexes' => $indexes,
					);

					set_site_transient( 'ep_sites_to_index', $last_run, 600 );

					if ( ! empty( $sites ) ) {
						$result['ep_sync_complete'] = 0;
					} else {
						$result['ep_sync_complete'] = 1;
						delete_site_transient( 'ep_sites_to_index' );
						ep_create_network_alias( $indexes );
					}

				} else {
					$result['ep_sync_complete'] = ( true === $result['ep_sync_complete'] ) ? 1 : 0;
				}

				ep_activate();
			}
		}
		if ( ! empty( $sites ) ) {
			$result['ep_sites_remaining'] = sizeof( $sites );
		} else {
			$result['ep_sites_remaining'] = 0;
		}

		$result['is_network'] = ( true === $network ) ? 1 : 0;

		wp_send_json_success( $result );
	}

	/**
	 * Process the indexing pause request
	 *
	 * Processes the action when the pause indexing button is clicked,
	 * re-enabling the ElasticPress integration while indexing is paused.
	 *
	 * @since 2.1
	 */
	public function action_wp_ajax_ep_pause_index() {
		// Verify nonce and make sure this is run by an admin.
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'ep_pause_index' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Security error!', 'elasticpress' ) );
		}

		$keep_active = isset( $_POST['keep_active'] ) ? 'true' === $_POST['keep_active'] : false;

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			update_site_option( 'ep_index_paused', true );
			update_site_option( 'ep_index_keep_active', $keep_active );
		} else {
			update_option( 'ep_index_paused', true, false );
			update_site_option( 'ep_index_keep_active', $keep_active );
		}

		// If ElasticPress is activated correctly, send a positive response
		if ( ep_activate() ) {
			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * Process the indexing restart request
	 *
	 * Processes the action when the restart indexing button is clicked,
	 * updating the option saying indexing is not paused anymore.
	 *
	 * @since 2.1
	 */
	public function action_wp_ajax_ep_restart_index() {
		// Verify nonce and make sure this is run by an admin.
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'ep_restart_index' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Security error!', 'elasticpress' ) );
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			delete_site_option( 'ep_index_paused' );
			delete_site_transient( 'ep_sites_to_index' );
			delete_site_option( 'ep_index_keep_active' );
		} else {
			delete_option( 'ep_index_paused' );
			delete_option( 'ep_index_keep_active' );
		}

		delete_transient( 'ep_index_offset' );
		delete_transient( 'ep_index_synced' );
		delete_transient( 'ep_post_count' );

		wp_send_json_success();
	}

	public function ajax_toggle_module() {
		if ( empty( $_POST['module'] ) || ! check_ajax_referer( 'ep_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		$module = ep_get_registered_module( $_POST['module'] );

		$active_modules = get_option( 'ep_active_modules', array() );

		if ( $module->is_active() ) {
			$key = array_search( $_POST['module'], $active_modules );

			if ( false !== $key ) {
				unset( $active_modules[$key] );
			}
		} else {
			$active_modules[] = $module->slug;

			if ( $module->requires_install_reindex ) {
				// Do reindex
			}

			$module->post_activation();
		}

		update_option( 'ep_active_modules', $active_modules );

		wp_send_json_success();
	}

	/**
	 * Register and Enqueue JavaScripts
	 *
	 * Registers and enqueues the necessary JavaScripts for the interface.
	 *
	 * @since 1.9
	 * @return void
	 */
	public function action_admin_enqueue_scripts() {
		// Only add the following to the settings page.
		if ( isset( get_current_screen()->id ) && strpos( get_current_screen()->id, 'elasticpress' ) !== false ) {
			$maybe_min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

			wp_enqueue_style( 'ep_admin_styles', EP_URL . 'assets/css/admin' . $maybe_min . '.css', array(), EP_VERSION );
			wp_enqueue_script( 'ep_admin_scripts', EP_URL . 'assets/js/admin' . $maybe_min . '.js', array( 'jquery' ), EP_VERSION, true );
			wp_localize_script( 'ep_admin_scripts', 'ep', array( 'nonce' => wp_create_nonce( 'ep_nonce' ) ) );
		}
	}

	/**
	 * Admin-init actions
	 *
	 * Sets up Settings API.
	 *
	 * @since 1.9
	 * @return void
	 */
	public function action_admin_init() {

		//Save options for multisite
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {

			
		}
	}


	/**
	 * Build settings page
	 *
	 * Loads up the settings page.
	 *
	 * @since 1.9
	 *
	 * @return void
	 */
	public function settings_page() {
		include( dirname( __FILE__ ) . '/../includes/settings-page.php' );
	}

	/**
	 * Admin menu actions
	 *
	 * Adds options page to admin menu.
	 *
	 * @since 1.9
	 * @return void
	 */
	public function action_admin_menu() {
		$capability  = 'manage_options';

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$capability  = 'manage_network';
		}

		$this->options_page = add_menu_page(
			'ElasticPress',
			'ElasticPress',
			$capability,
			'elasticpress',
			array( $this, 'settings_page' )
		);
	}
}
