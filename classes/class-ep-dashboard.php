<?php
/**
 * Create an ElasticPress dashboard page.
 *
 * @package elasticpress
 * @since   1.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ElasticPress Dashboard Page
 *
 * Sets up the dashboard page to handle ElasticPress configuration.
 */
class EP_Dashboard {

	/**
	 * Placeholder
	 *
	 * @since 1.9
	 */
	public function __construct() { }

	/**
	 * Setup actions and filters for all things settings
	 *
	 * @since  2.1
	 */
	public function setup() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) { // Must be network admin in multisite.
			add_action( 'network_admin_menu', array( $this, 'action_admin_menu' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		}

		add_action( 'wp_ajax_ep_toggle_module', array( $this, 'action_wp_ajax_ep_toggle_module' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'wp_ajax_ep_index', array( $this, 'action_wp_ajax_ep_index' ) );
		add_action( 'wp_ajax_ep_cancel_index', array( $this, 'action_wp_ajax_ep_cancel_index' ) );
		add_action('admin_notices', array( $this, 'action_mid_index_notice' ) );
	}

	/**
	 * Print out mid sync warning notice
	 *
	 * @since  2.1
	 */
	public function action_mid_index_notice() {
		if ( isset( get_current_screen()->id ) && strpos( get_current_screen()->id, 'elasticpress' ) !== false ) {
			return;
		}

		$index_meta = get_option( 'ep_index_meta', false );

		if ( empty( $index_meta ) ) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p><?php printf( __( 'ElasticPress is in the middle of a sync. The plugin wont work until this finishes. Want to <a href="%s">go back and finish it</a>?', 'elasticpress' ), esc_url( admin_url( 'admin.php?page=elasticpress&resume_sync' ) ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Continue index
	 *
	 * @since  2.1
	 */
	public function action_wp_ajax_ep_index() {
		if ( ! check_ajax_referer( 'ep_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		$index_meta = get_option( 'ep_index_meta', false );

		// No current index going on. Let's start over
		if ( false === $index_meta ) {
			ep_deactivate();

			$status = 'start';
			$index_meta = array(
				'offset' => 0,
				'start' => true
			);

			if ( ! empty( $_POST['module_sync'] ) ) {
				$index_meta['module_sync'] = esc_attr( $_POST['module_sync'] );
			}
		} else {
			$index_meta['start'] = false;
		}

		$posts_per_page = apply_filters( 'ep_index_posts_per_page', 1 );

		$args = apply_filters( 'ep_index_posts_args', array(
			'posts_per_page'         => $posts_per_page,
			'post_type'              => ep_get_indexable_post_types(),
			'post_status'            => ep_get_indexable_post_status(),
			'offset'                 => $index_meta['offset'],
			'ignore_sticky_posts'    => true,
			'orderby'                => 'ID',
			'order'                  => 'DESC',
			'cache_results '         => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );

		$query = new WP_Query( $args );

		$index_meta['found_posts'] = (int) $query->found_posts;

		if ( $status !== 'start' ) {
			if ( $query->have_posts() ) {
				$queued_posts = array();

				while ( $query->have_posts() ) {
					$query->the_post();
					$killed_post_count = 0;

					$post_args = ep_prepare_post( get_the_ID() );

					if ( apply_filters( 'ep_post_sync_kill', false, $post_args, get_the_ID() ) ) {

						$killed_post_count++;

					} else { // Post wasn't killed so process it.

						$queued_posts[ get_the_ID() ][] = '{ "index": { "_id": "' . absint( get_the_ID() ) . '" } }';

						if ( function_exists( 'wp_json_encode' ) ) {
							$queued_posts[ get_the_ID() ][] = addcslashes( wp_json_encode( $post_args ), "\n" );
						} else {
							$queued_posts[ get_the_ID() ][] = addcslashes( json_encode( $post_args ), "\n" );
						}
					}
				}

				if ( ! empty( $this->posts ) ) {
					$flatten = array();

					foreach ( $this->posts as $post ) {
						$flatten[] = $post[0];
						$flatten[] = $post[1];
					}

					// make sure to add a new line at the end or the request will fail
					$body = rtrim( implode( "\n", $flatten ) ) . "\n";

					ep_bulk_index_posts( $body );
				}

				$index_meta['offset'] = $index_meta['offset'] + $posts_per_page;

				update_option( 'ep_index_meta', $index_meta );
			} else {
				// We are done
				$index_meta['offset'] = $query->found_posts;
				delete_option( 'ep_index_meta' );

				ep_activate();
			}
		} else {

			update_option( 'ep_index_meta', $index_meta );
		}

		wp_send_json_success( $index_meta );
	}

	/**
	 * Cancel index
	 *
	 * @since  2.1
	 */
	public function action_wp_ajax_ep_cancel_index() {
		if ( ! check_ajax_referer( 'ep_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		delete_option( 'ep_index_meta' );

		ep_deactivate();

		wp_send_json_success();
	}

	/**
	 * Toggle module active or inactive
	 *
	 * @since  2.1
	 */
	public function action_wp_ajax_ep_toggle_module() {
		if ( empty( $_POST['module'] ) || ! check_ajax_referer( 'ep_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		$module = ep_get_registered_module( $_POST['module'] );

		$active_modules = get_option( 'ep_active_modules', array() );

		$data = array();

		if ( $module->is_active() ) {
			$key = array_search( $_POST['module'], $active_modules );

			if ( false !== $key ) {
				unset( $active_modules[$key] );
			}

			$data['active'] = false;
		} else {
			$active_modules[] = $module->slug;

			if ( $module->requires_install_reindex ) {
				$data['reindex'] = true;
			}

			$module->post_activation();

			$data['active'] = true;
		}

		update_option( 'ep_active_modules', $active_modules );

		wp_send_json_success( $data );
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

			$data = array( 'nonce' => wp_create_nonce( 'ep_nonce' ) );

			$index_meta = get_option( 'ep_index_meta' );

			if ( ! empty( $index_meta ) ) {
				$data['index_meta'] = $index_meta;

				if ( isset( $_GET['resume_sync'] ) ) {
					$data['auto_start_index'] = true;
				}
			}

			wp_localize_script( 'ep_admin_scripts', 'ep', $data );
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

		register_setting( 'elasticpress', 'ep_host', 'esc_url_raw' );
	}

	/**
	 * Build dashboard page
	 *
	 * @since 2.1
	 */
	public function dashboard_page() {
		include( dirname( __FILE__ ) . '/../includes/dashboard-page.php' );
	}

	/**
	 * Build settings page
	 *
	 * @since  2.1
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

		add_menu_page(
			'ElasticPress',
			'ElasticPress',
			$capability,
			'elasticpress',
			array( $this, 'dashboard_page' ),
			'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IiB2aWV3Qm94PSIwIDAgNzMgNzEuMyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNzMgNzEuMzsiIHhtbDpzcGFjZT0icHJlc2VydmUiPjxwYXRoIGQ9Ik0zNi41LDQuN0MxOS40LDQuNyw1LjYsMTguNiw1LjYsMzUuN2MwLDEwLDQuNywxOC45LDEyLjEsMjQuNWw0LjUtNC41YzAuMS0wLjEsMC4xLTAuMiwwLjItMC4zbDAuNy0wLjdsNi40LTYuNGMyLjEsMS4yLDQuNSwxLjksNy4xLDEuOWM4LDAsMTQuNS02LjUsMTQuNS0xNC41cy02LjUtMTQuNS0xNC41LTE0LjVTMjIsMjcuNiwyMiwzNS42YzAsMi44LDAuOCw1LjMsMi4xLDcuNWwtNi40LDYuNGMtMi45LTMuOS00LjYtOC43LTQuNi0xMy45YzAtMTIuOSwxMC41LTIzLjQsMjMuNC0yMy40czIzLjQsMTAuNSwyMy40LDIzLjRTNDkuNCw1OSwzNi41LDU5Yy0yLjEsMC00LjEtMC4zLTYtMC44bC0wLjYsMC42bC01LjIsNS40YzMuNiwxLjUsNy42LDIuMywxMS44LDIuM2MxNy4xLDAsMzAuOS0xMy45LDMwLjktMzAuOVM1My42LDQuNywzNi41LDQuN3oiLz48L3N2Zz4='
		);

		add_submenu_page(
			null,
			'ElasticPress' . esc_html__( 'Settings', 'elasticpress' ),
			'ElasticPress' . esc_html__( 'Settings', 'elasticpress' ),
			$capability,
			'elasticpress-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Return a singleton instance of the current class
	 *
	 * @since 2.1
	 * @return object
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}

EP_Dashboard::factory();

