<?php
/**
 * Simplified Links | Post Type.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Includes;

use SimplifiedWP\Links\Includes\Helpers;

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
			'name'                  => _x( 'Simplified Links', 'Post type general name', 'simplified-links' ),
			'singular_name'         => _x( 'Simplified Link', 'Post type singular name', 'simplified-links' ),
			'menu_name'             => _x( 'Simplified Links', 'Admin Menu text', 'simplified-links' ),
			'name_admin_bar'        => _x( 'Simplified Link', 'Add New on Toolbar', 'simplified-links' ),
			'add_new'               => __( 'Add New Link', 'simplified-links' ),
			'add_new_item'          => __( 'Add New Link', 'simplified-links' ),
			'new_item'              => __( 'New Simplified Link', 'simplified-links' ),
			'edit_item'             => __( 'Edit Simplified Link', 'simplified-links' ),
			'view_item'             => __( 'View Simplified Link', 'simplified-links' ),
			'all_items'             => __( 'All Links', 'simplified-links' ),
			'search_items'          => __( 'Search Simplified Links', 'simplified-links' ),
			'parent_item_colon'     => __( 'Parent Simplified Links:', 'simplified-links' ),
			'not_found'             => __( 'No Simplified Links found.', 'simplified-links' ),
			'not_found_in_trash'    => __( 'No Simplified Links found in Trash.', 'simplified-links' ),
			'featured_image'        => _x( 'Simplified Link Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'simplified-links' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'simplified-links' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'simplified-links' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'simplified-links' ),
			'archives'              => _x( 'Simplified Link archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'simplified-links' ),
			'insert_into_item'      => _x( 'Insert into Simplified Link', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'simplified-links' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this Simplified Link', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'simplified-links' ),
			'filter_items_list'     => _x( 'Filter Simplified Links list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'simplified-links' ),
			'items_list_navigation' => _x( 'Simplified Links list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'simplified-links' ),
			'items_list'            => _x( 'Simplified Links list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'simplified-links' ),
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

		$rewrite_slug = apply_filters( 'simplified_urls_slug', $rewrite_slug_default );

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
		register_post_type( 'simplifiedwp_links', $this->get_args() );

		// https://developer.wordpress.org/reference/functions/register_post_type/#flushing-rewrite-on-activation
	}

	/**
	 * Saves meta info for simplifiedwp links
	 *
	 * @since 1.0
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function save_link_meta( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		// Sanitize post data.
		$_post = Helpers::clean( $_POST );

		// Update post meta for simplifiedwp links
		if (
			! empty( $_post['simplified_redirect_nonce'] ) &&
			wp_verify_nonce( $_post['simplified_redirect_nonce'], 'simplified-save-redirect-meta' ) &&
			current_user_can( 'edit_post', $post_id ) &&
			'simplifiedwp_links' === $post->post_type
		) {

			if ( ! empty( $_post['simplified_redirect_url'] ) ) {

				// Remove all illegal characters from a url
				$url = filter_var( $_post['simplified_redirect_url'], FILTER_SANITIZE_URL );

				// Validate url
				if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
					update_post_meta( $post_id, 'simplified_redirect_url', esc_url( $url ) );
				}
			} else {
				delete_post_meta( $post_id, 'simplified_redirect_url' );
			}
		}
	}

	/**
	 * Registers meta boxes for simplifiedwp links post
	 *
	 * @since 1.0
	 * @return void
	 */
	public function action_add_url_metabox() {
		add_meta_box( 'simplifiedwp_redirection_settings', esc_html__( 'Redirection Settings', 'simplified-links' ), array( $this, 'link_metabox' ), 'simplifiedwp_links', 'normal', 'core' );
	}

	/**
	 * Echoes HTML for link meta box
	 *
	 * @since 1.0
	 * @param WP_Post $post Post object
	 * @return void
	 */
	public function link_metabox( $post ) {

		wp_nonce_field( 'simplified-save-redirect-meta', 'simplified_redirect_nonce' );

		$url = get_post_meta( $post->ID, 'simplified_redirect_url', true );
		?>

		<p>
			<label for="simplified_redirect_url"><strong><?php esc_html_e( 'Redirect to:', 'simplified-links' ); ?></strong></label><br />
			<input class="widefat" type="url" name="simplified_redirect_url" id="simplified_redirect_url" value="<?php echo esc_attr( $url ); ?>" />
		</p>
		<p><span class="description"><?php esc_html_e( 'This is the URL that the Redirect Link you create on this page will redirect to when accessed in a web browser.', 'simplified-links' ); ?> </span></p>
		<?php
		$count = isset( $post->ID ) ? get_post_meta( $post->ID, 'simplifiedwp_links_redirect_count', true ) : 0;
		/* translators: %d is the counter of clicks. */
		echo '<p>' . sprintf( esc_html__( 'This URL has been accessed %d times', 'simplified-links' ), esc_attr( $count ) ) . '</p>';
	}
}
