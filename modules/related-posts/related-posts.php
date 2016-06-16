<?php

function ep_related_posts_formatted_args( $formatted_args, $args ) {
	if ( ! empty( $args[ 'more_like' ] ) ) {
		$formatted_args[ 'query' ] = array(
			'more_like_this' => array(
				'ids'				 => is_array( $args[ 'more_like' ] ) ? $args[ 'more_like' ] : array( $args[ 'more_like' ] ),
				'fields'			 => array( 'post_title', 'post_content', 'terms.post_tag.name' ),
				'min_term_freq'		 => 1,
				'max_query_terms'	 => 12,
				'min_doc_freq'		 => 1,
			)
		);
	}
	
	return $formatted_args;
}

function ep_related_posts_filter_content( $content ) {
	if ( is_search() || is_home() || is_archive() || is_category() ) {
		return $content;
	}
	$post_id		 = get_the_ID();
	$cache_key		 = md5( 'related_posts_' . $post_id );
	$related_posts	 = wp_cache_get( $cache_key, 'ep-related-posts' );
	if ( false === $related_posts ) {
		$related_posts = $this->find_related( $post_id );
		wp_cache_set( $cache_key, $related_posts, 'ep-related-posts', 300 );
	}
	$html = $this->get_html( $related_posts );
	return $content . "\n" . $html;
}

function ep_find_related( $post_id, $return = 4 ) {
	$args = array(
		'more_like'		 => $post_id,
		'posts_per_page' => $return,
		's'				 => ''
	);

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return false;
	}
	return $query->posts;
}

function ep_related_get_html( $posts ) {
	if ( false === $posts ) {
		return '';
	}

	$html = '<h3>Related Posts</h3>';
	$html .= '<ul>';

	foreach ( $posts as $post ) {
		$html.=sprintf(
		'<li><a href="%s">%s</a></li>', esc_url( get_permalink( $post->ID ) ), esc_html( $post->post_title )
		);
	}

	$html .= '</ul>';
	/**
	 * Filter the display HTML for related posts.
	 * 
	 * If developers want to customize the returned HTML for related posts or
	 * write their own HTML, they have the power to do so.
	 * 
	 * @param string $html Default Generated HTML 
	 * @param array $posts Array of WP_Post objects.
	 */
	return apply_filters( 'ep_related_html', $html, $posts );
}

function ep_related_posts_setup() {
	add_filter( 'ep_formatted_args', 'ep_related_posts_formatted_args', 10, 2 );
	add_filter( 'the_content', 'ep_related_posts_filter_content' );
}

function ep_related_posts_module_box() {
	?>
	<p>Show related content below each post. Related content is queried performantly and effectively.</p>
	<?php
}

ep_register_module( 'related_posts', array(
	'title' => 'Related Posts',
	'setup_cb' => 'ep_related_posts_setup',
	'module_box_cb' => 'ep_related_posts_module_box',
	'requires_install_reindex' => false,
) );

