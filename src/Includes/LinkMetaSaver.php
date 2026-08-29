<?php
/**
 * CleanLinks | Link Metadata Saver.
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
 * Validates and persists CleanLinks link metadata.
 *
 * @since 1.1.1
 */
class LinkMetaSaver {
	/**
	 * Save metadata for a CleanLinks post.
	 *
	 * @since 1.1.1
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		if ( 'cleanlinks' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if (
			! isset( $_POST['cleanlink_redirect_nonce'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified below
			|| ! $this->verify_nonce( sanitize_text_field( wp_unslash( $_POST['cleanlink_redirect_nonce'] ) ), 'cleanlink-save-redirect-meta' ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified here
		) {
			return;
		}

		$this->save_redirect_url( $post_id );
	}

	/**
	 * Verify a post-update nonce.
	 *
	 * @since 1.1.1
	 *
	 * @param string $nonce_value Nonce value from the form.
	 * @param string $nonce_action Nonce action to verify against.
	 * @return bool True when the nonce is valid.
	 */
	private function verify_nonce( $nonce_value, $nonce_action ) {
		return ! empty( $nonce_value ) && wp_verify_nonce( $nonce_value, $nonce_action );
	}

	/**
	 * Save the redirect metadata for a CleanLinks post.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_redirect_url( $post_id ) {
		// Nonce is already verified in save(). Read only the fields owned by this form.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in save
		$raw_url = isset( $_POST['cleanlink_redirect_url'] ) ? $_POST['cleanlink_redirect_url'] : '';

		// Reject malformed list/object input before it reaches URL validation.
		if ( ! is_string( $raw_url ) || '' === $raw_url ) {
			return;
		}

		$raw_url   = wp_unslash( $raw_url );
		$valid_url = Helpers::validate_url( sanitize_text_field( $raw_url ) );

		if ( $valid_url ) {
			update_post_meta( $post_id, 'cleanlink_redirect_url', $valid_url );
			$nofollow = isset( $_POST['cleanlink_redirect_nofollow'] ) ? '1' : '0';
			update_post_meta( $post_id, 'cleanlink_redirect_nofollow', $nofollow );
			return;
		}

		delete_post_meta( $post_id, 'cleanlink_redirect_url' );
		delete_post_meta( $post_id, 'cleanlink_redirect_nofollow' );
	}
}
