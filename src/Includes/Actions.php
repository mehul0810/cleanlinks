<?php
/**
 * Simplified Links | Actions.
 *
 * @package WordPress
 * @subpackage Simplified Links
 * @since 1.0.0
 */

namespace SimplifiedWP\Links\Includes;

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
		add_action( 'template_redirect', array( $this, 'simplified_redirect_and_count' ) );
	}

	/**
	 * Count and redirect function.
	 */
	public function simplified_redirect_and_count() {

		if ( ! is_singular( 'simplifiedwp_links' ) ) {
			return;
		}

		global $wp_query;

		// Update the count.
		$count = isset( $wp_query->post->simplifiedwp_links_redirect_count ) ? (int) $wp_query->post->simplifiedwp_links_redirect_count : 0;
		update_post_meta( $wp_query->post->ID, 'simplifiedwp_links_redirect_count', $count + 1 );

		// Handle the redirect.
		$redirect = isset( $wp_query->post->ID ) ? get_post_meta( $wp_query->post->ID, 'simplified_redirect_url', true ) : '';

		/**
		 * Filter the redirect URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string  $redirect The URL to redirect to.
		 * @param int  $var The current click count.
		 */
		$redirect = apply_filters( 'simplified_urls_redirect_url', $redirect, $count );

		/**
		 * Action hook that fires before the redirect.
		 *
		 * @since 1.0.0
		 *
		 * @param string  $redirect The URL to redirect to.
		 * @param int  $var The current click count.
		 */
		do_action( 'simplified_urls_redirect', $redirect, $count );

		if ( ! empty( $redirect ) ) {
			wp_redirect( esc_url_raw( $redirect ), 301 );
			exit;
		} else {
			wp_safe_redirect( home_url(), 302 );
			exit;
		}
	}
}
