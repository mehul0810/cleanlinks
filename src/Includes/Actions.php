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
	 * Initialize the class.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
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

		// Update the access count
		$this->update_access_count( $post_id );

		// Get the redirect URL
		$redirect = $this->get_redirect_url( $post_id );

		// Perform the redirect
		$this->perform_redirect( $redirect, $post_id );
	}

	/**
	 * Update the access count for a clean link
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param int $post_id The post ID
	 * @return int The new count value
	 */
	private function update_access_count( $post_id ) {
		if ( ! $this->is_published_cleanlink( $post_id ) ) {
			return (int) get_post_meta( $post_id, 'cleanlink_redirect_count', true );
		}

		$count = (int) get_post_meta( $post_id, 'cleanlink_redirect_count', true );
		$new_count = $count + 1;

		update_post_meta( $post_id, 'cleanlink_redirect_count', $new_count );

		return $new_count;
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

	/**
	 * Get the redirect URL for a clean link
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param int $post_id The post ID
	 * @return string The redirect URL
	 */
	private function get_redirect_url( $post_id ) {
		$redirect = get_post_meta( $post_id, 'cleanlink_redirect_url', true );
		$count = (int) get_post_meta( $post_id, 'cleanlink_redirect_count', true );

		/**
		 * Filter the redirect URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $redirect The URL to redirect to.
		 * @param int    $count    The current click count.
		 */
		$redirect = apply_filters( 'cleanlinks_urls_redirect_url', $redirect, $count );

		/**
		 * Action hook that fires before the redirect.
		 *
		 * @since 1.0.0
		 *
		 * @param string $redirect The URL to redirect to.
		 * @param int    $count    The current click count.
		 */
		do_action( 'cleanlinks_urls_redirect', $redirect, $count );

		return $redirect;
	}

	/**
	 * Perform the redirect to the specified URL
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $redirect The URL to redirect to
	 * @param int    $post_id  The post ID
	 * @return void
	 */
	private function perform_redirect( $redirect, $post_id ) {
		if ( ! empty( $redirect ) ) {
			// Check if nofollow is enabled
			$nofollow = get_post_meta( $post_id, 'cleanlink_redirect_nofollow', true );

			if ( '1' === $nofollow ) {
				// Add nofollow meta tag before redirect
				echo '<meta name="robots" content="nofollow">';
				// Ensure the output is sent before the redirect
				flush();
			}

			wp_redirect( esc_url_raw( $redirect ), 301 );
			exit;
		} else {
			wp_safe_redirect( home_url(), 302 );
			exit;
		}
	}
}
