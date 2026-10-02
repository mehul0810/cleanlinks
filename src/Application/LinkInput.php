<?php
/**
 * Strict command input validation.
 *
 * @package CleanLinks
 */
namespace MG\CleanLinks\Application;

use MG\CleanLinks\Includes\UrlValidator;

/** Validates only the link fields owned by the application. */
class LinkInput {
	/**
	 * Validate shape, scalar types, destination safety and term IDs before mutation.
	 *
	 * @param mixed $input Unslashed command input.
	 * @return array|\WP_Error
	 */
	public function validate( $input ) {
		$allowed = array( 'id', 'destination', 'title', 'slug', 'status', 'groups', 'nofollow', 'expected_version', 'request_key', 'confirm_slug_change' );
		if ( ! is_array( $input ) || array_diff( array_keys( $input ), $allowed ) ) {
			return self::error( 'unsupported_input', 'input', 400 );
		}
		if ( array_key_exists( 'id', $input ) && ( ! is_int( $input['id'] ) || $input['id'] < 1 ) ) {
			return self::error( 'invalid_id', 'id', 400 );
		}
		foreach ( array( 'destination', 'title', 'slug', 'status', 'expected_version', 'request_key' ) as $field ) {
			if ( array_key_exists( $field, $input ) && ! is_string( $input[ $field ] ) ) {
				return self::error( 'invalid_type', $field, 400 );
			}
		}
		foreach ( array( 'nofollow', 'confirm_slug_change' ) as $field ) {
			if ( array_key_exists( $field, $input ) && ! is_bool( $input[ $field ] ) ) {
				return self::error( 'invalid_type', $field, 400 );
			}
		}
		$issued = ( new CommandReceipts() )->issued_at( isset( $input['request_key'] ) ? $input['request_key'] : null );
		if ( is_wp_error( $issued ) ) {
			return $issued;
		}
		if ( ! isset( $input['id'] ) && ! isset( $input['destination'] ) ) {
			return self::error( 'required', 'destination', 400 );
		}
		if ( isset( $input['destination'] ) ) {
			$input['destination'] = UrlValidator::validate( $input['destination'] );
			if ( false === $input['destination'] || '' === $input['destination'] ) {
				return self::error( 'unsafe_destination', 'destination', 400 );
			}
		}
		if ( isset( $input['title'] ) ) {
			$input['title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['slug'] ) && ( strlen( $input['slug'] ) > 200 || ( '' !== $input['slug'] && sanitize_title( $input['slug'] ) !== $input['slug'] ) ) ) {
			return self::error( 'invalid_slug', 'slug', 400 );
		}
		if ( isset( $input['status'] ) && ! in_array( $input['status'], array( 'draft', 'pending', 'publish', 'private' ), true ) ) {
			return self::error( 'invalid_status', 'status', 400 );
		}
		if ( isset( $input['id'] ) && ( ! isset( $input['expected_version'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $input['expected_version'] ) ) ) {
			return self::error( 'required_version', 'expected_version', 400 );
		}
		if ( array_key_exists( 'groups', $input ) ) {
			if ( ! is_array( $input['groups'] ) || array_values( $input['groups'] ) !== $input['groups'] || count( $input['groups'] ) > 50 ) {
				return self::error( 'invalid_groups', 'groups', 400 );
			}
			foreach ( $input['groups'] as $group ) {
				if ( ! is_int( $group ) || $group < 1 || ! term_exists( $group, 'cleanlinks_groups' ) ) {
					return self::error( 'invalid_groups', 'groups', 400 );
				}
			}
			$input['groups'] = array_values( array_unique( $input['groups'] ) );
			sort( $input['groups'] );
		}
		ksort( $input );
		return $input;
	}

	/**
	 * Return a field-specific public error.
	 *
	 * @param string $code Code.
	 * @param string $field Field.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	public static function error( $code, $field, $status = 409 ) {
		return new \WP_Error( $code, __( 'The link could not be saved. Check the indicated field and try again.', 'cleanlinks' ), array( 'field' => $field, 'status' => $status ) );
	}
}
