<?php

class EP_Search_Module extends EP_Module {
	public $slug = 'search';

	public $requires_install_reindex = true;

	public function setup() {

		/**
		 * By default EP will not integrate on admin or ajax requests. Since admin-ajax.php is
		 * technically an admin request, there is some weird logic here. If we are doing ajax
		 * and ep_ajax_wp_query_integration is filtered true, then we skip the next admin check.
		 */
		$admin_integration = apply_filters( 'ep_admin_wp_query_integration', false );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( ! apply_filters( 'ep_ajax_wp_query_integration', false ) ) {
				return;
			} else {
				$admin_integration = true;
			}
		}

		if ( is_admin() && ! $admin_integration ) {
			return;
		}

		add_filter( 'ep_elasticpress_enabled', array( $this, 'integrate_search_queries' ) );
		add_filter( 'ep_pre_query_post_type',array( $this, 'use_searchable_post_types_on_any' ) );
		add_filter( 'ep_formatted_args',  array( $this, 'weight_recent' ) , 10, 2 );
	}

	/**
	 * Handle weighting. Only affects search queryies
	 *
	 * @since  1.0
	 * @param  array $formatted_args The formatted WP query arguments
	 * @return array
	 */
	public function weight_recent( $formatted_args, $args ) {
		if ( ! empty( $args['s'] ) ) {
			$date_score = array(
				'function_score' => array(
					'query' => $formatted_args['query'],
					'exp' => array(
						'post_date_gmt' => array(
							'scale' => apply_filters( 'epwr_scale', '4w', $formatted_args, $args ),
							'decay' => apply_filters( 'epwr_decay', .25, $formatted_args, $args ),
							'offset' => apply_filters( 'epwr_offset', '1w', $formatted_args, $args ),
						),
					),
				),
			);

			$formatted_args['query'] = $date_score;
		}

		return $formatted_args;
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

return new EP_Search_Module;
