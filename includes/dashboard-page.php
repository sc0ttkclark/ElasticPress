<?php
/**
 * Template for ElasticPress dashboard page
 *
 * @since  2.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$index_meta = get_option( 'ep_index_meta', false );
?>

<?php require_once( dirname( __FILE__ ) . '/header.php' ); ?>

<div class="wrap js-ep-wrap <?php if ( ! empty( $index_meta ) ) : ?>syncing<?php endif; ?>">
	<h2><?php esc_html_e( 'ElasticPress', 'elasticpress' ); ?></h2>

	<p>ElasticPress let's you supercharge your WordPress website with various modules. Activate the ones you need below.</p>

	<ul class="ep-modules metabox-holder">
		<?php $modules = EP_Modules::factory()->registered_modules; ?>

		<?php foreach ( $modules as $module ) :
			$module_classes = ( $module->is_active() ) ? 'module-active' : '';

			if ( ! empty( $index_meta ) && ! empty( $index_meta['module_sync'] ) && $module->slug === $index_meta['module_sync'] ) {
				$module_classes .= ' module-syncing';
			}
			?>
			<li class="ep-module ep-module-<?php echo esc_attr( $module->slug ); ?> <?php echo esc_attr( $module_classes ); ?>">
				<div class="postbox">
					<h2 class="hndle"><span><?php echo esc_html( $module->title ); ?></span></h2>

					<div class="inside activity-block">

						<?php $module->output_module_box(); ?>

					</div>

					<div class="action">
						<a data-module="<?php echo esc_attr( $module->slug ); ?>" class="js-toggle-module deactivate button" href="#"><?php esc_html_e( 'Deactivate', 'elasticpress' ); ?></a>
						<a data-module="<?php echo esc_attr( $module->slug ); ?>" class="js-toggle-module activate button button-primary" href="#"><?php esc_html_e( 'Activate', 'elasticpress' ); ?></a>
						<button disabled data-module="<?php echo esc_attr( $module->slug ); ?>" class="js-toggle-module syncing-placeholder button" href="#"><?php esc_html_e( 'Syncing...', 'elasticpress' ); ?></a>
					</div>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
