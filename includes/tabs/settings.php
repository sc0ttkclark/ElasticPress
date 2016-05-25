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
	$keep_active = absint( get_site_option( 'ep_index_keep_active' ) );
} else {
	$paused      = absint( get_option( 'ep_index_paused' ) );
	$keep_active = absint( get_option( 'ep_index_keep_active' ) );
}

$keep_active_text = esc_html__( 'Do not deactivate Elasticsearch integration (this will not delete current index, mappings and posts before reindexing)', 'elasticpress' );
$host_alive = ep_check_host();

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
		<div id="progressstats">25 of 500</div>
		<div id="progresspercent">25%</div>
		<div id="progressbar"></div>
		<?php if ( $host_alive && ! is_wp_error( $host_alive ) ) : ?>
			<input type="submit" name="ep_pause_index" id="ep_pause_index" class="button button-secondary" value="<?php echo esc_attr( $stop_text ); ?>"<?php if ( $paused ) : echo ' data-paused="enabled"'; endif; ?>>
		<?php endif; ?>
	</div>

	<p class="ep-actions">
		<?php
			if ( ( ! ep_host_by_option() && ! is_wp_error( ep_check_host() ) ) || is_wp_error( ep_check_host() ) || $host ) {
				submit_button( esc_attr__('Save Changes', 'elasticpress'), 'primary', 'submit', false);
			}
		?>
		<?php if ( $host_alive && ! is_wp_error( $host_alive ) ) : ?>
			<input type="submit" name="ep_run_index" id="ep_run_index" class="button secondary button-large" value="<?php echo esc_attr( $run_text ); ?>"<?php if ( $paused ) : echo ' disabled="disabled"'; endif; ?>>
			<input type="submit" name="ep_restart_index" id="ep_restart_index" class="button button-large" value="<?php esc_attr_e( 'Restart Index', 'elasticpress' ); ?>">
			<br />
			<br />
			<input type="checkbox" name="ep_keep_active" id="ep_keep_active"<?php if ( $paused ) : echo ' disabled="disabled"'; endif; ?><?php if ( $keep_active ) : echo ' checked="checked"'; endif; ?>/><label for="ep_keep_active"><?php echo $keep_active_text; ?></label>
		<?php else : ?>
			<span class="error"><?php esc_html_e( 'A proper host must be set before running an index.', 'elasticpress' ); ?></span>
		<?php endif; ?>
	</p>

</form>
