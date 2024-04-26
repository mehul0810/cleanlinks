<?php
/**
 * Simplified Links | Taxonomy.
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

class TaxoNomy {
	/**
	 * Initiate Taxonomy.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_taxo_nomy' ) );
	}

	/**
	 * Get the labels for the taxonomy.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function get_labels() {
		return array(
			'name'                       => _x( 'Groups', 'taxonomy general name', 'simplified-links' ),
			'singular_name'              => _x( 'Group', 'taxonomy singular name', 'simplified-links' ),
			'search_items'               => __( 'Search Groups', 'simplified-links' ),
			'popular_items'              => __( 'Popular Groups', 'simplified-links' ),
			'all_items'                  => __( 'All Groups', 'simplified-links' ),
			'edit_item'                  => __( 'Edit Group', 'simplified-links' ),
			'update_item'                => __( 'Update Group', 'simplified-links' ),
			'add_new_item'               => __( 'Add New Group', 'simplified-links' ),
			'new_item_name'              => __( 'New Group Name', 'simplified-links' ),
			'separate_items_with_commas' => __( 'Separate groups with commas', 'simplified-links' ),
			'add_or_remove_items'        => __( 'Add or remove groups', 'simplified-links' ),
			'choose_from_most_used'      => __( 'Choose from the most used groups', 'simplified-links' ),
			'not_found'                  => __( 'No groups found', 'simplified-links' ),
			'menu_name'                  => __( 'Groups', 'simplified-links' ),
		);
	}

	/**
	 * Get the arguments for the taxonomy.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function get_args() {
		return array(
			'hierarchical'       => false, // Set to true if you want hierarchical groups like categories
			'labels'             => $this->get_labels(),
			'public'             => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_tagcloud'      => true,
			'show_in_quick_edit' => true,
			'show_admin_column'  => true,
			'rewrite'            => array( 'slug' => 'simplifiedwp_groups' ),
		);
	}

	/**
	 * Register taxonomy.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_taxo_nomy() {
		register_taxonomy( 'simplifiedwp_groups', 'simplifiedwp_links', $this->get_args() );
	}
}
