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
	 */
	public function cleanlink_redirect_and_count() {

		if ( ! is_singular( 'clean_links' ) ) {
			return;
		}

		global $wp_query;

		// Update the count.
		$count = isset( $wp_query->post->cleanlink_redirect_count ) ? (int) $wp_query->post->cleanlink_redirect_count : 0;
		update_post_meta( $wp_query->post->ID, 'cleanlink_redirect_count', $count + 1 );

		// Handle the redirect.
		$redirect = isset( $wp_query->post->ID ) ? get_post_meta( $wp_query->post->ID, 'cleanlink_redirect_url', true ) : '';

		/**
		 * Filter the redirect URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string  $redirect The URL to redirect to.
		 * @param int  $var The current click count.
		 */
		$redirect = apply_filters( 'cleanlinks_urls_redirect_url', $redirect, $count );

		/**
		 * Action hook that fires before the redirect.
		 *
		 * @since 1.0.0
		 *
		 * @param string  $redirect The URL to redirect to.
		 * @param int  $var The current click count.
		 */
		do_action( 'cleanlinks_urls_redirect', $redirect, $count );

		if ( ! empty( $redirect ) ) {
			// Check if nofollow is enabled
			$nofollow = get_post_meta( $wp_query->post->ID, 'cleanlink_redirect_nofollow', true );
			
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
