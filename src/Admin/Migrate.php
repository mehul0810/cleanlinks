<?php
/**
 * Simplified Links | Migrate.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Admin;

use SimplifiedWP\Links\Includes\Helpers;

/**
 *  Bailout, if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrate.
 */
class Migrate {
	/**
	 * Initiate Migrate class.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_ajax_simplified_links_migrate', [ $this, 'migrate_links' ] );
	}

	/**
	 * Render UI for migration.
	 *
	 * @return void
	 */
	public function render_ui() {
		$plugins = Helpers::get_migration_supported_plugins();

		foreach ( $plugins as $plugin ) {
			// Bailout to next iteration, if the migration supported plugin is not active.
			if ( ! is_plugin_active( $plugin['path'] ) ) {
				continue;
			}

			$publish_count = wp_count_posts( $plugin['post_type'] )->publish;
			$draft_count   = wp_count_posts( $plugin['post_type'] )->draft;
			$total_count   = $publish_count + $draft_count;
			?>
			<div id="#poststuff">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<div class="postbox-header">
								<h2 class="hndle ui-sortable-handle">
									<?php
									echo sprintf(
										'Migrate links from %1$s',
										$plugin['name']
									);
									?>
								</h2>
							</div>
							<div class="inside">
								<p>
									<?php
									echo sprintf(
										'There are %1$s published links and %2$s draft links available to migrate.',
										$publish_count,
										$draft_count
									);
									?>
								</p>
								<div class="simplified-links--migrate-btn-wrap">
									<button
										data-post_type="<?php echo esc_html( $plugin['post_type'] ); ?>"
										data-meta_key="<?php echo esc_html( $plugin['meta_key'] ); ?>"
										type="button"
										class="button button-primary"
										<?php echo $total_count > 0 ? '' : 'disabled'; ?>
									>
										<?php esc_html_e( 'Migrate', 'simplified-links' ); ?>
									</button>
									<span class="spinner"></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
	}

	/*
	 * Migrate links AJAX callback.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return WP_Object
	 */
	public function migrate_links() {
		// Sanitize and validate input.
		$_post = Helpers::clean( $_POST );
echo "<pre>"; print_r($_post); die();
		// Setup necessary variables.
		$offset    = isset( $_post['offset'] ) ? intval( $_post['offset'] ) : 0;
		$limit     = apply_filters( 'simplifiedwp/links/migration_limit', 100 );
		$post_type = ! empty( $_post['postType'] ) ? $_post['postType'] : '';
		$meta_key  = ! empty( $_post['metaKey'] ) ? $_post['metaKey'] : '';

		// Query old post type with offset and limit.
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $limit,
			'offset'         => $offset,
		);
		$old_posts = new \WP_Query( $args );

		if ( $old_posts->have_posts() ) {
			while ( $old_posts->have_posts() ) {
				$old_posts->the_post();

				$old_post_id = get_the_ID();

				// Create new post.
				$new_post = array(
					'ID'        => $old_post_id,
					'post_type' => 'simplifiedwp_links',
				);

				// Insert new post
				$new_post_id = wp_update_post( $new_post );

				if ($new_post_id) {
					// Get meta value of old post type meta key.
					$old_meta_value = get_post_meta( $old_post_id, $meta_key, true );

					// Update the post meta of new post type with the meta key.
					update_post_meta( $new_post_id, 'simplifiedwp_links_redirect_url', $old_meta_value );
				}
			}
			wp_reset_postdata();

			// Send response to client
			wp_send_json_success( true );
		} else {
			// No more posts to migrate
			wp_send_json_success( false );
		}
	}
}
