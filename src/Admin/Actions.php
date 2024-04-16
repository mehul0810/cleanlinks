<?php
/**
 * Simplified Links | Admin Actions.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Admin;

use SimplifiedWP\Links\includes\Helpers;
/** 
 *  Bailout, if accessed directly. 
 */ 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions {
	const LIMIT   = 100;
	const OPTION        = 'simplifiedwp_links_import_all_enable';
	const FILTER_PLUGIN = 'simplifiedwp_links_import_all_filter_plugin';

	/**
	 * Initialize the class.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'wp_ajax_simplified_import', [ $this, 'simplified_import_all_links' ] );
		add_action( 'admin_menu', [ $this, 'add_admin_pages' ] );
		add_action( 'manage_simplifiedwp_links_posts_custom_column', [ $this, 'simplifiedwp_links_custom_column_values' ], 10, 2 );
		add_action ('post_submitbox_minor_actions', [ $this, 'before_preview_changes'] );
		add_action( 'admin_post_export', [ $this, 'export_csv' ] );
	}
	
	/**
	 * Add Essential Admin Pages.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function add_admin_pages() {
		
		add_submenu_page(
			'edit.php?post_type=simplifiedwp_links',
			esc_html__( 'Import/Export', 'simplified-links' ),
			esc_html__( 'Import/Export', 'simplified-links' ),
			'manage_options',
			'simplified_links_import_export',
			[ $this, 'import_export_page' ],
			5
		);
		 
		add_submenu_page(
			'edit.php?post_type=simplifiedwp_links',
			esc_html__( 'More Plugins', 'simplified-links' ),
			esc_html__( 'More Plugins', 'simplified-links' ),
			'manage_options',
			'simplified_links_more_plugins',
			[ $this, 'more_plugins_page' ],
			5
		);
	}

	/**
	 * Import & Export Page for Simplified Links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function import_export_page() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		} ?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="migrate-content white-bg rounded shadow">
				<?php 
				$migration_support_plugins = Helpers::add_plugin_migration_support();
				$class = '';
				$class_applied = false;
				if( is_array( $migration_support_plugins ) && count( $migration_support_plugins ) > 0 )  {
					foreach ( $migration_support_plugins as $active_plugin ) {
						if ( is_plugin_active( $active_plugin['path'] ) ) {
							if ( !$class_applied ) {
								echo '<h2>' . esc_html__( 'Import', 'simplified-links' ) . '</h2>';
								$class = "simplified-horizontal-line";
								$class_applied = true; // Set the flag to true once the class is applied
								$total_posts = Database::total_posts();
							}
							$plugin_name = esc_html($active_plugin['name']);
						?>
						<h2> <?php esc_html_e( 'Migrate From '. $plugin_name, 'simplified-links' ); ?> </h2>
						<p><?php echo wp_kses_post( sprintf( __( 'This tool lets you migrate <strong>%s</strong> affiliate links of %s Plugin into Simplified Links Plugin.', 'simplified-links' ), $total_posts, $plugin_name ) ); ?></p>
						<p><?php esc_html_e( 'This will transfer and remove the link from your ' . $plugin_name . ' plugin into Simplified Links Plugin.', 'simplified-links' ); ?> </p>
						<div class="progress mt-3 mb-3 sl-hidden" id="progress" >
							<div class="progress-bar progress-bar-striped progress-bar-animated green-bg" role="progressbar" id="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
						</div>
						<p class="js-message"></p>
	
						<p class="submit">
							<button name="migrate-button" id="migrate-btn" class="button button-primary">
								<?php esc_html_e( 'Migrate', 'simplified-links' ); ?>
							</button>
						</p>
						<div class="errormessage sl-hidden" id="errormessage"></div>
						<?php
						}
					} 
				} ?>
			</div>
			
			<span class="differ <?php echo $class;?>">
			
			<div class="migrate-content white-bg rounded shadow section-2">
				<h2> <?php esc_html_e( 'Export your links', 'simplified-links' ); ?> </h2>
				<p> <?php esc_html_e( 'This tool lets you export a properly formatted CSV file containing a list of all the affiliate links of Simplified Links Plugin .', 'simplified-links' ); ?> </p>
				<form id="export-form" action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
					<?php wp_nonce_field( 'export_data', 'export_nonce' ); ?>
					<input type="hidden" name="action" value="export"/>
					<input type="submit" name="export-submit" id="export-submit" class="button button-primary" value="<?php esc_html_e( 'Export', 'simplified-links' ); ?>" />
				</form>
			</div>

		</div>
		<?php
	}

	/**
	 * More Plugins Page for Simplified Links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function more_plugins_page() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		} ?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		</div>
		<?php
	}

	/**
	 * Register Assets.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_enqueue_style( 'simplified-admin', SIMPLIFIED_LINKS_PLUGIN_URL . 'assets/src/css/admin/simplified-admin.css', '', SIMPLIFIED_LINKS_VERSION );
		wp_enqueue_script( 'simplified-admin', SIMPLIFIED_LINKS_PLUGIN_URL . 'assets/src/js/admin/simplified-admin.js', '', SIMPLIFIED_LINKS_VERSION, true );
	}

	/**
	 * Populate custom columns with data on the simplifiedwp_links admin listing page.
	 *
	 * @param string $column_name The name of the column to display.
	 * @param int $post_id The ID of the current post.
	 * @return void
	 */
	public function simplifiedwp_links_custom_column_values( $column, $post_id ) {
		switch ( $column ) {
			case 'simplified_permalink':
				
				$link = get_the_permalink();
				printf(
					'<button
							type="button"
							id="simplifedbutton"
							class="button js-simplified-link-button"
							aria-label="%1$s"
							data-default-text="Copy URL"
							data-copied-text="Copied!"
							data-url="%2$s">
						<span class="dashicons dashicons-admin-page"></span> <span class="simplified-button-text"> %3$s </span> 
					</button>',
					esc_attr( $link ),
					esc_attr( $link ),
					esc_html__( 'Copy URL', 'simplified-links' )
				);
				break;

			case 'redirect_to':
				
				$redirect_url = get_post_meta( $post_id , 'simplified_redirect_url' , true );
				$allowed_tags = array(
					'a' => array(
						'href' => array(),
						'rel'  => array(),
					),
				);
				echo wp_kses( make_clickable( esc_url( $redirect_url ? $redirect_url : '' ) ), $allowed_tags );
				break;
			
			case 'total_clicks':
				$count_click = get_post_meta( $post_id , 'simplified_redirect_count' , true );
				echo esc_html( $count_click ? $count_click : 0 );
				break;
		}	
	}
	/**
	 * This function is used for display click count to post meta box
	 */
	public function before_preview_changes($post) {
		if ( $post->post_type == 'simplifiedwp_links') { 
			$count = isset( $post->ID ) ? get_post_meta( $post->ID, 'simplified_redirect_count', true ) : 0;
			?>
			
			<div class="simplified-click-count">
				<?php /* translators: %d is the counter of clicks. */
				echo '<p>' . sprintf( esc_html__( 'This URL has been accessed %d times', 'simplified-links' ), esc_attr( $count ) ) . '</p>'; ?>
			</div>
		<?php
		}
	}

	/**
	 * Ajax callback function
	 */
	public function simplified_import_all_links() {
		
		global $wpdb;
		$simplified_db = new Database();
		$simplified_import = new Import();

		$filter_plugin = 'simple-urls';

		$sql = $simplified_db->get_import_urls_query( $filter_plugin );
		
		$all_imports = $sql->posts;
		
		$count = count( $all_imports );
		
		if( $count <= 0 ) {
			update_option( self::OPTION, '0' );
			
			wp_send_json_error(
				array(
					'status' => false,
				)
			);

		} else {
			foreach ( $all_imports as $import_id ) {
				$post = get_post( $import_id );
				if( $post->ID && $post->post_type ) {
					$simplified_import->process_single_link_data_import( $post->ID, $post->post_type );
				}
			}
			
			update_option( self::OPTION, '1' );

			wp_send_json_success(
				array(
					'status' => true,
				)
			);
		}
		wp_die();
	}

	/**
	 * Export csv functionality
	 */
	public function export_csv() {

		// Start the output buffer.
		ob_start();

		// Set PHP headers for CSV output.
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=export_simplified.csv');

		// Create the headers.
		$header_args = array( 'Id', 'Title', 'Date', 'Redirect From', 'Redirect To' );

		$args = array(
			'post_type'      => 'simplifiedwp_links',
			'post_status'    => 'publish',
			'posts_per_page' =>  200,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false
		);
		
		$query = new \WP_Query($args);

		$results = $query->posts;
		
		// Clean up output buffer before writing anything to CSV file.
		ob_end_clean();

		// Create a file pointer with PHP.
		$output = fopen( 'php://output', 'w' );
		
		// Write headers to CSV file.
		fputcsv( $output, $header_args );

		// Loop through the prepared data to output it to CSV file.
		foreach( $results as $post_id ) {
			$post = get_post($post_id);
			$modified_values = array( 
				$post_id,
				$post->post_title,
				$post->post_date,
				get_permalink( $post_id ),
				get_post_meta( $post_id, 'simplified_redirect_url', true )
			);
			fputcsv( $output, $modified_values );
		}

		// Close the file pointer with PHP with the updated output.
		fclose( $output );
		exit;
	}

}
