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
	 * @return void
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
	 * @return void
	 */
	public static function get_total_access_count( $post_id ) {
		$access_count = get_post_meta( $post_id, 'cleanlink_redirect_count', true );

		return $access_count ?: 0;
	}
}
