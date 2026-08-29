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
	 * Meta key used for the persisted access count.
	 *
	 * @var string
	 */
	private const COUNT_META_KEY = 'cleanlink_redirect_count';

	/**
	 * Value used when initializing a missing access-count row.
	 *
	 * @var int
	 */
	private const INITIAL_COUNT = 1;

	/**
	 * Maximum time to wait for the per-link initialization lock.
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT_SECONDS = 2;

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

		$access_count = get_post_meta( $post_id, self::COUNT_META_KEY, true );
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
			return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		}

		/*
		 * Keep the metadata short-circuit contract while avoiding the
		 * read-modify-write race in update_post_meta(). The regular path uses a
		 * single SQL UPDATE, so concurrent requests cannot overwrite one another.
		 */
		if ( ! $this->allows_count_update( $post_id ) ) {
			return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		}

		$updated = $this->increment_persisted_count( $post_id );

		if ( false === $updated ) {
			return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		}

		if ( 0 === $updated && ! $this->initialize_count( $post_id ) ) {
			return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		}

		$this->invalidate_count_caches( $post_id );

		// Read back the stored value so callers never expose an unpersisted count.
		return (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
	}

	/**
	 * Check whether metadata filters allow the count update.
	 *
	 * The direct SQL increment cannot call update_post_meta(), but the
	 * update_post_metadata short-circuit is a supported extension point that
	 * must retain its existing behavior.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return bool True when the count may be incremented.
	 */
	private function allows_count_update( $post_id ) {
		if ( false === has_filter( 'update_post_metadata' ) ) {
			return true;
		}

		$count = (int) get_post_meta( $post_id, self::COUNT_META_KEY, true );
		$check = apply_filters(
			'update_post_metadata',
			null,
			$post_id,
			self::COUNT_META_KEY,
			$count + 1,
			''
		);

		return null === $check;
	}

	/**
	 * Increment every existing count row atomically.
	 *
	 * WordPress post meta is intentionally retained as the storage contract,
	 * while the database performs the arithmetic in one statement. This avoids
	 * two requests reading the same value and subsequently losing an increment.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return int|false Number of changed rows, or false on a database error.
	 */
	private function increment_persisted_count( $post_id ) {
		$meta_rows = null;

		if ( $this->has_count_meta_actions() ) {
			$meta_rows = $this->get_count_meta_rows( $post_id );

			if ( false === $meta_rows ) {
				return false;
			}

			if ( empty( $meta_rows ) ) {
				return 0;
			}

			$this->fire_count_meta_actions( $post_id, $meta_rows, false );
		}

		$updated = $this->execute_atomic_count_update( $post_id );

		if ( false === $updated || 0 === $updated ) {
			return $updated;
		}

		// Match update_metadata() by invalidating the core cache before after-actions.
		wp_cache_delete( $post_id, 'post_meta' );

		if ( null !== $meta_rows ) {
			$this->fire_count_meta_actions( $post_id, $meta_rows, true );
		}

		return $updated;
	}

	/**
	 * Execute the database-side count increment.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return int|false Number of changed rows, or false on a database error.
	 */
	private function execute_atomic_count_update( $post_id ) {
		global $wpdb;

		return $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic increment.
			$wpdb->prepare(
				// The table name is supplied by WordPress; count values use placeholders.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$wpdb->postmeta}
				SET meta_value = CAST(meta_value AS UNSIGNED) + 1
				WHERE post_id = %d AND meta_key = %s",
				$post_id,
				self::COUNT_META_KEY
			)
		);
	}

	/**
	 * Check whether metadata update actions need to be mirrored.
	 *
	 * @since 1.1.1
	 *
	 * @return bool True when a metadata action is registered.
	 */
	private function has_count_meta_actions() {
		return false !== has_action( 'update_post_meta' )
			|| false !== has_action( 'update_postmeta' )
			|| false !== has_action( 'updated_post_meta' )
			|| false !== has_action( 'updated_postmeta' );
	}

	/**
	 * Get count metadata rows for action compatibility.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return array|false Metadata rows, or false on a database error.
	 */
	private function get_count_meta_rows( $post_id ) {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Read metadata rows.
			$wpdb->prepare(
				// The table name is supplied by WordPress; values use placeholders.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT meta_id, meta_value FROM {$wpdb->postmeta}
				WHERE post_id = %d AND meta_key = %s",
				$post_id,
				self::COUNT_META_KEY
			),
			ARRAY_A
		);
	}

	/**
	 * Fire the metadata actions that update_post_meta() would dispatch.
	 *
	 * @since 1.1.1
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $meta_rows Existing metadata rows.
	 * @param bool  $updated   Whether to fire after-update actions.
	 * @return void
	 */
	private function fire_count_meta_actions( $post_id, $meta_rows, $updated ) {
		foreach ( $meta_rows as $meta_row ) {
			$meta_id     = (int) $meta_row['meta_id'];
			$new_count   = (int) $meta_row['meta_value'] + 1;
			$hook        = $updated ? 'updated_post_meta' : 'update_post_meta';
			$legacy_hook = $updated ? 'updated_postmeta' : 'update_postmeta';

			do_action( $hook, $meta_id, $post_id, self::COUNT_META_KEY, $new_count );
			do_action( $legacy_hook, $meta_id, $post_id, self::COUNT_META_KEY, $new_count );
		}
	}

	/**
	 * Initialize a missing count row without racing another first request.
	 *
	 * A normal CleanLinks post may not have a count row until its first
	 * redirect. The advisory lock only runs on that cold path; established links
	 * use the single atomic UPDATE above and do not pay this extra round trip.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return bool True when a count was persisted.
	 */
	private function initialize_count( $post_id ) {
		global $wpdb;

		$lock_name = $this->get_lock_name( $post_id );
		$acquired  = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- First-write lock.
			$wpdb->prepare(
				'SELECT GET_LOCK(%s, %d)',
				$lock_name,
				self::LOCK_TIMEOUT_SECONDS
			)
		);

		if ( 1 !== (int) $acquired ) {
			return false;
		}

		try {
			// Another request may have initialized the row before this lock was acquired.
			$updated = $this->increment_persisted_count( $post_id );

			if ( false === $updated ) {
				return false;
			}

			if ( 0 < $updated ) {
				return true;
			}

			$added = add_post_meta( $post_id, self::COUNT_META_KEY, self::INITIAL_COUNT, true );

			if ( false !== $added ) {
				return true;
			}

			// A concurrent writer outside this lock may have created the row.
			return 0 < $this->increment_persisted_count( $post_id );
		} finally {
			$wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Release lock.
				$wpdb->prepare(
					'SELECT RELEASE_LOCK(%s)',
					$lock_name
				)
			);
		}
	}

	/**
	 * Invalidate WordPress and CleanLinks count caches after a direct update.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function invalidate_count_caches( $post_id ) {
		wp_cache_delete( $post_id, 'post_meta' );
		wp_cache_delete( $this->get_cache_key( $post_id ) );
	}

	/**
	 * Build a database-wide advisory lock name for one CleanLink.
	 *
	 * @since 1.1.1
	 *
	 * @param int $post_id Post ID.
	 * @return string Lock name.
	 */
	private function get_lock_name( $post_id ) {
		global $wpdb;

		$database = defined( 'DB_NAME' ) ? DB_NAME : '';

		return 'cleanlinks_count_' . md5( $database . ':' . $wpdb->postmeta . ':' . (int) $post_id );
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
