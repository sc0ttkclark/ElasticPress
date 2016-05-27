<?php
/**
 * Create an ElasticPress settings page.
 *
 * @package elasticpress
 *
 * @since   1.9
 *
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
	 *
	 * @var object
	 */
	var $options_page;

	/**
	 * Check host results
	 *
	 * @since 1.9
	 *
	 * @var object
	 */
	static $host;

	/**
	 * Register WordPress hooks
	 *
	 * Loads initial actions.
	 *
	 * @since 1.9
	 *
	 * @return EP_Settings
	 */
	public function __construct() {
		self::$host = ep_check_host();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) { // Must be network admin in multisite.
			add_action( 'network_admin_menu', array( $this, 'action_admin_menu' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}

		// Add JavaScripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );

		add_action( 'admin_init', array( $this, 'action_admin_init' ) );

		if ( current_user_can( 'manage_options' ) ) {

			add_action( 'wp_ajax_ep_get_site_post_types', array( $this, 'action_wp_ajax_ep_get_site_post_types' ) );
			add_action( 'wp_ajax_ep_process_site_post_types', array( $this, 'action_wp_ajax_ep_process_site_post_types' ) );

		}
	}

	/**
	 * Register and Enqueue JavaScripts
	 *
	 * Registers and enqueues the necessary JavaScripts for the interface.
	 *
	 * @since 1.9
	 *
	 * @return void
	 */
	public function action_admin_enqueue_scripts() {

		// Enqueue more easily debugged version if applicable.
		if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {

			wp_register_script( 'ep_admin', EP_URL . 'assets/js/elasticpress-admin.js', array( 'jquery', 'jquery-ui-progressbar' ), EP_VERSION, true );
			wp_register_style( 'ep_styles', EP_URL . 'assets/css/elasticpress.css', array(), EP_VERSION );

		} else {

			wp_register_script( 'ep_admin', EP_URL . 'assets/js/elasticpress-admin.min.js', array( 'jquery', 'jquery-ui-progressbar' ), EP_VERSION, true );
			wp_register_style( 'ep_styles', EP_URL . 'assets/css/elasticpress.min.css', array(), EP_VERSION );

		}

		// Only add the following to the settings page.
		if ( isset( get_current_screen()->id ) && strpos( get_current_screen()->id, 'settings_page_elasticpress' ) !== false ) {

			wp_enqueue_style( 'ep_progress_style' );
			wp_enqueue_style( 'ep_styles' );

			wp_enqueue_script( 'ep_admin' );

			$running      = 0;
			$total_posts  = 0;
			$synced_posts = 0;

			if ( false !== get_transient( 'ep_index_offset' ) ) {

				$running      = 1;
				$synced_posts = get_transient( 'ep_index_synced' );
				$total_posts  = get_transient( 'ep_post_count' );

			}

			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$paused = get_site_option( 'ep_index_paused' );
			} else {
				$paused = get_option( 'ep_index_paused' );
			}

			$indexed = esc_html__( 'items indexed', 'elasticpress' );

			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$indexed = esc_html__( 'items indexed in ', 'elasticpress' );
			}

			$allowed_link = array(
				'a' => array(
					'href' => array(),
				),
			);

			wp_localize_script(
				'ep_admin',
				'ep',
				array(
					'nonce'                  => wp_create_nonce( 'ep_manual_index' ),
					'pause_nonce'            => wp_create_nonce( 'ep_pause_index' ),
					'restart_nonce'          => wp_create_nonce( 'ep_restart_index' ),
					'stats_nonce'            => wp_create_nonce( 'ep_site_stats' ),
					'running_index_text'     => esc_html__( 'Running Index...', 'elasticpress' ),
					'index_complete_text'    => esc_html__( 'Run Index', 'elasticpress' ),
					'index_paused_text'      => esc_html__( 'Indexing is Paused', 'elasticpress' ),
					'index_resume_text'      => esc_html__( 'Resume Indexing', 'elasticpress' ),
					'index_pause_text'       => esc_html__( 'Pause Indexing', 'elasticpress' ),
					'items_indexed'          => $indexed,
					'items_indexed_suff'     => esc_html__( 'items indexed', 'elasticpress' ),
					'paused'                 => absint( $paused ),
					'sites'                  => esc_html__( ' site(s)', 'elasticpress' ),
					'index_running'          => $running,
					'total_posts'            => isset( $total_posts['total'] ) ? $total_posts['total'] : 0,
					'synced_posts'           => $synced_posts,
					'failed_text'            => esc_html__( 'A failure has occured. Please try the indexing operation again and if the error persists contact your website administrator.', 'elasticpress' ),
					'complete_text'          => esc_html__( 'Index complete', 'elasticpress' ),
					'post_type_nonce'        => wp_create_nonce( 'ep_post_type_nonce' ),
					'post_type_change_nonce' => wp_create_nonce( 'ep_post_type_change_nonce' ),
				)
			);

		}
	}

	/**
	 * Admin-init actions
	 *
	 * Sets up Settings API.
	 *
	 * @since 1.9
	 *
	 * @return void
	 */
	public function action_admin_init() {

		//Save options for multisite
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && ( isset( $_POST['ep_host'] ) || isset( $_POST['ep_activate'] ) ) ) {

			if ( ! check_admin_referer( 'elasticpress-options' ) ) {
				die( esc_html__( 'Security error!', 'elasticpress' ) );
			}

			if ( isset( $_POST['ep_host'] ) ) {

				$host = $this->sanitize_ep_host( $_POST['ep_host'] );
				update_site_option( 'ep_host', $host );

			}

			if ( isset( $_POST['ep_activate'] ) ) {

				$this->sanitize_ep_activate( $_POST['ep_activate'] );

			} else {

				$this->sanitize_ep_activate( false );

			}
			self::$host = ep_check_host();
		}

		//Notices
		add_filter( 'ep_admin_settings_error_notices', array( $this, 'error_notices' ) );

		add_settings_section( 'ep_settings_section_main', '', '__return_empty_string', 'elasticpress' );
		add_settings_section( 'ep_settings_section_post_types', '', '__return_empty_string', 'elasticpress_post_types' );

		add_settings_field( 'ep_host', esc_html__( 'Elasticsearch Host:', 'elasticpress' ), array(
			$this,
			'setting_callback_host',
		), 'elasticpress', 'ep_settings_section_main' );

		add_settings_field( 'ep_activate', esc_html__( 'Use Elasticsearch:', 'elasticpress' ), array(
			$this,
			'setting_callback_activate',
		), 'elasticpress', 'ep_settings_section_main' );

		add_settings_field( 'ep_post_types', esc_html__( 'Select Post Types To Search:', 'elasticpress' ), array(
			$this,
			'setting_callback_post_types',
		), 'elasticpress_post_types', 'ep_settings_section_post_types' );

		register_setting( 'elasticpress', 'ep_host', array( $this, 'sanitize_ep_host' ) );
		register_setting( 'elasticpress', 'ep_activate', array( $this, 'sanitize_ep_activate' ) );
		register_setting( 'elasticpress_post_types', 'ep_post_types', array( $this, 'sanitize_ep_post_types' ) );

	}

	/**
	 * Admin menu actions
	 *
	 * Adds options page to admin menu.
	 *
	 * @since 1.9
	 *
	 * @return void
	 */
	public function action_admin_menu() {

		$parent_slug = 'options-general.php';
		$capability  = 'manage_options';

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {

			$parent_slug = 'settings.php';
			$capability  = 'manage_network';

		}

		$this->options_page = add_submenu_page(
			$parent_slug,
			'ElasticPress',
			'ElasticPress',
			$capability,
			'elasticpress',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Action wp_ajax_ep_get_site_post_types
	 *
	 * Send post types for display on the form.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	public function action_wp_ajax_ep_get_site_post_types() {

		check_admin_referer( 'ep_post_type_nonce', 'nonce' );

		$site         = absint( $_POST['site'] ); // WPCS: input var ok.
		$network      = ( 0 === $site ) ? true : false;
		$current_site = get_current_blog_id();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && false === $network && $site !== $current_site ) {
			switch_to_blog( $current_site );
		}

		$post_types           = array();
		$post_types_selected  = ep_get_indexable_post_types( $network );
		$post_types_available = get_post_types( array( 'public' => true ) );

		foreach ( $post_types_available as $post_type_slug ) {

			$post_type = get_post_type_object( $post_type_slug );

			$post_types[ $post_type_slug ] = array(
				'name'     => esc_html( $post_type->labels->singular_name ),
				'selected' => in_array( $post_type_slug, $post_types_selected, true ),
			);

		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && false === $network && $site !== $current_site ) {
			restore_current_blog();
		}

		wp_send_json_success( $post_types );
		wp_die();

	}

	/**
	 * Action wp_ajax_ep_process_site_post_types
	 *
	 * Process post type changes for search.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	public function action_wp_ajax_ep_process_site_post_types() {

		check_admin_referer( 'ep_post_type_change_nonce', 'nonce' );

		$site      = absint( $_POST['site'] ); // WPCS: input var ok.
		$post_type = sanitize_text_field( $_POST['post_type'] ); // WPCS: input var ok.
		$status    = ( 'false' === $_POST['selected'] ) ? false : true; // WPCS: input var ok.
		$network   = true;

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {

			if ( 0 !== $site ) {

				$network = false;
				switch_to_blog( $site );

			}
		}

		$all_post_types = get_post_types( array( 'public' => true ) );

		if ( ! in_array( $post_type, $all_post_types, true ) ) {

			wp_send_json_error();
			wp_die();

		}

		$searchable_post_types = ep_get_indexable_post_types( $network );

		if ( false === $status && in_array( $post_type, $searchable_post_types, true ) ) {
			unset( $searchable_post_types[ $post_type ] );
		}

		if ( true === $status && ! in_array( $post_type, $searchable_post_types, true ) ) {
			$searchable_post_types[ $post_type ] = $post_type;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && 0 === $site ) {

			update_site_option( 'ep_post_types', $searchable_post_types );

		} else {

			update_option( 'ep_post_types', $searchable_post_types );

		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && 0 !== $site ) {
			restore_current_blog();
		}

		wp_send_json_success();
		wp_die();

	}

	/**
	 * Sanitize activation
	 *
	 * Sanitizes the activation input from the dashboard and performs activation/deactivation.
	 *
	 * @since 1.9
	 *
	 * @param string $input input items.
	 *
	 * @return string Sanitized input items
	 */
	public function sanitize_ep_activate( $input ) {

		$input = ( isset( $input ) && 1 === intval( $input ) ? true : false );

		if ( true === $input ) {

			ep_activate();

		} else {

			ep_deactivate();

		}

		return $input;

	}

	/**
	 * Sanitize EP_HOST
	 *
	 * Sanitizes the EP_HOST inputed from the dashboard.
	 *
	 * @since 1.9
	 *
	 * @param string $input input items.
	 *
	 * @return string Sanitized input items
	 */
	public function sanitize_ep_host( $input ) {

		$input = esc_url_raw( $input );

		return $input;

	}

	/**
	 * Sanitize ep_post_types option
	 *
	 * Sanitizes the ep_post_types inputed from the dashboard.
	 *
	 * @since 2.0
	 *
	 * @param string $input input items.
	 *
	 * @return string Sanitized input items
	 */
	public function sanitize_ep_post_types( $input ) {

		$post_types = get_post_types( array( 'public' => true ) );

		if ( ! is_array( $input ) ) {
			return false;
		}

		foreach ( $input as $post_type ) {
			if ( ! in_array( $post_type, $post_types, true ) ) {
				unset ( $input[ $post_type ] );
			}
		}

		return $input;

	}

	/**
	 * Setting callback
	 *
	 * Callback for settings field. Displays textbox to specify the EP_HOST.
	 *
	 * @since 1.9
	 *
	 * @return void
	 */
	public function setting_callback_activate() {
		$stats = ep_get_index_status();
		$disabled = 'disabled';
		if ( $stats['status'] && ! is_wp_error( ep_check_host() ) ) {
			$disabled = '';
		}
		echo '<input type="checkbox" value="1" name="ep_activate" id="ep_activate"' . checked( true, ep_is_activated(), false ) . ' ' . $disabled  . '/>';
	}

	/**
	 * Setting callback
	 *
	 * Callback for settings field. Displays checkboxes for the searched post types.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	public function setting_callback_post_types() {

		echo '<ul id="ep_post_type_list">';

		echo '</ul>';
		echo '</p>';

	}

	/**
	 * Setting callback
	 *
	 * Callback for settings field. Displays textbox to specify the EP_HOST.
	 *
	 * @since 1.9
	 *
	 * @return void
	 */
	public function setting_callback_host() {

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {

			$host = get_site_option( 'ep_host' );

		} else {

			$host = get_option( 'ep_host' );

		}

		$read_only = '';

		if ( false === ep_host_by_option() && defined( 'EP_HOST' ) ) {
			$read_only = 'readonly';
			$host      = EP_HOST;
		}

		$site_stats_id = null;

		if ( is_multisite() && ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) ) {
			$site_stats_id = get_current_blog_id();
		}

		$stats = ep_get_index_status( $site_stats_id );

		$status = ( ! is_wp_error(  $stats['status'] ) && ! is_wp_error( self::$host ) ) ? '<span class="dashicons dashicons-yes" style="color:green;"></span>' : '<span class="dashicons dashicons-no" style="color:red;"></span>';

		echo '<input name="ep_host" id="ep_host"  class="regular-text" type="text" value="' . esc_attr( $host ) . '" ' . esc_attr( $read_only ) . '>' . $status;

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

		include dirname( __FILE__ ) . '/../includes/settings-page.php';

	}

	/**
	 * Easily read bytes
	 *
	 * Converts bytes to human-readable format.
	 *
	 * @since 1.9
	 *
	 * @param int $bytes     The raw bytes to convert.
	 * @param int $precision The precision with which to display the conversion.
	 *
	 * @return string
	 */
	public static function ep_byte_size( $bytes, $precision = 2 ) {

		$kilobyte = 1024;
		$megabyte = $kilobyte * 1024;
		$gigabyte = $megabyte * 1024;
		$terabyte = $gigabyte * 1024;

		if ( ( $bytes >= 0 ) && ( $bytes < $kilobyte ) ) {

			return $bytes . ' B';

		} elseif ( ( $bytes >= $kilobyte ) && ( $bytes < $megabyte ) ) {

			return round( $bytes / $kilobyte, $precision ) . ' KB';

		} elseif ( ( $bytes >= $megabyte ) && ( $bytes < $gigabyte ) ) {

			return round( $bytes / $megabyte, $precision ) . ' MB';

		} elseif ( ( $bytes >= $gigabyte ) && ( $bytes < $terabyte ) ) {

			return round( $bytes / $gigabyte, $precision ) . ' GB';

		} elseif ( $bytes >= $terabyte ) {

			return round( $bytes / $terabyte, $precision ) . ' TB';

		} else {

			return $bytes . ' B';

		}
	}

	/**
	 * Return the url to a ElasticPress settings tab
	 *
	 * @since 2.0
	 *
	 * @param string $tab     The name of the tab you want the url for.
	 *
	 * @return string
	 */
	public static function ep_setting_tab_url( $tab = '' ){
		$defaults = array(
			'page' => 'elasticpress'
		);

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ){
			$url = network_admin_url( 'settings.php' );
		} else {
			$url = admin_url( 'options-general.php' );
		}

		return add_query_arg( wp_parse_args( array( 'tab' => $tab ), $defaults ), $url );
	}

	public function error_notices( $errors ){
		$site_stats_id = null;

		if ( is_multisite() && ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) ) {
			$site_stats_id = get_current_blog_id();
		}

		$stats = ep_get_index_status( $site_stats_id );
		$current_host = ep_get_host( true );

		if( is_wp_error( self::$host ) ){
			$errors[] = esc_html__( 'A host has not been set or is set but cannot be contacted. A proper host must be set before running an index.', 'elasticpress' );
		} elseif( is_wp_error( $current_host ) ){
			$errors[] = esc_html__( 'Current host is set but cannot be contacted. Please contact the server administrator.', 'elasticpress' );
		} elseif( ! is_wp_error( self::$host ) && isset( $stats['msg'] ) ){
			$errors[] = wp_kses( $stats['msg'], array(
				'p'    => array(),
				'code' => array(),
			) );
		}
		return $errors;
	}

	/**
	 * Use WordPress notices to inform user of errors and successful actions
	 *
	 * @since 2.0
	 */
	public function admin_notices(){

		if ( isset( get_current_screen()->id ) && false === strpos( get_current_screen()->id, 'settings_page_elasticpress' )  ) {
			return;
		}
		/**
		 * Allow other features to hook into the ElasticPress Error notices
		 *
		 * @since 2.0
		 */
		$errors = apply_filters( 'ep_admin_settings_error_notices', array() );
		/**
		 * Allow other features to hook into the ElasticPress Success notices
		 *
		 * @since 2.0
		 */
		$success = apply_filters( 'ep_admin_settings_success_notices', array() );

		if( !empty( $errors ) ){
			foreach ( $errors as $message ) {
				printf( '<div class="notice notice-error ep-error"><p>%s</p></div>', $message );
			}
		}

		if( !empty( $success ) ){
			foreach ( $success as $message ) {
				printf( '<div class="notice notice-success"><p>%s</p></div>', $message );
			}
		}

		echo '<div class="notice notice-success ep-notice hidden"><p></p></div>';
	}
}
