<?php
/**
 * Explicit destination metadata command shared by management adapters.
 *
 * @package CleanLinks
 */

namespace MG\CleanLinks\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns only destination and nofollow; callers cannot mutate arbitrary metadata.
 */
class LinkMetadataCommand {
	/** @var bool Reject command calls made recursively by save hooks. */
	private static $running = false;

	/** @return bool Whether metadata persistence is active. */
	public static function is_running() { return self::$running; }
	/**
	 * Save validated metadata without reading request globals.
	 *
	 * The transport must verify its nonce before calling this application command.
	 * Object authorization is always enforced here, including cron/AJAX callers.
	 *
	 * @param mixed $post_id CleanLinks integer ID.
	 * @param mixed $input Unslashed destination string and boolean nofollow.
	 * @return array|\WP_Error Saved values or field-specific error data.
	 */
	public function execute( $post_id, $input ) {
		if ( self::$running || \MG\CleanLinks\Application\LinkCommands::is_running() ) {
			return $this->error( 'command_busy', 'input' );
		}
		if ( ! is_int( $post_id ) || $post_id < 1 ) {
			return $this->error( 'invalid_id', 'id' );
		}
		$post = get_post( $post_id );
		if ( ! $post || 'cleanlinks' !== $post->post_type ) {
			return $this->error( 'invalid_id', 'id' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->error( 'forbidden', 'id' );
		}
		if ( ! is_array( $input ) || array_diff( array_keys( $input ), array( 'destination', 'nofollow' ) ) ) {
			return $this->error( 'unsupported_input', 'input' );
		}
		if ( ! isset( $input['destination'] ) || ! is_string( $input['destination'] ) ) {
			return $this->error( 'invalid_destination', 'destination' );
		}
		if ( ! isset( $input['nofollow'] ) || ! is_bool( $input['nofollow'] ) ) {
			return $this->error( 'invalid_nofollow', 'nofollow' );
		}
		$destination = UrlValidator::validate( $input['destination'] );
		if ( false === $destination || '' === $destination ) {
			return $this->error( 'unsafe_destination', 'destination' );
		}
		$values = array(
			'cleanlink_redirect_url'      => $destination,
			'cleanlink_redirect_nofollow' => $input['nofollow'] ? '1' : '0',
		);
		self::$running = true;
		$transaction = null;
		$owns_transaction = ! \MG\CleanLinks\Application\LinkCommands::is_running() && \MG\CleanLinks\Application\CommandTransaction::has_request_local_cache();
		// Preserve the legacy editor's ordinary metadata-save behavior when atomic rollback
		// cannot be guaranteed by the configured object cache.
		if ( $owns_transaction ) {
			$transaction = new \MG\CleanLinks\Application\CommandTransaction();
			if ( ! $transaction->begin() ) {
				self::$running = false;
				return $this->error( 'storage_unavailable', 'input' );
			}
		}
		$result = array( 'id' => $post_id, 'destination' => $destination, 'nofollow' => $input['nofollow'] );
		try {
			foreach ( $values as $key => $value ) {
				// wp_slash compensates for WordPress metadata's internal unslashing.
				update_post_meta( $post_id, $key, wp_slash( $value ) );
				// update_post_meta also returns false for unchanged values: read back.
				if ( $value !== get_post_meta( $post_id, $key, true ) ) {
					$result = $this->error( 'persistence_failed', $key );
					break;
				}
			}
		} catch ( \Throwable $exception ) {
			$result = $this->error( 'persistence_failed', 'input' );
		} finally {
			self::$running = false;
			if ( $transaction ) {
				$finished = $transaction->finish( ! is_wp_error( $result ) );
				clean_post_cache( $post_id );
				if ( ! $finished ) {
					$result = $this->error( 'commit_failed', 'input' );
				}
			}
		}
		return $result;
	}

	/**
	 * Create a structured error without leaking rejected input.
	 *
	 * @param string $code Error code.
	 * @param string $field Field name.
	 * @return \WP_Error
	 */
	private function error( $code, $field ) {
		return new \WP_Error( $code, __( 'The link could not be saved.', 'cleanlinks' ), array( 'field' => $field ) );
	}
}
