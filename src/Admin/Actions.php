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
		add_submenu_page(
			'edit.php?post_type=simplifiedwp_links',
			esc_html__( 'Dashboard', 'simplified-links' ),
			esc_html__( 'Dashboard', 'simplified-links' ),
			'manage_options',
			'simplified_links_dashboard',
			[ $this, 'dashboard_page' ],
			0
		);

		add_submenu_page(
			'edit.php?post_type=simplifiedwp_links',
			esc_html__( 'Settings', 'simplified-links' ),
			esc_html__( 'Settings', 'simplified-links' ),
			'manage_options',
			'simplified_links_settings',
			[ $this, 'settings_page' ],
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
	 * Settings Page for Simplified Links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function settings_page() {
		return 'Settings Page';
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
}
