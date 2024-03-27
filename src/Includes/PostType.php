<?php
/**
 * Simplified Links | Post Type.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Includes;

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
		add_action( 'init', [ $this, 'register_post_type' ] );
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
		return array(
			'labels'             => $this->get_labels(),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'simplifiedwp_links' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-admin-links',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
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
	}
}
