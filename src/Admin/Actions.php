<?php
/**
 * Simplified Links | Admin Actions.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Admin;

// Bailout, if accessed directly.
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
		add_action( 'admin_menu', [ $this, 'add_admin_pages' ] );
		add_action( 'manage_simplifiedwp_links_posts_custom_column', [ $this, 'simplifiedwp_links_custom_column_values' ], 10, 2 );
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
		/* add_submenu_page(
			'edit.php?post_type=simplifiedwp_links',
			esc_html__( 'Dashboard', 'simplified-links' ),
			esc_html__( 'Dashboard', 'simplified-links' ),
			'manage_options',
			'simplified_links_dashboard',
			[ $this, 'dashboard_page' ],
			0
		); */

		add_submenu_page(
			'edit.php?post_type=simplifiedwp_links',
			esc_html__( 'Reports', 'simplified-links' ),
			esc_html__( 'Reports', 'simplified-links' ),
			'manage_options',
			'simplified_links_reports',
			[ $this, 'reports_page' ],
			5
		);

		add_submenu_page(
			'edit.php?post_type=simplifiedwp_links',
			esc_html__( 'Support', 'simplified-links' ),
			esc_html__( 'Support', 'simplified-links' ),
			'manage_options',
			'simplified_links_support',
			[ $this, 'support_page' ],
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
	 * Dashboard Page for Simplified Links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function dashboard_page() {
		return 'Dashboard Page';
	}

	/**
	 * Reports Page for Simplified Links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function reports_page() {
		return 'Reports Page';
	}

	/**
	 * Support Page for Simplified Links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function support_page() {
		return 'Support Page';
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
		return 'More Plugins Page';
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
			case 'redirect_url':
				
				$redirect_url = get_post_meta( $post_id , 'simplified_redirect_url' , true );
				$allowed_tags = array(
					'a' => array(
						'href' => array(),
						'rel'  => array(),
					),
				);
				echo wp_kses( make_clickable( esc_url( $redirect_url ? $redirect_url : '' ) ), $allowed_tags );
				break;
			
			case 'clicks_count':
				$count_click = get_post_meta( $post_id , 'simplified_redirect_count' , true );
				echo esc_html( $count_click ? $count_click : 0 );
				break;
		}	
	}
}
