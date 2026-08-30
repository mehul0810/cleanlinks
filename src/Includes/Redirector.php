<?php
/**
 * CleanLinks | Redirector.
 *
 * @package WordPress
 * @subpackage CleanLinks
 * @since 1.1.1
 */

namespace MG\CleanLinks\Includes;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves cleanlink destinations and sends redirect responses.
 *
 * @since 1.1.1
 */
class Redirector {
	/**
	 * Resolve a cleanlink URL and fire its public extension points.
	 *
	 * @since 1.1.1
	 *
	 * @param int      $post_id Post ID.
	 * @param int|null $count   Current access count, when already known.
	 * @return string The filtered redirect URL.
	 */
	public function get_redirect_url( $post_id, $count = null ) {
		$redirect = get_post_meta( $post_id, 'cleanlink_redirect_url', true );

		if ( null === $count ) {
			$count = (int) get_post_meta( $post_id, 'cleanlink_redirect_count', true );
		}

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
	 * Send the appropriate redirect response for a cleanlink.
	 *
	 * @since 1.1.1
	 *
	 * @param string $redirect The URL to redirect to.
	 * @param int    $post_id  The post ID.
	 * @return void
	 */
	public function perform_redirect( $redirect, $post_id ) {
		if ( ! empty( $redirect ) ) {
			$nofollow = get_post_meta( $post_id, 'cleanlink_redirect_nofollow', true );

			$redirected = wp_redirect( esc_url_raw( $redirect ), 301 ); // phpcs:ignore WordPressVIPMinimum.Security.ExitAfterRedirect.NoExit -- The caller exits immediately after this method returns.

			if ( $redirected && '1' === $nofollow ) {
				// Preserve the crawl directive without sending a response body before redirect headers.
				header( 'X-Robots-Tag: nofollow', true );
			}
		} else {
			wp_safe_redirect( home_url(), 302 ); // phpcs:ignore WordPressVIPMinimum.Security.ExitAfterRedirect.NoExit -- The caller exits immediately after this method returns.
		}
	}
}
