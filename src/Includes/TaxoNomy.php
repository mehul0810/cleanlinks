<?php
/**
 * CleanLinks | Taxonomy.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

namespace MG\CleanLinks\Includes;

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
			'name'                       => _x( 'Groups', 'taxonomy general name', 'cleanlinks' ),
			'singular_name'              => _x( 'Group', 'taxonomy singular name', 'cleanlinks' ),
			'search_items'               => __( 'Search Groups', 'cleanlinks' ),
			'popular_items'              => __( 'Popular Groups', 'cleanlinks' ),
			'all_items'                  => __( 'All Groups', 'cleanlinks' ),
			'edit_item'                  => __( 'Edit Group', 'cleanlinks' ),
			'update_item'                => __( 'Update Group', 'cleanlinks' ),
			'add_new_item'               => __( 'Add New Group', 'cleanlinks' ),
			'new_item_name'              => __( 'New Group Name', 'cleanlinks' ),
			'separate_items_with_commas' => __( 'Separate groups with commas', 'cleanlinks' ),
			'add_or_remove_items'        => __( 'Add or remove groups', 'cleanlinks' ),
			'choose_from_most_used'      => __( 'Choose from the most used groups', 'cleanlinks' ),
			'not_found'                  => __( 'No groups found', 'cleanlinks' ),
			'menu_name'                  => __( 'Groups', 'cleanlinks' ),
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
			'rewrite'            => array( 'slug' => 'cleanlinks_groups' ),
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
		register_taxonomy( 'cleanlinks_groups', 'clean_links', $this->get_args() );
	}
}
