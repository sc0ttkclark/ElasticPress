<?php
/**
 * Template for ElasticPress settings page
 *
 * @since  1.7
 *
 * @package elasticpress
 *
 * @author  Allan Collins <allan.collins@10up.com>
 */
?>
<div class="wrap">
	<h2><?php esc_html_e( 'ElasticPress', 'elasticpress' ); ?></h2>

	<ul class="ep-modules">
		<?php $modules = EP_Modules::factory()->registered_modules; ?>

		<?php foreach ( $modules as $module ) : ?>
			<li class="ep-module-<?php echo esc_attr( $module->slug ); ?>">
				<h2><?php echo esc_html( $module->title ); ?></h2>

				<?php $module->output_module_box(); ?>

				<div class="action">
					<?php if ( $module->is_active() ) : ?>
						<a data-module="<?php echo esc_attr( $module->slug ); ?>" class="js-toggle-module button button-primary" href="#"><?php esc_html_e( 'Deactivate', 'elasticpress' ); ?></a>
					<?php else : ?>
						<a data-module="<?php echo esc_attr( $module->slug ); ?>" class="js-toggle-module button button-primary" href="#"><?php esc_html_e( 'Install and Activate', 'elasticpress' ); ?></a>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
