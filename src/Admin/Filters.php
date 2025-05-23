<?php
/**
 * CleanLinks | Admin Filters.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.0.0
 */

namespace MG\CleanLinks\Admin;

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
		add_filter( 'manage_edit-cleanlinks_columns', array( $this, 'add_custom_columns_to_cleanlinks' ) );
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

		if ( isset( $current_screen->post_type ) && $current_screen->post_type === 'cleanlinks' ) {

			return sprintf(
				/* translators: %s: Link to 5 star rating */
				__( 'If you like <strong>CleanLinks</strong> please leave us a %s rating. It takes a minute and helps a lot. Thanks in advance!', 'cleanlinks' ),
				'<a href="https://wordpress.org/support/view/plugin-reviews/cleanlinks?filter=5#postform" target="_blank" class="cleanlinks-rating-link" data-rated="' . esc_attr__( 'Thanks :)', 'cleanlinks' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
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
		if ( $file === CLEANLINKS_PLUGIN_BASENAME ) {
			$manage_link = [
				'manage_links' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'edit.php?post_type=cleanlinks' ) ),
					esc_html__( 'Manage Links', 'cleanlinks' )
				),
			];
			// Prepend the manage link before other links (like Deactivate)
			$links = array_merge( $manage_link, $links );
		}

		return $links;
	}

	/**
	 * Add custom columns to the cleanlinks admin listing page.
	 *
	 * @param array $columns An array of column names.
	 * @return array Modified array of column names.
	 */
	public function add_custom_columns_to_cleanlinks( $columns ) {

		$columns = array(
			'cb'                   => '<input type="checkbox" />',
			'title'                => esc_html__( 'Title', 'cleanlinks' ),
			'cleanlink_permalink' => esc_html__( 'Permalink', 'cleanlinks' ),
			'redirect_to'          => esc_html__( 'Redirect To', 'cleanlinks' ),
			'total_clicks'         => esc_html__( 'Total Clicks', 'cleanlinks' ),
			'date'                 => esc_html__( 'Date', 'cleanlinks' ),
		);

		return $columns;
	}
}
