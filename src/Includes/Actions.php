<?php
/**
 * CleanLinks | Actions.
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

class Actions {
	/**
	 * Access-count collaborator.
	 *
	 * @var AccessCounter
	 */
	private $access_counter;

	/**
	 * Redirect-response collaborator.
	 *
	 * @var Redirector
	 */
	private $redirector;

	/**
	 * Initialize the class.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param AccessCounter|null $access_counter Access-count collaborator.
	 * @param Redirector|null    $redirector     Redirect-response collaborator.
	 *
	 * @return void
	 */
	public function __construct( $access_counter = null, $redirector = null ) {
		$this->access_counter = null === $access_counter ? new AccessCounter() : $access_counter;
		$this->redirector     = null === $redirector ? new Redirector() : $redirector;

		add_action( 'template_redirect', array( $this, 'cleanlink_redirect_and_count' ) );
	}

	/**
	 * Count and redirect function.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cleanlink_redirect_and_count() {
		// Bailout if not a cleanlinks post type
		if ( ! is_singular( 'cleanlinks' ) ) {
			return;
		}

		global $wp_query;
		$post_id = isset( $wp_query->post->ID ) ? $wp_query->post->ID : 0;

		if ( ! $post_id ) {
			return;
		}

		if ( ! $this->is_published_cleanlink( $post_id ) ) {
			return;
		}

		// Update the access count.
		$count = $this->access_counter->increment( $post_id );

		// Get the redirect URL.
		$redirect = $this->redirector->get_redirect_url( $post_id, $count );

		// Perform the redirect.
		$this->redirector->perform_redirect( $redirect, $post_id );
		exit;
	}

	/**
	 * Check whether a post is a published cleanlink.
	 *
	 * @since 1.1.0
	 * @access private
	 *
	 * @param int $post_id The post ID.
	 * @return bool True when the post is a published cleanlink.
	 */
	private function is_published_cleanlink( $post_id ) {
		return 'cleanlinks' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id );
	}

}
