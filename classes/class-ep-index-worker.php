<?php
/**
 * Process indexing of posts
 *
 * @package ElasticPress
 * @since   1.9
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
}