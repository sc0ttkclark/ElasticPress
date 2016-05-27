<?php
/**
 * Template for displaying Elasticsearch statistics
 *
 * @since   1.9
 *
 * @package ElasticPress
 *
 * @author  Allan Collins <allan.collins@10up.com>
 */

$site_stats_id = null;

if ( is_multisite() && ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) ) {
	$site_stats_id = get_current_blog_id();
}

$stats = ep_get_index_status( $site_stats_id );
?>

<div id="ep_stats">

	<?php if ( $stats['status'] ): ?>

		<?php if ( ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ): ?>

			<div id="ep_ind_stats" class="ep_stats_section">

				<div id="ep_site_sel">
					<strong><?php esc_html_e( 'Select a site:', 'elasticpress' ) ?></strong>
					<select name="ep_site_select" id="ep_site_select">
						<option value="0"><?php esc_html_e( 'Select', 'elasticpress' ) ?></option>
						<?php
						$site_list = get_site_transient( 'ep_site_list_for_stats' );

						if ( false === $site_list ) {

							$site_list = '';
							$sites     = ep_get_sites();

							foreach ( $sites as $site ) {
								$details = get_blog_details( $site['blog_id'] );
								$site_list .= sprintf( '<option value="%d">%s</option>', $site['blog_id'], $details->blogname );
							}

							set_site_transient( 'ep_site_list_for_stats', $site_list, 600 );
						}

						echo wp_kses( $site_list, array( 'option' => array( 'value' => array() ) ) );
						?>
					</select>
				</div>

			</div>
		<?php endif; ?>

		<?php
			$stats      = ep_get_cluster_status();
			$fs         = $stats->nodes->fs;
			$disk_usage = $fs->total_in_bytes - $fs->available_in_bytes;
		?>

		<div id="ep_stats_container">

			<div class="postbox ep_stats_box">
				<h2><span><?php esc_html_e( 'Cluster Stats', 'elasticpress' ); ?></span></h2>
				<div class="inside">
					<div class="main">
						<ul>
							<li>
								<strong><?php esc_html_e( 'Disk Usage:', 'elasticpress' ); ?></strong> <?php echo esc_html( number_format( ( $disk_usage / $fs->total_in_bytes ) * 100, 0 ) ); ?>%
							</li>
							<li>
								<strong><?php esc_html_e( 'Disk Space Available:', 'elasticpress' ); ?></strong> <?php echo esc_html( $this->ep_byte_size( $fs->available_in_bytes ) ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Total Disk Space:', 'elasticpress' ); ?></strong> <?php echo esc_html( $this->ep_byte_size( $fs->total_in_bytes ) ); ?>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<?php if ( ! defined( 'EP_IS_NETWORK' ) ): ?>
				<?php
					$index_stats  = ep_get_index_status( $site_stats_id );
					$search_stats = ep_get_search_status( $site_stats_id );
				?>
				<div class="postbox ep_stats_box ep_ajax_box">
					<h2><span><?php esc_html_e( 'Search Stats', 'elasticpress' ); ?></span></h2>
					<div class="inside">
						<div class="main">
							<ul>
								<li><strong><?php esc_html_e( 'Total Queries:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $search_stats->query_total );?></li>
								<li><strong><?php esc_html_e( 'Query Time:', 'elasticpress' );?> </strong> <?php echo esc_html( $search_stats->query_time_in_millis ) . 'ms' ?></li>
								<li><strong><?php esc_html_e( 'Total Fetches:', 'elasticpress' );?> </strong> <?php echo esc_html( $search_stats->fetch_total );?></li>
								<li><strong><?php esc_html_e( 'Fetch Time:', 'elasticpress' );?> </strong> <?php echo esc_html( $search_stats->fetch_time_in_millis ) . 'ms'; ?></li>
							</ul>
						</div>
					</div>
				</div>

				<div class="postbox ep_stats_box ep_ajax_box">
					<h2><span><?php esc_html_e( 'Index Stats', 'elasticpress' ); ?></span></h2>
					<div class="inside">
						<div class="main">
							<ul>
								<li><strong><?php esc_html_e( 'Index Total:', 'elasticpress' );?> </strong> <?php echo esc_html( $index_stats['data']->index_total ); ?></li>
								<li><strong><?php esc_html_e( 'Index Time:', 'elasticpress' ); ?></strong> <?php echo esc_html( $index_stats['data']->index_time_in_millis ) . 'ms'; ?></li>
							</ul>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>



	<?php elseif ( ! is_wp_error( ep_check_host() ) ) :

		echo '<strong>' . esc_html__( 'ERROR:', 'elasticpress' ) . '</strong> ' . wp_kses( $stats['msg'], array(
				'p'    => array(),
				'code' => array(),
			) );

	endif; ?>

</div>
