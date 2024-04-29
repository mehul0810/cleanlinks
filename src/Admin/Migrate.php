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
									<button type="button" class="button button-primary" <?php echo $total_count > 0 ? '' : 'disabled'; ?>>
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

	}
}
