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
		add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );
		add_action( 'manage_simplifiedwp_links_posts_custom_column', array( $this, 'register_custom_columns' ), 10, 2 );
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
			'edit.php?post_type=simplifiedwp_links',
			esc_html__( 'Export', 'simplified-links' ),
			esc_html__( 'Export', 'simplified-links' ),
			'manage_options',
			'simplified_links_export',
			array( $this, 'render_export_page' ),
			5
		);

		// More Plugins.
		add_submenu_page(
			'edit.php?post_type=simplifiedwp_links',
			esc_html__( 'More Plugins', 'simplified-links' ),
			esc_html__( 'More Plugins', 'simplified-links' ),
			'manage_options',
			'simplified_links_more_plugins',
			array( $this, 'render_more_plugins_page' ),
			5
		);
	}

	/**
	 * Export Page for Simplified Links.
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
	 * More Plugins Page for Simplified Links.
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
	 * @return void
	 */
	public function register_assets() {
		wp_enqueue_script( 'simplified-admin', SIMPLIFIED_LINKS_PLUGIN_URL . 'assets/js/admin/main.js', '', SIMPLIFIED_LINKS_VERSION, true );
		
		// Add the type="module" attribute to the script
		add_filter('script_loader_tag', function($tag, $handle, $src) {
			if ( 'simplified-admin' === $handle ) {
				$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
			}
			return $tag;
		}, 10, 3);
	}

	/**
	 * Populate custom columns with data on the simplifiedwp_links admin listing page.
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
			case 'simplified_permalink':
				$default_text = esc_html__( 'Copy URL', 'simplified-links' );
				$permalink    = get_the_permalink( $post_id );
				?>
				<button
					type="button"
					class="button simplified-links--copy-button"
					aria-label="<?php echo $permalink; ?>"
					data-default-text="<?php echo esc_html( $default_text ); ?>"
					data-copied-text="<?php echo esc_html__( 'Copied!', 'simplified-links' ); ?>"
					data-url="<?php echo $permalink; ?>"
				>
					<span class="dashicons dashicons-admin-page"></span>
					<span class="simplified-links--copy-button-text"><?php echo esc_html( $default_text ); ?></span>
				</button>
				<?php
				break;

			case 'redirect_to':
				$redirect_url = get_post_meta( $post_id, 'simplified_redirect_url', true );
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
				echo ( $post_status != 'publish' ) ? '0' : Helpers::get_total_access_count( $post_id );
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
		// Bailout, if the post type is not simplifiedwp_links.
		if ( 'simplifiedwp_links' !== $post->post_type ) {
			return;
		}

		// Bailout, if the post is in draft or auto-draft.
		if ( in_array( $post->post_status, [ 'draft', 'auto-draft' ], true ) ) {
			return;
		}

		$count = Helpers::get_total_access_count( $post->ID );
		?>
		<div class="misc-pub-section simplified-links--access-count">
			<span class="dashicons dashicons-external"></span>
			<?php esc_html_e( 'Viewed:', 'simplified-links' ); ?>
			<span class="simplified-links--view-times">
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
