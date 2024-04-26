<?php
/**
 * Simplified Links | Admin Filters.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Admin;

/**
* Bailout, if accessed directly.
*/
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
		add_filter( 'admin_footer_text', array( $this, 'add_admin_footer_text' ), 100, 1 );
		add_filter( 'plugin_action_links', array( $this, 'add_plugin_action_links' ), 10, 2 );
		add_filter( 'manage_edit-simplifiedwp_links_columns', array( $this, 'add_custom_columns_to_simplifiedwp_links' ) );
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

		if ( isset( $current_screen->post_type ) && $current_screen->post_type === 'simplifiedwp_links' ) {

			return sprintf(
				/* translators: %s: Link to 5 star rating */
				__( 'If you like <strong>Simplified Links</strong> please leave us a %s rating. It takes a minute and helps a lot. Thanks in advance!', 'simplified-links' ),
				'<a href="https://wordpress.org/support/view/plugin-reviews/simplified-links?filter=5#postform" target="_blank" class="simplified-links-rating-link" data-rated="' . esc_attr__( 'Thanks :)', 'simplified-links' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
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

	/**
	 * Add custom columns to the simplifiedwp_links admin listing page.
	 *
	 * @param array $columns An array of column names.
	 * @return array Modified array of column names.
	 */
	public function add_custom_columns_to_simplifiedwp_links( $columns ) {

		$columns = array(
			'cb'                   => '<input type="checkbox" />',
			'title'                => esc_html__( 'Title', 'simplified-links' ),
			'simplified_permalink' => esc_html__( 'Permalink', 'simplified-links' ),
			'redirect_to'          => esc_html__( 'Redirect To', 'simplified-links' ),
			'total_clicks'         => esc_html__( 'Total Clicks', 'simplified-links' ),
			'date'                 => esc_html__( 'Date', 'simplified-links' ),
		);

		return $columns;
	}
}
