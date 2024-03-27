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
		add_filter( 'admin_footer_text', [ $this, 'add_admin_footer_text' ] );
		add_filter( 'plugin_action_links', [ $this, 'add_plugin_action_links' ], 10, 2 );
	}

	/**
	 * Add rating links to the admin dashboard.
	 *
	 * @param string $footer_text The existing footer text.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function add_admin_footer_text( $footer_text ) {
		$current_screen = get_current_screen();
		
		if ( true === stristr( $current_screen->base, 'simplified-links' ) ) {
			return sprintf(
				/* translators: %s: Link to 5 star rating */
				__( 'If you like <strong>Simplified Links</strong> please leave us a %s rating. It takes a minute and helps a lot. Thanks in advance!', 'simplified-links' ),
				'<a href="https://wordpress.org/support/view/plugin-reviews/simplified-links?filter=5#postform" target="_blank" class="simplified-links-rating-link" style="text-decoration:none;" data-rated="' . esc_attr__( 'Thanks :)', 'simplified-links' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}

		return $footer_text;
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
				esc_url( admin_url( 'edit.php?post_type=simplifiedwp_links' ) ),
				esc_html__( 'Manage Links', 'simplified-links' )
			);
		}

		return $links;
	}
}
