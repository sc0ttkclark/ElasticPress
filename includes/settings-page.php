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

	<h2 class="nav-tab-wrapper" id="elasticpress-tabs">

		<?php

			/**
			 * Allow other tabs
			 *
			 * Allows individual features to add their settings tabs.
			 *
			 * @since 2.0
			 *
			 * @param array tab slugs and their labels.
			 */
			$tabs = apply_filters( 'ep_setting_tabs', array(
				'settings' => esc_html__( 'Settings', 'elasticpress' ),
				'post-types' => esc_html__( 'Post Types', 'elasticpress' ),
				'stats' => esc_html__( 'Stats', 'elasticpress' ),
			) );
			$tab_slugs = array_keys( $tabs );
			$current_tab = ( isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ) ? $_GET['tab']  : reset( $tab_slugs );

			foreach( $tabs as $slug => $tab){
				$active_tab = ( $current_tab === $slug ) ? 'nav-tab-active' : '';
				printf( '<a class="nav-tab %s" id="%s-tab" href="%s">%s</a>', $active_tab, esc_attr( $slug ), esc_url( EP_Settings::ep_setting_tab_url( $slug ) ), esc_html( $tab )  );
			}
		?>
	</h2>

	<?php
		/**
		 * Allow other tabs
		 *
		 * Allows individual features to include their own tabs.
		 *
		 * @since 2.0
		 *
		 * @param string path to tab file.
		 */
		include_once( apply_filters( "ep_{$current_tab}_tab_include_pat", dirname( __FILE__ ) . "/tabs/{$current_tab}.php" ) );
	?>
</div>
