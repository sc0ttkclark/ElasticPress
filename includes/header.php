<?php
$settings_url =  ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ? admin_url( 'network/admin.php?page=elasticpress-settings' ) : admin_url( 'admin.php?page=elasticpress-settings' );
?>

<div class="ep-header-menu">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=elasticpress' ) ); ?>"><img width="150" src="<?php echo esc_url( plugins_url( '/images/logo.svg', dirname( __FILE__ ) ) ); ?>"></a>

	<div class="icons">
		<span class="sync-status"></span>
		<a class="dashicons pause-sync dashicons-controls-pause"></a>
		<a class="dashicons resume-sync dashicons-controls-play"></a>
		<a class="dashicons cancel-sync dashicons-no"></a>
		<a class="dashicons start-sync dashicons-update"></a>
		<a href="<?php echo esc_url( $settings_url ); ?>" class="dashicons dashicons-admin-generic"></a>
	</div>

	<div class="progress-bar"></div>
</div>