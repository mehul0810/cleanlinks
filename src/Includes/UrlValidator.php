<?php
/**
 * CleanLinks | URL Validator.
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
 * Sanitizes and validates destination URLs.
 *
 * @since 1.1.1
 */
class UrlValidator {
	/**
	 * Sanitize and validate a URL.
	 *
	 * @since 1.1.1
	 *
	 * @param string $url The URL to sanitize and validate.
	 * @return string|bool The sanitized URL if valid, false otherwise.
	 */
	public static function validate( $url ) {
		$url           = trim( $url );
		$validated_url = wp_http_validate_url( $url );

		if ( $validated_url ) {
			return esc_url( $validated_url );
		}

		return false;
	}
}
