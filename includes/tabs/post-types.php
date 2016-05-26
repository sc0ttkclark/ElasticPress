<?php
/**
 * Form for setting post type preferences
 *
 * @since   2.0
 *
 * @package elasticpress
 *
 * @author  Chris Wiegman <chris.wiegman@10up.com>
 */

// Set form action.
$action = 'options.php';

$network = false;

if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
	$network = true;
}

echo '<p class="ep-actions">';

if ( true === $network ) {

	echo '<table class="form-table"><tbody><tr><th scope="row">Select site:</th>';

	echo '<td>';
	echo '<div id="ep_site_sel">';
	echo '<select name="ep_site_select" id="ep_site_select">';
	echo '<option value="0">' . esc_html__( 'All Sites', 'elasticpress' ) . '</option>';

	$site_list = get_site_transient( 'ep_site_list_for_stats' );

	if ( false === $site_list ) {

		$site_list = '';
		$sites     = ep_get_sites();

		foreach ( $sites as $site ) {

			$details = get_blog_details( $site['blog_id'] );

			$site_list .= sprintf( '<option value="%d">%s</option>', $site['blog_id'], $details->blogname );

		}

		set_site_transient( 'ep_site_list_for_stats', $site_list, 600 );

	}

	echo wp_kses( $site_list, array( 'option' => array( 'value' => array() ) ) );

	echo '</select>';
	echo '</div>';
	echo '<p>' . esc_html__( 'Note: Selecting "All Sites" uses a generic set of post types from the main site on the network.', 'elasticpress' ) . '</p>';
	echo '</td>';
	echo '</tr></table>';

}

?>

<form method="POST" action="<?php echo esc_attr( $action ); ?>">

	<?php
	settings_fields( 'elasticpress_post_types' );
	do_settings_sections( 'elasticpress_post_types' );
	?>

	<input type="submit" class="button secondary button-large" value="<?php echo esc_attr__( 'Submit', 'elasticpress' ); ?>">

</form>
