<?php
/**
 * CleanLinks | Input Sanitizer.
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
 * Sanitizes scalar and nested array input for plugin-owned forms.
 *
 * @since 1.1.1
 */
class InputSanitizer {
	/**
	 * Sanitize input data recursively.
	 *
	 * @since 1.1.1
	 *
	 * @param string|array $input Any type of input data.
	 * @return string|array Sanitized input data.
	 */
	public static function sanitize( $input ) {
		if ( is_array( $input ) ) {
			return array_map( array( __CLASS__, 'sanitize' ), $input );
		}

		return is_scalar( $input ) ? sanitize_text_field( $input ) : '';
	}
}
