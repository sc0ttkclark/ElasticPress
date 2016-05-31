<?php
/**
 * Form for setting ElasticPress preferences
 *
 * @since   1.9
 *
 * @package elasticpress
 *
 * @author  Allan Collins <allan.collins@10up.com>
 */
?>

<?php
//Set form action
$action = 'options.php';

if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
	$action = '';
	$paused      = absint( get_site_option( 'ep_index_paused' ) );
	$setup = absint( get_site_option( 'ep_index_setup' ) );
} else {
	$paused      = absint( get_option( 'ep_index_paused' ) );
	$setup = absint( get_option( 'ep_index_setup' ) );
}

if ( false === get_transient( 'ep_index_offset' ) ) {
	$run_text = esc_html__( 'Run Index', 'elasticpress' );
} else {
	if ( $paused ) {
		$run_text = esc_html__( 'Indexing is Paused', 'elasticpress' );
	} else {
		$run_text = esc_html__( 'Running Index...', 'elasticpress' );
	}
}

$stop_text  = $paused ? esc_html__( 'Resume Indexing', 'elasticpress' ) : esc_html__( 'Pause Indexing', 'elasticpress' );
?>

<form method="POST" action="<?php echo esc_attr( $action ); ?>">
	<?php

	settings_fields( 'elasticpress' );
	do_settings_sections( 'elasticpress' );

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {

		$host = get_site_option( 'ep_host' );

	} else {

		$host = get_option( 'ep_host' );

	}
	?>

	<div id="indexprogresss">
		<div id="progressstats"></div>
		<div id="progresspercent"></div>
		<div id="progressbar"></div>
	</div>

	<p class="ep-actions">
		<?php
			if ( ( ! ep_host_by_option() && ! is_wp_error( EP_Settings::$host ) ) || is_wp_error( EP_Settings::$host ) || $host ) {
				submit_button( esc_attr__('Save Changes', 'elasticpress'), 'primary', 'submit', false);
			}
		?>
		<?php if ( EP_Settings::$host && ! is_wp_error( EP_Settings::$host ) ) : ?>
			<input type="submit" name="ep_run_index" id="ep_run_index" class="button button-large" value="<?php echo esc_attr( $run_text ); ?>"<?php if ( $paused ) : echo ' disabled="disabled"'; endif; ?>>
			<input type="submit" name="ep_restart_index" id="ep_restart_index" class="button button-large hidden" value="<?php esc_attr_e( 'Restart Index', 'elasticpress' ); ?>">
			<input type="submit" name="ep_pause_index" id="ep_pause_index" class="button hidden" value="<?php echo esc_attr( $stop_text ); ?>"<?php if ( $paused ) : echo ' data-paused="enabled"'; endif; ?>>
			<br />
			<br />
			<input type="checkbox" name="ep_index_setup" id="ep_index_setup"<?php if ( $paused ) : echo ' disabled="disabled"'; endif; ?><?php if ( $setup ) : echo ' checked="checked"'; endif; ?>/><label for="ep_keep_active"><?php esc_html_e( 'Run a full setup of indexes including mappings.', 'elasticpress' ) ?></label>
		<?php endif; ?>
	</p>

</form>
