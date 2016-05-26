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
?>

<form method="POST" action="<?php echo esc_attr( $action ); ?>">

	<?php
	settings_fields( 'elasticpress_post_types' );
	do_settings_sections( 'elasticpress_post_types' );
	?>

	<input type="submit" class="button secondary button-large" value="<?php echo esc_attr__( 'Submit', 'elasticpress' ); ?>">

</form>
