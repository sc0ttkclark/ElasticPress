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

?>

<div id="ep_post_types">

<?php if ( true === $network ) :?>
	<div id="ep_site_sel">
		<strong><?php esc_html_e( 'Select a site:', 'elasticpress' ) ?></strong>
		<select name="ep_site_select" id="ep_site_select">
			<option value="0"><?php esc_html_e( 'Select', 'elasticpress' ) ?></option>
			<?php
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
			?>
		</select>
		<p class="description"><?php esc_html_e( 'Note: Selecting "All Sites" uses a generic set of post types from the main site on the network.', 'elasticpress' ); ?></p>
	</div>
<?php else: ?>
	adsfadf

<?php
	endif;
	settings_fields( 'elasticpress_post_types' );
	do_settings_sections( 'elasticpress_post_types' );
?>


