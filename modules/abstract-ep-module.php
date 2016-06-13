<?php

abstract class EP_Module {
	public $slug;

	public $dependencies;

	public $requires_install_reindex;

	abstract public function setup();
}