<?php

class EP_Search_Module extends EP_Module {
	public $slug = 'search';

	public $requires_install_reindex = true;

	public function setup() {
		add_filter( 'ep_elasticpress_enabled', array( $this, 'integrate_search_queries' ) );
		add_filter( 'ep_pre_query_post_type',array( $this, 'use_searchable_post_types_on_any' ) );
	}

	public function use_searchable_post_types_on_any( $post_type, $query ) {
		if ( $query->is_search() && 'any' === $post_type ) {

			/*
			 * This is a search query
			 * To follow WordPress conventions,
			 * make sure we only search 'searchable' post types
			 */
			$searchable_post_types = ep_get_searchable_post_types();

			// If we have no searchable post types, there's no point going any further
			if ( empty( $searchable_post_types ) ) {

				// Have to return something or it improperly calculates the found_posts
				return false;
			}

			// Conform the post types array to an acceptable format for ES
			$post_types = array();

			foreach( $searchable_post_types as $type ) {
				$post_types[] = $type;
			}

			// These are now the only post types we will search
			$post_type = $post_types;
		}

		return $post_type;
	}

	public function integrate_search_queries( $enabled, $query ) {
		if ( method_exists( $query, 'is_search' ) && $query->is_search() ) {
			$enabled = true;
		}

		return $enabled;
	}

}
