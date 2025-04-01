<?php
/**
 * CleanLinks | Export.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

 namespace MG\CleanLinks\Admin;

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
		add_action( 'admin_post_clean_links_export', array( $this, 'export_csv' ) );
	}

	/**
	 * Export csv functionality
	 */
	public function export_csv() {

		// Start the output buffer.
		ob_start();

		// Set PHP headers for CSV output.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=export_cleanlink.csv' );

		// Create the headers.
		$header_args = array( 'Id', 'Title', 'Slug', 'Redirect To' );

		$args = array(
			'post_type'              => 'clean_links',
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$query = new \WP_Query( $args );

		$results = $query->posts;

		// Clean up output buffer before writing anything to CSV file.
		ob_end_clean();

		// Create a file pointer with PHP.
		$output = fopen( 'php://output', 'w' );

		// Write headers to CSV file.
		fputcsv( $output, $header_args );

		// Loop through the prepared data to output it to CSV file.
		foreach ( $results as $post_id ) {
			$post           = get_post( $post_id );
			$permalink 		= get_permalink( $post_id );
			$parts 			= explode( '/', rtrim( $permalink, '/' ) );
       		$slug 			= $parts[count($parts)-2]; // Get the slug part

			$modified_values = array(
				$post_id,
				$post->post_title,
				$slug,
				get_post_meta( $post_id, 'cleanlink_redirect_url', true ),
			);
			fputcsv( $output, $modified_values );
		}

		// Close the file pointer with PHP with the updated output.
		fclose( $output );
		exit;
	}

	public function render_ui() {
		?>
		<div id="#poststuff">
			<div class="metabox-holder">
				<div class="meta-box-sortables ui-sortable">
					<div class="postbox">
						<div class="postbox-header">
							<h2 class="hndle ui-sortable-handle">
								<?php esc_html_e( 'Export', 'cleanlinks' ); ?>
							</h2>
						</div>
						<div class="inside">
							<p><?php esc_html_e( 'Using this tool, you can have a seamless experience to export your links to a CSV (Comma Separated Values) file with just a single click.', 'cleanlinks' ); ?></p>
							<form method="post" id="export-form" action="<?php echo admin_url( 'admin-post.php' );?>">									
								<?php wp_nonce_field( 'cleanlinks_export', 'clean_links_export_nonce' ); ?>
								<input type="hidden" name="action" value="clean_links_export">
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Export', 'cleanlinks' ); ?></button>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
