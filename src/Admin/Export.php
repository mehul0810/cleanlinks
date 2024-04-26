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
		<div class="wrap">
			<h1><?php esc_html_e( 'Export Simplified Links', 'simplified-links' ); ?></h1>
			<p><?php esc_html_e( 'Export your links data to a CSV file.', 'simplified-links' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'simplifiedwp_links_export_nonce', 'simplifiedwp_links_export_nonce' ); ?>
				<input type="hidden" name="action" value="simplifiedwp_links_export">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Export', 'simplified-links' ); ?></button>
			</form>
		</div>
		<?php
	}
}
