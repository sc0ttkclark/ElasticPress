<?php
/**
 * Create an ElasticPress settings page.
 *
 * @package elasticpress
 * @since   1.9
 * @author  Allan Collins <allan.collins@10up.com>
 */

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

		// Add assets
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
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
		if ( isset( get_current_screen()->id ) && strpos( get_current_screen()->id, 'settings_page_elasticpress' ) !== false ) {
			$maybe_min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

			wp_enqueue_style( 'ep_styles', EP_URL . 'assets/css/elasticpress' . $maybe_min . '.css', array(), EP_VERSION );
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
