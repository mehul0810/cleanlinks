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
			return array_map( [ __CLASS__, 'clean' ], $input );
		} else {
			return is_scalar( $input ) ? sanitize_text_field( $input ) : '';
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
		// Check for cached value first
		$cache_key = 'cleanlink_count_' . $post_id;
		$cached = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$access_count = get_post_meta( $post_id, 'cleanlink_redirect_count', true );
		$count = $access_count ?: 0;

		// Cache the result for 1 hour
		wp_cache_set( $cache_key, $count, '', HOUR_IN_SECONDS );

		return $count;
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
		$url = trim( $url );

		// Validate url
		if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return esc_url( $url );
		}

		return false;
	}
}
