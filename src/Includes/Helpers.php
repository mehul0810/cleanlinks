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
		return InputSanitizer::sanitize( $input );
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
		return ( new AccessCounter() )->get_total_access_count( $post_id );
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
		return UrlValidator::validate( $url );
	}
}
