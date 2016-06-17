<?php
/**
 * Template for ElasticPress settings page
 *
 * @since  1.7
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="wrap">
	<h2><?php esc_html_e( 'ElasticPress', 'elasticpress' ); ?></h2>

	<ul class="ep-modules">
		<?php $modules = EP_Modules::factory()->registered_modules; ?>

		<?php foreach ( $modules as $module ) : ?>
			<li class="ep-module ep-module-<?php echo esc_attr( $module->slug ); ?> <?php if ( $module->is_active() ) : ?>module-active<?php endif; ?>">
				<h2><?php echo esc_html( $module->title ); ?></h2>

				<?php $module->output_module_box(); ?>

				<div class="action">
					<a data-module="<?php echo esc_attr( $module->slug ); ?>" class="js-toggle-module deactivate button button-primary" href="#"><?php esc_html_e( 'Deactivate', 'elasticpress' ); ?></a>
					<a data-module="<?php echo esc_attr( $module->slug ); ?>" class="js-toggle-module activate button button-primary" href="#"><?php esc_html_e( 'Install and Activate', 'elasticpress' ); ?></a>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
