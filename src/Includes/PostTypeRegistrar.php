<?php
/**
 * CleanLinks | Post Type Registrar.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.1.1
 */

namespace MG\CleanLinks\Includes;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the CleanLinks post type.
 *
 * @since 1.1.1
 */
class PostTypeRegistrar {
	/**
	 * Callback used by WordPress to add the post type metabox.
	 *
	 * @var callable
	 */
	private $metabox_callback;

	/**
	 * Set up the registrar.
	 *
	 * @since 1.1.1
	 *
	 * @param callable $metabox_callback Metabox registration callback.
	 */
	public function __construct( $metabox_callback ) {
		$this->metabox_callback = $metabox_callback;
	}

	/**
	 * Get the labels for the post type.
	 *
	 * @since 1.1.1
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
	 * @since 1.1.1
	 *
	 * @return array
	 */
	public function get_args() {
		$rewrite_slug_default = 'recommends';
		$rewrite_slug         = apply_filters( 'cleanlink_urls_slug', $rewrite_slug_default );
		$rewrite_slug         = sanitize_title( $rewrite_slug, $rewrite_slug_default );

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
			'register_meta_box_cb' => $this->metabox_callback,
			'supports'             => array( 'title' ),
			'can_export'           => true,
		);
	}

	/**
	 * Register the CleanLinks post type.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function register() {
		register_post_type( 'cleanlinks', $this->get_args() );
	}
}
