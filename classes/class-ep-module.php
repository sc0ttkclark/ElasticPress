<?php

class EP_Module {
	public $slug;

	public $title;

	public $setup_cb;

	public $module_box_cb;

	public $post_activation_cb;

	public $requires_install_reindex;

	public $active;

	public function __construct( $args ) {
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}

		do_action( 'ep_module_create', $this );
	}

	public function setup() {
		if ( ! empty( $this->setup_cb ) ) {
			call_user_func( $this->setup_cb );
		}

		$this->active = true;

		do_action( 'ep_module_setup', $this->slug, $this );
	}

	public function output_module_box() {
		if ( ! empty( $this->module_box_cb ) ) {
			call_user_func( $this->module_box_cb );
		}

		do_action( 'ep_module_box', $this->slug, $this );
	}

	public function is_active() {
		return $this->active;
	}
}