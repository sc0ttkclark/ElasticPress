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
	<?php printf( '<h2>%s</h2>', esc_html__( 'ElasticPress', 'elasticpress' ) ); ?>

	<div id="dashboard-widgets" class="metabox-holder columns-2 has-right-sidebar">
		<div class="ep-modules">
			<?php $modules = EP_Module_Loader::factory()->get_available(); var_dump($modules ); ?>
		</div>

	</div>
</div>
