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
		add_action( 'admin_post_cleanlinks_export', array( $this, 'export_csv' ) );
	}

	/**
	 * Export csv functionality
	 */
	public function export_csv() {
		// Verify nonce
		if (
			! isset( $_POST['cleanlinks_export_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cleanlinks_export_nonce'] ) ), 'cleanlinks_export' )
		) {
			wp_die( esc_html__( 'Security check failed.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 403 ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 403 ) );
		}

		// Load WP_Filesystem
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem || ! is_object( $wp_filesystem ) ) {
			wp_die( esc_html__( 'Filesystem API not available.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 500 ) );
		}

		// Build CSV content manually
		$csv_lines = array();
		$csv_lines[] = '"ID","Title","Redirect From","Redirect To"';

		$args = array(
			'post_type'              => 'cleanlinks',
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$query = new \WP_Query( $args );

		foreach ( $query->posts as $post_id ) {
			$post      = get_post( $post_id );
			$permalink = get_permalink( $post_id );

			$row = array(
				$post_id,
				$post->post_title,
				$permalink,
				get_post_meta( $post_id, 'cleanlink_redirect_url', true ),
			);

			$escaped = array_map(
				function ( $value ) {
					return '"' . str_replace( '"', '""', $value ) . '"';
				},
				$row
			);

			$csv_lines[] = implode( ',', $escaped );
		}

		$csv_data = implode( "\r\n", $csv_lines );

		// Write to temp file
		$tmp_file = wp_tempnam( 'export_cleanlink.csv' );
		if ( ! $tmp_file ) {
			wp_die( esc_html__( 'Could not create a temp file.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 500 ) );
		}

		if ( ! $wp_filesystem->put_contents( $tmp_file, $csv_data, FS_CHMOD_FILE ) ) {
			wp_die( esc_html__( 'Could not write CSV file.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 500 ) );
		}

		// Read and download
		$file_contents = $wp_filesystem->get_contents( $tmp_file );
		if ( false === $file_contents ) {
			wp_die( esc_html__( 'Could not read CSV file.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 500 ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=export_cleanlink.csv' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Outputting raw CSV data for file download
		echo $file_contents;

		$wp_filesystem->delete( $tmp_file );

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
							<form method="post" id="export-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) );?>">
								<?php wp_nonce_field( 'cleanlinks_export', 'cleanlinks_export_nonce' ); ?>
								<input type="hidden" name="action" value="cleanlinks_export">
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
