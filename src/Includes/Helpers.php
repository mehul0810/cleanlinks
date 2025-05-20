<?php
/**
 * CleanLinks | Helpers
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

class Helpers {
	/**
	 * Helps cleaning the input data. Prevents XSS.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string|array $input Any type of input data.
	 *
	 * @return string|array Sanitized input data.
	 */
	public static function clean( $input ) {
		if ( is_array( $input ) ) {
			return array_map( [ 'self', 'clean' ], $input );
		} else {
			return is_scalar( $input ) ? sanitize_text_field( $input ) : $input;
		}
	}

	/**
	 * Get total access count based on the post id.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int The number of times the link has been accessed.
	 */
	public static function get_total_access_count( $post_id ) {
		$access_count = get_post_meta( $post_id, 'cleanlink_redirect_count', true );

		return $access_count ?: 0;
	}

	/**
	 * Sanitize and validate a URL
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $url The URL to sanitize and validate.
	 *
	 * @return string|bool The sanitized URL if valid, false otherwise.
	 */
	public static function validate_url( $url ) {
		// Remove all illegal characters from a url
		$url = filter_var( $url, FILTER_SANITIZE_URL );

		// Validate url
		if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return esc_url( $url );
		}

		return false;
	}
}
