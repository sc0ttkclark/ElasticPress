<?php

class EP_Modules {

	public $registered_modules = array();

	public function setup() {
		add_action( 'after_setup_theme', array( $this, 'setup_modules' ) );
	}

	/**
	 * [ep_register_module description]
	 * @param  string $slug
	 * @param  array  $module_args
	 * 
	 *         Supported array parameters:
	 *         
	 *         "title" (string) - Pretty title for module
	 *         "requires_install_reindex" (boolean) - Setting to true will force a reindex after the module is activated
	 *         "setup_cb" (callback) - Callback to a function to be called on each page load when the module is activated
	 *         "post_activation_cb" (callback) - Callback to a function to be called after a module is first activated
	 *         "module_box_cb" (callback) - Callback to a function that outputs HTML module box interior (description of module)
	 *         
	 * @return boolean
	 */
	public function register_module( $slug, $module_args ) {
		if ( empty( $slug ) || empty( $module_args ) || ! is_array( $module_args ) ) {
			return false;
		}

		$module_args['slug'] = $slug;

		$this->registered_modules[$slug] = new EP_Module( $module_args );

		return true;
	}

	public function get_active_modules() {
		$active = array();

		foreach ( $this->registered_modules as $module ) {
			if ( $module->active ) {
				$active[$module->slug] = $module;
			}
		}

		return $active;
	}

	public function setup_modules() {
		$modules = apply_filters( 'ep_active_modules', get_option( 'ep_active_modules', array() ) );

		foreach ( $modules as $module_slug ) {
			if ( empty( $this->registered_modules[$module_slug] ) ) {
				continue;
			}

			$this->registered_modules[$module_slug]->setup();
		}
	}

	public static function factory() {
		static $instance = false;

		if ( ! $instance  ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}

EP_Modules::factory();

function ep_register_module( $slug, $module_args ) {
	return EP_Modules::factory()->register_module( $slug, $module_args );
}

function ep_get_registered_module( $slug ) {
	if ( empty( EP_Modules::factory()->registered_modules[$slug] ) ) {
		return false;
	}
	return EP_Modules::factory()->registered_modules[$slug];
}
