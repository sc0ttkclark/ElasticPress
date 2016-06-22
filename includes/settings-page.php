<?php
/**
 * Template for ElasticPress settings page
 *
 * @since  2.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( defined( 'EP_HOST' ) && EP_HOST ) {
	$host = EP_HOST;
} else {
	$host = get_option( 'ep_host', '' );
}
?>

<?php require_once( dirname( __FILE__ ) . '/header.php' ); ?>

<div class="wrap js-ep-wrap">
	<h2><?php esc_html_e( 'Settings', 'elasticpress' ); ?></h2>
	
	<form action="options.php" method="post">
		<?php settings_fields( 'elasticpress' ); ?>
		<?php settings_errors(); ?>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="ep_host"><?php esc_html_e( 'Elasticsearch Host', 'elasticpress' ); ?></label></th>
					<td>
						<input <?php if ( defined( 'EP_HOST' ) && EP_HOST ) : ?>disabled<?php endif; ?> placeholder="http://" type="text" value="<?php echo esc_url( $host ); ?>" name="ep_host" id="ep_host">
					</td>
				</tr>
				<tr>
			</tbody>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
