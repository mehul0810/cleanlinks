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
	 * Query used to retrieve export rows.
	 *
	 * @var ExportQuery
	 */
	private $query;

	/**
	 * Serializer used to build CSV output.
	 *
	 * @var ExportCsvSerializer
	 */
	private $serializer;

	/**
	 * Initialize export dependencies.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param ExportQuery         $query      Query used to retrieve export rows.
	 * @param ExportCsvSerializer $serializer Serializer used to build CSV output.
	 * @return void
	 */
	public function __construct( ?ExportQuery $query = null, ?ExportCsvSerializer $serializer = null ) {
		$this->query      = $query ? $query : new ExportQuery();
		$this->serializer = $serializer ? $serializer : new ExportCsvSerializer();
	}

	/**
	 * Register export hooks.
	 *
	 * @since 1.1.1
	 * @access public
	 *
	 * @return void
	 */
	public function register_hooks() {
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

		// Write to temp file
		$tmp_file = wp_tempnam( 'export_cleanlink.csv' );
		if ( ! $tmp_file ) {
			wp_die( esc_html__( 'Could not create a temp file.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 500 ) );
		}

		// Preserve compatibility with injected legacy query/serializer doubles.
		if ( ! method_exists( $this->query, 'iterate_rows' ) || ! method_exists( $this->serializer, 'serialize_header' ) || ! method_exists( $this->serializer, 'serialize_chunk' ) ) {
			$csv_data = $this->serializer->serialize( $this->query->get_rows() );
			if ( ! $wp_filesystem->put_contents( $tmp_file, $csv_data, FS_CHMOD_FILE ) ) {
				$wp_filesystem->delete( $tmp_file );
				wp_die( esc_html__( 'Could not write CSV file.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 500 ) );
			}

			$file_contents = $wp_filesystem->get_contents( $tmp_file );
			if ( false === $file_contents ) {
				$wp_filesystem->delete( $tmp_file );
				wp_die( esc_html__( 'Could not read CSV file.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 500 ) );
			}

			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=export_cleanlink.csv' );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Outputting raw CSV data for file download
			echo $file_contents;

			$wp_filesystem->delete( $tmp_file );
			exit;
		}

		// Use a native stream so the complete export is not held in memory.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations -- wp_tempnam() provides a local temporary path.
		$file_handle = fopen( $tmp_file, 'wb' );
		if ( false === $file_handle ) {
			$wp_filesystem->delete( $tmp_file );
			wp_die( esc_html__( 'Could not write CSV file.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 500 ) );
		}

		$write_succeeded = $this->write_file_contents( $file_handle, $this->serializer->serialize_header() );
		$rows            = method_exists( $this->query, 'iterate_rows' ) ? $this->query->iterate_rows() : $this->query->get_rows();
		$chunk           = array();
		foreach ( $rows as $row ) {
			$chunk[] = $row;
			if ( count( $chunk ) < ExportQuery::PAGE_SIZE ) {
				continue;
			}

			$write_succeeded = $write_succeeded && $this->write_file_contents( $file_handle, "\r\n" . $this->serializer->serialize_chunk( $chunk ) );
			$chunk           = array();
		}

		if ( ! empty( $chunk ) ) {
			$write_succeeded = $write_succeeded && $this->write_file_contents( $file_handle, "\r\n" . $this->serializer->serialize_chunk( $chunk ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations -- Close the local temporary stream opened above.
		fclose( $file_handle );
		if ( ! $write_succeeded ) {
			$wp_filesystem->delete( $tmp_file );
			wp_die( esc_html__( 'Could not read CSV file.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 500 ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=export_cleanlink.csv' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations -- Stream the local temporary file without duplicating it in memory.
		if ( false === readfile( $tmp_file ) ) {
			$wp_filesystem->delete( $tmp_file );
			wp_die( esc_html__( 'Could not read CSV file.', 'cleanlinks' ), esc_html__( 'Error', 'cleanlinks' ), array( 'response' => 500 ) );
		}

		$wp_filesystem->delete( $tmp_file );

		exit;
	}

	/**
	 * Write all content to an open temporary file stream.
	 *
	 * @since 1.1.1
	 * @access private
	 *
	 * @param resource $handle  Open file handle.
	 * @param string   $contents Content to write.
	 * @return bool
	 */
	private function write_file_contents( $handle, $contents ) {
		$length = strlen( $contents );
		$offset = 0;

		while ( $offset < $length ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations -- Write to the local temporary stream.
			$written = fwrite( $handle, substr( $contents, $offset ) );
			if ( false === $written || 0 === $written ) {
				return false;
			}

			$offset += $written;
		}

		return true;
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
