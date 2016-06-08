<?php

abstract class EP_Module {
	public $slug;

	public $dependencies = array();

	public $requires_install_reindex = false;

	abstract public function setup();
}