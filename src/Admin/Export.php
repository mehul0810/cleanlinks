<?php
/**
 * Simplified Links | Export.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

 namespace SimplifiedWP\Links\Admin;

/**
 *  Bailout, if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export.
 */
class Export {
	/**
	 * Initiate export.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {

	}

	public function render_ui() {
		?>
		<div id="#poststuff">
			<div class="metabox-holder">
				<div class="meta-box-sortables ui-sortable">
					<div class="postbox">
						<div class="postbox-header">
							<h2 class="hndle ui-sortable-handle">
								<?php esc_html_e( 'Export', 'simplified-links' ); ?>
							</h2>
						</div>
						<div class="inside">
							<p><?php esc_html_e( 'Using this tool, you can have a seamless experience to export your links to a CSV (Comma Separated Values) file with just a single click.', 'simplified-links' ); ?></p>
							<form method="post">
								<?php wp_nonce_field( 'simplifiedwp_links_export_nonce', 'simplifiedwp_links_export_nonce' ); ?>
								<input type="hidden" name="action" value="simplifiedwp_links_export">
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Export', 'simplified-links' ); ?></button>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
