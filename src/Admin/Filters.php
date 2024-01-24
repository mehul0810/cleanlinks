<?php
/**
 * Simplified Links | Admin Filters.
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

class Filters {
	/**
	 * Initialize the class.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'plugin_action_links', [ $this, 'add_plugin_action_links' ], 10, 2 );
	}

	/**
	 * Add Plugin Actions Links.
	 *
	 * @param array  $links List of existing plugin action links.
	 * @param string $file  Plugin Base File.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array List of modified plugin action links.
	 */
	public function add_plugin_action_links( $links, $file ) {
		if ( $file === SIMPLIFIED_LINKS_PLUGIN_BASENAME ) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'edit.php?post_type=simplified-links' ) ),
				esc_html__( 'Manage Links', 'simplified-links' )
			);
		}

		return $links;
	}
}
