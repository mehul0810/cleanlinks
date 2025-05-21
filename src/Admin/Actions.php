<?php
/**
 * CleanLinks | Admin Actions.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

namespace MG\CleanLinks\Admin;

use MG\CleanLinks\includes\Helpers;

/**
 *  Bailout, if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions {

	/**
	 * Initialize the class.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_script' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );
		add_action( 'manage_clean_links_posts_custom_column', array( $this, 'register_custom_columns' ), 10, 2 );
		add_action( 'post_submitbox_misc_actions', array( $this, 'before_preview_changes' ) );		
	}

	/**
	 * Register Admin Pages
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_admin_pages() {

		// Export.
		add_submenu_page(
			'edit.php?post_type=clean_links',
			esc_html__( 'Export', 'cleanlinks' ),
			esc_html__( 'Export', 'cleanlinks' ),
			'manage_options',
			'cleanlinks_export',
			array( $this, 'render_export_page' ),
			5
		);

		// More Plugins.
		add_submenu_page(
			'edit.php?post_type=clean_links',
			esc_html__( 'More Plugins', 'cleanlinks' ),
			esc_html__( 'More Plugins', 'cleanlinks' ),
			'manage_options',
			'cleanlinks_more_plugins',
			array( $this, 'render_more_plugins_page' ),
			5
		);
	}

	/**
	 * Export Page for CleanLinks.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function render_export_page() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			// Render Export UI.
			( new Export() )->render_ui();
			?>

		</div>
		<?php
	}

	/**
	 * More Plugins Page for CleanLinks.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function render_more_plugins_page() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
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
	 * @param string $hook The current admin page.
	 * 
	 * @return void
	 */
	public function register_assets($hook) {

    	//load only on custom post type 'clean_links' edit screens
		if ( 'edit.php' !== $hook ) {
			return;
		}
		
		$screen = get_current_screen();
		if ( ! isset( $screen->post_type ) || 'clean_links' !== $screen->post_type ) {
			return;
		}
		// Properly register the script first
		wp_register_script(
			'cleanlink-admin',
			CLEAN_LINKS_PLUGIN_URL . 'assets/js/admin/main.js',
			array(), // dependencies
			CLEAN_LINKS_VERSION,
			true // in footer
		);

		// Enqueue it
		wp_enqueue_script( 'cleanlink-admin' );
	}

	/**
	 * Register this filter globally
	 * Add the type="module" attribute to the script
	 */
	 public function add_module_type_to_script( $tag, $handle, $src ) {
		if ( 'cleanlink-admin' === $handle ) {
			return '<script type="module" src="' . esc_url( $src ) . '"></script>';
		}
		return $tag;
	}

	/**
	 * Populate custom columns with data on the clean_links admin listing page.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $column_name The name of the column to display.
	 * @param int $post_id The ID of the current post.
	 *
	 * @return mixed
	 */
	public function register_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'cleanlink_permalink':
				$default_text = esc_html__( 'Copy URL', 'cleanlinks' );
				$permalink    = get_the_permalink( $post_id );
				?>
				<button
					type="button"
					class="button cleanlinks--copy-button"
					aria-label="<?php echo esc_attr($permalink); ?>"
					data-default-text="<?php echo esc_attr( $default_text ); ?>"
					data-copied-text="<?php echo esc_attr__( 'Copied!', 'cleanlinks' ); ?>"
					data-url="<?php echo esc_url($permalink); ?>"
				>
					<span class="dashicons dashicons-admin-page"></span>
					<span class="cleanlinks--copy-button-text"><?php echo esc_html( $default_text ); ?></span>
				</button>
				<?php
				break;

			case 'redirect_to':
				$redirect_url = get_post_meta( $post_id, 'cleanlink_redirect_url', true );
				$allowed_tags = array(
					'a' => array(
						'href' => array(),
						'rel'  => array(),
					),
				);
				echo wp_kses( make_clickable( esc_url( $redirect_url ? $redirect_url : '' ) ), $allowed_tags );
				break;

			case 'total_clicks':
				$post_status = get_post_status ( $post_id );
				$total_clicks = ( $post_status != 'publish' ) ? 0 : Helpers::get_total_access_count( $post_id );

				// Display Total Clicks.
				echo absint( $total_clicks );
				break;
		}
	}

	/**
	 * Display link access count in the post submit box.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function before_preview_changes( $post ) {
		// Bailout, if the post type is not clean_links.
		if ( 'clean_links' !== $post->post_type ) {
			return;
		}

		// Bailout, if the post is in draft or auto-draft.
		if ( in_array( $post->post_status, [ 'draft', 'auto-draft' ], true ) ) {
			return;
		}

		$count = Helpers::get_total_access_count( $post->ID );
		?>
		<div class="misc-pub-section cleanlinks--access-count">
			<span class="dashicons dashicons-external"></span>
			<?php esc_html_e( 'Viewed:', 'cleanlinks' ); ?>
			<span class="cleanlinks--view-times">
				<?php
				/* translators: 1. Access Count */
				echo sprintf(
					'%1$s times',
					absint( $count )
				);
				?>
			</span>
		</div>
		<?php
	}

}
