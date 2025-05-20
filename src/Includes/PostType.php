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
			'name_admin_bar'        => _x( 'CleanLink', 'Add New on Toolbar', 'cleanlinks' ),
			'add_new'               => __( 'Add New Link', 'cleanlinks' ),
			'add_new_item'          => __( 'Add New Link', 'cleanlinks' ),
			'new_item'              => __( 'New CleanLink', 'cleanlinks' ),
			'edit_item'             => __( 'Edit CleanLink', 'cleanlinks' ),
			'view_item'             => __( 'View CleanLink', 'cleanlinks' ),
			'all_items'             => __( 'All Links', 'cleanlinks' ),
			'search_items'          => __( 'Search CleanLinks', 'cleanlinks' ),
			'parent_item_colon'     => __( 'Parent CleanLinks:', 'cleanlinks' ),
			'not_found'             => __( 'No CleanLinks found.', 'cleanlinks' ),
			'not_found_in_trash'    => __( 'No CleanLinks found in Trash.', 'cleanlinks' ),
			'featured_image'        => _x( 'CleanLink Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'cleanlinks' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'cleanlinks' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'cleanlinks' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'cleanlinks' ),
			'archives'              => _x( 'CleanLink archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'cleanlinks' ),
			'insert_into_item'      => _x( 'Insert into CleanLink', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'cleanlinks' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this CleanLink', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'cleanlinks' ),
			'filter_items_list'     => _x( 'Filter CleanLinks list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'cleanlinks' ),
			'items_list_navigation' => _x( 'CleanLinks list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'cleanlinks' ),
			'items_list'            => _x( 'CleanLinks list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'cleanlinks' ),
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
			'rewrite'              => array( 'slug' => $rewrite_slug ),
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
		register_post_type( 'clean_links', $this->get_args() );

		// https://developer.wordpress.org/reference/functions/register_post_type/#flushing-rewrite-on-activation
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

		// Bailout, if the post type is not `clean_links`.
		if ( 'clean_links' !== $post->post_type ) {
			return;
		}

		// Bailout, if the user doesn't have permissions to edit post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Prepare nonce variable.
		$nonce = filter_input( INPUT_POST, 'cleanlink_redirect_nonce', FILTER_UNSAFE_RAW );

		// Bailout, if the nonce is not verified.
		if ( ! wp_verify_nonce( $nonce, 'cleanlink-save-redirect-meta' ) ) {
			return;
		}

		// Sanitize post data.
		$post_data = Helpers::clean( $_POST );

		// Update post meta for cleanlinks
		if (
			! empty( $post_data['cleanlink_redirect_nonce'] ) &&
			wp_verify_nonce( $post_data['cleanlink_redirect_nonce'], 'cleanlink-save-redirect-meta' ) &&
			current_user_can( 'edit_post', $post_id ) &&
			'clean_links' === $post->post_type
		) {
			
			if ( ! empty( $post_data['cleanlink_redirect_url'] ) ) {

				// Remove all illegal characters from a url
				$url = esc_url_raw( trim( $post_data['cleanlink_redirect_url'] ) );

				// Validate url
				if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
					// Store the sanitized URL in post meta
					update_post_meta( $post_id, 'cleanlink_redirect_url', $url );
				}
				
				// Save nofollow setting
				$nofollow = isset( $post_data['cleanlink_redirect_nofollow'] ) ? '1' : '0';
				update_post_meta( $post_id, 'cleanlink_redirect_nofollow', $nofollow );
			} else {
				delete_post_meta( $post_id, 'cleanlink_redirect_url' );
			}
		} else {
			delete_post_meta( $post_id, 'cleanlink_redirect_url' );
		}
	}

	/**
	 * Registers meta boxes for clean link post
	 *
	 * @since 1.0
	 * @return void
	 */
	public function action_add_url_metabox() {
		add_meta_box( 'cleanlink_redirection_settings', esc_html__( 'Redirection Settings', 'cleanlinks' ), array( $this, 'link_metabox' ), 'clean_links', 'normal', 'core' );
	}

	/**
	 * Echoes HTML for link meta box
	 *
	 * @since 1.0
	 * @param WP_Post $post Post object
	 * @return void
	 */
	public function link_metabox( $post ) {

		wp_nonce_field( 'cleanlink-save-redirect-meta', 'cleanlink_redirect_nonce' );

		$url = get_post_meta( $post->ID, 'cleanlink_redirect_url', true );
		$nofollow = get_post_meta( $post->ID, 'cleanlink_redirect_nofollow', true );
		?>

		<p>
			<label for="cleanlink_redirect_url"><strong><?php esc_html_e( 'Redirect to:', 'cleanlinks' ); ?></strong></label><br />
			<input class="widefat" type="url" name="cleanlink_redirect_url" id="cleanlink_redirect_url" value="<?php echo esc_attr( $url ); ?>" />
		</p>
		<p><span class="description"><?php esc_html_e( 'This is the URL that the Redirect Link you create on this page will redirect to when accessed in a web browser.', 'cleanlinks' ); ?> </span></p>
		
		<p>
			<label for="cleanlink_redirect_nofollow">
				<input type="checkbox" name="cleanlink_redirect_nofollow" id="cleanlink_redirect_nofollow" value="1" <?php checked( $nofollow, '1' ); ?> />
				<?php esc_html_e( 'Add nofollow attribute to redirect', 'cleanlinks' ); ?>
			</label>
		</p>
		<p><span class="description"><?php esc_html_e( 'If checked, the nofollow attribute will be added to the redirect to prevent search engines from following the link.', 'cleanlinks' ); ?> </span></p>
		
		<?php
		$count = isset( $post->ID ) ? get_post_meta( $post->ID, 'cleanlink_redirect_count', true ) : 0;
		/* translators: %d is the counter of clicks. */
		echo '<p>' . sprintf( esc_html__( 'This URL has been accessed %d times', 'cleanlinks' ), esc_attr( $count ) ) . '</p>';
	}
}
