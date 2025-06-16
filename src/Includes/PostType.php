<?php
/**
 * CleanLinks | Post Type.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

namespace MG\CleanLinks\Includes;

use MG\CleanLinks\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostType {
	/**
	 * Initiate post type.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'save_post', array( $this, 'save_link_meta' ), 10, 2 );
	}

	/**
	 * Get the labels for the post type.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function get_labels() {
		return array(
			'name'                  => _x( 'CleanLinks', 'Post type general name', 'cleanlinks' ),
			'singular_name'         => _x( 'CleanLink', 'Post type singular name', 'cleanlinks' ),
			'menu_name'             => _x( 'CleanLinks', 'Admin Menu text', 'cleanlinks' ),
			'name_admin_bar'        => _x( 'Link', 'Add New on Toolbar', 'cleanlinks' ),
			'add_new'               => __( 'Add New Link', 'cleanlinks' ),
			'add_new_item'          => __( 'Add New Link', 'cleanlinks' ),
			'new_item'              => __( 'New CleanLink', 'cleanlinks' ),
			'edit_item'             => __( 'Edit CleanLink', 'cleanlinks' ),
			'view_item'             => __( 'View CleanLink', 'cleanlinks' ),
			'all_items'             => __( 'All Links', 'cleanlinks' ),
			'search_items'          => __( 'Search links', 'cleanlinks' ),
			'parent_item_colon'     => __( 'Parent links:', 'cleanlinks' ),
			'not_found'             => __( 'No link found.', 'cleanlinks' ),
			'not_found_in_trash'    => __( 'No links found in Trash.', 'cleanlinks' ),
			'archives'              => _x( 'CleanLink archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'cleanlinks' ),
			'insert_into_item'      => _x( 'Insert into Link', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'cleanlinks' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this Link', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'cleanlinks' ),
			'filter_items_list'     => _x( 'Filter Links list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'cleanlinks' ),
			'items_list_navigation' => _x( 'Links list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'cleanlinks' ),
			'items_list'            => _x( 'Links list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'cleanlinks' ),
		);
	}

	/**
	 * Get the arguments for the post type.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function get_args() {

		$rewrite_slug_default = 'recommends';

		$rewrite_slug = apply_filters( 'cleanlink_urls_slug', $rewrite_slug_default );

		$rewrite_slug = sanitize_title( $rewrite_slug, $rewrite_slug_default );

		return array(
			'labels'               => $this->get_labels(),
			'public'               => true,
			'publicly_queryable'   => true,
			'show_ui'              => true,
			'show_in_menu'         => true,
			'query_var'            => true,
			'rewrite'              => array( 'slug' => $rewrite_slug, 'with_front' => false ),
			'capability_type'      => 'post',
			'has_archive'          => false,
			'hierarchical'         => false,
			'menu_position'        => null,
			'show_in_rest'         => true,
			'menu_icon'            => 'dashicons-admin-links',
			'register_meta_box_cb' => array( $this, 'action_add_url_metabox' ),
			'supports'             => array( 'title' ),
			'can_export'           => true,
		);
	}

	/**
	 * Register Post Type.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type( 'cleanlinks', $this->get_args() );
	}

	/**
	 * Verify nonce for post update
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $nonce_value The nonce value from the form.
	 * @param string $nonce_action The nonce action to verify against.
	 *
	 * @return bool True if nonce is valid, false otherwise.
	 */
	private function verify_nonce( $nonce_value, $nonce_action ) {
		return ! empty( $nonce_value ) && wp_verify_nonce( $nonce_value, $nonce_action );
	}

	/**
	 * Saves meta info for clean links
	 *
	 * @since 1.0
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function save_link_meta( $post_id, $post ) {
		// Bailout, if doing autosave.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// Bailout, if doing ajax.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		// Bailout, if doing cron.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		// Bailout, if the post type is not `cleanlinks`.
		if ( 'cleanlinks' !== $post->post_type ) {
			return;
		}

		// Bailout, if the user doesn't have permissions to edit post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check and verify nonce properly
		if (
		! isset( $_POST['cleanlink_redirect_nonce'] ) ||
		! $this->verify_nonce( wp_unslash( $_POST['cleanlink_redirect_nonce'] ), 'cleanlink-save-redirect-meta' )
		) {
			return;
		}

		// Sanitize post data and save redirect URL
		$this->save_redirect_url( $post_id );
	}

	/**
	 * Save the redirect URL for a clean link
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	private function save_redirect_url( $post_id ) {
		// Nonce is already verified before this method is called.
		
		// Sanitize post data.
		$post_data = Helpers::clean( $_POST );

		// Process the redirect URL
		if ( ! empty( $post_data['cleanlink_redirect_url'] ) ) {
			// Validate and sanitize URL
			$valid_url = Helpers::validate_url( $post_data['cleanlink_redirect_url'] );

			if ( $valid_url ) {

				// Store the sanitized URL in post meta
				update_post_meta( $post_id, 'cleanlink_redirect_url', $valid_url );

				// Save nofollow setting
				$nofollow = isset( $post_data['cleanlink_redirect_nofollow'] ) ? '1' : '0';
				update_post_meta( $post_id, 'cleanlink_redirect_nofollow', $nofollow );
			} else {
				delete_post_meta( $post_id, 'cleanlink_redirect_url' );
				delete_post_meta( $post_id, 'cleanlink_redirect_nofollow' );
			}
		}
	}

	/**
	 * Registers meta boxes for clean link post
	 *
	 * @since 1.0
	 * @return void
	 */
	public function action_add_url_metabox() {
		add_meta_box( 'cleanlink_redirection_settings', esc_html__( 'Redirect Link Settings', 'cleanlinks' ), array( $this, 'link_metabox' ), 'cleanlinks', 'normal', 'core' );
	}

	/**
	 * Echoes HTML for link meta box
	 *
	 * @since 1.0
	 * @param WP_Post $post Post object
	 * @return void
	 */
	public function link_metabox( $post ) {
		// Add nonce field
		wp_nonce_field( 'cleanlink-save-redirect-meta', 'cleanlink_redirect_nonce' );

		// Get the redirect URL
		$url = get_post_meta( $post->ID, 'cleanlink_redirect_url', true );
		// Get the nofollow value
		$nofollow = get_post_meta( $post->ID, 'cleanlink_redirect_nofollow', true );
		// Display the redirect URL field
		$this->render_redirect_url_field( $url, $nofollow );

		// Display access count
		$this->render_access_count( $post->ID );
	}

	/**
	 * Render the redirect URL field
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $url The current redirect URL
	 * @param string $nofollow The current nofollow value
	 * @return void
	 */
	private function render_redirect_url_field( $url, $nofollow = '0' ) {
		?>
		<p>
			<label for="cleanlink_redirect_url"><strong><?php esc_html_e( 'Destination URL:', 'cleanlinks' ); ?></strong>
			<input placeholder="<?php esc_attr_e( 'Enter the full destination URL (e.g., https://example.com)', 'cleanlinks' ); ?>" class="widefat" type="url" name="cleanlink_redirect_url" id="cleanlink_redirect_url" value="<?php echo esc_attr( $url ); ?>" />
			</label>
			<span class="description">
				<?php esc_html_e( 'Visitors will be redirected to this URL when they access your link.', 'cleanlinks' ); ?>
			</span>
		</p>

		<p>
			<label for="cleanlink_redirect_nofollow">
				<input type="checkbox" name="cleanlink_redirect_nofollow" id="cleanlink_redirect_nofollow" value="1" <?php checked( $nofollow, '1' ); ?> />
				<?php esc_html_e( 'Add nofollow to this redirect', 'cleanlinks' ); ?>
			</label>
			<span class="description"><?php esc_html_e( 'Check this option to prevent search engines from following this redirect.', 'cleanlinks' ); ?> </span>
		</p>
		<?php
	}

	/**
	 * Render the access count information
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param int $post_id The post ID
	 * @return void
	 */
	private function render_access_count( $post_id ) {
		$count = Helpers::get_total_access_count( $post_id );
		?>
		<div class="cleanlinks--access-count">
			<span class="dashicons dashicons-chart-bar"></span>
			<?php
			// Translators: %d is the number of times the link has been visited.
			printf(
				esc_html__( 'This link has been visited %d times', 'cleanlinks' ),
				esc_html( $count )
			);
			?>
		</div>
		<?php
	}
}
