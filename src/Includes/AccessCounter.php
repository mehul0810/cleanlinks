<?php
/**
 * CleanLinks | Access Counter.
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
 * Owns persistence and caching for cleanlink access counts.
 *
 * @since 1.1.1
 */
class AccessCounter {
	/**
	 * Get the total access count for a cleanlink.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return int The number of times the link has been accessed.
	 */
	public function get_total_access_count( $post_id ) {
		$cache_key = $this->get_cache_key( $post_id );
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$access_count = get_post_meta( $post_id, 'cleanlink_redirect_count', true );
		$count        = $access_count ?: 0;
		wp_cache_set( $cache_key, $count, '', HOUR_IN_SECONDS );

		return $count;
	}

	/**
	 * Increment the access count for a published cleanlink.
	 *
	 * Draft and non-cleanlink posts retain their current count. This keeps the
	 * collaborator safe when it is used outside the template_redirect callback.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return int The current or new count value.
	 */
	public function increment( $post_id ) {
		if ( ! $this->is_published_cleanlink( $post_id ) ) {
			return (int) get_post_meta( $post_id, 'cleanlink_redirect_count', true );
		}

		$count     = (int) get_post_meta( $post_id, 'cleanlink_redirect_count', true );
		$new_count = $count + 1;

		update_post_meta( $post_id, 'cleanlink_redirect_count', $new_count );
		wp_cache_delete( $this->get_cache_key( $post_id ) );

		return $new_count;
	}

	/**
	 * Check whether a post is a published cleanlink.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return bool True when the post is a published cleanlink.
	 */
	private function is_published_cleanlink( $post_id ) {
		return 'cleanlinks' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id );
	}

	/**
	 * Build the cache key used for a cleanlink count.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return string Cache key.
	 */
	private function get_cache_key( $post_id ) {
		return 'cleanlink_count_' . $post_id;
	}
}
